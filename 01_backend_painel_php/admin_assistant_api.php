<?php
declare(strict_types=1);

/**
 * Endpoint JSON para Assistente Interno IA flutuante (V19 Ultra).
 *
 * Inspirado no comportamento das extensões de navegador (chatbot em qualquer tela),
 * mas totalmente implementado no backend PHP do painel.
 */

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/whatsapp_llm_helper.php';
require_once __DIR__ . '/llm_templates.php';
require_once __DIR__ . '/llm_vendor_prefs_model.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'    => false,
        'error' => 'Método não permitido. Use POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Lê corpo bruto apenas uma vez
$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    $data = [];
}

// Validação CSRF baseada no corpo JSON
if (!csrf_validate_from_array($data)) {
    http_response_code(403);
    echo json_encode([
        'ok'    => false,
        'error' => 'CSRF inválido. Recarregue a página.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pergunta = trim((string)($data['pergunta'] ?? $data['q'] ?? ''));

if ($pergunta === '') {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Pergunta vazia.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Usuário atual e preferências
$user = function_exists('current_user') ? current_user() : null;
$usuarioId = $user && isset($user['id']) ? (int)$user['id'] : 0;

// Tons e templates padrões (mesma base do admin_assistant / whatsapp_ai)
$whatsapp_tones = [
    'neutro'    => 'tom neutro, técnico e direto',
    'amigavel'  => 'tom amigável e próximo, com vocabulário simples',
    'formal'    => 'tom mais formal, mas ainda humano',
    'engajador' => 'tom engajador, despertando desejo e urgência sem ser agressivo',
];

$templates_raw = [
    'generico_operacao'         => 'Responder dúvidas internas sobre operação, processos, estoque ou funil de vendas da Rede Alabama.',
    'whatsapp_resposta_cliente' => 'Gerar uma resposta pronta para enviar no WhatsApp para um cliente, mantendo o estilo da marca Rede Alabama.',
    'whatsapp_cobranca'         => 'Cobrança amigável de cliente inadimplente, com firmeza mas respeito.',
    'whatsapp_reativacao'       => 'Reativação de cliente inativo, usando gatilhos de benefício, novidade e leve escassez.',
    'campanha_broadcast'        => 'Rascunhos de campanhas de broadcast em WhatsApp com foco em engajamento e conversão.',
];

// Puxa templates dinâmicos se existirem
$templates = alabama_llm_templates_get($pdo, 'admin_assistant', $templates_raw);

// Preferências do vendedor (auto-aprendizado)
$prefTone = null;
$prefTemplate = null;
if ($usuarioId > 0) {
    $prefs = alabama_llm_get_vendor_prefs($pdo, $usuarioId);
    if (is_array($prefs)) {
        $prefTone     = $prefs['preferred_tone'] ?? null;
        $prefTemplate = $prefs['preferred_template_slug'] ?? null;
    }
}

// Se o front-end mandar overrides, eles têm prioridade
$toneKey     = isset($data['tone']) ? (string)$data['tone'] : '';
$templateKey = isset($data['template']) ? (string)$data['template'] : '';

if ($toneKey === '' && is_string($prefTone) && $prefTone !== '') {
    $toneKey = $prefTone;
}
if ($templateKey === '' && is_string($prefTemplate) && $prefTemplate !== '') {
    $templateKey = $prefTemplate;
}

// Resolve descrições
$toneLabel    = isset($whatsapp_tones[$toneKey]) ? $whatsapp_tones[$toneKey] : null;
$templateDesc = isset($templates[$templateKey]) ? $templates[$templateKey] : null;

// Monta contexto em linha com o admin_assistant
$contextParts = [];

if ($templateDesc) {
    $contextParts[] = 'Objetivo da tarefa: ' . $templateDesc;
}
if ($toneLabel) {
    $contextParts[] = 'Tom desejado da resposta: ' . $toneLabel . '.';
}
if ($user) {
    $contextParts[] = 'Usuário interno: ' . ($user['nome'] ?? ('ID ' . $usuarioId));
    $contextParts[] = 'Nível de acesso: ' . ($user['nivel_acesso'] ?? '');
}

if (!empty($data['page'])) {
    $contextParts[] = 'Tela atual do painel: ' . (string)$data['page'];
}

if ($contextParts) {
    $promptComContexto = implode("\n", $contextParts) . "\n\nPedido principal:\n" . $pergunta;
} else {
    $promptComContexto = $pergunta;
}

// Carrega configurações do bot/LLM e injeta system prompt interno
$settings = whatsapp_bot_load_settings();
if (!$settings) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Configuração do bot/LLM não encontrada. Ajuste em "Bot WhatsApp IA".',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$settingsLocal = $settings;

// System prompt interno inspirado na UX das extensões (assistente contextual em qualquer tela)
$internalSystemPrompt = <<<PROMPT
Você é um assistente interno do painel administrativo da Rede Alabama.
Ajuda administradores, gerentes e vendedores a:
- entender números, campanhas, fluxos de WhatsApp e métricas de IA;
- sugerir próximos passos operacionais e de marketing;
- escrever textos e prompts prontos para usar no WhatsApp ou em campanhas;
- ler, resumir e reescrever textos internos do painel (mensagens, scripts, campanhas, fluxos) quando esse conteúdo for fornecido;
- manter respostas objetivas, em português do Brasil, focadas em ação, preferencialmente estruturadas em tópicos (visão geral, análise, próximos passos).

Quando a pergunta envolver clientes ou leads, lembre que você está falando com o time interno (não com o cliente final).
PROMPT;

$existingSystem = trim((string)($settings['llm_system_prompt'] ?? ''));
if ($existingSystem !== '') {
    $settingsLocal['llm_system_prompt'] = $internalSystemPrompt . "\n\n---\n\n" . $existingSystem;
} else {
    $settingsLocal['llm_system_prompt'] = $internalSystemPrompt;
}

// Chamada ao LLM reutilizando a mesma infraestrutura do bot WhatsApp
$historico = []; // para este endpoint, começamos sem histórico
$resultado = whatsapp_bot_chamar_llm($promptComContexto, $historico, $settingsLocal);

if (!is_array($resultado) || empty($resultado['ok'])) {
    log_app_event('admin_assistant', 'llm_error', [
        'usuario_id' => $usuarioId,
    ]);
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Falha ao chamar LLM. Verifique configuração e logs.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$resposta = (string)($resultado['resposta'] ?? '');

if ($resposta === '') {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'LLM retornou resposta vazia.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Logging compacto para auditoria de IA
$promptLog   = strlen($promptComContexto) > 1000 ? substr($promptComContexto, 0, 1000) . '...' : $promptComContexto;
$respostaLog = strlen($resposta) > 1000 ? substr($resposta, 0, 1000) . '...' : $resposta;

log_app_event('admin_assistant', 'fab_request', [
    'usuario_id' => $usuarioId,
    'tone'       => $toneKey,
    'template'   => $templateKey,
    'page'       => $data['page'] ?? null,
    'prompt'     => $promptLog,
    'resposta'   => $respostaLog,
]);

echo json_encode([
    'ok'       => true,
    'resposta' => $resposta,
], JSON_UNESCAPED_UNICODE);
exit;
