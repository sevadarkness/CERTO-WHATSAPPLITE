<?php
declare(strict_types=1);

namespace RedeAlabama\Domain\Flow;

final class Flow
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $active
    ) {
    }
}
