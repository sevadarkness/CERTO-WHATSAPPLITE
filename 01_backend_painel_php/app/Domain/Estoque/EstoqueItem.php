<?php
declare(strict_types=1);

namespace RedeAlabama\Domain\Estoque;

final class EstoqueItem
{
    public function __construct(
        public int $produtoId,
        public ?int $saborId,
        public int $vendedorId,
        public int $quantidade
    ) {
    }
}
