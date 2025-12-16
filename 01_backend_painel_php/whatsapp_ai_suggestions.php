<?php
declare(strict_types=1);

use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Services\Whatsapp\WhatsappAiSuggestionService;
use RedeAlabama\Repositories\WhatsappMessageRepository;



require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/app/Support/Env.php';
require_once __DIR__ . '/app/Support/Config.php';
require_once __DIR__ . '/app/Support/RequestContext.php';
require_once __DIR__ . '/app/Repositories/BaseRepository.php';
require_once __DIR__ . '/app/Repositories/WhatsappMessageRepository.php';
require_once __DIR__ . '/rate_limiter.php';

require_role(['Administrador', 'Gerente', 'Vendedor']);

header('Content-Type: application/json; charset=utf-8');

/**
 * Mapeia a ação enviada pela extensão/painel (new, improve, rephrase, etc.)
 * para instruções de tom mais específicas para o modelo de linguagem.
 */
function mapear_tom_triplo_ai(?string $tomBase, ?string $acaoIa): ?string
{
    $base = $tomBase ?: 'neutro e profissional';

    return match ($acaoIa) {
        'new' => $base . ', gerando uma nova resposta completa, clara e útil para o cliente, com foco em solução e proximidade',
        'improve' => $base . ', melhorando a redação da resposta do vendedor, mantendo a intenção original e aumentando a persuasão',
        'rephrase' => $base . ', reformulando a resposta em outras palavras, mantendo o mesmo sentido e evitando repetições',
        'longer' => $base . ', mais detalhado e explicativo, adicionando exemplos e esclarecimentos relevantes sem enrolação desnecessária',
        'shorter' => $base . ', mais curto e direto ao ponto, mantendo clareza, cordialidade e a informação essencial',
        default => $tomBase ?: $base,
    };
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'ok'    => false,
        'error' => 'Método não suportado. Use POST.'
    ], JSON_UNESCAPED_UNICODE);
    return;
}

// Rate limiting básico por usuário+IP para chamadas de IA via painel
$currentUserId = (int) ($_SESSION['usuario_id'] ?? 0);
$ip            = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$bucket        = 'whatsapp_ai:' . $currentUserId . ':' . $ip;

if (!rate_limit_or_fail($bucket, 60, 3600)) { // 60 chamadas/h por usuário+IP
    log_app_event('ia', 'whatsapp_ai_rate_limited', [
        'user_id'    => $currentUserId,
        'ip'         => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    echo json_encode([
        'ok'    => false,
        'error' => 'Limite de uso de IA atingido. Tente novamente em alguns minutos.'
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$threadId    = (string)($_POST['thread_id']        ?? '');
$contatoNome = (string)($_POST['contato_nome']     ?? '');
$contatoTel  = (string)($_POST['contato_telefone'] ?? '');
$ultimaMsg   = (string)($_POST['ultima_mensagem']  ?? '');
$tom         = isset($_POST['tom']) ? (string)$_POST['tom'] : null;
$acaoIa      = isset($_POST['acao_ia']) ? (string)$_POST['acao_ia'] : '';
$acaoIa      = trim($acaoIa);
if ($acaoIa === '') {
    $acaoIa = null;
}
$tom = mapear_tom_triplo_ai($tom, $acaoIa);


$llmConfig = LlmService::fromEnv();
$repo      = new WhatsappMessageRepository($pdo);
$aiService = new WhatsappAiSuggestionService($llmConfig, $repo);

$result = $aiService->gerarSugestao(
    $currentUserId,
    $threadId,
    $contatoNome,
    $contatoTel,
    $ultimaMsg,
    $tom,
    $acaoIa ?? null
);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
