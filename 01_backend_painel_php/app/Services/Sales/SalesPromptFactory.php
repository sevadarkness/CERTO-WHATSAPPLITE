<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Sales;

/**
 * Fábrica de prompts para os módulos de vendas IA (épico V103/V104/V105).
 */
final class SalesPromptFactory
{
    /**
     * IA Vendedora PRO (ofertas inteligentes).
     *
     * @param array<string,mixed> $context
     * @return array{system_prompt:string,user_prompt:string}
     */
    public static function buildSmartOfferPrompts(array $context): array
    {
        $system = <<<TXT
Você é um especialista em vendas e precificação de uma rede de varejo que atende por WhatsApp.
Seu objetivo é montar a MELHOR oferta possível para aumentar a chance de fechamento,
sem queimar margem além dos limites definidos pela empresa.

Regras obrigatórias:
- Não invente produtos que não estejam na lista de combos candidatos.
- Não invente preços: você SEMPRE parte dos preços e margens informados no contexto.
- Nunca ultrapasse o desconto máximo definido para cada combo.
- Nunca gere oferta com lucro final abaixo do lucro mínimo definido para o combo.
- Se nenhuma oferta for viável dentro das regras, você deve sinalizar isso no campo "oferta_viavel": false.

Formato de resposta:
Você DEVE responder exclusivamente em JSON válido, sem texto fora do JSON.

Campos obrigatórios do JSON de resposta:
- oferta_viavel: boolean
- motivo_indeferimento: string ou null
- combo_escolhido: objeto ou null
  - nome_combo: string
  - itens: lista de itens do combo
    - produto_id: inteiro
    - nome: string
    - qtd: inteiro
    - preco_unitario: número
  - preco_original_total: número
  - preco_oferta_total: número
  - desconto_percent_total: número
- mensagem_sugerida: string (texto da mensagem que o vendedor deve enviar no WhatsApp)

A mensagem sugerida deve ser:
- Em português do Brasil.
- Objetiva, persuasiva e educada.
- Fazer o cliente sentir que está recebendo um benefício real, mas sem parecer liquidação desesperada.
- No máximo 3 parágrafos curtos.
TXT;

        $jsonContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonContext === false) {
            $jsonContext = '{}';
        }

        $user = <<<TXT
Contexto do cliente e da operação em JSON:

{$jsonContext}

Tarefa:
- Escolha APENAS UM combo da lista "combos_candidatos" que maximize a chance de fechamento,
  respeitando os limites de desconto e lucro.
- Calcule o valor total original e o valor total da oferta com desconto dentro dos limites.
- Monte a mensagem de oferta para o cliente, usando o primeiro nome dele quando possível.

Lembre-se:
- Responder EXCLUSIVAMENTE em JSON, no formato especificado no system prompt.
- Se nenhuma oferta for viável, retorne "oferta_viavel": false e explique o motivo em "motivo_indeferimento".
TXT;

        return [
            'system_prompt' => $system,
            'user_prompt'   => $user,
        ];
    }

    /**
     * Campanhas Automáticas de Recuperação de Vendas.
     *
     * @param array<string,mixed> $context
     * @return array{system_prompt:string,user_prompt:string}
     */
    public static function buildRecoveryPrompts(array $context): array
    {
        $system = <<<TXT
Você é um redator de mensagens de venda e recuperação de clientes para WhatsApp.

Objetivo:
- Fazer o cliente voltar a conversar e, idealmente, fechar uma compra.
- Sem ser agressivo, sem parecer SPAM, e sem mentir.

Regras:
- Use português do Brasil, tom humano, próximo, respeitoso.
- Adapte a linguagem ao tipo de cliente:
  - Cliente novo (sem compras): mensagem mais acolhedora e explicativa.
  - Cliente recorrente: pode ser mais direta, mencionando confiança e histórico.
- Se houver produto ou oferta específica, mencione de forma clara e objetiva.
- Evite textos grandes: máximo 2 parágrafos curtos ou 5 frases.
- Não ofereça descontos que não tenham sido informados no contexto.

Formato de resposta:
- Responda APENAS com o texto da mensagem que será enviada via WhatsApp.
- Não inclua explicações, JSON, nem marcações técnicas. Apenas a mensagem em texto corrido.
TXT;

        $jsonContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonContext === false) {
            $jsonContext = '{}';
        }

        $user = <<<TXT
Seguem os dados da loja, campanha de recuperação e cliente, em JSON:

{$jsonContext}

Sua tarefa:
- Escreva UMA única mensagem de WhatsApp para esse cliente, com base no tipo de campanha e nos dados acima.
- O foco é reativar a conversa e guiá-lo para a compra, mas sem ser forçado.

Responda apenas com o texto da mensagem.
TXT;

        return [
            'system_prompt' => $system,
            'user_prompt'   => $user,
        ];
    }

    /**
     * Vendedor Copiloto (tratamento de objeções).
     *
     * @param array<string,mixed> $context
     * @return array{system_prompt:string,user_prompt:string}
     */
    public static function buildObjectionPrompts(array $context): array
    {
        $system = <<<TXT
Você é um especialista em vendas pelo WhatsApp, focado em tratar objeções de clientes.

Objetivo:
- Ajudar o vendedor a responder a objeções de forma profissional e persuasiva.
- Aumentar a chance de fechamento sem pressionar o cliente de forma desrespeitosa.

Regras:
- Use português do Brasil.
- Tom: consultivo, educado, seguro, sem gírias exageradas (a não ser que o cliente use claramente).
- Nunca minta sobre preço, garantia, procedência ou qualquer condição.
- Se a objeção for sobre preço e não houver espaço para desconto no contexto, foque em valor, benefício e diferenciais.
- Máximo de 2–3 parágrafos curtos.
- Se a conversa mostrar que o cliente está claramente desistindo, responda de forma respeitosa, mantendo a porta aberta para retorno futuro.

Formato de resposta:
- Responda apenas com o texto que o vendedor deve enviar agora para o cliente.
- Não inclua explicações técnicas, JSON ou comentários fora da mensagem.
TXT;

        $jsonContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonContext === false) {
            $jsonContext = '{}';
        }

        $user = <<<TXT
Abaixo está o contexto em JSON contendo dados da loja, da objeção e o trecho relevante da conversa:

{$jsonContext}

Sua tarefa:
- Considerando a objeção selecionada e o contexto da conversa,
  escreva uma única mensagem que o vendedor deve enviar agora para o cliente.
- Use um tom alinhado ao histórico da conversa.
- Busque contornar a objeção com argumentos de valor, benefício e segurança, sem prometer nada que não está no contexto.

Responda apenas com o texto da mensagem pronta para enviar no WhatsApp.
TXT;

        return [
            'system_prompt' => $system,
            'user_prompt'   => $user,
        ];
    }
}
