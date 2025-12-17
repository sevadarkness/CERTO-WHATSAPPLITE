/**
 * @fileoverview SmartBot Session Manager - Gerenciamento de sessões
 * @module smartbot/infrastructure/session-manager
 */

/**
 * Gerenciamento de sessões com timeout e cleanup automático
 */
export class SessionManager {
  constructor(options = {}) {
    this.defaultTimeout = options.defaultTimeout || 1800000; // 30 min
    this.cleanupInterval = options.cleanupInterval || 60000; // 1 min
    this.sessions = new Map();
    this.persistKey = options.persistKey || 'smartbot_sessions';
    
    this._startCleanup();
  }

  /**
   * Criar sessão
   */
  create(sessionId, data = {}, timeout = this.defaultTimeout) {
    const session = {
      id: sessionId,
      data,
      createdAt: Date.now(),
      lastActivity: Date.now(),
      expiresAt: Date.now() + timeout,
      timeout
    };

    this.sessions.set(sessionId, session);
    this._persist();
    return session;
  }

  /**
   * Obter sessão
   */
  get(sessionId) {
    const session = this.sessions.get(sessionId);
    if (!session) return null;

    // Verificar expiração
    if (Date.now() > session.expiresAt) {
      this.destroy(sessionId);
      return null;
    }

    // Atualizar atividade
    session.lastActivity = Date.now();
    session.expiresAt = Date.now() + session.timeout;
    
    return session;
  }

  /**
   * Atualizar dados da sessão
   */
  update(sessionId, data) {
    const session = this.get(sessionId);
    if (!session) return false;

    session.data = { ...session.data, ...data };
    session.lastActivity = Date.now();
    session.expiresAt = Date.now() + session.timeout;
    
    this._persist();
    return true;
  }

  /**
   * Destruir sessão
   */
  destroy(sessionId) {
    const deleted = this.sessions.delete(sessionId);
    if (deleted) this._persist();
    return deleted;
  }

  /**
   * Renovar sessão
   */
  renew(sessionId, additionalTime = null) {
    const session = this.get(sessionId);
    if (!session) return false;

    const timeout = additionalTime || session.timeout;
    session.expiresAt = Date.now() + timeout;
    session.lastActivity = Date.now();
    
    this._persist();
    return true;
  }

  /**
   * Verificar se sessão existe e é válida
   */
  has(sessionId) {
    return this.get(sessionId) !== null;
  }

  /**
   * Obter todas as sessões ativas
   */
  getAll() {
    const sessions = [];
    for (const [id, session] of this.sessions.entries()) {
      if (Date.now() <= session.expiresAt) {
        sessions.push(session);
      }
    }
    return sessions;
  }

  /**
   * Cleanup de sessões expiradas
   * @private
   */
  _cleanup() {
    const now = Date.now();
    let cleaned = 0;

    for (const [id, session] of this.sessions.entries()) {
      if (now > session.expiresAt) {
        this.sessions.delete(id);
        cleaned++;
      }
    }

    if (cleaned > 0) {
      this._persist();
    }

    return cleaned;
  }

  /**
   * Iniciar cleanup automático
   * @private
   */
  _startCleanup() {
    this.cleanupTimer = setInterval(() => {
      this._cleanup();
    }, this.cleanupInterval);
  }

  /**
   * Parar cleanup automático
   */
  stopCleanup() {
    if (this.cleanupTimer) {
      clearInterval(this.cleanupTimer);
      this.cleanupTimer = null;
    }
  }

  /**
   * Persistir em chrome.storage
   * @private
   */
  async _persist() {
    try {
      const data = Array.from(this.sessions.entries());
      await chrome.storage.local.set({ [this.persistKey]: data });
    } catch (e) {
      console.error('[SessionManager] Persist error:', e);
    }
  }

  /**
   * Carregar do chrome.storage
   */
  async load() {
    try {
      const result = await chrome.storage.local.get(this.persistKey);
      if (result[this.persistKey]) {
        this.sessions = new Map(result[this.persistKey]);
        this._cleanup(); // Limpar expiradas
      }
    } catch (e) {
      console.error('[SessionManager] Load error:', e);
    }
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    const now = Date.now();
    const sessions = this.getAll();
    
    const avgDuration = sessions.length > 0
      ? sessions.reduce((sum, s) => sum + (now - s.createdAt), 0) / sessions.length
      : 0;

    return {
      total: sessions.length,
      avgDuration: Math.round(avgDuration / 1000),
      oldest: sessions.length > 0
        ? Math.round((now - Math.min(...sessions.map(s => s.createdAt))) / 1000)
        : 0
    };
  }
}
