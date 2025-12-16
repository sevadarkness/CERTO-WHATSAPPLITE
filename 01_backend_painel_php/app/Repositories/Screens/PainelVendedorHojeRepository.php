<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   painel_vendedor_hoje.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class PainelVendedorHojeRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_438(): \PDOStatement
    {
        $sql = '
        SELECT id, nome_cliente, telefone_cliente, origem, status, criado_em, ultimo_contato_em
          FROM leads
         WHERE vendedor_responsavel_id = :uid
           AND status IN (\'novo\',\'em_atendimento\')
         ORDER BY criado_em ASC
         LIMIT 20
    ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1023(): \PDOStatement
    {
        $sql = '
        SELECT *
          FROM sessoes_atendimento
         WHERE usuario_id = :uid
           AND fim IS NULL
         ORDER BY inicio ASC
         LIMIT 1
    ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1472(): \PDOStatement
    {
        $sql = '
        SELECT id, titulo, data_hora_inicio, data_hora_fim, canal, local, status
          FROM agenda_compromissos
         WHERE usuario_id = :uid
           AND DATE(data_hora_inicio) = CURDATE()
         ORDER BY data_hora_inicio ASC
    ';
        return $this->pdo->prepare($sql);
    }

}
