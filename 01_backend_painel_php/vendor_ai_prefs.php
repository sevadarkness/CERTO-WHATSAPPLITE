<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/llm_templates.php';
require_once __DIR__ . '/llm_vendor_prefs_model.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$user = current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}
$usuarioId = (int)$user['id'];

$erro   = null;
$sucesso = null;

// Lista de tones disponíveis (deve casar com endpoints que usam IA)
$whatsapp_tones = [
    'neutro'    => 'tom neutro e profissional',
    'amigavel'  => 'tom amigável e próximo, com vocabulário simples',
    'formal'    => 'tom mais formal, mas ainda humano',
    'engajador' => 'tom engajador, despertando desejo e urgência sem ser agressivo',
];

// Templates LLM para contexto de WhatsApp (whatsapp_ai)
$templates_raw = [
    'whatsapp_resposta_cliente' => 'Resposta para cliente em canal WhatsApp.',
    'whatsapp_cobranca'         => 'Cobrança amigável de inadimplente.',
    'whatsapp_reativacao'       => 'Reativação de cliente inativo.',
    'campanha_broadcast'        => 'Campanhas de broadcast com foco em engajamento.',
];

$templates = alabama_llm_templates_get($pdo, 'whatsapp_ai', $templates_raw);

$prefs = alabama_llm_get_vendor_prefs($pdo, $usuarioId);
$currentTone = $prefs['preferred_tone'] ?? '';
$currentTemplate = $prefs['preferred_template_slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $toneKey     = isset($_POST['tone']) ? trim((string)$_POST['tone']) : '';
    $templateKey = isset($_POST['template']) ? trim((string)$_POST['template']) : '';

    if ($toneKey !== '' && !array_key_exists($toneKey, $whatsapp_tones)) {
        $erro = 'Tom inválido selecionado.';
    } elseif ($templateKey !== '' && !array_key_exists($templateKey, $templates)) {
        $erro = 'Template inválido selecionado.';
    } else {
        $toneToSave     = $toneKey !== '' ? $toneKey : null;
        $templateToSave = $templateKey !== '' ? $templateKey : null;

        alabama_llm_save_vendor_prefs($pdo, $usuarioId, $toneToSave, $templateToSave);

        $sucesso = 'Preferências de IA atualizadas com sucesso.';
        $currentTone = $toneKey;
        $currentTemplate = $templateKey;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
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
    <title>Preferências de IA do Usuário</title>
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="h3 mb-3">Preferências de IA</h1>
    <p class="text-muted">
        Defina o tom padrão e o template preferido para respostas geradas pela IA em operações de WhatsApp e assistentes internos.
        Essas preferências serão usadas quando você não selecionar explicitamente um tom ou template.
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>

    <form method="post" class="mb-4">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label for="tone">Tom padrão da IA</label>
            <select name="tone" id="tone" class="form-control">
                <option value="">(Deixar sem preferencial)</option>
                <?php foreach ($whatsapp_tones as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($currentTone === $key) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">
                Esse tom será sugerido automaticamente em chamadas de IA quando você não especificar um.
            </small>
        </div>

        <div class="form-group">
            <label for="template">Template preferido</label>
            <select name="template" id="template" class="form-control">
                <option value="">(Deixar sem preferencial)</option>
                <?php foreach ($templates as $slug => $label): ?>
                    <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo ($currentTemplate === $slug) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">
                Usado em features que trabalham com templates LLM para WhatsApp.
            </small>
        </div>

        <button type="submit" class="btn btn-primary">Salvar preferências</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
