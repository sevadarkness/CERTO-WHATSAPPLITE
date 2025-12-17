# SmartBot IA - Guia de Integração

## 📦 Visão Geral

Os módulos SmartBot estão organizados em `js/smartbot/` e exportados via `index.js`. Este guia explica como integrar e usar os módulos.

## 🔧 Opções de Integração

### Opção 1: Build com Bundler (Recomendado)

Use um bundler (webpack, rollup, esbuild) para compilar os módulos ES6 em um único arquivo compatível com content scripts.

**Exemplo webpack.config.js:**

```javascript
module.exports = {
  entry: './js/smartbot/index.js',
  output: {
    filename: 'smartbot-bundle.js',
    path: path.resolve(__dirname, 'dist'),
    library: 'SmartBot',
    libraryTarget: 'var'
  },
  mode: 'production'
};
```

**Depois, incluir no manifest.json:**

```json
{
  "content_scripts": [{
    "matches": ["https://web.whatsapp.com/*"],
    "js": [
      "dist/smartbot-bundle.js",
      "content/content.js"
    ]
  }]
}
```

### Opção 2: Import Dinâmico (Chrome 91+)

Chrome 91+ suporta ES modules em content scripts:

**manifest.json:**

```json
{
  "content_scripts": [{
    "matches": ["https://web.whatsapp.com/*"],
    "js": ["content/content.js"]
  }],
  "web_accessible_resources": [{
    "resources": ["js/smartbot/*.js"],
    "matches": ["https://web.whatsapp.com/*"]
  }]
}
```

**Em content.js:**

```javascript
// Carregar SmartBot dinamicamente
const { SmartBotCore, initSmartBot } = await import(
  chrome.runtime.getURL('js/smartbot/core/smartbot-core.js')
);

const smartbot = await initSmartBot();
window.wa.smartbot = smartbot;
```

### Opção 3: Via Background Script

Carregar módulos no background script e comunicar via mensagens:

**background.js:**

```javascript
import { SmartBotCore } from './js/smartbot/index.js';

const smartbot = new SmartBotCore();
await smartbot.initialize();

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'smartbot:process') {
    smartbot.processMessage(request.message, request.context)
      .then(result => sendResponse(result));
    return true; // Keep channel open
  }
});
```

**content.js:**

```javascript
async function processWithSmartBot(message) {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage({
      action: 'smartbot:process',
      message,
      context: {}
    }, resolve);
  });
}
```

## 🚀 Uso Básico

### Inicialização

```javascript
import { initSmartBot } from './js/smartbot/index.js';

// Inicializar com opções
const smartbot = await initSmartBot({
  logLevel: 'info',
  cacheStrategy: 'lru',
  cacheSize: 100,
  sessionTimeout: 1800000
});

// Ou usar instância manual
import { SmartBotCore } from './js/smartbot/core/smartbot-core.js';
const smartbot = new SmartBotCore(options);
await smartbot.initialize();
smartbot.start();
```

### Processamento de Mensagens

```javascript
// Processar mensagem com NLP
const result = await smartbot.processMessage('Olá, quero fazer um pedido', {
  userId: 'user123',
  tone: 'casual'
});

console.log(result.analysis.intent.name); // 'pedido'
console.log(result.analysis.sentiment.sentiment); // 'positive'
console.log(result.response); // Resposta gerada
```

### Sistema de Aprendizado

```javascript
// Feedback positivo
await smartbot.addFeedback('positive', {
  message: 'Quanto custa?',
  response: 'Nossos preços começam em R$50',
  intent: 'preco'
});

// Feedback negativo
await smartbot.addFeedback('negative', {
  message: 'Onde fica?',
  response: 'Não entendi',
  reason: 'Resposta inadequada'
});

// Correção
await smartbot.addFeedback('correction', {
  originalMessage: 'horário?',
  wrongResponse: 'Não sei',
  correctResponse: 'Funcionamos das 9h às 18h',
  correctIntent: 'horario'
});
```

### Cache

```javascript
// O cache é usado automaticamente, mas pode ser acessado diretamente
smartbot.cache.set('chave', 'valor', 300000); // 5 min TTL
const valor = smartbot.cache.get('chave');

// Estatísticas
const stats = smartbot.cache.getStats();
console.log(stats.hitRate); // Taxa de acerto
```

### Filas

```javascript
// Enfileirar mensagem com prioridade
smartbot.queue.enqueue('messages', {
  message: 'Urgente!',
  sentiment: 'negative', // Prioridade automática
  urgency: 'high'
}, {
  priority: 1, // Menor número = maior prioridade
  handler: async (task) => {
    // Processar mensagem
    await sendMessage(task.message);
  }
});

// Verificar status
const status = smartbot.queue.getQueueStatus('messages');
```

### Agendamento

```javascript
// Agendar tarefa diária
smartbot.scheduler.scheduleCron(
  'relatorio_diario',
  async () => {
    const stats = smartbot.getStats();
    console.log('Relatório:', stats);
  },
  '0 18 * * *' // 18:00 todos os dias
);

// Agendar com intervalo
smartbot.scheduler.scheduleInterval(
  'cleanup',
  async () => {
    smartbot.cache.cleanup();
  },
  3600000 // 1 hora
);

// Agendar para horário específico
smartbot.scheduler.scheduleAt(
  'lembrete',
  async () => {
    console.log('Lembrete!');
  },
  new Date('2024-12-20T10:00:00')
);
```

### Diálogos

```javascript
// Criar fluxo de conversa
smartbot.dialogs.createDialog('atendimento', {
  initialState: 'saudacao',
  states: {
    saudacao: {
      message: 'Olá! Como posso ajudar?',
      handler: async (msg, ctx) => {
        ctx.userName = msg;
        return { next: 'menu' };
      },
      transitions: { default: 'menu' }
    },
    menu: {
      message: 'Escolha: 1-Produtos 2-Suporte 3-Vendas',
      handler: async (msg, ctx) => {
        const choice = parseInt(msg);
        if (choice === 1) return { next: 'produtos' };
        if (choice === 2) return { next: 'suporte' };
        if (choice === 3) return { next: 'vendas' };
        return { next: 'menu' }; // Repetir
      }
    },
    produtos: {
      message: 'Aqui estão nossos produtos...',
      transitions: { default: 'end' }
    },
    end: { message: 'Obrigado!' }
  }
});

// Iniciar sessão
smartbot.dialogs.startSession('user123', 'atendimento');

// Processar mensagens do usuário
const response = await smartbot.dialogs.processMessage('user123', 'João');
console.log(response.message); // 'Escolha: 1-Produtos...'
```

### Analytics

```javascript
// Tracking automático, mas pode ser manual
smartbot.analytics.trackEvent('campaign', 'sent', 'whatsapp', 1);
smartbot.analytics.trackMessageSent('user123', 'text');
smartbot.analytics.trackCommand('help', 'user123', true);

// Gerar relatório
const report = smartbot.analytics.generateReport();
console.log(report.metrics.messages.total);

// Exportar
const csv = smartbot.analytics.export('csv');
```

### Webhooks

```javascript
// Registrar webhook
smartbot.webhooks.register('meu_webhook', {
  url: 'https://api.example.com/webhook',
  events: ['message:*', 'campaign:completed'],
  method: 'POST',
  secret: 'my-secret-key', // Para HMAC
  retryAttempts: 3
});

// Enviar evento
await smartbot.webhooks.dispatch('message:sent', {
  message: 'Olá',
  userId: 'user123'
});

// Testar webhook
await smartbot.webhooks.test('meu_webhook');
```

### Notificações

```javascript
// Enviar notificação Chrome
await smartbot.notifications.send('chrome', {
  title: 'Nova Mensagem',
  message: 'Você tem 1 mensagem não lida',
  priority: 2
});

// Broadcast para múltiplos canais
await smartbot.notifications.broadcast({
  title: 'Alerta',
  message: 'Sistema atualizado'
}, ['chrome', 'console', 'event']);

// Subscrever usuário
smartbot.notifications.subscribe('user123', 'chrome');

// Notificar subscritos
await smartbot.notifications.notifySubscribers('chrome', {
  title: 'Promoção',
  message: 'Nova oferta disponível!'
});
```

### Internacionalização

```javascript
// Mudar idioma global
smartbot.locale.setLocale('en');

// Traduzir
const text = smartbot.locale.t('greeting'); // 'Hello'

// Com parâmetros
const msg = smartbot.locale.t('welcome', { name: 'John' });

// Pluralização
const items = smartbot.locale.plural('item', 5, { count: 5 });

// Por usuário
smartbot.locale.setUserLocale('user123', 'es');
const userMsg = smartbot.locale.tu('user123', 'greeting'); // 'Hola'

// Auto-detectar do navegador
smartbot.locale.autoDetect();
```

### Permissões

```javascript
// Atribuir role
smartbot.permissions.assignRole('user123', 'moderator');

// Verificar permissão
if (smartbot.permissions.hasPermission('user123', 'campaign:send')) {
  // Executar ação
}

// Adicionar super admin
smartbot.permissions.addSuperAdmin('admin123');

// Criar role customizada
smartbot.permissions.addRole('custom', {
  permissions: ['message:*', 'user:view'],
  description: 'Role customizada'
});
```

### Plugins

```javascript
// Criar plugin
const meuPlugin = {
  name: 'Meu Plugin',
  version: '1.0.0',
  
  async init(context) {
    console.log('Plugin inicializado');
  },
  
  async enable(context) {
    console.log('Plugin habilitado');
  },
  
  async disable(context) {
    console.log('Plugin desabilitado');
  }
};

// Registrar e habilitar
smartbot.plugins.register('meu-plugin', meuPlugin);
await smartbot.plugins.enable('meu-plugin');

// Hooks
smartbot.plugins.registerHook('meu-plugin', 'message:process', async (data) => {
  console.log('Processando mensagem:', data);
  return data; // Retornar para próximo hook
});

// Executar hooks
const result = await smartbot.plugins.executeHook('message:process', { text: 'Hello' });

// Comandos
smartbot.plugins.registerCommand('meu-plugin', 'hello', async (args, ctx) => {
  return `Hello ${args[0]}!`;
});

await smartbot.plugins.executeCommand('hello', ['World']);
```

### Middleware

```javascript
// Adicionar middleware
smartbot.middleware.use(async (data, context) => {
  console.log('Middleware 1:', data);
  data.processed = true;
  return data;
});

smartbot.middleware.use(async (data, context) => {
  console.log('Middleware 2:', data);
  data.validated = true;
  return data;
}, { priority: 1 }); // Executa primeiro

// Processar
const result = await smartbot.middleware.process({ message: 'Hello' });
```

## 📊 Monitoramento

```javascript
// Estatísticas globais
const stats = smartbot.getStats();
console.log(stats.metrics);
console.log(stats.components.cache);
console.log(stats.components.queue);
console.log(stats.uptime); // Em segundos

// Por componente
console.log(smartbot.cache.getStats());
console.log(smartbot.queue.getStats());
console.log(smartbot.sessions.getStats());
console.log(smartbot.analytics.getMetrics());
```

## 🎯 Exemplos Práticos

### Bot de Atendimento Completo

```javascript
// 1. Inicializar
const smartbot = await initSmartBot();
smartbot.start();

// 2. Configurar NLP
smartbot.nlp.addIntent('saudacao', {
  patterns: ['oi', 'olá', 'bom dia'],
  responses: ['Olá! Como posso ajudar?']
});

smartbot.nlp.addIntent('preco', {
  patterns: ['quanto custa', 'preço', 'valor'],
  responses: ['Nossos preços começam em R$50']
});

// 3. Monitorar mensagens
window.addEventListener('message:received', async (event) => {
  const { message, userId } = event.detail;
  
  // Rate limit
  if (!smartbot.rateLimit.use(`user_${userId}`)) {
    console.log('Rate limit atingido');
    return;
  }
  
  // Processar com NLP
  const result = await smartbot.processMessage(message, { userId });
  
  // Enfileirar resposta
  smartbot.queue.enqueue('responses', {
    userId,
    response: result.response,
    sentiment: result.analysis.sentiment.sentiment
  }, {
    handler: async (task) => {
      // Enviar resposta ao usuário
      await sendWhatsAppMessage(task.userId, task.response);
    }
  });
  
  // Analytics
  smartbot.analytics.trackMessageReceived(userId);
  
  // Webhook
  await smartbot.webhooks.dispatch('message:processed', {
    userId,
    intent: result.analysis.intent.name,
    sentiment: result.analysis.sentiment.sentiment
  });
});
```

## 🔒 Segurança

- ✅ Rate limiting automático
- ✅ Permissões baseadas em roles
- ✅ HMAC para webhooks
- ✅ Validação de dados
- ✅ Logging de erros

## 📝 Notas Importantes

1. **Chrome Storage**: Todos os dados usam `chrome.storage.local` (limite de ~10MB)
2. **Persistência**: Dados são salvos automaticamente
3. **Cleanup**: Execute limpeza periódica para manter performance
4. **Eventos**: Use sistema de eventos para desacoplamento
5. **Logs**: Configure nível apropriado (debug/info/warn/error)

## 🐛 Debugging

```javascript
// Ativar logs debug
smartbot.log.setLevel('debug');

// Ver eventos
const history = smartbot.events.getHistory();

// Ver logs
const logs = smartbot.log.getLogs('error', 50);

// Exportar para análise
const data = smartbot.analytics.export('json');
```

## 🚀 Performance

- Cache reduz processamento redundante
- Filas gerenciam carga
- Rate limiting previne sobrecarga
- Cleanup automático mantém memória controlada

## 📚 Referências

- [README.md](./README.md) - Visão geral dos módulos
- [index.js](./index.js) - Exports de todos os módulos
- JSDoc inline em cada módulo para detalhes de API

---

**Versão:** 1.0.0  
**Atualizado:** 2024-12-17
