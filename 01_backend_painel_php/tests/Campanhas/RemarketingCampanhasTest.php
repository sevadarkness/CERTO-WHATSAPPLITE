<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RemarketingCampanhasTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testFile = sys_get_temp_dir() . '/test_campanhas_' . bin2hex(random_bytes(8)) . '.json';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->testFile)) {
            @unlink($this->testFile);
        }
    }

    /**
     * Helper to save test campaigns to file.
     */
    private function saveCampaigns(array $campanhas): bool
    {
        $payload = json_encode([
            'campanhas'   => array_values($campanhas),
            'updated_at'  => date('c'),
            'schema'      => 1,
            'storage'     => 'file',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return @file_put_contents($this->testFile, $payload, LOCK_EX) !== false;
    }

    /**
     * Helper to load campaigns from file.
     */
    private function loadCampaigns(): array
    {
        if (!is_file($this->testFile)) {
            return [];
        }
        $raw = @file_get_contents($this->testFile);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['campanhas']) && is_array($data['campanhas'])) {
            return $data['campanhas'];
        }
        return [];
    }

    public function testCreateCampanha(): void
    {
        $campanha = [
            'id'    => 'rm_test123',
            'nome'  => 'Campanha Teste',
            'ativo' => true,
        ];

        $this->assertTrue($this->saveCampaigns([$campanha]));
        
        $loaded = $this->loadCampaigns();
        $this->assertCount(1, $loaded);
        $this->assertEquals('rm_test123', $loaded[0]['id']);
        $this->assertEquals('Campanha Teste', $loaded[0]['nome']);
    }

    public function testUpdateCampanha(): void
    {
        // Create initial campaign
        $campanha = [
            'id'    => 'rm_test456',
            'nome'  => 'Nome Original',
            'ativo' => true,
        ];
        $this->saveCampaigns([$campanha]);

        // Update campaign
        $updated = [
            'id'    => 'rm_test456',
            'nome'  => 'Nome Atualizado',
            'ativo' => false,
        ];
        $this->saveCampaigns([$updated]);

        $loaded = $this->loadCampaigns();
        $this->assertEquals('Nome Atualizado', $loaded[0]['nome']);
        $this->assertFalse($loaded[0]['ativo']);
    }

    public function testDeleteCampanha(): void
    {
        // Create two campaigns
        $campanhas = [
            ['id' => 'rm_test1', 'nome' => 'Campanha 1', 'ativo' => true],
            ['id' => 'rm_test2', 'nome' => 'Campanha 2', 'ativo' => true],
        ];
        $this->saveCampaigns($campanhas);

        // Delete one
        $filtered = array_filter($campanhas, fn($c) => $c['id'] !== 'rm_test1');
        $this->saveCampaigns(array_values($filtered));

        $loaded = $this->loadCampaigns();
        $this->assertCount(1, $loaded);
        $this->assertEquals('rm_test2', $loaded[0]['id']);
    }

    public function testListCampanhas(): void
    {
        $campanhas = [
            ['id' => 'rm_1', 'nome' => 'Campanha A', 'ativo' => true],
            ['id' => 'rm_2', 'nome' => 'Campanha B', 'ativo' => false],
            ['id' => 'rm_3', 'nome' => 'Campanha C', 'ativo' => true],
        ];
        $this->saveCampaigns($campanhas);

        $loaded = $this->loadCampaigns();
        $this->assertCount(3, $loaded);
        
        $ids = array_column($loaded, 'id');
        $this->assertContains('rm_1', $ids);
        $this->assertContains('rm_2', $ids);
        $this->assertContains('rm_3', $ids);
    }

    public function testIdGeneration(): void
    {
        // Test that generated IDs have expected format
        $id = 'rm_' . bin2hex(random_bytes(6));
        
        $this->assertStringStartsWith('rm_', $id);
        $this->assertEquals(15, strlen($id)); // 'rm_' (3) + 12 hex chars = 15
        $this->assertMatchesRegularExpression('/^rm_[a-f0-9]{12}$/', $id);
    }

    public function testCampanhaWithConfig(): void
    {
        $campanha = [
            'id'       => 'rm_config',
            'nome'     => 'Campanha com Config',
            'ativo'    => true,
            'segmento' => 'D30+',
            'template' => 'reativacao_v1',
            'meta'     => ['key' => 'value'],
        ];

        $this->saveCampaigns([$campanha]);
        $loaded = $this->loadCampaigns();

        $this->assertArrayHasKey('segmento', $loaded[0]);
        $this->assertArrayHasKey('template', $loaded[0]);
        $this->assertArrayHasKey('meta', $loaded[0]);
        $this->assertEquals('D30+', $loaded[0]['segmento']);
    }

    public function testEmptyFile(): void
    {
        $loaded = $this->loadCampaigns();
        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }
}
