<?php
declare(strict_types=1);

namespace RedeAlabama\Domain\Automation;

final class AutomationRule
{
    public function __construct(
        public int $id,
        public string $nome,
        public bool $ativo,
        public string $gatilhoTipo,
        public string $gatilhoConfigJson,
        public string $acaoTipo,
        public string $acaoConfigJson
    ) {
    }
}
