/**
 * @fileoverview SmartBot Cache Manager - Sistema de cache LRU/LFU
 * @module smartbot/infrastructure/cache-manager
 */

/**
 * Sistema de cache com LRU/LFU e TTL
 */
export class CacheManager {
  /**
   * @param {Object} options - Opções
   * @param {string} options.strategy - 'lru' ou 'lfu'
   * @param {number} options.maxSize - Tamanho máximo
   * @param {number} options.defaultTTL - TTL padrão em ms
   */
  constructor(options = {}) {
    this.strategy = options.strategy || 'lru';
    this.maxSize = options.maxSize || 100;
    this.defaultTTL = options.defaultTTL || 300000; // 5 min
    this.cache = new Map();
    this.stats = {
      hits: 0,
      misses: 0,
      sets: 0,
      evictions: 0,
      expirations: 0
    };
  }

  /**
   * Obter item do cache
   * @param {string} key - Chave
   * @returns {*} Valor ou null se não encontrado/expirado
   */
  get(key) {
    const item = this.cache.get(key);

    if (!item) {
      this.stats.misses++;
      return null;
    }

    // Verificar expiração
    if (Date.now() > item.expiresAt) {
      this.cache.delete(key);
      this.stats.expirations++;
      this.stats.misses++;
      return null;
    }

    // Atualizar estratégia
    if (this.strategy === 'lru') {
      item.lastAccess = Date.now();
    } else if (this.strategy === 'lfu') {
      item.frequency++;
    }

    this.stats.hits++;
    return item.value;
  }

  /**
   * Definir item no cache
   * @param {string} key - Chave
   * @param {*} value - Valor
   * @param {number} ttl - TTL em ms (opcional)
   */
  set(key, value, ttl = this.defaultTTL) {
    // Eviction se necessário
    if (this.cache.size >= this.maxSize && !this.cache.has(key)) {
      this._evict();
    }

    const item = {
      value,
      expiresAt: Date.now() + ttl,
      createdAt: Date.now(),
      lastAccess: Date.now(),
      frequency: 1
    };

    this.cache.set(key, item);
    this.stats.sets++;
  }

  /**
   * Verificar se chave existe
   * @param {string} key - Chave
   */
  has(key) {
    return this.get(key) !== null;
  }

  /**
   * Deletar item
   * @param {string} key - Chave
   */
  delete(key) {
    return this.cache.delete(key);
  }

  /**
   * Limpar cache
   */
  clear() {
    this.cache.clear();
  }

  /**
   * Obter tamanho
   */
  size() {
    return this.cache.size;
  }

  /**
   * Fazer eviction baseado na estratégia
   * @private
   */
  _evict() {
    let keyToEvict = null;

    if (this.strategy === 'lru') {
      // Remover menos recentemente usado
      let oldestAccess = Infinity;
      for (const [key, item] of this.cache.entries()) {
        if (item.lastAccess < oldestAccess) {
          oldestAccess = item.lastAccess;
          keyToEvict = key;
        }
      }
    } else if (this.strategy === 'lfu') {
      // Remover menos frequentemente usado
      let lowestFreq = Infinity;
      for (const [key, item] of this.cache.entries()) {
        if (item.frequency < lowestFreq) {
          lowestFreq = item.frequency;
          keyToEvict = key;
        }
      }
    }

    if (keyToEvict) {
      this.cache.delete(keyToEvict);
      this.stats.evictions++;
    }
  }

  /**
   * Cleanup de items expirados
   */
  cleanup() {
    const now = Date.now();
    let cleaned = 0;

    for (const [key, item] of this.cache.entries()) {
      if (now > item.expiresAt) {
        this.cache.delete(key);
        this.stats.expirations++;
        cleaned++;
      }
    }

    return cleaned;
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    const hitRate = this.stats.hits + this.stats.misses > 0
      ? (this.stats.hits / (this.stats.hits + this.stats.misses) * 100).toFixed(2)
      : 0;

    return {
      ...this.stats,
      hitRate: `${hitRate}%`,
      size: this.cache.size,
      maxSize: this.maxSize,
      strategy: this.strategy
    };
  }

  /**
   * Resetar estatísticas
   */
  resetStats() {
    this.stats = {
      hits: 0,
      misses: 0,
      sets: 0,
      evictions: 0,
      expirations: 0
    };
  }

  /**
   * Obter todas as chaves
   */
  keys() {
    return Array.from(this.cache.keys());
  }

  /**
   * Obter todos os valores
   */
  values() {
    return Array.from(this.cache.values()).map(item => item.value);
  }

  /**
   * Obter todos os entries
   */
  entries() {
    const result = [];
    for (const [key, item] of this.cache.entries()) {
      result.push([key, item.value]);
    }
    return result;
  }
}
