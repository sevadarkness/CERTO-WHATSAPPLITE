<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/whatsapp_llm_helper.php';
require_once __DIR__ . '/llm_templates.php';
require_once __DIR__ . '/llm_vendor_prefs_model.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

function alabama_bot_get_settings(PDO $pdo): ?array
{
    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\AdminAssistantRepository($pdo))->query_500();
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('admin_assistant', 'erro_carregar_settings', ['erro' => $e->getMessage()]);
        }
        return null;
    }
}


$whatsapp_tones = [
    'neutro'    => 'tom neutro e profissional',
    'amigavel'  => 'tom amigável e próximo, com vocabulário simples',
    'formal'    => 'tom mais formal, mas ainda humano',
    'engajador' => 'tom engajador, despertando desejo e urgência sem ser agressivo',
];

$whatsapp_templates = [
    'generico_operacao'         => 'Responder dúvidas internas sobre operação, processos, estoque ou funil de vendas da Rede Alabama.',
    'whatsapp_resposta_cliente' => 'Gerar uma resposta pronta para enviar no WhatsApp para um cliente, mantendo o estilo da marca Rede Alabama.',
    'whatsapp_cobranca'         => 'Cobrar um cliente que está inadimplente com firmeza, mas de forma respeitosa, preservando o relacionamento.',
    'whatsapp_reativacao'       => 'Reativar um cliente que não compra há algum tempo, usando gatilhos de benefício, novidade e leve escassez.',
    'campanha_broadcast'        => 'Escrever rascunhos de campanhas de broadcast no WhatsApp com foco em engajamento e conversão.',
];

// V16 Ultra: carrega templates dinâmicos do banco (se disponível)
$whatsapp_templates = alabama_llm_templates_get($pdo, 'admin_assistant', $whatsapp_templates);

$pergunta = '';
$resposta = null;
$erro     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pergunta = trim((string)($_POST['pergunta'] ?? ''));
    $toneKey      = isset($_POST['tone']) ? (string)$_POST['tone'] : '';
    $templateKey  = isset($_POST['template']) ? (string)$_POST['template'] : '';

    if ($pergunta === '') {
        $erro = 'Digite uma pergunta ou comando para o assistente.';
    } else {
        $settings = alabama_bot_get_settings($pdo);
        if (!$settings) {
            $erro = 'Configuração do bot WhatsApp/LLM não encontrada. Configure em whatsapp_bot_config.php.';
        } else {
            $toneLabel    = isset($whatsapp_tones[$toneKey]) ? $whatsapp_tones[$toneKey] : null;
            $templateDesc = isset($whatsapp_templates[$templateKey]) ? $whatsapp_templates[$templateKey] : null;

            $contextParts = [];

            if ($templateDesc) {
                $contextParts[] = 'Objetivo da tarefa: ' . $templateDesc;
            }
            if ($toneLabel) {
                $contextParts[] = 'Tom desejado da resposta: ' . $toneLabel . '.';
            }

            if ($contextParts) {
                $promptComContexto = implode("
", $contextParts) . "

Pedido principal:
" . $pergunta;
            } else {
                $promptComContexto = $pergunta;
            }

            $historico = [];
            $resultado = whatsapp_bot_chamar_llm($promptComContexto, $historico, $settings);
            if (!is_array($resultado) || empty($resultado['ok'])) {
                $erro = 'Falha ao chamar LLM. Verifique logs e configuração.';
            } else {
                $resposta = $resultado['resposta'] ?? '';
                if (function_exists('log_app_event')) {
                    $promptLog   = strlen($promptComContexto) > 1000 ? substr($promptComContexto, 0, 1000) . '...' : $promptComContexto;
                    $respostaLog = strlen((string)$resposta) > 1000 ? substr((string)$resposta, 0, 1000) . '...' : (string)$resposta;
                    log_app_event('admin_assistant', 'llm_resposta', [
                        'tone'      => $toneKey,
                        'template'  => $templateKey,
                        'prompt'    => $promptLog,
                        'resposta'  => $respostaLog,
                    ]);
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <meta charset="UTF-8">
    <title>Assistente Interno - Rede Alabama</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="h3 mb-3">Assistente Interno (IA)</h1>
    <p class="text-muted">
        Use o mesmo LLM do Bot WhatsApp como assistente interno para dúvidas de operação, campanhas, funil, scripts etc.
        Ele respeita o System Prompt da Rede Alabama e os prompts dinâmicos (llm_prompts).
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post" class="mb-4">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="template" class="form-label">Modo / template</label>
                <select name="template" id="template" class="form-select">
                    <option value="">(Livre - sem template)</option>
                    <?php foreach ($whatsapp_templates as $key => $desc): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (isset($_POST['template']) && $_POST['template'] === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($key . ' – ' . $desc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Inspirado em fluxos de templates das extensões de WhatsApp/CRM (WAPlus, MailMagic).</div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="tone" class="form-label">Tom da resposta</label>
                <select name="tone" id="tone" class="form-select">
                    <option value="">(Padrão do bot)</option>
                    <?php foreach ($whatsapp_tones as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (isset($_POST['tone']) && $_POST['tone'] === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Orientação adicional para o LLM ao gerar o texto.</div>
            </div>
        </div>
        <div class="mb-3">
            <label for="pergunta" class="form-label">Pergunta / comando</label>
            <textarea name="pergunta" id="pergunta" class="form-control" rows="5" required><?php echo htmlspecialchars($pergunta); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Perguntar ao Assistente</button>
    </form>

    <?php if ($resposta !== null): ?>
        <div class="card bg-secondary text-light">
            <div class="card-header">
                Resposta da IA
            </div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($resposta); ?></pre>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
