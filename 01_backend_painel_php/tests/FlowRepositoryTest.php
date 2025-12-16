<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Repositories\FlowRepository;
use RedeAlabama\Domain\Flow\Flow;



final class FlowRepositoryTest extends TestCase
{
    public function testListActiveReturnsFlows(): void
    {
        $pdo = $this->createMock(PDO::class);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'nome' => 'Fluxo A', 'ativo' => 1],
            ['id' => 2, 'nome' => 'Fluxo B', 'ativo' => 1],
        ]);

        $pdo->method('query')->willReturn($stmt);

        $repo  = new FlowRepository($pdo);
        $flows = $repo->listActive();

        $this->assertCount(2, $flows);
        $this->assertInstanceOf(Flow::class, $flows[0]);
    }
}
