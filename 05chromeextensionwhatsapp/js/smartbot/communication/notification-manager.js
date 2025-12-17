/**
 * @fileoverview SmartBot Notification Manager - Sistema de notificações
 * @module smartbot/communication/notification-manager
 */

/**
 * Gerenciador de notificações multi-canal
 */
export class NotificationManager {
  constructor() {
    this.channels = new Map();
    this.subscriptions = new Map();
    this.queue = [];
    this.processing = false;
    
    this._setupDefaultChannels();
  }

  /**
   * Configurar canais padrão
   * @private
   */
  _setupDefaultChannels() {
    // Canal: Chrome Notifications
    this.registerChannel('chrome', async (notification) => {
      if (!chrome.notifications) {
        throw new Error('Chrome notifications not available');
      }

      await chrome.notifications.create({
        type: 'basic',
        iconUrl: '/icons/icon-48.png',
        title: notification.title || 'SmartBot',
        message: notification.message,
        priority: notification.priority || 0
      });
    });

    // Canal: Console
    this.registerChannel('console', async (notification) => {
      const level = notification.level || 'info';
      console[level](`[Notification] ${notification.title}: ${notification.message}`);
    });

    // Canal: DOM Event
    this.registerChannel('event', async (notification) => {
      window.dispatchEvent(new CustomEvent('smartbot:notification', {
        detail: notification
      }));
    });
  }

  /**
   * Registrar canal de notificação
   */
  registerChannel(id, handler) {
    this.channels.set(id, {
      id,
      handler,
      enabled: true,
      stats: { sent: 0, failed: 0 }
    });
  }

  /**
   * Enviar notificação
   */
  async send(channelId, notification, options = {}) {
    const channel = this.channels.get(channelId);
    if (!channel || !channel.enabled) {
      throw new Error(`Channel ${channelId} not available`);
    }

    if (options.queue) {
      this.enqueue(channelId, notification, options.priority || 5);
      return true;
    }

    return this._sendImmediate(channel, notification, options);
  }

  /**
   * Enviar imediatamente
   * @private
   */
  async _sendImmediate(channel, notification, options = {}) {
    const maxRetries = options.retry || 1;
    let lastError;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        await channel.handler(notification);
        channel.stats.sent++;
        return true;
      } catch (error) {
        lastError = error;
        if (attempt < maxRetries) {
          await this._sleep(1000 * attempt);
        }
      }
    }

    channel.stats.failed++;
    throw lastError;
  }

  /**
   * Enfileirar notificação
   */
  enqueue(channelId, notification, priority = 5) {
    this.queue.push({
      channelId,
      notification,
      priority,
      enqueuedAt: Date.now()
    });

    this.queue.sort((a, b) => a.priority - b.priority);
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
      const channel = this.channels.get(item.channelId);

      if (channel && channel.enabled) {
        try {
          await this._sendImmediate(channel, item.notification);
        } catch (e) {
          console.error('[NotificationManager] Failed to send:', e);
        }
      }

      await this._sleep(100);
    }

    this.processing = false;
  }

  /**
   * Broadcast para múltiplos canais
   */
  async broadcast(notification, channelIds = null) {
    const channels = channelIds
      ? channelIds.map(id => this.channels.get(id)).filter(Boolean)
      : Array.from(this.channels.values());

    const results = [];

    for (const channel of channels) {
      if (!channel.enabled) continue;

      try {
        await this._sendImmediate(channel, notification);
        results.push({ channelId: channel.id, success: true });
      } catch (error) {
        results.push({ channelId: channel.id, success: false, error: error.message });
      }
    }

    return results;
  }

  /**
   * Subscrever usuário a canal
   */
  subscribe(userId, channelId, preferences = {}) {
    if (!this.subscriptions.has(userId)) {
      this.subscriptions.set(userId, new Map());
    }

    this.subscriptions.get(userId).set(channelId, {
      channelId,
      preferences,
      subscribedAt: Date.now()
    });
  }

  /**
   * Desinscrever usuário
   */
  unsubscribe(userId, channelId = null) {
    if (!this.subscriptions.has(userId)) return false;

    if (channelId) {
      return this.subscriptions.get(userId).delete(channelId);
    } else {
      this.subscriptions.delete(userId);
      return true;
    }
  }

  /**
   * Verificar se usuário está subscrito
   */
  isSubscribed(userId, channelId) {
    if (!this.subscriptions.has(userId)) return false;
    return this.subscriptions.get(userId).has(channelId);
  }

  /**
   * Enviar notificação para usuários subscritos
   */
  async notifySubscribers(channelId, notification) {
    const subscribers = [];

    for (const [userId, channels] of this.subscriptions.entries()) {
      if (channels.has(channelId)) {
        subscribers.push(userId);
      }
    }

    const results = [];

    for (const userId of subscribers) {
      try {
        await this.send(channelId, {
          ...notification,
          userId
        });
        results.push({ userId, success: true });
      } catch (error) {
        results.push({ userId, success: false, error: error.message });
      }
    }

    return results;
  }

  /**
   * Habilitar/desabilitar canal
   */
  setChannelEnabled(channelId, enabled) {
    const channel = this.channels.get(channelId);
    if (channel) {
      channel.enabled = enabled;
      return true;
    }
    return false;
  }

  /**
   * Obter estatísticas
   */
  getStats(channelId = null) {
    if (channelId) {
      const channel = this.channels.get(channelId);
      return channel ? channel.stats : null;
    }

    const stats = {
      channels: this.channels.size,
      subscribers: this.subscriptions.size,
      queueSize: this.queue.length,
      totalSent: 0,
      totalFailed: 0
    };

    for (const channel of this.channels.values()) {
      stats.totalSent += channel.stats.sent;
      stats.totalFailed += channel.stats.failed;
    }

    return stats;
  }

  /**
   * Listar canais
   */
  listChannels() {
    return Array.from(this.channels.values()).map(ch => ({
      id: ch.id,
      enabled: ch.enabled,
      stats: ch.stats
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
