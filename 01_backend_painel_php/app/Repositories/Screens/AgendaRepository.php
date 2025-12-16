<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   agenda.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class AgendaRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1748(): \PDOStatement
    {
        $sql = '
                    INSERT INTO agenda_compromissos
                    (usuario_id, lead_id, titulo, descricao, data_hora_inicio, data_hora_fim, origem, canal, local, status, url_externa)
                    VALUES
                    (:usuario_id, :lead_id, :titulo, :descricao, :inicio, :fim, :origem, :canal, :local, \'agendado\', :url_externa)
                ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3681(): \PDOStatement
    {
        $sql = 'UPDATE agenda_compromissos SET status = :status WHERE id = :id AND usuario_id = :uid';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3953(): \PDOStatement
    {
        $sql = 'UPDATE agenda_compromissos SET status = :status WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function query_5447(): \PDOStatement
    {
        $sql = 'SELECT id, nome FROM usuarios WHERE nivel_acesso = \'Vendedor\' ORDER BY nome';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: agenda.php#query#5447');
        }
        return $stmt;
    }

}
