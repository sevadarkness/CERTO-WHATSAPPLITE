/**
 * @fileoverview SmartBot Dialog Manager - Gerenciamento de fluxos de conversa
 * @module smartbot/dialog/dialog-manager
 */

/**
 * Gerenciador de fluxos de diálogo
 */
export class DialogManager {
  constructor() {
    this.dialogs = new Map();
    this.sessions = new Map();
    this.history = new Map();
  }

  /**
   * Criar diálogo
   */
  createDialog(dialogId, config) {
    const dialog = {
      id: dialogId,
      name: config.name || dialogId,
      initialState: config.initialState || 'start',
      states: new Map(),
      metadata: config.metadata || {}
    };

    // Adicionar estados
    for (const [stateId, stateConfig] of Object.entries(config.states || {})) {
      this.addState(dialogId, stateId, stateConfig);
    }

    this.dialogs.set(dialogId, dialog);
    return dialog;
  }

  /**
   * Adicionar estado ao diálogo
   */
  addState(dialogId, stateId, config) {
    const dialog = this.dialogs.get(dialogId);
    if (!dialog) throw new Error(`Dialog ${dialogId} not found`);

    const state = {
      id: stateId,
      handler: config.handler,
      transitions: config.transitions || {},
      message: config.message || null,
      actions: config.actions || [],
      validation: config.validation || null
    };

    if (!dialog.states) dialog.states = new Map();
    dialog.states.set(stateId, state);
  }

  /**
   * Iniciar sessão de diálogo
   */
  startSession(userId, dialogId, context = {}) {
    const dialog = this.dialogs.get(dialogId);
    if (!dialog) throw new Error(`Dialog ${dialogId} not found`);

    const session = {
      userId,
      dialogId,
      currentState: dialog.initialState,
      context,
      history: [],
      startedAt: Date.now(),
      lastActivity: Date.now()
    };

    this.sessions.set(userId, session);
    this._addToHistory(userId, 'session_started', { dialogId });

    return session;
  }

  /**
   * Processar mensagem do usuário
   */
  async processMessage(userId, message) {
    const session = this.sessions.get(userId);
    if (!session) {
      return { error: 'No active session' };
    }

    const dialog = this.dialogs.get(session.dialogId);
    if (!dialog) {
      return { error: 'Dialog not found' };
    }

    const state = dialog.states.get(session.currentState);
    if (!state) {
      return { error: 'State not found' };
    }

    session.lastActivity = Date.now();
    this._addToHistory(userId, 'user_message', { message, state: session.currentState });

    // Executar handler se existir
    let result = {};
    if (state.handler) {
      try {
        result = await state.handler(message, session.context);
      } catch (e) {
        return { error: e.message };
      }
    }

    // Verificar validação
    if (state.validation) {
      const validation = await state.validation(message, session.context);
      if (!validation.valid) {
        return {
          valid: false,
          error: validation.error,
          retry: true
        };
      }
    }

    // Determinar próximo estado
    const nextState = this._getNextState(state, result, message);
    
    if (nextState) {
      session.currentState = nextState;
      this._addToHistory(userId, 'state_transition', { from: state.id, to: nextState });

      // Executar ações do próximo estado
      const newState = dialog.states.get(nextState);
      if (newState && newState.actions) {
        for (const action of newState.actions) {
          await action(session.context);
        }
      }
    }

    // Verificar se diálogo terminou
    if (session.currentState === 'end') {
      this.endSession(userId);
      return {
        completed: true,
        message: result.message || 'Diálogo concluído',
        context: session.context
      };
    }

    return {
      message: result.message || state.message,
      state: session.currentState,
      context: session.context
    };
  }

  /**
   * Determinar próximo estado
   * @private
   */
  _getNextState(state, result, message) {
    const transitions = state.transitions;

    // Verificar transições baseadas em resultado
    if (result.next) {
      return result.next;
    }

    // Verificar transições condicionais
    for (const [condition, nextState] of Object.entries(transitions)) {
      if (condition === 'default') continue;

      if (condition === 'intent' && result.intent) {
        if (transitions[result.intent]) {
          return transitions[result.intent];
        }
      }

      if (typeof transitions[condition] === 'function') {
        if (transitions[condition](message, result)) {
          return nextState;
        }
      }

      if (condition === message.toLowerCase()) {
        return nextState;
      }
    }

    // Default transition
    return transitions.default || null;
  }

  /**
   * Encerrar sessão
   */
  endSession(userId) {
    const session = this.sessions.get(userId);
    if (session) {
      this._addToHistory(userId, 'session_ended', {
        duration: Date.now() - session.startedAt
      });
      this.sessions.delete(userId);
    }
  }

  /**
   * Obter sessão
   */
  getSession(userId) {
    return this.sessions.get(userId);
  }

  /**
   * Atualizar contexto da sessão
   */
  updateContext(userId, updates) {
    const session = this.sessions.get(userId);
    if (session) {
      session.context = { ...session.context, ...updates };
      session.lastActivity = Date.now();
    }
  }

  /**
   * Adicionar ao histórico
   * @private
   */
  _addToHistory(userId, event, data) {
    if (!this.history.has(userId)) {
      this.history.set(userId, []);
    }

    const history = this.history.get(userId);
    history.push({
      event,
      data,
      timestamp: Date.now()
    });

    // Limitar histórico
    if (history.length > 100) {
      history.shift();
    }
  }

  /**
   * Obter histórico
   */
  getHistory(userId, limit = 20) {
    const history = this.history.get(userId) || [];
    return history.slice(-limit);
  }

  /**
   * Listar diálogos
   */
  listDialogs() {
    return Array.from(this.dialogs.values()).map(d => ({
      id: d.id,
      name: d.name,
      states: d.states.size
    }));
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    return {
      dialogs: this.dialogs.size,
      activeSessions: this.sessions.size,
      totalHistory: Array.from(this.history.values()).reduce((sum, h) => sum + h.length, 0)
    };
  }
}
