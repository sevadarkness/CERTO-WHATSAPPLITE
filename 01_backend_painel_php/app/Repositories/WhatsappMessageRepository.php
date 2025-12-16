<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories;

use PDO;
use DateTimeImmutable;

final class WhatsappMessageRepository extends BaseRepository
{
    public function storeIncoming(
        string $threadId,
        string $contatoNome,
        string $contatoTelefone,
        string $conteudo,
        ?int $vendedorId = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_messages
                (thread_id, contato_nome, contato_telefone, direction, conteudo, canal, vendedor_id, enviado_por_ia, created_at)
            VALUES
                (:thread_id, :contato_nome, :contato_telefone, 'in', :conteudo, 'whatsapp', :vendedor_id, 0, :created_at)
        ");

        $stmt->execute([
            ':thread_id'        => $threadId,
            ':contato_nome'     => $contatoNome,
            ':contato_telefone' => $contatoTelefone,
            ':conteudo'         => $conteudo,
            ':vendedor_id'      => $vendedorId,
            ':created_at'       => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function storeOutgoingIaSuggestion(
        string $threadId,
        string $contatoNome,
        string $contatoTelefone,
        string $conteudo,
        ?int $vendedorId = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_messages
                (thread_id, contato_nome, contato_telefone, direction, conteudo, canal, vendedor_id, enviado_por_ia, created_at)
            VALUES
                (:thread_id, :contato_nome, :contato_telefone, 'out', :conteudo, 'whatsapp', :vendedor_id, 1, :created_at)
        ");

        $stmt->execute([
            ':thread_id'        => $threadId,
            ':contato_nome'     => $contatoNome,
            ':contato_telefone' => $contatoTelefone,
            ':conteudo'         => $conteudo,
            ':vendedor_id'      => $vendedorId,
            ':created_at'       => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
