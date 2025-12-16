<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   whatsapp_handover.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class WhatsappHandoverRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_745(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_atendimentos SET status = \'fechado\', encerrado_em = NOW() WHERE conversa_id = :id AND status = \'aberto\'';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1013(): \PDOStatement
    {
        $sql = 'INSERT INTO whatsapp_atendimentos (conversa_id, usuario_id, modo, status) VALUES (:cid, :uid, \'humano\', \'aberto\')';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1444(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_atendimentos SET status = \'fechado\', encerrado_em = NOW() WHERE conversa_id = :id AND status = \'aberto\'';
        return $this->pdo->prepare($sql);
    }

}
