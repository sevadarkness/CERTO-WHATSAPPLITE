<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Remarketing;

/**
 * RemarketingLogsService
 *
 * Hoje ainda não há persistência real de logs de remarketing.
 * Mantém contrato retornando lista vazia.
 */
final class RemarketingLogsService
{
    /**
     * @param array $currentUser
     * @return array
     */
    public function listar(array $currentUser): array
    {
        return [
            'success' => true,
            'logs'    => [],
        ];
    }
}

