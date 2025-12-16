<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories;

use PDO;
use RedeAlabama\Domain\Flow\Flow;
use RedeAlabama\Support\Cache;

final class FlowRepository extends BaseRepository
{
    /**
     * Retorna um fluxo ativo pelo ID, ou null se não encontrado.
     */
    public function findActiveById(int $id): ?Flow
    {
        $row = Cache::remember('flows:active:' . $id, 30, function () use ($id) {
            $stmt = $this->pdo->prepare('SELECT id, nome, ativo FROM whatsapp_flows WHERE id = :id AND ativo = 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        });

        if ($row === null) {
            return null;
        }

        return new Flow(
            (int) $row['id'],
            (string) $row['nome'],
            (bool) $row['ativo']
        );
    }

    /**
     * Retorna todos os fluxos ativos.
     *
     * Utiliza cache para reduzir carga no banco.
     *
     * @return Flow[]
     */
    public function allActive(): array
    {
        $rows = Cache::remember('flows:all_active', 30, function () {
            $pdo = $this->pdo;
            $stmt = $pdo->query('SELECT id, nome, ativo FROM whatsapp_flows WHERE ativo = 1 ORDER BY nome ASC');
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        });

        $result = [];
        foreach ($rows as $row) {
            $result[] = new Flow(
                (int) $row['id'],
                (string) $row['nome'],
                (bool) $row['ativo']
            );
        }

        return $result;
    }
}

