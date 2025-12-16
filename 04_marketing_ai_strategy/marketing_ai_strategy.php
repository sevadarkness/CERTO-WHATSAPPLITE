<?php
declare(strict_types=1);

require_once __DIR__ . '/marketing_bootstrap.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payload JSON inválido.']);
    exit;
}

if (!csrf_validate_from_array($data)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token inválido. Atualize a página e tente novamente.']);
    exit;
}

$topic   = trim((string)($data['topic'] ?? ''));
$goal    = trim((string)($data['goal'] ?? ''));
$geo     = trim((string)($data['geo'] ?? ''));
$extra   = trim((string)($data['extra'] ?? ''));
$model   = trim((string)($data['model'] ?? 'gpt-4.1'));
$channels = $data['channels'] ?? [];

if ($topic === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tema/negócio é obrigatório.']);
    exit;
}

if (!is_array($channels)) {
    $channels = [];
}

$mainGoal = $goal !== ''
    ? $goal
    : 'maximizar performance de funil completo de marketing digital (aquisição, nutrição e conversão)';

$keyChannels = !empty($channels)
    ? implode(', ', $channels)
    : 'Meta Ads (Facebook/Instagram), WhatsApp e canais digitais complementares';

$geoText = $geo !== ''
    ? "Região/mercado: {$geo}."
    : "Região/mercado: não especificado (assuma Brasil como base).";

$extraText = $extra !== ''
    ? "Contexto adicional fornecido pelo usuário:\n{$extra}\n"
    : "Sem contexto extra fornecido.\n";

log_app_event('marketing_ai_strategy', 'request', [
    'topic'    => mb_substr($topic, 0, 120),
    'goal'     => mb_substr($goal, 0, 120),
    'geo'      => mb_substr($geo, 0, 80),
    'channels' => $channels,
    'model'    => $model,
]);

$systemInstructions = <<<TXT
Você é um consultor sênior de marketing digital e growth, especializado em:
- Funis de aquisição, nutrição e conversão.
- Meta Ads (Facebook/Instagram) com foco em performance.
- Estruturas de campanhas que clicam para WhatsApp.
- Ambientes com produtos sensíveis (como sexy shop), respeitando políticas de anúncios e boas práticas de comunicação.

Seu estilo:
- Extreamente objetivo, pragmático, orientado a ação.
- Estrutura o raciocínio em seções claras, com títulos e listas.
- Não inventa dados específicos (como números de mercado) se não tiver certeza; fala em termos relativos (alto, médio, baixo).
- Sempre traz o olhar de operação real de tráfego: naming das campanhas, públicos, criativos, métricas e rotina de otimização.

Se o tema fornecido for muito genérico, faça suposições razoáveis e explicite essas suposições em 2–3 linhas, para não devolver uma resposta superficial.
TXT;

$userPrompt = <<<PROMPT
Gere uma estratégia de marketing profissional, altamente detalhada e pragmática para o seguinte cenário:

Tema / negócio: {$topic}
Objetivo principal: {$mainGoal}
Canais prioritários: {$keyChannels}
{$geoText}

{$extraText}
Requisitos de saída:

1. Resuma em 4–6 linhas o diagnóstico do cenário e do desafio principal.
2. Proponha uma arquitetura de funil completa, com foco em:
   - Topo: aquisição e geração de atenção.
   - Meio: nutrição e educação do lead.
   - Fundo: conversão (com ênfase em WhatsApp quando aplicável).
   - Pós-venda e recorrência.

3. Se incluir Meta Ads:
   - Detalhe estrutura de campanhas (nomes sugeridos, objetivos).
   - Detalhe conjuntos de anúncios (públicos frios, mornos e quentes, com exemplos de interesses e lookalikes).
   - Sugira criativos (ângulos de copy, hooks, formatos).
   - Inclua observações de política (principalmente se o tema for sensível como sexy shop, saúde, etc.).

4. Se incluir WhatsApp:
   - Defina como será a conversão para WhatsApp (ads que clicam para WhatsApp, entry points, etc.).
   - Desenhe um roteiro de atendimento básico (primeira mensagem, qualificação, oferta, fechamento, follow-up).
   - Sugira organização com tags/etiquetas e métricas de atendimento.

5. Traga um quadro de KPIs mínimos por etapa do funil e uma rotina de otimização semanal.

6. Escreva tudo de forma direta, estruturada, com seções e bullet points. Não enrole, foque em ação.
7. Responda em português do Brasil.
PROMPT;

$apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: ''));
if ($apiKey === '') {
    $apiKey = trim((string)(getenv('ALABAMA_OPENAI_API_KEY') ?: ''));
}
if ($apiKey === '') {
    $apiKey = trim((string)(getenv('LLM_OPENAI_API_KEY') ?: ''));
}

if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'OPENAI_API_KEY (ou ALABAMA_OPENAI_API_KEY / LLM_OPENAI_API_KEY) não configurada no servidor.']);
    exit;
}

$payload = [
    'model' => $model,
    'instructions' => $systemInstructions,
    'input' => $userPrompt,
    'max_output_tokens' => 2200,
    'temperature' => 0.45,
    'store' => false,
];

$ch = curl_init('https://api.openai.com/v1/responses');
    $sec = __DIR__ . '/../01_backend_painel_php/app/Support/Security.php';
    if (file_exists($sec)) {
        require_once $sec;
    }
    $authHeader = class_exists('Security') ? \Security::build_bearer_header($apiKey) : ('Authorization: Bearer ' . $apiKey);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            $authHeader,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 60,
    ]);

$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($responseBody === false) {
    log_app_event('marketing_ai_strategy', 'curl_error', [
        'error' => $curlErr,
    ]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro de comunicação com a API de IA.']);
    exit;
}

$resp = json_decode($responseBody, true);
if (!is_array($resp)) {
    log_app_event('marketing_ai_strategy', 'invalid_json', [
        'body' => mb_substr($responseBody, 0, 1000),
    ]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Resposta inválida da API de IA.']);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    $errMsg = $resp['error']['message'] ?? ($resp['error'] ?? 'Erro da API de IA.');
    log_app_event('marketing_ai_strategy', 'api_error', [
        'status' => $httpCode,
        'error'  => $errMsg,
    ]);
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'error' => 'API de IA retornou erro: ' . $errMsg]);
    exit;
}

$text = $resp['output_text'] ?? '';

if ($text === '' && isset($resp['output']) && is_array($resp['output']) && count($resp['output']) > 0) {
    try {
        $first = $resp['output'][0];
        if (isset($first['content']) && is_array($first['content']) && count($first['content']) > 0) {
            foreach ($first['content'] as $part) {
                if (!is_array($part)) continue;
                if (isset($part['output_text']) && is_string($part['output_text'])) {
                    $text = $part['output_text'];
                    break;
                }
                if (isset($part['text']) && is_string($part['text'])) {
                    $text = $part['text'];
                    break;
                }
            }
        } elseif (isset($first['text']) && is_string($first['text'])) {
            $text = $first['text'];
        }
    } catch (Throwable $e) {
        // segue para fallback abaixo
    }
}

if ($text === '') {
    $text = json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

log_app_event('marketing_ai_strategy', 'success', [
    'model' => $resp['model'] ?? $model,
    'usage' => $resp['usage'] ?? null,
]);

echo json_encode([
    'ok'    => true,
    'text'  => $text,
    'model' => $resp['model'] ?? $model,
    'usage' => $resp['usage'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);