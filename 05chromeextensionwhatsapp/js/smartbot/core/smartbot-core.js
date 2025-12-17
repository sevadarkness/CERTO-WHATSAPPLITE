/**
 * @fileoverview SmartBot Core - Núcleo principal do SmartBot IA
 * @module smartbot/core/smartbot-core
 */

import { LogManager } from './log-manager.js';
import { EventManager } from './event-manager.js';
import { ConfigManager } from './config-manager.js';
import { CacheManager } from '../infrastructure/cache-manager.js';
import { QueueManager, IntelligentPriorityQueue } from '../infrastructure/queue-manager.js';
import { SessionManager } from '../infrastructure/session-manager.js';
import { RateLimitManager } from '../infrastructure/rate-limit-manager.js';
import { SchedulerManager } from '../infrastructure/scheduler-manager.js';
import { NlpManager, AdvancedContextAnalyzer, ContextualResponseGenerator } from '../nlp/nlp-manager.js';
import { ContinuousLearningSystem, FeedbackAnalyzer } from '../learning/learning-system.js';
import { PermissionManager } from '../security/permission-manager.js';
import { DialogManager } from '../dialog/dialog-manager.js';

/**
 * SmartBot Core - Sistema inteligente integrado
 */
export class SmartBotCore {
  constructor(options = {}) {
    this.options = options;
    this.isInitialized = false;
    this.isActive = false;
    
    // Inicializar componentes core
    this.log = new LogManager({
      prefix: '[SmartBot]',
      persist: options.persistLogs || false,
      level: options.logLevel || 'info'
    });

    this.events = new EventManager();
    this.config = new ConfigManager({ storageKey: 'smartbot_config' });
    
    // Infraestrutura
    this.cache = new CacheManager({
      strategy: options.cacheStrategy || 'lru',
      maxSize: options.cacheSize || 100
    });

    this.queue = new IntelligentPriorityQueue({
      concurrency: options.concurrency || 1,
      retryAttempts: options.retryAttempts || 3
    });

    this.sessions = new SessionManager({
      defaultTimeout: options.sessionTimeout || 1800000
    });

    this.rateLimit = new RateLimitManager();
    this.scheduler = new SchedulerManager();

    // NLP & Learning
    this.nlp = new NlpManager();
    this.contextAnalyzer = new AdvancedContextAnalyzer();
    this.responseGenerator = new ContextualResponseGenerator();
    this.learning = new ContinuousLearningSystem();
    this.feedbackAnalyzer = new FeedbackAnalyzer();

    // Segurança & Diálogo
    this.permissions = new PermissionManager();
    this.dialogs = new DialogManager();

    // Métricas
    this.metrics = {
      messagesProcessed: 0,
      responsesGenerated: 0,
      errors: 0,
      avgResponseTime: 0,
      uptime: 0
    };

    this.startTime = null;
  }

  /**
   * Inicializar SmartBot
   */
  async initialize() {
    if (this.isInitialized) {
      this.log.warn('SmartBot already initialized');
      return this;
    }

    this.log.info('Initializing SmartBot Core...');

    try {
      // Configurar padrões
      this._setupDefaults();

      // Carregar configurações
      await this.config.load();
      
      // Carregar dados persistidos
      await this.sessions.load();
      await this.learning.load();

      // Configurar rate limits padrão
      this._setupRateLimits();

      // Configurar listeners de eventos
      this._setupEventListeners();

      // Configurar diálogos padrão
      this._setupDefaultDialogs();

      this.isInitialized = true;
      this.startTime = Date.now();

      this.log.info('SmartBot Core initialized successfully');
      this.events.emit('smartbot:initialized', { timestamp: Date.now() });

      return this;

    } catch (error) {
      this.log.error('Initialization failed:', error);
      throw error;
    }
  }

  /**
   * Configurar valores padrão
   * @private
   */
  _setupDefaults() {
    this.config.setDefaults({
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
    });

    // Schema de validação
    this.config.setSchema({
      'nlp.confidenceThreshold': {
        type: 'number',
        min: 0,
        max: 100
      },
      'autoResponse.maxPerHour': {
        type: 'number',
        min: 1,
        max: 100
      }
    });
  }

  /**
   * Configurar rate limits
   * @private
   */
  _setupRateLimits() {
    this.rateLimit.addLimit('global', 100, 60000); // 100 req/min
    this.rateLimit.addLimit('user_message', 30, 60000); // 30 msg/min por usuário
  }

  /**
   * Configurar event listeners
   * @private
   */
  _setupEventListeners() {
    // Log de eventos importantes
    this.events.on('message:*', (data, event) => {
      this.log.debug(`Event: ${event}`, data);
    });

    this.events.on('error:*', (data, event) => {
      this.log.error(`Error event: ${event}`, data);
      this.metrics.errors++;
    });
  }

  /**
   * Configurar diálogos padrão
   * @private
   */
  _setupDefaultDialogs() {
    // Exemplo de diálogo de boas-vindas
    this.dialogs.createDialog('welcome', {
      name: 'Boas-vindas',
      initialState: 'greet',
      states: {
        greet: {
          message: 'Olá! Como posso ajudar?',
          handler: async (msg, ctx) => {
            ctx.userName = msg;
            return { next: 'ask_need' };
          },
          transitions: {
            default: 'ask_need'
          }
        },
        ask_need: {
          message: 'O que você precisa hoje?',
          handler: async (msg, ctx) => {
            const analysis = await this.nlp.process(msg);
            ctx.intent = analysis.intent.name;
            return { next: 'end' };
          },
          transitions: {
            default: 'end'
          }
        },
        end: {
          message: 'Obrigado! Vou processar sua solicitação.'
        }
      }
    });
  }

  /**
   * Processar mensagem recebida
   */
  async processMessage(message, context = {}) {
    const startTime = Date.now();
    
    try {
      // Verificar rate limit
      const rateLimitKey = `user_message_${context.userId || 'unknown'}`;
      if (!this.rateLimit.use(rateLimitKey)) {
        throw new Error('Rate limit exceeded');
      }

      // Verificar permissões
      if (context.userId && !this.permissions.hasPermission(context.userId, 'message:process')) {
        throw new Error('Permission denied');
      }

      // Verificar cache
      const cacheKey = `message_${this._hash(message)}`;
      const cached = this.cache.get(cacheKey);
      if (cached) {
        this.log.debug('Returning cached response');
        return cached;
      }

      // Processar com NLP
      const analysis = await this.nlp.process(message, context);

      // Atualizar contexto
      if (context.userId) {
        this.contextAnalyzer.analyzeProfile(context.userId, analysis);
        this.contextAnalyzer.addToHistory(context.userId, message, analysis);
      }

      // Gerar resposta
      const response = await this._generateResponse(analysis, context);

      // Cachear resultado
      this.cache.set(cacheKey, { analysis, response }, 300000);

      // Atualizar métricas
      this.metrics.messagesProcessed++;
      this.metrics.responsesGenerated++;
      const responseTime = Date.now() - startTime;
      this.metrics.avgResponseTime = 
        (this.metrics.avgResponseTime * (this.metrics.messagesProcessed - 1) + responseTime) 
        / this.metrics.messagesProcessed;

      // Emitir evento
      this.events.emit('message:processed', {
        message,
        analysis,
        response,
        responseTime
      });

      return {
        analysis,
        response,
        responseTime,
        cached: false
      };

    } catch (error) {
      this.log.error('Error processing message:', error);
      this.metrics.errors++;
      this.events.emit('error:message_processing', { message, error: error.message });
      
      return {
        error: error.message,
        fallback: 'Desculpe, tive um problema ao processar sua mensagem.'
      };
    }
  }

  /**
   * Gerar resposta
   * @private
   */
  async _generateResponse(analysis, context) {
    // Verificar se há diálogo ativo
    if (context.userId) {
      const session = this.dialogs.getSession(context.userId);
      if (session) {
        const result = await this.dialogs.processMessage(context.userId, analysis.originalText);
        if (result.message) {
          return result.message;
        }
      }
    }

    // Buscar padrão aprendido
    const learnedPattern = this.learning.findSimilarPattern(analysis.originalText);
    if (learnedPattern && learnedPattern.confidence > 70) {
      this.log.debug('Using learned pattern');
      return this.responseGenerator.generate(
        learnedPattern.pattern.intent,
        context,
        context.tone || 'casual'
      );
    }

    // Gerar resposta baseada em intenção
    if (analysis.intent && analysis.intent.name !== 'unknown') {
      return this.responseGenerator.generate(
        analysis.intent.name,
        context,
        context.tone || 'casual'
      );
    }

    // Fallback
    return 'Desculpe, não entendi. Pode reformular sua pergunta?';
  }

  /**
   * Adicionar feedback
   */
  async addFeedback(messageId, type, data = {}) {
    try {
      if (type === 'positive') {
        await this.learning.addPositiveFeedback(
          data.message,
          data.response,
          data.intent
        );
      } else if (type === 'negative') {
        await this.learning.addNegativeFeedback(
          data.message,
          data.response,
          data.reason
        );
      } else if (type === 'correction') {
        await this.learning.addCorrection(
          data.originalMessage,
          data.wrongResponse,
          data.correctResponse,
          data.correctIntent
        );
      }

      this.events.emit('feedback:added', { messageId, type, data });
      return true;

    } catch (error) {
      this.log.error('Error adding feedback:', error);
      return false;
    }
  }

  /**
   * Ativar SmartBot
   */
  start(options = {}) {
    if (!this.isInitialized) {
      throw new Error('SmartBot not initialized. Call initialize() first.');
    }

    if (this.isActive) {
      this.log.warn('SmartBot already active');
      return this;
    }

    this.isActive = true;
    this.config.set(options);
    
    this.log.info('SmartBot activated');
    this.events.emit('smartbot:started', { timestamp: Date.now(), options });

    return this;
  }

  /**
   * Desativar SmartBot
   */
  stop() {
    if (!this.isActive) {
      this.log.warn('SmartBot already inactive');
      return this;
    }

    this.isActive = false;
    
    this.log.info('SmartBot deactivated');
    this.events.emit('smartbot:stopped', { timestamp: Date.now() });

    return this;
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    const uptime = this.startTime ? Date.now() - this.startTime : 0;

    return {
      isActive: this.isActive,
      isInitialized: this.isInitialized,
      uptime: Math.floor(uptime / 1000),
      metrics: { ...this.metrics },
      components: {
        cache: this.cache.getStats(),
        queue: this.queue.getStats(),
        sessions: this.sessions.getStats(),
        nlp: this.nlp.getStats(),
        learning: this.learning.getFeedbackStats(),
        dialogs: this.dialogs.getStats()
      }
    };
  }

  /**
   * Hash simples para cache
   * @private
   */
  _hash(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash;
    }
    return hash.toString(36);
  }

  /**
   * Cleanup e shutdown
   */
  async shutdown() {
    this.log.info('Shutting down SmartBot...');

    this.stop();
    this.scheduler.clear();
    this.sessions.stopCleanup();

    // Salvar estado
    await this.config.persist();
    await this.learning._persist();

    this.log.info('SmartBot shutdown complete');
    this.events.emit('smartbot:shutdown', { timestamp: Date.now() });
  }
}

/**
 * Criar instância singleton do SmartBot
 */
let smartBotInstance = null;

export async function initSmartBot(options = {}) {
  if (smartBotInstance) {
    console.log('[SmartBot] Already initialized, returning existing instance');
    return smartBotInstance;
  }

  const smartbot = new SmartBotCore(options);
  await smartbot.initialize();
  
  smartBotInstance = smartbot;
  
  console.log(`
╔═══════════════════════════════════════════════════════════╗
║          SMARTBOT IA - SISTEMA INTELIGENTE 🧠             ║
╚═══════════════════════════════════════════════════════════╝

✅ SmartBot Core initialized successfully!

📦 Loaded Components:
   • NLP Manager - Processamento de linguagem natural
   • Learning System - Aprendizado contínuo
   • Context Analyzer - Análise contextual avançada
   • Dialog Manager - Gerenciamento de fluxos
   • Queue Manager - Filas inteligentes com prioridade
   • Cache Manager - Cache LRU/LFU
   • Session Manager - Gerenciamento de sessões
   • Permission Manager - Controle de acesso
   • Rate Limit Manager - Proteção contra abuso
   • Scheduler Manager - Tarefas agendadas

🚀 Ready to use! Access via window.smartbot
  `);

  return smartbot;
}

export default SmartBotCore;
