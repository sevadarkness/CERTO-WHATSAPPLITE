# SmartBot IA Core - Guia de Integração

## Visão Geral

O `smartbot-ia-core.js` é um sistema completo de atendimento automatizado inteligente que pode ser integrado ao WhatsApp via extensão Chrome.

## Arquitetura

### Componentes Principais (6)

1. **AdvancedContextAnalyzer** - Análise de perfil e contexto do cliente
2. **ContextualResponseGenerator** - Geração de respostas personalizadas
3. **IntelligentPriorityQueue** - Fila inteligente com priorização
4. **ContinuousLearningSystem** - Sistema de aprendizado contínuo
5. **HumanAssistanceSystem** - Escalonamento para atendentes humanos
6. **SmartBotDashboard** - Dashboard de métricas e relatórios

### Gerenciadores Auxiliares (24)

Fornece funcionalidades completas de:
- Agendamento, Notificações, Auth, Logs
- NLP, Diálogos, Sessões, Cache
- Webhooks, Plugins, Eventos, Analytics
- Filas, Rate Limiting, Middlewares, Permissões
- i18n, Múltiplos Canais

## Como Integrar com content.js

### Opção 1: Carregar via manifest.json (Recomendado)

Adicione ao `manifest.json`:

```json
{
  "content_scripts": [
    {
      "matches": ["https://web.whatsapp.com/*"],
      "js": [
        "content/smartbot-ia-core.js",
        "content/content.js"
      ],
      "run_at": "document_start"
    }
  ]
}
```

### Opção 2: Importar dinamicamente no content.js

```javascript
// No início do content.js
const script = document.createElement('script');
script.src = chrome.runtime.getURL('content/smartbot-ia-core.js');
document.head.appendChild(script);

// Aguardar carregamento
script.addEventListener('load', () => {
  console.log('[WHL] SmartBot IA Core carregado');
  initializeSmartBot();
});
```

## Exemplo de Uso Básico

```javascript
// 1. Inicializar o sistema
const smartbot = new SmartBotIA.Core();

await smartbot.initialize({
  bot: {
    name: 'WhatsHybrid Bot',
    version: '1.0.0',
    language: 'pt-BR'
  },
  features: {
    autoResponse: true,
    learning: true,
    humanHandoff: true
  },
  limits: {
    maxQueueSize: 100,
    maxHistorySize: 1000
  }
});

// 2. Processar mensagem recebida
const result = await smartbot.processMessage(
  clientId,           // ID do cliente (número ou nome)
  messageText,        // Texto da mensagem
  {
    clientName: 'João Silva',
    isVIP: false,
    receivedAt: Date.now()
  }
);

// 3. Verificar resultado
if (result.success) {
  console.log('Resposta:', result.response);
  console.log('Confiança:', result.confidence);
  console.log('Intenção:', result.intent);
  
  // Enviar resposta automaticamente (integrar com funções do content.js)
  await insertIntoComposer(result.response);
  await clickSend();
}

// 4. Processar feedback do usuário
smartbot.processFeedback(messageId, 'positive', {
  message: 'mensagem original',
  response: 'resposta gerada',
  intent: 'greeting'
});

// 5. Escalar para humano quando necessário
if (result.confidence < 0.5 || clientRequestsHuman) {
  smartbot.escalateToHuman(
    conversationId,
    'Confiança baixa',
    'normal',
    ['suporte']  // skills requeridas
  );
}

// 6. Obter relatórios
const report = smartbot.getReport();
console.log('Taxa de automação:', report.summary.automationRate + '%');
console.log('Tempo médio de resposta:', report.summary.avgResponseTime + 'ms');
```

## Integração Avançada

### Registrar Agentes Humanos

```javascript
// Registrar agente
smartbot.humanAssistance.registerAgent('agent-001', {
  name: 'Maria Silva',
  skills: ['suporte', 'vendas'],
  maxLoad: 5
});

// Atualizar status
smartbot.humanAssistance.updateAgentStatus('agent-001', 'available');

// Completar atendimento
smartbot.humanAssistance.completeHandoff(conversationId);
```

### Adicionar Intenções Customizadas

```javascript
// Registrar nova intenção
smartbot.intent.registerIntent('solicitar_orcamento', [
  /orçamento/i,
  /quanto custa/i,
  /valor/i
], 0.85);

// Adicionar template de resposta
smartbot.responseGenerator.addCustomTemplate(
  'solicitar_orcamento',
  'friendly',
  [
    'Claro! Posso fazer um orçamento pra você! 💰',
    'Vou preparar um orçamento! Me passa mais detalhes?'
  ]
);
```

### Configurar Webhooks

```javascript
// Registrar webhook para notificações
smartbot.webhook.register(
  'message:processed',
  'https://api.example.com/webhooks/messages',
  {
    'Authorization': 'Bearer TOKEN',
    'Content-Type': 'application/json'
  }
);

// Webhooks são disparados automaticamente
```

### Criar Plugin Customizado

```javascript
const myPlugin = {
  init() {
    console.log('[Plugin] Inicializado');
  },
  
  processMessage(message) {
    // Lógica customizada
    return message.toUpperCase();
  },
  
  destroy() {
    console.log('[Plugin] Finalizado');
  }
};

// Registrar plugin
smartbot.plugin.register('my-plugin', myPlugin);

// Usar plugin
const result = smartbot.plugin.execute('my-plugin', 'processMessage', 'teste');
```

### Adicionar Middleware

```javascript
// Adicionar log middleware
smartbot.middleware.use(async (context, next) => {
  console.log('[Middleware] Antes:', context);
  await next();
  console.log('[Middleware] Depois:', context);
});

// Middleware é executado automaticamente no pipeline
```

## Integração com Funções Existentes do content.js

```javascript
// Integrar com o sistema de mensagens do WhatsApp
window.addEventListener('message', async (event) => {
  if (event.data.type === 'WHATSAPP_MESSAGE_RECEIVED') {
    const { sender, text } = event.data;
    
    // Processar com SmartBot
    const result = await smartbot.processMessage(sender, text);
    
    if (result.success && result.confidence > 0.7) {
      // Auto-responder
      await insertIntoComposer(result.response);
      await clickSend();
      
      // Registrar na memória (Leão)
      await setMemory(sender, {
        lastInteraction: Date.now(),
        summary: `Bot respondeu: ${result.response}`,
        intent: result.intent
      });
    } else {
      // Sugerir resposta ao usuário
      console.log('[SmartBot] Sugestão:', result.response);
      showSuggestion(result.response);
    }
  }
});
```

## Eventos Disponíveis

```javascript
// Escutar eventos do sistema
smartbot.events.on('system:initialized', (data) => {
  console.log('Sistema inicializado:', data.version);
});

smartbot.events.on('message:processed', (data) => {
  console.log('Mensagem processada:', data.clientId);
});

smartbot.events.on('escalation:created', (data) => {
  console.log('Escalonamento criado:', data.conversationId);
  // Notificar agentes disponíveis
});

smartbot.events.on('message:error', (data) => {
  console.error('Erro ao processar:', data.error);
});
```

## Persistência de Dados

```javascript
// Salvar dados do sistema no Chrome Storage
async function saveSmartBotData() {
  const data = {
    knowledge: smartbot.learningSystem.exportKnowledge(),
    config: smartbot.config.getAll(),
    metrics: smartbot.dashboard.getReport()
  };
  
  await chrome.storage.local.set({ smartbot_data: data });
}

// Carregar dados salvos
async function loadSmartBotData() {
  const result = await chrome.storage.local.get(['smartbot_data']);
  if (result.smartbot_data) {
    smartbot.learningSystem.importKnowledge(result.smartbot_data.knowledge);
    // Restaurar outras configurações...
  }
}

// Salvar periodicamente
smartbot.scheduler.schedule('save-data', saveSmartBotData, 300000, true);
```

## Métricas e Monitoramento

```javascript
// Obter métricas em tempo real
setInterval(() => {
  const stats = {
    performance: smartbot.dashboard.getPerformanceMetrics(),
    queue: smartbot.priorityQueue.getStats(),
    learning: smartbot.learningSystem.getStats(),
    humanAssistance: smartbot.humanAssistance.getStats()
  };
  
  console.log('[SmartBot] Stats:', stats);
  
  // Enviar para analytics
  smartbot.analytics.track('stats:snapshot', stats);
}, 60000); // A cada minuto
```

## Shutdown Graceful

```javascript
// Finalizar sistema ao descarregar extensão
window.addEventListener('beforeunload', async () => {
  await saveSmartBotData();
  smartbot.shutdown();
});
```

## Troubleshooting

### Problema: Sistema não inicializa

```javascript
// Verificar se carregou corretamente
if (typeof window.SmartBotIA === 'undefined') {
  console.error('[SmartBot] Core não foi carregado!');
  // Recarregar script
}
```

### Problema: Memória crescente

```javascript
// Limpar dados antigos periodicamente
smartbot.scheduler.schedule('cleanup', () => {
  smartbot.priorityQueue.cleanup(7200000); // 2 horas
  smartbot.session.cleanup();
  smartbot.cache.clear();
}, 3600000, true);
```

### Problema: Rate Limiting

```javascript
// Ajustar configurações de rate limit
smartbot.config.set('limits.maxMessagesPerHour', 50);

// Verificar antes de enviar
if (!smartbot.rateLimiter.checkLimit(clientId)) {
  console.warn('[SmartBot] Rate limit atingido para', clientId);
}
```

## Exemplos de Uso Real

### Bot de Atendimento Completo

```javascript
async function setupFullBot() {
  const smartbot = new SmartBotIA.Core();
  
  await smartbot.initialize({
    bot: { name: 'Atendimento Automático' },
    features: { autoResponse: true, learning: true, humanHandoff: true }
  });
  
  // Registrar agentes
  smartbot.humanAssistance.registerAgent('agent-1', {
    name: 'João',
    skills: ['vendas', 'suporte'],
    maxLoad: 3
  });
  
  // Configurar intenções
  smartbot.intent.registerIntent('comprar', [/comprar|adquirir|pagar/i], 0.9);
  smartbot.intent.registerIntent('cancelar', [/cancelar|desistir/i], 0.85);
  
  // Adicionar respostas personalizadas
  smartbot.responseGenerator.addCustomTemplate('comprar', 'friendly', [
    'Ótimo! Vou te ajudar com a compra! 🛒',
    'Perfeito! Bora finalizar essa compra!'
  ]);
  
  // Processar mensagens
  smartbot.events.on('message:processed', async (data) => {
    if (data.intent === 'comprar' && data.confidence > 0.8) {
      // Enviar resposta automática
      await sendWhatsAppMessage(data.clientId, data.response);
    } else if (data.confidence < 0.5) {
      // Escalar para humano
      smartbot.escalateToHuman(data.clientId, 'Baixa confiança', 'normal');
    }
  });
  
  return smartbot;
}
```

## Referências

- Código fonte: `smartbot-ia-core.js`
- Classes: 31 (6 core + 24 auxiliary + 1 main)
- Linhas de código: ~2000
- Tamanho: 60KB
- Compatibilidade: Chrome Extension Manifest V3

## Suporte

Para dúvidas ou problemas, consulte a documentação inline no código-fonte ou abra uma issue no repositório.
