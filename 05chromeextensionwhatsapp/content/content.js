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
  // NOTE: Set to false in production to reduce console noise
  const DEBUG_MODE = false;
  
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

  // FIX 1: Move applyVars to global scope (was inside mount() at line 2716)
  // Used by executeDomCampaignDirectly() which is outside mount()
  function applyVars(msg, entry) {
    let out = safeText(msg);
    out = out.replaceAll('{{nome}}', entry.name || '');
    out = out.replaceAll('{{numero}}', entry.number || '');
    return out;
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
      // FIX 2: Executar diretamente no content script com sendResponse
      (async () => {
        try {
          const { members, message: msg, senderName } = message.payload;
          
          if (!members || !Array.isArray(members) || !msg) {
            sendResponse({ ok: false, error: 'Dados inválidos' });
            return;
          }

          log('Sending team messages to', members.length, 'members');
          
          // Convert team members to campaign-like entries
          const entries = members.map(m => ({
            name: m.name || '', // FIX 4: Nome opcional
            number: normalizePhoneNumber(m.phone), // FIX 3: Usar normalização
            vars: {}
          }));

          // Execute as a campaign
          await executeDomCampaignDirectly(entries, msg, null);
          
          sendResponse({ ok: true, sent: entries.length });
        } catch (e) {
          console.error('[WHL] Error sending team messages:', e);
          sendResponse({ ok: false, error: e.message || String(e) });
        }
      })();
      
      return true; // CRÍTICO: manter canal aberto para resposta assíncrona
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
      
      // FIX 3: Usar normalizePhoneNumber para normalização inteligente
      const normalizedNumber = normalizePhoneNumber(e.number);
      const phoneDigits = normalizedNumber.replace(/[^\d]/g, '');
      
      // FIX 4: Nome é opcional - usar número se não tiver nome
      const displayName = e.name || phoneDigits;
      
      debugLog(`[${i+1}/${entries.length}] Processing: ${displayName} (${phoneDigits})`);
      
      const text = applyVars(msg || '', e).trim();

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

        debugLog(`✅ Success for ${displayName}`);
      } catch (err) {
        debugLog(`❌ Error for ${displayName}:`, err);
        console.error(`[WHL] Error for ${displayName}:`, err);
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

  // FIX 5: Envio de Imagem (DOM) - Melhorias
  async function attachMediaAndSend(mediaPayload, captionText) {
    if (!mediaPayload?.base64) throw new Error('Mídia não carregada.');
    
    debugLog('Iniciando envio de mídia...');
    
    // 1. Encontrar botão de anexo com múltiplos seletores
    // FIX 2: Updated selectors for WhatsApp Web 2024/2025
    const attachSelectors = [
      'button[aria-label="Anexar"]',           // NEW - Working selector 2024/2025
      'span[data-icon="plus-rounded"]',        // NEW - Working selector 2024/2025
      'footer button[aria-label*="Anexar"]',   // Fallback
      'footer button[title*="Anexar"]',        // Fallback
      'span[data-icon="plus"]',                // Legacy
      'span[data-icon="attach-menu-plus"]',    // Legacy
      'span[data-icon="clip"]',                // Legacy
      '[data-testid="attach-menu-plus"]'       // Legacy
    ];
    
    let attachBtn = null;
    for (const sel of attachSelectors) {
      const el = document.querySelector(sel);
      if (el) {
        attachBtn = el.closest('button') || el.closest('div[role="button"]') || el;
        if (attachBtn) {
          debugLog('Botão de anexo encontrado:', sel);
          break;
        }
      }
    }
    
    if (!attachBtn) throw new Error('Botão de anexo não encontrado.');

    attachBtn.click();
    await sleep(1000); // FIX 3: Increased from 800ms to 1000ms

    // Aguardar menu de anexo aparecer
    await sleep(500);

    // 2. Encontrar input de arquivo com retry logic
    // FIX 3: Improved robustness with multiple retry attempts
    const inputSelectors = [
      'input[type="file"][accept*="image"]',
      'input[type="file"][accept*="video"]',
      'input[type="file"]'
    ];
    
    let input = null;
    for (let attempt = 0; attempt < 5; attempt++) {
      for (const sel of inputSelectors) {
        input = document.querySelector(sel);
        if (input && input.isConnected) {
          debugLog('Input de arquivo encontrado:', sel, 'na tentativa', attempt + 1);
          break;
        }
      }
      if (input) break;
      await sleep(300);
    }
    
    if (!input) throw new Error('Input de arquivo não encontrado.');

    // 3. Criar arquivo e disparar eventos
    const bytes = b64ToBytes(mediaPayload.base64);
    const mimeType = mediaPayload.type || 'image/jpeg';
    const fileName = mediaPayload.name || `image_${Date.now()}.jpg`;
    
    const blob = new Blob([bytes], { type: mimeType });
    const file = new File([blob], fileName, { type: mimeType, lastModified: Date.now() });

    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    
    // Disparar múltiplos eventos para garantir detecção
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    
    debugLog('Arquivo anexado, aguardando preview...');

    // 4. Aguardar preview com timeout maior
    let sendBtn = null;
    const maxAttempts = 60; // 18 segundos
    
    for (let i = 0; i < maxAttempts; i++) {
      await sleep(300);
      
      // Múltiplos seletores para botão de envio na dialog
      const sendSelectors = [
        '[data-testid="send"]',
        'span[data-icon="send"]',
        'button[aria-label*="Enviar"]',
        'div[role="button"][aria-label*="Enviar"]'
      ];
      
      for (const sel of sendSelectors) {
        const btn = document.querySelector(`div[role="dialog"] ${sel}, [data-testid="media-viewer"] ${sel}`);
        if (btn) {
          sendBtn = btn.closest('button') || btn.closest('div[role="button"]') || btn;
          break;
        }
      }
      
      if (sendBtn) {
        debugLog('Botão de envio encontrado após', i * 300, 'ms');
        break;
      }
    }
    
    if (!sendBtn) throw new Error('Preview não apareceu ou botão enviar não encontrado.');

    // 5. Adicionar legenda se houver
    if (captionText?.trim()) {
      const captionBox = document.querySelector('div[role="dialog"] [contenteditable="true"]');
      if (captionBox) {
        captionBox.focus();
        document.execCommand('selectAll', false, null);
        document.execCommand('insertText', false, captionText.trim());
        captionBox.dispatchEvent(new InputEvent('input', { bubbles: true }));
        await sleep(200);
      }
    }

    // 6. Clicar enviar
    sendBtn.click();
    await sleep(1500);
    
    debugLog('Mídia enviada com sucesso!');
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

  // -------------------------
  // Phone Normalization (FIX 3)
  // -------------------------
  // NOTE: This function assumes Brazilian phone format (+55) as default
  // For international use, this should be made configurable
  function normalizePhoneNumber(phone) {
    // Remover tudo exceto dígitos e +
    let digits = String(phone || '').replace(/[^\d+]/g, '');
    
    // Se não tem +, assumir Brasil (+55)
    // LIMITATION: Hardcoded to Brazilian format for now
    if (!digits.startsWith('+')) {
      // Se começa com 55, adicionar +
      if (digits.startsWith('55') && digits.length >= 12) {
        digits = '+' + digits;
      } else {
        // Default: assumir que é número brasileiro sem código do país
        digits = '+55' + digits;
      }
    }
    
    // Extrair apenas dígitos para validação
    const onlyDigits = digits.replace(/\D/g, '');
    
    // Validar tamanho (mínimo 10, máximo 15 dígitos)
    if (onlyDigits.length < 10 || onlyDigits.length > 15) {
      debugLog('[WHL] Número possivelmente inválido:', phone, '→', digits);
    }
    
    // NÃO forçar dígito 9 - números fixos e alguns celulares antigos não têm
    // Retornar como está, respeitando o formato original
    return digits;
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

    // Ensure WhatsApp Web main tab is focused
    window.focus();
    await sleep(100);

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

    // Configuration: validation parameters
    const MAX_COMPOSER_CHECK_ATTEMPTS = 20;
    const COMPOSER_CHECK_DELAY_MS = 300;
    const VALIDATION_SKIP_THRESHOLD = 15; // Skip validation after this many attempts
    const PHONE_SUFFIX_MATCH_LENGTH = 8;  // Match last 8 digits for validation
    
    // VALIDAÇÃO CRÍTICA: Verificar se chat correto foi aberto (FIX 6)
    debugLog('Verificando se composer apareceu e chat está correto...');
    for (let i = 0; i < MAX_COMPOSER_CHECK_ATTEMPTS; i++) {
      await sleep(COMPOSER_CHECK_DELAY_MS);
      const composer = findComposer();
      if (composer) {
        // Verify we're in the correct chat by checking header/title
        // This helps prevent sending to wrong chat if another tab was focused
        const currentTitle = getChatTitle();
        const titleDigits = currentTitle.replace(/\D/g, '');
        
        // Check if current chat contains the target digits
        // We match the last N digits to handle international prefixes flexibly
        const isCorrectChat = titleDigits.includes(digits.slice(-PHONE_SUFFIX_MATCH_LENGTH)) || 
                             digits.includes(titleDigits.slice(-PHONE_SUFFIX_MATCH_LENGTH)) ||
                             titleDigits === digits;
        
        // Skip validation after threshold to avoid infinite waiting
        // This is a fallback in case chat title doesn't include phone number
        if (isCorrectChat || i > VALIDATION_SKIP_THRESHOLD) {
          debugLog('✅ Chat aberto com sucesso (composer encontrado)');
          debugLog('   Chat title:', currentTitle);
          debugLog('   Target digits:', digits);
          if (!isCorrectChat) {
            debugLog('   ⚠️ Validation skipped after threshold - proceeding with caution');
          }
          return true;
        }
        
        debugLog(`⚠️ Chat aberto mas título não corresponde (${currentTitle} vs ${digits}), tentando novamente...`);
      }
    }
    
    debugLog('❌ Chat não abriu ou não corresponde ao número correto');
    throw new Error('Chat não abriu (composer não encontrado ou chat incorreto).');
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
        --bg: rgba(10, 12, 24, 0.95);
        --panel: rgba(13, 16, 32, 0.95);
        --stroke: rgba(255,255,255,.10);
        --stroke2: rgba(139,92,246,.35);
        --stroke3: rgba(59,130,246,.25);
        --text: rgba(255, 255, 255, 0.95);
        --text-secondary: rgba(255, 255, 255, 0.80);
        --muted: rgba(255, 255, 255, 0.65);
        --danger: #ef4444;
        --ok: #22c55e;
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
        color: var(--text) !important;
        white-space: pre-wrap;
        line-height:1.35;
      }
      .status.ok{ color: var(--ok) !important; }
      .status.err{ color: var(--danger) !important; }
      
      /* FIX 7: Garantir texto branco em todas as áreas */
      #chatOut, .chatOut {
        color: white !important;
        background: rgba(5, 7, 15, 0.7) !important;
      }
      
      p, span, div, label {
        color: var(--text) !important;
      }

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

    // applyVars moved to global scope (after line 73) - FIX 1

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

  // -------------------------
  // Quick Replies Listener
  // -------------------------
  let quickRepliesListener = null;

  // -------------------------
  // Quick Replies - Autocomplete Inline (NEW)
  // -------------------------
  function createQuickReplySuggestionUI() {
    const suggestionBox = document.createElement('div');
    suggestionBox.id = 'whl-quick-reply-suggestion';
    suggestionBox.style.cssText = `
      position: fixed;
      background: rgba(17, 20, 36, 0.95);
      border: 1px solid rgba(139, 92, 246, 0.5);
      border-radius: 8px;
      padding: 8px 12px;
      color: white;
      font-size: 13px;
      cursor: pointer;
      z-index: 9999;
      display: none;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      max-width: 400px;
    `;
    document.body.appendChild(suggestionBox);
    return suggestionBox;
  }

  function showQuickReplySuggestion(composer, quickReply) {
    let box = document.getElementById('whl-quick-reply-suggestion');
    if (!box) {
      box = createQuickReplySuggestionUI();
    }
    
    const rect = composer.getBoundingClientRect();
    
    box.innerHTML = `
      <div style="font-size: 11px; color: #888; margin-bottom: 4px;">Resposta rápida:</div>
      <div style="font-weight: 600;">/${quickReply.trigger}</div>
      <div style="margin-top: 4px; color: #ccc;">${quickReply.response.slice(0, 100)}${quickReply.response.length > 100 ? '...' : ''}</div>
    `;
    
    box.style.bottom = (window.innerHeight - rect.top + 10) + 'px';
    box.style.left = rect.left + 'px';
    box.style.display = 'block';
    
    // Click handler - substituir texto
    box.onclick = async () => {
      try {
        debugLog('Quick reply suggestion clicked:', quickReply.trigger);
        await insertIntoComposer(quickReply.response, false, false);
        hideQuickReplySuggestion();
      } catch (e) {
        debugLog('Error inserting quick reply:', e);
      }
    };
  }

  function hideQuickReplySuggestion() {
    const box = document.getElementById('whl-quick-reply-suggestion');
    if (box) box.style.display = 'none';
  }

  async function initQuickRepliesListener() {
    // Prevent multiple listeners
    if (quickRepliesListener) return;

    // Create suggestion UI
    createQuickReplySuggestionUI();
    
    // Declare debounce timer in closure
    let quickRepliesDebounceTimer = null;

    // Input listener for real-time detection
    const inputListener = async () => {
      try {
        const composer = findComposer();
        if (!composer) return;

        const text = composer.textContent || composer.innerText || '';
        
        // Verificar se texto começa com /
        if (text.startsWith('/')) {
          const trigger = text.slice(1).toLowerCase(); // remover /
          const settings = await getSettingsCached();
          const quickReplies = settings.quickReplies || [];
          
          // Buscar match
          const match = quickReplies.find(qr => 
            qr.trigger && (
              qr.trigger.toLowerCase() === trigger ||
              qr.trigger.toLowerCase().startsWith(trigger)
            )
          );
          
          if (match) {
            showQuickReplySuggestion(composer, match);
          } else {
            hideQuickReplySuggestion();
          }
        } else {
          hideQuickReplySuggestion();
        }
      } catch (e) {
        debugLog('Quick reply input error:', e);
      }
    };

    // Add input listener with debouncing
    quickRepliesListener = () => {
      clearTimeout(quickRepliesDebounceTimer);
      quickRepliesDebounceTimer = setTimeout(inputListener, 100);
    };

    // Listen on document for input events (bubbles up from composer)
    document.addEventListener('input', quickRepliesListener, true);
    
    // Hide suggestion when clicking outside
    document.addEventListener('click', (e) => {
      const box = document.getElementById('whl-quick-reply-suggestion');
      if (box && !box.contains(e.target)) {
        hideQuickReplySuggestion();
      }
    }, true);
    
    debugLog('✅ Quick replies autocomplete initialized');
  }

  // Mount when possible (document_start friendly)
  function boot() {
    try {
      mount();
      // Initialize quick replies listener after a short delay
      setTimeout(() => {
        initQuickRepliesListener();
      }, 2000);
    } catch (e) {
      warn('Falha ao montar painel:', e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }

  // ============================================================
  // WHATSAPP AUTOMATION HELPER 2024/2025
  // ============================================================

  /**
   * WhatsApp Web Automation Helper - Versão 2024/2025
   * Implementa todas as correções necessárias para compatibilidade atual
   */

  class WhatsAppAutomation {
    constructor() {
      // Seletores atualizados para 2024/2025
      this.selectors = {
        // Dialog de mídia - múltiplas opções para compatibilidade
        mediaDialog: [
          '[data-testid="media-viewer-modal"]',
          '[data-animate-modal-popup="true"]',
          '.media-canvas-renderer',
          '._2Ts6i._2xAQV',
          '[data-animate-media-viewer="true"]'
        ],
        
        // Botão enviar - versões atualizadas
        sendButton: [
          'span[data-icon="wds-ic-send-filled"]',  // NOVO - 2024/2025
          'span[data-icon="send"]',                // Fallback
          'button[aria-label*="Enviar"]',
          'button[data-testid="compose-btn-send"]',
          '[data-icon="send-light"]'
        ],
        
        // Botão enviar no dialog de mídia
        mediaSendButton: [
          'span[data-icon="wds-ic-send-filled"]',  // NOVO
          '[data-testid="media-send-button"]',
          'div[role="button"][aria-label*="Enviar"]',
          'button._1E0Oz',
          'span[data-icon="send"]'
        ],
        
        // Área de input de mensagem
        messageInput: [
          'div[contenteditable="true"][data-tab="10"]',
          '[data-testid="conversation-compose-box-input"]',
          'div[role="textbox"]',
          'div._3Uu1_'
        ],
        
        // Caption da mídia (legenda)
        mediaCaption: [
          '[data-testid="media-caption-input"]',
          'div[contenteditable="true"][data-tab="10"]',
          '.media-caption-input'
        ],
        
        // Busca de contatos
        searchBox: [
          '[data-testid="chat-list-search"]',
          'div[contenteditable="true"][data-tab="3"]',
          'div[role="textbox"][title*="Pesquisar"]'
        ],
        
        // Resultados da busca
        searchResults: [
          '[data-testid="search-result-chat"]',
          'div[role="listitem"]',
          '._3m_Xw'
        ]
      };
    }

    /**
     * Abre chat usando URL direta (funciona mesmo com contatos salvos)
     */
    async openChatByUrl(phoneNumber) {
      try {
        const digits = phoneNumber.replace(/\D/g, '');
        
        if (digits.length < 10) {
          throw new Error('Número de telefone inválido');
        }
        
        const url = `https://web.whatsapp.com/send?phone=${digits}`;
        window.location.href = url;
        
        console.log(`[WhatsApp] Abrindo chat via URL: ${url}`);
        
        await this.waitForChatLoad(10000);
        
        return true;
      } catch (error) {
        console.error('[WhatsApp] Erro ao abrir chat por URL:', error);
        return false;
      }
    }

    /**
     * Abre chat usando busca (Fallback)
     */
    async openChatBySearch(contactName) {
      try {
        console.log(`[WhatsApp] Buscando contato: ${contactName}`);
        
        const searchBox = this.findElement(this.selectors.searchBox);
        if (!searchBox) {
          throw new Error('Caixa de busca não encontrada');
        }
        
        searchBox.focus();
        searchBox.textContent = '';
        
        await this.typeText(searchBox, contactName);
        await this.sleep(1500);
        
        const results = document.querySelectorAll(this.selectors.searchResults.join(','));
        
        if (results.length === 0) {
          throw new Error('Nenhum resultado encontrado');
        }
        
        results[0].click();
        
        console.log('[WhatsApp] Contato selecionado');
        
        await this.waitForChatLoad(5000);
        
        return true;
      } catch (error) {
        console.error('[WhatsApp] Erro ao buscar contato:', error);
        return false;
      }
    }

    /**
     * Fallback inteligente: URL primeiro, busca depois
     */
    async openChat(identifier) {
      const isPhoneNumber = /^\+?\d{10,}$/.test(identifier.replace(/\D/g, ''));
      
      if (isPhoneNumber) {
        console.log('[WhatsApp] Detectado número - usando URL direta');
        const success = await this.openChatByUrl(identifier);
        
        if (success) return true;
        
        console.log('[WhatsApp] URL falhou, tentando busca como fallback');
      }
      
      return await this.openChatBySearch(identifier);
    }

    /**
     * Encontra botão enviar atualizado
     */
    findSendButton() {
      if (this.isMediaDialogOpen()) {
        return this.findMediaSendButton();
      }
      
      const button = this.findElement(this.selectors.sendButton);
      
      if (button) {
        console.log('[WhatsApp] Botão enviar encontrado:', button);
        return button;
      }
      
      console.warn('[WhatsApp] Botão enviar não encontrado');
      return null;
    }

    /**
     * Encontra botão enviar no dialog de mídia
     */
    findMediaSendButton() {
      const dialog = this.getMediaDialog();
      if (!dialog) {
        console.warn('[WhatsApp] Dialog de mídia não está aberto');
        return null;
      }
      
      for (const selector of this.selectors.mediaSendButton) {
        const button = dialog.querySelector(selector);
        if (button) {
          console.log('[WhatsApp] Botão enviar mídia encontrado:', selector);
          return button;
        }
      }
      
      console.warn('[WhatsApp] Botão enviar mídia não encontrado');
      return null;
    }

    /**
     * Verifica se dialog de mídia está aberto
     */
    isMediaDialogOpen() {
      return this.selectors.mediaDialog.some(selector => 
        document.querySelector(selector) !== null
      );
    }

    /**
     * Obtém elemento do dialog de mídia
     */
    getMediaDialog() {
      for (const selector of this.selectors.mediaDialog) {
        const dialog = document.querySelector(selector);
        if (dialog) {
          console.log('[WhatsApp] Dialog encontrado:', selector);
          return dialog;
        }
      }
      return null;
    }

    /**
     * Aguarda dialog de mídia abrir
     */
    async waitForMediaDialog(timeout = 5000) {
      const startTime = Date.now();
      
      return new Promise((resolve, reject) => {
        const interval = setInterval(() => {
          const dialog = this.getMediaDialog();
          
          if (dialog) {
            clearInterval(interval);
            resolve(dialog);
            return;
          }
          
          if (Date.now() - startTime > timeout) {
            clearInterval(interval);
            reject(new Error('Timeout aguardando dialog de mídia'));
          }
        }, 100);
      });
    }

    /**
     * Aguarda chat carregar
     */
    async waitForChatLoad(timeout = 10000) {
      const startTime = Date.now();
      
      return new Promise((resolve, reject) => {
        const interval = setInterval(() => {
          const input = this.findElement(this.selectors.messageInput);
          
          if (input) {
            clearInterval(interval);
            resolve(true);
            return;
          }
          
          if (Date.now() - startTime > timeout) {
            clearInterval(interval);
            reject(new Error('Timeout aguardando chat carregar'));
          }
        }, 200);
      });
    }

    /**
     * Envia mensagem de texto
     */
    async sendTextMessage(message) {
      try {
        const input = this.findElement(this.selectors.messageInput);
        if (!input) {
          throw new Error('Input de mensagem não encontrado');
        }
        
        await this.typeText(input, message);
        await this.sleep(500);
        
        const sendBtn = this.findSendButton();
        if (!sendBtn) {
          throw new Error('Botão enviar não encontrado');
        }
        
        sendBtn.click();
        console.log('[WhatsApp] Mensagem enviada:', message);
        
        return true;
      } catch (error) {
        console.error('[WhatsApp] Erro ao enviar mensagem:', error);
        return false;
      }
    }

    /**
     * Envia mídia com legenda
     */
    async sendMediaWithCaption(caption = '') {
      try {
        await this.waitForMediaDialog(5000);
        
        if (caption) {
          const captionInput = this.findElement(this.selectors.mediaCaption);
          if (captionInput) {
            await this.typeText(captionInput, caption);
            await this.sleep(300);
          }
        }
        
        const sendBtn = this.findMediaSendButton();
        if (!sendBtn) {
          throw new Error('Botão enviar mídia não encontrado');
        }
        
        sendBtn.click();
        console.log('[WhatsApp] Mídia enviada com legenda:', caption);
        
        return true;
      } catch (error) {
        console.error('[WhatsApp] Erro ao enviar mídia:', error);
        return false;
      }
    }

    findElement(selectors) {
      if (!Array.isArray(selectors)) selectors = [selectors];
      
      for (const selector of selectors) {
        const element = document.querySelector(selector);
        if (element) return element;
      }
      
      return null;
    }

    async typeText(element, text) {
      element.focus();
      
      // Build text incrementally in memory, then update DOM
      let currentText = '';
      for (const char of text) {
        currentText += char;
        element.textContent = currentText;
        element.dispatchEvent(new InputEvent('input', { bubbles: true, data: char }));
        element.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, key: char }));
        element.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true, key: char }));
        await this.sleep(50 + Math.random() * 100);
      }
    }

    sleep(ms) {
      return new Promise(resolve => setTimeout(resolve, ms));
    }

    debugButtons() {
      console.log('=== DEBUG: Botões Encontrados ===');
      
      const allButtons = document.querySelectorAll('button, span[data-icon], div[role="button"]');
      console.log(`Total de botões no DOM: ${allButtons.length}`);
      
      allButtons.forEach((btn, i) => {
        const icon = btn.getAttribute('data-icon');
        const ariaLabel = btn.getAttribute('aria-label');
        const testId = btn.getAttribute('data-testid');
        
        if (icon || (ariaLabel && ariaLabel.includes('nviar'))) {
          console.log(`[${i}]`, { icon, ariaLabel, testId, element: btn });
        }
      });
      
      console.log('=== Fim Debug ===');
    }
  }

  class WhatsAppMonitor {
    constructor(automation) {
      this.wa = automation;
      this.isMonitoring = false;
      this.lastDialogState = false;
    }
    
    start() {
      if (this.isMonitoring) {
        console.log('[Monitor] Já está rodando');
        return;
      }
      
      this.isMonitoring = true;
      console.log('[Monitor] 🟢 Monitoramento iniciado');
      
      this.intervalId = setInterval(() => {
        const isOpen = this.wa.isMediaDialogOpen();
        
        if (isOpen !== this.lastDialogState) {
          if (isOpen) {
            console.log('[Monitor] 📸 Dialog de mídia aberto!');
            const dialog = this.wa.getMediaDialog();
            const sendBtn = this.wa.findMediaSendButton();
            
            console.log('[Monitor] Dialog:', dialog ? '✓' : '✗');
            console.log('[Monitor] Botão Enviar:', sendBtn ? '✓' : '✗');
            
            if (sendBtn) {
              console.log('[Monitor] 🎯 Tudo pronto para enviar!');
            }
          } else {
            console.log('[Monitor] Dialog de mídia fechado');
          }
          
          this.lastDialogState = isOpen;
        }
      }, 500);
    }
    
    stop() {
      if (!this.isMonitoring) {
        console.log('[Monitor] Não está rodando');
        return;
      }
      
      clearInterval(this.intervalId);
      this.isMonitoring = false;
      console.log('[Monitor] 🔴 Monitoramento parado');
    }
  }

  class WhatsAppTextMonitor {
    constructor(automation) {
      this.wa = automation;
      this.isMonitoring = false;
      this.lastMessages = [];
      this.callbacks = {
        onNewMessage: [],
        onNewChat: [],
        onTyping: []
      };
      this.currentChatId = null;
      this.intervalId = null;
      
      this.selectors = {
        messageContainer: [
          '[data-testid="conversation-panel-messages"]',
          '.message-list',
          '._33LGR',
          '[data-testid="msg-container"]'
        ],
        messages: [
          '[data-testid="msg-container"]',
          '.message-in, .message-out',
          '._22Msk',
          '.focusable-list-item'
        ],
        typingIndicator: [
          '[data-testid="typing"]',
          '._2QZ0V',
          '.typing-indicator'
        ],
        currentChat: [
          '[data-testid="conversation-header"]',
          '.chat-active',
          '._2gzeB'
        ],
        messageText: [
          '[data-testid="msg-text"]',
          '.selectable-text',
          '._11JPr'
        ],
        messageInfo: [
          '[data-testid="msg-meta"]',
          '.message-datetime',
          '._1beEj'
        ],
        messageAuthor: [
          '[data-testid="msg-name"]',
          '.message-author',
          '._3FuDI'
        ]
      };
    }
    
    start(options = {}) {
      const defaultOptions = {
        interval: 1000,
        detectTyping: true,
        trackMessageStatus: true,
        storeHistory: true,
        maxHistoryLength: 50,
        debug: false
      };
      
      this.config = {...defaultOptions, ...options};
      
      if (this.isMonitoring) {
        console.log('[TextMonitor] 🟡 Monitor já está ativo');
        return;
      }
      
      this.isMonitoring = true;
      console.log('[TextMonitor] 🟢 Monitoramento de texto iniciado');
      this.lastCheck = Date.now();
      
      this.captureCurrentMessages();
      this.intervalId = setInterval(() => this.check(), this.config.interval);
    }
    
    stop() {
      if (!this.isMonitoring) {
        console.log('[TextMonitor] Não está ativo');
        return;
      }
      
      clearInterval(this.intervalId);
      this.isMonitoring = false;
      console.log('[TextMonitor] 🔴 Monitoramento de texto parado');
    }
    
    on(event, callback) {
      if (!this.callbacks[event]) {
        console.error(`[TextMonitor] Evento desconhecido: ${event}`);
        return;
      }
      
      this.callbacks[event].push(callback);
      console.log(`[TextMonitor] Callback registrado para ${event}`);
      
      return () => this.off(event, callback);
    }
    
    off(event, callback) {
      if (!this.callbacks[event]) return false;
      
      const index = this.callbacks[event].indexOf(callback);
      if (index !== -1) {
        this.callbacks[event].splice(index, 1);
        return true;
      }
      
      return false;
    }
    
    check() {
      this.checkCurrentChat();
      this.checkNewMessages();
      if (this.config.detectTyping) {
        this.checkTypingStatus();
      }
    }
    
    checkCurrentChat() {
      const header = this.findElement(this.selectors.currentChat);
      if (!header) return;
      
      const chatTitle = header.textContent.trim();
      const chatId = header.getAttribute('data-id') || chatTitle;
      
      if (chatId && chatId !== this.currentChatId) {
        const oldChatId = this.currentChatId;
        this.currentChatId = chatId;
        
        console.log(`[TextMonitor] 📱 Chat mudou: "${chatTitle}"`);
        this.captureCurrentMessages();
        
        this.triggerCallbacks('onNewChat', {
          chatId,
          chatTitle,
          previousChat: oldChatId,
          timestamp: Date.now()
        });
      }
    }
    
    checkNewMessages() {
      const container = this.findElement(this.selectors.messageContainer);
      if (!container) return;
      
      const messageElements = container.querySelectorAll(this.selectors.messages.join(','));
      if (!messageElements.length) return;
      
      const currentMessages = Array.from(messageElements).map(el => {
        const id = el.getAttribute('data-id') || 
                  el.getAttribute('data-testid') || 
                  this.getMessageHash(el);
                  
        const isOutgoing = el.classList.contains('message-out') || 
                          el.hasAttribute('data-outgoing') ||
                          !!el.querySelector('[data-outgoing="true"]');
        
        let textElement = null;
        for (const selector of this.selectors.messageText) {
          textElement = el.querySelector(selector);
          if (textElement) break;
        }
        
        let infoElement = null;
        for (const selector of this.selectors.messageInfo) {
          infoElement = el.querySelector(selector);
          if (infoElement) break;
        }
        
        let authorElement = null;
        for (const selector of this.selectors.messageAuthor) {
          authorElement = el.querySelector(selector);
          if (authorElement) break;
        }
        
        return {
          id,
          element: el,
          text: textElement ? textElement.textContent.trim() : '',
          info: infoElement ? infoElement.textContent.trim() : '',
          author: authorElement ? authorElement.textContent.trim() : '',
          isOutgoing,
          timestamp: Date.now(),
          processed: this.isMessageProcessed(id)
        };
      });
      
      const newMessages = currentMessages.filter(msg => !msg.processed);
      
      currentMessages.forEach(msg => {
        if (!msg.processed) {
          this.lastMessages.push(msg.id);
          if (this.lastMessages.length > this.config.maxHistoryLength) {
            this.lastMessages.shift();
          }
        }
      });
      
      if (newMessages.length > 0) {
        console.log(`[TextMonitor] 📨 ${newMessages.length} nova(s) mensagem(ns)`);
        
        newMessages.forEach(msg => {
          this.triggerCallbacks('onNewMessage', {
            id: msg.id,
            text: msg.text,
            isOutgoing: msg.isOutgoing,
            author: msg.author,
            info: msg.info,
            element: msg.element,
            chatId: this.currentChatId,
            timestamp: Date.now()
          });
        });
      }
    }
    
    checkTypingStatus() {
      const typingIndicator = this.findElement(this.selectors.typingIndicator);
      const isTyping = !!typingIndicator;
      
      if (isTyping !== this.lastTypingStatus) {
        this.lastTypingStatus = isTyping;
        
        if (isTyping) {
          console.log('[TextMonitor] ⌨️ Alguém está digitando...');
          let typerName = '';
          if (typingIndicator) {
            typerName = typingIndicator.textContent.replace('digitando...', '').trim();
          }
          
          this.triggerCallbacks('onTyping', {
            isTyping: true,
            name: typerName,
            chatId: this.currentChatId,
            timestamp: Date.now()
          });
        } else {
          console.log('[TextMonitor] Digitação parou');
          this.triggerCallbacks('onTyping', {
            isTyping: false,
            chatId: this.currentChatId,
            timestamp: Date.now()
          });
        }
      }
    }
    
    captureCurrentMessages() {
      const container = this.findElement(this.selectors.messageContainer);
      if (!container) {
        this.lastMessages = [];
        return;
      }
      
      const messageElements = container.querySelectorAll(this.selectors.messages.join(','));
      this.lastMessages = Array.from(messageElements).map(el => {
        return el.getAttribute('data-id') || 
               el.getAttribute('data-testid') || 
               this.getMessageHash(el);
      });
      
      console.log(`[TextMonitor] Base inicial: ${this.lastMessages.length} mensagens`);
    }
    
    isMessageProcessed(id) {
      return this.lastMessages.includes(id);
    }
    
    getMessageHash(el) {
      const text = el.textContent.trim();
      const classes = Array.from(el.classList).join('');
      // Add random component to prevent collisions
      const random = Math.random().toString(36).substring(2, 9);
      return `msg_${text.length}_${classes.length}_${Date.now()}_${random}`;
    }
    
    triggerCallbacks(event, data) {
      if (!this.callbacks[event] || !this.callbacks[event].length) return;
      
      this.callbacks[event].forEach(callback => {
        try {
          callback(data);
        } catch (error) {
          console.error(`[TextMonitor] Erro no callback de ${event}:`, error);
        }
      });
    }
    
    findElement(selectors) {
      if (!Array.isArray(selectors)) selectors = [selectors];
      for (const selector of selectors) {
        const element = document.querySelector(selector);
        if (element) return element;
      }
      return null;
    }
    
    async reply(text) {
      if (!this.currentChatId) {
        console.error('[TextMonitor] Nenhum chat ativo para responder');
        return false;
      }
      return await this.wa.sendTextMessage(text);
    }
    
    analyzeSentiment(text) {
      const positiveWords = ['bom', 'bem', 'ótimo', 'excelente', 'incrível', 'maravilhoso', 'obrigado', 'grato', 'feliz', 'perfeito', 'sim', '👍', '😊', '❤️'];
      const negativeWords = ['ruim', 'mal', 'péssimo', 'terrível', 'horrível', 'problema', 'erro', 'não', 'incorreto', 'insatisfeito', '👎', '😠'];
      
      const words = text.toLowerCase().split(/\s+/);
      let positiveScore = 0;
      let negativeScore = 0;
      
      words.forEach(word => {
        if (positiveWords.some(pw => word.includes(pw))) positiveScore++;
        if (negativeWords.some(nw => word.includes(nw))) negativeScore++;
      });
      
      const totalWords = words.length;
      const score = totalWords > 0 ? ((positiveScore - negativeScore) / totalWords) : 0;
      
      let sentiment = 'neutral';
      if (score > 0.05) sentiment = 'positive';
      if (score < -0.05) sentiment = 'negative';
      
      return { sentiment, score: parseFloat(score.toFixed(2)), positiveWords: positiveScore, negativeWords: negativeScore };
    }
    
    detectIntent(text) {
      const lowerText = text.toLowerCase();
      
      const patterns = {
        greeting: ['olá', 'oi', 'bom dia', 'boa tarde', 'boa noite', 'hello', 'hi'],
        farewell: ['tchau', 'adeus', 'até logo', 'até mais', 'goodbye', 'bye'],
        thanks: ['obrigado', 'obrigada', 'valeu', 'thanks'],
        question: ['?', 'quem', 'como', 'quando', 'onde', 'por que'],
        request: ['pode', 'poderia', 'gostaria', 'preciso', 'quero'],
        confirmation: ['sim', 'claro', 'ok', 'certo', 'yes', 'sure']
      };
      
      const matches = {};
      for (const [intent, keywords] of Object.entries(patterns)) {
        matches[intent] = 0;
        keywords.forEach(keyword => {
          if (lowerText.includes(keyword)) matches[intent]++;
        });
      }
      
      let primaryIntent = 'other';
      let maxScore = 0;
      
      for (const [intent, score] of Object.entries(matches)) {
        if (score > maxScore) {
          maxScore = score;
          primaryIntent = intent;
        }
      }
      
      if (text.includes('?')) primaryIntent = 'question';
      
      return { primaryIntent, allIntents: matches };
    }
    
    getChatStats() {
      const container = this.findElement(this.selectors.messageContainer);
      if (!container) return null;
      
      const messageElements = container.querySelectorAll(this.selectors.messages.join(','));
      if (!messageElements.length) {
        return { total: 0, incoming: 0, outgoing: 0, ratioInOut: 0, averageLength: 0 };
      }
      
      let incoming = 0, outgoing = 0, totalLength = 0;
      
      Array.from(messageElements).forEach(el => {
        const isOutgoing = el.classList.contains('message-out');
        if (isOutgoing) outgoing++;
        else incoming++;
        totalLength += el.textContent.length;
      });
      
      const total = incoming + outgoing;
      
      return {
        total,
        incoming,
        outgoing,
        ratioInOut: outgoing > 0 ? (incoming / outgoing).toFixed(2) : 'N/A',
        averageLength: total > 0 ? Math.floor(totalLength / total) : 0
      };
    }
    
    watchForAutoResponses(patterns) {
      if (!patterns || typeof patterns !== 'object') {
        console.error('[TextMonitor] Padrões inválidos');
        return null;
      }
      
      const processedPatterns = Object.entries(patterns).map(([pattern, response]) => {
        const regex = typeof pattern === 'string' ? new RegExp(pattern, 'i') : pattern;
        return { regex, response };
      });
      
      const callback = async (message) => {
        if (message.isOutgoing) return;
        
        for (const { regex, response } of processedPatterns) {
          if (regex.test(message.text)) {
            console.log(`[TextMonitor] 🤖 Resposta automática: "${regex}"`);
            await this.wa.sleep(1500);
            
            let finalResponse = response;
            if (typeof response === 'function') {
              finalResponse = response(message);
            }
            
            await this.reply(finalResponse);
            break;
          }
        }
      };
      
      this.on('onNewMessage', callback);
      return () => this.off('onNewMessage', callback);
    }
  }

  // ============================================================
  // INICIALIZAÇÃO DAS INSTÂNCIAS
  // ============================================================

  const waHelper = new WhatsAppAutomation();
  const waMonitor = new WhatsAppMonitor(waHelper);
  const waTextMonitor = new WhatsAppTextMonitor(waHelper);

  // ============================================================
  // EXPORTAÇÃO GLOBAL (window.wa)
  // ============================================================

  window.wa = {
    // Comandos rápidos
    enviar: (contato, mensagem) => {
      return (async () => {
        await waHelper.openChat(contato);
        await waHelper.sleep(2000);
        return await waHelper.sendTextMessage(mensagem);
      })();
    },
    
    abrir: (contato) => waHelper.openChat(contato),
    abrirUrl: (numero) => waHelper.openChatByUrl(numero),
    abrirBusca: (nome) => waHelper.openChatBySearch(nome),
    
    debug: () => waHelper.debugButtons(),
    
    status: () => ({
      mediaDialogAberto: waHelper.isMediaDialogOpen(),
      botaoEnviarEncontrado: waHelper.findSendButton() !== null,
      inputEncontrado: waHelper.findElement(waHelper.selectors.messageInput) !== null
    }),
    
    // Monitor de mídia
    monitor: {
      iniciar: () => waMonitor.start(),
      parar: () => waMonitor.stop(),
      status: () => waMonitor.isMonitoring ? '🟢 Ativo' : '🔴 Inativo'
    },
    
    // Monitor de texto
    text: {
      iniciar: (options) => waTextMonitor.start(options),
      parar: () => waTextMonitor.stop(),
      status: () => waTextMonitor.isMonitoring ? '🟢 Ativo' : '🔴 Inativo',
      stats: () => waTextMonitor.getChatStats(),
      analisar: (texto) => ({
        sentimento: waTextMonitor.analyzeSentiment(texto),
        intencao: waTextMonitor.detectIntent(texto)
      }),
      responderAuto: (padroes) => waTextMonitor.watchForAutoResponses(padroes),
      escutar: (callback) => waTextMonitor.on('onNewMessage', callback)
    },
    
    // Instâncias diretas
    helper: waHelper,
    mediaMonitor: waMonitor,
    textMonitor: waTextMonitor
  };

  // ============================================================
  // LOG DE INICIALIZAÇÃO
  // ============================================================

  console.log(`
╔═══════════════════════════════════════════════════════════╗
║   WhatsApp Web Automation Helper - v2024/2025            ║
║   Todas as correções implementadas! ✅                    ║
╚═══════════════════════════════════════════════════════════╝

📋 STATUS DAS CORREÇÕES:
✅ wds-ic-send-filled (novo ícone)
✅ Dialog de mídia [data-animate-modal-popup="true"]
✅ openChatByUrl() implementado
✅ Fallback inteligente (URL → Busca)
✅ findMediaSendButton() atualizado
✅ findSendButton() com novos seletores
✅ WhatsAppTextMonitor para mensagens
✅ Análise de sentimento e intenção

🎮 COMANDOS DISPONÍVEIS:

wa.enviar('+5511999887766', 'Olá!')
wa.abrir('João Silva')
wa.abrirUrl('+5511999887766')
wa.debug()
wa.status()

wa.monitor.iniciar()
wa.monitor.parar()

wa.text.iniciar()
wa.text.stats()
wa.text.analisar('texto aqui')
wa.text.escutar((msg) => console.log(msg))

🚀 PRONTO PARA USO!
`);

  // ============================================================
  // SMARTBOT IA - SISTEMA INTELIGENTE DE ATENDIMENTO
  // Integra: Sentimento + Intenção + Aprendizado + Auto-resposta
  // ============================================================

  class SmartBotIA {
    constructor(waHelper, waTextMonitor) {
      this.wa = waHelper;
      this.textMonitor = waTextMonitor;
      this.isActive = false;
      this.config = {
        autoResponseEnabled: false,
        confidenceThreshold: 70,
        learningEnabled: true,
        sentimentAdjustment: true,
        intentPrioritization: true,
        humanHoursOnly: false,
        maxAutoResponsesPerHour: 30,
        responseDelay: { min: 1500, max: 4000 }
      };
      
      this.knowledge = {
        intents: {},
        sentimentResponses: {},
        learnedPatterns: [],
        conversationHistory: new Map(),
        feedbackData: { positive: 0, negative: 0, corrections: [] }
      };
      
      this.metrics = {
        totalMessages: 0,
        autoResponses: 0,
        assistedResponses: 0,
        avgConfidence: 0,
        avgSentiment: 0,
        intentDistribution: {},
        responseTime: []
      };
      
      this.messageQueue = [];
      this.unsubscribers = [];
    }

    async initialize() {
      console.log('[SmartBot] 🚀 Inicializando...');
      await this.loadKnowledge();
      this.setupIntentResponses();
      this.setupSentimentAdjustments();
      console.log('[SmartBot] ✅ Inicializado');
      return this;
    }
    
    setupIntentResponses() {
      this.knowledge.intents = {
        greeting: {
          responses: ['Olá! 👋 Como posso ajudar você hoje?', 'Oi! Tudo bem? Em que posso ser útil?', 'Olá! Seja bem-vindo(a)! 😊'],
          confidence: 95, autoSend: true, priority: 'high'
        },
        farewell: {
          responses: ['Até logo! Foi um prazer ajudar! 😊', 'Tchau! Qualquer dúvida, estou por aqui!', 'Até mais! Tenha um ótimo dia! 🙌'],
          confidence: 90, autoSend: true, priority: 'medium'
        },
        thanks: {
          responses: ['Por nada! Fico feliz em ajudar! 😊', 'Disponha! Qualquer coisa, é só chamar!', 'Imagina! Foi um prazer! 🙌'],
          confidence: 90, autoSend: true, priority: 'medium'
        },
        question: { responses: [], confidence: 60, autoSend: false, priority: 'high', requiresAI: true },
        request: { responses: [], confidence: 50, autoSend: false, priority: 'high', requiresAI: true },
        confirmation: {
          responses: ['Perfeito! ✅ Vou processar isso agora.', 'Entendido! Já estou providenciando.', 'Certo! Confirmado! 👍'],
          confidence: 80, autoSend: false, priority: 'medium'
        },
        complaint: { responses: [], confidence: 30, autoSend: false, priority: 'urgent', requiresHuman: true, escalate: true },
        other: { responses: [], confidence: 40, autoSend: false, priority: 'low', requiresAI: true }
      };
    }
    
    setupSentimentAdjustments() {
      this.knowledge.sentimentResponses = {
        positive: { prefix: ['Que ótimo! ', 'Fico feliz! ', 'Excelente! '], suffix: [' 😊', ' 🎉', ' ✨'], toneBoost: 1.2, emojiFrequency: 'high' },
        negative: { prefix: ['Entendo sua frustração. ', 'Sinto muito por isso. ', 'Compreendo. '], suffix: [' Vou resolver isso para você.', ' Estou aqui para ajudar.', ''], toneBoost: 0.8, emojiFrequency: 'low', escalateProbability: 0.3 },
        neutral: { prefix: ['', '', ''], suffix: ['', ' 👍', ''], toneBoost: 1.0, emojiFrequency: 'medium' }
      };
    }

    start(options = {}) {
      if (this.isActive) { console.log('[SmartBot] ⚠️ Já está ativo'); return; }
      this.config = { ...this.config, ...options };
      console.log('[SmartBot] 🟢 Ativando...');
      
      this.textMonitor.start({ interval: 800, detectTyping: true, debug: false });
      
      const unsubMessage = this.textMonitor.on('onNewMessage', (msg) => this.processIncomingMessage(msg));
      this.unsubscribers.push(unsubMessage);
      
      const unsubChat = this.textMonitor.on('onNewChat', (data) => this.handleChatChange(data));
      this.unsubscribers.push(unsubChat);
      
      const unsubTyping = this.textMonitor.on('onTyping', (data) => this.handleTypingIndicator(data));
      this.unsubscribers.push(unsubTyping);
      
      this.isActive = true;
      console.log('[SmartBot] ✅ Ativo e monitorando!');
      return this;
    }
    
    stop() {
      if (!this.isActive) return;
      console.log('[SmartBot] 🔴 Desativando...');
      this.unsubscribers.forEach(unsub => unsub && unsub());
      this.unsubscribers = [];
      this.textMonitor.stop();
      this.saveKnowledge();
      this.isActive = false;
      console.log('[SmartBot] ✅ Desativado');
    }

    async processIncomingMessage(message) {
      if (message.isOutgoing) return;
      const startTime = Date.now();
      this.metrics.totalMessages++;
      console.log('[SmartBot] 📨 Nova mensagem:', message.text?.slice(0, 50));
      
      try {
        const analysis = this.analyzeMessage(message);
        console.log('[SmartBot] 📊 Análise:', { intent: analysis.intent.primaryIntent, sentiment: analysis.sentiment.sentiment, confidence: analysis.confidence });
        
        this.updateConversationHistory(message.chatId, message, analysis);
        this.updateMetrics(analysis);
        
        const decision = await this.decideAction(message, analysis);
        console.log('[SmartBot] 🎯 Decisão:', decision.action);
        
        await this.executeAction(decision, message, analysis);
        this.metrics.responseTime.push(Date.now() - startTime);
      } catch (error) {
        console.error('[SmartBot] ❌ Erro:', error);
      }
    }
    
    analyzeMessage(message) {
      const text = message.text || '';
      const sentiment = this.textMonitor.analyzeSentiment(text);
      const intent = this.textMonitor.detectIntent(text);
      const patterns = this.detectPatterns(text);
      const urgency = this.analyzeUrgency(text, sentiment, intent);
      const confidence = this.calculateConfidence(intent, sentiment, message);
      const learnedMatch = this.findLearnedPattern(text);
      return { sentiment, intent, patterns, urgency, confidence, learnedMatch, timestamp: Date.now() };
    }
    
    detectPatterns(text) {
      const results = { hasPhone: false, hasEmail: false, hasURL: false, hasMoney: false, entities: [] };
      const patterns = {
        phone: /(\+?\d{1,3}[\s-]?)?\(?\d{2,3}\)?[\s-]?\d{3,5}[\s-]?\d{4}/g,
        email: /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g,
        url: /(https?:\/\/[^\s]+)|www\.[^\s]+/g,
        money: /R\$\s?\d+([.,]\d+)?|\$\s?\d+([.,]\d+)?/g
      };
      for (const [type, regex] of Object.entries(patterns)) {
        const matches = text.match(regex);
        if (matches && matches.length > 0) {
          results[`has${type.charAt(0).toUpperCase() + type.slice(1)}`] = true;
          matches.forEach(match => results.entities.push({ type, value: match }));
        }
      }
      return results;
    }
    
    analyzeUrgency(text, sentiment, intent) {
      const urgentWords = ['urgente', 'urgência', 'agora', 'imediato', 'rápido', 'emergência', 'crítico', 'problema grave'];
      const lowerText = text.toLowerCase();
      let score = 0;
      urgentWords.forEach(word => { if (lowerText.includes(word)) score += 20; });
      if (sentiment.sentiment === 'negative') score += 15;
      if (intent.primaryIntent === 'complaint') score += 30;
      if ((text.match(/\?/g) || []).length > 1) score += 10;
      return { score: Math.min(100, score), level: score >= 50 ? 'high' : score >= 25 ? 'medium' : 'low' };
    }
    
    calculateConfidence(intent, sentiment, message) {
      let confidence = 50;
      const intentConfig = this.knowledge.intents[intent.primaryIntent];
      if (intentConfig) confidence = intentConfig.confidence;
      if (sentiment.sentiment === 'positive') confidence += 10;
      if (sentiment.sentiment === 'negative') confidence -= 20;
      const textLength = (message.text || '').length;
      if (textLength < 20) confidence += 15;
      if (textLength > 200) confidence -= 15;
      const history = this.knowledge.conversationHistory.get(message.chatId);
      if (history && history.length > 3) confidence += 10;
      const learnedMatch = this.findLearnedPattern(message.text);
      if (learnedMatch && learnedMatch.confidence > 80) confidence = Math.max(confidence, learnedMatch.confidence);
      return Math.min(100, Math.max(0, confidence));
    }
    
    findLearnedPattern(text) {
      if (!text) return null;
      const lowerText = text.toLowerCase();
      for (const pattern of this.knowledge.learnedPatterns) {
        if (pattern.triggers.some(t => lowerText.includes(t.toLowerCase()))) {
          return { pattern, confidence: pattern.confidence || 85, response: pattern.response };
        }
      }
      return null;
    }

    async decideAction(message, analysis) {
      const { intent, sentiment, confidence, urgency, learnedMatch } = analysis;
      if (this.config.humanHoursOnly && !this.isBusinessHours()) return { action: 'queue', reason: 'Fora do horário comercial' };
      if (!this.checkRateLimit()) return { action: 'queue', reason: 'Rate limit atingido' };
      if (urgency.level === 'high' && sentiment.sentiment === 'negative') return { action: 'escalate', reason: 'Urgência alta com sentimento negativo', priority: 'urgent' };
      if (learnedMatch && learnedMatch.confidence >= this.config.confidenceThreshold) return { action: 'auto_respond', response: learnedMatch.response, confidence: learnedMatch.confidence, source: 'learned_pattern' };
      
      const intentConfig = this.knowledge.intents[intent.primaryIntent];
      if (intentConfig && intentConfig.autoSend && confidence >= this.config.confidenceThreshold && intentConfig.responses.length > 0) {
        const response = this.selectAndAdjustResponse(intentConfig.responses, sentiment);
        return { action: 'auto_respond', response, confidence, source: 'intent_match', intent: intent.primaryIntent };
      }
      if (intentConfig?.requiresAI) return { action: 'ai_generate', context: { intent: intent.primaryIntent, sentiment: sentiment.sentiment, history: this.getConversationContext(message.chatId) } };
      if (intentConfig?.requiresHuman || intentConfig?.escalate) return { action: 'escalate', reason: 'Requer intervenção humana', priority: intentConfig.priority };
      return { action: 'suggest', reason: 'Confiança insuficiente', confidence };
    }
    
    async executeAction(decision, message, analysis) {
      switch (decision.action) {
        case 'auto_respond': await this.sendAutoResponse(decision, message, analysis); break;
        case 'ai_generate': await this.generateAIResponse(decision, message, analysis); break;
        case 'suggest': this.suggestResponse(decision, message, analysis); break;
        case 'escalate': this.escalateToHuman(decision, message, analysis); break;
        case 'queue': this.queueMessage(decision, message, analysis); break;
      }
    }
    
    async sendAutoResponse(decision, message, analysis) {
      console.log('[SmartBot] 🤖 Enviando resposta automática...');
      const delay = this.config.responseDelay.min + Math.random() * (this.config.responseDelay.max - this.config.responseDelay.min);
      await this.sleep(delay);
      try {
        const success = await this.wa.sendTextMessage(decision.response);
        if (success) {
          this.metrics.autoResponses++;
          this.recordInteraction(message, decision.response, 'auto', analysis);
          console.log('[SmartBot] ✅ Resposta enviada');
        }
      } catch (error) {
        console.error('[SmartBot] ❌ Erro ao enviar:', error);
      }
    }
    
    async generateAIResponse(decision, message, analysis) {
      console.log('[SmartBot] 🧠 Gerando resposta via IA...');
      try {
        const context = { message: message.text, intent: analysis.intent.primaryIntent, sentiment: analysis.sentiment.sentiment, conversationHistory: decision.context.history };
        const aiResponse = await this.callExistingAI(context);
        if (aiResponse) {
          const adjustedResponse = this.adjustResponseBySentiment(aiResponse, analysis.sentiment.sentiment);
          if (this.config.autoResponseEnabled && analysis.confidence >= 70) {
            await this.sendAutoResponse({ ...decision, response: adjustedResponse }, message, analysis);
          } else {
            this.suggestResponse({ ...decision, response: adjustedResponse }, message, analysis);
          }
        }
      } catch (error) {
        console.error('[SmartBot] ❌ Erro na IA:', error);
        this.suggestResponse(decision, message, analysis);
      }
    }
    
    async callExistingAI(context) {
      try {
        if (typeof aiChat === 'function') {
          return await aiChat({ mode: 'reply', extraInstruction: `Intenção: ${context.intent}, Sentimento: ${context.sentiment}`, transcript: context.message, chatTitle: 'SmartBot' });
        }
        if (typeof bg === 'function') {
          const resp = await bg('AI_CHAT', { messages: [{ role: 'system', content: 'Você é um assistente de atendimento inteligente.' }, { role: 'user', content: context.message }] });
          return resp?.text || null;
        }
        return null;
      } catch (error) { return null; }
    }
    
    suggestResponse(decision, message, analysis) {
      console.log('[SmartBot] 💡 Sugerindo resposta...');
      this.metrics.assistedResponses++;
      window.dispatchEvent(new CustomEvent('smartbot:suggestion', { detail: { message, analysis, suggestion: decision.response || 'Analise e responda.', confidence: decision.confidence || analysis.confidence } }));
    }
    
    escalateToHuman(decision, message, analysis) {
      console.log('[SmartBot] 🚨 Escalando para humano...');
      window.dispatchEvent(new CustomEvent('smartbot:escalation', { detail: { message, analysis, reason: decision.reason, priority: decision.priority } }));
    }
    
    queueMessage(decision, message, analysis) {
      console.log('[SmartBot] 📥 Enfileirado:', decision.reason);
      this.messageQueue.push({ message, analysis, decision, queuedAt: Date.now() });
    }

    selectAndAdjustResponse(responses, sentiment) {
      const baseResponse = responses[Math.floor(Math.random() * responses.length)];
      return this.adjustResponseBySentiment(baseResponse, sentiment.sentiment);
    }
    
    adjustResponseBySentiment(response, sentimentType) {
      if (!this.config.sentimentAdjustment) return response;
      const adjustments = this.knowledge.sentimentResponses[sentimentType];
      if (!adjustments) return response;
      let adjusted = response;
      if (adjustments.prefix.length > 0 && Math.random() > 0.5) {
        const prefix = adjustments.prefix[Math.floor(Math.random() * adjustments.prefix.length)];
        if (prefix && !adjusted.startsWith(prefix.trim())) adjusted = prefix + adjusted;
      }
      if (adjustments.suffix.length > 0) {
        const addSuffix = adjustments.emojiFrequency === 'high' ? 0.8 : adjustments.emojiFrequency === 'medium' ? 0.5 : 0.2;
        if (Math.random() < addSuffix) {
          const suffix = adjustments.suffix[Math.floor(Math.random() * adjustments.suffix.length)];
          if (suffix && !adjusted.endsWith(suffix.trim())) adjusted = adjusted + suffix;
        }
      }
      return adjusted;
    }

    recordInteraction(message, response, type, analysis) {
      if (this.config.learningEnabled) {
        this.learnFromInteraction({ messageText: message.text, response, type, intent: analysis.intent.primaryIntent, sentiment: analysis.sentiment.sentiment, confidence: analysis.confidence, timestamp: Date.now() });
      }
    }
    
    learnFromInteraction(interaction) {
      const existingPattern = this.knowledge.learnedPatterns.find(p => p.triggers.some(t => interaction.messageText.toLowerCase().includes(t.toLowerCase())));
      if (existingPattern) {
        existingPattern.occurrences = (existingPattern.occurrences || 0) + 1;
        existingPattern.confidence = Math.min(95, existingPattern.confidence + 2);
        existingPattern.lastUsed = Date.now();
      } else if (interaction.type === 'auto' && interaction.confidence >= 80) {
        const words = interaction.messageText.toLowerCase().split(/\s+/).filter(w => w.length > 3).slice(0, 5);
        if (words.length >= 2) {
          this.knowledge.learnedPatterns.push({ triggers: words, response: interaction.response, intent: interaction.intent, confidence: 70, occurrences: 1, createdAt: Date.now() });
          console.log('[SmartBot] 📚 Padrão aprendido:', words);
        }
      }
      if (this.knowledge.learnedPatterns.length > 200) {
        this.knowledge.learnedPatterns.sort((a, b) => (b.occurrences * b.confidence) - (a.occurrences * a.confidence));
        this.knowledge.learnedPatterns = this.knowledge.learnedPatterns.slice(0, 150);
      }
    }
    
    provideFeedback(messageId, feedbackType, correction = null) {
      if (feedbackType === 'positive') this.knowledge.feedbackData.positive++;
      else if (feedbackType === 'negative') this.knowledge.feedbackData.negative++;
      if (correction) this.knowledge.feedbackData.corrections.push({ correction, timestamp: Date.now() });
      console.log('[SmartBot] 📝 Feedback:', feedbackType);
    }

    updateConversationHistory(chatId, message, analysis) {
      if (!this.knowledge.conversationHistory.has(chatId)) this.knowledge.conversationHistory.set(chatId, []);
      const history = this.knowledge.conversationHistory.get(chatId);
      history.push({ text: message.text, isOutgoing: message.isOutgoing, intent: analysis.intent.primaryIntent, sentiment: analysis.sentiment.sentiment, timestamp: Date.now() });
      if (history.length > 20) history.shift();
    }
    
    getConversationContext(chatId) {
      const history = this.knowledge.conversationHistory.get(chatId) || [];
      return history.slice(-5).map(h => `${h.isOutgoing ? 'EU' : 'CLIENTE'}: ${h.text?.slice(0, 100)}`);
    }
    
    handleChatChange(data) { console.log('[SmartBot] 📱 Chat mudou:', data.chatTitle); }
    handleTypingIndicator(data) { if (data.isTyping) console.log('[SmartBot] ⌨️ Digitando...'); }
    
    sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }
    isBusinessHours() {
      const now = new Date();
      const hour = now.getHours();
      const day = now.getDay();
      return (day >= 1 && day <= 5 && hour >= 8 && hour < 20) || (day === 6 && hour >= 9 && hour < 14);
    }
    checkRateLimit() {
      const oneHourAgo = Date.now() - 3600000;
      const recent = this.metrics.responseTime.filter(t => t > oneHourAgo);
      return recent.length < this.config.maxAutoResponsesPerHour;
    }
    updateMetrics(analysis) {
      const intent = analysis.intent.primaryIntent;
      this.metrics.intentDistribution[intent] = (this.metrics.intentDistribution[intent] || 0) + 1;
      this.metrics.avgConfidence = (this.metrics.avgConfidence * (this.metrics.totalMessages - 1) + analysis.confidence) / this.metrics.totalMessages;
    }

    async loadKnowledge() {
      return new Promise((resolve) => {
        chrome.storage.local.get(['smartbot_knowledge'], (res) => {
          if (res?.smartbot_knowledge) {
            this.knowledge.learnedPatterns = res.smartbot_knowledge.learnedPatterns || [];
            this.knowledge.feedbackData = res.smartbot_knowledge.feedbackData || this.knowledge.feedbackData;
            console.log('[SmartBot] 📂 Carregado:', this.knowledge.learnedPatterns.length, 'padrões');
          }
          resolve();
        });
      });
    }
    
    async saveKnowledge() {
      return new Promise((resolve) => {
        chrome.storage.local.set({ smartbot_knowledge: { learnedPatterns: this.knowledge.learnedPatterns, feedbackData: this.knowledge.feedbackData, savedAt: new Date().toISOString() } }, () => {
          console.log('[SmartBot] 💾 Salvo');
          resolve();
        });
      });
    }

    getStats() {
      return { isActive: this.isActive, metrics: this.metrics, learnedPatterns: this.knowledge.learnedPatterns.length, feedbackScore: this.knowledge.feedbackData.positive - this.knowledge.feedbackData.negative, queueSize: this.messageQueue.length, config: this.config };
    }
    setConfig(newConfig) { this.config = { ...this.config, ...newConfig }; console.log('[SmartBot] ⚙️ Config atualizada'); }
    addCustomIntent(intentName, config) {
      this.knowledge.intents[intentName] = { responses: config.responses || [], confidence: config.confidence || 70, autoSend: config.autoSend || false, priority: config.priority || 'medium', ...config };
    }
    addLearnedPattern(triggers, response, confidence = 80) {
      this.knowledge.learnedPatterns.push({ triggers: Array.isArray(triggers) ? triggers : [triggers], response, confidence, occurrences: 0, createdAt: Date.now(), source: 'manual' });
    }
  }

  // ============================================================
  // INICIALIZAÇÃO DO SMARTBOT
  // ============================================================

  window.smartbot = null;

  window.initSmartBot = async () => {
    if (!window.wa || !window.wa.helper || !window.wa.textMonitor) {
      console.error('[SmartBot] Dependências não encontradas');
      return null;
    }
    
    const bot = new SmartBotIA(window.wa.helper, window.wa.textMonitor);
    await bot.initialize();
    window.smartbot = bot;
    
    window.wa.smartbot = {
      iniciar: (options) => bot.start(options),
      parar: () => bot.stop(),
      status: () => bot.getStats(),
      config: (cfg) => bot.setConfig(cfg),
      feedback: (msgId, type, correction) => bot.provideFeedback(msgId, type, correction),
      addIntent: (name, cfg) => bot.addCustomIntent(name, cfg),
      addPattern: (triggers, response, confidence) => bot.addLearnedPattern(triggers, response, confidence),
      salvar: () => bot.saveKnowledge(),
      carregar: () => bot.loadKnowledge()
    };
    
    console.log(`
╔═══════════════════════════════════════════════════════════╗
║          SMARTBOT IA - SISTEMA INTELIGENTE 🧠             ║
╚═══════════════════════════════════════════════════════════╝

🎮 COMANDOS:
wa.smartbot.iniciar()
wa.smartbot.iniciar({ autoResponseEnabled: true })
wa.smartbot.parar()
wa.smartbot.status()
wa.smartbot.config({ confidenceThreshold: 80 })
wa.smartbot.feedback(msgId, 'positive')
wa.smartbot.addIntent('preco', { responses: ['R$99'], autoSend: true })
wa.smartbot.addPattern(['quanto custa'], 'Nossos preços começam em R$99!')
wa.smartbot.salvar()

🚀 Execute wa.smartbot.iniciar() para ativar!
`);
    
    return bot;
  };

  setTimeout(() => {
    if (window.wa && window.wa.helper) window.initSmartBot();
  }, 3000);

})();
