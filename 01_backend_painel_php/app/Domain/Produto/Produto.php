<?php
declare(strict_types=1);

namespace RedeAlabama\Domain\Produto;

final class Produto
{
    public function __construct(
        public int $id,
        public string $nome,
        public ?string $descricao,
        public float $preco
    ) {
    }
}
