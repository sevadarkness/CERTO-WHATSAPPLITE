<?php
declare(strict_types=1);

require_once __DIR__ . '/marketing_bootstrap.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>IA Marketing • Rede Alabama</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root {
      --bg: #0f172a;
      --accent: #22c55e;
      --accent-soft: rgba(34, 197, 94, 0.12);
      --text: #e5e7eb;
      --text-soft: #9ca3af;
      --danger: #f97373;
      --border: #1f2933;
      --radius-lg: 12px;
      --radius-md: 8px;
      --shadow-soft: 0 18px 45px rgba(15, 23, 42, 0.7);
      --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      --font-sans: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: var(--font-sans);
      background: radial-gradient(circle at top left, #1e293b 0, #020617 50%, #000 100%);
      color: var(--text);
      display: flex;
      align-items: stretch;
      justify-content: center;
      padding: 8px;
    }

    .app-shell {
      width: 100%;
      max-width: 1100px;
      background: linear-gradient(145deg, rgba(15, 23, 42, 0.98), rgba(15, 23, 42, 0.98));
      border-radius: 24px;
      border: 1px solid rgba(148, 163, 184, 0.25);
      box-shadow: var(--shadow-soft);
      overflow: hidden;
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.8fr);
      gap: 0;
    }

    @media (max-width: 900px) {
      .app-shell {
        grid-template-columns: minmax(0, 1fr);
      }
    }

    .sidebar {
      padding: 16px 18px 18px;
      border-right: 1px solid rgba(148, 163, 184, 0.2);
      background: radial-gradient(circle at top right, rgba(34, 197, 94, 0.15), transparent 55%);
    }

    .main {
      padding: 16px 18px 18px;
      background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.14), transparent 55%);
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.85);
      border: 1px solid rgba(148, 163, 184, 0.6);
      color: var(--text-soft);
      font-size: 11px;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 8px;
    }

    .badge span.dot {
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: var(--accent);
      box-shadow: 0 0 12px rgba(34, 197, 94, 0.9);
    }

    h1 {
      font-size: 18px;
      margin: 0 0 6px;
      letter-spacing: 0.02em;
    }

    .subtitle {
      font-size: 12px;
      color: var(--text-soft);
      margin-bottom: 16px;
      line-height: 1.4;
    }

    .form-group {
      margin-bottom: 12px;
    }

    label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      margin-bottom: 4px;
    }

    label span.hint {
      font-weight: 400;
      color: var(--text-soft);
      font-size: 11px;
      margin-left: 4px;
    }

    input[type="text"],
    textarea,
    select {
      width: 100%;
      padding: 8px 10px;
      border-radius: var(--radius-md);
      border: 1px solid rgba(148, 163, 184, 0.45);
      background: rgba(15, 23, 42, 0.95);
      color: var(--text);
      font-size: 12px;
      outline: none;
      transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
      resize: vertical;
      min-height: 32px;
      max-height: 160px;
    }

    input::placeholder,
    textarea::placeholder {
      color: rgba(148, 163, 184, 0.7);
    }

    input:focus,
    textarea:focus,
    select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.35);
      background: rgba(15, 23, 42, 1);
    }

    .inline-fields {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    @media (max-width: 900px) {
      .inline-fields {
        grid-template-columns: minmax(0, 1fr);
      }
    }

    .pill-row {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 4px;
    }

    .pill {
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 11px;
      border: 1px solid rgba(148, 163, 184, 0.55);
      color: var(--text-soft);
      cursor: pointer;
      user-select: none;
      transition: all 0.12s ease;
      background: rgba(15, 23, 42, 0.9);
    }

    .pill.active {
      border-color: var(--accent);
      background: var(--accent-soft);
      color: var(--accent);
    }

    .pill:hover {
      border-color: var(--accent);
    }

    .button-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: 12px;
    }

    .actions-left {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    button[type="submit"] {
      border: none;
      border-radius: 999px;
      padding: 7px 14px;
      background: linear-gradient(135deg, #16a34a, #22c55e);
      color: #f9fafb;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 12px 30px rgba(22, 163, 74, 0.45);
      transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
      white-space: nowrap;
    }

    button[type="submit"]:hover {
      transform: translateY(-1px);
      filter: brightness(1.05);
      box-shadow: 0 15px 40px rgba(22, 163, 74, 0.55);
    }

    button[type="submit"]:disabled {
      opacity: 0.55;
      cursor: default;
      transform: none;
      box-shadow: none;
    }

    .btn-secondary {
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, 0.6);
      background: rgba(15, 23, 42, 0.85);
      color: var(--text-soft);
      font-size: 11px;
      padding: 5px 9px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      transition: all 0.12s ease;
    }

    .btn-secondary:hover {
      border-color: rgba(148, 163, 184, 0.95);
      color: var(--text);
    }

    .status-chip {
      font-size: 11px;
      color: var(--text-soft);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .status-dot {
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: rgba(148, 163, 184, 0.7);
    }

    .status-dot.loading {
      background: var(--accent);
      box-shadow: 0 0 12px rgba(34, 197, 94, 0.9);
      animation: pulse 1.1s infinite ease-in-out;
    }

    .status-dot.error {
      background: var(--danger);
      box-shadow: 0 0 10px rgba(248, 113, 113, 0.8);
    }

    @keyframes pulse {
      0% { transform: scale(1); opacity: 0.9; }
      50% { transform: scale(1.25); opacity: 0.4; }
      100% { transform: scale(1); opacity: 0.9; }
    }

    .output-header {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 6px;
    }

    .output-title {
      font-size: 13px;
      font-weight: 500;
    }

    .output-meta {
      font-size: 11px;
      color: var(--text-soft);
    }

    .output-wrapper {
      border-radius: var(--radius-lg);
      border: 1px solid rgba(148, 163, 184, 0.35);
      background: radial-gradient(circle at top left, rgba(15, 23, 42, 0.94), rgba(15, 23, 42, 0.99));
      padding: 9px 10px;
      max-height: 520px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .output-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      border-bottom: 1px solid rgba(30, 64, 175, 0.5);
      padding-bottom: 4px;
      margin-bottom: 2px;
    }

    .output-toolbar-left {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      color: var(--text-soft);
      font-family: var(--font-mono);
    }

    .tag {
      padding: 2px 7px;
      border-radius: 999px;
      border: 1px solid rgba(96, 165, 250, 0.7);
      background: rgba(15, 23, 42, 0.95);
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 0.065em;
    }

    .output-body {
      position: relative;
      overflow-y: auto;
      padding-right: 4px;
      font-size: 12px;
      line-height: 1.5;
      font-family: var(--font-mono);
      white-space: pre-wrap;
    }

    .output-body::-webkit-scrollbar {
      width: 6px;
    }

    .output-body::-webkit-scrollbar-track {
      background: transparent;
    }

    .output-body::-webkit-scrollbar-thumb {
      background: rgba(55, 65, 81, 0.9);
      border-radius: 999px;
    }

    .placeholder-text {
      color: var(--text-soft);
      font-size: 12px;
      line-height: 1.5;
    }

    .error-box {
      margin-top: 6px;
      padding: 6px 8px;
      border-radius: 8px;
      border: 1px solid rgba(248, 113, 113, 0.65);
      background: rgba(127, 29, 29, 0.35);
      font-size: 11px;
      color: #fecaca;
    }

    .hint-list {
      margin: 0;
      padding-left: 16px;
      font-size: 11px;
      color: var(--text-soft);
      line-height: 1.4;
    }

    code {
      font-family: var(--font-mono);
      font-size: 11px;
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="badge">
        <span class="dot"></span>
        <span>IA Marketing • Rede Alabama</span>
      </div>

      <h1>Gerador de Estratégia de Funil</h1>
      <p class="subtitle">
        Digite o tema do negócio (ex.: “Sexy Shop”) e receba um plano
        de marketing estruturado com foco em Meta Ads e conversão via WhatsApp,
        usando a infraestrutura de IA do próprio Alabama.
      </p>

      <form id="strategy-form" autocomplete="off">
        <div class="inline-fields">
          <div class="form-group">
            <label for="modelSelect">
              Modelo de IA
              <span class="hint">usa o provider configurado no backend</span>
            </label>
            <select id="modelSelect" name="modelSelect">
              <option value="gpt-4.1" selected>gpt-4.1 (padrão)</option>
              <option value="gpt-4o">gpt-4o</option>
              <option value="gpt-5-mini">gpt-5-mini</option>
              <option value="gpt-5.1">gpt-5.1</option>
            </select>
          </div>
          <div class="form-group">
            <label>
              Info modelo
              <span class="hint">texto informativo</span>
            </label>
            <div style="font-size:11px;color:var(--text-soft);line-height:1.4;">
              O modelo selecionado é enviado ao backend. A lógica de roteamento
              e billing fica 100% no servidor do Alabama.
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="topic">
            Tema / negócio
            <span class="hint">(obrigatório)</span>
          </label>
          <input
            id="topic"
            name="topic"
            type="text"
            placeholder="Ex.: Sexy Shop focado em vendas via WhatsApp e Meta Ads"
            required
          />
        </div>

        <div class="inline-fields">
          <div class="form-group">
            <label for="goal">
              Objetivo principal
              <span class="hint">impacta o prompt</span>
            </label>
            <input
              id="goal"
              name="goal"
              type="text"
              placeholder="Ex.: maximizar conversão em vendas via WhatsApp"
            />
          </div>
          <div class="form-group">
            <label for="geo">
              Região / mercado
              <span class="hint">campo livre (texto)</span>
            </label>
            <input
              id="geo"
              name="geo"
              type="text"
              placeholder="Ex.: São Paulo - SP, Brasil"
            />
          </div>
        </div>

        <div class="form-group">
          <label>
            Canais prioritários
            <span class="hint">ajuste conforme seu stack</span>
          </label>
          <div class="pill-row" id="channels-pills">
            <div class="pill active" data-value="meta_ads">Meta Ads</div>
            <div class="pill active" data-value="whatsapp">WhatsApp</div>
            <div class="pill" data-value="google_ads">Google Ads</div>
            <div class="pill" data-value="conteudo_org">Conteúdo orgânico</div>
            <div class="pill" data-value="email_crm">Email / CRM</div>
          </div>
        </div>

        <div class="form-group">
          <label for="extra">
            Contexto extra
            <span class="hint">opcional, mas ajuda na precisão</span>
          </label>
          <textarea
            id="extra"
            name="extra"
            rows="3"
            placeholder="Ex.: orçamento mensal, maturidade da operação, se já tem Pixel/Analytics, restrições de política (sexy shop, saúde, etc.)."
          ></textarea>
        </div>

        <div class="button-row">
          <div class="actions-left">
            <button type="submit" id="submit-btn">
              <span>Gerar estratégia</span>
              <span id="spinner" style="display:none;">⏳</span>
            </button>
            <button type="button" class="btn-secondary" id="btn-preset-sexyshop">
              Usar preset “Sexy Shop”
            </button>
          </div>

          <div class="status-chip">
            <span id="status-dot" class="status-dot"></span>
            <span id="status-text">Aguardando entrada</span>
          </div>
        </div>

        <div id="error-box" class="error-box" style="display:none;"></div>

        <div style="margin-top: 8px;">
          <ul class="hint-list">
            <li>Chamada ao endpoint interno <code>marketing_ai_strategy.php</code>.</li>
            <li>Modelo e parâmetros são encaminhados ao backend, que fala com a OpenAI (ou outro provider).</li>
            <li>Prompt completo fica versionado no servidor (manutenível pelo time técnico).</li>
          </ul>
        </div>
      </form>
    </aside>

    <main class="main">
      <div class="output-header">
        <div class="output-title">Resultado da IA (plano de ação)</div>
        <div class="output-meta" id="output-meta">
          Nenhuma consulta ainda.
        </div>
      </div>

      <div class="output-wrapper">
        <div class="output-toolbar">
          <div class="output-toolbar-left">
            <span class="tag">Estratégia</span>
            <span id="toolbar-model">model: gpt-4.1</span>
          </div>
          <div class="output-toolbar-right">
            <button type="button" class="btn-secondary" id="btn-copy">
              Copiar texto
            </button>
          </div>
        </div>

        <div id="output-body" class="output-body">
          <div class="placeholder-text" id="placeholder">
            1) Informe o tema/negócio e contexto básico.<br />
            2) Clique em “Gerar estratégia”.<br />
            3) O backend do Alabama chamará a IA e retornará
            um plano de marketing completo, focado em Meta Ads + WhatsApp,
            em português do Brasil.
          </div>
          <pre id="output-text" style="display:none;"></pre>
        </div>
      </div>
    </main>
  </div>

  <script<?= defined('ALABAMA_CSP_NONCE') ? ' nonce="' . htmlspecialchars((string)ALABAMA_CSP_NONCE, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    const STRATEGY_ENDPOINT = "marketing_ai_strategy.php";
    const CSRF_TOKEN = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    const form = document.getElementById("strategy-form");
    const modelSelect = document.getElementById("modelSelect");
    const topicInput = document.getElementById("topic");
    const goalInput = document.getElementById("goal");
    const geoInput = document.getElementById("geo");
    const extraInput = document.getElementById("extra");
    const channelsPills = document.getElementById("channels-pills");
    const submitBtn = document.getElementById("submit-btn");
    const spinner = document.getElementById("spinner");
    const statusDot = document.getElementById("status-dot");
    const statusText = document.getElementById("status-text");
    const errorBox = document.getElementById("error-box");
    const outputMeta = document.getElementById("output-meta");
    const outputText = document.getElementById("output-text");
    const placeholder = document.getElementById("placeholder");
    const btnCopy = document.getElementById("btn-copy");
    const btnPresetSexyshop = document.getElementById("btn-preset-sexyshop");
    const toolbarModel = document.getElementById("toolbar-model");

    channelsPills.addEventListener("click", (event) => {
      const pill = event.target.closest(".pill");
      if (!pill) return;
      pill.classList.toggle("active");
    });

    btnPresetSexyshop.addEventListener("click", () => {
      if (!topicInput.value) {
        topicInput.value = "Sexy Shop focado em vendas via WhatsApp e Meta Ads";
      }
      if (!goalInput.value) {
        goalInput.value =
          "maximizar conversão de vendas de produtos de bem-estar íntimo via campanhas Meta + WhatsApp";
      }
      if (!extraInput.value) {
        extraInput.value =
          "Tema sensível (produtos íntimos), precisa respeitar políticas de anúncios da Meta " +
          "e focar em bem-estar, relacionamento e discrição. Foco em funil com anúncios que clicam para WhatsApp, " +
          "segmentação inteligente, recuperação de leads e acompanhamento pós-venda.";
      }
      topicInput.focus();
    });

    function setLoading(loading) {
      submitBtn.disabled = loading;
      spinner.style.display = loading ? "inline-block" : "none";
      statusDot.classList.toggle("loading", loading);
      statusText.textContent = loading
        ? "Gerando estratégia com IA..."
        : "Aguardando entrada";
      if (!loading) {
        statusDot.classList.remove("error");
      }
    }

    function showError(message) {
      errorBox.style.display = "block";
      errorBox.textContent = message;
      statusDot.classList.add("error");
      statusText.textContent = "Erro ao gerar estratégia";
    }

    function clearError() {
      errorBox.style.display = "none";
      errorBox.textContent = "";
      statusDot.classList.remove("error");
    }

    function buildPromptPayload() {
      const topic = topicInput.value.trim();
      const goal = goalInput.value.trim();
      const geo = geoInput.value.trim();
      const extra = extraInput.value.trim();
      const selectedChannels = Array.from(
        channelsPills.querySelectorAll(".pill.active")
      ).map(pill => pill.dataset.value);

      return {
        topic,
        goal,
        geo,
        extra,
        channels: selectedChannels
      };
    }

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      clearError();

      const topic = topicInput.value.trim();
      if (!topic) {
        showError("Informe pelo menos o tema/negócio para gerar a estratégia.");
        topicInput.focus();
        return;
      }

      const model = modelSelect.value || "gpt-4.1";
      const payload = buildPromptPayload();
      payload.model = model;
      payload._csrf_token = CSRF_TOKEN;

      setLoading(true);
      placeholder.style.display = "none";
      outputText.style.display = "block";
      outputText.textContent = "";

      try {
        const res = await fetch(STRATEGY_ENDPOINT, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          body: JSON.stringify(payload)
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data || data.ok === false) {
          const msg = (data && data.error) || ("HTTP " + res.status + " ao chamar backend de IA.");
          showError(msg);
          return;
        }

        toolbarModel.textContent = "model: " + (data.model || model);
        outputText.textContent = data.text || "[Resposta vazia do backend de IA]";

        const now = new Date();
        outputMeta.textContent =
          "Última geração: " +
          now.toLocaleString("pt-BR", {
            dateStyle: "short",
            timeStyle: "short"
          }) +
          " • tema: " +
          topic;
      } catch (err) {
        console.error(err);
        showError(
          "Falha ao chamar o backend de IA. Verifique logs no servidor.\nDetalhes: " +
          (err && err.message ? err.message : String(err))
        );
      } finally {
        setLoading(false);
      }
    });

    btnCopy.addEventListener("click", async () => {
      const text = outputText.textContent.trim();
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        btnCopy.textContent = "Copiado!";
        setTimeout(() => {
          btnCopy.textContent = "Copiar texto";
        }, 1200);
      } catch {
        btnCopy.textContent = "Falha ao copiar";
        setTimeout(() => {
          btnCopy.textContent = "Copiar texto";
        }, 1200);
      }
    });
  </script>
</body>
</html>