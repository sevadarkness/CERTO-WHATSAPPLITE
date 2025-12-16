<?php
/**
 * Geração de PDF do relatório diário.
 *
 * Correções aplicadas:
 * - Evita fatal error quando TCPDF não estiver presente no projeto
 * - Fallback para arquivo TXT (mantendo o retorno do caminho)
 */

declare(strict_types=1);

function gerarPDF(string $mensagem, string $destino = 'relatorio_diario.pdf'): string
{
    $tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';

    if (!is_file($tcpdfPath)) {
        // Fallback: grava como TXT (útil para não quebrar o fluxo da aplicação)
        $fallback = preg_replace('/\.pdf$/i', '.txt', $destino);
        if (!is_string($fallback) || $fallback === '') {
            $fallback = 'relatorio_diario.txt';
        }
        @file_put_contents($fallback, $mensagem);
        return $fallback;
    }

    require_once $tcpdfPath;

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Write(0, $mensagem);
    $pdf->Output($destino, 'F');
    return $destino;
}
