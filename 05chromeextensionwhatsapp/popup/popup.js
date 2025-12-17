const el = (id) => document.getElementById(id);

// Constants
const TEAM_SEND_TIMEOUT_MS = 60000; // 60 seconds timeout for team messaging

// Global state
let quickReplies = [];
let teamMembers = [];

async function send(type, payload) {
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

function setStatus(msg, ok = true) {
  const s = el("status");
  s.textContent = msg || "";
  s.className = "status " + (ok ? "ok" : "err");
}

// -------------------------
// Tab Navigation
// -------------------------
document.querySelectorAll('.popup-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    // Remover active de todas as abas
    document.querySelectorAll('.popup-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.popup-tab-content').forEach(c => c.classList.remove('active'));
    
    // Ativar aba clicada
    tab.classList.add('active');
    const tabId = `tab-${tab.dataset.tab}`;
    document.getElementById(tabId).classList.add('active');
  });
});

// -------------------------
// Load Settings
// -------------------------
async function load() {
  const resp = await send("GET_SETTINGS", {});
  if (!resp?.ok) throw new Error(resp?.error || "Falha ao carregar settings.");

  const st = resp?.settings || {};

  // API Key
  el("openaiApiKey").value = st.openaiApiKey || "";
  updateApiStatus(st.openaiApiKey);

  // Chatbot
  el("persona").value = st.persona || "";
  el("businessContext").value = st.businessContext || "";
  el("autoSuggest").checked = Boolean(st.autoSuggest);
  el("autoMemory").checked = Boolean(st.autoMemory);

  // Quick Replies
  quickReplies = st.quickReplies || [];
  renderQuickReplies(quickReplies);

  // Team
  el("senderName").value = st.senderName || "";
  teamMembers = st.teamMembers || [];
  renderTeamMembers(teamMembers);
  
  // Load copilot data
  await loadCopilotData();
}

// -------------------------
// Save Settings
// -------------------------
async function saveSettings() {
  setStatus("Salvando…", true);

  const settings = {
    // API Key
    openaiApiKey: el("openaiApiKey").value.trim(),
    
    // Chatbot
    persona: el("persona").value,
    businessContext: el("businessContext").value,
    autoSuggest: el("autoSuggest").checked,
    autoMemory: el("autoMemory").checked,
    
    // Quick Replies
    quickReplies: quickReplies,
    
    // Team
    senderName: el("senderName").value,
    teamMembers: teamMembers,
  };

  const resp = await send("SAVE_SETTINGS", { settings });
  if (resp?.ok) {
    setStatus("Salvo ✅", true);
    updateApiStatus(settings.openaiApiKey);
  } else {
    setStatus(resp?.error || "Falha ao salvar", false);
  }
}

el("save").addEventListener("click", saveSettings);

// -------------------------
// API Key Functions
// -------------------------
function updateApiStatus(apiKey) {
  const statusEl = el("apiStatus");
  const indicatorEl = el("apiStatusIndicator");
  const textEl = el("apiStatusText");
  
  if (!apiKey || apiKey.trim() === "") {
    indicatorEl.textContent = "⚪";
    textEl.textContent = "Chave não configurada";
    statusEl.classList.remove("connected");
  } else if (apiKey.startsWith("sk-")) {
    indicatorEl.textContent = "🟢";
    textEl.textContent = "Chave configurada";
    statusEl.classList.add("connected");
  } else {
    indicatorEl.textContent = "🟡";
    textEl.textContent = "Formato de chave inválido";
    statusEl.classList.remove("connected");
  }
}

el("toggleApiKeyVisibility").addEventListener("click", () => {
  const input = el("openaiApiKey");
  const btn = el("toggleApiKeyVisibility");
  
  if (input.type === "password") {
    input.type = "text";
    btn.textContent = "🙈";
  } else {
    input.type = "password";
    btn.textContent = "👁️";
  }
});

el("openaiApiKey").addEventListener("input", (e) => {
  updateApiStatus(e.target.value);
});

el("testApiKey").addEventListener("click", async () => {
  const apiKey = el("openaiApiKey").value.trim();
  
  if (!apiKey) {
    setStatus("❌ Digite uma chave API primeiro", false);
    return;
  }
  
  if (!apiKey.startsWith("sk-")) {
    setStatus("❌ Formato de chave inválido (deve começar com 'sk-')", false);
    return;
  }
  
  setStatus("Testando conexão...", true);
  const indicatorEl = el("apiStatusIndicator");
  const textEl = el("apiStatusText");
  
  try {
    const response = await send("AI_CHAT", {
      apiKey: apiKey,
      messages: [{ role: "user", content: "Teste" }],
      maxTokens: 10
    });
    
    if (response?.ok && response?.text) {
      indicatorEl.textContent = "🟢";
      textEl.textContent = "Conexão bem-sucedida!";
      setStatus("✅ Conexão com OpenAI estabelecida com sucesso!", true);
    } else {
      indicatorEl.textContent = "🔴";
      textEl.textContent = "Erro na conexão";
      setStatus("❌ Erro: " + (response?.error || "Falha na comunicação"), false);
    }
  } catch (e) {
    indicatorEl.textContent = "🔴";
    textEl.textContent = "Erro na conexão";
    setStatus("❌ Erro ao testar: " + (e.message || e), false);
  }
});

// -------------------------
// Copilot Mode Functions
// -------------------------
async function loadCopilotData() {
  try {
    const resp = await send("GET_CONFIDENCE", {});
    if (!resp?.ok) {
      console.error("Failed to load copilot data:", resp?.error);
      return;
    }
    
    const { score, level, metrics, config, points_to_threshold } = resp;
    
    // Update confidence bar and percentage
    el("confidencePercent").textContent = `${Math.round(score)}%`;
    const fillEl = el("confidenceFill");
    fillEl.style.width = `${score}%`;
    fillEl.setAttribute("data-level", level.level);
    
    // Update confidence level
    el("confidenceLevel").innerHTML = `
      <span class="level-emoji">${level.emoji}</span>
      <span class="level-label">${level.label}</span>
      <span class="level-desc">${level.description}</span>
    `;
    
    // Update threshold indicator
    el("confidenceThreshold").style.left = `${config.copilot_threshold}%`;
    
    // Update copilot controls
    el("copilotEnabled").checked = config.copilot_enabled;
    el("copilotEnabled").disabled = score < config.copilot_threshold;
    el("copilotStatusText").textContent = config.copilot_enabled 
      ? "Modo Copiloto Ativo" 
      : "Modo Copiloto Desativado";
    
    el("copilotThreshold").value = config.copilot_threshold;
    el("thresholdValue").textContent = `${config.copilot_threshold}%`;
    
    // Update stats
    el("statGood").textContent = metrics.total_good;
    el("statBad").textContent = metrics.total_bad;
    el("statCorrections").textContent = metrics.total_corrections;
    el("statAutoSent").textContent = metrics.total_auto_sent;
    
    // Update goal
    const goalEl = el("copilotGoal");
    if (score >= config.copilot_threshold) {
      goalEl.innerHTML = "🎉 <strong>Meta Atingida!</strong> Modo Copiloto disponível";
      goalEl.classList.add("achieved");
    } else {
      goalEl.innerHTML = `🎯 <strong>Faltam <span id="pointsToGoal">${Math.round(points_to_threshold)}</span> pontos</strong> para o Modo Copiloto`;
      goalEl.classList.remove("achieved");
    }
  } catch (e) {
    console.error("Error loading copilot data:", e);
  }
}

el("copilotEnabled").addEventListener("change", async (e) => {
  const enabled = e.target.checked;
  const resp = await send("TOGGLE_COPILOT", { enabled });
  if (resp?.ok) {
    el("copilotStatusText").textContent = enabled 
      ? "Modo Copiloto Ativo" 
      : "Modo Copiloto Desativado";
  } else {
    e.target.checked = !enabled;
  }
});

el("copilotThreshold").addEventListener("input", (e) => {
  el("thresholdValue").textContent = `${e.target.value}%`;
});

el("copilotThreshold").addEventListener("change", async (e) => {
  const threshold = Number(e.target.value);
  const resp = await send("SET_THRESHOLD", { threshold });
  if (resp?.ok) {
    el("confidenceThreshold").style.left = `${threshold}%`;
    await loadCopilotData();
  }
});

// -------------------------
// Quick Replies Functions
// -------------------------
function addQuickReply() {
  const trigger = el("qrTrigger").value.trim().toLowerCase();
  const response = el("qrResponse").value.trim();
  
  if (!trigger || !response) {
    setStatus("Preencha gatilho e resposta", false);
    return;
  }
  
  const newReply = {
    id: `qr_${Date.now()}`,
    trigger,
    response,
    createdAt: new Date().toISOString()
  };
  
  quickReplies.push(newReply);
  renderQuickReplies(quickReplies);
  saveSettings();
  
  // Limpar form
  el("qrTrigger").value = "";
  el("qrResponse").value = "";
  setStatus("Mensagem rápida adicionada ✅", true);
}

function removeQuickReply(id) {
  quickReplies = quickReplies.filter(qr => qr.id !== id);
  renderQuickReplies(quickReplies);
  saveSettings();
  setStatus("Mensagem rápida removida", true);
}

function renderQuickReplies(replies) {
  const container = el("quickReplyList");
  if (!replies.length) {
    container.innerHTML = '<p class="empty-state">Nenhuma mensagem rápida cadastrada</p>';
    return;
  }
  
  container.innerHTML = replies.map(qr => `
    <div class="quick-reply-item" data-id="${qr.id}">
      <div class="qr-trigger">/${qr.trigger}</div>
      <div class="qr-response">${escapeHtml(qr.response)}</div>
      <button class="btn-delete" data-id="${qr.id}">🗑️</button>
    </div>
  `).join('');
  
  // Add event listeners for delete buttons
  container.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = e.target.dataset.id;
      removeQuickReply(id);
    });
  });
}

el("addQuickReply").addEventListener("click", addQuickReply);

// -------------------------
// Team Functions
// -------------------------
function addTeamMember() {
  const name = el("memberName").value.trim();
  let phone = el("memberPhone").value.trim().replace(/\D/g, '');
  
  // FIX 4: Nome é OPCIONAL, apenas número é obrigatório
  if (!phone) {
    setStatus("Preencha o número", false);
    return;
  }
  
  // FIX 3: Validação flexível: 10-15 dígitos (aceita fixos e celulares)
  if (phone.length < 10 || phone.length > 15) {
    setStatus("Número deve ter entre 10 e 15 dígitos", false);
    return;
  }
  
  // Check for duplicate phone numbers
  if (teamMembers.some(m => m.phone === phone)) {
    setStatus("Número já cadastrado", false);
    return;
  }
  
  const newMember = {
    id: `tm_${Date.now()}`,
    name: name || '', // FIX 4: Permite vazio
    phone,
    selected: false,
    createdAt: new Date().toISOString()
  };
  
  teamMembers.push(newMember);
  renderTeamMembers(teamMembers);
  saveSettings();
  
  // Limpar form
  el("memberName").value = "";
  el("memberPhone").value = "";
  setStatus("Membro adicionado ✅", true);
}

function removeTeamMember(id) {
  teamMembers = teamMembers.filter(m => m.id !== id);
  renderTeamMembers(teamMembers);
  saveSettings();
  setStatus("Membro removido", true);
}

function toggleMemberSelection(id) {
  const member = teamMembers.find(m => m.id === id);
  if (member) {
    member.selected = !member.selected;
    renderTeamMembers(teamMembers);
    updateMessagePreview();
  }
}

function selectAllMembers() {
  teamMembers.forEach(m => m.selected = true);
  renderTeamMembers(teamMembers);
  updateMessagePreview();
}

function clearSelection() {
  teamMembers.forEach(m => m.selected = false);
  renderTeamMembers(teamMembers);
  updateMessagePreview();
}

function renderTeamMembers(members) {
  const container = el("teamMembersContainer");
  if (!members.length) {
    container.innerHTML = '<p class="empty-state">Nenhum membro cadastrado</p>';
    return;
  }
  
  container.innerHTML = members.map(m => `
    <div class="team-member-item ${m.selected ? 'selected' : ''}" data-id="${m.id}">
      <label class="checkbox-container">
        <input type="checkbox" ${m.selected ? 'checked' : ''} data-id="${m.id}">
      </label>
      <div class="member-info">
        <span class="member-name">${escapeHtml(m.name)}</span>
        <span class="member-phone">${formatPhone(m.phone)}</span>
      </div>
      <button class="btn-delete" data-id="${m.id}">🗑️</button>
    </div>
  `).join('');
  
  // Add event listeners
  container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', (e) => {
      toggleMemberSelection(e.target.dataset.id);
    });
  });
  
  container.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      removeTeamMember(e.target.dataset.id);
    });
  });
  
  updateMessagePreview();
}

function formatPhone(phone) {
  // Handle different phone number formats
  if (phone.length === 13) {
    // International format: +55 11 99999-9999 (Brazil)
    return phone.replace(/(\d{2})(\d{2})(\d{5})(\d{4})/, '+$1 ($2) $3-$4');
  } else if (phone.length === 12) {
    // International format: +1 234 567-8901 (USA/Canada)
    return phone.replace(/(\d{1})(\d{3})(\d{3})(\d{4})/, '+$1 ($2) $3-$4');
  } else if (phone.length === 11) {
    // National format: (11) 99999-9999
    return phone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
  } else if (phone.length === 10) {
    // National format: (11) 9999-9999
    return phone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
  } else if (phone.length >= 14) {
    // Very long international format - show country code + rest
    return phone.replace(/(\d{2})(\d+)/, '+$1 $2');
  }
  // Fallback: just return as-is if format unknown
  return phone;
}

function updateMessagePreview() {
  const senderName = el("senderName").value || "Empresa";
  const message = el("teamMessage").value || "Sua mensagem aqui...";
  const selectedCount = teamMembers.filter(m => m.selected).length;
  
  el("teamMessagePreview").innerHTML = `
    📱 <strong>Preview da notificação:</strong><br>
    "<strong>${escapeHtml(senderName)}:</strong> ${escapeHtml(message.substring(0, 50))}${message.length > 50 ? '...' : ''}"<br>
    <small>${selectedCount} membro(s) selecionado(s)</small>
  `;
}

async function sendToTeam() {
  const senderName = el("senderName").value.trim();
  const message = el("teamMessage").value.trim();
  const selectedMembers = teamMembers.filter(m => m.selected);
  
  if (!message) {
    setStatus("Digite uma mensagem", false);
    return;
  }
  
  if (!selectedMembers.length) {
    setStatus("Selecione pelo menos um membro", false);
    return;
  }
  
  const fullMessage = senderName ? `*${senderName}:* ${message}` : message;
  
  setStatus(`Enviando para ${selectedMembers.length} membro(s)...`, true);
  
  try {
    // FIX 2: Usar Promise wrapper para garantir resposta
    const response = await new Promise((resolve, reject) => {
      let timeout = setTimeout(() => {
        timeout = null;
        reject(new Error(`Timeout: WhatsApp Web não respondeu em ${TEAM_SEND_TIMEOUT_MS/1000}s`));
      }, TEAM_SEND_TIMEOUT_MS);
      
      chrome.runtime.sendMessage({
        type: "SEND_TO_TEAM",
        payload: {
          members: selectedMembers,
          message: fullMessage,
          senderName
        }
      }, (resp) => {
        if (timeout) {
          clearTimeout(timeout);
          timeout = null;
        }
        if (chrome.runtime.lastError) {
          reject(new Error(chrome.runtime.lastError.message));
        } else {
          resolve(resp);
        }
      });
    });
    
    if (response?.ok) {
      setStatus(`✅ Enviado para ${selectedMembers.length} membro(s)!`, true);
      el("teamMessage").value = "";
      clearSelection();
      updateMessagePreview();
    } else {
      setStatus(`❌ Erro: ${response?.error || 'Falha desconhecida'}`, false);
    }
  } catch (e) {
    setStatus(`❌ Erro: ${e.message}`, false);
  }
}

// Team event listeners
el("addMember").addEventListener("click", addTeamMember);
el("selectAllMembers").addEventListener("click", selectAllMembers);
el("clearSelection").addEventListener("click", clearSelection);
el("sendToTeam").addEventListener("click", sendToTeam);
el("senderName").addEventListener("input", updateMessagePreview);
el("teamMessage").addEventListener("input", updateMessagePreview);

// -------------------------
// Utility Functions
// -------------------------
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// -------------------------
// Initialize
// -------------------------
load().catch((e) => setStatus(String(e?.message || e), false));
