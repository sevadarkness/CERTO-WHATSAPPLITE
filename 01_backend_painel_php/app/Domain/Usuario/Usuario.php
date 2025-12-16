<?php
declare(strict_types=1);

namespace RedeAlabama\Domain\Usuario;

final class Usuario
{
    public function __construct(
        public int $id,
        public string $nome,
        public string $telefone,
        public string $nivelAcesso
    ) {
    }
}
