<?php
declare(strict_types=1);

/**
 * Helpers de segurança globais (CSP, etc).
 */

if (!function_exists('alabama_csp_nonce_attr')) {
    /**
     * Retorna atributo nonce para uso em <script> ou <style> inline.
     *
     * Exemplo:
     *   <script <?php echo alabama_csp_nonce_attr(); ?>>
     */
    function alabama_csp_nonce_attr(): string
    {
        if (!defined('ALABAMA_CSP_NONCE')) {
            return '';
        }
        return 'nonce="' . htmlspecialchars(ALABAMA_CSP_NONCE, ENT_QUOTES, 'UTF-8') . '"';
    }
}
