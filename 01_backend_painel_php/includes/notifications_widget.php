<?php
declare(strict_types=1);

/**
 * Notification Widget
 * Displays a notification bell icon with badge in the header
 */

if (!isset($_SESSION['usuario_id'])) {
    return; // Don't show widget if user is not logged in
}

$userId = (int)$_SESSION['usuario_id'];
?>

<!-- Notification Widget CSS -->
<style>
.notification-widget {
    position: relative;
    display: inline-block;
    margin-right: 15px;
}

.notification-bell {
    position: relative;
    cursor: pointer;
    font-size: 20px;
    color: var(--al-text-secondary, #999);
    transition: color 0.3s ease;
}

.notification-bell:hover {
    color: var(--al-primary, #ffa500);
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--al-error, #dc3545);
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
    display: none;
}

.notification-badge.visible {
    display: block;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 10px;
    width: 360px;
    max-height: 480px;
    background: var(--al-card-bg, #1a1a1a);
    border: 1px solid var(--al-border, #333);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    display: none;
    z-index: 1000;
    overflow: hidden;
}

.notification-dropdown.show {
    display: block;
}

.notification-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--al-border, #333);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h6 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--al-text-primary, #fff);
}

.notification-mark-all {
    font-size: 12px;
    color: var(--al-primary, #ffa500);
    cursor: pointer;
    text-decoration: none;
}

.notification-mark-all:hover {
    text-decoration: underline;
}

.notification-list {
    max-height: 360px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--al-border-subtle, #2a2a2a);
    cursor: pointer;
    transition: background 0.2s ease;
}

.notification-item:hover {
    background: var(--al-hover-bg, #252525);
}

.notification-item.unread {
    background: rgba(255, 165, 0, 0.05);
}

.notification-item-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--al-text-primary, #fff);
    margin-bottom: 4px;
}

.notification-item-message {
    font-size: 12px;
    color: var(--al-text-secondary, #999);
    margin-bottom: 4px;
}

.notification-item-time {
    font-size: 11px;
    color: var(--al-text-muted, #666);
}

.notification-empty {
    padding: 40px 16px;
    text-align: center;
    color: var(--al-text-muted, #666);
    font-size: 13px;
}

.notification-footer {
    padding: 10px 16px;
    border-top: 1px solid var(--al-border, #333);
    text-align: center;
}

.notification-view-all {
    font-size: 12px;
    color: var(--al-primary, #ffa500);
    text-decoration: none;
}

.notification-view-all:hover {
    text-decoration: underline;
}

/* Scrollbar styling */
.notification-list::-webkit-scrollbar {
    width: 6px;
}

.notification-list::-webkit-scrollbar-track {
    background: var(--al-bg-secondary, #1a1a1a);
}

.notification-list::-webkit-scrollbar-thumb {
    background: var(--al-border, #333);
    border-radius: 3px;
}

.notification-list::-webkit-scrollbar-thumb:hover {
    background: var(--al-primary, #ffa500);
}
</style>

<!-- Notification Widget HTML -->
<div class="notification-widget">
    <div class="notification-bell" id="notificationBell">
        <i class="fas fa-bell"></i>
        <span class="notification-badge" id="notificationBadge">0</span>
    </div>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h6>Notificações</h6>
            <a href="#" class="notification-mark-all" id="markAllRead">Marcar todas como lidas</a>
        </div>
        
        <div class="notification-list" id="notificationList">
            <div class="notification-empty">
                <i class="fas fa-bell-slash" style="font-size: 32px; margin-bottom: 8px; opacity: 0.3;"></i>
                <div>Nenhuma notificação</div>
            </div>
        </div>
    </div>
</div>

<!-- Notification Widget JavaScript -->
<script>
(function() {
    const bell = document.getElementById('notificationBell');
    const badge = document.getElementById('notificationBadge');
    const dropdown = document.getElementById('notificationDropdown');
    const list = document.getElementById('notificationList');
    const markAllBtn = document.getElementById('markAllRead');
    
    let isOpen = false;
    
    // Toggle dropdown
    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen = !isOpen;
        dropdown.classList.toggle('show', isOpen);
        
        if (isOpen) {
            loadNotifications();
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
            isOpen = false;
            dropdown.classList.remove('show');
        }
    });
    
    // Mark all as read
    markAllBtn.addEventListener('click', function(e) {
        e.preventDefault();
        markAllAsRead();
    });
    
    // Load notifications count
    function loadNotificationCount() {
        fetch('/api/notifications.php?count=1')
            .then(response => response.json())
            .then(data => {
                if (data.ok && data.data.count !== undefined) {
                    const count = parseInt(data.data.count);
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.add('visible');
                    } else {
                        badge.classList.remove('visible');
                    }
                }
            })
            .catch(err => console.error('Error loading notification count:', err));
    }
    
    // Load notifications list
    function loadNotifications() {
        fetch('/api/notifications.php?limit=10')
            .then(response => response.json())
            .then(data => {
                if (data.ok && Array.isArray(data.data)) {
                    renderNotifications(data.data);
                }
            })
            .catch(err => console.error('Error loading notifications:', err));
    }
    
    // Render notifications
    function renderNotifications(notifications) {
        if (notifications.length === 0) {
            list.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash" style="font-size: 32px; margin-bottom: 8px; opacity: 0.3;"></i><div>Nenhuma notificação</div></div>';
            return;
        }
        
        list.innerHTML = notifications.map(notif => {
            const isUnread = notif.is_read === 0 || notif.is_read === false;
            const time = formatTime(notif.created_at);
            
            return `
                <div class="notification-item ${isUnread ? 'unread' : ''}" data-id="${notif.id}">
                    <div class="notification-item-title">${escapeHtml(notif.title)}</div>
                    <div class="notification-item-message">${escapeHtml(notif.message || '')}</div>
                    <div class="notification-item-time">${time}</div>
                </div>
            `;
        }).join('');
        
        // Add click handlers to mark as read
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const id = this.dataset.id;
                markAsRead(id);
            });
        });
    }
    
    // Mark notification as read
    function markAsRead(id) {
        fetch(`/api/notifications.php?action=read&id=${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                loadNotificationCount();
                loadNotifications();
            }
        })
        .catch(err => console.error('Error marking notification as read:', err));
    }
    
    // Mark all notifications as read
    function markAllAsRead() {
        fetch('/api/notifications.php?action=read_all', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                loadNotificationCount();
                loadNotifications();
            }
        })
        .catch(err => console.error('Error marking all as read:', err));
    }
    
    // Format time ago
    function formatTime(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHour / 24);
        
        if (diffSec < 60) return 'Agora mesmo';
        if (diffMin < 60) return `${diffMin} min atrás`;
        if (diffHour < 24) return `${diffHour}h atrás`;
        if (diffDay < 7) return `${diffDay}d atrás`;
        
        return date.toLocaleDateString('pt-BR');
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initial load
    loadNotificationCount();
    
    // Refresh count every 30 seconds
    setInterval(loadNotificationCount, 30000);
})();
</script>
