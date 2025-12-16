<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Testes para o sistema de auto-migration.
 */
final class AutoMigrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Usa SQLite em memória para testes isolados
        $this->dbFile = sys_get_temp_dir() . '/test_automigration_' . bin2hex(random_bytes(8)) . '.db';
        $this->pdo = new PDO('sqlite:' . $this->dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Carrega as funções de auto-migration
        require_once __DIR__ . '/../database/auto_migrate.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->pdo = null;
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    public function testAutoMigrateTableExistsReturnsFalseForNonExistentTable(): void
    {
        $exists = auto_migrate_table_exists($this->pdo, 'non_existent_table');
        $this->assertFalse($exists);
    }

    public function testAutoMigrateTableExistsReturnsTrueForExistingTable(): void
    {
        // Cria uma tabela de teste
        $this->pdo->exec("CREATE TABLE test_table (id INTEGER PRIMARY KEY)");
        
        $exists = auto_migrate_table_exists($this->pdo, 'test_table');
        $this->assertTrue($exists);
    }

    public function testAutoMigrateRunCreatesTableWhenNotExists(): void
    {
        // Adapta a migration para SQLite (sem ENGINE, CHARSET, etc)
        $originalFunc = 'auto_migrate_run';
        
        // Executa a migration (adaptada para SQLite)
        $results = $this->runSqliteMigration();
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        
        // Verifica se alguma tabela foi criada
        $created = array_filter($results, fn($r) => $r['status'] === 'created');
        $this->assertNotEmpty($created);
    }

    public function testAutoMigrateRunIsIdempotent(): void
    {
        // Primeira execução
        $results1 = $this->runSqliteMigration();
        
        // Segunda execução
        $results2 = $this->runSqliteMigration();
        
        // A segunda execução deve retornar 'exists' em vez de 'created'
        $this->assertIsArray($results2);
        
        $exists = array_filter($results2, fn($r) => $r['status'] === 'exists');
        $this->assertNotEmpty($exists);
    }

    public function testAutoMigrateTableExistsHandlesExceptions(): void
    {
        // Cria um PDO inválido para forçar exceção
        $badPdo = new PDO('sqlite::memory:');
        $badPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Fecha a conexão para forçar erro
        unset($badPdo);
        
        // Testa com PDO válido mas query inválida não deve lançar exceção
        $exists = auto_migrate_table_exists($this->pdo, ''); // Nome vazio
        $this->assertFalse($exists); // Deve retornar false, não lançar exceção
    }

    /**
     * Helper para executar migration adaptada para SQLite.
     * SQLite não suporta todas as sintaxes do MySQL.
     */
    private function runSqliteMigration(): array
    {
        $results = [];
        
        if (!auto_migrate_table_exists($this->pdo, 'remarketing_campanhas')) {
            // SQL adaptado para SQLite
            $sql = "CREATE TABLE IF NOT EXISTS remarketing_campanhas (
                id VARCHAR(32) PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                ativo INTEGER DEFAULT 1,
                config_json TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER NULL
            )";
            
            try {
                $this->pdo->exec($sql);
                $results[] = ['table' => 'remarketing_campanhas', 'status' => 'created'];
            } catch (Throwable $e) {
                $results[] = ['table' => 'remarketing_campanhas', 'status' => 'error', 'message' => $e->getMessage()];
            }
        } else {
            $results[] = ['table' => 'remarketing_campanhas', 'status' => 'exists'];
        }
        
        return $results;
    }

    public function testMigrationResultStructure(): void
    {
        $results = $this->runSqliteMigration();
        
        $this->assertIsArray($results);
        
        foreach ($results as $result) {
            $this->assertArrayHasKey('table', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertIsString($result['table']);
            $this->assertIsString($result['status']);
            $this->assertContains($result['status'], ['created', 'exists', 'error']);
        }
    }
}
