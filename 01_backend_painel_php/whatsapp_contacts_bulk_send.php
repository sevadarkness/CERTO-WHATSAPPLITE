<?php
declare(strict_types=1);

/**
 * Tela de criação de campanhas de envio em massa de WhatsApp.
 *
 * Permite:
 *  - Definir nome da campanha
 *  - Escrever mensagem de texto
 *  - Opcionalmente informar uma media_url (imagem/vídeo já hospedado)
 *  - Colar lista de destinatários (um número por linha)
 *  - Configurar agendamento (agora ou data/hora futura)
 *  - Definir intervalo mínimo/máximo entre envios (ms)
 *  - Ativar modo simulação (não envia de fato para a API, apenas registra como enviado)
 *
 * Requer:
 *  - Tabelas whatsapp_bulk_jobs e whatsapp_bulk_job_items (migrate.php).
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/whatsapp_contacts_utils.php';
require_once __DIR__ . '/whatsapp_official_api.php';

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

// Navegação padrão do painel
if (!$embed) {
    include __DIR__ . '/menu_navegacao.php';
}


$errors = [];
$success = null;

// Limite conservador por campanha para evitar abusos acidentais
$MAX_DESTINATARIOS = 1000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $nomeCampanha = trim($_POST['nome_campanha'] ?? '');
    $mensagem     = trim($_POST['mensagem'] ?? '');
    $mediaUrl     = trim($_POST['media_url'] ?? '');
    $lista        = trim($_POST['lista_destinatarios'] ?? '');
    $agendadoPara = trim($_POST['agendado_para'] ?? '');
    $minDelayMs   = isset($_POST['min_delay_ms']) ? (int)$_POST['min_delay_ms'] : 3000;
    $maxDelayMs   = isset($_POST['max_delay_ms']) ? (int)$_POST['max_delay_ms'] : 7000;
    $isSimulation = !empty($_POST['is_simulation']) ? 1 : 0;

    if ($nomeCampanha === '') {
        $errors[] = 'Nome da campanha é obrigatório.';
    }
    if ($mensagem === '') {
        $errors[] = 'Mensagem é obrigatória.';
    }
    if ($lista === '') {
        $errors[] = 'Informe ao menos um destinatário.';
    }

    // Quebra lista em linhas
    $linhas = preg_split('/\r\n|\r|\n/', $lista);
    $destinatarios = [];

    if (is_array($linhas)) {
        foreach ($linhas as $linha) {
            $raw = trim($linha);
            if ($raw === '') {
                continue;
            }

            // Normaliza para E.164 (+55...) usando helper oficial
            $toE164 = whatsapp_normalize_phone_e164($raw, '55');
            if ($toE164 === '') {
                $errors[] = 'Número inválido: ' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
                continue;
            }

            $destinatarios[] = [
                'raw'        => $raw,
                'normalized' => wa_normalize_phone($raw),
                'to_e164'    => $toE164,
            ];
        }
    }

    if (empty($destinatarios)) {
        $errors[] = 'Nenhum número válido encontrado na lista.';
    }

    if (count($destinatarios) > $MAX_DESTINATARIOS) {
        $errors[] = 'Quantidade de destinatários excede o limite permitido (' . $MAX_DESTINATARIOS . ').';
    }

    if ($minDelayMs <= 0) {
        $minDelayMs = 3000;
    }
    if ($maxDelayMs < $minDelayMs) {
        $maxDelayMs = $minDelayMs + 2000;
    }

    $agendadoDatetime = null;
    if ($agendadoPara !== '') {
        // Espera datetime-local HTML5: 2025-12-01T20:30
        $agendadoDatetime = str_replace('T', ' ', $agendadoPara) . ':00';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $usuarioId = $_SESSION['usuario_id'] ?? null;

            $stmt = $pdo->prepare("
                INSERT INTO whatsapp_bulk_jobs
                (user_id, nome_campanha, mensagem, media_url, status, total_destinatarios, agendado_para, min_delay_ms, max_delay_ms, is_simulation)
                VALUES (:user_id, :nome, :msg, :media, 'queued', :total, :agendado, :min_delay, :max_delay, :sim)
            ");
            $stmt->execute([
                ':user_id'   => $usuarioId,
                ':nome'      => $nomeCampanha,
                ':msg'       => $mensagem,
                ':media'     => $mediaUrl !== '' ? $mediaUrl : null,
                ':total'     => count($destinatarios),
                ':agendado'  => $agendadoDatetime,
                ':min_delay' => $minDelayMs,
                ':max_delay' => $maxDelayMs,
                ':sim'       => $isSimulation,
            ]);

            $jobId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO whatsapp_bulk_job_items
                (bulk_job_id, telefone_raw, telefone_normalizado, to_e164)
                VALUES (:job_id, :raw, :norm, :to_e164)
            ");

            foreach ($destinatarios as $dest) {
                $itemStmt->execute([
                    ':job_id'  => $jobId,
                    ':raw'     => $dest['raw'],
                    ':norm'    => $dest['normalized'],
                    ':to_e164' => $dest['to_e164'],
                ]);
            }

            $pdo->commit();

            log_app_event('whatsapp_bulk', 'create_job', [
                'job_id'     => $jobId,
                'total'      => count($destinatarios),
                'simulated'  => (bool)$isSimulation,
                'created_by' => $usuarioId,
            ]);

            $success = 'Campanha criada com sucesso. ID: ' . $jobId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao criar campanha: ' . $e->getMessage();

            log_app_event('whatsapp_bulk', 'create_job_error', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Envio em Massa de WhatsApp</title>
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-dark text-light">
<div class="container my-4">
    <h1 class="h3 mb-3">Envio em Massa de WhatsApp</h1>
    <p class="text-muted">
        Crie campanhas de envio em massa com controle de agendamento e intervalo entre mensagens.
        Use com responsabilidade e apenas para contatos com opt-in.
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo $e; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="card bg-secondary text-light p-3">
        <?php echo csrf_field(); ?>

        <div class="mb-3">
            <label class="form-label">Nome da campanha</label>
            <input type="text" name="nome_campanha" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mensagem</label>
            <textarea name="mensagem" rows="5" class="form-control" required></textarea>
            <small class="form-text text-muted">
                Mensagem enviada na íntegra para todos os destinatários. 
                Se quiser personalização avançada por contato, recomenda-se integrar via flows/IA.
            </small>
        </div>

        <div class="mb-3">
            <label class="form-label">URL da mídia (opcional)</label>
            <input type="text" name="media_url" class="form-control" placeholder="https://exemplo.com/imagem.jpg">
            <small class="form-text text-muted">
                Para uso com a API oficial do WhatsApp, a mídia precisa estar hospedada em URL acessível.
            </small>
        </div>

        <div class="mb-3">
            <label class="form-label">Lista de destinatários (um número por linha)</label>
            <textarea name="lista_destinatarios" rows="8" class="form-control" placeholder="+5592987654321"></textarea>
            <small class="form-text text-muted">
                Os números serão normalizados para E.164 com DDI padrão 55 quando aplicável.
                Limite atual: <?php echo (int)$MAX_DESTINATARIOS; ?> destinatários por campanha.
            </small>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Agendar para (opcional)</label>
                <input type="datetime-local" name="agendado_para" class="form-control">
                <small class="form-text text-muted">
                    Se não informado, entra na fila imediatamente.
                </small>
            </div>
            <div class="col-md-4">
                <label class="form-label">Delay mínimo entre envios (ms)</label>
                <input type="number" name="min_delay_ms" class="form-control" value="3000" min="500">
            </div>
            <div class="col-md-4">
                <label class="form-label">Delay máximo entre envios (ms)</label>
                <input type="number" name="max_delay_ms" class="form-control" value="7000" min="1000">
            </div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_simulation" name="is_simulation">
            <label class="form-check-label" for="is_simulation">
                Modo simulação (não enviar de fato para o WhatsApp, apenas registrar como enviado)
            </label>
        </div>

        <button type="submit" class="btn btn-primary">
            Criar campanha
        </button>

        <a href="whatsapp_contacts_bulk_status.php" class="btn btn-outline-light ms-2">
            Ver campanhas criadas
        </a>
    </form>
</div>
</body>
</html>
