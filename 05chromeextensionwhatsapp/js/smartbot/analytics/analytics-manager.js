/**
 * @fileoverview SmartBot Analytics Manager - Sistema de analytics e tracking
 * @module smartbot/analytics/analytics-manager
 */

/**
 * Gerenciador de analytics com tracking de eventos e métricas
 */
export class AnalyticsManager {
  constructor(options = {}) {
    this.storageKey = options.storageKey || 'smartbot_analytics';
    this.maxEvents = options.maxEvents || 10000;
    this.events = [];
    this.metrics = {
      messages: { sent: 0, received: 0, failed: 0 },
      users: { active: new Set(), total: 0 },
      commands: {},
      sessions: { total: 0, avg_duration: 0 },
      errors: []
    };
    this.startTime = Date.now();
  }

  /**
   * Registrar evento
   */
  trackEvent(category, action, label = null, value = null, metadata = {}) {
    const event = {
      category,
      action,
      label,
      value,
      metadata,
      timestamp: Date.now(),
      sessionId: this._getCurrentSessionId()
    };

    this.events.push(event);

    // Limitar tamanho
    if (this.events.length > this.maxEvents) {
      this.events = this.events.slice(-this.maxEvents);
    }

    // Atualizar métricas baseadas no evento
    this._updateMetricsFromEvent(event);

    this._persist();
    return event;
  }

  /**
   * Registrar mensagem enviada
   */
  trackMessageSent(userId, messageType = 'text', metadata = {}) {
    this.metrics.messages.sent++;
    this.metrics.users.active.add(userId);
    
    return this.trackEvent('message', 'sent', messageType, 1, {
      userId,
      ...metadata
    });
  }

  /**
   * Registrar mensagem recebida
   */
  trackMessageReceived(userId, messageType = 'text', metadata = {}) {
    this.metrics.messages.received++;
    this.metrics.users.active.add(userId);
    
    return this.trackEvent('message', 'received', messageType, 1, {
      userId,
      ...metadata
    });
  }

  /**
   * Registrar comando executado
   */
  trackCommand(command, userId, success = true, metadata = {}) {
    if (!this.metrics.commands[command]) {
      this.metrics.commands[command] = { count: 0, success: 0, failed: 0 };
    }

    this.metrics.commands[command].count++;
    if (success) {
      this.metrics.commands[command].success++;
    } else {
      this.metrics.commands[command].failed++;
    }

    return this.trackEvent('command', command, success ? 'success' : 'failed', 1, {
      userId,
      ...metadata
    });
  }

  /**
   * Registrar erro
   */
  trackError(error, context = {}) {
    const errorEntry = {
      message: error.message || String(error),
      stack: error.stack,
      context,
      timestamp: Date.now()
    };

    this.metrics.errors.push(errorEntry);

    // Manter apenas últimos 100 erros
    if (this.metrics.errors.length > 100) {
      this.metrics.errors.shift();
    }

    return this.trackEvent('error', 'occurred', error.message, 1, context);
  }

  /**
   * Registrar sessão
   */
  trackSession(userId, duration, metadata = {}) {
    this.metrics.sessions.total++;
    
    // Atualizar duração média
    const currentTotal = this.metrics.sessions.avg_duration * (this.metrics.sessions.total - 1);
    this.metrics.sessions.avg_duration = (currentTotal + duration) / this.metrics.sessions.total;

    return this.trackEvent('session', 'ended', null, duration, {
      userId,
      ...metadata
    });
  }

  /**
   * Obter métricas
   */
  getMetrics() {
    return {
      messages: {
        ...this.metrics.messages,
        total: this.metrics.messages.sent + this.metrics.messages.received
      },
      users: {
        active: this.metrics.users.active.size,
        total: this.metrics.users.total
      },
      commands: { ...this.metrics.commands },
      sessions: { ...this.metrics.sessions },
      errors: {
        count: this.metrics.errors.length,
        recent: this.metrics.errors.slice(-10)
      },
      uptime: Date.now() - this.startTime
    };
  }

  /**
   * Obter eventos
   */
  getEvents(filter = {}) {
    let filtered = this.events;

    if (filter.category) {
      filtered = filtered.filter(e => e.category === filter.category);
    }

    if (filter.action) {
      filtered = filtered.filter(e => e.action === filter.action);
    }

    if (filter.startTime) {
      filtered = filtered.filter(e => e.timestamp >= filter.startTime);
    }

    if (filter.endTime) {
      filtered = filtered.filter(e => e.timestamp <= filter.endTime);
    }

    const limit = filter.limit || 100;
    return filtered.slice(-limit);
  }

  /**
   * Obter relatório
   */
  generateReport(startTime = null, endTime = null) {
    const start = startTime || this.startTime;
    const end = endTime || Date.now();
    
    const events = this.getEvents({ startTime: start, endTime: end });
    
    // Agrupar por categoria
    const byCategory = {};
    for (const event of events) {
      if (!byCategory[event.category]) {
        byCategory[event.category] = [];
      }
      byCategory[event.category].push(event);
    }

    // Calcular estatísticas
    const stats = {
      period: {
        start: new Date(start).toISOString(),
        end: new Date(end).toISOString(),
        duration: end - start
      },
      events: {
        total: events.length,
        byCategory: Object.entries(byCategory).map(([category, events]) => ({
          category,
          count: events.length
        }))
      },
      metrics: this.getMetrics()
    };

    return stats;
  }

  /**
   * Exportar dados
   */
  export(format = 'json') {
    const data = {
      metrics: this.getMetrics(),
      events: this.events,
      exportedAt: new Date().toISOString()
    };

    switch (format) {
      case 'json':
        return JSON.stringify(data, null, 2);
      case 'csv':
        return this._toCSV(data.events);
      default:
        return data;
    }
  }

  /**
   * Converter eventos para CSV
   * @private
   */
  _toCSV(events) {
    const lines = ['Category,Action,Label,Value,Timestamp'];
    
    for (const event of events) {
      lines.push([
        event.category,
        event.action,
        event.label || '',
        event.value || '',
        new Date(event.timestamp).toISOString()
      ].join(','));
    }

    return lines.join('\n');
  }

  /**
   * Atualizar métricas baseadas em evento
   * @private
   */
  _updateMetricsFromEvent(event) {
    // Atualizar contagem de usuários totais
    if (event.metadata && event.metadata.userId) {
      this.metrics.users.total = Math.max(
        this.metrics.users.total,
        this.metrics.users.active.size
      );
    }
  }

  /**
   * Obter ID da sessão atual
   * @private
   */
  _getCurrentSessionId() {
    // Usar timestamp do início como session ID simplificado
    return `session_${this.startTime}`;
  }

  /**
   * Persistir em chrome.storage
   * @private
   */
  async _persist() {
    try {
      await chrome.storage.local.set({
        [this.storageKey]: {
          metrics: {
            ...this.metrics,
            users: {
              ...this.metrics.users,
              active: Array.from(this.metrics.users.active)
            }
          },
          events: this.events.slice(-1000), // Últimos 1000 eventos
          startTime: this.startTime,
          savedAt: Date.now()
        }
      });
    } catch (e) {
      console.error('[AnalyticsManager] Persist error:', e);
    }
  }

  /**
   * Carregar do chrome.storage
   */
  async load() {
    try {
      const result = await chrome.storage.local.get(this.storageKey);
      const data = result[this.storageKey];
      
      if (data) {
        if (data.metrics) {
          this.metrics = {
            ...data.metrics,
            users: {
              ...data.metrics.users,
              active: new Set(data.metrics.users.active || [])
            }
          };
        }
        if (data.events) {
          this.events = data.events;
        }
        if (data.startTime) {
          this.startTime = data.startTime;
        }
      }
    } catch (e) {
      console.error('[AnalyticsManager] Load error:', e);
    }
  }

  /**
   * Limpar dados antigos
   */
  cleanup(olderThan = 30 * 24 * 60 * 60 * 1000) {
    const cutoff = Date.now() - olderThan;
    this.events = this.events.filter(e => e.timestamp > cutoff);
    this.metrics.errors = this.metrics.errors.filter(e => e.timestamp > cutoff);
    this._persist();
  }

  /**
   * Resetar métricas
   */
  reset() {
    this.events = [];
    this.metrics = {
      messages: { sent: 0, received: 0, failed: 0 },
      users: { active: new Set(), total: 0 },
      commands: {},
      sessions: { total: 0, avg_duration: 0 },
      errors: []
    };
    this.startTime = Date.now();
    this._persist();
  }
}
