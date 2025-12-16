<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Services\Whatsapp\WhatsappAiSuggestionService;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\WhatsappMessageRepository;



final class WhatsappAiSuggestionServiceTest extends TestCase
{
    public function testFalhaQuandoDadosInsuficientes(): void
    {
        $llm    = new LlmService('stub', 5);
        $repo   = $this->createMock(WhatsappMessageRepository::class);
        $service = new WhatsappAiSuggestionService($llm, $repo);

        $result = $service->gerarSugestao(1, '', '', '', '');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['resposta']);
        $this->assertSame('Dados insuficientes para gerar sugestão.', $result['error']);
    }

    public function testGeraSugestaoComStubProvider(): void
    {
        $llm    = new LlmService('stub', 5);
        $repo   = $this->createMock(WhatsappMessageRepository::class);

        $repo->expects($this->once())
             ->method('storeOutgoingIaSuggestion');

        $service = new WhatsappAiSuggestionService($llm, $repo);

        $result = $service->gerarSugestao(
            10,
            'thread-xyz',
            'Cliente Teste',
            '11999999999',
            'Olá, gostaria de saber mais sobre o produto X.',
            'informal',
            null
        );

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['resposta']);
        $this->assertNull($result['error']);
    }
}
