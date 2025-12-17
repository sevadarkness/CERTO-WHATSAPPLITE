/**
 * @fileoverview SmartBot NLP Manager - Processamento de linguagem natural
 * @module smartbot/nlp/nlp-manager
 */

/**
 * Gerenciador NLP com análise de contexto e detecção de intenções
 */
export class NlpManager {
  constructor() {
    this.intents = new Map();
    this.entities = new Map();
    this.contexts = new Map();
    this.examples = [];
  }

  /**
   * Adicionar intenção
   * @param {string} name - Nome da intenção
   * @param {Object} config - Configuração
   */
  addIntent(name, config) {
    this.intents.set(name, {
      name,
      patterns: config.patterns || [],
      responses: config.responses || [],
      entities: config.entities || [],
      priority: config.priority || 5,
      confidence: config.confidence || 70
    });
  }

  /**
   * Adicionar entidade
   * @param {string} name - Nome da entidade
   * @param {Object} config - Configuração
   */
  addEntity(name, config) {
    this.entities.set(name, {
      name,
      type: config.type || 'regex', // regex, list, fuzzy
      patterns: config.patterns || [],
      values: config.values || [],
      examples: config.examples || []
    });
  }

  /**
   * Processar texto
   * @param {string} text - Texto a processar
   * @param {Object} context - Contexto adicional
   * @returns {Object} Análise completa
   */
  async process(text, context = {}) {
    const normalized = this._normalize(text);
    
    // Detectar intenção
    const intent = this._detectIntent(normalized);
    
    // Extrair entidades
    const entities = this._extractEntities(normalized);
    
    // Análise de sentimento
    const sentiment = this._analyzeSentiment(normalized);
    
    // Contexto do usuário
    const userContext = this.contexts.get(context.userId) || {};
    
    return {
      originalText: text,
      normalized,
      intent,
      entities,
      sentiment,
      context: userContext,
      confidence: intent.confidence,
      timestamp: Date.now()
    };
  }

  /**
   * Detectar intenção
   * @private
   */
  _detectIntent(text) {
    let bestMatch = {
      name: 'unknown',
      confidence: 0,
      patterns: []
    };

    for (const [name, intent] of this.intents.entries()) {
      const confidence = this._calculateIntentConfidence(text, intent);
      
      if (confidence > bestMatch.confidence) {
        bestMatch = {
          name,
          confidence,
          patterns: intent.patterns,
          responses: intent.responses
        };
      }
    }

    return bestMatch;
  }

  /**
   * Calcular confiança da intenção
   * @private
   */
  _calculateIntentConfidence(text, intent) {
    let maxScore = 0;

    for (const pattern of intent.patterns) {
      const score = this._matchPattern(text, pattern);
      if (score > maxScore) {
        maxScore = score;
      }
    }

    return Math.min(100, maxScore);
  }

  /**
   * Fazer match de pattern
   * @private
   */
  _matchPattern(text, pattern) {
    // Exact match
    if (text === pattern) return 100;

    // Contains
    if (text.includes(pattern)) return 90;

    // Word match
    const textWords = text.split(/\s+/);
    const patternWords = pattern.split(/\s+/);
    
    let matches = 0;
    for (const word of patternWords) {
      if (textWords.includes(word)) {
        matches++;
      }
    }

    if (matches > 0) {
      return Math.floor((matches / patternWords.length) * 80);
    }

    // Fuzzy match (Levenshtein simplificado)
    const similarity = this._stringSimilarity(text, pattern);
    return Math.floor(similarity * 70);
  }

  /**
   * Calcular similaridade entre strings
   * @private
   */
  _stringSimilarity(str1, str2) {
    const longer = str1.length > str2.length ? str1 : str2;
    const shorter = str1.length > str2.length ? str2 : str1;
    
    if (longer.length === 0) return 1.0;
    
    const editDistance = this._levenshteinDistance(longer, shorter);
    return (longer.length - editDistance) / longer.length;
  }

  /**
   * Distância de Levenshtein
   * @private
   */
  _levenshteinDistance(str1, str2) {
    const matrix = [];

    for (let i = 0; i <= str2.length; i++) {
      matrix[i] = [i];
    }

    for (let j = 0; j <= str1.length; j++) {
      matrix[0][j] = j;
    }

    for (let i = 1; i <= str2.length; i++) {
      for (let j = 1; j <= str1.length; j++) {
        if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
          matrix[i][j] = matrix[i - 1][j - 1];
        } else {
          matrix[i][j] = Math.min(
            matrix[i - 1][j - 1] + 1,
            matrix[i][j - 1] + 1,
            matrix[i - 1][j] + 1
          );
        }
      }
    }

    return matrix[str2.length][str1.length];
  }

  /**
   * Extrair entidades
   * @private
   */
  _extractEntities(text) {
    const found = [];

    for (const [name, entity] of this.entities.entries()) {
      if (entity.type === 'regex') {
        for (const pattern of entity.patterns) {
          const regex = new RegExp(pattern, 'gi');
          const matches = Array.from(text.matchAll(regex));
          
          for (const match of matches) {
            found.push({
              entity: name,
              value: match[0],
              position: match.index
            });
          }
        }
      } else if (entity.type === 'list') {
        for (const value of entity.values) {
          if (text.includes(value.toLowerCase())) {
            found.push({
              entity: name,
              value,
              position: text.indexOf(value.toLowerCase())
            });
          }
        }
      }
    }

    return found;
  }

  /**
   * Analisar sentimento
   * @private
   */
  _analyzeSentiment(text) {
    const positive = ['ótimo', 'bom', 'excelente', 'maravilhoso', 'legal', 'perfeito', 'obrigado', 'obrigada', 'gostei', 'adorei'];
    const negative = ['ruim', 'péssimo', 'horrível', 'problema', 'defeito', 'reclamação', 'não', 'nunca', 'nada'];
    
    let score = 0;
    const words = text.split(/\s+/);

    for (const word of words) {
      if (positive.includes(word)) score += 1;
      if (negative.includes(word)) score -= 1;
    }

    let sentiment = 'neutral';
    if (score > 0) sentiment = 'positive';
    if (score < 0) sentiment = 'negative';

    return {
      sentiment,
      score,
      confidence: Math.min(100, Math.abs(score) * 30)
    };
  }

  /**
   * Normalizar texto
   * @private
   */
  _normalize(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '') // Remove acentos
      .replace(/[^\w\s]/g, ' ') // Remove pontuação
      .replace(/\s+/g, ' ') // Remove espaços múltiplos
      .trim();
  }

  /**
   * Atualizar contexto do usuário
   */
  updateContext(userId, context) {
    const current = this.contexts.get(userId) || {};
    this.contexts.set(userId, { ...current, ...context });
  }

  /**
   * Obter contexto do usuário
   */
  getContext(userId) {
    return this.contexts.get(userId) || {};
  }

  /**
   * Limpar contexto do usuário
   */
  clearContext(userId) {
    this.contexts.delete(userId);
  }

  /**
   * Treinar com exemplos
   */
  train(examples) {
    this.examples = [...this.examples, ...examples];
    // Aqui poderíamos implementar aprendizado mais avançado
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    return {
      intents: this.intents.size,
      entities: this.entities.size,
      contexts: this.contexts.size,
      examples: this.examples.length
    };
  }
}

/**
 * Analisador de contexto avançado
 */
export class AdvancedContextAnalyzer {
  constructor() {
    this.profiles = new Map();
    this.conversationHistory = new Map();
  }

  /**
   * Analisar perfil do cliente
   */
  analyzeProfile(userId, data) {
    const profile = this.profiles.get(userId) || {
      userId,
      firstSeen: Date.now(),
      interactions: 0,
      preferences: {},
      sentiment: { positive: 0, negative: 0, neutral: 0 }
    };

    profile.interactions++;
    profile.lastSeen = Date.now();
    
    // Atualizar sentimento
    if (data.sentiment) {
      profile.sentiment[data.sentiment]++;
    }

    // Atualizar preferências
    if (data.intent) {
      profile.preferences[data.intent] = (profile.preferences[data.intent] || 0) + 1;
    }

    this.profiles.set(userId, profile);
    return profile;
  }

  /**
   * Adicionar ao histórico de conversa
   */
  addToHistory(userId, message, analysis) {
    if (!this.conversationHistory.has(userId)) {
      this.conversationHistory.set(userId, []);
    }

    const history = this.conversationHistory.get(userId);
    history.push({
      message,
      analysis,
      timestamp: Date.now()
    });

    // Manter apenas últimas 50 mensagens
    if (history.length > 50) {
      history.shift();
    }
  }

  /**
   * Obter histórico
   */
  getHistory(userId, limit = 10) {
    const history = this.conversationHistory.get(userId) || [];
    return history.slice(-limit);
  }

  /**
   * Obter perfil
   */
  getProfile(userId) {
    return this.profiles.get(userId) || null;
  }
}

/**
 * Gerador de respostas contextualizadas
 */
export class ContextualResponseGenerator {
  constructor() {
    this.templates = new Map();
    this.tones = {
      formal: { greeting: 'Olá', closing: 'Atenciosamente' },
      casual: { greeting: 'Oi', closing: 'Abraços' },
      friendly: { greeting: 'E aí', closing: 'Valeu' }
    };
  }

  /**
   * Gerar resposta personalizada
   */
  generate(intent, context = {}, tone = 'casual') {
    const template = this.templates.get(intent);
    if (!template) {
      return 'Desculpe, não entendi. Pode reformular?';
    }

    let response = Array.isArray(template) 
      ? template[Math.floor(Math.random() * template.length)]
      : template;

    // Aplicar variáveis
    response = this._applyVariables(response, context);

    // Aplicar tom
    response = this._applyTone(response, tone);

    return response;
  }

  /**
   * Adicionar template
   */
  addTemplate(intent, template) {
    this.templates.set(intent, template);
  }

  /**
   * Aplicar variáveis
   * @private
   */
  _applyVariables(text, variables) {
    let result = text;
    for (const [key, value] of Object.entries(variables)) {
      result = result.replace(new RegExp(`{{${key}}}`, 'g'), value);
    }
    return result;
  }

  /**
   * Aplicar tom
   * @private
   */
  _applyTone(text, tone) {
    const toneConfig = this.tones[tone] || this.tones.casual;
    
    // Adicionar saudação se não tiver
    if (!text.match(/^(oi|olá|e aí)/i)) {
      text = `${toneConfig.greeting}! ${text}`;
    }

    return text;
  }
}
