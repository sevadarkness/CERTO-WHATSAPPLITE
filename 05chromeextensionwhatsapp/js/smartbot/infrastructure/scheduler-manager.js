/**
 * @fileoverview SmartBot Scheduler Manager - Tarefas agendadas
 * @module smartbot/infrastructure/scheduler-manager
 */

/**
 * Sistema de agendamento de tarefas com suporte a cron
 */
export class SchedulerManager {
  constructor() {
    this.tasks = new Map();
    this.timers = new Map();
    this.history = [];
    this.maxHistory = 100;
  }

  /**
   * Agendar tarefa com intervalo
   * @param {string} taskId - ID da tarefa
   * @param {Function} handler - Handler a executar
   * @param {number} interval - Intervalo em ms
   * @param {Object} options - Opções
   */
  scheduleInterval(taskId, handler, interval, options = {}) {
    const task = {
      id: taskId,
      type: 'interval',
      handler,
      interval,
      enabled: true,
      createdAt: Date.now(),
      lastRun: null,
      nextRun: Date.now() + interval,
      runCount: 0,
      options
    };

    this.tasks.set(taskId, task);
    this._startInterval(taskId);
    
    return taskId;
  }

  /**
   * Agendar tarefa para horário específico
   * @param {string} taskId - ID da tarefa
   * @param {Function} handler - Handler a executar
   * @param {Date|string} time - Horário
   * @param {Object} options - Opções
   */
  scheduleAt(taskId, handler, time, options = {}) {
    const scheduledTime = typeof time === 'string' ? new Date(time) : time;
    const delay = scheduledTime.getTime() - Date.now();

    if (delay <= 0) {
      throw new Error('Scheduled time must be in the future');
    }

    const task = {
      id: taskId,
      type: 'once',
      handler,
      scheduledAt: scheduledTime.getTime(),
      enabled: true,
      createdAt: Date.now(),
      nextRun: scheduledTime.getTime(),
      runCount: 0,
      options
    };

    this.tasks.set(taskId, task);
    this._startTimeout(taskId, delay);
    
    return taskId;
  }

  /**
   * Agendar com padrão cron simplificado
   * @param {string} taskId - ID da tarefa
   * @param {Function} handler - Handler
   * @param {string} pattern - Padrão: '0 9 * * *' (9h todos os dias)
   */
  scheduleCron(taskId, handler, pattern, options = {}) {
    const task = {
      id: taskId,
      type: 'cron',
      handler,
      pattern,
      enabled: true,
      createdAt: Date.now(),
      lastRun: null,
      nextRun: this._getNextCronTime(pattern),
      runCount: 0,
      options
    };

    this.tasks.set(taskId, task);
    this._scheduleCronTask(taskId);
    
    return taskId;
  }

  /**
   * Cancelar tarefa
   */
  cancel(taskId) {
    const task = this.tasks.get(taskId);
    if (!task) return false;

    // Limpar timer
    if (this.timers.has(taskId)) {
      const timer = this.timers.get(taskId);
      clearTimeout(timer);
      clearInterval(timer);
      this.timers.delete(taskId);
    }

    this.tasks.delete(taskId);
    return true;
  }

  /**
   * Pausar tarefa
   */
  pause(taskId) {
    const task = this.tasks.get(taskId);
    if (!task) return false;

    task.enabled = false;
    
    // Limpar timer mas manter tarefa
    if (this.timers.has(taskId)) {
      const timer = this.timers.get(taskId);
      clearTimeout(timer);
      clearInterval(timer);
      this.timers.delete(taskId);
    }

    return true;
  }

  /**
   * Retomar tarefa
   */
  resume(taskId) {
    const task = this.tasks.get(taskId);
    if (!task) return false;

    task.enabled = true;

    if (task.type === 'interval') {
      this._startInterval(taskId);
    } else if (task.type === 'cron') {
      this._scheduleCronTask(taskId);
    }

    return true;
  }

  /**
   * Executar tarefa imediatamente
   */
  async runNow(taskId) {
    const task = this.tasks.get(taskId);
    if (!task) return false;

    await this._executeTask(task);
    return true;
  }

  /**
   * Obter lista de tarefas
   */
  getTasks() {
    return Array.from(this.tasks.values()).map(task => ({
      id: task.id,
      type: task.type,
      enabled: task.enabled,
      lastRun: task.lastRun,
      nextRun: task.nextRun,
      runCount: task.runCount
    }));
  }

  /**
   * Obter histórico
   */
  getHistory(limit = 50) {
    return this.history.slice(-limit);
  }

  /**
   * Iniciar intervalo
   * @private
   */
  _startInterval(taskId) {
    const task = this.tasks.get(taskId);
    if (!task || !task.enabled) return;

    const timer = setInterval(async () => {
      if (task.enabled) {
        await this._executeTask(task);
      }
    }, task.interval);

    this.timers.set(taskId, timer);
  }

  /**
   * Iniciar timeout
   * @private
   */
  _startTimeout(taskId, delay) {
    const task = this.tasks.get(taskId);
    if (!task || !task.enabled) return;

    const timer = setTimeout(async () => {
      await this._executeTask(task);
      this.tasks.delete(taskId);
      this.timers.delete(taskId);
    }, delay);

    this.timers.set(taskId, timer);
  }

  /**
   * Agendar tarefa cron
   * @private
   */
  _scheduleCronTask(taskId) {
    const task = this.tasks.get(taskId);
    if (!task || !task.enabled) return;

    const now = Date.now();
    const delay = task.nextRun - now;

    if (delay <= 0) {
      task.nextRun = this._getNextCronTime(task.pattern);
      this._scheduleCronTask(taskId);
      return;
    }

    const timer = setTimeout(async () => {
      await this._executeTask(task);
      task.nextRun = this._getNextCronTime(task.pattern);
      this._scheduleCronTask(taskId);
    }, delay);

    this.timers.set(taskId, timer);
  }

  /**
   * Executar tarefa
   * @private
   */
  async _executeTask(task) {
    const startTime = Date.now();
    
    try {
      await task.handler();
      
      task.lastRun = startTime;
      task.runCount++;

      this._addHistory({
        taskId: task.id,
        success: true,
        executedAt: startTime,
        duration: Date.now() - startTime
      });

    } catch (error) {
      this._addHistory({
        taskId: task.id,
        success: false,
        error: error.message,
        executedAt: startTime,
        duration: Date.now() - startTime
      });
    }
  }

  /**
   * Adicionar ao histórico
   * @private
   */
  _addHistory(entry) {
    this.history.push(entry);
    if (this.history.length > this.maxHistory) {
      this.history.shift();
    }
  }

  /**
   * Calcular próximo tempo cron (simplificado)
   * @private
   */
  _getNextCronTime(pattern) {
    // Padrão simplificado: 'minute hour * * *'
    // Exemplo: '30 9 * * *' = 9:30 todos os dias
    const parts = pattern.split(' ');
    const [minute, hour] = parts.map(p => parseInt(p));

    const now = new Date();
    const next = new Date();
    next.setHours(hour, minute, 0, 0);

    // Se já passou hoje, agendar para amanhã
    if (next <= now) {
      next.setDate(next.getDate() + 1);
    }

    return next.getTime();
  }

  /**
   * Limpar tudo
   */
  clear() {
    for (const taskId of this.tasks.keys()) {
      this.cancel(taskId);
    }
  }
}
