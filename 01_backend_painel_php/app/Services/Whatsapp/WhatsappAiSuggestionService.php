<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Whatsapp;

use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\WhatsappMessageRepository;
use function log_app_event;

/**
 * Orquestra geração de sugestões de resposta via IA para conversas de WhatsApp.
 *
 * Integra diretamente com LlmService (provider OpenAI ou stub) e registra
 * métricas/auditoria via logger estruturado.
 */
final class WhatsappAiSuggestionService
{
    public function __construct(
        private readonly LlmService $llm,
        private readonly WhatsappMessageRepository $messages
    ) {
    }

    /**
     * Gera uma sugestão de resposta para um vendedor.
     *
     * @param int         $vendedorId       ID do vendedor logado
     * @param string      $threadId         Identificador da thread/conversa
     * @param string      $contatoNome      Nome do contato
     * @param string      $contatoTelefone  Telefone do contato
     * @param string      $ultimaMensagem   Última mensagem recebida
     * @param string|null $tomDesejado      Ex.: "formal", "informal", "neutro"
     *
     * @return array{ok:bool,resposta:?string,error:?string}
     */
    public function gerarSugestao(
        int $vendedorId,
        string $threadId,
        string $contatoNome,
        string $contatoTelefone,
        string $ultimaMensagem,
        ?string $tomDesejado = null,
        ?string $acaoIa = null
    ): array {
        $threadId        = trim($threadId);
        $contatoNome     = trim($contatoNome);
        $contatoTelefone = trim($contatoTelefone);
        $ultimaMensagem  = trim($ultimaMensagem);
        $acaoIa = $acaoIa !== null ? trim($acaoIa) : null;
        if ($acaoIa === '') {
            $acaoIa = null;
        }


        if ($threadId === '' || $contatoTelefone === '' || $ultimaMensagem === '') {
            return [
                'ok'       => false,
                'resposta' => null,
                'error'    => 'Dados insuficientes para gerar sugestão.',
            ];
        }

        $tarefa   = $this->descreverTarefa($acaoIa);
        $variante = $this->escolherVariantePrompt($acaoIa);

        $prompt = $this->montarPrompt(
            $contatoNome,
            $contatoTelefone,
            $ultimaMensagem,
            $tomDesejado,
            $tarefa,
            $variante
        );

        $resposta = $this->chamarLlm($prompt);

        if ($resposta === null) {
            log_app_event('ia', 'whatsapp_ai_erro', [
                'vendedor_id' => $vendedorId,
                'thread_id'   => $threadId,
                'acao_ia'     => $acaoIa,
                'variante'    => $variante,
            ]);

            return [
                'ok'       => false,
                'resposta' => null,
                'error'    => 'Não foi possível gerar sugestão no momento.',
            ];
        }

        // Persiste o fato de que a sugestão foi gerada (para métricas/auditoria)
        $this->messages->storeOutgoingIaSuggestion(
            $threadId,
            $contatoNome,
            $contatoTelefone,
            $resposta,
            $vendedorId
        );

        log_app_event('ia', 'whatsapp_ai_sugestao_gerada', [
            'vendedor_id' => $vendedorId,
            'thread_id'   => $threadId,
            'acao_ia'     => $acaoIa,
            'variante'    => $variante ?? 'A',
        ]);

        return [
            'ok'       => true,
            'resposta' => $resposta,
            'error'    => null,
        ];
    }


    /**
     * Descreve a tarefa solicitada para a IA com base na ação enviada pela extensão/painel.
     */
    private function descreverTarefa(?string $acaoIa): string
    {
        return match ($acaoIa) {
            'new' => 'Gerar uma nova resposta completa para o cliente com base na última mensagem recebida.',
            'improve' => 'Melhorar a redação da resposta do vendedor, mantendo a intenção original e aumentando a clareza e a persuasão.',
            'rephrase' => 'Reformular a resposta em outras palavras, mantendo o mesmo sentido e evitando repetições.',
            'longer' => 'Tornar a resposta mais detalhada e explicativa, adicionando informações úteis e exemplos quando fizer sentido.',
            'shorter' => 'Tornar a resposta mais curta e direta ao ponto, preservando a cordialidade e as informações essenciais.',
            default => 'Gerar uma resposta adequada e útil para o cliente, alinhada ao contexto da conversa.',
        };
    }

    /**
     * Escolhe uma variante de prompt para possibilitar testes A/B de estrutura.
     */
    private function escolherVariantePrompt(?string $acaoIa): string
    {
        try {
            $seed = microtime(true) . ':' . ($acaoIa ?? 'none') . ':' . random_int(0, 1000000);
            return (hexdec(substr(sha1($seed), 0, 4)) % 2) === 0 ? 'A' : 'B';
        } catch (\Throwable $e) {
            return 'A';
        }
    }

        private function montarPrompt(
        string $contatoNome,
        string $contatoTelefone,
        string $ultimaMensagem,
        ?string $tomDesejado,
        string $tarefa,
        string $variante
    ): string {
        $tom = $tomDesejado ?: 'neutro e profissional';

        // A/B test simples de prompt: a variante B pede clarificação quando a mensagem está ambígua
        $instrucoesVariante = '';
        if ($variante === 'B') {
            $instrucoesVariante = "Se a mensagem do cliente estiver ambígua, faça UMA pergunta curta de clarificação antes de tentar vender.\n";
        }

        return sprintf(
            "Você é um assistente de vendas de uma loja de varejo.\n" .
            "Escreva UMA ÚNICA mensagem de resposta em português do Brasil.\n" .
            "Use um tom %s, sempre respeitoso.\n" .
            "Tarefa: %s\n" .
            "%s\n" .
            "Nome do cliente: %s\n" .
            "Telefone do cliente: %s\n" .
            "Última mensagem recebida:\n\"%s\"\n\n" .
            "Responda apenas com o texto da mensagem que o vendedor deve enviar no WhatsApp, sem explicações adicionais.",
            $tom,
            $tarefa,
            $instrucoesVariante,
            $contatoNome !== '' ? $contatoNome : 'Cliente',
            $contatoTelefone,
            $ultimaMensagem
        );
    }

/**
     * Encapsula chamada real à LLM via LlmService.
     */
    private function chamarLlm(string $prompt): ?string
    {
        $resposta = $this->llm->generateChatCompletion($prompt);

        if ($resposta === null) {
            return null;
        }

        // Normaliza quebras de linha e espaços excessivos
        $resposta = preg_replace('/\s+/', ' ', $resposta ?? '');

        return trim((string) $resposta);
    }
}
