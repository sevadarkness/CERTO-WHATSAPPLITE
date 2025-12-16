<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Lead;

/**
 * Facade/alias para o serviço de regras de negócio de Leads.
 *
 * Mantém compatibilidade com a nomenclatura plural (LeadsService),
 * reutilizando toda a lógica existente em LeadService.
 */
final class LeadsService extends LeadService
{
    // No momento, não adiciona comportamento novo.
    // Serve como ponto de extensão para futuras regras específicas da API v2.
}

