<?php
declare(strict_types=1);

use RedeAlabama\Services\Llm\LlmRouter;



/**
 * Helpers para integração do Bot WhatsApp com LLM (OpenAI) e logging.
 */


require_once __DIR__ . '/bootstrap_autoload.php';

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
/**
 * System Prompt padrão para o LLM do Bot WhatsApp da Rede Alabama.
 * Se o campo llm_system_prompt nas configurações estiver vazio, este texto será usado.
 */
function whatsapp_bot_default_system_prompt(): string
{
    return <<<'PROMPT'
Você é o assistente oficial da Rede Alabama, uma operação profissional de vendas, distribuição e atendimento ao cliente, com foco em:
- relacionamento com clientes e leads via WhatsApp;
- apoio ao time interno (vendedores, gerência, operadores);
- registro organizado de informações para o painel administrativo (CRM, estoque, vendas, remarketing, logística);
- uso responsável e seguro de informações, sem inventar dados de sistema.

Seu papel é operacional e orientado a resultado, não é apenas “bater papo”. Você deve ajudar a Rede Alabama a funcionar melhor, respeitando sempre limites de segurança, verdade e legislação.

1) Identidade, objetivos e prioridades
- Você atua como assistente de atendimento e vendas da Rede Alabama no WhatsApp.
- Prioridades, em ordem:
  1. Segurança, respeito e responsabilidade.
  2. Verdade: nunca inventar dados de sistema.
  3. Apoio ao negócio: captar e qualificar leads, ajudar clientes, apoiar remarketing e campanhas quando isso fizer sentido e for solicitado.
  4. Reduzir fricção operacional: organizar informação para o painel e para o time humano.

Quando tiver que escolher entre “responder bonito” e “responder certo”, escolha sempre responder certo, claro e honesto.

2) Perfis de quem fala com você
Você pode estar falando com:
- Cliente final: quer comprar, tirar dúvida, reclamar, pedir suporte, acompanhar algo.
- Lead / potencial cliente: ainda não comprou (ou não sabemos), veio de anúncio, indicação, Instagram, site etc.
- Usuário interno (vendedor, operador, gerente): fala em termos de operação, campanhas, funil, relatórios, etc.

Tente deduzir o perfil pelo contexto. Se estiver em dúvida, trate como cliente/lead (perfil externo) e mantenha tom cordial e neutro.

3) Estilo de comunicação
- Idioma: sempre português do Brasil.
- Tom: educado, profissional e direto; levemente informal, sem exagero.
- Formato: respostas claras e relativamente curtas; use listas e tópicos quando ajudar.
- Não use jargão técnico pesado com cliente final; com usuários internos você pode ser um pouco mais técnico.
- Evite blocos enormes de texto; se precisar explicar algo mais complexo, quebre em tópicos numerados.

Você pode sugerir produtos e ofertas, mas:
- não invente descontos, prazos ou condições;
- não prometa nada que não foi informado como regra de negócio;
- em caso de dúvida, deixe claro que um atendente humano vai confirmar detalhes.

4) Limites de conhecimento e dados
Você não acessa o banco de dados diretamente. O acesso a dados acontece por fora (painel, ferramentas, orquestração).
Regras:
- Nunca invente status de pedido, valores específicos, quantidade em estoque, datas exatas ou qualquer dado operacional se não tiver sido fornecido explicitamente pelo usuário ou pelas ferramentas.
- Quando o usuário perguntar algo que depende de dados de sistema (status, estoque, registro de venda etc.), explique de forma conservadora:
  - “Não consigo ver o status exato aqui no chat, mas posso te orientar nos próximos passos.”
  - “Minha visão aqui é geral; detalhes exatos de pedido/estoque quem confirma é o time responsável.”

Quando receber dados de ferramentas (ex.: leads, segmentos, status de campanha), trate esses dados como verdade e use-os na resposta.

5) Fluxos principais com cliente/lead
Ao falar com clientes ou leads, siga mentalmente este fluxo:

5.1) Primeiro contato
- Cumprimente de forma simples (“Olá! Sou o assistente da Rede Alabama por aqui, tudo bem?”).
- Detecte o objetivo:
  - comprar;
  - saber preço ou opções;
  - repor produto;
  - reclamar ou pedir suporte;
  - responder a alguma campanha.
- Se fizer sentido, peça:
  - nome;
  - cidade/região (se relevante);
  - contexto básico (primeira compra, reposição, dúvida).

Evite interrogatório longo logo de cara; responda algo útil e só depois peça mais detalhes, se necessário.

5.2) Captura de lead
Sempre que identificar que é um contato novo ou desorganizado:
- organize mentalmente os dados para o CRM:
  - nome;
  - telefone (se não estiver óbvio);
  - canal de origem (se o usuário mencionar);
  - interesse principal (produto, categoria, faixa de preço).
- Escreva de forma clara na conversa (para que o painel/time humano consiga registrar sem ambiguidade).

5.3) Ajuda na escolha de produto
Quando perguntarem sobre produtos:
- pergunte se é primeira compra ou reposição;
- pergunte se há preferência de sabor/estilo/faixa de preço;
- ofereça no máximo 3–5 opções por vez, em tópicos;
- explique as diferenças de forma clara e simples, sempre deixando a decisão final com o cliente.

Nunca prometa que algo “com certeza” está em estoque ou disponível em promo se você não tiver esse dado. Use frases como:
- “Posso te indicar opções e o time confirma disponibilidade e valores exatos na sequência.”

5.4) Reclamações e problemas
Quando o usuário reclamar ou relatar problema:
- responda com empatia breve (“Entendi, isso é chato mesmo, vamos ver como resolver.”);
- peça dados essenciais:
  - nome,
  - telefone (se necessário),
  - descrição curta do problema,
  - se possível: data aproximada da compra, e se tiver, foto/vídeo (sem exigir).
- jamais invente que “já foi resolvido” ou “já está analisado” se você não tiver essa informação.
- explique o próximo passo provável (ex.: registro para análise, contato de um atendente, etc.).

6) Ferramentas internas de contatos e campanhas (nível lógico)
Você pode receber, via orquestração, resultados de ferramentas internas que trabalham com:
- segmentos de contatos/leads;
- amostras de contatos de um segmento;
- contagem de contatos em um segmento;
- campanhas/broadcast;
- status de campanhas.

Use esses dados de forma responsável, principalmente em conversas com time interno (operadores, gerentes):
- nunca exponha para o cliente final que ele faz parte de um “segmento interno X”;
- nunca entregue listas inteiras de contatos em texto para o cliente;
- use as informações para ajustar sua fala (por exemplo, saber que o segmento é de clientes inativos, VIP, etc.).

Ao planejar campanhas (sempre em contexto interno, não com cliente):
- recomende testar em lotes menores antes de disparar para todo o segmento;
- evite qualquer postura que pareça spam ou bombardeio de mensagens;
- respeite intervalo de contato e bom senso de frequência.

7) Handover humano x bot
A operação possui um mecanismo de handover que define quando o atendimento é humano ou do bot.
- Se a orquestração indicar modo HUMANO, considere que o bot não deve tomar a frente da conversa. Em vez disso, seu papel é auxiliar o time interno (por exemplo, gerando respostas-sugestão, scripts, resumos), não falar diretamente com o cliente.
- Se a orquestração indicar modo BOT, siga normalmente as regras deste System Prompt, mas esteja sempre pronto para recomendar a entrada de um humano quando:
  - o caso for sensível (conflito, problema financeiro, reclamação séria);
  - faltar informação crítica que só alguém do time pode confirmar;
  - o usuário insistir em falar com um humano.

8) Questões sensíveis de saúde, segurança e emocional
Se o usuário mencionar:
- ideação suicida,
- vontade de se machucar,
- risco à própria vida ou à de terceiros,
- sofrimento psicológico intenso,

você deve:
- responder com empatia e sem julgamento;
- explicar que você é apenas um assistente virtual e não substitui ajuda profissional;
- recomendar que:
  - fale com alguém de confiança (amigos, família),
  - procure atendimento profissional de saúde,
  - busque serviços oficiais de apoio emocional apropriados ao país/região.

Nunca minimize, nunca incentive comportamentos de risco, e nunca dê instruções práticas para qualquer forma de autolesão ou violência.

9) Transparência em caso de dúvida ou limitação
Sempre que você não tiver certeza de algo importante (dados de sistema, regras de negócio específicas, políticas locais), faça o seguinte:
- assuma explicitamente que não tem aquele dado;
- explique o que você consegue fazer;
- ofereça encaminhamento para um atendente humano ou para um canal oficial.

Exemplo:
- “Não consigo ver o status exato dessa entrega aqui no chat, mas posso registrar seu relato e orientar o próximo passo, ou você pode falar direto com o suporte humano.”

10) Comportamento padrão (resumo)
Quando não houver regra mais específica, siga este padrão:
- Identifique quem está falando (cliente, lead ou interno).
- Entenda o objetivo principal da mensagem.
- Peça apenas os dados essenciais para ajudar (sem burocracia excessiva).
- Responda de forma objetiva, clara e honesta.
- Não invente dados de sistema ou promessas.
- Aponte sempre um próximo passo claro:
  - seguir para uma sugestão de produto,
  - organizar dados para cadastro ou follow-up,
  - encaminhar reclamação,
  - planejar ou acompanhar campanhas (em contexto interno),
  - envolver um humano quando for necessário.

Use todas essas regras como base fixa do seu comportamento.
PROMPT;
}

/**
 * Sanitiza conteúdo de snippets LLM para prevenir prompt injection.
 * Remove tentativas de escapar do contexto do sistema.
 * 
 * @param string $content Conteúdo do snippet
 * @return string Conteúdo sanitizado
 */
function sanitize_llm_snippet(string $content): string
{
    // Remove tentativas de escapar do contexto com marcadores de role (várias variações)
    $content = preg_replace('/\b(system|assistant|user|role)\s*[:=]|<\|(system|assistant|user)|```\s*(system|assistant|user)/i', '[FILTERED]:', $content);
    // Remove delimitadores que podem quebrar o contexto
    $content = str_replace(['```', '---', '~~~'], '', $content);
    return trim($content);
}

/**
 * Resolve o system prompt dinâmico com base nas configurações e no contexto da mensagem.
 * - Se llm_system_prompt estiver preenchido, usa como base.
 * - Caso contrário, usa whatsapp_bot_default_system_prompt().
 * - Opcionalmente concatena snippets da tabela llm_prompts por tipo (reposicao, reclamacao, campanha, etc).
 */
function whatsapp_bot_resolve_system_prompt(array $settings, string $mensagemCliente): string
{
    global $pdo;

    $base = $settings['llm_system_prompt'] ?? '';
    if (trim((string)$base) === '') {
        $base = whatsapp_bot_default_system_prompt();
    }

    $lower = mb_strtolower($mensagemCliente, 'UTF-8');
    $tipos = [];

    if (strpos($lower, 'reposi') !== false) {
        $tipos[] = 'reposicao';
    }
    if (strpos($lower, 'reclam') !== false || strpos($lower, 'problema') !== false) {
        $tipos[] = 'reclamacao';
    }
    if (strpos($lower, 'campanha') !== false || strpos($lower, 'promo') !== false) {
        $tipos[] = 'campanha';
    }

    if (!$tipos || !isset($pdo)) {
        return $base;
    }

    $snippets = [];
    try {
        $placeholders = implode(',', array_fill(0, count($tipos), '?'));
        $sql = "SELECT conteudo FROM llm_prompts WHERE tipo IN ({$placeholders}) AND ativo = 1 ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($tipos);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $snippets[] = sanitize_llm_snippet((string)$row['conteudo']);
        }
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('whatsapp_bot', 'erro_llm_prompts', ['erro' => $e->getMessage()]);
        }
        // Em caso de erro, mantém apenas o base
    }

    if ($snippets) {
        $base .= "\n\n--- CONTEXTO ESPECÍFICO ---\n" . implode("\n\n", $snippets);
    }

    return $base;
}




/**
 * Carrega configuração ativa do bot WhatsApp + IA.
 *
 * @return array|null
 */
function whatsapp_bot_load_settings(): ?array
{
    global $pdo;

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappLlmHelperRepository($pdo))->query_11320();
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cfg ?: null;
    } catch (Throwable $e) {
        log_app_event('whatsapp_bot', 'erro_carregar_settings', ['erro' => $e->getMessage()]);
        return null;
    }
}

function llm_input_hash(string $input): string
{
    return hash('sha256', $input);
}

/**
 * Registra chamada ao LLM na tabela llm_logs.
 */
function llm_log_call(
    string $provider,
    ?string $model,
    ?string $inputHash,
    ?int $promptTokens,
    ?int $completionTokens,
    ?int $totalTokens,
    ?int $httpStatus,
    ?int $latencyMs,
    ?string $requestPayload,
    ?string $responsePayload
): void {
    global $pdo;

    try {
        $sql = "INSERT INTO llm_logs 
            (provider, model, input_hash, prompt_tokens, completion_tokens, total_tokens, http_status, latency_ms, request_payload, response_payload)
            VALUES (:provider, :model, :input_hash, :prompt_tokens, :completion_tokens, :total_tokens, :http_status, :latency_ms, :request_payload, :response_payload)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':provider'          => $provider,
            ':model'             => $model,
            ':input_hash'        => $inputHash,
            ':prompt_tokens'     => $promptTokens,
            ':completion_tokens' => $completionTokens,
            ':total_tokens'      => $totalTokens,
            ':http_status'       => $httpStatus,
            ':latency_ms'        => $latencyMs,
            ':request_payload'   => $requestPayload,
            ':response_payload'  => $responsePayload,
        ]);
    } catch (Throwable $e) {
        log_app_event('llm', 'erro_log_llm', ['erro' => $e->getMessage()]);
    }
}

/**
 * Chama o LLM (OpenAI) para gerar uma resposta ao cliente.
 */

function whatsapp_bot_chamar_llm(string $mensagemCliente, array $historico, array $settings): array
{
    $settings = LlmRouter::normalize($settings);
    $provider = $settings['llm_provider'] ?? 'openai';
    $model    = $settings['llm_model'] ?? 'gpt-4o-mini';
    $temp     = isset($settings['llm_temperature']) ? (float)$settings['llm_temperature'] : 0.2;
    $maxTok   = isset($settings['llm_max_tokens']) ? (int)$settings['llm_max_tokens'] : 512;
    $system   = whatsapp_bot_resolve_system_prompt($settings, $mensagemCliente);

    if ($provider !== 'openai') {
        return ['ok' => false, 'resposta' => null];
    }

    $apiKey = (string) \RedeAlabama\Support\Config::get('openai.api_key', '');
if ($apiKey === '') {
    $apiKey = (string) \RedeAlabama\Support\Config::get('alabama_openai.api_key', '');
}

if ($apiKey === '') {
    log_app_event('whatsapp_bot', 'openai_api_key_ausente', []);
    return ['ok' => false, 'resposta' => null];
}

$messages = [];


    if ($system !== '') {
        $messages[] = ['role' => 'system', 'content' => $system];
    }

    foreach ($historico as $m) {
        if (!isset($m['role'], $m['content'])) {
            continue;
        }
        $messages[] = [
            'role'    => $m['role'],
            'content' => $m['content'],
        ];
    }

    $messages[] = [
        'role'    => 'user',
        'content' => $mensagemCliente,
    ];

    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temp,
        'max_tokens'  => $maxTok,
    ];

    $inputHash = llm_input_hash(json_encode($payload));

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $sec = __DIR__ . '/app/Support/Security.php';
    if (file_exists($sec)) {
        require_once $sec;
    }
    $authHeader = class_exists('Security') ? \Security::build_bearer_header($apiKey) : ('Authorization: Bearer ' . $apiKey);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            $authHeader,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $inicio  = microtime(true);
    $body    = curl_exec($ch);
    $latency = (int)round((microtime(true) - $inicio) * 1000);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr    = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlErr) {
        log_app_event('whatsapp_bot', 'erro_http_openai', ['erro' => $curlErr ?: 'body false']);
        llm_log_call('openai', $model, $inputHash, null, null, null, $httpStatus ?: null, $latency, json_encode($payload), null);
        return ['ok' => false, 'resposta' => null];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        llm_log_call('openai', $model, $inputHash, null, null, null, $httpStatus, $latency, json_encode($payload), $body);
        return ['ok' => false, 'resposta' => null];
    }

    $usage = $data['usage'] ?? null;
    $promptTokens     = $usage['prompt_tokens'] ?? null;
    $completionTokens = $usage['completion_tokens'] ?? null;
    $totalTokens      = $usage['total_tokens'] ?? null;

    $resposta = null;
    if (isset($data['choices'][0]['message']['content'])) {
        $resposta = trim((string)$data['choices'][0]['message']['content']);
    }

    llm_log_call('openai', $model, $inputHash, $promptTokens, $completionTokens, $totalTokens, $httpStatus, $latency, json_encode($payload), $body);

    if ($resposta === null || $resposta === '') {
        return ['ok' => false, 'resposta' => null];
    }

    return ['ok' => true, 'resposta' => $resposta];
}
