/**
 * @fileoverview SmartBot Plugin Manager - Sistema de plugins
 * @module smartbot/plugins/plugin-manager
 */

/**
 * Gerenciador de plugins com lifecycle
 */
export class PluginManager {
  constructor() {
    this.plugins = new Map();
    this.hooks = new Map();
    this.commands = new Map();
  }

  /**
   * Registrar plugin
   */
  register(id, plugin) {
    if (this.plugins.has(id)) {
      throw new Error(`Plugin ${id} already registered`);
    }

    const pluginData = {
      id,
      name: plugin.name || id,
      version: plugin.version || '1.0.0',
      description: plugin.description || '',
      author: plugin.author || '',
      plugin,
      enabled: false,
      initialized: false,
      registeredAt: Date.now(),
      hooks: [],
      commands: []
    };

    this.plugins.set(id, pluginData);
    return pluginData;
  }

  /**
   * Inicializar plugin
   */
  async initialize(id, context = {}) {
    const plugin = this.plugins.get(id);
    if (!plugin) {
      throw new Error(`Plugin ${id} not found`);
    }

    if (plugin.initialized) {
      return plugin;
    }

    try {
      if (plugin.plugin.init && typeof plugin.plugin.init === 'function') {
        await plugin.plugin.init(context);
      }

      plugin.initialized = true;
      plugin.initializedAt = Date.now();

      return plugin;
    } catch (error) {
      throw new Error(`Failed to initialize plugin ${id}: ${error.message}`);
    }
  }

  /**
   * Habilitar plugin
   */
  async enable(id, context = {}) {
    const plugin = this.plugins.get(id);
    if (!plugin) {
      throw new Error(`Plugin ${id} not found`);
    }

    if (!plugin.initialized) {
      await this.initialize(id, context);
    }

    if (plugin.enabled) {
      return plugin;
    }

    try {
      if (plugin.plugin.enable && typeof plugin.plugin.enable === 'function') {
        await plugin.plugin.enable(context);
      }

      plugin.enabled = true;
      plugin.enabledAt = Date.now();

      return plugin;
    } catch (error) {
      throw new Error(`Failed to enable plugin ${id}: ${error.message}`);
    }
  }

  /**
   * Desabilitar plugin
   */
  async disable(id, context = {}) {
    const plugin = this.plugins.get(id);
    if (!plugin) {
      throw new Error(`Plugin ${id} not found`);
    }

    if (!plugin.enabled) {
      return plugin;
    }

    try {
      if (plugin.plugin.disable && typeof plugin.plugin.disable === 'function') {
        await plugin.plugin.disable(context);
      }

      plugin.enabled = false;
      plugin.disabledAt = Date.now();

      return plugin;
    } catch (error) {
      throw new Error(`Failed to disable plugin ${id}: ${error.message}`);
    }
  }

  /**
   * Destruir plugin
   */
  async destroy(id, context = {}) {
    const plugin = this.plugins.get(id);
    if (!plugin) {
      throw new Error(`Plugin ${id} not found`);
    }

    // Desabilitar se habilitado
    if (plugin.enabled) {
      await this.disable(id, context);
    }

    try {
      if (plugin.plugin.destroy && typeof plugin.plugin.destroy === 'function') {
        await plugin.plugin.destroy(context);
      }

      // Remover hooks
      for (const hookName of plugin.hooks) {
        this.removeHook(hookName, id);
      }

      // Remover comandos
      for (const command of plugin.commands) {
        this.commands.delete(command);
      }

      this.plugins.delete(id);

      return true;
    } catch (error) {
      throw new Error(`Failed to destroy plugin ${id}: ${error.message}`);
    }
  }

  /**
   * Registrar hook
   */
  registerHook(pluginId, hookName, handler, priority = 5) {
    const plugin = this.plugins.get(pluginId);
    if (!plugin) {
      throw new Error(`Plugin ${pluginId} not found`);
    }

    if (!this.hooks.has(hookName)) {
      this.hooks.set(hookName, []);
    }

    const hook = {
      pluginId,
      handler,
      priority
    };

    this.hooks.get(hookName).push(hook);
    
    // Ordenar por prioridade
    this.hooks.get(hookName).sort((a, b) => a.priority - b.priority);

    // Registrar no plugin
    plugin.hooks.push(hookName);
  }

  /**
   * Remover hook
   */
  removeHook(hookName, pluginId) {
    if (!this.hooks.has(hookName)) return false;

    const hooks = this.hooks.get(hookName);
    const index = hooks.findIndex(h => h.pluginId === pluginId);

    if (index !== -1) {
      hooks.splice(index, 1);
      return true;
    }

    return false;
  }

  /**
   * Executar hook
   */
  async executeHook(hookName, data = {}) {
    if (!this.hooks.has(hookName)) return data;

    const hooks = this.hooks.get(hookName);
    let result = data;

    for (const hook of hooks) {
      const plugin = this.plugins.get(hook.pluginId);
      
      // Apenas executar se plugin estiver habilitado
      if (!plugin || !plugin.enabled) continue;

      try {
        result = await hook.handler(result);
      } catch (error) {
        console.error(`[PluginManager] Error in hook ${hookName} from plugin ${hook.pluginId}:`, error);
      }
    }

    return result;
  }

  /**
   * Registrar comando
   */
  registerCommand(pluginId, commandName, handler, options = {}) {
    const plugin = this.plugins.get(pluginId);
    if (!plugin) {
      throw new Error(`Plugin ${pluginId} not found`);
    }

    if (this.commands.has(commandName)) {
      throw new Error(`Command ${commandName} already registered`);
    }

    const command = {
      name: commandName,
      pluginId,
      handler,
      description: options.description || '',
      usage: options.usage || '',
      permissions: options.permissions || []
    };

    this.commands.set(commandName, command);
    
    // Registrar no plugin
    plugin.commands.push(commandName);
  }

  /**
   * Executar comando
   */
  async executeCommand(commandName, args = [], context = {}) {
    const command = this.commands.get(commandName);
    if (!command) {
      throw new Error(`Command ${commandName} not found`);
    }

    const plugin = this.plugins.get(command.pluginId);
    if (!plugin || !plugin.enabled) {
      throw new Error(`Plugin for command ${commandName} is not enabled`);
    }

    try {
      return await command.handler(args, context);
    } catch (error) {
      throw new Error(`Error executing command ${commandName}: ${error.message}`);
    }
  }

  /**
   * Obter plugin
   */
  getPlugin(id) {
    return this.plugins.get(id);
  }

  /**
   * Listar plugins
   */
  listPlugins(filter = null) {
    let plugins = Array.from(this.plugins.values());

    if (filter === 'enabled') {
      plugins = plugins.filter(p => p.enabled);
    } else if (filter === 'disabled') {
      plugins = plugins.filter(p => !p.enabled);
    }

    return plugins.map(p => ({
      id: p.id,
      name: p.name,
      version: p.version,
      enabled: p.enabled,
      initialized: p.initialized,
      hooks: p.hooks.length,
      commands: p.commands.length
    }));
  }

  /**
   * Listar comandos
   */
  listCommands() {
    return Array.from(this.commands.values()).map(cmd => ({
      name: cmd.name,
      pluginId: cmd.pluginId,
      description: cmd.description,
      usage: cmd.usage
    }));
  }

  /**
   * Listar hooks
   */
  listHooks() {
    const result = [];
    for (const [name, hooks] of this.hooks.entries()) {
      result.push({
        name,
        handlers: hooks.length,
        plugins: hooks.map(h => h.pluginId)
      });
    }
    return result;
  }

  /**
   * Verificar se plugin está habilitado
   */
  isEnabled(id) {
    const plugin = this.plugins.get(id);
    return plugin ? plugin.enabled : false;
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    return {
      total: this.plugins.size,
      enabled: Array.from(this.plugins.values()).filter(p => p.enabled).length,
      hooks: this.hooks.size,
      commands: this.commands.size
    };
  }
}

/**
 * Middleware Manager - Pipeline de processamento
 */
export class MiddlewareManager {
  constructor() {
    this.middleware = [];
    this.enabled = true;
  }

  /**
   * Adicionar middleware
   */
  use(handler, options = {}) {
    const middleware = {
      handler,
      name: options.name || `middleware_${this.middleware.length}`,
      enabled: options.enabled !== false,
      priority: options.priority || 5
    };

    this.middleware.push(middleware);
    
    // Ordenar por prioridade
    this.middleware.sort((a, b) => a.priority - b.priority);

    return middleware.name;
  }

  /**
   * Remover middleware
   */
  remove(name) {
    const index = this.middleware.findIndex(m => m.name === name);
    if (index !== -1) {
      this.middleware.splice(index, 1);
      return true;
    }
    return false;
  }

  /**
   * Habilitar/desabilitar middleware
   */
  setEnabled(name, enabled) {
    const middleware = this.middleware.find(m => m.name === name);
    if (middleware) {
      middleware.enabled = enabled;
      return true;
    }
    return false;
  }

  /**
   * Processar através do pipeline
   */
  async process(data, context = {}) {
    if (!this.enabled) return data;

    let result = data;

    for (const middleware of this.middleware) {
      if (!middleware.enabled) continue;

      try {
        result = await middleware.handler(result, context);
      } catch (error) {
        console.error(`[MiddlewareManager] Error in ${middleware.name}:`, error);
        // Continuar processamento mesmo com erro
      }
    }

    return result;
  }

  /**
   * Listar middleware
   */
  list() {
    return this.middleware.map(m => ({
      name: m.name,
      enabled: m.enabled,
      priority: m.priority
    }));
  }

  /**
   * Limpar todos
   */
  clear() {
    this.middleware = [];
  }

  /**
   * Obter estatísticas
   */
  getStats() {
    return {
      total: this.middleware.length,
      enabled: this.middleware.filter(m => m.enabled).length
    };
  }
}
