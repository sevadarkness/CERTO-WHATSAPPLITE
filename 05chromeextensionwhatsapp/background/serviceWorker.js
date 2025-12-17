// background/serviceWorker.js
// MV3 Service Worker - handlers must be registered at top-level.
//
// WhatsHybrid Lite (Alabama) v0.2.x
// - OpenAI direct calls (chat/completions)
// - Optional backend proxy calls (AI + Campaigns)
// - Settings stored in chrome.storage.local
//
// NOTE: This service worker is intentionally small and defensive.

const DEFAULTS = {
  // Conexão - Usuário deve configurar via popup
  provider: "openai",
  openaiApiKey: "", // Usuário deve inserir sua própria chave
  openaiModel: "gpt-4o-mini",

  // Backend - PRÉ-CONFIGURADO (não aparece no popup)
  backendUrl: "https://adm.redealabama.com",
  backendAiPath: "/ai/chat.php",
  backendCampaignPath: "/api/campaigns.php",
  backendSecret: "", // Removido por segurança - configurar via popup se necessário

  // Generation
  temperature: 0.7,
  maxTokens: 450,

  // Memória Híbrida - PRÉ-CONFIGURADO (sempre ativo)
  memoryServerUrl: "https://adm.redealabama.com/api/memory",
  memoryWorkspaceKey: "alabama-workspace-default",
  memorySyncEnabled: true, // Sempre true, sem toggle

  // Chatbot behavior (used by content script) - Usuário configura
  persona: "",
  businessContext: "",
  autoSuggest: false,
  autoMemory: false,
  
  // Copilot mode - Usuário configura
  copilotEnabled: false,
  copilotThreshold: 70,
  confidenceScore: 0,
  confidenceStats: {
    total_good: 0,
    total_bad: 0,
    total_corrections: 0,
    total_auto_sent: 0,
    total_suggestions_used: 0,
    total_suggestions_edited: 0
  },

  // Mensagens Rápidas - NOVO
  quickReplies: [],
  
  // Equipe - NOVO
  teamMembers: [],
  senderName: "", // Nome do remetente (empresa)
};

async function getSettings() {
  const data = await chrome.storage.local.get(Object.keys(DEFAULTS));
  return { ...DEFAULTS, ...data };
}

function ok(sendResponse, payload) {
  try { 
    sendResponse({ ok: true, ...payload }); 
  } catch (e) {
    console.error('[WhatsHybrid Lite] Failed to send response:', e);
  }
}

function fail(sendResponse, error, extra = {}) {
  const msg = (error && error.message) ? error.message : String(error || "Erro desconhecido");
  console.error('[WhatsHybrid Lite] Error:', msg, error);
  try { sendResponse({ ok: false, error: msg, ...extra }); } catch (e) {
    console.error('[WhatsHybrid Lite] Failed to send error response:', e);
  }
}

function clampNumber(v, min, max, fallback) {
  const n = Number(v);
  if (!Number.isFinite(n)) return fallback;
  return Math.max(min, Math.min(max, n));
}


async function callMemoryJson({ settings, path, method = "POST", body, timeoutMs = 30000 }) {
  const base = String(settings?.memoryServerUrl || "").trim();
  const key = String(settings?.memoryWorkspaceKey || "").trim();
  if (!base || !key) throw new Error("Memory server não configurado (URL/chave).");

  const url = base.replace(/\/+$/, "") + path;
  
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
  
  try {
    const resp = await fetch(url, {
      method,
      headers: {
        "Content-Type": "application/json",
        "X-Workspace-Key": key
      },
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal
    });
    clearTimeout(timeoutId);

    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      const msg = data?.error || data?.message || `HTTP ${resp.status}`;
      throw new Error(`Memory server: ${msg}`);
    }
    return data;
  } catch (e) {
    clearTimeout(timeoutId);
    if (e.name === 'AbortError') {
      throw new Error('Memory server timeout após ' + (timeoutMs/1000) + 's');
    }
    throw e;
  }
}

async function enqueueMemoryEvent(ev) {
  const res = await chrome.storage.local.get(["whl_sync_queue"]);
  let q = Array.isArray(res?.whl_sync_queue) ? res.whl_sync_queue : [];
  
  // Remover eventos mais antigos que 24 horas
  const maxAge = 24 * 60 * 60 * 1000; // 24h em ms
  const now = Date.now();
  q = q.filter(e => (now - (e.at || 0)) < maxAge);
  
  q.push({ ...ev, at: now });
  
  // Keep queue bounded
  const bounded = q.slice(-500);
  await chrome.storage.local.set({ whl_sync_queue: bounded });
  return bounded.length;
}

async function flushMemoryQueue(settings) {
  const res = await chrome.storage.local.get(["whl_sync_queue"]);
  const q = Array.isArray(res?.whl_sync_queue) ? res.whl_sync_queue : [];
  if (!q.length) return { ok: true, flushed: 0 };

  // Try batch endpoint
  const data = await callMemoryJson({
    settings,
    path: "/v1/memory/batch.php",
    body: { events: q }
  });

  // If server accepted, clear queue
  await chrome.storage.local.set({ whl_sync_queue: [] });
  return { ok: true, flushed: q.length, server: data };
}

function normalizePath(p, fallback) {
  const s = String(p || "").trim();
  if (!s) return fallback;
  return s.startsWith("/") ? s : `/${s}`;
}

async function callOpenAI({ apiKey, model, messages, temperature, maxTokens, timeoutMs = 30000 }) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
  
  try {
    const resp = await fetch("https://api.openai.com/v1/chat/completions", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${apiKey}`
      },
      body: JSON.stringify({
        model,
        messages,
        temperature,
        max_tokens: maxTokens
      }),
      signal: controller.signal
    });
    clearTimeout(timeoutId);

    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      const errMsg = data?.error?.message || `HTTP ${resp.status}`;
      throw new Error(errMsg);
    }

    const text = data?.choices?.[0]?.message?.content || "";
    return { text, raw: data };
  } catch (e) {
    clearTimeout(timeoutId);
    if (e.name === 'AbortError') {
      throw new Error('Request timeout após ' + (timeoutMs/1000) + 's');
    }
    throw e;
  }
}

async function callBackendJson({ backendUrl, path, payload, secret, timeoutMs = 30000 }) {
  const base = String(backendUrl || "").trim().replace(/\/$/, "");
  if (!base) throw new Error("Backend URL não configurado.");
  const p = normalizePath(path, "/");
  
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
  
  try {
    const resp = await fetch(`${base}${p}`, {
      method: "POST",
      headers: Object.assign({ "Content-Type": "application/json" }, secret ? { "X-Alabama-Proxy-Key": secret } : {}),
      body: JSON.stringify(payload || {}),
      signal: controller.signal
    });
    clearTimeout(timeoutId);
    
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      const errMsg = data?.error || data?.message || `HTTP ${resp.status}`;
      throw new Error(errMsg);
    }
    return data;
  } catch (e) {
    clearTimeout(timeoutId);
    if (e.name === 'AbortError') {
      throw new Error('Request timeout após ' + (timeoutMs/1000) + 's');
    }
    throw e;
  }
}

// Keep event handlers at top-level (MV3 requirement)
chrome.runtime.onInstalled.addListener(() => {
  console.log("[WhatsHybrid Lite] instalado/atualizado");
});

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  (async () => {
    try {
      if (!msg || !msg.type) return ok(sendResponse, { ignored: true });

      // -------------------------
      // Settings
      // -------------------------
      if (msg.type === "GET_SETTINGS") {
        const settings = await getSettings();
        return ok(sendResponse, { settings });
      }

      if (msg.type === "SAVE_SETTINGS") {
        const incoming = msg.settings || {};
        const clean = {};

        for (const k of Object.keys(DEFAULTS)) {
          if (Object.prototype.hasOwnProperty.call(incoming, k)) clean[k] = incoming[k];
        }

        // small sanity
        if (typeof clean.provider !== "string") clean.provider = DEFAULTS.provider;
        if (typeof clean.openaiApiKey !== "string") clean.openaiApiKey = "";
        if (typeof clean.openaiModel !== "string") clean.openaiModel = DEFAULTS.openaiModel;
        if (typeof clean.backendUrl !== "string") clean.backendUrl = "";
        if (typeof clean.backendSecret !== "string") clean.backendSecret = "";
        clean.backendAiPath = normalizePath(clean.backendAiPath, DEFAULTS.backendAiPath);
        clean.backendCampaignPath = normalizePath(clean.backendCampaignPath, DEFAULTS.backendCampaignPath);

        clean.temperature = clampNumber(clean.temperature, 0, 2, DEFAULTS.temperature);
        clean.maxTokens = clampNumber(clean.maxTokens, 50, 4000, DEFAULTS.maxTokens);

        clean.persona = String(clean.persona || "");
        clean.businessContext = String(clean.businessContext || "");
        clean.autoSuggest = Boolean(clean.autoSuggest);
        clean.autoMemory = Boolean(clean.autoMemory);

        await chrome.storage.local.set(clean);

        // Push context to memory server (optional)
        try {
          const settings = await getSettings();
          if (settings.memorySyncEnabled && settings.memoryServerUrl && settings.memoryWorkspaceKey) {
            await callMemoryJson({
              settings,
              path: "/v1/memory/context.php",
              body: {
                persona: settings.persona || "",
                businessContext: settings.businessContext || ""
              }
            });
            // also attempt to flush any queued events
            await flushMemoryQueue(settings).catch((flushErr) => {
              console.warn('[WhatsHybrid Lite] Failed to flush memory queue:', flushErr);
            });
          }
        } catch (e) {
          // best-effort, don't block save
          console.warn('[WhatsHybrid Lite] Failed to sync memory context:', e);
        }

        return ok(sendResponse, { saved: true });
      }

      // -------------------------
      // AI Chat (used by content script)
      // -------------------------
      if (msg.type === "AI_CHAT") {
        const settings = await getSettings();
        const provider = (msg.provider || settings.provider || DEFAULTS.provider);

        // Backend proxy
        if (provider === "backend") {
          const payload =
            msg.payload ||
            {
              messages: msg.messages || [],
              model: msg.model || settings.openaiModel,
              temperature: typeof msg.temperature === "number" ? msg.temperature : settings.temperature,
              max_tokens: typeof msg.maxTokens === "number" ? msg.maxTokens : settings.maxTokens
            };

          const data = await callBackendJson({
            backendUrl: settings.backendUrl,
            path: settings.backendAiPath,
            payload,
            secret: settings.backendSecret
          });

          const text =
            data?.text ??
            data?.message ??
            data?.choices?.[0]?.message?.content ??
            "";

          return ok(sendResponse, { text });
        }

        // OpenAI direct
        const apiKey = String(msg.apiKey || settings.openaiApiKey || "").trim();
        if (!apiKey) throw new Error("OpenAI API Key não configurada.");
        const model = String(msg.model || settings.openaiModel || DEFAULTS.openaiModel);
        const temperature = typeof msg.temperature === "number" ? msg.temperature : settings.temperature;
        const maxTokens = typeof msg.maxTokens === "number" ? msg.maxTokens : settings.maxTokens;

        const res = await callOpenAI({
          apiKey,
          model,
          messages: msg.messages || [],
          temperature,
          maxTokens
        });

        return ok(sendResponse, { text: res.text });
      }

      
      // -------------------------
      // Hybrid Memory Server (optional)
      // -------------------------
      if (msg.type === "MEMORY_PUSH") {
        const settings = await getSettings();
        // Always enqueue first (so we never lose it)
        await enqueueMemoryEvent(msg.event || { type: "unknown" });

        if (settings.memorySyncEnabled && settings.memoryServerUrl && settings.memoryWorkspaceKey) {
          try {
            const r = await flushMemoryQueue(settings);
            return ok(sendResponse, { flushed: r.flushed || 0 });
          } catch (e) {
            return ok(sendResponse, { queued: true });
          }
        }
        return ok(sendResponse, { queued: true });
      }

      if (msg.type === "MEMORY_QUERY") {
        const settings = await getSettings();
        if (!(settings.memorySyncEnabled && settings.memoryServerUrl && settings.memoryWorkspaceKey)) {
          return ok(sendResponse, { ok: false, disabled: true });
        }

        const payload = msg.payload || {};
        const data = await callMemoryJson({ settings, path: "/v1/memory/query.php", body: payload });
        return ok(sendResponse, { ok: true, data });
      }


// -------------------------
      // Campaigns via Backend API
      // -------------------------
      if (msg.type === "CAMPAIGN_API_CREATE") {
        const settings = await getSettings();
        const payload = msg.payload || {};

        const data = await callBackendJson({
          backendUrl: settings.backendUrl,
          path: settings.backendCampaignPath,
          payload,
          secret: settings.backendSecret
        });

        return ok(sendResponse, { data });
      }

      // -------------------------
      // Scheduled Campaigns
      // -------------------------
      if (msg.type === "SCHEDULE_CAMPAIGN") {
        const campaign = msg.campaign || {};
        if (!campaign.id || !campaign.scheduledTime) {
          return fail(sendResponse, new Error("Invalid campaign data"));
        }

        const scheduledDate = new Date(campaign.scheduledTime);
        const now = Date.now();
        const delayMs = scheduledDate.getTime() - now;
        
        // Chrome alarms minimum delay is 1 minute for unpacked extensions, less for packed
        // For campaigns scheduled in less than 1 minute, we use the minimum
        const delayMinutes = Math.max(0.1, Math.ceil(delayMs / 60000));

        // Create alarm for this campaign
        await chrome.alarms.create(campaign.id, {
          delayInMinutes: delayMinutes
        });

        return ok(sendResponse, { scheduled: true, alarmName: campaign.id, delayMinutes });
      }

      if (msg.type === "CANCEL_SCHEDULED_CAMPAIGN") {
        const campaignId = msg.campaignId;
        if (campaignId) {
          await chrome.alarms.clear(campaignId);
        }
        return ok(sendResponse, { cancelled: true });
      }

      // -------------------------
      // Copilot Mode / AI Confidence
      // -------------------------
      if (msg.type === "GET_CONFIDENCE") {
        const settings = await getSettings();
        
        // Try to get from backend first
        if (settings.backendUrl) {
          try {
            const data = await callBackendJson({
              backendUrl: settings.backendUrl,
              path: "/api/ai_confidence.php",
              payload: {},
              secret: settings.backendSecret
            });
            
            if (data?.ok) {
              // Update local cache
              await chrome.storage.local.set({
                confidenceScore: data.score || 0,
                copilotEnabled: data.config?.copilot_enabled || false,
                copilotThreshold: data.config?.copilot_threshold || 70,
                confidenceStats: data.metrics || DEFAULTS.confidenceStats
              });
              
              return ok(sendResponse, data);
            }
          } catch (e) {
            console.warn("Failed to fetch confidence from backend:", e);
          }
        }
        
        // Fallback to local storage
        return ok(sendResponse, {
          ok: true,
          score: settings.confidenceScore || 0,
          level: {
            level: 'beginner',
            label: 'Iniciante',
            color: '#ef4444',
            emoji: '🔴',
            description: 'IA apenas sugere respostas'
          },
          metrics: settings.confidenceStats || DEFAULTS.confidenceStats,
          config: {
            copilot_enabled: settings.copilotEnabled || false,
            copilot_threshold: settings.copilotThreshold || 70
          },
          points_to_threshold: (settings.copilotThreshold || 70) - (settings.confidenceScore || 0)
        });
      }

      if (msg.type === "UPDATE_CONFIDENCE") {
        const settings = await getSettings();
        const payload = msg.payload || {};
        
        if (settings.backendUrl) {
          try {
            const data = await callBackendJson({
              backendUrl: settings.backendUrl,
              path: "/api/ai_confidence.php",
              payload,
              secret: settings.backendSecret
            });
            
            if (data?.ok) {
              // Update local cache
              await chrome.storage.local.set({
                confidenceScore: data.score || 0
              });
            }
            
            return ok(sendResponse, data);
          } catch (e) {
            return fail(sendResponse, e);
          }
        }
        
        // If no backend, just update local
        return ok(sendResponse, { ok: true, local_only: true });
      }

      if (msg.type === "TOGGLE_COPILOT") {
        const enabled = Boolean(msg.enabled);
        const settings = await getSettings();
        
        if (settings.backendUrl) {
          try {
            const data = await callBackendJson({
              backendUrl: settings.backendUrl,
              path: "/api/ai_confidence.php",
              payload: { action: "toggle_copilot", enabled },
              secret: settings.backendSecret
            });
            
            if (data?.ok) {
              await chrome.storage.local.set({ copilotEnabled: enabled });
            }
            
            return ok(sendResponse, data);
          } catch (e) {
            return fail(sendResponse, e);
          }
        }
        
        // Fallback to local
        await chrome.storage.local.set({ copilotEnabled: enabled });
        return ok(sendResponse, { ok: true, copilot_enabled: enabled });
      }

      if (msg.type === "SET_THRESHOLD") {
        const threshold = Number(msg.threshold || 70);
        const settings = await getSettings();
        
        if (settings.backendUrl) {
          try {
            const data = await callBackendJson({
              backendUrl: settings.backendUrl,
              path: "/api/ai_confidence.php",
              payload: { action: "set_threshold", threshold },
              secret: settings.backendSecret
            });
            
            if (data?.ok) {
              await chrome.storage.local.set({ copilotThreshold: threshold });
            }
            
            return ok(sendResponse, data);
          } catch (e) {
            return fail(sendResponse, e);
          }
        }
        
        // Fallback to local
        await chrome.storage.local.set({ copilotThreshold: threshold });
        return ok(sendResponse, { ok: true, copilot_threshold: threshold });
      }

      // -------------------------
      // Team Messaging (FIX 2)
      // -------------------------
      if (msg.type === "SEND_TO_TEAM") {
        const { members, message, senderName } = msg.payload;
        
        // Converter para Promise-based para evitar erro de canal assíncrono
        (async () => {
          try {
            const tabs = await chrome.tabs.query({ url: "https://web.whatsapp.com/*" });
            if (tabs.length === 0) {
              sendResponse({ ok: false, error: "WhatsApp Web não está aberto." });
              return;
            }

            // Usar chrome.tabs.sendMessage com Promise
            const response = await chrome.tabs.sendMessage(tabs[0].id, {
              type: "SEND_TEAM_MESSAGES",
              payload: { members, message, senderName }
            });
            
            sendResponse({ ok: true, ...response });
          } catch (e) {
            console.error('[WHL] SEND_TO_TEAM error:', e);
            sendResponse({ ok: false, error: e.message || String(e) });
          }
        })();
        
        return true; // Manter canal aberto
      }

      // -------------------------
      // Unknown
      // -------------------------
      return ok(sendResponse, { unknownType: msg.type });
    } catch (e) {
      return fail(sendResponse, e);
    }
  })();

  // return true to keep sendResponse valid async
  return true;
});

// Handle alarms for scheduled campaigns
chrome.alarms.onAlarm.addListener(async (alarm) => {
  console.log("[WhatsHybrid Lite] Alarm triggered:", alarm.name);
  
  // Get scheduled campaigns
  const data = await chrome.storage.local.get(['whl_scheduled_campaigns']);
  const campaigns = Array.isArray(data?.whl_scheduled_campaigns) ? data.whl_scheduled_campaigns : [];
  
  // Find the campaign that matches this alarm
  const campaign = campaigns.find(c => c.id === alarm.name);
  if (!campaign) {
    console.log("[WhatsHybrid Lite] Campaign not found for alarm:", alarm.name);
    return;
  }

  console.log("[WhatsHybrid Lite] Executing scheduled campaign:", campaign.id);

  // Send message to content script to execute campaign
  try {
    const tabs = await chrome.tabs.query({ url: "https://web.whatsapp.com/*" });
    if (tabs.length === 0) {
      console.log("[WhatsHybrid Lite] No WhatsApp Web tab found");
      return;
    }

    // Send to the first WhatsApp Web tab found
    await chrome.tabs.sendMessage(tabs[0].id, {
      type: "EXECUTE_SCHEDULED_CAMPAIGN",
      campaign: campaign
    });

    // Remove campaign from storage after sending
    const filtered = campaigns.filter(c => c.id !== campaign.id);
    await chrome.storage.local.set({ whl_scheduled_campaigns: filtered });
  } catch (e) {
    console.error("[WhatsHybrid Lite] Error executing scheduled campaign:", e);
  }
});
