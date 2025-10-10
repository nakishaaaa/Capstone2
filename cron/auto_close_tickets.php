<?php
/**
 * Auto-close Support Tickets
 * This script should be run daily via cron job to automatically close tickets
 * that have been inactive for 7 days after the last admin reply
 */

require_once __DIR__ . '/../includes/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log function
function logMessage($message) {
    $logFile = __DIR__ . '/auto_close_tickets.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("Starting auto-close ticket process...");
    
    // Find conversations where:
    // 1. Status is 'open'
    // 2. Last message was from admin (is_admin = 1)
    // 3. Last message was more than 7 days ago
    // 4. Message type is 'customer_support'
    
    $query = "
        SELECT DISTINCT sm.conversation_id, 
               MAX(sm.created_at) as last_message_time,
               (SELECT s2.is_admin FROM support_messages s2 
                WHERE s2.conversation_id = sm.conversation_id 
                AND s2.message_type = 'customer_support'
                ORDER BY s2.created_at DESC LIMIT 1) as last_is_admin,
               (SELECT s3.conversation_status FROM support_messages s3 
                WHERE s3.conversation_id = sm.conversation_id 
                AND s3.message_type = 'customer_support'
                ORDER BY s3.created_at DESC LIMIT 1) as current_status
        FROM support_messages sm
        WHERE sm.message_type = 'customer_support'
        GROUP BY sm.conversation_id
        HAVING last_is_admin = 1 
           AND current_status = 'open'
           AND last_message_time < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $closedCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $conversationId = $row['conversation_id'];
        $lastMessageTime = $row['last_message_time'];
        
        // Update all messages in this conversation to 'solved' status
        $updateQuery = "
            UPDATE support_messages 
            SET conversation_status = 'solved' 
            WHERE conversation_id = ? 
            AND message_type = 'customer_support'
        ";
        
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("s", $conversationId);
        
        if ($updateStmt->execute()) {
            $closedCount++;
            logMessage("Auto-closed conversation: $conversationId (last message: $lastMessageTime)");
            
            // Optional: Add a system message to the conversation
            $systemMessage = "This ticket has been automatically closed due to inactivity. If you need further assistance, please submit a new support request.";
            
            // Get conversation details for the system message
            $detailQuery = "
                SELECT user_name, user_email, subject 
                FROM support_messages 
                WHERE conversation_id = ? 
                AND message_type = 'customer_support'
                LIMIT 1
            ";
            
            $detailStmt = $conn->prepare($detailQuery);
            $detailStmt->bind_param("s", $conversationId);
            $detailStmt->execute();
            $detailResult = $detailStmt->get_result();
            
            if ($details = $detailResult->fetch_assoc()) {
                // Insert system message
                $insertQuery = "
                    INSERT INTO support_messages 
                    (conversation_id, user_name, user_email, admin_name, subject, message, 
                     message_type, conversation_status, is_admin, created_at) 
                    VALUES (?, ?, ?, 'System', ?, ?, 'customer_support', 'solved', 1, NOW())
                ";
                
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("sssss", 
                    $conversationId, 
                    $details['user_name'], 
                    $details['user_email'],
                    $details['subject'],
                    $systemMessage
                );
                
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $detailStmt->close();
        }
        
        $updateStmt->close();
    }
    
    $stmt->close();
    
    logMessage("Auto-close process completed. Closed $closedCount ticket(s).");
    
    // Also close anonymous tickets (those with ANON- prefix in subject)
    $anonQuery = "
        SELECT DISTINCT sm.conversation_id, 
               MAX(sm.created_at) as last_message_time,
               sm.subject,
               (SELECT s2.is_admin FROM support_messages s2 
                WHERE s2.conversation_id = sm.conversation_id 
                AND s2.message_type = 'customer_support'
                ORDER BY s2.created_at DESC LIMIT 1) as last_is_admin,
               (SELECT s3.conversation_status FROM support_messages s3 
                WHERE s3.conversation_id = sm.conversation_id 
                AND s3.message_type = 'customer_support'
                ORDER BY s3.created_at DESC LIMIT 1) as current_status
        FROM support_messages sm
        WHERE sm.message_type = 'customer_support'
          AND sm.subject LIKE '%ANON-%'
        GROUP BY sm.conversation_id
        HAVING last_is_admin = 1 
           AND current_status = 'open'
           AND last_message_time < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
    
    $anonStmt = $conn->prepare($anonQuery);
    $anonStmt->execute();
    $anonResult = $anonStmt->get_result();
    
    $anonClosedCount = 0;
    
    while ($row = $anonResult->fetch_assoc()) {
        $conversationId = $row['conversation_id'];
        
        // Update to solved status
        $updateQuery = "
            UPDATE support_messages 
            SET conversation_status = 'solved' 
            WHERE conversation_id = ? 
            AND message_type = 'customer_support'
        ";
        
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("s", $conversationId);
        
        if ($updateStmt->execute()) {
            $anonClosedCount++;
            logMessage("Auto-closed anonymous ticket: $conversationId (subject: {$row['subject']})");
        }
        
        $updateStmt->close();
    }
    
    $anonStmt->close();
    
    if ($anonClosedCount > 0) {
        logMessage("Closed $anonClosedCount anonymous ticket(s).");
    }
    
    echo "Success: Closed " . ($closedCount + $anonClosedCount) . " total ticket(s)\n";
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
