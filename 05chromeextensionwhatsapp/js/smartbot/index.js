/**
 * @fileoverview SmartBot IA - Sistema Inteligente Completo
 * @module smartbot
 * 
 * Exporta todos os módulos do SmartBot IA para uso na extensão
 */

// Core
export { LogManager } from './core/log-manager.js';
export { EventManager } from './core/event-manager.js';
export { ConfigManager } from './core/config-manager.js';
export { SmartBotCore, initSmartBot } from './core/smartbot-core.js';

// Infrastructure
export { CacheManager } from './infrastructure/cache-manager.js';
export { QueueManager, IntelligentPriorityQueue } from './infrastructure/queue-manager.js';
export { SessionManager } from './infrastructure/session-manager.js';
export { RateLimitManager } from './infrastructure/rate-limit-manager.js';
export { SchedulerManager } from './infrastructure/scheduler-manager.js';

// NLP
export { 
  NlpManager, 
  AdvancedContextAnalyzer, 
  ContextualResponseGenerator 
} from './nlp/nlp-manager.js';

// Learning
export { 
  ContinuousLearningSystem, 
  FeedbackAnalyzer 
} from './learning/learning-system.js';

// Analytics
export { AnalyticsManager } from './analytics/analytics-manager.js';

// Security
export { PermissionManager } from './security/permission-manager.js';

// Communication
export { WebhookManager } from './communication/webhook-manager.js';
export { NotificationManager } from './communication/notification-manager.js';

// Dialog
export { DialogManager } from './dialog/dialog-manager.js';

// I18n
export { LocaleManager } from './i18n/locale-manager.js';

// Plugins
export { PluginManager, MiddlewareManager } from './plugins/plugin-manager.js';

/**
 * Versão do SmartBot
 */
export const SMARTBOT_VERSION = '1.0.0';

/**
 * Metadados do SmartBot
 */
export const SMARTBOT_INFO = {
  name: 'SmartBot IA',
  version: SMARTBOT_VERSION,
  description: 'Sistema inteligente de automação e processamento de linguagem natural',
  modules: [
    'Core', 'Infrastructure', 'NLP', 'Learning', 'Analytics', 
    'Security', 'Communication', 'Dialog', 'I18n', 'Plugins'
  ],
  features: [
    'Processamento de Linguagem Natural',
    'Aprendizado Contínuo',
    'Análise Contextual',
    'Gerenciamento de Diálogos',
    'Filas Inteligentes',
    'Cache Otimizado',
    'Rate Limiting',
    'Sistema de Permissões',
    'Agendamento de Tarefas',
    'Analytics e Métricas',
    'Webhooks com Retry',
    'Notificações Multi-canal',
    'Internacionalização',
    'Sistema de Plugins',
    'Pipeline de Middleware'
  ]
};

export default {
  version: SMARTBOT_VERSION,
  info: SMARTBOT_INFO
};
