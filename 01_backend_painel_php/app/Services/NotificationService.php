<?php
declare(strict_types=1);

namespace RedeAlabama\Services;

use PDO;
use Throwable;

/**
 * Service for managing user notifications.
 */
class NotificationService
{
    /**
     * Create a new notification.
     *
     * @param PDO $pdo Database connection
     * @param int|null $userId User ID (null for system-wide)
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data as associative array
     * @return int Notification ID
     * @throws \Exception If creation fails
     */
    public static function create(
        PDO $pdo,
        ?int $userId,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): int {
        $sql = "INSERT INTO notifications (user_id, type, title, message, data_json, created_at)
                VALUES (:user_id, :type, :title, :message, :data_json, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':message' => $message,
            ':data_json' => empty($data) ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        
        return (int) $pdo->lastInsertId();
    }

    /**
     * Get unread notifications for a user.
     *
     * @param PDO $pdo Database connection
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications to return
     * @return array List of notifications
     */
    public static function getUnread(PDO $pdo, int $userId, int $limit = 10): array
    {
        $sql = "SELECT id, type, title, message, data_json, created_at
                FROM notifications
                WHERE user_id = :user_id AND is_read = 0
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON data
        foreach ($results as &$row) {
            if (!empty($row['data_json'])) {
                $row['data'] = json_decode($row['data_json'], true);
            } else {
                $row['data'] = [];
            }
            unset($row['data_json']);
        }
        
        return $results;
    }

    /**
     * Get all notifications for a user (read and unread).
     *
     * @param PDO $pdo Database connection
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications to return
     * @param int $offset Offset for pagination
     * @return array List of notifications
     */
    public static function getAll(PDO $pdo, int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT id, type, title, message, data_json, is_read, created_at, read_at
                FROM notifications
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON data
        foreach ($results as &$row) {
            if (!empty($row['data_json'])) {
                $row['data'] = json_decode($row['data_json'], true);
            } else {
                $row['data'] = [];
            }
            unset($row['data_json']);
        }
        
        return $results;
    }

    /**
     * Mark a notification as read.
     *
     * @param PDO $pdo Database connection
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for security check)
     * @return bool True if marked, false otherwise
     */
    public static function markAsRead(PDO $pdo, int $notificationId, int $userId): bool
    {
        $sql = "UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = :id AND user_id = :user_id AND is_read = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @param PDO $pdo Database connection
     * @param int $userId User ID
     * @return int Number of notifications marked as read
     */
    public static function markAllAsRead(PDO $pdo, int $userId): int
    {
        $sql = "UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = :user_id AND is_read = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->rowCount();
    }

    /**
     * Get count of unread notifications for a user.
     *
     * @param PDO $pdo Database connection
     * @param int $userId User ID
     * @return int Count of unread notifications
     */
    public static function getUnreadCount(PDO $pdo, int $userId): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM notifications
                WHERE user_id = :user_id AND is_read = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Delete old read notifications (cleanup).
     *
     * @param PDO $pdo Database connection
     * @param int $daysOld Number of days to keep read notifications
     * @return int Number of deleted notifications
     */
    public static function deleteOld(PDO $pdo, int $daysOld = 30): int
    {
        $sql = "DELETE FROM notifications
                WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => $daysOld]);
        
        return $stmt->rowCount();
    }
}
