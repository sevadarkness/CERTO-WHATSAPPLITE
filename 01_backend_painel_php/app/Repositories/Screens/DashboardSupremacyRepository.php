<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   dashboard_supremacy.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class DashboardSupremacyRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_423(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM vendas WHERE DATE(data_venda) = :d';
        return $this->pdo->prepare($sql);
    }

    public function prepare_681(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM vendas WHERE DATE(data_venda) >= :dini';
        return $this->pdo->prepare($sql);
    }

    public function query_937(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd FROM leads WHERE status IN (\'novo\',\'em_atendimento\')';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: dashboard_supremacy.php#query#937');
        }
        return $stmt;
    }

    public function query_1131(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd FROM whatsapp_conversas WHERE status = \'ativa\'';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: dashboard_supremacy.php#query#1131');
        }
        return $stmt;
    }

    public function query_1349(): \PDOStatement
    {
        $sql = 'SELECT AVG(TIMESTAMPDIFF(SECOND, criado_em, primeiro_contato_em)) AS sla_seg
                           FROM leads
                          WHERE primeiro_contato_em IS NOT NULL
                            AND criado_em IS NOT NULL
                            AND primeiro_contato_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: dashboard_supremacy.php#query#1349');
        }
        return $stmt;
    }

    public function query_1965(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd
                           FROM leads
                          WHERE status IN (\'novo\',\'em_atendimento\')
                            AND (primeiro_contato_em IS NULL OR primeiro_contato_em = \'0000-00-00 00:00:00\')';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: dashboard_supremacy.php#query#1965');
        }
        return $stmt;
    }

    public function query_2418(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd FROM jobs_agendados WHERE status = \'pendente\'';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: dashboard_supremacy.php#query#2418');
        }
        return $stmt;
    }

    public function query_2653(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd FROM jobs_agendados WHERE status = \'erro\'';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: dashboard_supremacy.php#query#2653');
        }
        return $stmt;
    }

    public function query_2897(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) AS qtd FROM whatsapp_atendimentos WHERE status = \'aberto\'';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: dashboard_supremacy.php#query#2897');
        }
        return $stmt;
    }

}
