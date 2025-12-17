/**
 * @fileoverview SmartBot Webhook Manager - Sistema de webhooks
 * @module smartbot/communication/webhook-manager
 */

/**
 * Gerenciador de webhooks com retry e assinatura HMAC
 */
export class WebhookManager {
  constructor(options = {}) {
    this.webhooks = new Map();
    this.queue = [];
    this.processing = false;
    this.retryAttempts = options.retryAttempts || 3;
    this.retryDelay = options.retryDelay || 1000;
    this.timeout = options.timeout || 30000;
    this.secret = options.secret || null;
  }

  /**
   * Registrar webhook
   */
  register(id, config) {
    this.webhooks.set(id, {
      id,
      url: config.url,
      events: config.events || ['*'],
      method: config.method || 'POST',
      headers: config.headers || {},
      enabled: config.enabled !== false,
      secret: config.secret || this.secret,
      retryAttempts: config.retryAttempts || this.retryAttempts,
      createdAt: Date.now(),
      stats: {
        sent: 0,
        failed: 0,
        lastSent: null,
        lastError: null
      }
    });

    return this.webhooks.get(id);
  }

  /**
   * Remover webhook
   */
  unregister(id) {
    return this.webhooks.delete(id);
  }

  /**
   * Enviar evento para webhooks
   */
  async dispatch(event, data) {
    const webhooks = Array.from(this.webhooks.values())
      .filter(wh => wh.enabled)
      .filter(wh => this._matchesEvent(wh.events, event));

    const results = [];

    for (const webhook of webhooks) {
      const result = await this._send(webhook, event, data);
      results.push({ webhookId: webhook.id, ...result });
    }

    return results;
  }

  /**
   * Broadcast para múltiplos webhooks
   */
  async broadcast(event, data) {
    return this.dispatch(event, data);
  }

  /**
   * Enfileirar para envio posterior
   */
  enqueue(event, data, priority = 5) {
    this.queue.push({
      event,
      data,
      priority,
      enqueuedAt: Date.now()
    });

    // Ordenar por prioridade
    this.queue.sort((a, b) => a.priority - b.priority);

    // Processar fila
    this._processQueue();
  }

  /**
   * Processar fila
   * @private
   */
  async _processQueue() {
    if (this.processing || this.queue.length === 0) return;

    this.processing = true;

    while (this.queue.length > 0) {
      const item = this.queue.shift();
      await this.dispatch(item.event, item.data);
      await this._sleep(100);
    }

    this.processing = false;
  }

  /**
   * Enviar para webhook específico
   * @private
   */
  async _send(webhook, event, data, attempt = 1) {
    try {
      const payload = {
        event,
        data,
        timestamp: Date.now()
      };

      // Gerar assinatura HMAC se tiver secret
      let signature = null;
      if (webhook.secret) {
        signature = await this._generateSignature(payload, webhook.secret);
      }

      const headers = {
        'Content-Type': 'application/json',
        'X-Webhook-Event': event,
        'X-Webhook-Timestamp': payload.timestamp.toString(),
        ...webhook.headers
      };

      if (signature) {
        headers['X-Webhook-Signature'] = signature;
      }

      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), this.timeout);

      const response = await fetch(webhook.url, {
        method: webhook.method,
        headers,
        body: JSON.stringify(payload),
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      // Atualizar stats
      webhook.stats.sent++;
      webhook.stats.lastSent = Date.now();

      return {
        success: true,
        status: response.status,
        attempt
      };

    } catch (error) {
      // Retry com backoff exponencial
      if (attempt < webhook.retryAttempts) {
        const delay = this.retryDelay * Math.pow(2, attempt - 1);
        await this._sleep(delay);
        return this._send(webhook, event, data, attempt + 1);
      }

      // Falha definitiva
      webhook.stats.failed++;
      webhook.stats.lastError = {
        message: error.message,
        timestamp: Date.now()
      };

      return {
        success: false,
        error: error.message,
        attempt
      };
    }
  }

  /**
   * Gerar assinatura HMAC
   * @private
   */
  async _generateSignature(payload, secret) {
    const encoder = new TextEncoder();
    const data = encoder.encode(JSON.stringify(payload));
    const key = await crypto.subtle.importKey(
      'raw',
      encoder.encode(secret),
      { name: 'HMAC', hash: 'SHA-256' },
      false,
      ['sign']
    );

    const signature = await crypto.subtle.sign('HMAC', key, data);
    const hexSignature = Array.from(new Uint8Array(signature))
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
    return `sha256=${hexSignature}`;
  }

  /**
   * Verificar se webhook combina com evento
   * @private
   */
  _matchesEvent(webhookEvents, event) {
    if (webhookEvents.includes('*')) return true;
    if (webhookEvents.includes(event)) return true;

    // Wildcard match (ex: 'message:*')
    for (const pattern of webhookEvents) {
      if (pattern.endsWith(':*')) {
        const prefix = pattern.slice(0, -2);
        if (event.startsWith(prefix + ':')) return true;
      }
    }

    return false;
  }

  /**
   * Habilitar/desabilitar webhook
   */
  setEnabled(id, enabled) {
    const webhook = this.webhooks.get(id);
    if (webhook) {
      webhook.enabled = enabled;
      return true;
    }
    return false;
  }

  /**
   * Testar webhook
   */
  async test(id) {
    const webhook = this.webhooks.get(id);
    if (!webhook) {
      throw new Error(`Webhook ${id} not found`);
    }

    return this._send(webhook, 'test', {
      message: 'Test webhook',
      timestamp: Date.now()
    });
  }

  /**
   * Obter estatísticas
   */
  getStats(id = null) {
    if (id) {
      const webhook = this.webhooks.get(id);
      return webhook ? webhook.stats : null;
    }

    const stats = {
      total: this.webhooks.size,
      enabled: 0,
      totalSent: 0,
      totalFailed: 0
    };

    for (const webhook of this.webhooks.values()) {
      if (webhook.enabled) stats.enabled++;
      stats.totalSent += webhook.stats.sent;
      stats.totalFailed += webhook.stats.failed;
    }

    return stats;
  }

  /**
   * Listar webhooks
   */
  list() {
    return Array.from(this.webhooks.values()).map(wh => ({
      id: wh.id,
      url: wh.url,
      events: wh.events,
      enabled: wh.enabled,
      stats: wh.stats
    }));
  }

  /**
   * Sleep helper
   * @private
   */
  _sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}
