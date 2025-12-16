<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   whatsapp_flow_worker.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class WhatsappFlowWorkerRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_2039(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_flow_queue
                                  SET status = \'erro\',
                                      last_error = :err
                                  WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3226(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_flow_queue
                                  SET status = \'erro\',
                                      last_error = :err
                                  WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4077(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_flow_queue
                                  SET status = \'enviado\',
                                      sent_at = NOW(),
                                      last_error = NULL
                                  WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

}
