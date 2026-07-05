<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notification_helper.php';

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$role = $_SESSION['role'] ?? '';
$baseUrl = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__)));

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, type, message, is_read, related_entity_id, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 20"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) use ($pdo, $role, $baseUrl) {
            return [
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'title' => notificationTypeLabel($row['type']),
                'message' => $row['message'],
                'is_read' => (bool) $row['is_read'],
                'related_entity_id' => $row['related_entity_id'] ? (int) $row['related_entity_id'] : null,
                'created_at' => $row['created_at'],
                'url' => notificationLink($pdo, $role, $row, $baseUrl),
            ];
        }, $rows);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
        );
        $countStmt->execute([$userId]);
        $unreadCount = (int) $countStmt->fetchColumn();

        echo json_encode(['unread_count' => $unreadCount, 'notifications' => $items]);
    } catch (PDOException $e) {
        error_log('Notifications API list error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load notifications']);
    }
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';
    $notificationId = (int) ($input['id'] ?? 0);

    if ($action === 'mark_read' && $notificationId > 0) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$notificationId, $userId]);

            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
            );
            $countStmt->execute([$userId]);

            echo json_encode([
                'success' => true,
                'unread_count' => (int) $countStmt->fetchColumn(),
            ]);
        } catch (PDOException $e) {
            error_log('Notifications API mark_read error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update notification']);
        }
        exit;
    }

    if ($action === 'delete' && $notificationId > 0) {
        try {
            $stmt = $pdo->prepare(
                "DELETE FROM notifications WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$notificationId, $userId]);

            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
            );
            $countStmt->execute([$userId]);

            echo json_encode([
                'success' => true,
                'unread_count' => (int) $countStmt->fetchColumn(),
            ]);
        } catch (PDOException $e) {
            error_log('Notifications API delete error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete notification']);
        }
        exit;
    }

    if ($action === 'mark_all_read') {
        try {
            $pdo->prepare(
                "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
            )->execute([$userId]);

            echo json_encode(['success' => true, 'unread_count' => 0]);
        } catch (PDOException $e) {
            error_log('Notifications API mark_all_read error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mark all as read']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
