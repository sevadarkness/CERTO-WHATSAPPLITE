<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Lead;

use RedeAlabama\Repositories\LeadRepository;
use InvalidArgumentException;

/**
 * Serviço de regras de negócio para Leads.
 *
 * Responsável por:
 *  - Validar payload de criação/consulta
 *  - Delegar persistência ao LeadRepository
 *  - Encapsular regras de status, origem, etc.
 */
final class LeadService
{
    private LeadRepository $repository;

    public function __construct(LeadRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Cria um novo lead a partir do payload recebido.
     *
     * @param array $payload Dados crus da requisição (já normalizados para array)
     * @param array $currentUser Usuário autenticado atual (para atribuição de vendedor, auditoria, etc.)
     *
     * @return array Dados mínimos do lead criado
     */
    public function createLead(array $payload, array $currentUser): array
    {
        $telefone = isset($payload['telefone']) ? trim((string) $payload['telefone']) : '';
        if ($telefone === '') {
            throw new InvalidArgumentException('Telefone é obrigatório para criação de lead.');
        }

        $nome       = isset($payload['nome']) ? trim((string) ($payload['nome'] ?? '')) : null;
        $origem     = isset($payload['origem']) ? trim((string) $payload['origem']) : 'extensao_chrome';
        $urlOrigem  = isset($payload['url_origem']) ? trim((string) $payload['url_origem']) : null;
        $observacao = isset($payload['observacao']) ? trim((string) $payload['observacao']) : null;
        $status     = isset($payload['status']) ? trim((string) $payload['status']) : 'novo';

        $allowedStatus = [
            'novo',
            'em_atendimento',
            'convertido',
            'perdido',
            'arquivado',
        ];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'novo';
        }

        $vendedorId = $currentUser['id'] ?? null;
        if (isset($currentUser['nivel_acesso']) && $currentUser['nivel_acesso'] === 'Administrador') {
            // Admin pode enviar leads sem vincular a um vendedor específico
            // (mantemos compatibilidade básica com o fluxo atual).
        }

        $leadId = $this->repository->insertLead(
            $nome !== '' ? $nome : null,
            $telefone,
            $origem,
            $urlOrigem !== '' ? $urlOrigem : null,
            $observacao !== '' ? $observacao : null,
            $status,
            is_int($vendedorId) ? $vendedorId : null
        );

        return [
            'lead_id'     => $leadId,
            'telefone'    => $telefone,
            'status'      => $status,
            'was_created' => true,
            'message'     => 'Lead criado com sucesso.',
        ];
    }

    /**
     * Consulta lead por telefone ou ID.
     *
     * @param string|null $telefone
     * @param int|null    $id
     *
     * @return array|null
     */
    public function getLead(?string $telefone, ?int $id): ?array
    {
        if ($id !== null) {
            return $this->repository->findById($id);
        }

        if ($telefone !== null && $telefone !== '') {
            return $this->repository->findByTelefone($telefone);
        }

        return null;
    }
}

