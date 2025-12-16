<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   flows_engine_runner.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class FlowsEngineRunnerRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_5369(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_flow_executions 
                                              SET status = \'finalizado\', updated_at = NOW()
                                              WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_7646(): \PDOStatement
    {
        $sql = 'INSERT INTO whatsapp_flow_queue
                    (created_at, flow_id, flow_step_id, conversa_id, telefone, mensagem, status)
                    VALUES (NOW(), :flow_id, :step_id, :conversa_id, :telefone, :mensagem, \'pendente\')';
        return $this->pdo->prepare($sql);
    }

    public function prepare_8318(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_flow_executions
                                              SET current_step = :current_step,
                                                  status       = \'em_andamento\',
                                                  next_run_at  = DATE_ADD(NOW(), INTERVAL :delay MINUTE),
                                                  updated_at   = NOW()
                                              WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_9083(): \PDOStatement
    {
        $sql = 'INSERT INTO whatsapp_flow_executions
                        (created_at, updated_at, flow_id, conversa_id, current_step, status, next_run_at)
                        VALUES (NOW(), NOW(), :flow_id, :conversa_id, :current_step, \'em_andamento\',
                                DATE_ADD(NOW(), INTERVAL :delay MINUTE))';
        return $this->pdo->prepare($sql);
    }

}
