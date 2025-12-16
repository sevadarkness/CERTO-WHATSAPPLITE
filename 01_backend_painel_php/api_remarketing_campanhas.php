<?php
/**
 * api_remarketing_campanhas.php
 *
 * Endpoint para campanhas de remarketing com storage em MySQL.
 *
 * Observação:
 * - Este arquivo mantém compatibilidade com o wrapper /api/v2/remarketing_campanhas.php.
 * - Storage primário: MySQL (tabela remarketing_campanhas)
 * - Storage fallback: Arquivo JSON (graceful degradation quando BD não disponível)
 */

declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Tenta usar o PDO global configurado em db_config.php
$useDatabase = true;
try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $useDatabase = false;
    } else {
        // Verifica se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'remarketing_campanhas'");
        if ($stmt->rowCount() === 0) {
            $useDatabase = false;
        }
    }
} catch (Throwable $e) {
    $useDatabase = false;
}

// Storage em arquivo (fallback quando BD não disponível)
$baseDir = rtrim((string)(getenv('ALABAMA_STORAGE_DIR') ?: ''), '/');
if ($baseDir === '' || !is_dir($baseDir) || !is_writable($baseDir)) {
    $baseDir = rtrim(sys_get_temp_dir(), '/');
}
$storeFile = $baseDir . '/alabama_remarketing_campanhas.json';

/**
 * @return array<int,array<string,mixed>>
 */
function rm_load_campaigns(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    // Normaliza para lista
    if (isset($data['campanhas']) && is_array($data['campanhas'])) {
        $data = $data['campanhas'];
    }
    // Reindex
    return array_values(array_filter($data, fn($x) => is_array($x)));
}

/**
 * @param array<int,array<string,mixed>> $campanhas
 */
function rm_save_campaigns(string $file, array $campanhas): bool
{
    $payload = json_encode([
        'campanhas'   => array_values($campanhas),
        'updated_at'  => date('c'),
        'schema'      => 1,
        'storage'     => 'file',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if (!is_string($payload)) {
        return false;
    }

    return @file_put_contents($file, $payload, LOCK_EX) !== false;
}

/**
 * @return array<string,mixed>
 */
function rm_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Carrega campanhas do banco de dados.
 * @return array<int,array<string,mixed>>
 */
function rm_load_campaigns_db(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, nome, ativo, config_json, created_at, updated_at, created_by FROM remarketing_campanhas ORDER BY created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $campanhas = [];
    foreach ($rows as $row) {
        $config = [];
        if (!empty($row['config_json'])) {
            $decoded = json_decode($row['config_json'], true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        
        $campanhas[] = [
            'id'         => $row['id'],
            'nome'       => $row['nome'],
            'ativo'      => (bool)$row['ativo'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'created_by' => $row['created_by'],
        ] + $config;
    }
    
    return $campanhas;
}

/**
 * Salva ou atualiza uma campanha no banco de dados.
 */
function rm_save_campaign_db(PDO $pdo, array $campanha): bool
{
    $id = $campanha['id'] ?? null;
    $nome = $campanha['nome'] ?? 'Campanha';
    $ativo = isset($campanha['ativo']) ? (int)(bool)$campanha['ativo'] : 1;
    
    // Separa campos conhecidos do config
    $config = $campanha;
    unset($config['id'], $config['nome'], $config['ativo'], $config['created_at'], $config['updated_at'], $config['created_by']);
    $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
    
    $userId = $_SESSION['usuario_id'] ?? null;
    
    try {
        // Verifica se já existe
        $stmt = $pdo->prepare("SELECT id FROM remarketing_campanhas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Atualiza
            $stmt = $pdo->prepare("UPDATE remarketing_campanhas SET nome = :nome, ativo = :ativo, config_json = :config, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':id'     => $id,
                ':nome'   => $nome,
                ':ativo'  => $ativo,
                ':config' => $configJson,
            ]);
        } else {
            // Insere
            $stmt = $pdo->prepare("INSERT INTO remarketing_campanhas (id, nome, ativo, config_json, created_by) VALUES (:id, :nome, :ativo, :config, :created_by)");
            $stmt->execute([
                ':id'         => $id,
                ':nome'       => $nome,
                ':ativo'      => $ativo,
                ':config'     => $configJson,
                ':created_by' => $userId,
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao salvar campanha no DB: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove uma campanha do banco de dados.
 */
function rm_delete_campaign_db(PDO $pdo, string $id): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM remarketing_campanhas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao deletar campanha do DB: " . $e->getMessage());
        return false;
    }
}

// Carrega campanhas (DB ou arquivo)
if ($useDatabase) {
    try {
        $campanhas = rm_load_campaigns_db($pdo);
        $storageType = 'database';
        $storagePath = 'MySQL (remarketing_campanhas)';
    } catch (Throwable $e) {
        // Fallback para arquivo se DB falhar
        $campanhas = rm_load_campaigns($storeFile);
        $storageType = 'file';
        $storagePath = basename($storeFile);
    }
} else {
    $campanhas = rm_load_campaigns($storeFile);
    $storageType = 'file';
    $storagePath = basename($storeFile);
}

// GET: lista
if ($method === 'GET') {
    echo json_encode([
        'success'   => true,
        'campanhas' => $campanhas,
        'storage'   => [
            'type' => $storageType,
            'path' => $storagePath,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: cria/atualiza
if ($method === 'POST') {
    $body = rm_read_json_body();
    if (!is_array($body)) {
        $body = [];
    }

    $camp = $body['campanha'] ?? $body;
    if (!is_array($camp)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Payload inválido. Envie JSON com objeto "campanha".',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = $camp['id'] ?? null;
    if (!is_string($id) || trim($id) === '') {
        // id simples (não-UUID) – suficiente para uso interno.
        $id = 'rm_' . bin2hex(random_bytes(6));
        $camp['id'] = $id;
    }

    // Normalizações
    $camp['nome'] = isset($camp['nome']) && is_string($camp['nome']) ? trim($camp['nome']) : 'Campanha';
    $camp['ativo'] = isset($camp['ativo']) ? (bool)$camp['ativo'] : true;
    $camp['updated_at'] = date('c');
    if (!isset($camp['created_at'])) {
        $camp['created_at'] = date('c');
    }

    $success = false;
    $errorMsg = '';
    
    // Tenta salvar no database primeiro
    if ($useDatabase) {
        try {
            $success = rm_save_campaign_db($pdo, $camp);
            if ($success) {
                // Recarrega todas as campanhas do DB
                $campanhas = rm_load_campaigns_db($pdo);
            } else {
                $errorMsg = 'Falha ao persistir campanha no banco de dados.';
            }
        } catch (Throwable $e) {
            $errorMsg = 'Erro ao acessar banco de dados: ' . $e->getMessage();
        }
    }
    
    // Fallback para arquivo se DB falhar ou não disponível
    if (!$success) {
        $found = false;
        foreach ($campanhas as $i => $existing) {
            if (is_array($existing) && isset($existing['id']) && $existing['id'] === $id) {
                $campanhas[$i] = array_merge($existing, $camp);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $campanhas[] = $camp;
        }

        if (rm_save_campaigns($storeFile, $campanhas)) {
            $success = true;
        } else {
            $errorMsg = 'Falha ao persistir campanhas no servidor (storage file).';
        }
    }

    if (!$success) {
        echo json_encode([
            'success' => false,
            'error'   => $errorMsg,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'campanha'  => $camp,
        'campanhas' => $campanhas,
        'message'   => 'Campanha salva com sucesso.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// DELETE: remove (?id=...)
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!is_string($id) || trim($id) === '') {
        echo json_encode([
            'success' => false,
            'error'   => 'Informe o parâmetro id.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $success = false;
    $errorMsg = '';
    
    // Tenta deletar do database primeiro
    if ($useDatabase) {
        try {
            $success = rm_delete_campaign_db($pdo, $id);
            if ($success) {
                // Recarrega todas as campanhas do DB
                $campanhas = rm_load_campaigns_db($pdo);
            } else {
                $errorMsg = 'Campanha não encontrada no banco de dados.';
            }
        } catch (Throwable $e) {
            $errorMsg = 'Erro ao acessar banco de dados: ' . $e->getMessage();
        }
    }
    
    // Fallback para arquivo se DB falhar ou não disponível
    if (!$success) {
        $before = count($campanhas);
        $campanhas = array_values(array_filter($campanhas, function ($c) use ($id) {
            return !(is_array($c) && isset($c['id']) && $c['id'] === $id);
        }));

        if ($before === count($campanhas)) {
            echo json_encode([
                'success' => false,
                'error'   => 'Campanha não encontrada.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (rm_save_campaigns($storeFile, $campanhas)) {
            $success = true;
        } else {
            $errorMsg = 'Falha ao persistir campanhas no servidor (storage file).';
        }
    }

    if (!$success) {
        echo json_encode([
            'success' => false,
            'error'   => $errorMsg,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'campanhas' => $campanhas,
        'message'   => 'Campanha removida.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'error'   => 'Método não suportado.',
], JSON_UNESCAPED_UNICODE);
