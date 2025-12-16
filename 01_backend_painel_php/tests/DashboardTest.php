<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for Dashboard Analytics functionality.
 */
final class DashboardTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use SQLite in memory for isolated tests
        $this->dbFile = sys_get_temp_dir() . '/test_dashboard_' . bin2hex(random_bytes(8)) . '.db';
        $this->pdo = new PDO('sqlite:' . $this->dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create test tables
        $this->createTestTables();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->pdo = null;
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    private function createTestTables(): void
    {
        // LLM logs table
        $this->pdo->exec("CREATE TABLE llm_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_tokens INTEGER DEFAULT 0,
            latency_ms INTEGER DEFAULT 0,
            success INTEGER DEFAULT 1
        )");

        // Remarketing campaigns table
        $this->pdo->exec("CREATE TABLE remarketing_campanhas (
            id VARCHAR(32) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            ativo INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // WhatsApp bulk job items table
        $this->pdo->exec("CREATE TABLE whatsapp_bulk_job_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // WhatsApp conversations table
        $this->pdo->exec("CREATE TABLE whatsapp_conversas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            telefone_cliente VARCHAR(64) NOT NULL,
            status VARCHAR(32) DEFAULT 'ativa',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // WhatsApp messages table
        $this->pdo->exec("CREATE TABLE whatsapp_mensagens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversa_id INTEGER NOT NULL,
            direction VARCHAR(3) NOT NULL,
            conteudo TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private function seedTestData(): void
    {
        // Seed LLM logs
        $this->pdo->exec("INSERT INTO llm_logs (total_tokens, latency_ms, success) VALUES (100, 500, 1)");
        $this->pdo->exec("INSERT INTO llm_logs (total_tokens, latency_ms, success) VALUES (150, 600, 1)");
        $this->pdo->exec("INSERT INTO llm_logs (total_tokens, latency_ms, success) VALUES (200, 400, 0)");

        // Seed campaigns
        $this->pdo->exec("INSERT INTO remarketing_campanhas (id, nome, ativo) VALUES ('camp1', 'Test Campaign 1', 1)");
        $this->pdo->exec("INSERT INTO remarketing_campanhas (id, nome, ativo) VALUES ('camp2', 'Test Campaign 2', 0)");

        // Seed bulk job items
        $this->pdo->exec("INSERT INTO whatsapp_bulk_job_items (job_id, status) VALUES (1, 'sent')");
        $this->pdo->exec("INSERT INTO whatsapp_bulk_job_items (job_id, status) VALUES (1, 'sent')");
        $this->pdo->exec("INSERT INTO whatsapp_bulk_job_items (job_id, status) VALUES (1, 'failed')");

        // Seed conversations
        $this->pdo->exec("INSERT INTO whatsapp_conversas (telefone_cliente, status) VALUES ('+5511999999999', 'ativa')");
        $this->pdo->exec("INSERT INTO whatsapp_conversas (telefone_cliente, status) VALUES ('+5511888888888', 'finalizada')");

        // Seed messages
        $this->pdo->exec("INSERT INTO whatsapp_mensagens (conversa_id, direction, conteudo) VALUES (1, 'in', 'Hello')");
        $this->pdo->exec("INSERT INTO whatsapp_mensagens (conversa_id, direction, conteudo) VALUES (1, 'out', 'Hi there')");
    }

    public function testLlmMetricsCalculation(): void
    {
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(total_tokens) as total_tokens,
                    AVG(latency_ms) as avg_latency,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count
                FROM llm_logs";
        
        $stmt = $this->pdo->query($sql);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertIsArray($metrics);
        $this->assertEquals(3, $metrics['total_calls']);
        $this->assertEquals(450, $metrics['total_tokens']);
        $this->assertEquals(2, $metrics['success_count']);
        $this->assertGreaterThan(0, $metrics['avg_latency']);
    }

    public function testCampaignMetricsCalculation(): void
    {
        $sql = "SELECT 
                    COUNT(*) as total_campaigns,
                    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as active_campaigns,
                    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inactive_campaigns
                FROM remarketing_campanhas";
        
        $stmt = $this->pdo->query($sql);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertIsArray($metrics);
        $this->assertEquals(2, $metrics['total_campaigns']);
        $this->assertEquals(1, $metrics['active_campaigns']);
        $this->assertEquals(1, $metrics['inactive_campaigns']);
    }

    public function testBulkJobItemsMetrics(): void
    {
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM whatsapp_bulk_job_items";
        
        $stmt = $this->pdo->query($sql);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertIsArray($metrics);
        $this->assertEquals(3, $metrics['total_items']);
        $this->assertEquals(2, $metrics['sent_count']);
        $this->assertEquals(1, $metrics['failed_count']);
    }

    public function testConversationMetrics(): void
    {
        $sql = "SELECT 
                    COUNT(*) as total_conversations,
                    SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) as active_conversations,
                    COUNT(DISTINCT telefone_cliente) as unique_contacts
                FROM whatsapp_conversas";
        
        $stmt = $this->pdo->query($sql);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertIsArray($metrics);
        $this->assertEquals(2, $metrics['total_conversations']);
        $this->assertEquals(1, $metrics['active_conversations']);
        $this->assertEquals(2, $metrics['unique_contacts']);
    }

    public function testMessageDirectionMetrics(): void
    {
        $sql = "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN direction = 'in' THEN 1 ELSE 0 END) as incoming_messages,
                    SUM(CASE WHEN direction = 'out' THEN 1 ELSE 0 END) as outgoing_messages
                FROM whatsapp_mensagens";
        
        $stmt = $this->pdo->query($sql);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertIsArray($metrics);
        $this->assertEquals(2, $metrics['total_messages']);
        $this->assertEquals(1, $metrics['incoming_messages']);
        $this->assertEquals(1, $metrics['outgoing_messages']);
    }

    public function testDeliveryRateCalculation(): void
    {
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count
                FROM whatsapp_bulk_job_items";
        
        $stmt = $this->pdo->query($sql);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalItems = (int)$metrics['total_items'];
        $sentCount = (int)$metrics['sent_count'];
        
        $deliveryRate = $totalItems > 0 ? ($sentCount / $totalItems) * 100 : 0;
        
        $this->assertEquals(66.67, round($deliveryRate, 2));
    }

    public function testSuccessRateCalculation(): void
    {
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count
                FROM llm_logs";
        
        $stmt = $this->pdo->query($sql);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalCalls = (int)$metrics['total_calls'];
        $successCount = (int)$metrics['success_count'];
        
        $successRate = $totalCalls > 0 ? ($successCount / $totalCalls) * 100 : 0;
        
        $this->assertEquals(66.67, round($successRate, 2));
    }
}
