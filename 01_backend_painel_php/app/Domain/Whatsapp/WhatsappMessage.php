<?php
declare(strict_types=1);

namespace RedeAlabama\Domain\Whatsapp;

final class WhatsappMessage
{
    public function __construct(
        public int $id,
        public string $threadId,
        public string $contatoTelefone,
        public string $direction,
        public string $conteudo,
        public bool $enviadoPorIa
    ) {
    }
}
