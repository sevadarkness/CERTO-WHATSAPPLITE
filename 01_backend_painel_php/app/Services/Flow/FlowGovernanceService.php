<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Flow;

use PDO;
use RedeAlabama\Repositories\FlowRepository;

/**
 * Governança de fluxos com snapshots e rollback.
 *
 * Snapshots são gravados em arquivos JSON em ALABAMA_LOG_DIR/flows_versions.
 */
final class FlowGovernanceService
{
    public function __construct(
        private PDO $pdo,
        private FlowRepository $flows
    ) {
    }

    private function versionsDir(): string
    {
        $base = defined('ALABAMA_LOG_DIR') ? ALABAMA_LOG_DIR : (__DIR__ . '/../../../logs');
        $dir  = $base . '/flows_versions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * Cria um snapshot completo de whatsapp_flows e automation_rules.
     *
     * @return int ID lógico da versão (timestamp)
     */
    public function snapshot(string $reason, ?int $usuarioId = null): int
    {
        $versionId = time();

        $flows = $this->pdo->query('SELECT * FROM whatsapp_flows')->fetchAll(PDO::FETCH_ASSOC);
        $rules = $this->pdo->query('SELECT * FROM automation_rules')->fetchAll(PDO::FETCH_ASSOC);

        $payload = [
            'version_id' => $versionId,
            'created_at' => date(DATE_ATOM),
            'usuario_id' => $usuarioId,
            'reason'     => $reason,
            'flows'      => $flows,
            'rules'      => $rules,
        ];

        $file = $this->versionsDir() . '/flows_' . $versionId . '.json';
        @file_put_contents($file, json_encode($payload));

        if (function_exists('log_audit_event')) {
            log_audit_event('flow_snapshot_create', 'flow_snapshot', $versionId, [
                'reason'     => $reason,
                'usuario_id' => $usuarioId,
            ]);
        }

        return $versionId;
    }

    /**
     * Lista snapshots existentes (metadados).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listVersions(): array
    {
        $dir = $this->versionsDir();
        $files = glob($dir . '/flows_*.json') ?: [];
        $versions = [];

        foreach ($files as $file) {
            $json = @file_get_contents($file);
            if (!is_string($json) || $json === '') {
                continue;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                continue;
            }
            $versions[] = [
                'version_id' => $decoded['version_id'] ?? null,
                'created_at' => $decoded['created_at'] ?? null,
                'usuario_id' => $decoded['usuario_id'] ?? null,
                'reason'     => $decoded['reason'] ?? null,
            ];
        }

        usort($versions, static function ($a, $b) {
            return ($b['version_id'] ?? 0) <=> ($a['version_id'] ?? 0);
        });

        return $versions;
    }

    /**
     * Realiza rollback completo para uma versão.
     */
    public function rollback(int $versionId): bool
    {
        $file = $this->versionsDir() . '/flows_' . $versionId . '.json';
        if (!is_file($file)) {
            return false;
        }
        $json = @file_get_contents($file);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return false;
        }

        $flows = $decoded['flows'] ?? [];
        $rules = $decoded['rules'] ?? [];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM whatsapp_flows');
            $this->pdo->exec('DELETE FROM automation_rules');

            if (!empty($flows)) {
                $cols = array_keys($flows[0]);
                $colsSql = implode(',', $cols);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $this->pdo->prepare('INSERT INTO whatsapp_flows (' . $colsSql . ') VALUES (' . $placeholders . ')');
                foreach ($flows as $row) {
                    $stmt->execute(array_values($row));
                }
            }

            if (!empty($rules)) {
                $cols = array_keys($rules[0]);
                $colsSql = implode(',', $cols);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $this->pdo->prepare('INSERT INTO automation_rules (' . $colsSql . ') VALUES (' . $placeholders . ')');
                foreach ($rules as $row) {
                    $stmt->execute(array_values($row));
                }
            }

            $this->pdo->commit();

            if (function_exists('log_audit_event')) {
                log_audit_event('flow_snapshot_rollback', 'flow_snapshot', $versionId, []);
            }

            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            if (function_exists('log_app_event')) {
                log_app_event('flow_governance', 'rollback_failed', [
                    'version_id' => $versionId,
                    'error'      => $e->getMessage(),
                ]);
            }
            return false;
        }
    }
}

