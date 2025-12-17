/**
 * @fileoverview SmartBot Locale Manager - Sistema de internacionalização
 * @module smartbot/i18n/locale-manager
 */

/**
 * Gerenciador de localização e internacionalização
 */
export class LocaleManager {
  constructor(options = {}) {
    this.defaultLocale = options.defaultLocale || 'pt-BR';
    this.currentLocale = this.defaultLocale;
    this.fallbackLocale = options.fallbackLocale || 'en';
    this.translations = new Map();
    this.userLocales = new Map();
    
    this._loadDefaultTranslations();
  }

  /**
   * Carregar traduções padrão
   * @private
   */
  _loadDefaultTranslations() {
    // Português (Brasil)
    this.addTranslations('pt-BR', {
      'greeting': 'Olá',
      'goodbye': 'Até logo',
      'thank_you': 'Obrigado',
      'yes': 'Sim',
      'no': 'Não',
      'help': 'Ajuda',
      'error': 'Erro',
      'success': 'Sucesso',
      'loading': 'Carregando...',
      'save': 'Salvar',
      'cancel': 'Cancelar',
      'confirm': 'Confirmar',
      'message_sent': 'Mensagem enviada',
      'message_failed': 'Falha ao enviar mensagem'
    });

    // English
    this.addTranslations('en', {
      'greeting': 'Hello',
      'goodbye': 'Goodbye',
      'thank_you': 'Thank you',
      'yes': 'Yes',
      'no': 'No',
      'help': 'Help',
      'error': 'Error',
      'success': 'Success',
      'loading': 'Loading...',
      'save': 'Save',
      'cancel': 'Cancel',
      'confirm': 'Confirm',
      'message_sent': 'Message sent',
      'message_failed': 'Failed to send message'
    });

    // Español
    this.addTranslations('es', {
      'greeting': 'Hola',
      'goodbye': 'Adiós',
      'thank_you': 'Gracias',
      'yes': 'Sí',
      'no': 'No',
      'help': 'Ayuda',
      'error': 'Error',
      'success': 'Éxito',
      'loading': 'Cargando...',
      'save': 'Guardar',
      'cancel': 'Cancelar',
      'confirm': 'Confirmar',
      'message_sent': 'Mensaje enviado',
      'message_failed': 'Error al enviar mensaje'
    });
  }

  /**
   * Adicionar traduções
   */
  addTranslations(locale, translations) {
    if (!this.translations.has(locale)) {
      this.translations.set(locale, {});
    }

    const current = this.translations.get(locale);
    this.translations.set(locale, { ...current, ...translations });
  }

  /**
   * Traduzir texto
   */
  t(key, params = {}, locale = null) {
    const targetLocale = locale || this.currentLocale;
    
    // Tentar locale especificado
    let translation = this._getTranslation(targetLocale, key);

    // Fallback para locale padrão
    if (!translation && targetLocale !== this.defaultLocale) {
      translation = this._getTranslation(this.defaultLocale, key);
    }

    // Fallback final
    if (!translation && this.fallbackLocale !== targetLocale) {
      translation = this._getTranslation(this.fallbackLocale, key);
    }

    // Se não encontrou, retornar key
    if (!translation) {
      return key;
    }

    // Substituir parâmetros
    return this._replacePlaceholders(translation, params);
  }

  /**
   * Obter tradução
   * @private
   */
  _getTranslation(locale, key) {
    const translations = this.translations.get(locale);
    if (!translations) return null;

    // Suporte a dot notation (ex: 'messages.error.not_found')
    const keys = key.split('.');
    let value = translations;

    for (const k of keys) {
      if (value && typeof value === 'object' && k in value) {
        value = value[k];
      } else {
        return null;
      }
    }

    return typeof value === 'string' ? value : null;
  }

  /**
   * Substituir placeholders
   * @private
   */
  _replacePlaceholders(text, params) {
    let result = text;

    for (const [key, value] of Object.entries(params)) {
      result = result.replace(new RegExp(`{{${key}}}`, 'g'), value);
    }

    return result;
  }

  /**
   * Mudar locale global
   */
  setLocale(locale) {
    if (this.translations.has(locale)) {
      this.currentLocale = locale;
      return true;
    }
    return false;
  }

  /**
   * Obter locale atual
   */
  getLocale() {
    return this.currentLocale;
  }

  /**
   * Definir locale por usuário
   */
  setUserLocale(userId, locale) {
    if (this.translations.has(locale)) {
      this.userLocales.set(userId, locale);
      return true;
    }
    return false;
  }

  /**
   * Obter locale do usuário
   */
  getUserLocale(userId) {
    return this.userLocales.get(userId) || this.currentLocale;
  }

  /**
   * Traduzir para usuário específico
   */
  tu(userId, key, params = {}) {
    const locale = this.getUserLocale(userId);
    return this.t(key, params, locale);
  }

  /**
   * Pluralização
   */
  plural(key, count, params = {}, locale = null) {
    const targetLocale = locale || this.currentLocale;
    
    // Regras de pluralização por locale
    const rules = {
      'pt-BR': (n) => n === 1 ? 'one' : 'other',
      'en': (n) => n === 1 ? 'one' : 'other',
      'es': (n) => n === 1 ? 'one' : 'other'
    };

    const rule = rules[targetLocale] || rules['en'];
    const form = rule(count);
    
    const pluralKey = `${key}.${form}`;
    const translation = this.t(pluralKey, { count, ...params }, targetLocale);
    
    // Fallback se não encontrar forma plural
    if (translation === pluralKey) {
      return this.t(key, { count, ...params }, targetLocale);
    }

    return translation;
  }

  /**
   * Listar locales disponíveis
   */
  getAvailableLocales() {
    return Array.from(this.translations.keys());
  }

  /**
   * Verificar se locale existe
   */
  hasLocale(locale) {
    return this.translations.has(locale);
  }

  /**
   * Obter todas as traduções de um locale
   */
  getTranslations(locale) {
    return this.translations.get(locale) || {};
  }

  /**
   * Exportar traduções
   */
  export(locale = null) {
    if (locale) {
      return {
        locale,
        translations: this.getTranslations(locale)
      };
    }

    const all = {};
    for (const [loc, trans] of this.translations.entries()) {
      all[loc] = trans;
    }

    return all;
  }

  /**
   * Importar traduções
   */
  import(data) {
    if (data.locale && data.translations) {
      // Importar locale único
      this.addTranslations(data.locale, data.translations);
    } else {
      // Importar múltiplos locales
      for (const [locale, translations] of Object.entries(data)) {
        this.addTranslations(locale, translations);
      }
    }
  }

  /**
   * Detectar locale do navegador
   */
  detectBrowserLocale() {
    const browserLocale = navigator.language || navigator.userLanguage;
    
    // Tentar match exato
    if (this.hasLocale(browserLocale)) {
      return browserLocale;
    }

    // Tentar match por idioma base (ex: 'pt' de 'pt-BR')
    const baseLang = browserLocale.split('-')[0];
    for (const locale of this.getAvailableLocales()) {
      if (locale.startsWith(baseLang)) {
        return locale;
      }
    }

    return this.defaultLocale;
  }

  /**
   * Auto-detectar e definir locale
   */
  autoDetect() {
    const detected = this.detectBrowserLocale();
    this.setLocale(detected);
    return detected;
  }
}
