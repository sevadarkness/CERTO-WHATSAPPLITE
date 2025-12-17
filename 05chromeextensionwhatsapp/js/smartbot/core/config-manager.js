/**
 * @fileoverview SmartBot Config Manager - Gerenciador de configurações
 * @module smartbot/core/config-manager
 */

/**
 * Gerenciador de configurações com validação e persistência
 */
export class ConfigManager {
  constructor(options = {}) {
    this.storageKey = options.storageKey || 'smartbot_config';
    this.config = {};
    this.defaults = {};
    this.schema = {};
    this.changeCallbacks = [];
  }

  /**
   * Definir schema de validação
   * @param {Object} schema - Schema de validação
   */
  setSchema(schema) {
    this.schema = schema;
  }

  /**
   * Definir valores padrão
   * @param {Object} defaults - Configurações padrão
   */
  setDefaults(defaults) {
    this.defaults = defaults;
    this.config = { ...defaults };
  }

  /**
   * Obter configuração
   * @param {string} key - Chave (suporta dot notation: 'nlp.threshold')
   */
  get(key) {
    if (!key) return { ...this.config };

    const keys = key.split('.');
    let value = this.config;

    for (const k of keys) {
      if (value && typeof value === 'object' && k in value) {
        value = value[k];
      } else {
        return undefined;
      }
    }

    return value;
  }

  /**
   * Definir configuração
   * @param {string} key - Chave ou objeto de configurações
   * @param {*} value - Valor (opcional se key for objeto)
   */
  set(key, value) {
    if (typeof key === 'object') {
      // Set multiple configs
      for (const [k, v] of Object.entries(key)) {
        this._setSingle(k, v);
      }
    } else {
      this._setSingle(key, value);
    }

    return this.persist();
  }

  /**
   * Definir configuração única
   * @private
   */
  _setSingle(key, value) {
    // Validar se há schema
    if (Object.keys(this.schema).length > 0) {
      const validation = this._validate(key, value);
      if (!validation.valid) {
        throw new Error(`Validation failed for ${key}: ${validation.errors.join(', ')}`);
      }
    }

    const keys = key.split('.');
    let obj = this.config;

    for (let i = 0; i < keys.length - 1; i++) {
      const k = keys[i];
      if (!obj[k] || typeof obj[k] !== 'object') {
        obj[k] = {};
      }
      obj = obj[k];
    }

    const oldValue = obj[keys[keys.length - 1]];
    obj[keys[keys.length - 1]] = value;

    // Notificar callbacks
    this._notifyChange(key, value, oldValue);
  }

  /**
   * Validar configuração contra schema
   * @private
   */
  _validate(key, value) {
    const errors = [];
    const schemaRule = this.schema[key];

    if (!schemaRule) return { valid: true, errors };

    // Type check
    if (schemaRule.type) {
      const actualType = Array.isArray(value) ? 'array' : typeof value;
      if (actualType !== schemaRule.type) {
        errors.push(`Expected type ${schemaRule.type}, got ${actualType}`);
      }
    }

    // Range check for numbers
    if (schemaRule.type === 'number') {
      if (schemaRule.min !== undefined && value < schemaRule.min) {
        errors.push(`Value ${value} is less than minimum ${schemaRule.min}`);
      }
      if (schemaRule.max !== undefined && value > schemaRule.max) {
        errors.push(`Value ${value} is greater than maximum ${schemaRule.max}`);
      }
    }

    // Enum check
    if (schemaRule.enum && !schemaRule.enum.includes(value)) {
      errors.push(`Value must be one of: ${schemaRule.enum.join(', ')}`);
    }

    // Required check
    if (schemaRule.required && (value === null || value === undefined)) {
      errors.push(`Field is required`);
    }

    // Custom validator
    if (schemaRule.validator && typeof schemaRule.validator === 'function') {
      try {
        const result = schemaRule.validator(value);
        if (result !== true) {
          errors.push(result || 'Custom validation failed');
        }
      } catch (e) {
        errors.push(`Validator error: ${e.message}`);
      }
    }

    return { valid: errors.length === 0, errors };
  }

  /**
   * Notificar mudança
   * @private
   */
  _notifyChange(key, newValue, oldValue) {
    for (const callback of this.changeCallbacks) {
      try {
        callback(key, newValue, oldValue);
      } catch (e) {
        console.error('[ConfigManager] Callback error:', e);
      }
    }
  }

  /**
   * Registrar callback de mudança
   * @param {Function} callback - Callback(key, newValue, oldValue)
   */
  onChange(callback) {
    this.changeCallbacks.push(callback);
    return () => {
      this.changeCallbacks = this.changeCallbacks.filter(cb => cb !== callback);
    };
  }

  /**
   * Resetar para padrões
   */
  reset(key = null) {
    if (key) {
      this.set(key, this._getDefault(key));
    } else {
      this.config = { ...this.defaults };
      this.persist();
    }
  }

  /**
   * Obter valor padrão
   * @private
   */
  _getDefault(key) {
    const keys = key.split('.');
    let value = this.defaults;

    for (const k of keys) {
      if (value && typeof value === 'object' && k in value) {
        value = value[k];
      } else {
        return undefined;
      }
    }

    return value;
  }

  /**
   * Persistir em chrome.storage
   */
  async persist() {
    try {
      await chrome.storage.local.set({ [this.storageKey]: this.config });
      return true;
    } catch (e) {
      console.error('[ConfigManager] Persist error:', e);
      return false;
    }
  }

  /**
   * Carregar do chrome.storage
   */
  async load() {
    try {
      const result = await chrome.storage.local.get(this.storageKey);
      if (result[this.storageKey]) {
        this.config = { ...this.defaults, ...result[this.storageKey] };
      } else {
        this.config = { ...this.defaults };
      }
      return true;
    } catch (e) {
      console.error('[ConfigManager] Load error:', e);
      return false;
    }
  }

  /**
   * Exportar configuração
   */
  export() {
    return JSON.stringify(this.config, null, 2);
  }

  /**
   * Importar configuração
   */
  import(jsonString) {
    try {
      const imported = JSON.parse(jsonString);
      this.config = { ...this.defaults, ...imported };
      this.persist();
      return true;
    } catch (e) {
      console.error('[ConfigManager] Import error:', e);
      return false;
    }
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    return {
      totalKeys: this._countKeys(this.config),
      hasDefaults: Object.keys(this.defaults).length > 0,
      hasSchema: Object.keys(this.schema).length > 0,
      changeCallbacks: this.changeCallbacks.length
    };
  }

  /**
   * Contar chaves recursivamente
   * @private
   */
  _countKeys(obj) {
    let count = 0;
    for (const value of Object.values(obj)) {
      count++;
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        count += this._countKeys(value);
      }
    }
    return count;
  }
}
