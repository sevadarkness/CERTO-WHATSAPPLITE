# 📋 Guia de Testes - WhatsHybrid Lite v0.2.2

## ⚠️ IMPORTANTE
Este guia descreve como testar manualmente todas as correções implementadas na extensão Chrome.

## 🔧 Preparação

### 1. Carregar Extensão no Chrome
1. Abra Chrome e vá para `chrome://extensions/`
2. Ative "Modo do desenvolvedor" (canto superior direito)
3. Clique em "Carregar sem compactação"
4. Selecione a pasta `05chromeextensionwhatsapp`
5. Verifique se a extensão aparece ativa

### 2. Configurar API Key
1. Clique no ícone da extensão
2. Vá para aba "Configurações"
3. Insira sua OpenAI API Key (começa com `sk-`)
4. Clique "Salvar"

### 3. Abrir WhatsApp Web
1. Vá para https://web.whatsapp.com
2. Escaneie QR code se necessário
3. Aguarde carregar completamente

### 4. Ativar Debug Mode (Opcional mas Recomendado)
1. Abra o console do DevTools (F12)
2. Vá para o arquivo `content/content.js`
3. Na linha 28, altere `const DEBUG_MODE = false;` para `const DEBUG_MODE = true;`
4. Recarregue a extensão
5. Agora você verá logs detalhados com prefixo `[WHL Debug]`

---

## ✅ Teste 1: Respostas Rápidas (Quick Replies)

### Objetivo
Verificar que gatilhos como `/teste` inserem automaticamente respostas no chat

### Configuração
1. Abra o popup da extensão
2. Vá para aba "Rápidas"
3. Configure uma resposta rápida:
   - **Gatilho**: `teste` (sem a barra /)
   - **Resposta**: `Olá! Esta é uma resposta rápida de teste.`
4. Clique "Adicionar"
5. Clique "Salvar"

### Execução
1. No WhatsApp Web, abra qualquer conversa
2. No campo de mensagem, digite: `/teste`
3. Pressione Enter

### Resultado Esperado
- ✅ O texto `/teste` deve ser substituído por `Olá! Esta é uma resposta rápida de teste.`
- ✅ O texto deve aparecer no composer (campo de mensagem)
- ✅ Você pode editar o texto antes de enviar
- ✅ A mensagem NÃO é enviada automaticamente (você controla quando enviar)

### Logs de Debug Esperados
```
[WHL Debug] Quick reply matched: teste → Olá! Esta é uma resposta rápida de teste.
[WHL Debug] ✅ Quick reply inserted successfully
```

### ❌ Troubleshooting
- Se não funcionar, verifique que o gatilho foi salvo sem a barra `/`
- Verifique que o cache foi atualizado (aguarde 5 segundos após salvar)
- Teste com outro gatilho diferente

---

## ✅ Teste 2: Envio para Equipe/Empresa

### Objetivo
Verificar que mensagens são enviadas para múltiplos membros da equipe

### Configuração
1. Abra o popup da extensão
2. Vá para aba "Equipe"
3. Configure o nome do remetente: `Teste Company`
4. Adicione membros da equipe:
   - **Nome**: `Membro 1`
   - **Número**: Seu próprio número de WhatsApp (para testar com segurança)
   - Clique "Adicionar Membro"
5. Clique "Salvar"

### Execução
1. Selecione o membro adicionado (checkbox)
2. Digite uma mensagem de teste: `Olá equipe! Mensagem de teste.`
3. Clique "Enviar para Selecionados"

### Resultado Esperado
- ✅ WhatsApp Web deve abrir automaticamente o chat com o número
- ✅ A mensagem deve ser formatada: `*Teste Company:* Olá equipe! Mensagem de teste.`
- ✅ A mensagem deve ser inserida no composer e enviada
- ✅ Status deve mostrar "✅ Enviado para 1 membro(s)!"

### Logs de Debug Esperados
```
[WHL] Sending team messages to 1 members
[WHL Debug] [1/1] Processing scheduled: +5511999999999
[WHL Debug] Opening chat...
[WHL Debug] openChatBySearch: dígitos extraídos: 5511999999999
[WHL Debug] ✅ Chat aberto com sucesso (composer encontrado)
[WHL Debug] Inserting text...
[WHL Debug] ✅ Success for +5511999999999
```

### ❌ Troubleshooting
- Se não enviar, verifique que o WhatsApp Web está aberto e logado
- Verifique que o número está no formato correto (com DDD)
- Teste com seu próprio número primeiro para segurança

---

## ✅ Teste 3: Campanhas com Imagens

### Objetivo
Verificar que imagens são anexadas e enviadas corretamente em campanhas

### Preparação
1. Prepare uma imagem pequena (< 1MB) para teste
2. Prepare um arquivo CSV com formato:
   ```
   numero,nome
   5511999999999,Teste 1
   ```
   (Use seu próprio número para teste seguro)

### Configuração
1. Abra o popup → WhatsApp Web deve estar aberto
2. Clique no ícone da extensão no WhatsApp Web (canto inferior direito)
3. Vá para aba "Campanhas"
4. Selecione modo "DOM (Assistido)"

### Execução
1. Cole os números no campo (um por linha): `5511999999999,Teste`
2. Digite a mensagem: `Olá {{nome}}! Esta é uma campanha de teste.`
3. Clique em "Escolher arquivo" e selecione a imagem
4. Aguarde aparecer "✅ Mídia pronta: [nome_da_imagem]"
5. Clique "Iniciar Campanha"
6. No modal de preview, clique "Confirmar"

### Resultado Esperado
- ✅ WhatsApp deve abrir o chat automaticamente
- ✅ Botão de anexo (📎) deve ser clicado automaticamente
- ✅ Imagem deve aparecer no preview do WhatsApp
- ✅ Legenda deve ser preenchida com a mensagem
- ✅ Imagem deve ser enviada automaticamente
- ✅ Status: "✅ Enviado (1/1) para +5511999999999"

### Logs de Debug Esperados
```
[WHL Debug] Iniciando campanha DOM com 1 contatos
[WHL Debug] [1/1] Processando: 5511999999999
[WHL Debug] Abrindo chat...
[WHL Debug] ✅ Chat aberto com sucesso
[WHL Debug] Composer encontrado!
[WHL Debug] attachMediaAndSend: iniciando envio de mídia
[WHL Debug] ✓ Botão de anexo encontrado, clicando...
[WHL Debug] ✓ Input de arquivo encontrado: image/*
[WHL Debug] ✓ Arquivo criado: image.jpg image/jpeg 12345 bytes
[WHL Debug] ✓ Arquivo anexado, aguardando preview...
[WHL Debug] ✓ Botão de enviar mídia encontrado (tentativa 3)
[WHL Debug] Adicionando legenda: Olá Teste! Esta é uma campanha de teste.
[WHL Debug] ✓ Legenda inserida
[WHL Debug] Clicando botão enviar mídia...
[WHL Debug] ✅ Mídia enviada com sucesso
```

### Tempos Esperados
- Clicar anexo: ~600ms
- Aguardar preview: até 15 segundos (50 tentativas × 300ms)
- Enviar mídia: ~1200ms
- **Total**: ~2-5 segundos por imagem

### ❌ Troubleshooting
- Se "Preview de mídia não apareceu":
  - Verifique que a imagem é válida (JPEG/PNG < 16MB)
  - Tente com uma imagem menor
  - Aguarde mais tempo (aumentar tentativas no código)
- Se imagem não envia:
  - Verifique logs de debug para ver onde falhou
  - Tente enviar manualmente uma vez para testar WhatsApp

---

## ✅ Teste 4: Validação de Destino Correto

### Objetivo
Verificar que mensagens vão para o número correto, não para chat ativo

### Preparação
1. Abra duas conversas diferentes no WhatsApp Web
2. Deixe a Conversa A aberta (foco nela)
3. Prepare campanha para Conversa B (número diferente)

### Configuração
1. No painel de Campanhas
2. Cole o número da Conversa B (não a que está aberta)
3. Digite mensagem: `Teste de validação - {{nome}}`

### Execução
1. Certifique-se que Conversa A está aberta e ativa
2. Inicie a campanha para o número da Conversa B
3. Observe o comportamento

### Resultado Esperado
- ✅ WhatsApp deve AUTOMATICAMENTE mudar para Conversa B
- ✅ Busca deve abrir e digitar o número
- ✅ Chat correto deve ser aberto (validado por título)
- ✅ Mensagem deve ser enviada para Conversa B (não A)
- ✅ Logs devem mostrar validação bem-sucedida

### Logs de Debug Esperados
```
[WHL Debug] openChatBySearch: dígitos extraídos: 5522888888888
[WHL Debug] Procurando caixa de busca...
[WHL Debug] ✅ Caixa de busca encontrada
[WHL Debug] Digitando número na busca: 5522888888888
[WHL Debug] Aguardando resultados da busca...
[WHL Debug] Encontrados 1 resultados correspondentes
[WHL Debug] Clicando no melhor resultado...
[WHL Debug] Verificando se composer apareceu e chat está correto...
[WHL Debug] ✅ Chat aberto com sucesso (composer encontrado)
[WHL Debug]    Chat title: Contato B
[WHL Debug]    Target digits: 5522888888888
```

### Validação Implementada
- Compara últimos 8 dígitos do número alvo com título do chat
- Até 20 tentativas (6 segundos) para confirmar chat correto
- Fallback após 15 tentativas se título não contém número
- Logs detalhados de cada etapa

### ❌ Troubleshooting
- Se mensagem for para chat errado:
  - Verifique logs para ver se validação passou
  - Aumente tempo de espera se validação está pulando
  - Verifique se título do chat contém o número

---

## ✅ Teste 5: Contraste de Texto da UI

### Objetivo
Verificar que todos os textos da interface têm contraste adequado

### Áreas a Verificar

#### 5.1. Badge de Notificação
1. Com extension aberta no WhatsApp Web
2. Se houver alguma notificação, um badge deve aparecer no FAB
3. **Verifique**: Badge deve ter fundo roxo e texto branco legível

**Esperado:**
- Fundo: Roxo (`rgba(139,92,246,.92)`)
- Texto: Branco (`rgba(255,255,255,.98)`)
- Peso da fonte: 600 (negrito)
- Sombra visível

#### 5.2. Área de Resposta da IA (Chatbot)
1. Vá para aba "Chatbot"
2. Digite uma mensagem de teste
3. Clique "Gerar Resposta"
4. **Verifique**: Texto da resposta na textarea deve ser branco sobre fundo escuro

**Esperado:**
- Textarea background: `rgba(5,7,15,.55)` (escuro semi-transparente)
- Texto: `var(--text)` = `rgba(240,243,255,.95)` (branco)
- Alto contraste, fácil de ler

#### 5.3. Status e Mensagens
1. Em qualquer aba, execute uma ação (salvar, enviar, etc.)
2. Observe mensagens de status
3. **Verifique**: 
   - ✅ Mensagens de sucesso: Verde claro legível
   - ❌ Mensagens de erro: Vermelho legível
   - ℹ️ Mensagens informativas: Branco/cinza legível

**Esperado:**
- Sucesso: `rgba(120, 255, 190, .95)` (verde claro)
- Erro: `#ff4d4f` (vermelho)
- Info: `rgba(240,243,255,.70)` (cinza claro)

#### 5.4. Labels e Inputs
1. Verifique todas as abas
2. Observe labels de campos
3. **Verifique**: Labels devem ser brancas e legíveis

**Esperado:**
- Labels: `rgba(240,243,255,.92)` (quase branco)
- Inputs/textarea: Texto branco sobre fundo escuro
- Placeholders: Cinza claro visível

### ✅ Critério de Sucesso
- Todos os textos devem ser legíveis sem esforço
- Nenhum texto preto em fundo roxo/escuro
- Contraste mínimo WCAG AA: 4.5:1 para texto normal

---

## 📊 Checklist Final de Validação

Marque cada item após testar:

- [ ] **Respostas Rápidas**: `/gatilho` insere texto no composer
- [ ] **Envio Equipe**: Mensagem enviada para múltiplos membros
- [ ] **Imagem Campanha**: Imagem anexada e enviada com legenda
- [ ] **Validação Destino**: Mensagem vai para número correto, não chat ativo
- [ ] **Contraste Badge**: Badge roxo com texto branco legível
- [ ] **Contraste Textarea**: Resposta IA branca sobre fundo escuro
- [ ] **Contraste Status**: Mensagens de sucesso/erro legíveis
- [ ] **Contraste Labels**: Labels e inputs com texto branco

---

## 🐛 Reportar Problemas

Se encontrar problemas:

1. **Capture logs do console** (F12 → Console)
2. **Tire screenshot** da tela com o problema
3. **Descreva passos exatos** para reproduzir
4. **Inclua informações**:
   - Versão do Chrome
   - Sistema operacional
   - Número de contatos testados
   - Tipo de arquivo (se imagem)

---

## 💡 Dicas de Teste

### Teste Seguro
- Use seu próprio número para testes iniciais
- Crie um grupo de teste só para você
- Nunca teste com clientes reais primeiro

### Debug Eficiente
- Ative DEBUG_MODE para logs detalhados
- Console do DevTools mostra todos os passos
- Procure por linhas com `[WHL Debug]` ou `[WHL]`

### Performance
- Tempos podem variar com conexão internet
- WhatsApp Web precisa estar totalmente carregado
- Aguarde animações terminarem antes de testar novamente

---

## ✅ Sucesso!

Se todos os testes passaram, a extensão está funcionando corretamente! 🎉

As correções implementadas garantem:
1. ✅ Respostas rápidas funcionais
2. ✅ Envio para equipe operacional
3. ✅ Imagens sendo enviadas corretamente
4. ✅ Mensagens indo para destino correto
5. ✅ Interface com alto contraste

**Versão testada:** WhatsHybrid Lite v0.2.2
**Data das correções:** Dezembro 2024
