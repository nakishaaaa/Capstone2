<?php
/**
 * User Support Conversations API
 * Returns a list of previous conversations for the logged-in user
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

session_start();

try {
    // Debug: Log session data
    error_log("Session data: " . json_encode($_SESSION));
    
    // Match session variables with user_page.php
    $userId = $_SESSION['user_user_id'] ?? $_SESSION['user_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? $_SESSION['email'] ?? null;
    $userName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? null;

    // Debug: Log extracted values
    error_log("Extracted - UserId: $userId, UserEmail: $userEmail, UserName: $userName");

    if (!$userId && !$userEmail && !$userName) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Not authenticated',
            'debug' => [
                'userId' => $userId,
                'userEmail' => $userEmail,
                'userName' => $userName,
                'session_keys' => array_keys($_SESSION)
            ]
        ]);
        exit();
    }

    // Fetch conversations for this user
    // We group by conversation_id and return last message, last updated time, subject (last non-null), and message count
    $query = "
        SELECT 
            sm.conversation_id,
            MAX(sm.created_at) AS last_updated,
            (SELECT s2.subject FROM support_messages s2 
             WHERE s2.conversation_id = sm.conversation_id AND s2.subject IS NOT NULL 
             ORDER BY s2.created_at DESC LIMIT 1) AS subject,
            (SELECT s3.message FROM support_messages s3 
             WHERE s3.conversation_id = sm.conversation_id 
             ORDER BY s3.created_at DESC LIMIT 1) AS last_message,
            COUNT(*) AS message_count,
            SUM(CASE WHEN is_admin = 1 AND is_read = 0 THEN 1 ELSE 0 END) AS unread_admin_messages
        FROM support_messages sm
        WHERE (sm.user_id = :uid OR sm.user_email = :uemail OR sm.user_name = :uname)
        GROUP BY sm.conversation_id
        ORDER BY last_updated DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':uid' => $userId,
        ':uemail' => $userEmail,
        ':uname' => $userName,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log query results
    error_log("Query returned " . count($rows) . " rows");
    error_log("Raw rows: " . json_encode($rows));

    // Format
    $conversations = array_map(function ($r) {
        return [
            'conversation_id' => $r['conversation_id'],
            'subject' => $r['subject'] ?: 'General',
            'last_message' => $r['last_message'],
            'last_updated' => $r['last_updated'],
            'last_updated_human' => timeAgo($r['last_updated']),
            'message_count' => (int)$r['message_count'],
            'unread' => (int)$r['unread_admin_messages'],
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'data' => [
            'conversations' => $conversations
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load conversations', 'error' => $e->getMessage()]);
}

function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    return floor($time/31536000) . 'y ago';
}
