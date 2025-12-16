<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   sessoes_atendimento.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class SessoesAtendimentoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_517(): \PDOStatement
    {
        $sql = '
    SELECT *
    FROM sessoes_atendimento
    WHERE usuario_id = :uid AND fim IS NULL
    ORDER BY inicio DESC
    LIMIT 1
';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1431(): \PDOStatement
    {
        $sql = '
                    INSERT INTO sessoes_atendimento (usuario_id, lead_id, canal, tipo, inicio, observacoes)
                    VALUES (:usuario_id, :lead_id, :canal, :tipo, NOW(), :observacoes)
                ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_2655(): \PDOStatement
    {
        $sql = '
                    UPDATE sessoes_atendimento
                    SET fim = NOW(), duracao_segundos = TIMESTAMPDIFF(SECOND, inicio, NOW())
                    WHERE id = :id AND usuario_id = :uid AND fim IS NULL
                ';
        return $this->pdo->prepare($sql);
    }

    public function query_4114(): \PDOStatement
    {
        $sql = 'SELECT id, nome FROM usuarios WHERE nivel_acesso = \'Vendedor\' ORDER BY nome';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: sessoes_atendimento.php#query#4114');
        }
        return $stmt;
    }

}
