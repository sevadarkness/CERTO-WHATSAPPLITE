<?php
/**
 * Smoke tests básicos para Alabama.
 * NÃO DEVE FICAR PÚBLICO EM PRODUÇÃO SEM PROTEÇÃO.
 */

declare(strict_types=1);

require_once __DIR__ . '/../db_config.php';

header('Content-Type: text/plain; charset=utf-8');

$results = [];

function ok(string $msg): void {
    global $results;
    $results[] = "[OK]  " . $msg;
}

function fail(string $msg): void {
    global $results;
    $results[] = "[ERRO] " . $msg;
}

// 1) Conexão com o banco
try {
    $pdo->query('SELECT 1');
    ok('Conexão com o banco de dados OK.');
} catch (Throwable $e) {
    fail('Falha na conexão com o banco: ' . $e->getMessage());
}

// 2) Pelo menos um usuário Administrador
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel_acesso = 'Administrador'");
    $countAdmin = (int) $stmt->fetchColumn();
    if ($countAdmin > 0) {
        ok('Há pelo menos um usuário Administrador.');
    } else {
        fail('Nenhum usuário Administrador encontrado.');
    }
} catch (Throwable $e) {
    fail('Erro ao verificar usuários Administrador: ' . $e->getMessage());
}

// 3) Tabela estoque_vendedores acessível
try {
    $pdo->query('SELECT 1 FROM estoque_vendedores LIMIT 1');
    ok('Tabela estoque_vendedores acessível.');
} catch (Throwable $e) {
    fail('Tabela estoque_vendedores não acessível: ' . $e->getMessage());
}

// 4) Tabela matching_registros acessível (se existir)
try {
    $pdo->query('SELECT 1 FROM matching_registros LIMIT 1');
    ok('Tabela matching_registros acessível.');
} catch (Throwable $e) {
    fail('Tabela matching_registros não acessível: ' . $e->getMessage());
}

echo implode("\n", $results), "\n";

// Verifica tabelas do Bot WhatsApp + IA
try {
    $pdo->query('SELECT 1 FROM whatsapp_bot_settings LIMIT 1');
    ok('Tabela whatsapp_bot_settings acessível.');
    $pdo->query('SELECT 1 FROM whatsapp_conversas LIMIT 1');
    ok('Tabela whatsapp_conversas acessível.');
    $pdo->query('SELECT 1 FROM whatsapp_mensagens LIMIT 1');
    ok('Tabela whatsapp_mensagens acessível.');
    $pdo->query('SELECT 1 FROM llm_logs LIMIT 1');
    ok('Tabela llm_logs acessível.');
} catch (Throwable $e) {
    fail('Falha ao acessar tabelas WhatsApp/LLM: ' . $e->getMessage());
}

// Verifica tabelas V10 (Supremacy)
try {
    $pdo->query('SELECT 1 FROM jobs_agendados LIMIT 1');
    ok('Tabela jobs_agendados acessível.');
    $pdo->query('SELECT 1 FROM jobs_logs LIMIT 1');
    ok('Tabela jobs_logs acessível.');
    $pdo->query('SELECT 1 FROM whatsapp_atendimentos LIMIT 1');
    ok('Tabela whatsapp_atendimentos acessível.');
    $pdo->query('SELECT 1 FROM llm_training_samples LIMIT 1');
    ok('Tabela llm_training_samples acessível.');
} catch (Throwable $e) {
    fail('Falha ao acessar tabelas V10 (Supremacy): ' . $e->getMessage());
}
