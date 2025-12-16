<?php
declare(strict_types=1);

use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\LeadRepository;
use RedeAlabama\Repositories\WhatsappMessageRepository;
use RedeAlabama\Services\Sales\SalesSmartOfferService;
use RedeAlabama\Services\Sales\SalesObjectionAssistantService;
use RedeAlabama\Services\Sales\SalesRecoveryCampaignService;
use RedeAlabama\Services\Sales\SalesRecoveryRunnerService;




require_once __DIR__ . '/bootstrap_autoload.php';

require_once __DIR__ . '/rbac.php';
require_role(['Vendedor', 'Gerente', 'Administrador']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';


$usuario = current_user();
$tenantId = (int)($usuario['tenant_id'] ?? 1);

$llm            = LlmService::fromEnv();
$leadRepo       = new LeadRepository($pdo);
$msgRepo        = new WhatsappMessageRepository($pdo);
$offerService   = new SalesSmartOfferService($pdo, $llm, $leadRepo, $msgRepo);
$objectionSrv   = new SalesObjectionAssistantService($pdo, $llm, $msgRepo);
$campaignSrv    = new SalesRecoveryCampaignService($pdo, $llm, $leadRepo, $msgRepo);
$runnerSrv      = new SalesRecoveryRunnerService($pdo, $llm, $msgRepo);

$tab     = $_GET['tab'] ?? 'oferta';
$result1 = null;
$result2 = null;
$result3 = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'gerar_oferta') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $threadId  = (string)($_POST['thread_id'] ?? '');

        $result1 = $offerService->gerarOferta($tenantId, (int)$usuario['id'], $clienteId, $threadId);
        $tab = 'oferta';
    } elseif (isset($_POST['acao']) && $_POST['acao'] === 'resolver_objeção') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $threadId  = (string)($_POST['thread_id'] ?? '');
        $codigo    = (string)($_POST['codigo_objecao'] ?? '');

        $result2 = $objectionSrv->resolveObjection($tenantId, (int)$usuario['id'], $clienteId, $threadId, $codigo);
        $tab = 'objeções';
    } elseif (isset($_POST['acao']) && $_POST['acao'] === 'rodar_campanhas') {
        $max = (int)($_POST['max'] ?? 50);
        $result3 = $runnerSrv->processarLote($tenantId, $max);
        $tab = 'campanhas';
    }
}

// helpers mínimos
function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// carrega leads para dropdown simples
$leads = [];
try {
    $stmt = $pdo->query("SELECT id, nome, telefone FROM leads ORDER BY id DESC LIMIT 50");
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    log_app_event('ia_console', 'erro_carregar_leads', ['erro' => $e->getMessage()]);
}

// carrega objeções
$objeções = [];
try {
    $stmt = $pdo->prepare("SELECT codigo, titulo FROM sales_objection_library WHERE tenant_id = :tenant_id AND ativo = 1 ORDER BY titulo ASC");
    $stmt->execute([':tenant_id' => $tenantId]);
    $objeções = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    log_app_event('ia_console', 'erro_carregar_objeções', ['erro' => $e->getMessage()]);
}

// campanhas
$campanhas = [];
try {
    $stmt = $pdo->prepare("SELECT id, nome, tipo_segmento FROM sales_recovery_campaigns WHERE tenant_id = :tenant_id ORDER BY id DESC");
    $stmt->execute([':tenant_id' => $tenantId]);
    $campanhas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    log_app_event('ia_console', 'erro_carregar_campanhas', ['erro' => $e->getMessage()]);
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel IA de Vendas - Console</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body class="bg-dark text-light">
<div class="container mt-4">
    <h1 class="mb-3">Painel IA de Vendas - Console</h1>
    <p class="text-muted">Ferramentas diretas para testar IA Vendedora, Objeções e Campanhas de Recuperação.</p>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'oferta' ? 'active' : ''; ?>" href="?tab=oferta">IA Vendedora PRO</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'objeções' ? 'active' : ''; ?>" href="?tab=objeções">Copiloto - Objeções</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'campanhas' ? 'active' : ''; ?>" href="?tab=campanhas">Campanhas de Recuperação</a>
        </li>
    </ul>

    <?php if ($tab === 'oferta'): ?>
        <div class="card bg-secondary text-light mb-4">
            <div class="card-header">
                Gerar Oferta Inteligente (IA Vendedora PRO)
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="acao" value="gerar_oferta">

                    <div class="mb-3">
                        <label class="form-label">Lead / Cliente</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Selecione um lead...</option>
                            <?php foreach ($leads as $ld): ?>
                                <option value="<?php echo (int)$ld['id']; ?>">
                                    <?php echo h(($ld['nome'] ?? 'Sem nome') . ' - ' . ($ld['telefone'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Thread ID (whatsapp_messages.thread_id)</label>
                        <input type="text" name="thread_id" class="form-control" placeholder="ex.: lead-123-telefone" required>
                        <div class="form-text">Use o mesmo thread_id que o worker/fluxo usa para registrar as mensagens.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Gerar Oferta IA</button>
                </form>

                <?php if (is_array($result1)): ?>
                    <hr>
                    <?php if ($result1['ok'] ?? false): ?>
                        <h5>Oferta gerada:</h5>
                        <pre class="bg-dark text-success p-3 small"><?php echo h(json_encode($result1['proposta_json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        <h5>Mensagem sugerida:</h5>
                        <div class="bg-dark p-3 mb-3"><?php echo nl2br(h((string)$result1['mensagem_sugerida'])); ?></div>
                        <p class="text-muted small mb-0">log_id: <?php echo (int)($result1['log_id'] ?? 0); ?></p>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3">
                            Falha ao gerar oferta IA: <?php echo h((string)($result1['error'] ?? 'erro desconhecido')); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($tab === 'objeções'): ?>
        <div class="card bg-secondary text-light mb-4">
            <div class="card-header">
                Copiloto - Objeções
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="acao" value="resolver_objeção">

                    <div class="mb-3">
                        <label class="form-label">Lead / Cliente</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Selecione um lead...</option>
                            <?php foreach ($leads as $ld): ?>
                                <option value="<?php echo (int)$ld['id']; ?>">
                                    <?php echo h(($ld['nome'] ?? 'Sem nome') . ' - ' . ($ld['telefone'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Thread ID</label>
                        <input type="text" name="thread_id" class="form-control" placeholder="thread da conversa" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Objeção</label>
                        <select name="codigo_objecao" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($objeções as $ob): ?>
                                <option value="<?php echo h((string)$ob['codigo']); ?>">
                                    <?php echo h(($ob['titulo'] ?? '') . ' (' . ($ob['codigo'] ?? '') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Gerar Resposta IA</button>
                </form>

                <?php if (is_array($result2)): ?>
                    <hr>
                    <?php if ($result2['ok'] ?? false): ?>
                        <h5>Resposta sugerida:</h5>
                        <div class="bg-dark p-3 mb-3"><?php echo nl2br(h((string)$result2['resposta_sugerida'])); ?></div>
                        <p class="text-muted small mb-0">log_id: <?php echo (int)($result2['log_id'] ?? 0); ?></p>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3">
                            Falha ao tratar objeção: <?php echo h((string)($result2['error'] ?? 'erro desconhecido')); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($tab === 'campanhas'): ?>
        <div class="card bg-secondary text-light mb-4">
            <div class="card-header">
                Execução de Campanhas de Recuperação
            </div>
            <div class="card-body">
                <p class="mb-2">Campanhas cadastradas:</p>
                <?php if (!$campanhas): ?>
                    <p class="text-muted">Nenhuma campanha cadastrada ainda (use a tela de remarketing/campanhas para criar).</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($campanhas as $c): ?>
                            <li>#<?php echo (int)$c['id']; ?> - <?php echo h($c['nome']); ?> (<?php echo h($c['tipo_segmento']); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <hr>

                <form method="post">
                    <input type="hidden" name="acao" value="rodar_campanhas">

                    <div class="mb-3">
                        <label class="form-label">Máximo de registros por execução</label>
                        <input type="number" name="max" class="form-control" value="50" min="1" max="1000">
                    </div>

                    <button type="submit" class="btn btn-primary">Rodar Runner de Campanhas</button>
                </form>

                <?php if (is_array($result3)): ?>
                    <hr>
                    <?php if ($result3['ok'] ?? false): ?>
                        <div class="alert alert-success mt-3">
                            Runner executado. Total processado: <strong><?php echo (int)($result3['total_processado'] ?? 0); ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3">
                            Falha ao rodar runner: <?php echo h((string)($result3['error'] ?? 'erro desconhecido')); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <a href="painel_vendedor_hoje.php" class="btn btn-outline-light">Voltar para painel do vendedor</a>
</div>
</body>
</html>
