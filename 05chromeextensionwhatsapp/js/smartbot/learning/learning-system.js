/**
 * @fileoverview SmartBot Learning System - Sistema de aprendizado contínuo
 * @module smartbot/learning/learning-system
 */

/**
 * Sistema de aprendizado contínuo com feedback
 */
export class ContinuousLearningSystem {
  constructor(options = {}) {
    this.storageKey = options.storageKey || 'smartbot_learning';
    this.maxExamples = options.maxExamples || 500;
    this.positiveExamples = [];
    this.negativeExamples = [];
    this.patterns = new Map();
    this.feedbackData = {
      positive: 0,
      negative: 0,
      corrections: []
    };
  }

  /**
   * Adicionar feedback positivo
   */
  addPositiveFeedback(message, response, intent) {
    const example = {
      message,
      response,
      intent,
      timestamp: Date.now(),
      type: 'positive'
    };

    this.positiveExamples.push(example);
    this.feedbackData.positive++;
    
    // Extrair padrões
    this._extractPattern(message, intent, 'positive');
    
    // Limitar tamanho
    if (this.positiveExamples.length > this.maxExamples) {
      this.positiveExamples.shift();
    }

    this._persist();
    return example;
  }

  /**
   * Adicionar feedback negativo
   */
  addNegativeFeedback(message, response, reason) {
    const example = {
      message,
      response,
      reason,
      timestamp: Date.now(),
      type: 'negative'
    };

    this.negativeExamples.push(example);
    this.feedbackData.negative++;

    if (this.negativeExamples.length > this.maxExamples) {
      this.negativeExamples.shift();
    }

    this._persist();
    return example;
  }

  /**
   * Adicionar correção
   */
  addCorrection(originalMessage, wrongResponse, correctResponse, correctIntent) {
    const correction = {
      originalMessage,
      wrongResponse,
      correctResponse,
      correctIntent,
      timestamp: Date.now()
    };

    this.feedbackData.corrections.push(correction);

    // Aprender com a correção
    this._extractPattern(originalMessage, correctIntent, 'correction');
    
    // Criar exemplo positivo
    this.addPositiveFeedback(originalMessage, correctResponse, correctIntent);

    if (this.feedbackData.corrections.length > 100) {
      this.feedbackData.corrections.shift();
    }

    this._persist();
    return correction;
  }

  /**
   * Extrair padrão de mensagem
   * @private
   */
  _extractPattern(message, intent, source) {
    const words = message.toLowerCase().split(/\s+/).filter(w => w.length > 3);
    
    if (words.length < 2) return;

    const pattern = {
      words: words.slice(0, 5), // Primeiras 5 palavras significativas
      intent,
      source,
      confidence: source === 'correction' ? 90 : 70,
      occurrences: 1,
      createdAt: Date.now()
    };

    const key = words.join('_');
    
    if (this.patterns.has(key)) {
      const existing = this.patterns.get(key);
      existing.occurrences++;
      existing.confidence = Math.min(95, existing.confidence + 2);
    } else {
      this.patterns.set(key, pattern);
    }

    // Limitar padrões
    if (this.patterns.size > 200) {
      // Remover padrões com menor confiança
      const sorted = Array.from(this.patterns.entries())
        .sort((a, b) => (b[1].confidence * b[1].occurrences) - (a[1].confidence * a[1].occurrences));
      
      this.patterns = new Map(sorted.slice(0, 150));
    }
  }

  /**
   * Obter padrões aprendidos
   */
  getLearnedPatterns() {
    return Array.from(this.patterns.values())
      .sort((a, b) => (b.confidence * b.occurrences) - (a.confidence * a.occurrences));
  }

  /**
   * Encontrar padrão similar
   */
  findSimilarPattern(message) {
    const words = message.toLowerCase().split(/\s+/).filter(w => w.length > 3);
    let bestMatch = null;
    let bestScore = 0;

    for (const [key, pattern] of this.patterns.entries()) {
      let matches = 0;
      for (const word of words) {
        if (pattern.words.includes(word)) {
          matches++;
        }
      }

      if (matches > 0) {
        const score = (matches / pattern.words.length) * pattern.confidence * Math.log(pattern.occurrences + 1);
        if (score > bestScore) {
          bestScore = score;
          bestMatch = pattern;
        }
      }
    }

    if (bestMatch && bestScore > 50) {
      return {
        pattern: bestMatch,
        confidence: Math.min(100, bestScore)
      };
    }

    return null;
  }

  /**
   * Obter estatísticas de feedback
   */
  getFeedbackStats() {
    const total = this.feedbackData.positive + this.feedbackData.negative;
    const positiveRate = total > 0 
      ? ((this.feedbackData.positive / total) * 100).toFixed(2)
      : 0;

    return {
      positive: this.feedbackData.positive,
      negative: this.feedbackData.negative,
      corrections: this.feedbackData.corrections.length,
      positiveRate: `${positiveRate}%`,
      patterns: this.patterns.size,
      examples: this.positiveExamples.length + this.negativeExamples.length
    };
  }

  /**
   * Otimizar base de conhecimento
   */
  optimize() {
    // Remover exemplos negativos antigos
    const oneMonthAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
    this.negativeExamples = this.negativeExamples.filter(
      ex => ex.timestamp > oneMonthAgo
    );

    // Consolidar padrões similares
    this._consolidatePatterns();

    this._persist();
  }

  /**
   * Consolidar padrões similares
   * @private
   */
  _consolidatePatterns() {
    // Agrupar padrões por intent
    const byIntent = new Map();
    
    for (const [key, pattern] of this.patterns.entries()) {
      if (!byIntent.has(pattern.intent)) {
        byIntent.set(pattern.intent, []);
      }
      byIntent.get(pattern.intent).push({ key, pattern });
    }

    // Consolidar padrões com palavras sobrepostas
    for (const [intent, patterns] of byIntent.entries()) {
      for (let i = 0; i < patterns.length; i++) {
        for (let j = i + 1; j < patterns.length; j++) {
          const p1 = patterns[i].pattern;
          const p2 = patterns[j].pattern;
          
          const overlap = p1.words.filter(w => p2.words.includes(w)).length;
          
          // Se 80% de overlap, consolidar
          if (overlap / Math.min(p1.words.length, p2.words.length) > 0.8) {
            p1.occurrences += p2.occurrences;
            p1.confidence = Math.min(95, (p1.confidence + p2.confidence) / 2 + 5);
            this.patterns.delete(patterns[j].key);
            patterns.splice(j, 1);
            j--;
          }
        }
      }
    }
  }

  /**
   * Persistir em chrome.storage
   * @private
   */
  async _persist() {
    try {
      await chrome.storage.local.set({
        [this.storageKey]: {
          positiveExamples: this.positiveExamples,
          negativeExamples: this.negativeExamples,
          patterns: Array.from(this.patterns.entries()),
          feedbackData: this.feedbackData,
          savedAt: Date.now()
        }
      });
    } catch (e) {
      console.error('[LearningSystem] Persist error:', e);
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
        this.positiveExamples = data.positiveExamples || [];
        this.negativeExamples = data.negativeExamples || [];
        this.patterns = new Map(data.patterns || []);
        this.feedbackData = data.feedbackData || this.feedbackData;
      }
    } catch (e) {
      console.error('[LearningSystem] Load error:', e);
    }
  }

  /**
   * Exportar dados
   */
  export() {
    return {
      positiveExamples: this.positiveExamples,
      negativeExamples: this.negativeExamples,
      patterns: Array.from(this.patterns.entries()),
      feedbackData: this.feedbackData,
      stats: this.getFeedbackStats(),
      exportedAt: new Date().toISOString()
    };
  }

  /**
   * Importar dados
   */
  import(data) {
    try {
      if (data.positiveExamples) this.positiveExamples = data.positiveExamples;
      if (data.negativeExamples) this.negativeExamples = data.negativeExamples;
      if (data.patterns) this.patterns = new Map(data.patterns);
      if (data.feedbackData) this.feedbackData = data.feedbackData;
      
      this._persist();
      return true;
    } catch (e) {
      console.error('[LearningSystem] Import error:', e);
      return false;
    }
  }
}

/**
 * Analisador de feedback
 */
export class FeedbackAnalyzer {
  constructor() {
    this.sentimentKeywords = {
      positive: ['obrigado', 'obrigada', 'perfeito', 'ótimo', 'excelente', 'bom', 'ajudou', 'resolveu'],
      negative: ['não', 'ruim', 'errado', 'problema', 'incorreto', 'péssimo', 'horrível']
    };
  }

  /**
   * Analisar sentimento do feedback
   */
  analyzeSentiment(text) {
    const normalized = text.toLowerCase();
    let score = 0;

    for (const word of this.sentimentKeywords.positive) {
      if (normalized.includes(word)) score += 1;
    }

    for (const word of this.sentimentKeywords.negative) {
      if (normalized.includes(word)) score -= 1;
    }

    return {
      sentiment: score > 0 ? 'positive' : score < 0 ? 'negative' : 'neutral',
      score,
      confidence: Math.min(100, Math.abs(score) * 30)
    };
  }

  /**
   * Extrair keywords
   */
  extractKeywords(text) {
    const words = text.toLowerCase()
      .split(/\s+/)
      .filter(w => w.length > 3)
      .filter(w => !['para', 'com', 'por', 'que', 'uma', 'isso', 'esse', 'está'].includes(w));

    // Contar frequência
    const frequency = new Map();
    for (const word of words) {
      frequency.set(word, (frequency.get(word) || 0) + 1);
    }

    // Ordenar por frequência
    return Array.from(frequency.entries())
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([word, count]) => ({ word, count }));
  }

  /**
   * Gerar insights do feedback
   */
  generateInsights(feedbackData) {
    const insights = [];

    // Taxa de satisfação
    const total = feedbackData.positive + feedbackData.negative;
    if (total > 0) {
      const satisfactionRate = (feedbackData.positive / total) * 100;
      
      if (satisfactionRate < 60) {
        insights.push({
          type: 'warning',
          message: `Taxa de satisfação baixa: ${satisfactionRate.toFixed(1)}%`,
          priority: 'high'
        });
      } else if (satisfactionRate > 80) {
        insights.push({
          type: 'success',
          message: `Excelente taxa de satisfação: ${satisfactionRate.toFixed(1)}%`,
          priority: 'low'
        });
      }
    }

    // Correções frequentes
    if (feedbackData.corrections && feedbackData.corrections.length > 10) {
      insights.push({
        type: 'info',
        message: `${feedbackData.corrections.length} correções registradas - considere retreinar`,
        priority: 'medium'
      });
    }

    return insights;
  }
}
