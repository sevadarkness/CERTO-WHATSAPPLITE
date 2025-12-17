// smartbot-ia-core.js
// SmartBot IA - Sistema Completo de Atendimento Automatizado
// Compatível com Chrome Extensions (Manifest V3)
// Versão: 1.0.0
// Data: December 2024

(() => {
  'use strict';

  // ============================================================
  // UTILS E HELPERS
  // ============================================================

  /**
   * Utilitários globais para o SmartBot
   */
  const SmartBotUtils = {
    generateId: () => `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
    sanitizeText: (text) => {
      if (!text) return '';
      return String(text).replace(/[<>]/g, '');
    },
    formatDate: (date) => {
      if (!date) return '';
      const d = new Date(date);
      return d.toLocaleString('pt-BR');
    },
    hashString: (str) => {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash |= 0;
      }
      return hash.toString(36);
    },
    deepClone: (obj) => {
      try {
        return JSON.parse(JSON.stringify(obj));
      } catch (e) {
        return obj;
      }
    },
    debounce: (func, wait) => {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    },
    throttle: (func, limit) => {
      let inThrottle;
      return function(...args) {
        if (!inThrottle) {
          func.apply(this, args);
          inThrottle = true;
          setTimeout(() => inThrottle = false, limit);
        }
      };
    }
  };

  console.log('[SmartBot IA Core] Carregado v1.0.0');

  // Export for content.js integration
  if (typeof window !== 'undefined') {
    window.SmartBotUtils = SmartBotUtils;
  }

})();

  // ============================================================
  // CORE CLASSES
  // ============================================================

  // 1. AdvancedContextAnalyzer - Análise de perfis e contexto
  class AdvancedContextAnalyzer {
    constructor() {
      this.profiles = new Map();
      this.conversationHistory = new Map();
    }

    analyzeProfile(clientId, message, metadata = {}) {
      let profile = this.profiles.get(clientId) || this.createProfile(clientId);
      profile.messageCount++;
      profile.lastInteraction = Date.now();
      const detectedTone = this.detectTone(message);
      profile.preferredTone = this.updateTone(profile.preferredTone, detectedTone);
      const sentiment = this.analyzeSentiment(message);
      profile.satisfactionScore = this.updateSatisfaction(profile.satisfactionScore, sentiment);
      this.profiles.set(clientId, profile);
      return profile;
    }

    createProfile(clientId) {
      return {
        id: clientId,
        createdAt: Date.now(),
        messageCount: 0,
        preferredTone: 'neutral',
        satisfactionScore: 0.5,
        lastInteraction: Date.now(),
        averageResponseTime: 0
      };
    }

    detectTone(message) {
      const text = message.toLowerCase();
      const formalIndicators = ['senhor', 'senhora', 'prezado', 'cordialmente'];
      const casualIndicators = ['oi', 'olá', 'beleza', 'valeu', '😊'];
      const formalScore = formalIndicators.filter(w => text.includes(w)).length;
      const casualScore = casualIndicators.filter(w => text.includes(w)).length;
      if (formalScore > casualScore) return 'formal';
      if (casualScore > formalScore) return 'friendly';
      return 'neutral';
    }

    updateTone(currentTone, newTone) {
      const toneMap = { friendly: 0, neutral: 1, formal: 2 };
      const current = toneMap[currentTone] || 1;
      const detected = toneMap[newTone] || 1;
      const averaged = Math.round((current * 0.7 + detected * 0.3));
      return ['friendly', 'neutral', 'formal'][averaged] || 'neutral';
    }

    analyzeSentiment(message) {
      const text = message.toLowerCase();
      const positiveWords = ['bom', 'ótimo', 'excelente', 'obrigado', 'perfeito'];
      const negativeWords = ['ruim', 'péssimo', 'problema', 'insatisfeito'];
      let score = 0.5;
      positiveWords.forEach(w => { if (text.includes(w)) score += 0.1; });
      negativeWords.forEach(w => { if (text.includes(w)) score -= 0.1; });
      return Math.max(0, Math.min(1, score));
    }

    updateSatisfaction(currentScore, newSentiment) {
      return currentScore * 0.8 + newSentiment * 0.2;
    }

    trackConversation(clientId, message, response) {
      let history = this.conversationHistory.get(clientId) || [];
      history.push({
        timestamp: Date.now(),
        message,
        response,
        sentiment: this.analyzeSentiment(message)
      });
      if (history.length > 50) history = history.slice(-50);
      this.conversationHistory.set(clientId, history);
      return history;
    }

    getConversationContext(clientId, limit = 10) {
      const history = this.conversationHistory.get(clientId) || [];
      return history.slice(-limit);
    }
  }

  // 2. ContextualResponseGenerator - Gerador de respostas personalizadas
  class ContextualResponseGenerator {
    constructor() {
      this.templates = {
        greeting: {
          friendly: ['Oi {{nome}}! 😊 Como posso te ajudar hoje?', 'Olá {{nome}}! Tudo bem? ��'],
          neutral: ['Olá {{nome}}, como posso ajudá-lo?', 'Bom dia {{nome}}, em que posso auxiliar?'],
          formal: ['Prezado(a) {{nome}}, em que posso auxiliá-lo(a)?', 'Cordialmente, {{nome}}.']
        },
        clarification: {
          friendly: ['Hmm, não entendi direito 🤔 Pode explicar melhor?', 'Desculpa, não peguei! 😅'],
          neutral: ['Poderia esclarecer sua dúvida?', 'Não compreendi. Pode detalhar?'],
          formal: ['Perdão, não compreendi. Poderia reformular?', 'Necessito de mais detalhes.']
        },
        acknowledgment: {
          friendly: ['Entendi! ✅ Já vou resolver isso!', 'Beleza! 👍 Deixa comigo!'],
          neutral: ['Compreendido. Vou providenciar.', 'Entendi. Processando.'],
          formal: ['Perfeitamente compreendido. Providenciarei.', 'Entendido.']
        },
        apology: {
          friendly: ['Poxa, desculpa! 😔 Vou resolver agora!', 'Que chato! Sinto muito!'],
          neutral: ['Desculpe pelo inconveniente.', 'Peço desculpas.'],
          formal: ['Lamento profundamente.', 'Minhas sinceras desculpas.']
        },
        closing: {
          friendly: ['Qualquer coisa, só chamar! 😊', 'Disponha! 👋'],
          neutral: ['Estou à disposição.', 'Até breve.'],
          formal: ['Atenciosamente, fico à disposição.', 'Cordialmente.']
        }
      };
    }

    generateResponse(type, profile, variables = {}) {
      const tone = profile?.preferredTone || 'neutral';
      const templates = this.templates[type]?.[tone] || this.templates[type]?.neutral || [];
      if (templates.length === 0) return 'Desculpe, não consegui processar sua solicitação.';
      const template = templates[Math.floor(Math.random() * templates.length)];
      return this.applyVariables(template, variables);
    }

    applyVariables(template, variables) {
      let result = template;
      for (const [key, value] of Object.entries(variables)) {
        result = result.replace(new RegExp(`{{${key}}}`, 'g'), value || '');
      }
      return result.replace(/{{[^}]+}}/g, '');
    }

    personalizeResponse(response, profile) {
      if (!profile) return response;
      if (profile.preferredTone === 'formal') {
        return this.formalize(response);
      } else if (profile.preferredTone === 'friendly') {
        return this.casualize(response);
      }
      return response;
    }

    formalize(text) {
      return text.replace(/oi/gi, 'Olá').replace(/tá/gi, 'está').replace(/pra/gi, 'para')
        .replace(/vc/gi, 'você').replace(/😊|😄|👍/g, '');
    }

    casualize(text) {
      return text.replace(/você está/gi, 'tá').replace(/para/gi, 'pra')
        .replace(/Prezado/gi, 'Oi');
    }

    addCustomTemplate(type, tone, templates) {
      if (!this.templates[type]) this.templates[type] = {};
      if (!Array.isArray(this.templates[type][tone])) this.templates[type][tone] = [];
      this.templates[type][tone].push(...templates);
    }
  }

  // 3. IntelligentPriorityQueue - Gerenciamento de filas com priorização
  class IntelligentPriorityQueue {
    constructor() {
      this.queue = [];
      this.urgentKeywords = ['urgente', 'agora', 'imediato', 'rápido', 'emergência'];
    }

    enqueue(message, metadata = {}) {
      const priority = this.calculatePriority(message, metadata);
      const item = {
        id: SmartBotUtils.generateId(),
        message,
        metadata,
        priority,
        timestamp: Date.now(),
        processed: false
      };
      this.queue.push(item);
      this.queue.sort((a, b) => b.priority - a.priority);
      return item;
    }

    dequeue() {
      const item = this.queue.find(i => !i.processed);
      if (item) item.processed = true;
      return item;
    }

    calculatePriority(message, metadata) {
      let priority = 50;
      const sentiment = metadata.sentiment || 0.5;
      if (sentiment < 0.3) priority += 30;
      else if (sentiment > 0.7) priority += 10;
      const text = message.toLowerCase();
      const urgencyScore = this.urgentKeywords.filter(kw => text.includes(kw)).length;
      priority += urgencyScore * 15;
      const intent = metadata.intent || 'other';
      if (intent === 'complaint') priority += 25;
      else if (intent === 'question') priority += 10;
      if (metadata.isVIP) priority += 20;
      return Math.min(100, priority);
    }

    getStats() {
      const total = this.queue.length;
      const processed = this.queue.filter(i => i.processed).length;
      const pending = total - processed;
      const avgPriority = this.queue.reduce((sum, i) => sum + i.priority, 0) / (total || 1);
      return { total, processed, pending, avgPriority: Math.round(avgPriority) };
    }

    cleanup(olderThan = 3600000) {
      const cutoff = Date.now() - olderThan;
      this.queue = this.queue.filter(i => !i.processed || i.timestamp > cutoff);
    }

    peek() {
      return this.queue.find(i => !i.processed);
    }
  }

  // 4. ContinuousLearningSystem - Sistema de aprendizado contínuo
  class ContinuousLearningSystem {
    constructor() {
      this.feedbackQueue = [];
      this.knowledgeBase = new Map();
      this.batchSize = 10;
    }

    collectFeedback(messageId, feedback) {
      this.feedbackQueue.push({
        id: SmartBotUtils.generateId(),
        messageId,
        feedback,
        timestamp: Date.now()
      });
      if (this.feedbackQueue.length >= this.batchSize) {
        this.processBatch();
      }
    }

    processBatch() {
      if (this.feedbackQueue.length === 0) return;
      const batch = this.feedbackQueue.splice(0, this.batchSize);
      batch.forEach(item => {
        if (item.feedback.type === 'positive') this.learnPositive(item);
        else if (item.feedback.type === 'negative') this.learnNegative(item);
      });
      console.log('[ContinuousLearning] Processados', batch.length, 'feedbacks');
    }

    learnPositive(feedbackItem) {
      const { feedback } = feedbackItem;
      const { message, response, intent } = feedback;
      const pattern = this.extractPattern(message);
      let entry = this.knowledgeBase.get(pattern) || {
        pattern,
        responses: [],
        successCount: 0,
        intent
      };
      entry.responses.push(response);
      entry.successCount++;
      if (entry.responses.length > 5) entry.responses = entry.responses.slice(-5);
      this.knowledgeBase.set(pattern, entry);
    }

    learnNegative(feedbackItem) {
      const { feedback } = feedbackItem;
      const { message, response } = feedback;
      const pattern = this.extractPattern(message);
      const entry = this.knowledgeBase.get(pattern);
      if (entry) {
        entry.responses = entry.responses.filter(r => r !== response);
        if (entry.responses.length === 0) this.knowledgeBase.delete(pattern);
        else this.knowledgeBase.set(pattern, entry);
      }
    }

    extractPattern(message) {
      const normalized = message.toLowerCase().replace(/[^\w\s]/g, '');
      const keywords = normalized.split(/\s+/).filter(w => w.length > 3).slice(0, 5).join(' ');
      return keywords || normalized;
    }

    findLearnedResponse(message) {
      const pattern = this.extractPattern(message);
      const entry = this.knowledgeBase.get(pattern);
      if (entry && entry.responses.length > 0) {
        return {
          response: entry.responses[entry.responses.length - 1],
          confidence: Math.min(0.9, 0.5 + (entry.successCount * 0.1)),
          intent: entry.intent
        };
      }
      return null;
    }

    optimizeKnowledge() {
      for (const [pattern, entry] of this.knowledgeBase.entries()) {
        if (entry.successCount < 2 && entry.responses.length < 2) {
          this.knowledgeBase.delete(pattern);
        }
      }
      console.log('[ContinuousLearning] Base otimizada:', this.knowledgeBase.size, 'padrões');
    }

    exportKnowledge() {
      const entries = [];
      for (const [pattern, entry] of this.knowledgeBase.entries()) {
        entries.push({ pattern, ...entry });
      }
      return entries;
    }

    importKnowledge(entries) {
      entries.forEach(entry => {
        const { pattern, ...data } = entry;
        this.knowledgeBase.set(pattern, data);
      });
    }

    getStats() {
      return {
        knowledgeBaseSize: this.knowledgeBase.size,
        feedbackQueueSize: this.feedbackQueue.length
      };
    }
  }

  // 5. HumanAssistanceSystem - Escalonamento para atendentes
  class HumanAssistanceSystem {
    constructor() {
      this.agents = new Map();
      this.escalationQueue = [];
      this.activeHandoffs = new Map();
    }

    registerAgent(agentId, info) {
      this.agents.set(agentId, {
        id: agentId,
        name: info.name || 'Agente',
        status: 'available',
        skills: info.skills || [],
        currentLoad: 0,
        maxLoad: info.maxLoad || 5,
        registeredAt: Date.now()
      });
    }

    escalate(conversationId, reason, priority = 'normal', requiredSkills = []) {
      const escalation = {
        id: SmartBotUtils.generateId(),
        conversationId,
        reason,
        priority,
        requiredSkills,
        createdAt: Date.now(),
        status: 'pending'
      };
      this.escalationQueue.push(escalation);
      this.escalationQueue.sort((a, b) => {
        const priorityOrder = { urgent: 3, high: 2, normal: 1, low: 0 };
        return priorityOrder[b.priority] - priorityOrder[a.priority];
      });
      this.assignToAgent(escalation);
      return escalation;
    }

    assignToAgent(escalation) {
      const suitableAgent = this.findSuitableAgent(escalation.requiredSkills);
      if (suitableAgent) {
        escalation.status = 'assigned';
        escalation.assignedTo = suitableAgent.id;
        escalation.assignedAt = Date.now();
        suitableAgent.currentLoad++;
        this.activeHandoffs.set(escalation.conversationId, { escalation, agent: suitableAgent });
        this.agents.set(suitableAgent.id, suitableAgent);
        console.log('[HumanAssistance] Atribuído ao agente:', suitableAgent.name);
        return true;
      }
      return false;
    }

    findSuitableAgent(requiredSkills = []) {
      const availableAgents = Array.from(this.agents.values())
        .filter(agent => agent.status === 'available' && agent.currentLoad < agent.maxLoad);
      if (availableAgents.length === 0) return null;
      if (requiredSkills.length === 0) {
        return availableAgents.sort((a, b) => a.currentLoad - b.currentLoad)[0];
      }
      const skillMatches = availableAgents.filter(agent => 
        requiredSkills.every(skill => agent.skills.includes(skill))
      );
      return skillMatches.length > 0 
        ? skillMatches.sort((a, b) => a.currentLoad - b.currentLoad)[0]
        : availableAgents[0];
    }

    completeHandoff(conversationId) {
      const handoff = this.activeHandoffs.get(conversationId);
      if (handoff) {
        const agent = this.agents.get(handoff.agent.id);
        if (agent) {
          agent.currentLoad = Math.max(0, agent.currentLoad - 1);
          this.agents.set(agent.id, agent);
        }
        this.activeHandoffs.delete(conversationId);
        this.escalationQueue = this.escalationQueue.filter(e => e.conversationId !== conversationId);
        return true;
      }
      return false;
    }

    updateAgentStatus(agentId, status) {
      const agent = this.agents.get(agentId);
      if (agent) {
        agent.status = status;
        this.agents.set(agentId, agent);
        return true;
      }
      return false;
    }

    estimateWaitTime(priority = 'normal') {
      const queuePosition = this.escalationQueue
        .filter(e => e.status === 'pending')
        .findIndex(e => e.priority === priority) + 1;
      const avgHandlingTime = 300;
      const availableAgents = Array.from(this.agents.values())
        .filter(agent => agent.status === 'available').length;
      if (availableAgents === 0) return queuePosition * avgHandlingTime;
      return Math.ceil((queuePosition / availableAgents) * avgHandlingTime);
    }

    getStats() {
      const totalAgents = this.agents.size;
      const availableAgents = Array.from(this.agents.values())
        .filter(agent => agent.status === 'available').length;
      const busyAgents = Array.from(this.agents.values())
        .filter(agent => agent.currentLoad > 0).length;
      const queueSize = this.escalationQueue.filter(e => e.status === 'pending').length;
      const activeHandoffs = this.activeHandoffs.size;
      return { totalAgents, availableAgents, busyAgents, queueSize, activeHandoffs, avgWaitTime: this.estimateWaitTime() };
    }
  }

  // 6. SmartBotDashboard - Métricas e relatórios
  class SmartBotDashboard {
    constructor() {
      this.metrics = {
        totalMessages: 0,
        autoResponses: 0,
        humanHandoffs: 0,
        avgConfidence: 0,
        avgResponseTime: 0,
        intentDistribution: {},
        sentimentDistribution: { positive: 0, neutral: 0, negative: 0 },
        learningProgress: { patternsLearned: 0, feedbackCollected: 0 }
      };
      this.history = [];
      this.maxHistorySize = 1000;
    }

    recordInteraction(interaction) {
      this.metrics.totalMessages++;
      if (interaction.automated) this.metrics.autoResponses++;
      if (interaction.handoff) this.metrics.humanHandoffs++;
      const intent = interaction.intent || 'other';
      this.metrics.intentDistribution[intent] = (this.metrics.intentDistribution[intent] || 0) + 1;
      const sentiment = interaction.sentiment || 'neutral';
      if (this.metrics.sentimentDistribution[sentiment] !== undefined) {
        this.metrics.sentimentDistribution[sentiment]++;
      }
      if (interaction.confidence !== undefined) {
        const total = this.metrics.totalMessages;
        this.metrics.avgConfidence = 
          (this.metrics.avgConfidence * (total - 1) + interaction.confidence) / total;
      }
      if (interaction.responseTime !== undefined) {
        const total = this.metrics.autoResponses;
        this.metrics.avgResponseTime = 
          (this.metrics.avgResponseTime * (total - 1) + interaction.responseTime) / total;
      }
      this.history.push({ ...interaction, timestamp: Date.now() });
      if (this.history.length > this.maxHistorySize) {
        this.history = this.history.slice(-this.maxHistorySize);
      }
    }

    getPerformanceMetrics() {
      return {
        totalMessages: this.metrics.totalMessages,
        autoResponses: this.metrics.autoResponses,
        humanHandoffs: this.metrics.humanHandoffs,
        automationRate: this.metrics.totalMessages > 0 
          ? ((this.metrics.autoResponses / this.metrics.totalMessages) * 100).toFixed(2)
          : 0,
        avgConfidence: this.metrics.avgConfidence.toFixed(2),
        avgResponseTime: Math.round(this.metrics.avgResponseTime)
      };
    }

    getIntentDistribution() {
      return { ...this.metrics.intentDistribution };
    }

    getSentimentDistribution() {
      return { ...this.metrics.sentimentDistribution };
    }

    getLearningProgress() {
      return { ...this.metrics.learningProgress };
    }

    updateLearningProgress(patternsLearned, feedbackCollected) {
      this.metrics.learningProgress.patternsLearned = patternsLearned;
      this.metrics.learningProgress.feedbackCollected = feedbackCollected;
    }

    getTrends(period = '24h') {
      const now = Date.now();
      const periodMs = this.parsePeriod(period);
      const cutoff = now - periodMs;
      const recentHistory = this.history.filter(h => h.timestamp >= cutoff);
      const confidenceTrend = recentHistory.filter(h => h.confidence !== undefined).map(h => h.confidence);
      const successRate = recentHistory.filter(h => h.automated && !h.handoff).length / (recentHistory.length || 1) * 100;
      return {
        period,
        messageCount: recentHistory.length,
        avgConfidence: confidenceTrend.length > 0 
          ? (confidenceTrend.reduce((a, b) => a + b, 0) / confidenceTrend.length).toFixed(2)
          : 0,
        successRate: successRate.toFixed(2),
        handoffRate: ((this.metrics.humanHandoffs / this.metrics.totalMessages) * 100).toFixed(2)
      };
    }

    parsePeriod(period) {
      const units = { 'h': 3600000, 'd': 86400000, 'w': 604800000 };
      const match = period.match(/^(\d+)([hdw])$/);
      if (match) {
        const value = parseInt(match[1]);
        const unit = match[2];
        return value * units[unit];
      }
      return 86400000;
    }

    generateReport() {
      return {
        summary: this.getPerformanceMetrics(),
        intents: this.getIntentDistribution(),
        sentiments: this.getSentimentDistribution(),
        learning: this.getLearningProgress(),
        trends: { last24h: this.getTrends('24h'), last7d: this.getTrends('7d') },
        generatedAt: new Date().toISOString()
      };
    }

    reset() {
      this.metrics = {
        totalMessages: 0,
        autoResponses: 0,
        humanHandoffs: 0,
        avgConfidence: 0,
        avgResponseTime: 0,
        intentDistribution: {},
        sentimentDistribution: { positive: 0, neutral: 0, negative: 0 },
        learningProgress: { patternsLearned: 0, feedbackCollected: 0 }
      };
      this.history = [];
    }
  }


  // ============================================================
  // AUXILIARY MANAGERS (Part 1)
  // ============================================================

  // 7. SchedulerSystem - Agendamento de tarefas
  class SchedulerSystem {
    constructor() {
      this.tasks = new Map();
      this.timers = new Map();
    }

    schedule(taskId, callback, delay, repeat = false) {
      const task = {
        id: taskId,
        callback,
        delay,
        repeat,
        scheduledAt: Date.now(),
        nextRun: Date.now() + delay
      };
      this.tasks.set(taskId, task);
      const timerId = setTimeout(() => this.executeTask(taskId), delay);
      this.timers.set(taskId, timerId);
      return taskId;
    }

    executeTask(taskId) {
      const task = this.tasks.get(taskId);
      if (task) {
        try {
          task.callback();
          if (task.repeat) {
            this.schedule(taskId, task.callback, task.delay, true);
          } else {
            this.cancel(taskId);
          }
        } catch (e) {
          console.error('[Scheduler] Erro:', e);
        }
      }
    }

    cancel(taskId) {
      const timerId = this.timers.get(taskId);
      if (timerId) {
        clearTimeout(timerId);
        this.timers.delete(taskId);
      }
      this.tasks.delete(taskId);
    }

    cancelAll() {
      for (const timerId of this.timers.values()) {
        clearTimeout(timerId);
      }
      this.tasks.clear();
      this.timers.clear();
    }
  }

  // 8. FeedbackAnalyzer - Análise de feedback
  class FeedbackAnalyzer {
    constructor() {
      this.sentimentKeywords = {
        positive: ['bom', 'ótimo', 'excelente', 'perfeito', 'obrigado'],
        negative: ['ruim', 'péssimo', 'problema', 'erro', 'insatisfeito']
      };
    }

    analyze(feedback) {
      const sentiment = this.analyzeSentiment(feedback.text || '');
      const keywords = this.extractKeywords(feedback.text || '');
      return { sentiment, keywords, score: sentiment.score };
    }

    analyzeSentiment(text) {
      const lowerText = text.toLowerCase();
      let score = 0;
      let type = 'neutral';
      
      for (const word of this.sentimentKeywords.positive) {
        if (lowerText.includes(word)) score += 0.2;
      }
      for (const word of this.sentimentKeywords.negative) {
        if (lowerText.includes(word)) score -= 0.2;
      }
      
      score = Math.max(-1, Math.min(1, score));
      if (score > 0.2) type = 'positive';
      else if (score < -0.2) type = 'negative';
      
      return { score, type };
    }

    extractKeywords(text) {
      const words = text.toLowerCase().split(/\s+/);
      return words.filter(w => w.length > 3).slice(0, 10);
    }
  }

  // 9. NotificationManager - Sistema de notificações
  class NotificationManager {
    constructor() {
      this.notifications = [];
      this.templates = {
        info: { icon: 'ℹ️', color: 'blue' },
        success: { icon: '✅', color: 'green' },
        warning: { icon: '⚠️', color: 'yellow' },
        error: { icon: '❌', color: 'red' }
      };
    }

    notify(type, message, duration = 5000) {
      const notification = {
        id: SmartBotUtils.generateId(),
        type,
        message,
        timestamp: Date.now(),
        template: this.templates[type] || this.templates.info
      };
      this.notifications.push(notification);
      console.log(`[${notification.template.icon}] ${message}`);
      setTimeout(() => this.dismiss(notification.id), duration);
      return notification;
    }

    dismiss(notificationId) {
      this.notifications = this.notifications.filter(n => n.id !== notificationId);
    }

    getAll() {
      return [...this.notifications];
    }
  }

  // 10. AuthManager - Autenticação e autorização
  class AuthManager {
    constructor() {
      this.users = new Map();
      this.sessions = new Map();
      this.permissions = new Map();
    }

    authenticate(username, token) {
      const user = this.users.get(username);
      if (user && user.token === token) {
        const sessionId = SmartBotUtils.generateId();
        this.sessions.set(sessionId, {
          userId: user.id,
          username,
          createdAt: Date.now(),
          expiresAt: Date.now() + 86400000
        });
        return { sessionId, user };
      }
      return null;
    }

    authorize(sessionId, permission) {
      const session = this.sessions.get(sessionId);
      if (!session || session.expiresAt < Date.now()) return false;
      const userPermissions = this.permissions.get(session.userId) || [];
      return userPermissions.includes(permission) || userPermissions.includes('*');
    }

    registerUser(username, token, permissions = []) {
      const userId = SmartBotUtils.generateId();
      this.users.set(username, { id: userId, username, token });
      this.permissions.set(userId, permissions);
      return userId;
    }
  }

  // 11. LogManager - Sistema de logs
  class LogManager {
    constructor() {
      this.logs = [];
      this.maxLogs = 1000;
      this.levels = ['debug', 'info', 'warn', 'error'];
    }

    log(level, message, data = {}) {
      const entry = {
        id: SmartBotUtils.generateId(),
        level,
        message,
        data,
        timestamp: Date.now()
      };
      this.logs.push(entry);
      if (this.logs.length > this.maxLogs) this.logs = this.logs.slice(-this.maxLogs);
      console[level] ? console[level](`[Log]`, message, data) : console.log(`[Log]`, message, data);
    }

    debug(msg, data) { this.log('debug', msg, data); }
    info(msg, data) { this.log('info', msg, data); }
    warn(msg, data) { this.log('warn', msg, data); }
    error(msg, data) { this.log('error', msg, data); }

    getLogs(level = null, limit = 100) {
      let filtered = level ? this.logs.filter(l => l.level === level) : this.logs;
      return filtered.slice(-limit);
    }

    clear() { this.logs = []; }
  }

  // 12. ApiManager - Integração com APIs externas
  class ApiManager {
    constructor() {
      this.endpoints = new Map();
      this.cache = new Map();
      this.cacheTTL = 300000; // 5 min
    }

    registerEndpoint(name, config) {
      this.endpoints.set(name, config);
    }

    async call(endpointName, params = {}) {
      const endpoint = this.endpoints.get(endpointName);
      if (!endpoint) throw new Error(`Endpoint ${endpointName} não encontrado`);
      
      const cacheKey = `${endpointName}:${JSON.stringify(params)}`;
      const cached = this.cache.get(cacheKey);
      if (cached && (Date.now() - cached.timestamp < this.cacheTTL)) {
        return cached.data;
      }
      
      try {
        const url = endpoint.url + (endpoint.queryString ? `?${endpoint.queryString}` : '');
        const response = await fetch(url, {
          method: endpoint.method || 'GET',
          headers: endpoint.headers || {},
          body: params ? JSON.stringify(params) : undefined
        });
        const data = await response.json();
        this.cache.set(cacheKey, { data, timestamp: Date.now() });
        return data;
      } catch (error) {
        console.error('[ApiManager] Erro:', error);
        throw error;
      }
    }

    clearCache() { this.cache.clear(); }
  }

  // 13. ConfigManager - Gerenciamento de configurações
  class ConfigManager {
    constructor() {
      this.config = {
        bot: {
          name: 'SmartBot',
          version: '1.0.0',
          language: 'pt-BR'
        },
        features: {
          autoResponse: true,
          learning: true,
          humanHandoff: true
        },
        limits: {
          maxQueueSize: 100,
          maxHistorySize: 1000,
          maxCacheSize: 500
        }
      };
    }

    get(path) {
      const keys = path.split('.');
      let value = this.config;
      for (const key of keys) {
        value = value?.[key];
        if (value === undefined) return null;
      }
      return value;
    }

    set(path, value) {
      const keys = path.split('.');
      let target = this.config;
      for (let i = 0; i < keys.length - 1; i++) {
        if (!target[keys[i]]) target[keys[i]] = {};
        target = target[keys[i]];
      }
      target[keys[keys.length - 1]] = value;
    }

    getAll() {
      return SmartBotUtils.deepClone(this.config);
    }

    reset() {
      this.config = this.getAll();
    }
  }


  // ============================================================
  // AUXILIARY MANAGERS (Part 2)
  // ============================================================

  // 14. NlpManager - Processamento de linguagem natural
  class NlpManager {
    constructor() {
      this.patterns = {
        greeting: /\b(oi|olá|ola|bom dia|boa tarde|boa noite)\b/i,
        farewell: /\b(tchau|adeus|até|bye)\b/i,
        question: /\?|como|quando|onde|por que|quem|qual/i,
        affirmative: /\b(sim|claro|ok|certo|pode|yes)\b/i,
        negative: /\b(não|nao|never|jamais)\b/i
      };
    }

    detectIntent(text) {
      for (const [intent, pattern] of Object.entries(this.patterns)) {
        if (pattern.test(text)) return intent;
      }
      return 'other';
    }

    extractEntities(text) {
      const entities = {
        numbers: text.match(/\d+/g) || [],
        emails: text.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g) || [],
        phones: text.match(/(\+?\d{1,3}[\s-]?)?\(?\d{2,3}\)?[\s-]?\d{3,5}[\s-]?\d{4}/g) || []
      };
      return entities;
    }

    tokenize(text) {
      return text.toLowerCase().split(/\s+/).filter(w => w.length > 0);
    }
  }

  // 15. DialogManager - Gerenciamento de fluxos de diálogo
  class DialogManager {
    constructor() {
      this.flows = new Map();
      this.activeDialogs = new Map();
    }

    registerFlow(flowId, steps) {
      this.flows.set(flowId, { id: flowId, steps, createdAt: Date.now() });
    }

    startDialog(userId, flowId) {
      const flow = this.flows.get(flowId);
      if (!flow) return null;
      const dialog = {
        id: SmartBotUtils.generateId(),
        userId,
        flowId,
        currentStep: 0,
        data: {},
        startedAt: Date.now()
      };
      this.activeDialogs.set(userId, dialog);
      return dialog;
    }

    processInput(userId, input) {
      const dialog = this.activeDialogs.get(userId);
      if (!dialog) return null;
      const flow = this.flows.get(dialog.flowId);
      if (!flow) return null;
      const step = flow.steps[dialog.currentStep];
      if (step.validate && !step.validate(input)) {
        return { valid: false, message: step.errorMessage || 'Entrada inválida' };
      }
      dialog.data[step.key] = input;
      dialog.currentStep++;
      if (dialog.currentStep >= flow.steps.length) {
        this.activeDialogs.delete(userId);
        return { completed: true, data: dialog.data };
      }
      return { valid: true, nextStep: flow.steps[dialog.currentStep] };
    }

    cancelDialog(userId) {
      return this.activeDialogs.delete(userId);
    }
  }

  // 16. ContextManager - Gerenciamento de contexto
  class ContextManager {
    constructor() {
      this.contexts = new Map();
      this.maxContextAge = 3600000; // 1h
    }

    setContext(userId, key, value, ttl = this.maxContextAge) {
      if (!this.contexts.has(userId)) {
        this.contexts.set(userId, new Map());
      }
      const userContext = this.contexts.get(userId);
      userContext.set(key, { value, expiresAt: Date.now() + ttl });
    }

    getContext(userId, key) {
      const userContext = this.contexts.get(userId);
      if (!userContext) return null;
      const entry = userContext.get(key);
      if (!entry) return null;
      if (entry.expiresAt < Date.now()) {
        userContext.delete(key);
        return null;
      }
      return entry.value;
    }

    clearContext(userId, key = null) {
      if (key) {
        const userContext = this.contexts.get(userId);
        if (userContext) userContext.delete(key);
      } else {
        this.contexts.delete(userId);
      }
    }

    getAllContext(userId) {
      const userContext = this.contexts.get(userId);
      if (!userContext) return {};
      const result = {};
      for (const [key, entry] of userContext.entries()) {
        if (entry.expiresAt > Date.now()) {
          result[key] = entry.value;
        }
      }
      return result;
    }
  }

  // 17. EntityManager - Extração de entidades
  class EntityManager {
    constructor() {
      this.extractors = new Map();
    }

    registerExtractor(name, pattern) {
      this.extractors.set(name, pattern);
    }

    extract(text) {
      const entities = {};
      for (const [name, pattern] of this.extractors.entries()) {
        if (pattern instanceof RegExp) {
          const matches = text.match(pattern);
          entities[name] = matches || [];
        } else if (typeof pattern === 'function') {
          entities[name] = pattern(text);
        }
      }
      return entities;
    }
  }

  // 18. IntentManager - Detecção de intenções
  class IntentManager {
    constructor() {
      this.intents = new Map();
    }

    registerIntent(name, patterns, confidence = 0.8) {
      this.intents.set(name, { patterns, confidence });
    }

    detectIntent(text) {
      const lowerText = text.toLowerCase();
      let bestMatch = { name: 'unknown', confidence: 0 };
      
      for (const [name, intent] of this.intents.entries()) {
        for (const pattern of intent.patterns) {
          if (pattern instanceof RegExp && pattern.test(lowerText)) {
            if (intent.confidence > bestMatch.confidence) {
              bestMatch = { name, confidence: intent.confidence };
            }
          } else if (typeof pattern === 'string' && lowerText.includes(pattern)) {
            if (intent.confidence > bestMatch.confidence) {
              bestMatch = { name, confidence: intent.confidence };
            }
          }
        }
      }
      
      return bestMatch;
    }
  }

  // 19. SessionManager - Gerenciamento de sessões
  class SessionManager {
    constructor() {
      this.sessions = new Map();
      this.sessionTTL = 1800000; // 30 min
    }

    createSession(userId, data = {}) {
      const sessionId = SmartBotUtils.generateId();
      this.sessions.set(sessionId, {
        id: sessionId,
        userId,
        data,
        createdAt: Date.now(),
        lastActivity: Date.now(),
        expiresAt: Date.now() + this.sessionTTL
      });
      return sessionId;
    }

    getSession(sessionId) {
      const session = this.sessions.get(sessionId);
      if (!session) return null;
      if (session.expiresAt < Date.now()) {
        this.sessions.delete(sessionId);
        return null;
      }
      session.lastActivity = Date.now();
      return session;
    }

    updateSession(sessionId, data) {
      const session = this.getSession(sessionId);
      if (session) {
        session.data = { ...session.data, ...data };
        session.lastActivity = Date.now();
        return true;
      }
      return false;
    }

    destroySession(sessionId) {
      return this.sessions.delete(sessionId);
    }

    cleanup() {
      const now = Date.now();
      for (const [id, session] of this.sessions.entries()) {
        if (session.expiresAt < now) {
          this.sessions.delete(id);
        }
      }
    }
  }

  // 20. CacheManager - Sistema de cache LRU
  class CacheManager {
    constructor(maxSize = 100) {
      this.cache = new Map();
      this.maxSize = maxSize;
    }

    set(key, value, ttl = 300000) {
      if (this.cache.size >= this.maxSize) {
        const firstKey = this.cache.keys().next().value;
        this.cache.delete(firstKey);
      }
      this.cache.set(key, { value, expiresAt: Date.now() + ttl });
    }

    get(key) {
      const entry = this.cache.get(key);
      if (!entry) return null;
      if (entry.expiresAt < Date.now()) {
        this.cache.delete(key);
        return null;
      }
      // Move to end (LRU)
      this.cache.delete(key);
      this.cache.set(key, entry);
      return entry.value;
    }

    has(key) {
      return this.get(key) !== null;
    }

    delete(key) {
      return this.cache.delete(key);
    }

    clear() {
      this.cache.clear();
    }

    size() {
      return this.cache.size;
    }
  }

  // 21. WebhookManager - Gerenciamento de webhooks
  class WebhookManager {
    constructor() {
      this.webhooks = new Map();
    }

    register(event, url, headers = {}) {
      if (!this.webhooks.has(event)) {
        this.webhooks.set(event, []);
      }
      this.webhooks.get(event).push({ url, headers, createdAt: Date.now() });
    }

    async trigger(event, data) {
      const hooks = this.webhooks.get(event);
      if (!hooks) return;
      
      const promises = hooks.map(hook => 
        fetch(hook.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', ...hook.headers },
          body: JSON.stringify({ event, data, timestamp: Date.now() })
        }).catch(err => console.error('[Webhook] Error:', err))
      );
      
      await Promise.all(promises);
    }

    unregister(event, url) {
      const hooks = this.webhooks.get(event);
      if (hooks) {
        const filtered = hooks.filter(h => h.url !== url);
        this.webhooks.set(event, filtered);
      }
    }
  }

  // 22. PluginManager - Sistema de plugins
  class PluginManager {
    constructor() {
      this.plugins = new Map();
    }

    register(name, plugin) {
      if (typeof plugin.init === 'function') {
        try {
          plugin.init();
          this.plugins.set(name, plugin);
          console.log('[PluginManager] Plugin registrado:', name);
        } catch (e) {
          console.error('[PluginManager] Erro ao inicializar:', e);
        }
      }
    }

    execute(pluginName, method, ...args) {
      const plugin = this.plugins.get(pluginName);
      if (!plugin) {
        console.error('[PluginManager] Plugin não encontrado:', pluginName);
        return null;
      }
      if (typeof plugin[method] === 'function') {
        try {
          return plugin[method](...args);
        } catch (e) {
          console.error('[PluginManager] Erro ao executar:', e);
          return null;
        }
      }
      return null;
    }

    unregister(name) {
      const plugin = this.plugins.get(name);
      if (plugin && typeof plugin.destroy === 'function') {
        plugin.destroy();
      }
      return this.plugins.delete(name);
    }

    listPlugins() {
      return Array.from(this.plugins.keys());
    }
  }


  // ============================================================
  // AUXILIARY MANAGERS (Part 3)
  // ============================================================

  // 23. EventManager - Sistema de eventos
  class EventManager {
    constructor() {
      this.listeners = new Map();
    }

    on(event, callback) {
      if (!this.listeners.has(event)) {
        this.listeners.set(event, []);
      }
      this.listeners.get(event).push(callback);
      return () => this.off(event, callback);
    }

    off(event, callback) {
      const callbacks = this.listeners.get(event);
      if (callbacks) {
        const index = callbacks.indexOf(callback);
        if (index !== -1) {
          callbacks.splice(index, 1);
        }
      }
    }

    emit(event, data) {
      const callbacks = this.listeners.get(event);
      if (callbacks) {
        callbacks.forEach(cb => {
          try {
            cb(data);
          } catch (e) {
            console.error('[EventManager] Error in callback:', e);
          }
        });
      }
    }

    removeAllListeners(event) {
      if (event) {
        this.listeners.delete(event);
      } else {
        this.listeners.clear();
      }
    }
  }

  // 24. AnalyticsManager - Métricas e analytics
  class AnalyticsManager {
    constructor() {
      this.events = [];
      this.metrics = {};
    }

    track(eventName, properties = {}) {
      this.events.push({
        id: SmartBotUtils.generateId(),
        name: eventName,
        properties,
        timestamp: Date.now()
      });
      this.updateMetric(eventName);
    }

    updateMetric(metricName) {
      this.metrics[metricName] = (this.metrics[metricName] || 0) + 1;
    }

    getMetrics() {
      return { ...this.metrics };
    }

    getEvents(eventName = null, limit = 100) {
      let filtered = eventName ? this.events.filter(e => e.name === eventName) : this.events;
      return filtered.slice(-limit);
    }

    clear() {
      this.events = [];
      this.metrics = {};
    }
  }

  // 25. QueueManager - Gerenciamento de filas genéricas
  class QueueManager {
    constructor() {
      this.queues = new Map();
    }

    createQueue(name, config = {}) {
      this.queues.set(name, {
        name,
        items: [],
        config: { maxSize: config.maxSize || 1000, ...config },
        createdAt: Date.now()
      });
    }

    enqueue(queueName, item) {
      const queue = this.queues.get(queueName);
      if (!queue) return false;
      if (queue.items.length >= queue.config.maxSize) {
        queue.items.shift();
      }
      queue.items.push({ id: SmartBotUtils.generateId(), data: item, timestamp: Date.now() });
      return true;
    }

    dequeue(queueName) {
      const queue = this.queues.get(queueName);
      return queue ? queue.items.shift() : null;
    }

    peek(queueName) {
      const queue = this.queues.get(queueName);
      return queue && queue.items.length > 0 ? queue.items[0] : null;
    }

    size(queueName) {
      const queue = this.queues.get(queueName);
      return queue ? queue.items.length : 0;
    }

    clear(queueName) {
      const queue = this.queues.get(queueName);
      if (queue) queue.items = [];
    }
  }

  // 26. RateLimiter - Controle de taxa
  class RateLimiter {
    constructor(maxRequests = 60, windowMs = 60000) {
      this.maxRequests = maxRequests;
      this.windowMs = windowMs;
      this.requests = new Map();
    }

    checkLimit(key) {
      const now = Date.now();
      const userRequests = this.requests.get(key) || [];
      const windowStart = now - this.windowMs;
      const recentRequests = userRequests.filter(t => t > windowStart);
      
      if (recentRequests.length >= this.maxRequests) {
        return false;
      }
      
      recentRequests.push(now);
      this.requests.set(key, recentRequests);
      return true;
    }

    getRemainingRequests(key) {
      const now = Date.now();
      const userRequests = this.requests.get(key) || [];
      const windowStart = now - this.windowMs;
      const recentRequests = userRequests.filter(t => t > windowStart);
      return Math.max(0, this.maxRequests - recentRequests.length);
    }

    reset(key) {
      this.requests.delete(key);
    }
  }

  // 27. MiddlewareManager - Sistema de middlewares
  class MiddlewareManager {
    constructor() {
      this.middlewares = [];
    }

    use(middleware) {
      this.middlewares.push(middleware);
    }

    async execute(context) {
      let index = 0;
      const next = async () => {
        if (index < this.middlewares.length) {
          const middleware = this.middlewares[index++];
          await middleware(context, next);
        }
      };
      await next();
      return context;
    }
  }

  // 28. PermissionManager - Gerenciamento de permissões
  class PermissionManager {
    constructor() {
      this.roles = new Map();
      this.userRoles = new Map();
    }

    createRole(roleName, permissions) {
      this.roles.set(roleName, new Set(permissions));
    }

    assignRole(userId, roleName) {
      if (!this.userRoles.has(userId)) {
        this.userRoles.set(userId, new Set());
      }
      this.userRoles.get(userId).add(roleName);
    }

    hasPermission(userId, permission) {
      const userRoles = this.userRoles.get(userId);
      if (!userRoles) return false;
      
      for (const roleName of userRoles) {
        const rolePermissions = this.roles.get(roleName);
        if (rolePermissions && (rolePermissions.has(permission) || rolePermissions.has('*'))) {
          return true;
        }
      }
      return false;
    }

    revokeRole(userId, roleName) {
      const userRoles = this.userRoles.get(userId);
      if (userRoles) {
        userRoles.delete(roleName);
      }
    }
  }

  // 29. LocaleManager - Internacionalização (i18n)
  class LocaleManager {
    constructor(defaultLocale = 'pt-BR') {
      this.defaultLocale = defaultLocale;
      this.currentLocale = defaultLocale;
      this.translations = new Map();
    }

    addTranslations(locale, translations) {
      this.translations.set(locale, translations);
    }

    setLocale(locale) {
      if (this.translations.has(locale)) {
        this.currentLocale = locale;
        return true;
      }
      return false;
    }

    t(key, params = {}) {
      const translations = this.translations.get(this.currentLocale) || {};
      let text = translations[key] || key;
      
      for (const [param, value] of Object.entries(params)) {
        text = text.replace(new RegExp(`{{${param}}}`, 'g'), value);
      }
      
      return text;
    }

    getCurrentLocale() {
      return this.currentLocale;
    }
  }

  // 30. ChannelConnector - Conexão com canais (WhatsApp, Telegram, etc.)
  class ChannelConnector {
    constructor() {
      this.channels = new Map();
      this.activeConnections = new Map();
    }

    registerChannel(channelName, config) {
      this.channels.set(channelName, {
        name: channelName,
        config,
        status: 'registered',
        registeredAt: Date.now()
      });
    }

    async connect(channelName) {
      const channel = this.channels.get(channelName);
      if (!channel) return false;
      
      try {
        channel.status = 'connecting';
        // Aqui seria a lógica de conexão real
        // Para WhatsApp, já estamos conectados via content.js
        channel.status = 'connected';
        channel.connectedAt = Date.now();
        this.activeConnections.set(channelName, channel);
        console.log('[ChannelConnector] Conectado ao canal:', channelName);
        return true;
      } catch (e) {
        channel.status = 'error';
        channel.error = e.message;
        console.error('[ChannelConnector] Erro:', e);
        return false;
      }
    }

    disconnect(channelName) {
      const channel = this.channels.get(channelName);
      if (channel) {
        channel.status = 'disconnected';
        this.activeConnections.delete(channelName);
        return true;
      }
      return false;
    }

    async sendMessage(channelName, recipient, message) {
      const connection = this.activeConnections.get(channelName);
      if (!connection || connection.status !== 'connected') {
        throw new Error(`Canal ${channelName} não está conectado`);
      }
      
      // Aqui seria a lógica de envio específica do canal
      // Para WhatsApp, integrar com as funções do content.js
      console.log(`[ChannelConnector] Enviando mensagem via ${channelName}:`, message);
      return { success: true, messageId: SmartBotUtils.generateId() };
    }

    getChannelStatus(channelName) {
      const channel = this.channels.get(channelName);
      return channel ? channel.status : 'not_found';
    }

    listChannels() {
      return Array.from(this.channels.keys());
    }
  }

  // ============================================================
  // MAIN SMARTBOT SYSTEM
  // ============================================================

  /**
   * Sistema Principal do SmartBot IA
   * Integra todos os componentes e gerencia o ciclo de vida
   */
  class SmartBotIACore {
    constructor() {
      this.version = '1.0.0';
      this.initialized = false;
      
      // Componentes principais
      this.contextAnalyzer = new AdvancedContextAnalyzer();
      this.responseGenerator = new ContextualResponseGenerator();
      this.priorityQueue = new IntelligentPriorityQueue();
      this.learningSystem = new ContinuousLearningSystem();
      this.humanAssistance = new HumanAssistanceSystem();
      this.dashboard = new SmartBotDashboard();
      
      // Gerenciadores auxiliares
      this.scheduler = new SchedulerSystem();
      this.feedbackAnalyzer = new FeedbackAnalyzer();
      this.notifications = new NotificationManager();
      this.auth = new AuthManager();
      this.logger = new LogManager();
      this.api = new ApiManager();
      this.config = new ConfigManager();
      this.nlp = new NlpManager();
      this.dialog = new DialogManager();
      this.context = new ContextManager();
      this.entity = new EntityManager();
      this.intent = new IntentManager();
      this.session = new SessionManager();
      this.cache = new CacheManager();
      this.webhook = new WebhookManager();
      this.plugin = new PluginManager();
      this.events = new EventManager();
      this.analytics = new AnalyticsManager();
      this.queue = new QueueManager();
      this.rateLimiter = new RateLimiter();
      this.middleware = new MiddlewareManager();
      this.permissions = new PermissionManager();
      this.locale = new LocaleManager();
      this.channels = new ChannelConnector();
    }

    /**
     * Inicializa o sistema
     */
    async initialize(config = {}) {
      if (this.initialized) {
        this.logger.warn('Sistema já inicializado');
        return this;
      }

      this.logger.info('Inicializando SmartBot IA Core v' + this.version);
      
      // Aplicar configurações
      if (config.bot) this.config.set('bot', config.bot);
      if (config.features) this.config.set('features', config.features);
      if (config.limits) this.config.set('limits', config.limits);
      
      // Registrar canal WhatsApp
      this.channels.registerChannel('whatsapp', { type: 'messaging' });
      await this.channels.connect('whatsapp');
      
      // Configurar intents padrão
      this.setupDefaultIntents();
      
      // Iniciar limpeza periódica
      this.scheduler.schedule('cleanup', () => {
        this.priorityQueue.cleanup();
        this.session.cleanup();
        this.cache.clear();
      }, 3600000, true); // A cada hora
      
      this.initialized = true;
      this.logger.info('SmartBot IA Core inicializado com sucesso');
      this.events.emit('system:initialized', { version: this.version });
      
      return this;
    }

    /**
     * Configura intenções padrão
     */
    setupDefaultIntents() {
      this.intent.registerIntent('greeting', [/\b(oi|olá|bom dia|boa tarde)\b/i], 0.9);
      this.intent.registerIntent('farewell', [/\b(tchau|adeus|até)\b/i], 0.9);
      this.intent.registerIntent('thanks', [/\b(obrigado|obrigada|valeu)\b/i], 0.8);
      this.intent.registerIntent('question', [/\?/], 0.7);
      this.intent.registerIntent('complaint', [/\b(problema|erro|reclamação)\b/i], 0.8);
    }

    /**
     * Processa mensagem recebida
     */
    async processMessage(clientId, message, metadata = {}) {
      this.analytics.track('message:received', { clientId });
      
      try {
        // 1. Analisar perfil do cliente
        const profile = this.contextAnalyzer.analyzeProfile(clientId, message, metadata);
        
        // 2. Detectar intenção
        const intentResult = this.intent.detectIntent(message);
        
        // 3. Adicionar à fila de priorização
        const queueItem = this.priorityQueue.enqueue(message, {
          clientId,
          profile,
          intent: intentResult.name,
          sentiment: profile.satisfactionScore,
          ...metadata
        });
        
        // 4. Buscar resposta aprendida
        const learnedResponse = this.learningSystem.findLearnedResponse(message);
        
        // 5. Gerar resposta
        let response;
        if (learnedResponse && learnedResponse.confidence > 0.7) {
          response = learnedResponse.response;
        } else {
          response = this.responseGenerator.generateResponse(
            intentResult.name,
            profile,
            { nome: metadata.clientName || 'Cliente' }
          );
        }
        
        // 6. Personalizar resposta
        response = this.responseGenerator.personalizeResponse(response, profile);
        
        // 7. Registrar interação
        this.dashboard.recordInteraction({
          clientId,
          message,
          response,
          intent: intentResult.name,
          sentiment: profile.satisfactionScore > 0.6 ? 'positive' : (profile.satisfactionScore < 0.4 ? 'negative' : 'neutral'),
          confidence: learnedResponse?.confidence || intentResult.confidence,
          automated: true,
          responseTime: Date.now() - queueItem.timestamp
        });
        
        // 8. Rastrear conversa
        this.contextAnalyzer.trackConversation(clientId, message, response);
        
        // 9. Emitir evento
        this.events.emit('message:processed', { clientId, message, response, profile });
        
        return {
          success: true,
          response,
          confidence: learnedResponse?.confidence || intentResult.confidence,
          intent: intentResult.name,
          profile
        };
        
      } catch (error) {
        this.logger.error('Erro ao processar mensagem', { error: error.message, clientId });
        this.events.emit('message:error', { clientId, error: error.message });
        
        return {
          success: false,
          error: error.message,
          response: 'Desculpe, ocorreu um erro ao processar sua mensagem.'
        };
      }
    }

    /**
     * Processa feedback
     */
    processFeedback(messageId, feedbackType, details = {}) {
      this.learningSystem.collectFeedback(messageId, {
        type: feedbackType,
        ...details
      });
      
      this.analytics.track('feedback:received', { messageId, type: feedbackType });
      
      // Atualizar progresso de aprendizado no dashboard
      const stats = this.learningSystem.getStats();
      this.dashboard.updateLearningProgress(
        stats.knowledgeBaseSize,
        stats.feedbackQueueSize
      );
    }

    /**
     * Escalona para atendimento humano
     */
    escalateToHuman(conversationId, reason, priority = 'normal') {
      const escalation = this.humanAssistance.escalate(conversationId, reason, priority);
      this.analytics.track('escalation:created', { conversationId, reason, priority });
      this.events.emit('escalation:created', escalation);
      return escalation;
    }

    /**
     * Obtém relatório completo
     */
    getReport() {
      const report = this.dashboard.generateReport();
      report.system = {
        version: this.version,
        initialized: this.initialized,
        uptime: Date.now() - (this.initTimestamp || Date.now())
      };
      report.learning = this.learningSystem.getStats();
      report.humanAssistance = this.humanAssistance.getStats();
      report.queue = this.priorityQueue.getStats();
      return report;
    }

    /**
     * Reseta o sistema (apenas métricas, mantém aprendizado)
     */
    reset() {
      this.dashboard.reset();
      this.analytics.clear();
      this.logger.info('Sistema resetado');
    }

    /**
     * Finaliza o sistema
     */
    shutdown() {
      this.scheduler.cancelAll();
      this.channels.disconnect('whatsapp');
      this.initialized = false;
      this.logger.info('Sistema finalizado');
      this.events.emit('system:shutdown');
    }
  }

  // ============================================================
  // EXPORT TO GLOBAL SCOPE
  // ============================================================

  // Exportar para window (disponível globalmente)
  if (typeof window !== 'undefined') {
    window.SmartBotIA = {
      // Core System
      Core: SmartBotIACore,
      
      // Main Classes
      AdvancedContextAnalyzer,
      ContextualResponseGenerator,
      IntelligentPriorityQueue,
      ContinuousLearningSystem,
      HumanAssistanceSystem,
      SmartBotDashboard,
      
      // Auxiliary Managers
      SchedulerSystem,
      FeedbackAnalyzer,
      NotificationManager,
      AuthManager,
      LogManager,
      ApiManager,
      ConfigManager,
      NlpManager,
      DialogManager,
      ContextManager,
      EntityManager,
      IntentManager,
      SessionManager,
      CacheManager,
      WebhookManager,
      PluginManager,
      EventManager,
      AnalyticsManager,
      QueueManager,
      RateLimiter,
      MiddlewareManager,
      PermissionManager,
      LocaleManager,
      ChannelConnector,
      
      // Utils
      Utils: SmartBotUtils,
      
      // Version
      version: '1.0.0'
    };
    
    console.log(`
╔════════════════════════════════════════════════════════════╗
║          SMARTBOT IA CORE v1.0.0 - CARREGADO              ║
╚════════════════════════════════════════════════════════════╝

✅ 6 Componentes Principais:
  - AdvancedContextAnalyzer
  - ContextualResponseGenerator
  - IntelligentPriorityQueue
  - ContinuousLearningSystem
  - HumanAssistanceSystem
  - SmartBotDashboard

✅ 24 Gerenciadores Auxiliares:
  - Scheduler, Feedback, Notifications
  - Auth, Log, API, Config
  - NLP, Dialog, Context, Entity
  - Intent, Session, Cache, Webhook
  - Plugin, Event, Analytics, Queue
  - RateLimiter, Middleware, Permissions
  - Locale, ChannelConnector

🚀 Uso:
  const smartbot = new SmartBotIA.Core();
  await smartbot.initialize();
  const result = await smartbot.processMessage(clientId, message);

📚 Documentação completa disponível no código-fonte.
  }

})();
`);
  }

