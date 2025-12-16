<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   jobs_runner.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class JobsRunnerRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_2292(): \PDOStatement
    {
        $sql = 'UPDATE jobs_agendados SET status = \'concluido\', tentativas = tentativas + 1, executado_em = NOW(), erro_ultimo = NULL WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_2505(): \PDOStatement
    {
        $sql = 'INSERT INTO jobs_logs (job_id, tipo, status, mensagem) VALUES (:job_id, :tipo, \'concluido\', :mensagem)';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3056(): \PDOStatement
    {
        $sql = 'UPDATE jobs_agendados SET status = :status, tentativas = :tentativas, erro_ultimo = :erro WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3388(): \PDOStatement
    {
        $sql = 'INSERT INTO jobs_logs (job_id, tipo, status, mensagem) VALUES (:job_id, :tipo, :status, :mensagem)';
        return $this->pdo->prepare($sql);
    }

}
