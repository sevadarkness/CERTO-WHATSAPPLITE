<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Services\NotificationService;

/**
 * Tests for Notification Service functionality.
 */
final class NotificationTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use SQLite in memory for isolated tests
        $this->dbFile = sys_get_temp_dir() . '/test_notifications_' . bin2hex(random_bytes(8)) . '.db';
        $this->pdo = new PDO('sqlite:' . $this->dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create notifications table
        $this->createNotificationsTable();
        
        // Load NotificationService class
        require_once __DIR__ . '/../app/Services/NotificationService.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->pdo = null;
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    private function createNotificationsTable(): void
    {
        $this->pdo->exec("CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            data_json TEXT,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL
        )");
    }

    public function testCreateNotification(): void
    {
        $notifId = NotificationService::create(
            $this->pdo,
            1,
            'new_lead',
            'Novo Lead',
            'Lead João Silva cadastrado',
            ['lead_id' => 123]
        );
        
        $this->assertGreaterThan(0, $notifId);
        
        // Verify in database
        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$notifId]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertIsArray($notif);
        $this->assertEquals(1, $notif['user_id']);
        $this->assertEquals('new_lead', $notif['type']);
        $this->assertEquals('Novo Lead', $notif['title']);
        $this->assertEquals('Lead João Silva cadastrado', $notif['message']);
        $this->assertEquals(0, $notif['is_read']);
    }

    public function testCreateNotificationWithData(): void
    {
        $data = ['lead_id' => 123, 'phone' => '+5511999999999'];
        
        $notifId = NotificationService::create(
            $this->pdo,
            1,
            'new_lead',
            'Novo Lead',
            'Lead com dados',
            $data
        );
        
        $stmt = $this->pdo->prepare("SELECT data_json FROM notifications WHERE id = ?");
        $stmt->execute([$notifId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotNull($result['data_json']);
        $decoded = json_decode($result['data_json'], true);
        $this->assertEquals($data, $decoded);
    }

    public function testGetUnreadNotifications(): void
    {
        // Create multiple notifications
        NotificationService::create($this->pdo, 1, 'new_lead', 'Notif 1', 'Message 1');
        NotificationService::create($this->pdo, 1, 'new_lead', 'Notif 2', 'Message 2');
        NotificationService::create($this->pdo, 2, 'new_lead', 'Notif 3', 'Message 3'); // Different user
        
        $unread = NotificationService::getUnread($this->pdo, 1, 10);
        
        $this->assertIsArray($unread);
        $this->assertCount(2, $unread);
        $this->assertEquals('Notif 2', $unread[0]['title']); // Most recent first
        $this->assertEquals('Notif 1', $unread[1]['title']);
    }

    public function testGetUnreadWithLimit(): void
    {
        // Create 5 notifications
        for ($i = 1; $i <= 5; $i++) {
            NotificationService::create($this->pdo, 1, 'test', "Notif $i", "Message $i");
        }
        
        $unread = NotificationService::getUnread($this->pdo, 1, 3);
        
        $this->assertCount(3, $unread);
    }

    public function testMarkAsRead(): void
    {
        $notifId = NotificationService::create(
            $this->pdo,
            1,
            'new_lead',
            'Test Notification',
            'Test Message'
        );
        
        $success = NotificationService::markAsRead($this->pdo, $notifId, 1);
        
        $this->assertTrue($success);
        
        // Verify in database
        $stmt = $this->pdo->prepare("SELECT is_read, read_at FROM notifications WHERE id = ?");
        $stmt->execute([$notifId]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals(1, $notif['is_read']);
        $this->assertNotNull($notif['read_at']);
    }

    public function testMarkAsReadWithWrongUser(): void
    {
        $notifId = NotificationService::create(
            $this->pdo,
            1,
            'new_lead',
            'Test Notification',
            'Test Message'
        );
        
        // Try to mark as read with different user
        $success = NotificationService::markAsRead($this->pdo, $notifId, 2);
        
        $this->assertFalse($success);
    }

    public function testMarkAllAsRead(): void
    {
        // Create multiple notifications
        NotificationService::create($this->pdo, 1, 'test', 'Notif 1', 'Message 1');
        NotificationService::create($this->pdo, 1, 'test', 'Notif 2', 'Message 2');
        NotificationService::create($this->pdo, 2, 'test', 'Notif 3', 'Message 3'); // Different user
        
        $count = NotificationService::markAllAsRead($this->pdo, 1);
        
        $this->assertEquals(2, $count);
        
        // Verify only user 1's notifications are read
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = 1 AND is_read = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(2, $result['count']);
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = 2 AND is_read = 0");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, $result['count']);
    }

    public function testGetUnreadCount(): void
    {
        // Create notifications
        NotificationService::create($this->pdo, 1, 'test', 'Notif 1', 'Message 1');
        NotificationService::create($this->pdo, 1, 'test', 'Notif 2', 'Message 2');
        NotificationService::create($this->pdo, 1, 'test', 'Notif 3', 'Message 3');
        
        $count = NotificationService::getUnreadCount($this->pdo, 1);
        
        $this->assertEquals(3, $count);
        
        // Mark one as read
        $notifs = NotificationService::getUnread($this->pdo, 1, 1);
        NotificationService::markAsRead($this->pdo, (int)$notifs[0]['id'], 1);
        
        $count = NotificationService::getUnreadCount($this->pdo, 1);
        $this->assertEquals(2, $count);
    }

    public function testGetAllNotifications(): void
    {
        // Create notifications, some read
        $id1 = NotificationService::create($this->pdo, 1, 'test', 'Notif 1', 'Message 1');
        NotificationService::create($this->pdo, 1, 'test', 'Notif 2', 'Message 2');
        
        NotificationService::markAsRead($this->pdo, $id1, 1);
        
        $all = NotificationService::getAll($this->pdo, 1, 50, 0);
        
        $this->assertCount(2, $all);
        
        // Check that read status is included
        $readNotif = array_filter($all, fn($n) => $n['is_read'] == 1);
        $unreadNotif = array_filter($all, fn($n) => $n['is_read'] == 0);
        
        $this->assertCount(1, $readNotif);
        $this->assertCount(1, $unreadNotif);
    }

    public function testDeleteOldNotifications(): void
    {
        // Create a notification and mark as read
        $notifId = NotificationService::create($this->pdo, 1, 'test', 'Old Notif', 'Message');
        NotificationService::markAsRead($this->pdo, $notifId, 1);
        
        // Manually set read_at to 31 days ago
        $this->pdo->exec("UPDATE notifications SET read_at = datetime('now', '-31 days') WHERE id = $notifId");
        
        // Delete old notifications
        $deleted = NotificationService::deleteOld($this->pdo, 30);
        
        $this->assertEquals(1, $deleted);
        
        // Verify it's deleted
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM notifications WHERE id = $notifId");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(0, $result['count']);
    }

    public function testNotificationTypeFilter(): void
    {
        NotificationService::create($this->pdo, 1, 'new_lead', 'Lead Notif', 'Message');
        NotificationService::create($this->pdo, 1, 'campaign_completed', 'Campaign Notif', 'Message');
        NotificationService::create($this->pdo, 1, 'new_lead', 'Another Lead', 'Message');
        
        // Query by type
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = 1 AND type = ?");
        $stmt->execute(['new_lead']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals(2, $result['count']);
    }

    public function testNotificationDataJsonParsing(): void
    {
        $data = ['key' => 'value', 'number' => 123];
        $notifId = NotificationService::create($this->pdo, 1, 'test', 'Test', 'Message', $data);
        
        $notifications = NotificationService::getUnread($this->pdo, 1, 10);
        
        $this->assertArrayHasKey('data', $notifications[0]);
        $this->assertEquals($data, $notifications[0]['data']);
    }
}
