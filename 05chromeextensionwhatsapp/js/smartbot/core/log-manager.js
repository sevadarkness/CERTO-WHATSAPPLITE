/**
 * @fileoverview SmartBot Log Manager - Sistema de logs com níveis e persistência
 * @module smartbot/core/log-manager
 */

/**
 * Sistema de logs com níveis e formatação
 */
export class LogManager {
  /**
   * @param {Object} options - Opções de configuração
   * @param {string} options.prefix - Prefixo para logs
   * @param {boolean} options.persist - Se deve persistir logs
   * @param {number} options.maxLogs - Máximo de logs a manter
   */
  constructor(options = {}) {
    this.prefix = options.prefix || '[SmartBot]';
    this.persist = options.persist || false;
    this.maxLogs = options.maxLogs || 1000;
    this.logs = [];
    this.levels = ['debug', 'info', 'warn', 'error'];
    this.currentLevel = options.level || 'info';
    this.apiMetrics = {
      calls: 0,
      errors: 0,
      totalTime: 0
    };
  }

  /**
   * Log de debug
   */
  debug(...args) {
    if (this._shouldLog('debug')) {
      console.debug(this.prefix, ...args);
      this._store('debug', args);
    }
  }

  /**
   * Log de informação
   */
  info(...args) {
    if (this._shouldLog('info')) {
      console.log(this.prefix, ...args);
      this._store('info', args);
    }
  }

  /**
   * Log de aviso
   */
  warn(...args) {
    if (this._shouldLog('warn')) {
      console.warn(this.prefix, ...args);
      this._store('warn', args);
    }
  }

  /**
   * Log de erro
   */
  error(...args) {
    if (this._shouldLog('error')) {
      console.error(this.prefix, ...args);
      this._store('error', args);
    }
  }

  /**
   * Registrar chamada de API
   */
  logApiCall(endpoint, duration, success = true) {
    this.apiMetrics.calls++;
    this.apiMetrics.totalTime += duration;
    if (!success) this.apiMetrics.errors++;
    
    this.debug(`API Call: ${endpoint} (${duration}ms) ${success ? '✓' : '✗'}`);
  }

  /**
   * Obter métricas de API
   */
  getApiMetrics() {
    return {
      ...this.apiMetrics,
      avgTime: this.apiMetrics.calls > 0 
        ? (this.apiMetrics.totalTime / this.apiMetrics.calls).toFixed(2) 
        : 0,
      errorRate: this.apiMetrics.calls > 0
        ? ((this.apiMetrics.errors / this.apiMetrics.calls) * 100).toFixed(2)
        : 0
    };
  }

  /**
   * Mudar nível de log
   */
  setLevel(level) {
    if (this.levels.includes(level)) {
      this.currentLevel = level;
      this.info(`Log level changed to: ${level}`);
    }
  }

  /**
   * Obter logs armazenados
   */
  getLogs(level = null, limit = 100) {
    let filtered = level 
      ? this.logs.filter(log => log.level === level)
      : this.logs;
    
    return filtered.slice(-limit);
  }

  /**
   * Limpar logs
   */
  clearLogs() {
    this.logs = [];
    this.info('Logs cleared');
  }

  /**
   * Verificar se deve logar
   * @private
   */
  _shouldLog(level) {
    const currentIndex = this.levels.indexOf(this.currentLevel);
    const levelIndex = this.levels.indexOf(level);
    return levelIndex >= currentIndex;
  }

  /**
   * Armazenar log
   * @private
   */
  _store(level, args) {
    if (!this.persist) return;

    const log = {
      level,
      message: args.map(a => typeof a === 'object' ? JSON.stringify(a) : String(a)).join(' '),
      timestamp: Date.now()
    };

    this.logs.push(log);

    // Manter apenas maxLogs
    if (this.logs.length > this.maxLogs) {
      this.logs = this.logs.slice(-this.maxLogs);
    }
  }

  /**
   * Exportar logs
   */
  export(format = 'json') {
    switch (format) {
      case 'json':
        return JSON.stringify(this.logs, null, 2);
      case 'csv':
        return this._toCSV();
      case 'text':
        return this._toText();
      default:
        return this.logs;
    }
  }

  /**
   * Converter para CSV
   * @private
   */
  _toCSV() {
    const lines = ['Level,Message,Timestamp'];
    this.logs.forEach(log => {
      lines.push(`${log.level},"${log.message.replace(/"/g, '""')}",${log.timestamp}`);
    });
    return lines.join('\n');
  }

  /**
   * Converter para texto
   * @private
   */
  _toText() {
    return this.logs.map(log => {
      const date = new Date(log.timestamp).toISOString();
      return `[${date}] [${log.level.toUpperCase()}] ${log.message}`;
    }).join('\n');
  }
}
