/**
 * @fileoverview SmartBot Event Manager - Sistema de eventos pub/sub
 * @module smartbot/core/event-manager
 */

/**
 * Sistema de eventos com pub/sub e wildcards
 */
export class EventManager {
  constructor() {
    this.listeners = new Map();
    this.onceListeners = new Set();
    this.eventHistory = [];
    this.maxHistory = 100;
  }

  /**
   * Adicionar listener para evento
   * @param {string} event - Nome do evento (suporta wildcards: 'message:*')
   * @param {Function} callback - Callback a executar
   * @param {number} priority - Prioridade (menor = executado primeiro)
   */
  on(event, callback, priority = 5) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }

    const listener = { callback, priority, once: false };
    this.listeners.get(event).push(listener);
    
    // Ordenar por prioridade
    this.listeners.get(event).sort((a, b) => a.priority - b.priority);

    return () => this.off(event, callback);
  }

  /**
   * Adicionar listener para um único evento
   */
  once(event, callback, priority = 5) {
    const listener = { callback, priority, once: true };
    
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }

    this.listeners.get(event).push(listener);
    this.onceListeners.add(listener);
    this.listeners.get(event).sort((a, b) => a.priority - b.priority);

    return () => this.off(event, callback);
  }

  /**
   * Remover listener
   */
  off(event, callback) {
    if (!this.listeners.has(event)) return false;

    const listeners = this.listeners.get(event);
    const index = listeners.findIndex(l => l.callback === callback);
    
    if (index !== -1) {
      const removed = listeners.splice(index, 1)[0];
      this.onceListeners.delete(removed);
      return true;
    }

    return false;
  }

  /**
   * Emitir evento
   * @param {string} event - Nome do evento
   * @param {*} data - Dados do evento
   */
  emit(event, data) {
    // Armazenar no histórico
    this.eventHistory.push({
      event,
      data,
      timestamp: Date.now()
    });

    if (this.eventHistory.length > this.maxHistory) {
      this.eventHistory.shift();
    }

    // Listeners exatos
    this._executeListeners(event, data);

    // Listeners com wildcard
    this._executeWildcardListeners(event, data);
  }

  /**
   * Executar listeners
   * @private
   */
  _executeListeners(event, data) {
    if (!this.listeners.has(event)) return;

    const listeners = [...this.listeners.get(event)];
    
    for (const listener of listeners) {
      try {
        listener.callback(data, event);
        
        // Remover se for once
        if (listener.once) {
          this.off(event, listener.callback);
        }
      } catch (error) {
        // Use console.error as fallback since we may not have LogManager reference
        // In production, this should be replaced with proper logging
        console.error(`[EventManager] Error in listener for "${event}":`, error);
      }
    }
  }

  /**
   * Executar listeners com wildcard
   * @private
   */
  _executeWildcardListeners(event, data) {
    for (const [pattern, listeners] of this.listeners.entries()) {
      if (pattern.includes('*') && this._matchWildcard(event, pattern)) {
        for (const listener of listeners) {
          try {
            listener.callback(data, event);
            
            if (listener.once) {
              this.off(pattern, listener.callback);
            }
          } catch (error) {
            console.error(`[EventManager] Error in wildcard listener for "${pattern}":`, error);
          }
        }
      }
    }
  }

  /**
   * Verificar se evento combina com pattern wildcard
   * @private
   */
  _matchWildcard(event, pattern) {
    const eventParts = event.split(':');
    const patternParts = pattern.split(':');

    if (patternParts.length > eventParts.length) return false;

    for (let i = 0; i < patternParts.length; i++) {
      if (patternParts[i] === '*') continue;
      if (patternParts[i] !== eventParts[i]) return false;
    }

    return true;
  }

  /**
   * Remover todos os listeners de um evento
   */
  removeAllListeners(event) {
    if (event) {
      this.listeners.delete(event);
    } else {
      this.listeners.clear();
      this.onceListeners.clear();
    }
  }

  /**
   * Obter número de listeners
   */
  listenerCount(event) {
    if (!event) {
      let total = 0;
      for (const listeners of this.listeners.values()) {
        total += listeners.length;
      }
      return total;
    }

    return this.listeners.has(event) ? this.listeners.get(event).length : 0;
  }

  /**
   * Obter eventos registrados
   */
  getEvents() {
    return Array.from(this.listeners.keys());
  }

  /**
   * Obter histórico de eventos
   */
  getHistory(event = null, limit = 50) {
    let history = event
      ? this.eventHistory.filter(h => h.event === event)
      : this.eventHistory;

    return history.slice(-limit);
  }

  /**
   * Limpar histórico
   */
  clearHistory() {
    this.eventHistory = [];
  }
}
