/**
 * @fileoverview SmartBot Rate Limit Manager - Rate limiting
 * @module smartbot/infrastructure/rate-limit-manager
 */

/**
 * Sistema de rate limiting por usuário/comando/global
 */
export class RateLimitManager {
  constructor(options = {}) {
    this.limits = new Map();
    this.usage = new Map();
    this.blocked = new Set();
    this.defaultWindow = options.defaultWindow || 60000; // 1 min
  }

  /**
   * Adicionar limite
   * @param {string} key - Chave do limite (user:123, command:help, global)
   * @param {number} maxRequests - Máximo de requisições
   * @param {number} window - Janela de tempo em ms
   */
  addLimit(key, maxRequests, window = this.defaultWindow) {
    this.limits.set(key, { maxRequests, window });
  }

  /**
   * Verificar se pode executar
   * @param {string} key - Chave
   * @returns {Object} { allowed, remaining, resetAt }
   */
  check(key) {
    // Verificar se está bloqueado
    if (this.blocked.has(key)) {
      return { allowed: false, remaining: 0, resetAt: null, blocked: true };
    }

    const limit = this.limits.get(key);
    if (!limit) {
      return { allowed: true, remaining: Infinity, resetAt: null };
    }

    const now = Date.now();
    
    // Inicializar uso se não existir
    if (!this.usage.has(key)) {
      this.usage.set(key, {
        requests: [],
        firstRequest: now
      });
    }

    const usage = this.usage.get(key);
    
    // Limpar requisições antigas
    usage.requests = usage.requests.filter(
      timestamp => now - timestamp < limit.window
    );

    const remaining = limit.maxRequests - usage.requests.length;
    const allowed = remaining > 0;
    
    const resetAt = usage.requests.length > 0
      ? usage.requests[0] + limit.window
      : null;

    return { allowed, remaining, resetAt, blocked: false };
  }

  /**
   * Registrar uso
   * @param {string} key - Chave
   * @returns {boolean} Se foi permitido
   */
  use(key) {
    const status = this.check(key);
    
    if (!status.allowed) {
      return false;
    }

    const usage = this.usage.get(key) || { requests: [], firstRequest: Date.now() };
    usage.requests.push(Date.now());
    this.usage.set(key, usage);

    return true;
  }

  /**
   * Bloquear chave
   * @param {string} key - Chave
   * @param {number} duration - Duração do bloqueio em ms (0 = permanente)
   */
  block(key, duration = 0) {
    this.blocked.add(key);
    
    if (duration > 0) {
      setTimeout(() => {
        this.blocked.delete(key);
      }, duration);
    }
  }

  /**
   * Desbloquear chave
   */
  unblock(key) {
    return this.blocked.delete(key);
  }

  /**
   * Verificar se está bloqueado
   */
  isBlocked(key) {
    return this.blocked.has(key);
  }

  /**
   * Resetar uso de uma chave
   */
  reset(key) {
    this.usage.delete(key);
    this.blocked.delete(key);
  }

  /**
   * Resetar todos os limites
   */
  resetAll() {
    this.usage.clear();
    this.blocked.clear();
  }

  /**
   * Obter estatísticas de uma chave
   */
  getStats(key) {
    const limit = this.limits.get(key);
    const usage = this.usage.get(key);
    const status = this.check(key);

    if (!limit) {
      return null;
    }

    return {
      key,
      limit: limit.maxRequests,
      window: limit.window,
      used: usage ? usage.requests.length : 0,
      remaining: status.remaining,
      resetAt: status.resetAt,
      blocked: status.blocked
    };
  }

  /**
   * Obter todas as estatísticas
   */
  getAllStats() {
    const stats = [];
    for (const key of this.limits.keys()) {
      stats.push(this.getStats(key));
    }
    return stats;
  }

  /**
   * Cleanup de dados antigos
   */
  cleanup() {
    const now = Date.now();
    let cleaned = 0;

    for (const [key, usage] of this.usage.entries()) {
      const limit = this.limits.get(key);
      if (!limit) continue;

      usage.requests = usage.requests.filter(
        timestamp => now - timestamp < limit.window
      );

      if (usage.requests.length === 0) {
        this.usage.delete(key);
        cleaned++;
      }
    }

    return cleaned;
  }
}
