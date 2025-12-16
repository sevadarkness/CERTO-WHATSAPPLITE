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

function whatsapp_bot_get_settings(PDO $pdo): ?array
{
    $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappBotConfigRepository($pdo))->query_259();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$erro = null;
$ok   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome            = trim($_POST['nome'] ?? 'Bot WhatsApp IA');
    $ativo           = isset($_POST['ativo']) ? 1 : 0;
    $phoneNumberId   = trim($_POST['phone_number_id'] ?? '');
    $verifyToken     = trim($_POST['verify_token'] ?? '');
    $metaAccessToken = trim($_POST['meta_access_token'] ?? '');
    $llmProvider     = trim($_POST['llm_provider'] ?? 'openai');
    $llmModel        = trim($_POST['llm_model'] ?? 'gpt-4.1-mini');
    $llmTemp         = isset($_POST['llm_temperature']) ? (float)$_POST['llm_temperature'] : 0.2;
    $llmMaxTokens    = isset($_POST['llm_max_tokens']) ? (int)$_POST['llm_max_tokens'] : 512;
    $llmSystemPrompt = trim($_POST['llm_system_prompt'] ?? '');

    try {
        $existing = whatsapp_bot_get_settings($pdo);

        if ($existing) {
            $sql = "UPDATE whatsapp_bot_settings
                    SET nome = :nome,
                        ativo = :ativo,
                        phone_number_id = :phone_number_id,
                        verify_token = :verify_token,
                        meta_access_token = :meta_access_token,
                        llm_provider = :llm_provider,
                        llm_model = :llm_model,
                        llm_temperature = :llm_temperature,
                        llm_max_tokens = :llm_max_tokens,
                        llm_system_prompt = :llm_system_prompt,
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome'            => $nome,
                ':ativo'           => $ativo,
                ':phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                ':verify_token'    => $verifyToken !== '' ? $verifyToken : null,
                ':meta_access_token' => $metaAccessToken !== '' ? $metaAccessToken : null,
                ':llm_provider'    => $llmProvider !== '' ? $llmProvider : 'openai',
                ':llm_model'       => $llmModel !== '' ? $llmModel : null,
                ':llm_temperature' => $llmTemp,
                ':llm_max_tokens'  => $llmMaxTokens,
                ':llm_system_prompt' => $llmSystemPrompt !== '' ? $llmSystemPrompt : null,
                ':id'              => (int)$existing['id'],
            ]);
            $ok = 'Configurações atualizadas com sucesso.';
        } else {
            $sql = "INSERT INTO whatsapp_bot_settings
                (nome, ativo, phone_number_id, verify_token, meta_access_token, llm_provider, llm_model, llm_temperature, llm_max_tokens, llm_system_prompt)
                VALUES (:nome, :ativo, :phone_number_id, :verify_token, :meta_access_token, :llm_provider, :llm_model, :llm_temperature, :llm_max_tokens, :llm_system_prompt)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome'            => $nome,
                ':ativo'           => $ativo,
                ':phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                ':verify_token'    => $verifyToken !== '' ? $verifyToken : null,
                ':meta_access_token' => $metaAccessToken !== '' ? $metaAccessToken : null,
                ':llm_provider'    => $llmProvider !== '' ? $llmProvider : 'openai',
                ':llm_model'       => $llmModel !== '' ? $llmModel : null,
                ':llm_temperature' => $llmTemp,
                ':llm_max_tokens'  => $llmMaxTokens,
                ':llm_system_prompt' => $llmSystemPrompt !== '' ? $llmSystemPrompt : null,
            ]);
            $ok = 'Configurações criadas com sucesso.';
        }

        log_app_event('whatsapp_bot', 'config_atualizada', [
            'usuario_id' => $_SESSION['usuario_id'] ?? null,
        ]);
    } catch (Throwable $e) {
        $erro = 'Erro ao salvar configurações: ' . htmlspecialchars($e->getMessage());
        log_app_event('whatsapp_bot', 'erro_salvar_config', ['erro' => $e->getMessage()]);
    }
}

$settings = whatsapp_bot_get_settings($pdo);

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';
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
    <title>Bot WhatsApp IA - Configuração</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="al-body">
<div class="container mt-4">
    <h1 class="mb-3"><i class="fab fa-whatsapp"></i> Bot WhatsApp IA - Configuração</h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="alert alert-success"><?php echo $ok; ?></div>
    <?php endif; ?>

    <form method="post" class="card bg-secondary text-light mb-4">
        <div class="card-body">
            <h5 class="card-title">Geral</h5>
            <div class="mb-3 form-check form-switch">
                <input class="form-check-input" type="checkbox" id="ativo" name="ativo" <?php echo (!empty($settings['ativo'])) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ativo">Bot Ativo</label>
            </div>
            <div class="mb-3">
                <label for="nome" class="form-label">Nome do Bot</label>
                <input type="text" class="form-control" id="nome" name="nome"
                       value="<?php echo htmlspecialchars($settings['nome'] ?? 'Bot WhatsApp IA'); ?>">
            </div>
        </div>

        <div class="card-body border-top border-dark">
            <h5 class="card-title">WhatsApp Cloud API</h5>
            <div class="mb-3">
                <label for="phone_number_id" class="form-label">Phone Number ID</label>
                <input type="text" class="form-control" id="phone_number_id" name="phone_number_id"
                       value="<?php echo htmlspecialchars($settings['phone_number_id'] ?? ''); ?>"
                       placeholder="Ex.: 123456789012345">
            </div>
            <div class="mb-3">
                <label for="verify_token" class="form-label">Verify Token (Webhook)</label>
                <input type="text" class="form-control" id="verify_token" name="verify_token"
                       value="<?php echo htmlspecialchars($settings['verify_token'] ?? ''); ?>"
                       placeholder="Token secreto para validação do webhook">
            </div>
            <div class="mb-3">
                <label for="meta_access_token" class="form-label">Access Token (WhatsApp Cloud API)</label>
                <input type="text" class="form-control" id="meta_access_token" name="meta_access_token"
                       value="<?php echo htmlspecialchars($settings['meta_access_token'] ?? ''); ?>"
                       placeholder="Token de acesso à API do WhatsApp.">
            </div>
        </div>

        <div class="card-body border-top border-dark">
            <h5 class="card-title">Modelo de IA (LLM)</h5>
            <div class="mb-3">
                <label for="llm_provider" class="form-label">Provedor</label>
                <select class="form-select" id="llm_provider" name="llm_provider">
                    <?php
                    $provAtual = $settings['llm_provider'] ?? 'openai';
                    $providers = ['openai' => 'OpenAI / ChatGPT'];
                    foreach ($providers as $value => $label):
                        $sel = ($provAtual === $value) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($value) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                    endforeach;
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="llm_model" class="form-label">Modelo</label>
                <input type="text" class="form-control" id="llm_model" name="llm_model"
                       value="<?php echo htmlspecialchars($settings['llm_model'] ?? 'gpt-4.1-mini'); ?>"
                       placeholder="Ex.: gpt-4.1-mini">
            </div>
            <div class="mb-3">
                <label for="llm_temperature" class="form-label">Temperature</label>
                <input type="number" step="0.01" min="0" max="1" class="form-control" id="llm_temperature" name="llm_temperature"
                       value="<?php echo htmlspecialchars((string)($settings['llm_temperature'] ?? '0.20')); ?>">
            </div>
            <div class="mb-3">
                <label for="llm_max_tokens" class="form-label">Max Tokens</label>
                <input type="number" class="form-control" id="llm_max_tokens" name="llm_max_tokens"
                       value="<?php echo htmlspecialchars((string)($settings['llm_max_tokens'] ?? '512')); ?>">
            </div>
            <div class="mb-3">
                <label for="llm_system_prompt" class="form-label">System Prompt (Contexto do Negócio)</label>
                <textarea class="form-control" id="llm_system_prompt" name="llm_system_prompt" rows="6"><?php
                    echo htmlspecialchars($settings['llm_system_prompt'] ?? '');
                ?></textarea>
            </div>
        </div>

        <div class="card-body border-top border-dark">
            <button type="submit" class="btn btn-success">Salvar configurações</button>
        </div>
    </form>

    <div class="card bg-secondary text-light">
        <div class="card-header">
            Como integrar com o WhatsApp Cloud API
        </div>
        <div class="card-body">
            <ol>
                <li>Configure um app no Meta Developers com produto WhatsApp.</li>
                <li>Aponte o Webhook para: <code>https://SEU-DOMINIO/adm.redealabama/whatsapp_webhook.php</code></li>
                <li>Use o mesmo Verify Token aqui e no Meta.</li>
                <li>Copie o Phone Number ID e o Access Token e configure acima.</li>
                <li>Defina a variável de ambiente <code>OPENAI_API_KEY</code> no servidor.</li>
            </ol>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
