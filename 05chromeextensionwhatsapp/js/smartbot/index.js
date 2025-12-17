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

// Security
export { PermissionManager } from './security/permission-manager.js';

// Dialog
export { DialogManager } from './dialog/dialog-manager.js';

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
    'Core', 'Infrastructure', 'NLP', 'Learning', 'Security', 'Dialog'
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
    'Agendamento de Tarefas'
  ]
};

export default {
  version: SMARTBOT_VERSION,
  info: SMARTBOT_INFO
};
