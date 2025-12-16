<?php
declare(strict_types=1);

/**
 * Utilitários para normalização de telefone e extração de DDI/DDD
 * usados pelas rotinas de contatos do Bot WhatsApp.
 *
 * Todas as funções aqui trabalham apenas em cima dos números já existentes
 * na base (sem gerar dados fictícios) e aplicam heurísticas simples para
 * extrair país e DDD quando possível.
 */

/**
 * Remove caracteres não numéricos, preservando apenas dígitos.
 */
function wa_only_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

/**
 * Normaliza o telefone apenas removendo formatação.
 * Não altera DDI/DDD nem insere valores fictícios.
 */
function wa_normalize_phone(string $phone): string
{
    $digits = wa_only_digits($phone);
    return $digits;
}

/**
 * Tenta extrair o código de país (DDI) a partir do número informado.
 *
 * Regra conservadora:
 *  - Se começar com '+', usamos os primeiros 2 dígitos após o '+'.
 *  - Se começar com '00', usamos os 2 dígitos seguintes.
 *  - Caso contrário, não inferimos nada (retorna null).
 *
 * O objetivo aqui é evitar "chutar" país quando o formato não deixa claro.
 */
function wa_extract_country_code(string $phone): ?string
{
    $trim = trim($phone);
    $digits = wa_only_digits($trim);
    if ($digits === '') {
        return null;
    }

    if (strpos($trim, '+') === 0) {
        // Ex: +55 21 99999-9999 -> DDI 55
        return substr($digits, 0, 2);
    }

    if (strpos($digits, '00') === 0 && strlen($digits) > 4) {
        // Ex: 005521999999999 -> DDI 55
        return substr($digits, 2, 2);
    }

    // Sem indicação clara de DDI -> não arriscamos
    return null;
}

/**
 * Tenta extrair o código de área (DDD) a partir do número informado.
 *
 * Regra conservadora:
 *  - Se o DDI for conhecido (ex.: '55'), o DDD são os 2 dígitos seguintes.
 *  - Caso contrário, não inferimos nada (retorna null).
 *
 * Isso cobre bem o formato padrão brasileiro (+55 XX 9XXXX-XXXX) sem
 * inventar DDD para outros países.
 */
function wa_extract_area_code(string $phone, ?string $countryCode = null): ?string
{
    $digits = wa_only_digits($phone);
    if ($digits === '') {
        return null;
    }

    if ($countryCode === null) {
        $countryCode = wa_extract_country_code($phone);
    }

    if ($countryCode === null) {
        return null;
    }

    if (strpos($digits, $countryCode) !== 0) {
        return null;
    }

    $rest = substr($digits, strlen($countryCode));
    if (strlen($rest) < 2) {
        return null;
    }

    return substr($rest, 0, 2);
}
