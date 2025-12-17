# SmartBot IA - Sistema Inteligente

Sistema completo de automação e processamento de linguagem natural para WhatsApp.

## 📁 Estrutura de Módulos

```
js/smartbot/
├── core/                      # Módulos principais
│   ├── smartbot-core.js      # Núcleo de integração
│   ├── config-manager.js     # Gerenciamento de configurações
│   ├── log-manager.js        # Sistema de logs
│   └── event-manager.js      # Sistema de eventos pub/sub
│
├── nlp/                       # Processamento de Linguagem Natural
│   └── nlp-manager.js        # NLP com detecção de intenções e entidades
│
├── learning/                  # Aprendizado Contínuo
│   └── learning-system.js    # Sistema de feedback e aprendizado
│
├── infrastructure/            # Infraestrutura
│   ├── cache-manager.js      # Cache LRU/LFU
│   ├── queue-manager.js      # Filas com prioridade e retry
│   ├── session-manager.js    # Gerenciamento de sessões
│   ├── rate-limit-manager.js # Rate limiting
│   └── scheduler-manager.js  # Agendamento de tarefas
│
├── security/                  # Segurança
│   └── permission-manager.js # Sistema de permissões e roles
│
├── dialog/                    # Gerenciamento de Diálogos
│   └── dialog-manager.js     # Fluxos de conversa com estados
│
└── index.js                   # Exporta todos os módulos

```

## 🚀 Inicialização

O SmartBot é inicializado automaticamente 5 segundos após o carregamento da extensão:

```javascript
// API disponível em:
window.wa.smartbot

// Ou via:
window.smartbotCore
```

## 📖 API Pública

### Processar Mensagem

```javascript
const analysis = await window.wa.smartbot.processMessage(
  "Quero fazer um pedido",
  { userId: "user123" }
);

// Retorna:
// {
//   message: "...",
//   intent: "pedido",
//   sentiment: "positive",
//   confidence: 85,
//   processed: true
// }
```

### Adicionar Feedback

```javascript
// Feedback positivo
await window.wa.smartbot.addFeedback('positive', {
  message: "Quanto custa?",
  response: "Nossos preços começam em R$50",
  intent: "preco"
});

// Feedback negativo
await window.wa.smartbot.addFeedback('negative', {
  message: "Onde fica?",
  response: "Desculpe, não entendi",
  reason: "Resposta inadequada"
});

// Correção
await window.wa.smartbot.addFeedback('correction', {
  originalMessage: "Onde fica?",
  wrongResponse: "Não sei",
  correctResponse: "Ficamos na Rua ABC, 123",
  correctIntent: "localizacao"
});
```

### Obter Estatísticas

```javascript
const stats = window.wa.smartbot.getStats();

// Retorna:
// {
//   version: "1.0.0",
//   initialized: true,
//   messagesProcessed: 150,
//   uptime: 3600000
// }
```

## 🧠 Módulos Principais

### 1. NLP Manager
**Arquivo:** `nlp/nlp-manager.js`

Processamento de linguagem natural com:
- Detecção de intenções
- Extração de entidades
- Análise de sentimento
- Gerenciamento de contexto

### 2. Learning System
**Arquivo:** `learning/learning-system.js`

Sistema de aprendizado contínuo:
- Feedback positivo/negativo
- Correções
- Extração automática de padrões
- Otimização da base de conhecimento

### 3. Cache Manager
**Arquivo:** `infrastructure/cache-manager.js`

Cache otimizado com:
- Estratégias LRU (Least Recently Used) ou LFU (Least Frequently Used)
- TTL configurável
- Eviction automática
- Estatísticas de hit/miss rate

### 4. Queue Manager
**Arquivo:** `infrastructure/queue-manager.js`

Sistema de filas inteligentes:
- Priorização de mensagens
- Retry automático com backoff
- Concorrência configurável
- Fila de prioridade inteligente baseada em sentimento

### 5. Session Manager
**Arquivo:** `infrastructure/session-manager.js`

Gerenciamento de sessões:
- Timeout configurável
- Cleanup automático
- Persistência em chrome.storage
- Renovação de sessões

### 6. Rate Limit Manager
**Arquivo:** `infrastructure/rate-limit-manager.js`

Proteção contra abuso:
- Limites por usuário/comando/global
- Bloqueio temporário ou permanente
- Janelas de tempo configuráveis

### 7. Scheduler Manager
**Arquivo:** `infrastructure/scheduler-manager.js`

Agendamento de tarefas:
- Intervalos regulares
- Horários específicos
- Padrões cron simplificados
- Histórico de execuções

### 8. Permission Manager
**Arquivo:** `security/permission-manager.js`

Sistema de permissões:
- Roles (admin, moderator, user)
- Permissões granulares
- Wildcards (`namespace:*`)
- Super admins
- Herança de permissões

### 9. Dialog Manager
**Arquivo:** `dialog/dialog-manager.js`

Fluxos de conversa:
- Estados e transições
- Handlers por estado
- Validação de entrada
- Histórico de conversas
- Contexto persistente

### 10. Config Manager
**Arquivo:** `core/config-manager.js`

Gerenciamento de configurações:
- Validação por schema
- Valores padrão
- Persistência automática
- Callbacks de mudança
- Import/export

### 11. Log Manager
**Arquivo:** `core/log-manager.js`

Sistema de logs:
- Níveis (debug, info, warn, error)
- Formatação consistente
- Persistência opcional
- Métricas de API calls
- Export (JSON, CSV, texto)

### 12. Event Manager
**Arquivo:** `core/event-manager.js`

Sistema de eventos:
- Pub/sub
- Wildcards (`message:*`)
- Prioridade de listeners
- Once listeners
- Histórico de eventos

## 🔧 Configuração

### Configurações Padrão

```javascript
{
  nlp: {
    enabled: true,
    confidenceThreshold: 70,
    contextWindowSize: 5
  },
  autoResponse: {
    enabled: false,
    maxPerHour: 30,
    requiresConfirmation: true
  },
  learning: {
    enabled: true,
    autoOptimize: true
  },
  security: {
    rateLimit: true,
    permissions: true
  }
}
```

## 📊 Integração com content.js

O SmartBot está integrado ao `content.js` e monitora automaticamente:

### Eventos Monitorados

```javascript
// Mensagens recebidas
window.addEventListener('message:received', (event) => {
  // SmartBot processa automaticamente
});

// Análise do SmartBot
window.addEventListener('smartbot:analysis', (event) => {
  console.log('Análise:', event.detail);
});

// Inicialização
window.addEventListener('smartbot:initialized', (event) => {
  console.log('SmartBot inicializado:', event.detail);
});
```

## 🎯 Casos de Uso

### 1. Resposta Automática Inteligente

```javascript
// Configurar intenções
window.wa.smartbot.addIntent('saudacao', {
  patterns: ['oi', 'olá', 'bom dia'],
  responses: ['Olá! Como posso ajudar?', 'Oi! Em que posso ser útil?']
});

// Processar mensagem
const result = await window.wa.smartbot.processMessage('oi');
// result.intent.name === 'saudacao'
```

### 2. Filas Prioritárias

```javascript
// Mensagens com sentimento negativo têm prioridade
window.wa.smartbot.queue.enqueue('messages', {
  message: 'Estou com problema!',
  sentiment: 'negative',
  urgency: 'high'
});
```

### 3. Diálogos Multi-Etapa

```javascript
// Criar fluxo de atendimento
window.wa.smartbot.dialogs.createDialog('pedido', {
  states: {
    inicio: {
      message: 'Que produto você deseja?',
      transitions: { default: 'quantidade' }
    },
    quantidade: {
      message: 'Quantos você quer?',
      transitions: { default: 'confirmacao' }
    },
    confirmacao: {
      message: 'Confirma o pedido?',
      transitions: { default: 'end' }
    }
  }
});

// Iniciar sessão
window.wa.smartbot.dialogs.startSession('user123', 'pedido');
```

### 4. Cache de Respostas

```javascript
// Respostas frequentes são cacheadas automaticamente
const response1 = await processMessage('horário de funcionamento'); // Consulta BD
const response2 = await processMessage('horário de funcionamento'); // Cache (rápido!)
```

### 5. Agendamento de Tarefas

```javascript
// Relatório diário às 18h
window.wa.smartbot.scheduler.scheduleCron(
  'relatorio_diario',
  async () => {
    // Gerar e enviar relatório
  },
  '0 18 * * *' // 18:00 todos os dias
);
```

## 🔐 Segurança

### Rate Limiting

```javascript
// Limitar mensagens por usuário
window.wa.smartbot.rateLimit.addLimit('user_messages', 30, 60000);

// Verificar antes de processar
if (window.wa.smartbot.rateLimit.check('user_123').allowed) {
  // Processar mensagem
}
```

### Permissões

```javascript
// Atribuir role
window.wa.smartbot.permissions.assignRole('user123', 'moderator');

// Verificar permissão
if (window.wa.smartbot.permissions.hasPermission('user123', 'campaign:send')) {
  // Executar ação
}
```

## 📈 Métricas e Monitoramento

```javascript
// Estatísticas globais
const stats = window.wa.smartbot.getStats();

// Estatísticas por componente
const cacheStats = window.wa.smartbot.cache.getStats();
const queueStats = window.wa.smartbot.queue.getStats();
const learningStats = window.wa.smartbot.learning.getFeedbackStats();
```

## 🛠️ Desenvolvimento

### Adicionar Novo Módulo

1. Criar arquivo em `js/smartbot/{categoria}/`
2. Implementar como classe ES6+ com JSDoc
3. Exportar no `index.js`
4. Integrar no `smartbot-core.js` se necessário

### Convenções

- **Prefixo de logs:** `[SmartBot:{ModuleName}]`
- **Storage keys:** `smartbot_{module_name}`
- **Eventos:** `smartbot:{event_name}`
- **Nomenclatura:** camelCase para métodos, PascalCase para classes

## 📝 Licença

Parte do projeto CERTO-WHATSAPPLITE

## 🤝 Contribuindo

Ao adicionar funcionalidades:
1. Documente com JSDoc
2. Siga as convenções de código
3. Adicione testes se disponível
4. Atualize este README

---

**Versão:** 1.0.0  
**Última atualização:** 2024-12-17
