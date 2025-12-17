/**
 * @fileoverview SmartBot Queue Manager - Sistema de filas com prioridade
 * @module smartbot/infrastructure/queue-manager
 */

/**
 * Sistema de filas com prioridade e retry automático
 */
export class QueueManager {
  constructor(options = {}) {
    this.concurrency = options.concurrency || 1;
    this.retryAttempts = options.retryAttempts || 3;
    this.retryDelay = options.retryDelay || 1000;
    this.queues = new Map();
    this.processing = new Set();
    this.stats = {
      total: 0,
      processed: 0,
      failed: 0,
      retried: 0
    };
  }

  /**
   * Adicionar item à fila
   * @param {string} queueName - Nome da fila
   * @param {*} task - Tarefa a executar
   * @param {Object} options - Opções
   * @param {number} options.priority - Prioridade (menor = maior prioridade)
   * @param {Function} options.handler - Handler para processar
   * @param {number} options.retryAttempts - Tentativas de retry
   */
  enqueue(queueName, task, options = {}) {
    if (!this.queues.has(queueName)) {
      this.queues.set(queueName, []);
    }

    const item = {
      id: `${Date.now()}_${Math.random().toString(36).slice(2, 11)}`,
      task,
      priority: options.priority || 5,
      handler: options.handler,
      retryAttempts: options.retryAttempts || this.retryAttempts,
      currentAttempt: 0,
      enqueuedAt: Date.now(),
      status: 'queued'
    };

    const queue = this.queues.get(queueName);
    queue.push(item);
    
    // Ordenar por prioridade
    queue.sort((a, b) => a.priority - b.priority);

    this.stats.total++;
    this._processQueue(queueName);

    return item.id;
  }

  /**
   * Processar fila
   * @private
   */
  async _processQueue(queueName) {
    const queue = this.queues.get(queueName);
    if (!queue || queue.length === 0) return;

    // Verificar concorrência
    const processingCount = Array.from(this.processing).filter(
      id => id.startsWith(queueName)
    ).length;

    if (processingCount >= this.concurrency) return;

    const item = queue.shift();
    if (!item) return;

    const processingId = `${queueName}:${item.id}`;
    this.processing.add(processingId);
    item.status = 'processing';
    item.startedAt = Date.now();

    try {
      if (!item.handler || typeof item.handler !== 'function') {
        throw new Error('No handler function provided');
      }

      await item.handler(item.task);
      
      item.status = 'completed';
      item.completedAt = Date.now();
      this.stats.processed++;
      
    } catch (error) {
      item.currentAttempt++;
      item.lastError = error.message;

      if (item.currentAttempt < item.retryAttempts) {
        // Retry com backoff exponencial
        item.status = 'retrying';
        this.stats.retried++;
        
        const delay = this.retryDelay * Math.pow(2, item.currentAttempt - 1);
        await this._sleep(delay);
        
        // Re-enqueue
        queue.unshift(item);
      } else {
        item.status = 'failed';
        item.failedAt = Date.now();
        this.stats.failed++;
      }
    } finally {
      this.processing.delete(processingId);
      
      // Continuar processando
      if (queue.length > 0) {
        this._processQueue(queueName);
      }
    }
  }

  /**
   * Obter status da fila
   */
  getQueueStatus(queueName) {
    const queue = this.queues.get(queueName);
    if (!queue) return null;

    const processing = Array.from(this.processing).filter(
      id => id.startsWith(queueName)
    ).length;

    return {
      name: queueName,
      queued: queue.length,
      processing,
      total: this.stats.total
    };
  }

  /**
   * Obter todas as filas
   */
  getAllQueues() {
    const queues = [];
    for (const [name, queue] of this.queues.entries()) {
      queues.push({
        name,
        size: queue.length,
        items: queue.map(item => ({
          id: item.id,
          priority: item.priority,
          status: item.status,
          attempts: item.currentAttempt
        }))
      });
    }
    return queues;
  }

  /**
   * Pausar fila
   */
  pause(queueName) {
    const queue = this.queues.get(queueName);
    if (queue) {
      queue.forEach(item => {
        if (item.status === 'queued') {
          item.status = 'paused';
        }
      });
    }
  }

  /**
   * Retomar fila
   */
  resume(queueName) {
    const queue = this.queues.get(queueName);
    if (queue) {
      queue.forEach(item => {
        if (item.status === 'paused') {
          item.status = 'queued';
        }
      });
      this._processQueue(queueName);
    }
  }

  /**
   * Limpar fila
   */
  clear(queueName) {
    if (queueName) {
      this.queues.delete(queueName);
    } else {
      this.queues.clear();
    }
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    const successRate = this.stats.processed + this.stats.failed > 0
      ? (this.stats.processed / (this.stats.processed + this.stats.failed) * 100).toFixed(2)
      : 0;

    return {
      ...this.stats,
      successRate: `${successRate}%`,
      queues: this.queues.size,
      processing: this.processing.size
    };
  }

  /**
   * Sleep helper
   * @private
   */
  _sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

/**
 * Fila inteligente com priorização baseada em sentimento e urgência
 */
export class IntelligentPriorityQueue extends QueueManager {
  constructor(options = {}) {
    super(options);
    this.priorityRules = new Map();
    this._setupDefaultRules();
  }

  /**
   * Configurar regras padrão de priorização
   * @private
   */
  _setupDefaultRules() {
    // Sentimento negativo = maior prioridade
    this.priorityRules.set('negative_sentiment', (task) => {
      if (task.sentiment === 'negative') return 1;
      return null;
    });

    // Urgência alta
    this.priorityRules.set('high_urgency', (task) => {
      if (task.urgency === 'high') return 2;
      return null;
    });

    // VIP
    this.priorityRules.set('vip_customer', (task) => {
      if (task.isVip) return 3;
      return null;
    });
  }

  /**
   * Adicionar regra de priorização
   */
  addPriorityRule(name, rule) {
    this.priorityRules.set(name, rule);
  }

  /**
   * Calcular prioridade baseada em regras
   */
  calculatePriority(task) {
    let priority = task.priority || 5;

    for (const [name, rule] of this.priorityRules.entries()) {
      const rulePriority = rule(task);
      if (rulePriority !== null && rulePriority < priority) {
        priority = rulePriority;
      }
    }

    return priority;
  }

  /**
   * Enqueue com priorização inteligente
   */
  enqueue(queueName, task, options = {}) {
    const priority = this.calculatePriority(task);
    return super.enqueue(queueName, task, { ...options, priority });
  }
}
