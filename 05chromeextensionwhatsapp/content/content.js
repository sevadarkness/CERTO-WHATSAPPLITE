// content/content.js
// WhatsHybrid Lite (Alabama) - Content Script (MV3)
// Estratégia 2024-2025:
// - NÃO depender de APIs internas do WhatsApp Web (window.Store / window.require).
// - Interagir principalmente via DOM (ler mensagens visíveis + simular digitação + clique enviar).
// - Fallback: apenas detectar se Store existe (via injected.js) para debug.
//
// Módulos:
// - Chatbot IA (OpenAI ou Backend)
// - Memória (Leão) por conversa + contexto global do negócio
// - Campanhas: Links (assistido) | DOM (assistido/auto com confirmação) | API (backend)
// - Extração de contatos: leitura de IDs/JIDs no DOM (quando possível) + título/headers

(() => {
  'use strict';

  const EXT = {
    id: 'whl-root',
    name: 'WhatsHybrid Lite',
    version: '0.2.0'
  };

  // -------------------------
  // Utils & Debug
  // -------------------------
  // DEBUG_MODE: Set to true for troubleshooting DOM automation issues
  // NOTE: In production, consider setting to false to reduce console noise
  const DEBUG_MODE = true;
  
  const log = (...args) => console.log('[WhatsHybrid Lite]', ...args);
  const warn = (...args) => console.warn('[WhatsHybrid Lite]', ...args);
  
  function debugLog(...args) {
    if (DEBUG_MODE) {
      console.log('[WHL Debug]', ...args);
    }
  }

  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  function safeText(x) {
    return (x === undefined || x === null) ? '' : String(x);
  }

  function clamp(n, min, max) {
    n = Number(n);
    if (!Number.isFinite(n)) return min;
    return Math.max(min, Math.min(max, n));
  }

  function uniq(arr) {
    return Array.from(new Set(arr.filter(Boolean)));
  }

  function csvEscape(v) {
    const s = safeText(v);
    if (/[",\n]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
    return s;
  }

  async function bg(type, payload) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type, ...(payload || {}) }, (resp) => {
          const err = chrome.runtime.lastError;
          if (err) return resolve({ ok: false, error: err.message || String(err) });
          resolve(resp);
        });
      } catch (e) {
        resolve({ ok: false, error: e?.message || String(e) });
      }
    });
  }

  // -------------------------
  // Smart Cache System
  // -------------------------
  class SmartCache {
    constructor(defaultTTL = 60000) {
      this.cache = new Map();
      this.defaultTTL = defaultTTL;
    }
    
    set(key, value, ttl = this.defaultTTL) {
      this.cache.set(key, { value, expiresAt: Date.now() + ttl });
    }
    
    get(key) {
      const item = this.cache.get(key);
      if (!item) return null;
      if (Date.now() > item.expiresAt) {
        this.cache.delete(key);
        return null;
      }
      return item.value;
    }
    
    has(key) { return this.get(key) !== null; }
    delete(key) { this.cache.delete(key); }
    clear() { this.cache.clear(); }
    
    cleanup() {
      const now = Date.now();
      for (const [key, item] of this.cache.entries()) {
        if (now > item.expiresAt) this.cache.delete(key);
      }
    }
  }

  const whlCache = new SmartCache();
  setInterval(() => whlCache.cleanup(), 120000);

  async function getSettingsCached() {
    // Use SmartCache for settings
    const cached = whlCache.get('settings');
    if (cached) return cached;
    
    const resp = await bg('GET_SETTINGS', {});
    const st = resp?.settings || {};
    whlCache.set('settings', st, 5000);
    return st;
  }

  // -------------------------
  // Inject (fallback detection)
  // -------------------------
  function injectMainWorld() {
    try {
      const s = document.createElement('script');
      s.src = chrome.runtime.getURL('content/injected.js');
      s.async = false;
      (document.head || document.documentElement).appendChild(s);
      s.remove();
    } catch (e) {
      // ignore
    }
  }

  const injectedStatus = { received: false, info: null };
  window.addEventListener('message', (ev) => {
    try {
      if (!ev?.data || ev.data.source !== 'WHL') return;
      if (ev.data.type === 'INJECTED_STATUS') {
        injectedStatus.received = true;
        injectedStatus.info = ev.data.info || null;
        log('Injected status:', injectedStatus.info);
      }
    } catch (_) {}
  });

  injectMainWorld();

  // Listen for messages from background script (scheduled campaigns)
  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type === 'EXECUTE_SCHEDULED_CAMPAIGN') {
      (async () => {
        try {
          const campaign = message.campaign;
          if (!campaign || !campaign.entries || !Array.isArray(campaign.entries)) {
            console.error('[WHL] Invalid scheduled campaign data');
            return;
          }

          log('Executing scheduled campaign:', campaign.id);
          
          // Find the shadow root elements (they should already be mounted)
          const host = document.getElementById(EXT.id);
          if (!host || !host.shadowRoot) {
            console.error('[WHL] Extension UI not mounted');
            return;
          }

          // Execute campaign (reusing the executeDomCampaign logic)
          // We need to store the media payload if present
          let mediaPayload = campaign.media || null;
          
          // Execute the campaign
          await executeDomCampaignDirectly(campaign.entries, campaign.message, mediaPayload);
          
        } catch (e) {
          console.error('[WHL] Error executing scheduled campaign:', e);
        }
      })();
      return true; // Keep channel open for async response
    }

    if (message.type === 'SEND_TEAM_MESSAGES') {
      (async () => {
        try {
          const { members, message: msg, senderName } = message.payload;
          if (!members || !Array.isArray(members) || !msg) {
            console.error('[WHL] Invalid team message data');
            return;
          }

          log('Sending team messages to', members.length, 'members');
          
          // Convert team members to campaign-like entries
          const entries = members.map(m => ({
            name: m.name,
            number: m.phone,
            vars: {}
          }));

          // Execute as a campaign
          await executeDomCampaignDirectly(entries, msg, null);
          
        } catch (e) {
          console.error('[WHL] Error sending team messages:', e);
        }
      })();
      return true; // Keep channel open for async response
    }
  });

  // Helper function to execute campaign directly (used by scheduled campaigns)
  async function executeDomCampaignDirectly(entries, msg, mediaPayload) {
    debugLog('Executing scheduled campaign with', entries.length, 'contacts');
    
    // Use default delays for scheduled campaigns
    const dmin = 8;
    const dmax = 15;

    for (let i = 0; i < entries.length; i++) {
      const e = entries[i];
      debugLog(`[${i+1}/${entries.length}] Processing scheduled:`, e.number);
      
      const text = applyVars(msg || '', e).trim();
      const phoneDigits = e.number.replace(/[^\d]/g, '');

      try {
        debugLog('Opening chat...');
        await openChatBySearch(phoneDigits);
        await sleep(500);
        
        const composer = findComposer();
        if (!composer) {
          throw new Error('Composer not found');
        }

        if (mediaPayload) {
          debugLog('Sending media...');
          await attachMediaAndSend(mediaPayload, text);
          await sleep(500);
          recordMessageSent();
        } else {
          if (!text) throw new Error('Empty message');
          debugLog('Inserting text...');
          await insertIntoComposer(text, false, true);
          await sleep(300);
          await clickSend(true);
          await sleep(500);
        }

        debugLog(`✅ Success for ${e.number}`);
      } catch (err) {
        debugLog(`❌ Error for ${e.number}:`, err);
        console.error(`[WHL] Error for ${e.number}:`, err);
      }

      // Delay between messages
      if (i < entries.length - 1) {
        const delay = (Math.random() * (dmax - dmin) + dmin) * 1000;
        debugLog(`Waiting ${delay/1000}s...`);
        await sleep(delay);
      }
    }

    debugLog('Scheduled campaign completed!');
  }

  // -------------------------
  // WhatsApp DOM helpers
  // -------------------------
  // WA_SELECTORS: Robust selectors with fallback for WhatsApp Web changes (updated 2024/2025)
  const WA_SELECTORS = {
    chatHeader: [
      'header span[title]',
      'header [title]',
      '#main header span[dir="auto"]',
      'header',
      '[data-testid="conversation-header"]',
      '#main header'
    ],
    composer: [
      // New 2024/2025 selectors first (Lexical editor)
      '[data-testid="conversation-compose-box-input"]',
      'footer div[contenteditable="true"][data-lexical-editor="true"]',
      '[data-lexical-editor="true"]',
      'div[contenteditable="true"][data-tab="10"]',
      // Legacy selectors
      'footer [contenteditable="true"][role="textbox"]',
      '#main footer div[contenteditable="true"]',
      'footer div[contenteditable="true"]',
      '#main footer [contenteditable="true"]'
    ],
    sendButton: [
      '[data-testid="compose-btn-send"]',
      'footer button span[data-icon="send"]',
      'footer button span[data-icon="send-light"]',
      'button span[data-icon="send"]',
      'button[aria-label="Enviar"]',
      'button[aria-label="Send"]',
      'footer button[data-testid="compose-btn-send"]',
      'footer button[aria-label*="Enviar"]',
      'footer button[aria-label*="Send"]'
    ],
    attachButton: [
      'footer button[aria-label*="Anexar"]',
      'footer button[title*="Anexar"]',
      'footer span[data-icon="attach-menu-plus"]',
      'footer span[data-icon="clip"]',
      'footer span[data-icon="attach"]'
    ],
    searchBox: [
      // Novos seletores 2024/2025 - testados e funcionando
      '[contenteditable="true"][data-tab="3"]',
      'div[role="textbox"][data-tab="3"]',
      '#side div[contenteditable="true"]',
      'div[aria-label="Caixa de texto de pesquisa"]',
      'div[aria-label="Search input textbox"]',
      // Seletores antigos como fallback
      '[data-testid="chat-list-search"]',
      '[data-testid="chat-list-search"] div[contenteditable="true"]',
      '#pane-side div[contenteditable="true"]'
    ],
    searchResults: [
      '[data-testid="cell-frame-container"]',
      '#pane-side [role="listitem"]',
      '#pane-side [role="row"]',
      '[data-testid="chat-list"] [role="row"]'
    ],
    messagesContainer: [
      '[data-testid="conversation-panel-messages"]',
      '#main div[role="application"]',
      '#main'
    ],
    messageNodes: [
      'div[data-pre-plain-text]',
      '[data-testid="msg-container"]'
    ],
    chatList: [
      '#pane-side [role="row"]',
      '[data-testid="chat-list"] [role="row"]',
      '[data-testid="chat-list"] [role="listitem"]'
    ],
    dialogRoot: [
      'div[role="dialog"]',
      '[data-testid="media-viewer"]',
      '[data-testid="popup"]'
    ]
  };

  // querySelector with fallback support
  function querySelector(selectors) {
    const selectorList = Array.isArray(selectors) ? selectors : [selectors];
    for (const sel of selectorList) {
      try {
        const el = document.querySelector(sel);
        if (el && el.isConnected) return el;
      } catch (e) {}
    }
    return null;
  }

  // querySelectorAll with fallback support
  function querySelectorAll(selectors) {
    const selectorList = Array.isArray(selectors) ? selectors : [selectors];
    const results = [];
    for (const sel of selectorList) {
      try {
        const els = document.querySelectorAll(sel);
        for (const el of els) {
          if (el && el.isConnected && !results.includes(el)) {
            results.push(el);
          }
        }
      } catch (e) {}
    }
    return results;
  }

  // findElement with visibility check - uses WA_SELECTORS keys
  function findElement(selectorKey, parent = document) {
    const selectors = WA_SELECTORS[selectorKey];
    if (!selectors) return null;
    
    for (const sel of selectors) {
      try {
        const el = parent.querySelector(sel);
        if (el && el.isConnected && (el.offsetWidth || el.offsetHeight || el.getClientRects().length)) {
          return el;
        }
      } catch (e) {}
    }
    return null;
  }

  // findElementWithRetry - retry finding element with delays
  async function findElementWithRetry(selectorKey, maxAttempts = 10, delayMs = 300) {
    for (let i = 0; i < maxAttempts; i++) {
      const el = findElement(selectorKey);
      if (el) return el;
      await sleep(delayMs);
    }
    return null;
  }

  function getChatTitle() {
    // best-effort: WhatsApp changes DOM often
    const header = querySelector(WA_SELECTORS.chatHeader);
    if (!header) return 'chat_desconhecido';
    const span = header.querySelector('span[title]') || header.querySelector('[title]');
    const title = span?.getAttribute('title') || span?.textContent || '';
    return title.trim() || 'chat_desconhecido';
  }

  function getVisibleTranscript(limit = 25) {
    // WhatsApp often uses data-pre-plain-text attribute in message nodes.
    const nodes = Array.from(document.querySelectorAll('div[data-pre-plain-text]'));
    const slice = nodes.slice(Math.max(0, nodes.length - limit));
    const lines = [];
    for (const node of slice) {
      const txt =
        node.querySelector('span.selectable-text')?.innerText ||
        node.querySelector('span[dir="ltr"]')?.innerText ||
        node.innerText ||
        '';
      const clean = safeText(txt).replace(/\s+\n/g, '\n').trim();
      if (!clean) continue;
      const who = node.closest('.message-out') ? 'EU' : (node.closest('.message-in') ? 'CONTATO' : 'MSG');
      lines.push(`${who}: ${clean}`);
    }
    return lines.join('\n');
  }

  function findComposer() {
    // Try new findElement helper first (with visibility check)
    const el = findElement('composer');
    if (el) return el;
    
    // Fallback to original implementation
    const cands = querySelectorAll(WA_SELECTORS.composer).filter(el => el && el.isConnected);
    if (!cands.length) return null;
    const visible = cands.find(el => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    return visible || cands[0];
  }

  // -------------------------
  // Stealth Mode (Human Behavior Simulation)
  // -------------------------
  const STEALTH_CONFIG = {
    typingDelayMin: 30,
    typingDelayMax: 120,
    beforeSendDelayMin: 200,
    beforeSendDelayMax: 800,
    delayVariation: 0.3,
    humanHoursStart: 7,
    humanHoursEnd: 22,
    maxMessagesPerHour: 30,
    randomLongPauseChance: 0.05,
    randomLongPauseMin: 30000,
    randomLongPauseMax: 120000
  };

  function randomBetween(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  function isHumanHour() {
    const hour = new Date().getHours();
    return hour >= STEALTH_CONFIG.humanHoursStart && hour < STEALTH_CONFIG.humanHoursEnd;
  }

  const messageTimestamps = [];
  function checkRateLimit() {
    const oneHourAgo = Date.now() - 3600000;
    while (messageTimestamps.length && messageTimestamps[0] < oneHourAgo) {
      messageTimestamps.shift();
    }
    return messageTimestamps.length < STEALTH_CONFIG.maxMessagesPerHour;
  }

  function recordMessageSent() {
    messageTimestamps.push(Date.now());
  }

  async function humanType(element, text) {
    element.focus();
    document.execCommand('selectAll', false, null);
    await sleep(randomBetween(50, 150));
    
    for (let i = 0; i < text.length; i++) {
      const delay = randomBetween(STEALTH_CONFIG.typingDelayMin, STEALTH_CONFIG.typingDelayMax);
      await sleep(delay);
      document.execCommand('insertText', false, text[i]);
      
      if (Math.random() < 0.02) {
        await sleep(randomBetween(300, 800));
      }
    }
    
    element.dispatchEvent(new InputEvent('input', { bubbles: true }));
  }

  async function maybeRandomLongPause() {
    if (Math.random() < STEALTH_CONFIG.randomLongPauseChance) {
      const pause = randomBetween(STEALTH_CONFIG.randomLongPauseMin, STEALTH_CONFIG.randomLongPauseMax);
      await sleep(pause);
      return true;
    }
    return false;
  }

  // Humanized typing for stealth mode (original implementation - keeping for compatibility)
  async function humanizedType(box, text, minDelay = 30, maxDelay = 80) {
    box.focus();
    for (const char of text) {
      try {
        document.execCommand('insertText', false, char);
      } catch (_) {
        box.textContent += char;
      }
      box.dispatchEvent(new InputEvent('input', { bubbles: true }));
      const delay = Math.floor(Math.random() * (maxDelay - minDelay)) + minDelay;
      await sleep(delay);
    }
  }

  async function insertIntoComposer(text, humanized = false, stealthMode = false) {
    const box = findComposer();
    if (!box) {
      debugLog('Composer não encontrado. Seletores tentados:', WA_SELECTORS.composer);
      throw new Error('Não encontrei a caixa de mensagem do WhatsApp.');
    }
    
    debugLog('Composer encontrado:', box);
    const t = safeText(text);
    if (!t) {
      debugLog('Texto vazio fornecido');
      throw new Error('Mensagem vazia.');
    }
    
    debugLog('Tentando inserir texto:', t.slice(0, 50) + (t.length > 50 ? '...' : ''));

    if (stealthMode) {
      // Enhanced stealth mode with full human simulation
      debugLog('Modo stealth ativado - digitação humanizada');
      await humanType(box, t);
      return true;
    }

    if (humanized) {
      // Clear existing content first
      debugLog('Modo humanizado ativado');
      try {
        document.execCommand('selectAll', false, null);
        document.execCommand('delete', false, null);
      } catch (_) {
        box.textContent = '';
      }
      await humanizedType(box, t);
      return true;
    }

    // Fast mode with multiple fallback methods
    // Focar no elemento e limpar conteúdo existente
    box.focus();
    await sleep(100);
    
    // Limpar qualquer texto existente primeiro
    try {
      document.execCommand('selectAll', false, null);
      document.execCommand('delete', false, null);
      await sleep(50);
    } catch (_) {}

    // Método 1: execCommand (funciona na maioria dos casos)
    debugLog('Método 1: Tentando execCommand...');
    try {
      document.execCommand('insertText', false, t);
      box.dispatchEvent(new InputEvent('input', { bubbles: true }));
      
      // Verificar se texto foi inserido (com validação mais rigorosa)
      const inserted = box.textContent || box.innerText || '';
      if (inserted.trim() === t.trim() || inserted.includes(t.slice(0, Math.min(20, t.length)))) {
        debugLog('✅ execCommand funcionou');
        return true;
      }
      debugLog('⚠️ execCommand não inseriu o texto corretamente');
    } catch (e) {
      debugLog('❌ execCommand falhou:', e);
    }

    // Método 2: Clipboard API (fallback)
    debugLog('Método 2: Tentando Clipboard API...');
    try {
      // Limpar antes de tentar clipboard
      box.textContent = '';
      await sleep(50);
      
      await navigator.clipboard.writeText(t);
      document.execCommand('paste');
      box.dispatchEvent(new InputEvent('input', { bubbles: true }));
      await sleep(100);
      
      const inserted = box.textContent || box.innerText || '';
      if (inserted.trim() === t.trim() || inserted.includes(t.slice(0, Math.min(20, t.length)))) {
        debugLog('✅ Clipboard API funcionou');
        return true;
      }
      debugLog('⚠️ Clipboard API não inseriu o texto corretamente');
    } catch (e) {
      debugLog('❌ Clipboard API falhou:', e);
    }

    // Método 3: Keyboard events (último recurso)
    debugLog('Método 3: Tentando textContent direto...');
    try {
      box.textContent = t;
      box.dispatchEvent(new InputEvent('input', { bubbles: true }));
      box.dispatchEvent(new Event('change', { bubbles: true }));
      debugLog('✅ textContent aplicado');
      return true;
    } catch (e) {
      debugLog('❌ textContent falhou:', e);
    }

    throw new Error('Não consegui inserir texto no composer (todos os métodos falharam).');
  }

  function findSendButton() {
    // Try new findElement helper first (with visibility check)
    const el = findElement('sendButton');
    if (el) return el;
    
    // Fallback to original implementation
    return querySelector(WA_SELECTORS.sendButton);
  }

  async function clickSend(stealthMode = false) {
    if (stealthMode) {
      // Add natural delay before sending in stealth mode
      const delay = randomBetween(STEALTH_CONFIG.beforeSendDelayMin, STEALTH_CONFIG.beforeSendDelayMax);
      debugLog(`Stealth mode: aguardando ${delay}ms antes de enviar`);
      await sleep(delay);
      
      // Check rate limit
      if (!checkRateLimit()) {
        throw new Error('Rate limit atingido. Aguarde para enviar mais mensagens.');
      }
    }
    
    // Tentar encontrar botão de enviar
    debugLog('Procurando botão de enviar...');
    let btn = findSendButton();
    
    if (!btn) {
      debugLog('Botão de enviar não encontrado via findSendButton, tentando fallback...');
      // Fallback: buscar por ícone send
      const sendIcon = document.querySelector('span[data-icon="send"], span[data-icon="send-light"]');
      if (sendIcon) {
        btn = sendIcon.closest('button') || sendIcon.parentElement;
        debugLog('Botão encontrado via ícone send');
      }
    }

    if (!btn) {
      debugLog('Botão não encontrado, tentando Enter key como último recurso...');
      // Último fallback: Enter key
      const composer = findComposer();
      if (composer) {
        composer.dispatchEvent(new KeyboardEvent('keydown', {
          key: 'Enter',
          code: 'Enter',
          keyCode: 13,
          which: 13,
          bubbles: true
        }));
        
        debugLog('✅ Enter key enviado ao composer');
        
        if (stealthMode) {
          recordMessageSent();
          await maybeRandomLongPause();
        }
        return true;
      }
      
      debugLog('❌ Nem botão nem composer encontrados');
      throw new Error('Não encontrei o botão ENVIAR nem o composer para simular Enter.');
    }

    debugLog('Clicando no botão de enviar:', btn);
    btn.click();
    
    if (stealthMode) {
      recordMessageSent();
      // Maybe add a random long pause after sending
      await maybeRandomLongPause();
    }
    
    debugLog('✅ Mensagem enviada com sucesso');
    return true;
  }

  function b64ToBytes(b64) {
    const s = safeText(b64).replace(/\s+/g, '');
    const bin = atob(s);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return bytes;
  }

  function findAttachButton() {
    const btn = querySelector(WA_SELECTORS.attachButton);
    return btn?.closest('button') || btn;
  }

  function findBestFileInput() {
    const inputs = Array.from(document.querySelectorAll('input[type="file"]'))
      .filter(el => el && el.isConnected);
    if (!inputs.length) return null;

    // Prefer image accept
    const img = inputs.find(i => safeText(i.accept).includes('image'));
    return img || inputs[0];
  }

  function findDialogRoot() {
    return querySelector(WA_SELECTORS.dialogRoot);
  }

  function findMediaCaptionBox() {
    const dlg = findDialogRoot();
    if (!dlg) return null;

    const box =
      dlg.querySelector('[contenteditable="true"][role="textbox"]') ||
      dlg.querySelector('div[contenteditable="true"][data-tab]') ||
      null;

    if (box && box.closest('footer')) return null;
    return box;
  }

  function findMediaSendButton() {
    const dlg = findDialogRoot();
    if (!dlg) return null;

    const btn =
      dlg.querySelector('button[aria-label*="Enviar"]') ||
      dlg.querySelector('button[aria-label*="Send"]') ||
      dlg.querySelector('button span[data-icon="send"]')?.closest('button') ||
      dlg.querySelector('button span[data-icon="send-light"]')?.closest('button') ||
      null;

    if (btn && btn.closest('footer')) return null;
    return btn;
  }

  async function attachMediaAndSend(mediaPayload, captionText) {
    if (!mediaPayload?.base64) throw new Error('Mídia não carregada.');
    const attachBtn = findAttachButton();
    if (!attachBtn) throw new Error('Não encontrei o botão de anexo (📎).');

    attachBtn.click();
    await sleep(450);

    const input = findBestFileInput();
    if (!input) throw new Error('Não encontrei o input de arquivo do WhatsApp.');

    const bytes = b64ToBytes(mediaPayload.base64);
    const blob = new Blob([bytes], { type: mediaPayload.type || 'image/*' });
    const file = new File([blob], mediaPayload.name || 'image', { type: blob.type });

    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));

    // Wait preview/dialog
    let sendBtn = null;
    for (let i = 0; i < 40; i++) {
      await sleep(250);
      sendBtn = findMediaSendButton();
      if (sendBtn) break;
    }
    if (!sendBtn) throw new Error('Preview de mídia não apareceu (botão enviar não encontrado).');

    // Caption
    const cap = safeText(captionText).trim();
    if (cap) {
      const box = findMediaCaptionBox();
      if (box) {
        box.focus();
        try {
          document.execCommand('selectAll', false, null);
          document.execCommand('insertText', false, cap);
          box.dispatchEvent(new InputEvent('input', { bubbles: true }));
        } catch (_) {
          box.textContent = cap;
          box.dispatchEvent(new InputEvent('input', { bubbles: true }));
        }
      }
    }

    await sleep(120);
    sendBtn.click();
    await sleep(900);
    return true;
  }


  async function copyToClipboard(text) {
    const t = safeText(text);
    if (!t) return;
    try {
      await navigator.clipboard.writeText(t);
      return true;
    } catch (e) {
      // fallback
      const ta = document.createElement('textarea');
      ta.value = t;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
      return true;
    }
  }

  function parseNumbersFromText(text) {
    const t = safeText(text);
    const nums = [];
    // +55 11 99999-9999 or 5511999999999 etc.
    const re = /(\+?\d[\d\s().-]{6,}\d)/g;
    for (const m of t.matchAll(re)) {
      const raw = m[1];
      let digits = raw.replace(/[^\d+]/g, '');
      if (!digits) continue;
      // normalize: keep leading + if present, else add +
      if (!digits.startsWith('+')) digits = '+' + digits;
      // minimal length
      if (digits.replace(/\D/g, '').length < 10) continue;
      nums.push(digits);
    }
    return nums;
  }

  function extractJidsFromDom() {
    // Try to extract phone numbers from JIDs present in attributes.
    // Common forms: 5511999999999@c.us , 5511999999999@s.whatsapp.net , true_5511999999999@s.whatsapp.net
    const found = [];
    const els = document.querySelectorAll('[data-id],[id],[href],[data-testid],[aria-label]');
    const attrs = ['data-id', 'id', 'href', 'data-testid', 'aria-label'];

    for (const el of els) {
      for (const a of attrs) {
        const v = el.getAttribute?.(a);
        if (!v) continue;
        const s = String(v);
        const m = s.match(/(\d{7,})@(?:c\.us|s\.whatsapp\.net)/);
        if (m && m[1]) {
          found.push('+' + m[1]);
        }
        // Some IDs have true_ prefix
        const m2 = s.match(/true_(\d{7,})@/);
        if (m2 && m2[1]) {
          found.push('+' + m2[1]);
        }
      }
    }
    return found;
  }

  async function openChatBySearch(query) {
    // Best-effort. Works inside SAME WhatsApp Web tab. No window.Store / no links.
    const q = safeText(query).replace(/[^\d+]/g, '');
    const digits = q.replace(/[^\d]/g, '');
    
    debugLog('openChatBySearch: query original:', query);
    debugLog('openChatBySearch: dígitos extraídos:', digits);
    
    if (!digits || digits.length < 8) {
      debugLog('❌ Número inválido (muito curto):', digits);
      throw new Error('Número inválido.');
    }

    // Encontrar caixa de busca
    debugLog('Procurando caixa de busca...');
    const box = findElement('searchBox');
    
    if (!box) {
      debugLog('❌ Caixa de busca não encontrada. Seletores tentados:', WA_SELECTORS.searchBox);
      throw new Error('Caixa de busca não encontrada.');
    }
    
    debugLog('✅ Caixa de busca encontrada:', box);

    // Limpar busca anterior
    debugLog('Limpando busca anterior...');
    box.focus();
    await sleep(200);
    document.execCommand('selectAll', false, null);
    document.execCommand('insertText', false, '');
    box.dispatchEvent(new InputEvent('input', { bubbles: true }));
    await sleep(500);

    // Digitar número
    debugLog('Digitando número na busca:', digits);
    box.focus();
    document.execCommand('selectAll', false, null);
    document.execCommand('insertText', false, digits);
    box.dispatchEvent(new InputEvent('input', { bubbles: true }));

    // Esperar resultados com mais tempo
    debugLog('Aguardando resultados da busca...');
    await sleep(2000);

    const isVisible = (el) => !!(el && el.isConnected && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));

    // Buscar resultados
    debugLog('Procurando resultados...');
    const rows = querySelectorAll(WA_SELECTORS.searchResults).filter(el => {
      const text = (el.innerText || '').replace(/\D/g, '');
      const match = text.includes(digits.slice(-6)) || digits.includes(text.slice(-6));
      if (match) debugLog('Resultado encontrado:', el.innerText?.slice(0, 50));
      return match;
    });

    debugLog(`Encontrados ${rows.length} resultados correspondentes`);

    if (!rows.length) {
      debugLog('Nenhum resultado exato, tentando clicar no primeiro disponível...');
      // Tentar clicar no primeiro resultado disponível
      const anyRow = querySelector(WA_SELECTORS.searchResults);
      if (anyRow) {
        debugLog('Clicando no primeiro resultado:', anyRow.innerText?.slice(0, 50));
        anyRow.click();
        await sleep(1000);
      } else {
        debugLog('❌ Nenhum resultado na busca');
        throw new Error('Nenhum resultado na busca.');
      }
    } else {
      debugLog('Clicando no melhor resultado...');
      rows[0].click();
      await sleep(1000);
    }

    // Limpar busca
    debugLog('Limpando caixa de busca...');
    try {
      const searchBox = findElement('searchBox');
      if (searchBox) {
        searchBox.focus();
        document.execCommand('selectAll', false, null);
        document.execCommand('insertText', false, '');
        searchBox.dispatchEvent(new InputEvent('input', { bubbles: true }));
      }
    } catch (e) {
      debugLog('Erro ao limpar busca (não crítico):', e);
    }

    // Verificar se composer apareceu
    debugLog('Verificando se composer apareceu...');
    for (let i = 0; i < 20; i++) {
      await sleep(300);
      if (findComposer()) {
        debugLog('✅ Chat aberto com sucesso (composer encontrado)');
        return true;
      }
    }
    
    debugLog('❌ Chat não abriu (composer não encontrado após 20 tentativas)');
    throw new Error('Chat não abriu (composer não encontrado).');
  }

  // -------------------------
  // Memory (Leão) store
  // -------------------------
  async function getMemory(chatKey) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_memories'], (res) => {
        const mems = res?.whl_memories || {};
        resolve(mems[chatKey] || null);
      });
    });
  }

  async function setMemory(chatKey, memoryObj) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_memories'], (res) => {
        const mems = res?.whl_memories || {};
        
        // Limitar tamanho do summary a 2000 caracteres
        const summary = memoryObj?.summary || '';
        const truncatedSummary = summary.length > 2000 ? summary.slice(0, 2000) + '...' : summary;
        
        mems[chatKey] = { 
          ...(memoryObj || {}), 
          summary: truncatedSummary,
          updatedAt: new Date().toISOString() 
        };
        
        // Manter apenas as 100 memórias mais recentes
        const keys = Object.keys(mems);
        if (keys.length > 100) {
          const sorted = keys.sort((a, b) => {
            const dateA = new Date(mems[a]?.updatedAt || 0);
            const dateB = new Date(mems[b]?.updatedAt || 0);
            return dateB - dateA;
          });
          const toKeep = sorted.slice(0, 100);
          const newMems = {};
          for (const k of toKeep) {
            newMems[k] = mems[k];
          }
          Object.assign(mems, newMems);
          for (const k of keys) {
            if (!toKeep.includes(k)) delete mems[k];
          }
        }
        
        chrome.storage.local.set({ whl_memories: mems }, async () => {
          try {
            await bg('MEMORY_PUSH', { event: { type: 'chat_memory', chatTitle: chatKey, memory: mems[chatKey] } });
          } catch (e) {}
          resolve(true);
        });
      });
    });
  }

  // Training examples (few-shot)
  async function getExamples() {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_examples'], (res) => {
        resolve(Array.isArray(res?.whl_examples) ? res.whl_examples : []);
      });
    });
  }

  async function addExample(example) {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_examples'], (res) => {
        const arr = Array.isArray(res?.whl_examples) ? res.whl_examples : [];
        arr.unshift({ ...example, at: new Date().toISOString() });
        const trimmed = arr.slice(0, 60);
        chrome.storage.local.set({ whl_examples: trimmed }, async () => {
          try {
            await bg('MEMORY_PUSH', { event: { type: 'example', example: trimmed[0] } });
          } catch (e) {}
          resolve(true);
        });
      });
    });
  }

  // -------------------------
  // Campaign Storage (Persistência de campanhas)
  // -------------------------
  const CampaignStorage = {
    KEY: 'whl_campaign_state',
    
    async save(state) {
      return new Promise((resolve) => {
        chrome.storage.local.set({ [this.KEY]: state }, () => resolve(true));
      });
    },
    
    async load() {
      return new Promise((resolve) => {
        chrome.storage.local.get([this.KEY], (res) => {
          resolve(res?.[this.KEY] || null);
        });
      });
    },
    
    async clear() {
      return new Promise((resolve) => {
        chrome.storage.local.remove([this.KEY], () => resolve(true));
      });
    }
  };

  // Campaign persistence wrapper functions
  async function saveCampaignState(state) {
    await chrome.storage.local.set({ 'whl_campaign_active': state });
  }

  async function loadCampaignState() {
    const result = await chrome.storage.local.get(['whl_campaign_active']);
    return result.whl_campaign_active || null;
  }

  async function clearCampaignState() {
    await chrome.storage.local.remove(['whl_campaign_active']);
  }

  async function saveCampaignToHistory(campaign) {
    const result = await chrome.storage.local.get(['whl_campaign_history']);
    const history = result.whl_campaign_history || [];
    history.unshift({
      id: campaign.id,
      createdAt: campaign.createdAt,
      completedAt: new Date().toISOString(),
      stats: campaign.progress,
      message: (campaign.config?.message || '').slice(0, 50) + '...'
    });
    await chrome.storage.local.set({ 'whl_campaign_history': history.slice(0, 20) });
  }

  // -------------------------
  // Knowledge Management (Training Tab)
  // -------------------------
  const defaultKnowledge = {
    business: {
      name: '',
      description: '',
      segment: '',
      hours: ''
    },
    policies: {
      payment: '',
      delivery: '',
      returns: ''
    },
    products: [],
    faq: [],
    cannedReplies: [],
    documents: [],
    tone: {
      style: 'informal',
      useEmojis: true,
      greeting: '',
      closing: ''
    }
  };

  const defaultTrainingStats = {
    good: 0,
    bad: 0,
    corrected: 0
  };

  async function getKnowledge() {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_knowledge'], (res) => {
        resolve(res?.whl_knowledge || defaultKnowledge);
      });
    });
  }

  async function saveKnowledge(knowledge) {
    return new Promise((resolve) => {
      chrome.storage.local.set({ 'whl_knowledge': knowledge }, () => {
        resolve();
      });
    });
  }

  async function getTrainingStats() {
    return new Promise((resolve) => {
      chrome.storage.local.get(['whl_training_stats'], (res) => {
        resolve(res?.whl_training_stats || defaultTrainingStats);
      });
    });
  }

  async function saveTrainingStats(stats) {
    return new Promise((resolve) => {
      chrome.storage.local.set({ 'whl_training_stats': stats }, () => {
        resolve();
      });
    });
  }

  function parseProductsCSV(csvText) {
    const lines = csvText.split('\n').filter(l => l.trim());
    const products = [];
    for (const line of lines.slice(1)) { // skip header
      const [name, price, stock, description] = line.split(',').map(s => s.trim());
      if (name) {
        products.push({
          id: Date.now() + Math.random(),
          name,
          price: parseFloat(price) || 0,
          stock: parseInt(stock) || 0,
          description: description || ''
        });
      }
    }
    return products;
  }

  function checkCannedReply(message, cannedReplies) {
    const msgLower = message.toLowerCase();
    for (const canned of cannedReplies) {
      for (const trigger of canned.triggers) {
        if (msgLower.includes(trigger.toLowerCase())) {
          return canned.reply;
        }
      }
    }
    return null;
  }

  // -------------------------
  // AI prompting
  // -------------------------
  async function buildSystemPrompt({ persona, businessContext }) {
    const base =
`Você é um assistente de atendimento no WhatsApp.
Objetivo: responder rápido, claro, profissional e humano, sem inventar informações.

Regras:
- Nunca invente dados (preços, prazos, políticas). Se não souber, pergunte ou diga "não tenho essa informação".
- Não peça dados sensíveis desnecessários.
- Seja direto e útil. Use linguagem natural em pt-BR.
- Se houver contexto do negócio, use como verdade.`;

    const p = safeText(persona).trim();
    const ctx = safeText(businessContext).trim();

    // Load knowledge from training tab
    const knowledge = await getKnowledge();
    
    let knowledgeText = '';
    
    if (knowledge.business.name) {
      knowledgeText += `\nNEGÓCIO: ${knowledge.business.name}`;
      if (knowledge.business.description) knowledgeText += `\n${knowledge.business.description}`;
      if (knowledge.business.segment) knowledgeText += `\nSegmento: ${knowledge.business.segment}`;
      if (knowledge.business.hours) knowledgeText += `\nHorário: ${knowledge.business.hours}`;
    }
    
    if (knowledge.products.length) {
      knowledgeText += `\n\nPRODUTOS DISPONÍVEIS:`;
      for (const p of knowledge.products.slice(0, 20)) {
        const stockText = p.stock > 0 ? `${p.stock} em estoque` : 'ESGOTADO';
        knowledgeText += `\n- ${p.name}: R$${p.price.toFixed(2)} (${stockText})`;
        if (p.description) knowledgeText += ` - ${p.description}`;
      }
    }
    
    if (knowledge.faq.length) {
      knowledgeText += `\n\nFAQ:`;
      for (const f of knowledge.faq.slice(0, 10)) {
        knowledgeText += `\nP: ${f.question}\nR: ${f.answer}`;
      }
    }
    
    if (knowledge.policies.payment || knowledge.policies.delivery || knowledge.policies.returns) {
      knowledgeText += `\n\nPOLÍTICAS:`;
      if (knowledge.policies.payment) knowledgeText += `\nPagamento: ${knowledge.policies.payment}`;
      if (knowledge.policies.delivery) knowledgeText += `\nEntrega: ${knowledge.policies.delivery}`;
      if (knowledge.policies.returns) knowledgeText += `\nTrocas: ${knowledge.policies.returns}`;
    }
    
    if (knowledge.tone.style) {
      knowledgeText += `\n\nTOM: Use linguagem ${knowledge.tone.style}.`;
      if (knowledge.tone.useEmojis) knowledgeText += ` Use emojis moderadamente.`;
      if (knowledge.tone.greeting) knowledgeText += ` Saudação: "${knowledge.tone.greeting}"`;
      if (knowledge.tone.closing) knowledgeText += ` Despedida: "${knowledge.tone.closing}"`;
    }

    return [
      base,
      p ? `\nPERSONA (regras extras):\n${p}` : '',
      ctx ? `\nCONTEXTO DO NEGÓCIO (conhecimento):\n${ctx}` : '',
      knowledgeText
    ].filter(Boolean).join('\n');
  }

  function pickExamples(examples, transcript, max = 3) {
    // Very simple relevance: keyword overlap (lightweight, no embeddings).
    const t = transcript.toLowerCase();
    const scored = examples.map((ex) => {
      const u = safeText(ex?.user || '').toLowerCase();
      let score = 0;
      for (const w of u.split(/\W+/).filter(x => x.length >= 4).slice(0, 18)) {
        if (t.includes(w)) score += 1;
      }
      return { ex, score };
    }).sort((a,b) => b.score - a.score);
    return scored.filter(s => s.score > 0).slice(0, max).map(s => s.ex);
  }

  async function getHybridContext({ chatTitle, transcript }) {
    const settings = await getSettingsCached();
    const localMemory = await getMemory(chatTitle);
    const localExamples = await getExamples();

    if (settings?.memorySyncEnabled && settings?.memoryServerUrl && settings?.memoryWorkspaceKey) {
      try {
        const r = await bg('MEMORY_QUERY', { payload: { chatTitle, transcript, topK: 4 } });
        if (r?.ok && r?.data) {
          const d = r.data || {};
          const memory = d.memory || localMemory;
          const examples = Array.isArray(d.examples) ? d.examples : localExamples;
          const context = d.context || null;
          return { memory, examples, context, source: 'server' };
        }
      } catch (e) {}
    }

    return { memory: localMemory, examples: localExamples, context: null, source: 'local' };
  }

  async function aiChat({ mode, extraInstruction, transcript, memory, chatTitle, examplesOverride, contextOverride }) {
    const settings = await getSettingsCached();
    const systemBase = await buildSystemPrompt({ persona: settings.persona, businessContext: settings.businessContext });
    const system = systemBase + (contextOverride?.additions ? `\n\nCONTEXTO (Servidor):\n${safeText(contextOverride.additions)}` : '');

    const memText = memory?.summary ? `\n\nMEMÓRIA (Leão) deste contato:\n${memory.summary}` : '';
    const action =
      mode === 'summary' ? 'Resuma a conversa em tópicos curtos.' :
      mode === 'followup' ? 'Sugira próximos passos claros e objetivos.' :
      mode === 'train' ? 'Gere melhorias para o atendimento (ver instruções).' :
      'Escreva uma sugestão de resposta pronta para eu enviar, mantendo tom premium e humano.';

    let user = `CHAT: ${chatTitle}\n\nCONVERSA (mais recente por último):\n${transcript || '(não consegui ler mensagens)'}${memText}\n\nTAREFA:\n${action}\n`;

    if (mode === 'train') {
      user += `\nINSTRUÇÕES DO MODO TREINO:\n- Analise a conversa e proponha melhorias.\n- Retorne em JSON com chaves: knowledge_additions (array de strings), canned_replies (array de {trigger, reply}), questions_to_clarify (array), risks (array).\n- Não invente informações do negócio.\n`;
    } else {
      const extra = safeText(extraInstruction).trim();
      if (extra) user += `\nINSTRUÇÃO EXTRA:\n${extra}\n`;
      user += `\nResponda SOMENTE com o texto final pronto para enviar.`;
    }

    const examples = Array.isArray(examplesOverride) ? examplesOverride : await getExamples();
    const picked = pickExamples(examples, transcript, 3);

    const messages = [{ role: 'system', content: system }];

    for (const ex of picked) {
      if (safeText(ex?.user).trim() && safeText(ex?.assistant).trim()) {
        messages.push({ role: 'user', content: safeText(ex.user).trim() });
        messages.push({ role: 'assistant', content: safeText(ex.assistant).trim() });
      }
    }

    messages.push({ role: 'user', content: user });

    const contactPhone = (parseNumbersFromText(chatTitle)[0] || parseNumbersFromText(transcript || '')[0] || '').trim();

    const payload = {
      messages,
      model: settings.openaiModel,
      temperature: settings.temperature,
      max_tokens: settings.maxTokens,
      meta: {
        chatTitle,
        contactPhone,
        mode
      },
      transcript: transcript || ''
    };

    const resp = await bg('AI_CHAT', { messages, payload });
    if (!resp?.ok) throw new Error(resp?.error || 'Falha na IA');
    return safeText(resp.text || '').trim();
  }

  async function aiMemoryFromTranscript(transcript) {
    const settings = await getSettingsCached();
    const system = await buildSystemPrompt({ persona: settings.persona, businessContext: settings.businessContext }) +
      `\n\nVocê agora cria uma memória curta (perfil do contato + contexto) para futuras conversas.`;

    const user =
`A partir da conversa abaixo, gere uma memória estruturada em JSON com o formato:
{
  "profile": "resumo do contato em 1-3 linhas",
  "preferences": ["..."],
  "context": ["fatos relevantes confirmados"],
  "open_loops": ["pendências/perguntas em aberto"],
  "next_actions": ["próximos passos sugeridos"],
  "tone": "tom recomendado"
}

Regras:
- Não invente. Se algo não está claro, use "desconhecido".
- Evite dados sensíveis desnecessários.
- Retorne SOMENTE o JSON.

CONVERSA:
${transcript || '(não consegui ler mensagens)'}
`;

    const chatTitle = getChatTitle();
    const contactPhone = (parseNumbersFromText(chatTitle)[0] || parseNumbersFromText(transcript || '')[0] || '').trim();

    const messages = [
      { role: 'system', content: system },
      { role: 'user', content: user }
    ];

    const payload = {
      messages,
      model: settings.openaiModel,
      temperature: settings.temperature,
      max_tokens: settings.maxTokens,
      meta: { chatTitle, contactPhone, mode: 'memory' },
      transcript: transcript || ''
    };

    const resp = await bg('AI_CHAT', { messages, payload });
    if (!resp?.ok) throw new Error(resp?.error || 'Falha na IA');
    return safeText(resp.text || '').trim();
  }

  function tryParseJson(text) {
    const t = safeText(text).trim();
    if (!t) return null;
    try { return JSON.parse(t); } catch (_) {}
    // try extract JSON object from fenced code or extra text
    const m = t.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
    if (m && m[1]) {
      try { return JSON.parse(m[1]); } catch (_) {}
    }
    const m2 = t.match(/\{[\s\S]*\}/);
    if (m2) {
      try { return JSON.parse(m2[0]); } catch (_) {}
    }
    return null;
  }

  // -------------------------
  // UI mount
  // -------------------------
  function mount() {
    if (document.getElementById(EXT.id)) return;

    const host = document.createElement('div');
    host.id = EXT.id;

    const shadow = host.attachShadow({ mode: 'open' });

    const style = document.createElement('style');
    style.textContent = `
      :host{
        --bg: rgba(10, 12, 24, 0.88);
        --panel: rgba(13, 16, 32, 0.92);
        --stroke: rgba(255,255,255,.10);
        --stroke2: rgba(139,92,246,.35);
        --stroke3: rgba(59,130,246,.25);
        --text: rgba(240,243,255,.95);
        --muted: rgba(240,243,255,.70);
        --danger: #ff4d4f;
        --ok: rgba(120, 255, 190, .95);
        --accent: #8b5cf6;
        --accent2: #3b82f6;
        --shadow: 0 18px 60px rgba(0,0,0,.45);
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      }
      *{ box-sizing:border-box; }
      .fab{
        width: 52px; height: 52px; border-radius: 16px;
        display:flex; align-items:center; justify-content:center;
        cursor:pointer; user-select:none;
        background: linear-gradient(135deg, rgba(139,92,246,.95), rgba(59,130,246,.95));
        border: 1px solid rgba(255,255,255,.18);
        box-shadow: 0 16px 44px rgba(0,0,0,.45);
        position: fixed;
        right: 24px;
        top: 80px;
      }
      .fab span{ font-size: 20px; filter: drop-shadow(0 6px 12px rgba(0,0,0,.35)); }
      .badge{
        position:absolute;
        right: -2px;
        top: -2px;
        min-width: 18px;
        height: 18px;
        padding: 0 6px;
        border-radius: 999px;
        background: rgba(255,255,255,.92);
        color: #0b1020;
        font-size: 11px;
        display:none;
        align-items:center;
        justify-content:center;
        border: 1px solid rgba(0,0,0,.06);
      }
      .badge.on{ display:flex; }

      .panel{
        position:fixed;
        right: 24px;
        top: 142px;
        width: 388px;
        max-height: 74vh;
        overflow:auto;
        border-radius: 18px;
        background: radial-gradient(1200px 500px at 20% -10%, rgba(139,92,246,.20), transparent 50%),
                    radial-gradient(1000px 600px at 90% 0%, rgba(59,130,246,.16), transparent 55%),
                    var(--panel);
        border: 1px solid var(--stroke);
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
        display:none;
      }
      .panel.open{ display:block; }

      .hdr{
        padding: 12px 12px 10px;
        border-bottom: 1px solid rgba(255,255,255,.08);
        display:flex; align-items:center; justify-content:space-between; gap:10px;
      }
      .hdr h2{ margin:0; font-size: 13px; letter-spacing:.2px; }
      .hdr .sub{ font-size: 11px; color: var(--muted); margin-top:2px; }
      .hdr .right{ display:flex; gap:8px; align-items:center; }
      .pill{
        font-size: 11px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.10);
        background: rgba(255,255,255,.06);
        color: var(--muted);
      }
      button.icon{
        width: 34px; height: 34px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.06);
        color: var(--text);
        cursor:pointer;
      }
      button.icon:hover{ background: rgba(255,255,255,.10); }

      .tabs{
        display:flex;
        gap:8px;
        padding: 10px 12px 8px;
        border-bottom: 1px solid rgba(255,255,255,.08);
      }
      .tab{
        font-size: 12px;
        padding: 7px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(5,7,15,.35);
        color: var(--muted);
        cursor:pointer;
      }
      .tab.active{
        color: var(--text);
        border-color: rgba(139,92,246,.55);
        box-shadow: 0 0 0 4px rgba(139,92,246,.12);
        background: rgba(139,92,246,.18);
      }

      .sec{ padding: 10px 12px 14px; display:none; }
      .sec.active{ display:block; }

      .note{
        font-size: 11px;
        color: var(--muted);
        line-height: 1.35;
        background: rgba(5,7,15,.35);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 14px;
        padding: 10px;
      }

      label{ display:block; font-size: 12px; margin: 10px 0 4px; color: rgba(240,243,255,.92); }

      textarea, input, select{
        width: 100%;
        font-size: 12px;
        padding: 9px 10px;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(5,7,15,.55);
        color: var(--text);
        outline: none;
      }
      textarea{ min-height: 96px; resize: vertical; }
      input[type="file"]{
        padding: 10px;
        background: rgba(5,7,15,.35);
      }
      input[type="file"]::file-selector-button{
        margin-right: 10px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 12px;
        background: rgba(255,255,255,.06);
        color: var(--text);
        padding: 8px 10px;
        cursor: pointer;
      }
      input[type="file"]::file-selector-button:hover{
        background: rgba(255,255,255,.10);
      }

      textarea:focus, input:focus, select:focus{
        border-color: rgba(139,92,246,.55);
        box-shadow: 0 0 0 4px rgba(139,92,246,.14);
      }

      .row{ display:flex; gap:8px; align-items:flex-end; }
      .row > *{ flex:1; }

      .btns{ display:flex; gap:8px; margin-top: 10px; flex-wrap: wrap; }
      button{
        padding: 9px 10px;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.06);
        color: var(--text);
        cursor:pointer;
        font-size: 12px;
      }
      button:hover{ background: rgba(255,255,255,.10); }
      button.primary{
        background: linear-gradient(135deg, rgba(139,92,246,.95), rgba(59,130,246,.95));
        border-color: rgba(255,255,255,.18);
        font-weight: 700;
      }
      button.danger{
        background: rgba(255,77,79,.14);
        border-color: rgba(255,77,79,.35);
      }
      button:disabled{ opacity: .6; cursor:not-allowed; }

      .status{
        font-size: 11px;
        margin-top: 8px;
        color: var(--muted);
        white-space: pre-wrap;
        line-height:1.35;
      }
      .status.ok{ color: var(--ok); }
      .status.err{ color: var(--danger); }

      .list a{
        display:block;
        font-size:12px;
        padding: 8px 10px;
        border:1px solid rgba(255,255,255,.10);
        border-radius: 14px;
        margin: 8px 0;
        text-decoration:none;
        color: var(--text);
        background: rgba(5,7,15,.35);
      }
      .list a:hover{ background: rgba(255,255,255,.06); }

      .split{
        display:flex;
        gap:8px;
        align-items:center;
        justify-content:space-between;
      }
      .checkline{
        display:flex;
        align-items:center;
        gap:8px;
        padding: 10px;
        border:1px solid rgba(255,255,255,.08);
        border-radius: 14px;
        background: rgba(5,7,15,.35);
        margin-top: 8px;
        color: var(--muted);
        font-size: 11px;
      }
      .checkline input{ width:16px; height:16px; }
      .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

      .preview-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
      }
      .preview-content {
        background: var(--panel);
        border-radius: 18px;
        padding: 20px;
        max-width: 400px;
        max-height: 70vh;
        overflow: auto;
        border: 1px solid var(--stroke);
      }
      .preview-stats {
        font-size: 13px;
        margin: 10px 0;
        padding: 10px;
        background: rgba(139,92,246,0.15);
        border-radius: 12px;
      }
      .preview-message {
        font-size: 12px;
        padding: 12px;
        background: rgba(5,7,15,0.55);
        border-radius: 12px;
        margin: 10px 0;
        white-space: pre-wrap;
        border: 1px solid rgba(255,255,255,0.1);
      }
      .preview-contacts {
        font-size: 11px;
        max-height: 150px;
        overflow: auto;
        padding: 10px;
        background: rgba(5,7,15,0.35);
        border-radius: 12px;
      }
      .schedule-box {
        margin: 10px 0;
        padding: 10px;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        background: rgba(5,7,15,0.35);
      }
      .scheduled-item {
        padding: 8px 10px;
        margin: 6px 0;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        background: rgba(5,7,15,0.45);
        font-size: 11px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
      }
      .scheduled-item .info {
        flex: 1;
        line-height: 1.4;
      }
      .scheduled-item button {
        padding: 4px 8px;
        font-size: 10px;
        min-width: 60px;
      }

      .progress-wrap {
        margin-top: 10px;
        background: rgba(5,7,15,.55);
        border-radius: 14px;
        height: 28px;
        position: relative;
        overflow: hidden;
      }
      .progress-bar {
        height: 100%;
        background: linear-gradient(135deg, rgba(139,92,246,.8), rgba(59,130,246,.8));
        border-radius: 14px;
        width: 0%;
        transition: width 0.3s ease;
      }
      .progress-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 11px;
        color: var(--text);
      }

      /* Training Tab Styles */
      .knowledge-section {
        margin: 12px 0;
        padding: 12px;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        background: rgba(5,7,15,0.35);
      }
      .knowledge-section label {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 8px;
        display: block;
      }
      .products-list, .faq-list, .canned-list, .docs-list {
        max-height: 150px;
        overflow: auto;
        margin: 8px 0;
      }
      .product-item, .faq-item, .canned-item, .doc-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 10px;
        margin: 4px 0;
        font-size: 11px;
      }
      .product-item .price { color: var(--ok); }
      .product-item .stock { color: var(--muted); }
      .product-item .stock.out { color: var(--danger); }
      .test-result {
        margin-top: 10px;
        padding: 12px;
        background: rgba(5,7,15,0.55);
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.1);
      }
      .stats-box {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        font-size: 12px;
      }
      .stats-box div {
        padding: 8px;
        background: rgba(5,7,15,0.55);
        border-radius: 10px;
      }
      .item-content {
        flex: 1;
        margin-right: 8px;
      }
      .item-actions {
        display: flex;
        gap: 4px;
      }
      .item-actions button {
        padding: 4px 8px;
        font-size: 10px;
        min-width: unset;
      }
    `;
    shadow.appendChild(style);

    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <div class="fab" title="WhatsHybrid Lite">
        <span>🤖</span>
        <div class="badge" id="badge">1</div>
      </div>

      <div class="panel" role="dialog" aria-label="WhatsHybrid Lite">
        <div class="hdr">
          <div>
            <h2>WhatsHybrid Lite</h2>
            <div class="sub">IA • Memória • Campanhas • Contatos</div>
          </div>
          <div class="right">
            <div class="pill" id="pillStatus">online</div>
            <button class="icon" id="closeBtn" title="Fechar">✕</button>
          </div>
        </div>

        <div class="tabs">
          <div class="tab active" data-tab="chat">Chatbot</div>
          <div class="tab" data-tab="camp">Campanhas</div>
          <div class="tab" data-tab="cont">Contatos</div>
          <div class="tab" data-tab="training">🧠 IA</div>
        </div>

        <div class="sec active" data-sec="chat">
          <div class="note">
            <b>Modo seguro:</b> o chatbot gera texto. Você decide o que enviar.<br/>
            A IA usa <b>contexto do negócio</b> + <b>memória (Leão)</b> + <b>exemplos</b>.
          </div>

          <label>Instrução extra</label>
          <textarea id="chatPrompt" placeholder="Ex.: Responda curto, com tom premium e CTA."></textarea>

          <div class="row">
            <div>
              <label>Mensagens lidas</label>
              <input id="chatLimit" type="number" min="5" max="80" value="30" />
            </div>
            <div>
              <label>Ação</label>
              <select id="chatMode">
                <option value="reply">Sugerir resposta</option>
                <option value="summary">Resumir conversa</option>
                <option value="followup">Próximos passos</option>
                <option value="train">Treino (melhorias)</option>
              </select>
            </div>
          </div>

          <div class="btns">
            <button class="primary" id="genBtn">Gerar</button>
            <button id="memBtn">Atualizar Memória (Leão)</button>
            <button id="saveExampleBtn">Salvar como exemplo</button>
          </div>

          <label>Saída</label>
          <textarea id="chatOut" placeholder="Aqui aparece a resposta..."></textarea>

          <div class="btns">
            <button id="insertBtn">Inserir no WhatsApp</button>
            <button id="sendBtn">Inserir no WhatsApp (assistido)</button>
            <button id="copyBtn">Copiar</button>
          </div>

          <div class="status" id="chatStatus"></div>
          <div class="status mono" id="trainStatus" style="display:none;"></div>
        </div>

        <div class="sec" data-sec="camp">
          <div class="note">
            Campanhas: <b>DOM</b> (automático no WhatsApp Web) ou <b>API</b> (backend oficial).
          </div>

          <label>Modo</label>
          <select id="campMode">
            <option value="dom">DOM (automático)</option>
            <option value="api">API (backend)</option>
          </select>

          <label>Lista de números (1 por linha, com DDI) ou CSV: numero,nome</label>
          <textarea id="campNumbers" placeholder="+5511999999999,João&#10;+5511988888888,Maria"></textarea>

          <label>Mensagem (use {{nome}} e {{numero}})</label>
          <textarea id="campMsg" placeholder="Olá {{nome}}, tudo bem?"></textarea>

          <label>Mídia (opcional - imagem/vídeo)</label>
          <input id="campMedia" type="file" accept="image/*,video/*" />
          <div class="status" id="campMediaStatus"></div>

          <div id="campDomBox" style="display:none;">
            <!-- Agendamento -->
            <div class="schedule-box">
              <label>Quando enviar?</label>
              <div class="checkline">
                <input type="radio" name="scheduleType" id="scheduleNow" value="now" checked>
                <label for="scheduleNow">Enviar agora</label>
              </div>
              <div class="checkline">
                <input type="radio" name="scheduleType" id="scheduleLater" value="later">
                <label for="scheduleLater">Agendar para:</label>
                <input type="datetime-local" id="scheduleDateTime" disabled>
              </div>
            </div>
            <div class="row">
              <div>
                <label>Delay min (s)</label>
                <input id="campDelayMin" type="number" min="3" max="120" value="8" />
              </div>
              <div>
                <label>Delay max (s)</label>
                <input id="campDelayMax" type="number" min="5" max="240" value="15" />
              </div>
            </div>

            <div class="note" style="margin-top:10px;">
              <b>⚠️ Atenção:</b> Use com moderação. Envios em massa podem causar bloqueio do número.
              Recomendado: máximo 50 contatos por sessão com delays altos.
            </div>

            <div class="btns">
              <button class="primary" id="campStartBtn">▶ Iniciar Campanha</button>
              <button id="campPauseBtn">⏸ Pausar</button>
              <button class="danger" id="campStopBtn">⏹ Parar</button>
            </div>

            <div class="status" id="campDomStatus"></div>

            <div class="progress-wrap" id="campProgress" style="display:none;">
              <div class="progress-bar" id="campProgressBar"></div>
              <span class="progress-text" id="campProgressText">0/0</span>
            </div>

            <!-- Scheduled Campaigns List -->
            <div id="scheduledCampaignsBox" style="margin-top:15px; display:none;">
              <label>📅 Campanhas Agendadas</label>
              <div id="scheduledCampaignsList" style="max-height:200px; overflow:auto;"></div>
            </div>
          </div>

          <div id="campApiBox" style="display:none;">
            <div class="note" style="margin-top:10px;">
              API envia para o backend (ex.: WhatsApp Business API). Requer Backend URL configurado no popup.
            </div>
            <div class="row">
              <div>
                <label>Lote</label>
                <input id="campBatch" type="number" min="1" max="200" value="25" />
              </div>
              <div>
                <label>Intervalo (s)</label>
                <input id="campInterval" type="number" min="1" max="300" value="8" />
              </div>
            </div>
            <div class="btns">
              <button class="primary" id="campApiBtn">Enviar via API</button>
            </div>
            <div class="status" id="campApiStatus"></div>
          </div>
        </div>

        <div class="sec" data-sec="cont">
          <div class="note">
            Extração pega números visíveis (títulos, header e mensagens).
            Resultados dependem do WhatsApp Web.
          </div>

          <div class="btns">
            <button class="primary" id="extractBtn">Extrair números</button>
            <button id="downloadBtn">Baixar CSV</button>
          </div>

          <label>Números</label>
          <textarea id="contOut" placeholder="Saída..."></textarea>

          <div class="status" id="contStatus"></div>
        </div>

        <div class="sec" data-sec="training">
          <div class="note">
            <b>Treinamento de IA:</b> Configure conhecimento do negócio para respostas mais inteligentes e personalizadas.
          </div>

          <!-- Sobre o Negócio -->
          <div class="knowledge-section">
            <label>🏢 Sobre o Negócio</label>
            <textarea id="bizName" placeholder="Nome da empresa"></textarea>
            <textarea id="bizDescription" placeholder="Descrição do negócio, o que vendem, diferenciais..."></textarea>
            <input id="bizSegment" placeholder="Segmento (ex: Varejo, Serviços, Tech)">
            <input id="bizHours" placeholder="Horário de atendimento (ex: 9h às 18h)">
          </div>

          <!-- Políticas -->
          <div class="knowledge-section">
            <label>📋 Políticas</label>
            <textarea id="policyPayment" placeholder="Formas de pagamento aceitas..."></textarea>
            <textarea id="policyDelivery" placeholder="Política de entrega..."></textarea>
            <textarea id="policyReturns" placeholder="Política de trocas e devoluções..."></textarea>
          </div>

          <!-- Catálogo de Produtos -->
          <div class="knowledge-section">
            <label>📦 Catálogo de Produtos</label>
            <input type="file" id="productsFile" accept=".csv,.txt">
            <div class="note">Formato CSV: nome,preço,estoque,descrição</div>
            <div id="productsList" class="products-list"></div>
            <button id="addProductBtn">+ Adicionar Produto Manual</button>
          </div>

          <!-- FAQ -->
          <div class="knowledge-section">
            <label>❓ FAQ - Perguntas Frequentes</label>
            <div id="faqList" class="faq-list"></div>
            <div class="row">
              <input id="faqQuestion" placeholder="Pergunta">
              <input id="faqAnswer" placeholder="Resposta">
              <button id="addFaqBtn">+</button>
            </div>
          </div>

          <!-- Respostas Rápidas -->
          <div class="knowledge-section">
            <label>💬 Respostas Rápidas</label>
            <div class="note">Defina gatilhos (palavras-chave) e respostas automáticas</div>
            <div id="cannedList" class="canned-list"></div>
            <div class="row">
              <input id="cannedTriggers" placeholder="Gatilhos (separados por vírgula)">
              <textarea id="cannedReply" placeholder="Resposta"></textarea>
              <button id="addCannedBtn">+</button>
            </div>
          </div>

          <!-- Upload de Documentos -->
          <div class="knowledge-section">
            <label>📄 Documentos</label>
            <input type="file" id="docsFile" accept=".pdf,.txt,.md" multiple>
            <div class="note">Upload de catálogos, manuais, políticas em PDF ou TXT</div>
            <div id="docsList" class="docs-list"></div>
          </div>

          <!-- Tom de Voz -->
          <div class="knowledge-section">
            <label>🗣️ Tom de Voz</label>
            <select id="toneStyle">
              <option value="formal">Formal</option>
              <option value="informal" selected>Informal</option>
              <option value="friendly">Amigável</option>
              <option value="professional">Profissional</option>
            </select>
            <div class="checkline">
              <input type="checkbox" id="toneEmojis" checked>
              <label>Usar emojis nas respostas</label>
            </div>
            <input id="toneGreeting" placeholder="Saudação padrão (ex: Olá! 👋)">
            <input id="toneClosing" placeholder="Despedida padrão (ex: Qualquer dúvida, estou aqui!)">
          </div>

          <!-- Testar IA -->
          <div class="knowledge-section">
            <label>🧪 Testar IA</label>
            <div class="row">
              <textarea id="testQuestion" placeholder="Digite uma pergunta de teste..."></textarea>
              <button id="testAiBtn" class="primary">Testar</button>
            </div>
            <div id="testResult" class="test-result" style="display:none;">
              <label>Resposta da IA:</label>
              <div id="testAnswer"></div>
              <div class="btns">
                <button id="testGoodBtn">✅ Boa</button>
                <button id="testBadBtn">❌ Ruim</button>
                <button id="testCorrectBtn">✏️ Corrigir</button>
              </div>
            </div>
          </div>

          <!-- Estatísticas -->
          <div class="knowledge-section">
            <label>📊 Estatísticas de Treinamento</label>
            <div id="trainingStats" class="stats-box">
              <div>✅ Respostas boas: <span id="statGood">0</span></div>
              <div>❌ Respostas ruins: <span id="statBad">0</span></div>
              <div>✏️ Correções: <span id="statCorrected">0</span></div>
              <div>📦 Produtos: <span id="statProducts">0</span></div>
              <div>❓ FAQs: <span id="statFaqs">0</span></div>
            </div>
          </div>

          <!-- Botões de Ação -->
          <div class="btns">
            <button id="saveKnowledgeBtn" class="primary">💾 Salvar Conhecimento</button>
            <button id="exportKnowledgeBtn">📤 Exportar JSON</button>
            <button id="importKnowledgeBtn">📥 Importar JSON</button>
            <button id="clearKnowledgeBtn" class="danger">🗑️ Limpar Tudo</button>
          </div>
          <input type="file" id="importFile" accept=".json" style="display:none;">
          <div class="status" id="trainingStatus"></div>
        </div>
      </div>

      <!-- Modal de Prévia (inicialmente oculto) -->
      <div class="preview-modal" id="previewModal" style="display:none;">
        <div class="preview-content">
          <h3>📋 Prévia da Campanha</h3>
          <div class="preview-stats" id="previewStats"></div>
          <div class="preview-message" id="previewMessage"></div>
          <div class="preview-contacts" id="previewContacts"></div>
          <div class="btns">
            <button class="danger" id="previewCancelBtn">❌ Cancelar</button>
            <button class="primary" id="previewConfirmBtn">✅ Confirmar Envio</button>
          </div>
        </div>
      </div>
    `;
    shadow.appendChild(wrap);

    document.documentElement.appendChild(host);

    // UI elements
    const fab = shadow.querySelector('.fab');
    const badge = shadow.getElementById('badge');
    const panel = shadow.querySelector('.panel');
    const closeBtn = shadow.getElementById('closeBtn');
    const pillStatus = shadow.getElementById('pillStatus');

    const tabs = Array.from(shadow.querySelectorAll('.tab'));
    const secs = Array.from(shadow.querySelectorAll('.sec'));

    function setTab(key) {
      tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === key));
      secs.forEach(s => s.classList.toggle('active', s.dataset.sec === key));
    }

    fab.addEventListener('click', () => {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) {
        badge.classList.remove('on');
      }
    });
    closeBtn.addEventListener('click', () => panel.classList.remove('open'));
    tabs.forEach(t => t.addEventListener('click', () => setTab(t.dataset.tab)));

    // Provider status indicator
    (async () => {
      try {
        const st = await getSettingsCached();
        pillStatus.textContent = st.provider === 'backend' ? 'backend' : 'openai';
      } catch (_) {
        pillStatus.textContent = 'offline';
      }
    })();

    // -------------------------
    // Chatbot wiring
    // -------------------------
    const chatPrompt = shadow.getElementById('chatPrompt');
    const chatOut = shadow.getElementById('chatOut');
    const chatLimit = shadow.getElementById('chatLimit');
    const chatMode = shadow.getElementById('chatMode');
    const chatStatus = shadow.getElementById('chatStatus');
    const trainStatus = shadow.getElementById('trainStatus');

    const genBtn = shadow.getElementById('genBtn');
    const memBtn = shadow.getElementById('memBtn');
    const saveExampleBtn = shadow.getElementById('saveExampleBtn');
    const insertBtn = shadow.getElementById('insertBtn');
    const sendBtn = shadow.getElementById('sendBtn');
    const copyBtn = shadow.getElementById('copyBtn');

    function setChatStatus(msg, kind) {
      chatStatus.textContent = msg || '';
      chatStatus.classList.remove('ok','err');
      if (kind === 'ok') chatStatus.classList.add('ok');
      if (kind === 'err') chatStatus.classList.add('err');
    }

    function showTrainStatus(txt) {
      const t = safeText(txt).trim();
      if (!t) {
        trainStatus.style.display = 'none';
        trainStatus.textContent = '';
        return;
      }
      trainStatus.style.display = 'block';
      trainStatus.textContent = t;
    }

    async function runChat() {
      setChatStatus('', null);
      showTrainStatus('');

      genBtn.disabled = true;
      try {
        const limit = clamp(chatLimit.value || 30, 5, 80);
        const transcript = getVisibleTranscript(limit);
        const chatTitle = getChatTitle();
        const hybrid = await getHybridContext({ chatTitle, transcript });
        const mem = hybrid.memory;
        const examplesOverride = hybrid.examples;
        const contextOverride = hybrid.context;

        const mode = chatMode.value || 'reply';
        const extra = safeText(chatPrompt.value);

        const text = await aiChat({ mode, extraInstruction: extra, transcript, memory: mem, chatTitle });

        if (mode === 'train') {
          // Training suggestions
          const json = tryParseJson(text);
          if (!json) {
            showTrainStatus(text);
            setChatStatus('Treino gerado (texto) ✅', 'ok');
          } else {
            showTrainStatus(JSON.stringify(json, null, 2));
            setChatStatus('Treino gerado (JSON) ✅', 'ok');
          }
          return;
        }

        chatOut.value = text;
        setChatStatus('OK ✅', 'ok');

        // Optional: auto memory update
        const st = await getSettingsCached();
        if (st.autoMemory) {
          try {
            await autoUpdateMemory(transcript, chatTitle);
          } catch (e) {
            warn('autoMemory falhou:', e);
          }
        }

      } catch (e) {
        setChatStatus(`Erro: ${e?.message || String(e)}`, 'err');
      } finally {
        genBtn.disabled = false;
      }
    }

    async function autoUpdateMemory(transcript, chatTitle) {
      // Lightweight debounce: only update if transcript has enough content
      const t = safeText(transcript).trim();
      if (t.length < 60) return;
      const raw = await aiMemoryFromTranscript(t);
      const json = tryParseJson(raw);
      const summary =
        json
          ? [
              `Perfil: ${safeText(json.profile)}`,
              json.tone ? `Tom: ${safeText(json.tone)}` : '',
              Array.isArray(json.preferences) && json.preferences.length ? `Preferências: ${json.preferences.join('; ')}` : '',
              Array.isArray(json.context) && json.context.length ? `Contexto: ${json.context.join('; ')}` : '',
              Array.isArray(json.open_loops) && json.open_loops.length ? `Pendências: ${json.open_loops.join('; ')}` : '',
              Array.isArray(json.next_actions) && json.next_actions.length ? `Próximos: ${json.next_actions.join('; ')}` : '',
            ].filter(Boolean).join('\n')
          : raw;

      await setMemory(chatTitle, { summary, json });
    }

    genBtn.addEventListener('click', runChat);

    memBtn.addEventListener('click', async () => {
      setChatStatus('', null);
      memBtn.disabled = true;
      try {
        const limit = clamp(chatLimit.value || 30, 10, 120);
        const transcript = getVisibleTranscript(limit);
        const chatTitle = getChatTitle();

        await autoUpdateMemory(transcript, chatTitle);
        setChatStatus('Memória atualizada ✅', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao atualizar memória: ${e?.message || String(e)}`, 'err');
      } finally {
        memBtn.disabled = false;
      }
    });

    saveExampleBtn.addEventListener('click', async () => {
      setChatStatus('', null);
      try {
        const limit = clamp(chatLimit.value || 30, 5, 80);
        const transcript = getVisibleTranscript(limit);
        const assistant = safeText(chatOut.value).trim();

        if (!transcript.trim()) throw new Error('Sem conversa visível para usar como exemplo.');
        if (!assistant) throw new Error('Gere uma resposta primeiro para salvar como exemplo.');

        // The "user" side example is: last inbound message or last few lines.
        const lines = transcript.split('\n').slice(-6).join('\n').trim();
        await addExample({ user: `Contexto:\n${lines}\n\nGere uma resposta:`, assistant });

        setChatStatus('Exemplo salvo ✅ (ajuda a IA a ficar mais consistente)', 'ok');
      } catch (e) {
        setChatStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    insertBtn.addEventListener('click', async () => {
      try {
        await insertIntoComposer(chatOut.value || '');
        setChatStatus('Inserido ✅ (confira e envie)', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao inserir: ${e?.message || String(e)}`, 'err');
      }
    });

    sendBtn.addEventListener('click', async () => {
      try {
        await insertIntoComposer(chatOut.value || '');
        setChatStatus('Inserido ✅ (envio assistido — clique em enviar no WhatsApp)', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao inserir: ${e?.message || String(e)}`, 'err');
      }
    });

    copyBtn.addEventListener('click', async () => {
      try {
        await copyToClipboard(chatOut.value || '');
        setChatStatus('Copiado ✅', 'ok');
      } catch (e) {
        setChatStatus(`Erro ao copiar: ${e?.message || String(e)}`, 'err');
      }
    });

    // -------------------------
    // Auto-suggest (MutationObserver)
    // -------------------------
    let autoSuggestTimer = null;
    let lastSuggestFingerprint = '';
    async function maybeAutoSuggest() {
      try {
        const st = await getSettingsCached();
        if (!st.autoSuggest) return;

        const limit = clamp(chatLimit.value || 30, 5, 80);
        const transcript = getVisibleTranscript(limit);
        const fp = String(transcript).slice(-600); // cheap fingerprint
        if (!fp || fp === lastSuggestFingerprint) return;
        lastSuggestFingerprint = fp;

        badge.textContent = '!';
        badge.classList.add('on');

        // If panel is open, auto-fill output suggestion
        const chatTitle = getChatTitle();
        const hybrid = await getHybridContext({ chatTitle, transcript });
        const mem = hybrid.memory;
        const examplesOverride = hybrid.examples;
        const contextOverride = hybrid.context;
        const text = await aiChat({
          mode: 'reply',
          extraInstruction: safeText(chatPrompt.value),
          transcript,
          memory: mem,
          chatTitle
        });

        // Don't overwrite if user is editing
        if (!safeText(chatOut.value).trim()) {
          chatOut.value = text;
        }
        setChatStatus('Sugestão automática pronta ✅', 'ok');

      } catch (e) {
        warn('autoSuggest erro:', e);
      }
    }

    function hookMessageObserver() {
      // Observe message container changes
      const container =
        document.querySelector('[data-testid="conversation-panel-messages"]') ||
        document.querySelector('#main') ||
        null;

      if (!container) return;

      const obs = new MutationObserver(() => {
        if (autoSuggestTimer) clearTimeout(autoSuggestTimer);
        autoSuggestTimer = setTimeout(() => {
          maybeAutoSuggest();
        }, 1200);
      });

      obs.observe(container, { childList: true, subtree: true });
    }

    hookMessageObserver();

    // -------------------------
    // Campaigns
    // -------------------------
    const campMode = shadow.getElementById('campMode');
    const campNumbers = shadow.getElementById('campNumbers');
    const campMsg = shadow.getElementById('campMsg');


    const campDomStatus = shadow.getElementById('campDomStatus');
    const campMediaStatus = shadow.getElementById('campMediaStatus');
    const campMedia = shadow.getElementById('campMedia');

    let campMediaPayload = null;

    async function fileToPayload(file) {
      if (!file) return null;
      const maxBytes = 16 * 1024 * 1024; // 16MB (WhatsApp supports up to 16MB for media)
      if (file.size > maxBytes) throw new Error('Arquivo muito grande (máx 16MB).');
      const dataUrl = await new Promise((resolve, reject) => {
        const fr = new FileReader();
        fr.onerror = () => reject(new Error('Falha ao ler arquivo'));
        fr.onload = () => resolve(String(fr.result || ''));
        fr.readAsDataURL(file);
      });
      const m = dataUrl.match(/^data:(.+?);base64,(.+)$/);
      return {
        name: file.name || 'media',
        type: (m && m[1]) ? m[1] : (file.type || 'application/octet-stream'),
        base64: (m && m[2]) ? m[2] : ''
      };
    }

    function setCampDomStatus(msg, kind) {
      if (!campDomStatus) return;
      campDomStatus.textContent = msg || '';
      campDomStatus.classList.remove('ok','err');
      if (kind === 'ok') campDomStatus.classList.add('ok');
      if (kind === 'err') campDomStatus.classList.add('err');
    }

    function setCampMediaStatus(msg, kind) {
      if (!campMediaStatus) return;
      campMediaStatus.textContent = msg || '';
      campMediaStatus.classList.remove('ok','err');
      if (kind === 'ok') campMediaStatus.classList.add('ok');
      if (kind === 'err') campMediaStatus.classList.add('err');
    }

    if (campMedia) {
      campMedia.addEventListener('change', async () => {
        try {
          const f = campMedia.files && campMedia.files[0];
          campMediaPayload = f ? await fileToPayload(f) : null;
          if (campMediaPayload) setCampMediaStatus(`✅ Mídia pronta: ${campMediaPayload.name}`, 'ok');
          else setCampMediaStatus('Sem mídia selecionada.', 'ok');
        } catch (e) {
          campMediaPayload = null;
          setCampMediaStatus(e?.message || String(e), 'err');
        }
      });
    }

    const campDomBox = shadow.getElementById('campDomBox');
    const campApiBox = shadow.getElementById('campApiBox');

    const campDelayMin = shadow.getElementById('campDelayMin');
    const campDelayMax = shadow.getElementById('campDelayMax');
    const campStartBtn = shadow.getElementById('campStartBtn');
    const campPauseBtn = shadow.getElementById('campPauseBtn');
    const campStopBtn = shadow.getElementById('campStopBtn');

    const campBatch = shadow.getElementById('campBatch');
    const campInterval = shadow.getElementById('campInterval');
    const campApiBtn = shadow.getElementById('campApiBtn');
    const campApiStatus = shadow.getElementById('campApiStatus');

    const campProgress = shadow.getElementById('campProgress');
    const campProgressBar = shadow.getElementById('campProgressBar');
    const campProgressText = shadow.getElementById('campProgressText');

    // Scheduling elements
    const scheduleNow = shadow.getElementById('scheduleNow');
    const scheduleLater = shadow.getElementById('scheduleLater');
    const scheduleDateTime = shadow.getElementById('scheduleDateTime');
    const scheduledCampaignsBox = shadow.getElementById('scheduledCampaignsBox');
    const scheduledCampaignsList = shadow.getElementById('scheduledCampaignsList');

    // Preview modal elements
    const previewModal = shadow.getElementById('previewModal');
    const previewStats = shadow.getElementById('previewStats');
    const previewMessage = shadow.getElementById('previewMessage');
    const previewContacts = shadow.getElementById('previewContacts');
    const previewCancelBtn = shadow.getElementById('previewCancelBtn');
    const previewConfirmBtn = shadow.getElementById('previewConfirmBtn');

    const campRun = { running:false, paused:false, abort:false, cursor:0, total:0 };

    function setCampApiStatus(msg, kind) {
      campApiStatus.textContent = msg || '';
      campApiStatus.classList.remove('ok','err');
      if (kind === 'ok') campApiStatus.classList.add('ok');
      if (kind === 'err') campApiStatus.classList.add('err');
    }

    function updateProgress(current, total) {
      if (!campProgress || !campProgressBar || !campProgressText) return;
      const percent = total > 0 ? Math.round((current / total) * 100) : 0;
      campProgressBar.style.width = `${percent}%`;
      campProgressText.textContent = `${current}/${total}`;
      campProgress.style.display = total > 0 ? 'block' : 'none';
    }

    function parseCampaignLines(raw) {
      const lines = safeText(raw).split(/\r?\n/).map(l => l.trim()).filter(Boolean);
      const entries = [];
      for (const line of lines) {
        const parts = line.split(',').map(p => p.trim());
        const number = (parts[0] || '').replace(/[^\d+]/g, '');
        const name = parts[1] || '';
        if (!number) continue;
        const normalized = number.startsWith('+') ? number : '+' + number;
        entries.push({ number: normalized, name });
      }
      const map = new Map();
      for (const e of entries) map.set(e.number, e);
      return Array.from(map.values());
    }

    function applyVars(msg, entry) {
      let out = safeText(msg);
      out = out.replaceAll('{{nome}}', entry.name || '');
      out = out.replaceAll('{{numero}}', entry.number || '');
      return out;
    }

    function renderCampMode() {
      const m = campMode.value;
      campDomBox.style.display = (m === 'dom') ? 'block' : 'none';
      campApiBox.style.display = (m === 'api') ? 'block' : 'none';
    }

    // Schedule radio button logic
    function updateScheduleInputs() {
      if (scheduleDateTime) {
        scheduleDateTime.disabled = !scheduleLater.checked;
      }
    }

    if (scheduleNow) {
      scheduleNow.addEventListener('change', updateScheduleInputs);
    }
    if (scheduleLater) {
      scheduleLater.addEventListener('change', updateScheduleInputs);
    }
    updateScheduleInputs();

    async function waitWhilePaused() {
      while (campRun.paused && !campRun.abort) {
        await sleep(250);
      }
    }

    function showPreviewModal(entries, msg) {
      if (!previewModal || !previewStats || !previewMessage || !previewContacts) return;

      // Stats
      previewStats.innerHTML = `
        <strong>Total de contatos:</strong> ${entries.length}<br/>
        ${campMediaPayload ? '<strong>📎 Mídia:</strong> ' + campMediaPayload.name + '<br/>' : ''}
      `;

      // Preview message with first contact as example
      const firstEntry = entries[0] || { number: '+5511999999999', name: 'Exemplo' };
      const previewText = applyVars(msg || '', firstEntry);
      previewMessage.textContent = previewText || '(sem mensagem)';

      // Contact list
      const contactListHtml = entries.slice(0, 50).map((e, i) => 
        `<div style="padding:4px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
          ${i+1}. ${e.name || '(sem nome)'} - ${e.number}
        </div>`
      ).join('');
      const moreText = entries.length > 50 ? `<div style="padding:8px 0; color:var(--muted);">... e mais ${entries.length - 50} contatos</div>` : '';
      previewContacts.innerHTML = contactListHtml + moreText;

      // Show modal
      previewModal.style.display = 'flex';
    }

    function hidePreviewModal() {
      if (previewModal) {
        previewModal.style.display = 'none';
      }
    }

    // Preview modal button handlers
    if (previewCancelBtn) {
      previewCancelBtn.addEventListener('click', () => {
        hidePreviewModal();
        setCampDomStatus('❌ Campanha cancelada pelo usuário.', 'err');
      });
    }

    if (previewConfirmBtn) {
      previewConfirmBtn.addEventListener('click', async () => {
        hidePreviewModal();
        setCampDomStatus('Iniciando campanha...', 'ok');
        
        try {
          const entries = parseCampaignLines(campNumbers.value);
          const msg = safeText(campMsg.value).trim();
          await executeDomCampaign(entries, msg);
        } catch (e) {
          setCampDomStatus(`Erro: ${e?.message || String(e)}`, 'err');
        }
      });
    }

    // Scheduled campaigns storage
    async function saveScheduledCampaign(campaign) {
      return new Promise((resolve) => {
        chrome.storage.local.get(['whl_scheduled_campaigns'], (res) => {
          const campaigns = Array.isArray(res?.whl_scheduled_campaigns) ? res.whl_scheduled_campaigns : [];
          const newCampaign = {
            ...campaign,
            id: `camp_${Date.now()}_${Math.random().toString(36).slice(2, 11)}`
          };
          campaigns.push(newCampaign);
          chrome.storage.local.set({ whl_scheduled_campaigns: campaigns }, () => {
            // Notify background to create alarm
            bg('SCHEDULE_CAMPAIGN', { campaign: newCampaign }).then(() => {
              resolve(newCampaign);
            }).catch(() => {
              resolve(newCampaign);
            });
          });
        });
      });
    }

    async function getScheduledCampaigns() {
      return new Promise((resolve) => {
        chrome.storage.local.get(['whl_scheduled_campaigns'], (res) => {
          resolve(Array.isArray(res?.whl_scheduled_campaigns) ? res.whl_scheduled_campaigns : []);
        });
      });
    }

    async function removeScheduledCampaign(campaignId) {
      return new Promise((resolve) => {
        chrome.storage.local.get(['whl_scheduled_campaigns'], (res) => {
          const campaigns = Array.isArray(res?.whl_scheduled_campaigns) ? res.whl_scheduled_campaigns : [];
          const filtered = campaigns.filter(c => c.id !== campaignId);
          chrome.storage.local.set({ whl_scheduled_campaigns: filtered }, () => {
            // Notify background to cancel alarm
            bg('CANCEL_SCHEDULED_CAMPAIGN', { campaignId }).then(() => {
              resolve(true);
            }).catch(() => {
              resolve(true);
            });
          });
        });
      });
    }

    // HTML escape helper to prevent XSS
    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = String(str || '');
      return div.innerHTML;
    }

    async function refreshScheduledCampaignsList() {
      if (!scheduledCampaignsBox || !scheduledCampaignsList) return;

      const campaigns = await getScheduledCampaigns();
      
      if (campaigns.length === 0) {
        scheduledCampaignsBox.style.display = 'none';
        return;
      }

      scheduledCampaignsBox.style.display = 'block';
      
      scheduledCampaignsList.innerHTML = campaigns.map(camp => {
        const scheduledDate = new Date(camp.scheduledTime);
        const contactCount = camp.entries ? camp.entries.length : 0;
        const rawMessage = camp.message || '';
        const messagePreview = rawMessage.slice(0, 30) + (rawMessage.length > 30 ? '...' : '');
        
        return `
          <div class="scheduled-item" data-camp-id="${escapeHtml(camp.id)}">
            <div class="info">
              <div><strong>📅 ${escapeHtml(scheduledDate.toLocaleString('pt-BR'))}</strong></div>
              <div>👥 ${contactCount} contatos</div>
              <div style="color:var(--muted);">${escapeHtml(messagePreview)}</div>
            </div>
            <button class="danger cancel-scheduled" data-camp-id="${escapeHtml(camp.id)}">Cancelar</button>
          </div>
        `;
      }).join('');

      // Add event listeners for cancel buttons
      const cancelButtons = scheduledCampaignsList.querySelectorAll('.cancel-scheduled');
      cancelButtons.forEach(btn => {
        btn.addEventListener('click', async (e) => {
          e.preventDefault();
          const campId = btn.getAttribute('data-camp-id');
          if (campId) {
            try {
              await removeScheduledCampaign(campId);
              // Use safe text to prevent any XSS in status message
              setCampDomStatus(`✅ Agendamento cancelado.`, 'ok');
              await refreshScheduledCampaignsList();
            } catch (err) {
              setCampDomStatus(`❌ Erro ao cancelar: ${err?.message || String(err)}`, 'err');
            }
          }
        });
      });
    }

    // Refresh scheduled campaigns list when DOM mode is shown
    function renderCampModeWithScheduled() {
      renderCampMode();
      if (campMode.value === 'dom') {
        refreshScheduledCampaignsList();
      }
    }

    campMode.addEventListener('change', renderCampModeWithScheduled);
    renderCampModeWithScheduled();

    async function executeDomCampaign(entries, msg) {
      debugLog('Iniciando campanha DOM com', entries.length, 'contatos');
      
      const dmin = clamp(campDelayMin.value || 8, 3, 120);
      const dmax = clamp(campDelayMax.value || 15, 5, 240);

      campRun.running = true;
      campRun.paused = false;
      campRun.abort = false;
      campRun.cursor = 0;
      campRun.total = entries.length;

      // Persistir estado inicial
      await CampaignStorage.save({
        entries,
        message: msg,
        cursor: 0,
        status: 'running',
        startedAt: new Date().toISOString()
      });

      setCampDomStatus(`🚀 Iniciando campanha: ${entries.length} contatos…`, 'ok');
      updateProgress(0, entries.length);

      for (let i = 0; i < entries.length; i++) {
        if (campRun.abort) break;
        await waitWhilePaused();
        if (campRun.abort) break;

        const e = entries[i];
        debugLog(`[${i+1}/${entries.length}] Processando:`, e.number);
        
        const text = applyVars(msg || '', e).trim();
        const phoneDigits = e.number.replace(/[^\d]/g, '');

        try {
          // 1. Abrir chat
          setCampDomStatus(`📱 (${i+1}/${entries.length}) Abrindo ${e.number}…`, 'ok');
          updateProgress(i, entries.length);
          
          debugLog('Abrindo chat...');
          await openChatBySearch(phoneDigits);
          debugLog('Chat aberto!');
          
          // 2. Aguardar composer
          await sleep(500);
          const composer = findComposer();
          if (!composer) {
            debugLog('❌ Composer não encontrado após abrir chat');
            throw new Error('Composer não encontrado após abrir chat');
          }
          debugLog('Composer encontrado!');

          if (campMediaPayload) {
            // 3a. Enviar mídia (note: attachMediaAndSend handles its own send logic)
            setCampDomStatus(`📎 (${i+1}/${entries.length}) Enviando mídia para ${e.number}…`, 'ok');
            debugLog('Enviando mídia com legenda:', text.slice(0, 30) + '...');
            await attachMediaAndSend(campMediaPayload, text);
            debugLog('Mídia enviada!');
            await sleep(500);
            
            // Record for rate limiting (stealth mode tracking)
            recordMessageSent();
          } else {
            // 3b. Inserir texto
            if (!text) {
              debugLog('❌ Mensagem vazia e sem mídia');
              throw new Error('Mensagem vazia (e sem mídia).');
            }
            setCampDomStatus(`💬 (${i+1}/${entries.length}) Enviando mensagem para ${e.number}…`, 'ok');
            debugLog('Inserindo texto:', text.slice(0, 30) + '...');
            await insertIntoComposer(text, false, true);
            debugLog('Texto inserido!');
            
            // 4. Enviar
            await sleep(300);
            debugLog('Clicando enviar...');
            await clickSend(true);
            debugLog('Mensagem enviada!');
            await sleep(500);
          }

          setCampDomStatus(`✅ Enviado (${i+1}/${entries.length}) para ${e.number}`, 'ok');
          updateProgress(i + 1, entries.length);
          debugLog(`✅ Sucesso em ${e.number}`);
        } catch (err) {
          debugLog(`❌ Erro em ${e.number}:`, err);
          console.error(`[WHL] Erro em ${e.number}:`, err);
          setCampDomStatus(`❌ Falha (${i+1}/${entries.length}) em ${e.number}: ${err?.message || String(err)}`, 'err');
          updateProgress(i + 1, entries.length);
          // Continue to next contact even if one fails
        } finally {
          campRun.cursor = i + 1;
          // Atualizar estado persistido após cada envio
          await CampaignStorage.save({
            entries,
            message: msg,
            cursor: i + 1,
            status: campRun.abort ? 'aborted' : 'running',
            startedAt: campRun.startedAt || new Date().toISOString()
          });
        }

        // Random delay between messages
        if (i < entries.length - 1) { // Don't delay after last message
          const delay = (Math.random() * (dmax - dmin) + dmin) * 1000;
          debugLog(`Aguardando ${delay/1000}s...`);
          setCampDomStatus(`⏳ Aguardando ${Math.round(delay/1000)}s até próximo envio… (${i+1}/${entries.length} concluídos)`, 'ok');
          await sleep(delay);
        }
      }

      campRun.running = false;
      campRun.paused = false;
      campPauseBtn.textContent = '⏸ Pausar';
      updateProgress(entries.length, entries.length);

      if (campRun.abort) {
        debugLog('Campanha interrompida pelo usuário');
        setCampDomStatus('⚠️ Campanha interrompida pelo usuário.', 'err');
      } else {
        debugLog('Campanha concluída com sucesso!');
        setCampDomStatus(`🎉 Campanha concluída! ${entries.length} contatos processados.`, 'ok');
      }

      // Limpar estado persistido
      await CampaignStorage.clear();
    }

    campStartBtn.addEventListener('click', async () => {
      setCampDomStatus('', null);
      try {
        const entries = parseCampaignLines(campNumbers.value);
        if (!entries.length) throw new Error('Cole pelo menos 1 número.');

        const msg = safeText(campMsg.value).trim();
        const hasMedia = Boolean(campMediaPayload && campMediaPayload.base64);
        if (!msg && !hasMedia) throw new Error('Digite a mensagem ou selecione uma mídia.');

        if (campRun.running) throw new Error('Já existe uma execução em andamento.');

        // Check if scheduling
        const isScheduled = scheduleLater && scheduleLater.checked;
        if (isScheduled) {
          const scheduledTime = scheduleDateTime ? scheduleDateTime.value : '';
          if (!scheduledTime) throw new Error('Selecione data e hora para o agendamento.');
          
          const scheduledDate = new Date(scheduledTime);
          const now = new Date();
          if (scheduledDate <= now) throw new Error('A data/hora deve ser no futuro.');

          // Save scheduled campaign
          await saveScheduledCampaign({
            entries,
            message: msg,
            media: campMediaPayload,
            scheduledTime: scheduledDate.toISOString(),
            createdAt: now.toISOString()
          });

          setCampDomStatus(`✅ Campanha agendada para ${scheduledDate.toLocaleString('pt-BR')}`, 'ok');
          
          // Refresh the scheduled campaigns list
          await refreshScheduledCampaignsList();
          
          return;
        }

        // Show preview modal for immediate campaigns
        showPreviewModal(entries, msg);
      } catch (e) {
        setCampDomStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    campPauseBtn.addEventListener('click', () => {
      if (!campRun.running) return;
      campRun.paused = !campRun.paused;
      campPauseBtn.textContent = campRun.paused ? '▶ Retomar' : '⏸ Pausar';
    });

    campStopBtn.addEventListener('click', () => {
      if (!campRun.running) return;
      campRun.abort = true;
      campRun.paused = false;
      campPauseBtn.textContent = '⏸ Pausar';
      setCampDomStatus('🛑 Parando campanha…', 'err');
    });

    // API mode (backend) - compatible with old backend /api/campaigns shape
    campApiBtn.addEventListener('click', async () => {
      setCampApiStatus('', null);
      try {
        const entries = parseCampaignLines(campNumbers.value);
        if (!entries.length) throw new Error('Cole pelo menos 1 número.');
        
        const msg = safeText(campMsg.value).trim();
        const hasMedia = Boolean(campMediaPayload && campMediaPayload.base64);
        if (!msg && !hasMedia) throw new Error('Digite a mensagem ou selecione uma mídia.');

        const batchSize = clamp(campBatch.value || 25, 1, 200);
        const intervalSeconds = clamp(campInterval.value || 8, 1, 300);

        // backend expects: { message, messages:[{phone, vars?}], batchSize, intervalSeconds, media? }
        // We'll send phone without '+'
        const messages = entries.map((e) => ({
          phone: e.number.replace(/[^\d]/g, ''),
          vars: e.name ? { nome: e.name, numero: e.number } : { numero: e.number }
        }));

        const payload = {
          message: msg, // keep {{nome}} placeholders - backend may replace
          messages,
          batchSize,
          intervalSeconds,
          // Add media support
          media: campMediaPayload ? {
            name: campMediaPayload.name,
            type: campMediaPayload.type,
            base64: campMediaPayload.base64
          } : null
        };

        const resp = await bg('CAMPAIGN_API_CREATE', { payload });
        if (!resp?.ok) throw new Error(resp?.error || 'Falha na API');

        const id = resp?.data?.id || resp?.data?.campaignId || '';
        setCampApiStatus(`✅ Enviado ao backend! ${id ? `Campanha ID: ${id}` : ''}`, 'ok');
      } catch (e) {
        setCampApiStatus(`❌ Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    // -------------------------
    // Contacts extraction
    // -------------------------
    const extractBtn = shadow.getElementById('extractBtn');
    const downloadBtn = shadow.getElementById('downloadBtn');
    const contOut = shadow.getElementById('contOut');
    const contStatus = shadow.getElementById('contStatus');

    function setContStatus(msg, kind) {
      contStatus.textContent = msg || '';
      contStatus.classList.remove('ok','err');
      if (kind === 'ok') contStatus.classList.add('ok');
      if (kind === 'err') contStatus.classList.add('err');
    }

    function extractNumbersDeep() {
      const nums = [];

      // Titles in chat list / header
      const titled = Array.from(document.querySelectorAll('[title]')).slice(0, 1200);
      for (const el of titled) {
        const title = el.getAttribute('title');
        nums.push(...parseNumbersFromText(title));
      }

      // Header text
      nums.push(...parseNumbersFromText(getChatTitle()));

      // (Restrito) Não extrai IDs internos automaticamente.

      return uniq(nums);
    }

    extractBtn.addEventListener('click', async () => {
      setContStatus('', null);
      try {
        const nums = extractNumbersDeep();
        contOut.value = nums.join('\n');
        setContStatus(`Encontrados: ${nums.length}`, nums.length ? 'ok' : 'err');
      } catch (e) {
        setContStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    downloadBtn.addEventListener('click', () => {
      try {
        const nums = safeText(contOut.value).split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        if (!nums.length) throw new Error('Nada para baixar.');
        const csv = ['numero', ...nums].map(csvEscape).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `contatos_whl_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        setContStatus('CSV baixado ✅', 'ok');
        setTimeout(() => URL.revokeObjectURL(url), 1500);
      } catch (e) {
        setContStatus(`Erro: ${e?.message || String(e)}`, 'err');
      }
    });

    // -------------------------
    // Training tab wiring
    // -------------------------
    const trainingStatus = shadow.getElementById('trainingStatus');
    
    const bizName = shadow.getElementById('bizName');
    const bizDescription = shadow.getElementById('bizDescription');
    const bizSegment = shadow.getElementById('bizSegment');
    const bizHours = shadow.getElementById('bizHours');
    
    const policyPayment = shadow.getElementById('policyPayment');
    const policyDelivery = shadow.getElementById('policyDelivery');
    const policyReturns = shadow.getElementById('policyReturns');
    
    const productsFile = shadow.getElementById('productsFile');
    const productsList = shadow.getElementById('productsList');
    const addProductBtn = shadow.getElementById('addProductBtn');
    
    const faqList = shadow.getElementById('faqList');
    const faqQuestion = shadow.getElementById('faqQuestion');
    const faqAnswer = shadow.getElementById('faqAnswer');
    const addFaqBtn = shadow.getElementById('addFaqBtn');
    
    const cannedList = shadow.getElementById('cannedList');
    const cannedTriggers = shadow.getElementById('cannedTriggers');
    const cannedReply = shadow.getElementById('cannedReply');
    const addCannedBtn = shadow.getElementById('addCannedBtn');
    
    const docsFile = shadow.getElementById('docsFile');
    const docsList = shadow.getElementById('docsList');
    
    const toneStyle = shadow.getElementById('toneStyle');
    const toneEmojis = shadow.getElementById('toneEmojis');
    const toneGreeting = shadow.getElementById('toneGreeting');
    const toneClosing = shadow.getElementById('toneClosing');
    
    const testQuestion = shadow.getElementById('testQuestion');
    const testAiBtn = shadow.getElementById('testAiBtn');
    const testResult = shadow.getElementById('testResult');
    const testAnswer = shadow.getElementById('testAnswer');
    const testGoodBtn = shadow.getElementById('testGoodBtn');
    const testBadBtn = shadow.getElementById('testBadBtn');
    const testCorrectBtn = shadow.getElementById('testCorrectBtn');
    
    const statGood = shadow.getElementById('statGood');
    const statBad = shadow.getElementById('statBad');
    const statCorrected = shadow.getElementById('statCorrected');
    const statProducts = shadow.getElementById('statProducts');
    const statFaqs = shadow.getElementById('statFaqs');
    
    const saveKnowledgeBtn = shadow.getElementById('saveKnowledgeBtn');
    const exportKnowledgeBtn = shadow.getElementById('exportKnowledgeBtn');
    const importKnowledgeBtn = shadow.getElementById('importKnowledgeBtn');
    const clearKnowledgeBtn = shadow.getElementById('clearKnowledgeBtn');
    const importFile = shadow.getElementById('importFile');
    
    let currentKnowledge = null;
    let lastTestAnswer = '';

    function setTrainingStatus(msg, kind) {
      trainingStatus.textContent = msg || '';
      trainingStatus.classList.remove('ok','err');
      if (kind === 'ok') trainingStatus.classList.add('ok');
      if (kind === 'err') trainingStatus.classList.add('err');
    }

    async function loadKnowledgeUI() {
      try {
        currentKnowledge = await getKnowledge();
        
        // Business
        bizName.value = currentKnowledge.business.name || '';
        bizDescription.value = currentKnowledge.business.description || '';
        bizSegment.value = currentKnowledge.business.segment || '';
        bizHours.value = currentKnowledge.business.hours || '';
        
        // Policies
        policyPayment.value = currentKnowledge.policies.payment || '';
        policyDelivery.value = currentKnowledge.policies.delivery || '';
        policyReturns.value = currentKnowledge.policies.returns || '';
        
        // Tone
        toneStyle.value = currentKnowledge.tone.style || 'informal';
        toneEmojis.checked = currentKnowledge.tone.useEmojis !== false;
        toneGreeting.value = currentKnowledge.tone.greeting || '';
        toneClosing.value = currentKnowledge.tone.closing || '';
        
        // Render lists
        renderProducts();
        renderFAQ();
        renderCannedReplies();
        renderDocuments();
        
        // Update stats
        await updateStats();
      } catch (e) {
        setTrainingStatus(`Erro ao carregar: ${e?.message || String(e)}`, 'err');
      }
    }

    function renderProducts() {
      productsList.innerHTML = '';
      if (!currentKnowledge.products.length) {
        productsList.innerHTML = '<div style="font-size:11px;color:var(--muted);padding:8px;">Nenhum produto cadastrado</div>';
        return;
      }
      currentKnowledge.products.forEach((p, idx) => {
        const div = document.createElement('div');
        div.className = 'product-item';
        const stockClass = p.stock > 0 ? 'stock' : 'stock out';
        div.innerHTML = `
          <div class="item-content">
            <div><b>${p.name}</b></div>
            <div><span class="price">R$${p.price.toFixed(2)}</span> • <span class="${stockClass}">${p.stock > 0 ? p.stock + ' em estoque' : 'Esgotado'}</span></div>
            ${p.description ? '<div style="font-size:10px;color:var(--muted);">' + p.description + '</div>' : ''}
          </div>
          <div class="item-actions">
            <button data-idx="${idx}" class="remove-product danger">✕</button>
          </div>
        `;
        productsList.appendChild(div);
      });
      
      shadow.querySelectorAll('.remove-product').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const idx = parseInt(e.target.dataset.idx);
          currentKnowledge.products.splice(idx, 1);
          renderProducts();
          updateStats();
        });
      });
    }

    function renderFAQ() {
      faqList.innerHTML = '';
      if (!currentKnowledge.faq.length) {
        faqList.innerHTML = '<div style="font-size:11px;color:var(--muted);padding:8px;">Nenhuma FAQ cadastrada</div>';
        return;
      }
      currentKnowledge.faq.forEach((f, idx) => {
        const div = document.createElement('div');
        div.className = 'faq-item';
        div.innerHTML = `
          <div class="item-content">
            <div><b>P:</b> ${f.question}</div>
            <div><b>R:</b> ${f.answer}</div>
          </div>
          <div class="item-actions">
            <button data-idx="${idx}" class="remove-faq danger">✕</button>
          </div>
        `;
        faqList.appendChild(div);
      });
      
      shadow.querySelectorAll('.remove-faq').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const idx = parseInt(e.target.dataset.idx);
          currentKnowledge.faq.splice(idx, 1);
          renderFAQ();
          updateStats();
        });
      });
    }

    function renderCannedReplies() {
      cannedList.innerHTML = '';
      if (!currentKnowledge.cannedReplies.length) {
        cannedList.innerHTML = '<div style="font-size:11px;color:var(--muted);padding:8px;">Nenhuma resposta rápida cadastrada</div>';
        return;
      }
      currentKnowledge.cannedReplies.forEach((c, idx) => {
        const div = document.createElement('div');
        div.className = 'canned-item';
        div.innerHTML = `
          <div class="item-content">
            <div><b>Gatilhos:</b> ${c.triggers.join(', ')}</div>
            <div><b>Resposta:</b> ${c.reply.slice(0, 80)}${c.reply.length > 80 ? '...' : ''}</div>
          </div>
          <div class="item-actions">
            <button data-idx="${idx}" class="remove-canned danger">✕</button>
          </div>
        `;
        cannedList.appendChild(div);
      });
      
      shadow.querySelectorAll('.remove-canned').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const idx = parseInt(e.target.dataset.idx);
          currentKnowledge.cannedReplies.splice(idx, 1);
          renderCannedReplies();
        });
      });
    }

    function renderDocuments() {
      docsList.innerHTML = '';
      if (!currentKnowledge.documents.length) {
        docsList.innerHTML = '<div style="font-size:11px;color:var(--muted);padding:8px;">Nenhum documento enviado</div>';
        return;
      }
      currentKnowledge.documents.forEach((d, idx) => {
        const div = document.createElement('div');
        div.className = 'doc-item';
        div.innerHTML = `
          <div class="item-content">
            <div><b>${d.name}</b></div>
            <div style="font-size:10px;color:var(--muted);">${d.type} • ${(d.size / 1024).toFixed(1)} KB</div>
          </div>
          <div class="item-actions">
            <button data-idx="${idx}" class="remove-doc danger">✕</button>
          </div>
        `;
        docsList.appendChild(div);
      });
      
      shadow.querySelectorAll('.remove-doc').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const idx = parseInt(e.target.dataset.idx);
          currentKnowledge.documents.splice(idx, 1);
          renderDocuments();
        });
      });
    }

    async function updateStats() {
      const stats = await getTrainingStats();
      statGood.textContent = stats.good || 0;
      statBad.textContent = stats.bad || 0;
      statCorrected.textContent = stats.corrected || 0;
      statProducts.textContent = currentKnowledge.products.length;
      statFaqs.textContent = currentKnowledge.faq.length;
    }

    // Event: Products CSV upload
    productsFile.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        try {
          const csvText = ev.target.result;
          const products = parseProductsCSV(csvText);
          currentKnowledge.products.push(...products);
          renderProducts();
          updateStats();
          setTrainingStatus(`${products.length} produtos importados ✅`, 'ok');
        } catch (err) {
          setTrainingStatus(`Erro ao ler CSV: ${err?.message || String(err)}`, 'err');
        }
      };
      reader.readAsText(file);
    });

    // Event: Add product manually
    addProductBtn.addEventListener('click', () => {
      const name = prompt('Nome do produto:');
      if (!name) return;
      const price = parseFloat(prompt('Preço (R$):') || '0');
      const stock = parseInt(prompt('Estoque:') || '0');
      const description = prompt('Descrição (opcional):') || '';
      
      currentKnowledge.products.push({
        id: Date.now() + Math.random(),
        name,
        price,
        stock,
        description
      });
      renderProducts();
      updateStats();
    });

    // Event: Add FAQ
    addFaqBtn.addEventListener('click', () => {
      const q = safeText(faqQuestion.value).trim();
      const a = safeText(faqAnswer.value).trim();
      if (!q || !a) {
        setTrainingStatus('Preencha pergunta e resposta', 'err');
        return;
      }
      currentKnowledge.faq.push({ question: q, answer: a });
      faqQuestion.value = '';
      faqAnswer.value = '';
      renderFAQ();
      updateStats();
      setTrainingStatus('FAQ adicionada ✅', 'ok');
    });

    // Event: Add canned reply
    addCannedBtn.addEventListener('click', () => {
      const triggers = safeText(cannedTriggers.value).trim().split(',').map(t => t.trim()).filter(Boolean);
      const reply = safeText(cannedReply.value).trim();
      if (!triggers.length || !reply) {
        setTrainingStatus('Preencha gatilhos e resposta', 'err');
        return;
      }
      currentKnowledge.cannedReplies.push({ triggers, reply });
      cannedTriggers.value = '';
      cannedReply.value = '';
      renderCannedReplies();
      setTrainingStatus('Resposta rápida adicionada ✅', 'ok');
    });

    // Event: Documents upload
    docsFile.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      if (!files.length) return;
      
      files.forEach(file => {
        currentKnowledge.documents.push({
          name: file.name,
          type: file.type,
          size: file.size,
          uploadedAt: new Date().toISOString()
        });
      });
      
      renderDocuments();
      setTrainingStatus(`${files.length} documento(s) adicionado(s) ✅`, 'ok');
    });

    // Event: Test AI
    testAiBtn.addEventListener('click', async () => {
      const question = safeText(testQuestion.value).trim();
      if (!question) {
        setTrainingStatus('Digite uma pergunta de teste', 'err');
        return;
      }
      
      testAiBtn.disabled = true;
      setTrainingStatus('Testando IA...', null);
      
      try {
        // Check canned replies first
        const cannedResponse = checkCannedReply(question, currentKnowledge.cannedReplies);
        if (cannedResponse) {
          lastTestAnswer = cannedResponse;
          testAnswer.textContent = '🚀 RESPOSTA RÁPIDA:\n' + cannedResponse;
          testResult.style.display = 'block';
          setTrainingStatus('Resposta rápida encontrada! ✅', 'ok');
          testAiBtn.disabled = false;
          return;
        }
        
        // Call AI
        const response = await aiChat({
          mode: 'reply',
          extraInstruction: '',
          transcript: `Usuário: ${question}`,
          memory: null,
          chatTitle: 'Teste de IA'
        });
        
        lastTestAnswer = response;
        testAnswer.textContent = response;
        testResult.style.display = 'block';
        setTrainingStatus('Resposta gerada ✅', 'ok');
      } catch (e) {
        setTrainingStatus(`Erro ao testar: ${e?.message || String(e)}`, 'err');
      } finally {
        testAiBtn.disabled = false;
      }
    });

    // Event: Test feedback
    testGoodBtn.addEventListener('click', async () => {
      const stats = await getTrainingStats();
      stats.good = (stats.good || 0) + 1;
      await saveTrainingStats(stats);
      await updateStats();
      setTrainingStatus('Feedback registrado ✅', 'ok');
    });

    testBadBtn.addEventListener('click', async () => {
      const stats = await getTrainingStats();
      stats.bad = (stats.bad || 0) + 1;
      await saveTrainingStats(stats);
      await updateStats();
      setTrainingStatus('Feedback registrado ✅', 'ok');
    });

    testCorrectBtn.addEventListener('click', async () => {
      const correction = prompt('Digite a resposta correta:', lastTestAnswer);
      if (!correction) return;
      
      const stats = await getTrainingStats();
      stats.corrected = (stats.corrected || 0) + 1;
      await saveTrainingStats(stats);
      await updateStats();
      
      // Could save as example
      await addExample({ 
        user: `Contexto:\nUsuário: ${safeText(testQuestion.value)}\n\nGere uma resposta:`, 
        assistant: correction 
      });
      
      setTrainingStatus('Correção salva como exemplo ✅', 'ok');
    });

    // Event: Save knowledge
    saveKnowledgeBtn.addEventListener('click', async () => {
      try {
        // Collect all form data
        currentKnowledge.business = {
          name: safeText(bizName.value).trim(),
          description: safeText(bizDescription.value).trim(),
          segment: safeText(bizSegment.value).trim(),
          hours: safeText(bizHours.value).trim()
        };
        
        currentKnowledge.policies = {
          payment: safeText(policyPayment.value).trim(),
          delivery: safeText(policyDelivery.value).trim(),
          returns: safeText(policyReturns.value).trim()
        };
        
        currentKnowledge.tone = {
          style: toneStyle.value,
          useEmojis: toneEmojis.checked,
          greeting: safeText(toneGreeting.value).trim(),
          closing: safeText(toneClosing.value).trim()
        };
        
        await saveKnowledge(currentKnowledge);
        setTrainingStatus('Conhecimento salvo ✅', 'ok');
        
        // Clear cache to force reload
        whlCache.delete('settings');
      } catch (e) {
        setTrainingStatus(`Erro ao salvar: ${e?.message || String(e)}`, 'err');
      }
    });

    // Event: Export JSON
    exportKnowledgeBtn.addEventListener('click', async () => {
      try {
        const knowledge = await getKnowledge();
        const json = JSON.stringify(knowledge, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `whl_knowledge_${new Date().toISOString().slice(0,10)}.json`;
        a.click();
        setTrainingStatus('JSON exportado ✅', 'ok');
        setTimeout(() => URL.revokeObjectURL(url), 1500);
      } catch (e) {
        setTrainingStatus(`Erro ao exportar: ${e?.message || String(e)}`, 'err');
      }
    });

    // Event: Import JSON
    importKnowledgeBtn.addEventListener('click', () => {
      importFile.click();
    });

    importFile.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      const reader = new FileReader();
      reader.onload = async (ev) => {
        try {
          const json = JSON.parse(ev.target.result);
          currentKnowledge = { ...defaultKnowledge, ...json };
          await saveKnowledge(currentKnowledge);
          await loadKnowledgeUI();
          setTrainingStatus('Conhecimento importado ✅', 'ok');
        } catch (err) {
          setTrainingStatus(`Erro ao importar: ${err?.message || String(err)}`, 'err');
        }
      };
      reader.readAsText(file);
    });

    // Event: Clear all
    clearKnowledgeBtn.addEventListener('click', async () => {
      if (!confirm('Deseja realmente limpar todo o conhecimento? Esta ação não pode ser desfeita.')) return;
      
      try {
        currentKnowledge = { ...defaultKnowledge };
        await saveKnowledge(currentKnowledge);
        await loadKnowledgeUI();
        setTrainingStatus('Conhecimento limpo ✅', 'ok');
      } catch (e) {
        setTrainingStatus(`Erro ao limpar: ${e?.message || String(e)}`, 'err');
      }
    });

    // Load knowledge when training tab is opened
    tabs.forEach(t => {
      if (t.dataset.tab === 'training') {
        t.addEventListener('click', () => {
          loadKnowledgeUI();
        });
      }
    });

    // Initial load if training tab is already active
    if (tabs.find(t => t.classList.contains('active') && t.dataset.tab === 'training')) {
      loadKnowledgeUI();
    }
  }

  // -------------------------
  // Copilot Mode - Auto Send Decision Logic
  // -------------------------

  /**
   * Decide if message can be sent automatically by AI
   */
  async function canAutoSend(message, chatTitle) {
    try {
      const settings = await getSettingsCached();
      
      // 1. Copilot must be enabled
      if (!settings.copilotEnabled) {
        return { canSend: false, reason: 'copilot_disabled' };
      }
      
      // 2. Get confidence data
      const confidenceResp = await bg('GET_CONFIDENCE', {});
      if (!confidenceResp?.ok) {
        return { canSend: false, reason: 'confidence_unavailable' };
      }
      
      const { score, config } = confidenceResp;
      
      // 3. Score must be above threshold
      if (score < (config?.copilot_threshold || 70)) {
        return { 
          canSend: false, 
          reason: 'below_threshold', 
          score, 
          threshold: config?.copilot_threshold 
        };
      }
      
      // 4. Check message type
      const knowledge = await getKnowledge();
      
      // Simple greetings - can auto-respond
      if (isSimpleGreeting(message)) {
        return { 
          canSend: true, 
          reason: 'greeting', 
          confidence: 95,
          answer: null // Will be generated by AI
        };
      }
      
      // FAQ match - can auto-respond if high confidence
      const faqMatch = findFAQMatch(message, knowledge.faq);
      if (faqMatch && faqMatch.confidence > 80) {
        return { 
          canSend: true, 
          reason: 'faq_match', 
          confidence: faqMatch.confidence, 
          answer: faqMatch.answer 
        };
      }
      
      // Canned reply match - can auto-respond
      const cannedMatch = checkCannedReply(message, knowledge.cannedReplies);
      if (cannedMatch) {
        return { 
          canSend: true, 
          reason: 'canned_reply', 
          confidence: 90, 
          answer: cannedMatch 
        };
      }
      
      // Product match - can auto-respond if high confidence
      const productMatch = findProductMatch(message, knowledge.products);
      if (productMatch && productMatch.confidence > 75) {
        return { 
          canSend: true, 
          reason: 'product_match', 
          confidence: productMatch.confidence,
          answer: null // Will be generated by AI with product context
        };
      }
      
      // Complex conversation - assisted mode
      return { canSend: false, reason: 'complex_conversation' };
      
    } catch (e) {
      warn('Error in canAutoSend:', e);
      return { canSend: false, reason: 'error', error: e?.message };
    }
  }

  /**
   * Check if message is a simple greeting
   */
  function isSimpleGreeting(message) {
    const greetings = [
      'oi', 'olá', 'ola', 'oie', 'eae', 'eaí', 'e ai', 'e aí',
      'bom dia', 'boa tarde', 'boa noite',
      'hey', 'hi', 'hello', 'olá'
    ];
    const normalized = message.toLowerCase().trim();
    
    // Check exact match or starts with greeting
    return greetings.some(g => 
      normalized === g || 
      normalized === g + '!' || 
      normalized === g + '.' ||
      normalized.startsWith(g + ' ') || 
      normalized.startsWith(g + '!')
    );
  }

  /**
   * Find FAQ match with confidence score
   */
  function findFAQMatch(message, faqs) {
    if (!Array.isArray(faqs) || !faqs.length) return null;
    
    const normalized = message.toLowerCase();
    const words = normalized.split(/\s+/).filter(w => w.length > 2);
    
    let bestMatch = null;
    let bestConfidence = 0;
    
    for (const faq of faqs) {
      if (!faq.question || !faq.answer) continue;
      
      const question = faq.question.toLowerCase();
      const questionWords = question.split(/\s+/).filter(w => w.length > 2);
      
      // Count matching words
      const matches = questionWords.filter(qw => 
        words.some(w => w.includes(qw) || qw.includes(w))
      );
      
      const confidence = questionWords.length > 0 
        ? (matches.length / questionWords.length) * 100 
        : 0;
      
      if (confidence > bestConfidence) {
        bestConfidence = confidence;
        bestMatch = { answer: faq.answer, confidence };
      }
    }
    
    return bestMatch;
  }

  /**
   * Check for canned reply match
   */
  function checkCannedReply(message, cannedReplies) {
    if (!Array.isArray(cannedReplies) || !cannedReplies.length) return null;
    
    const normalized = message.toLowerCase().trim();
    
    for (const reply of cannedReplies) {
      if (!reply.trigger || !reply.response) continue;
      
      const trigger = reply.trigger.toLowerCase();
      
      // Exact match or contains trigger
      if (normalized === trigger || normalized.includes(trigger)) {
        return reply.response;
      }
    }
    
    return null;
  }

  /**
   * Find product match with confidence
   */
  function findProductMatch(message, products) {
    if (!Array.isArray(products) || !products.length) return null;
    
    const normalized = message.toLowerCase();
    let bestMatch = null;
    let bestConfidence = 0;
    
    for (const product of products) {
      if (!product.name) continue;
      
      const productName = product.name.toLowerCase();
      const productWords = productName.split(/\s+/).filter(w => w.length > 2);
      
      // Check if product name is mentioned
      const matches = productWords.filter(pw => normalized.includes(pw));
      
      const confidence = productWords.length > 0
        ? (matches.length / productWords.length) * 100
        : 0;
      
      if (confidence > bestConfidence) {
        bestConfidence = confidence;
        bestMatch = { product, confidence };
      }
    }
    
    return bestMatch;
  }

  /**
   * Send feedback to backend about AI response quality
   */
  async function sendConfidenceFeedback(type, metadata = {}) {
    try {
      await bg('UPDATE_CONFIDENCE', {
        payload: {
          action: 'feedback',
          type, // 'good', 'bad', 'correction'
          metadata
        }
      });
    } catch (e) {
      warn('Failed to send confidence feedback:', e);
    }
  }

  /**
   * Record suggestion usage
   */
  async function recordSuggestionUsage(edited = false, metadata = {}) {
    try {
      await bg('UPDATE_CONFIDENCE', {
        payload: {
          action: 'suggestion_used',
          edited,
          metadata
        }
      });
    } catch (e) {
      warn('Failed to record suggestion usage:', e);
    }
  }

  /**
   * Record automatic send
   */
  async function recordAutoSend(metadata = {}) {
    try {
      await bg('UPDATE_CONFIDENCE', {
        payload: {
          action: 'auto_sent',
          metadata
        }
      });
    } catch (e) {
      warn('Failed to record auto send:', e);
    }
  }

  // Mount when possible (document_start friendly)
  function boot() {
    try {
      mount();
    } catch (e) {
      warn('Falha ao montar painel:', e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
