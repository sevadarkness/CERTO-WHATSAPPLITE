<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Remarketing;

use RuntimeException;

/**
 * RemarketingDisparosService
 *
 * Implementa a lógica de disparos de remarketing.
 * Neste momento, a API apenas valida o payload e responde sucesso,
 * sem persistir dados, de acordo com o comportamento original.
 */
final class RemarketingDisparosService
{
    /**
     * @param array $payload
     * @param array $currentUser
     * @return array
     */
    public function disparar(array $payload, array $currentUser): array
    {
        // Aqui poderíamos validar estrutura mínima (ex.: lista de clientes, mensagem, canal, etc).
        if (!is_array($payload) || $payload === []) {
            return [
                'success' => false,
                'error'   => 'Payload de disparo vazio ou inválido.',
            ];
        }

        // Mantém contrato simples: responde sucesso e devolve eco do payload.
        return [
            'success' => true,
            'payload' => $payload,
        ];
    }
}

