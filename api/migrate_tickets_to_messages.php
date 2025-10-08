<?php
/**
 * Migration Script: Move Support Tickets to support_tickets_messages
 * This migrates existing support_tickets to the new support_tickets_messages table
 */

session_start();
require_once '../includes/config.php';

// Check if user is developer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'developer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    // Get all support tickets that haven't been migrated yet
    $ticketsQuery = "
        SELECT st.*, u.email as customer_email
        FROM support_tickets st
        LEFT JOIN users u ON st.user_id = u.id
        WHERE st.id NOT IN (
            SELECT DISTINCT CAST(SUBSTRING(conversation_id, 8) AS UNSIGNED) 
            FROM support_tickets_messages 
            WHERE conversation_id LIKE 'ticket_%'
        )
        ORDER BY st.created_at ASC
    ";
    
    $result = $conn->query($ticketsQuery);
    $migratedCount = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($ticket = $result->fetch_assoc()) {
            $conversationId = 'ticket_' . $ticket['id'];
            $customerEmail = $ticket['customer_email'] ?: 'no-email@example.com';
            
            // Insert original customer message
            $insertCustomer = "
                INSERT INTO support_tickets_messages 
                (conversation_id, user_id, user_name, user_email, admin_name, subject, message, is_admin, created_at, is_read) 
                VALUES (?, ?, ?, ?, ?, ?, ?, FALSE, ?, TRUE)
            ";
            $stmt = $conn->prepare($insertCustomer);
            $adminName = null;
            $stmt->bind_param("sissssss", 
                $conversationId,
                $ticket['user_id'],
                $ticket['username'],
                $customerEmail,
                $adminName,
                $ticket['subject'],
                $ticket['message'],
                $ticket['created_at']
            );
            $stmt->execute();
            
            // Insert admin response if exists
            if (!empty($ticket['admin_response'])) {
                $insertAdmin = "
                    INSERT INTO support_tickets_messages 
                    (conversation_id, user_id, user_name, user_email, admin_name, subject, message, is_admin, created_at, is_read) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?, TRUE)
                ";
                $stmt = $conn->prepare($insertAdmin);
                $adminUsername = $ticket['admin_username'] ?: 'Super Admin';
                $stmt->bind_param("sissssss", 
                    $conversationId,
                    $ticket['user_id'],
                    $ticket['username'],
                    $customerEmail,
                    $adminUsername,
                    $ticket['subject'],
                    $ticket['admin_response'],
                    $ticket['updated_at']
                );
                $stmt->execute();
            }
            
            $migratedCount++;
        }
    }
    
    // Get stats
    $totalTickets = "SELECT COUNT(*) as count FROM support_tickets";
    $totalResult = $conn->query($totalTickets);
    $total = $totalResult->fetch_assoc()['count'];
    
    $totalConversations = "SELECT COUNT(DISTINCT conversation_id) as count FROM support_tickets_messages";
    $conversationsResult = $conn->query($totalConversations);
    $conversations = $conversationsResult->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration completed successfully',
        'data' => [
            'migrated_tickets' => $migratedCount,
            'total_tickets' => $total,
            'total_conversations' => $conversations
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
