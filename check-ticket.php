<?php
/**
 * Anonymous Ticket Lookup Page
 * Allows anonymous users to check their support ticket status and replies
 */

session_start();
require_once 'includes/config.php';

// No CSRF token needed for ticket lookup (read-only operation)

$ticket_id = '';
$conversation = null;
$messages = [];
$error = '';

// Handle ticket lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = trim($_POST['ticket_id']);
    
    if (empty($ticket_id)) {
        $error = 'Please enter a ticket ID';
    } else {
        try {
            // Search for the ticket in support_messages by subject pattern (any message in conversation)
            $stmt = $conn->prepare("
                SELECT conversation_id, user_email, subject, message, created_at, conversation_status 
                FROM support_messages 
                WHERE subject LIKE ? AND message_type = 'customer_support'
                ORDER BY created_at ASC
                LIMIT 1
            ");
            
            // Search for the ticket ID in the subject field
            $searchPattern = "%[" . $ticket_id . "]%";
            $stmt->bind_param("s", $searchPattern);
            
            // Debug: Log what we're searching for
            error_log("Searching for ticket: " . $ticket_id);
            error_log("Search pattern: " . $searchPattern);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Debug: Log how many results found
            error_log("Results found: " . $result->num_rows);
            
            if ($result->num_rows > 0) {
                $conversation = $result->fetch_assoc();
                error_log("Found conversation: " . $conversation['conversation_id'] . " with subject: " . $conversation['subject']);
                
                // Get all messages in this conversation
                $stmt2 = $conn->prepare("
                    SELECT user_name, user_email, message, is_admin, created_at 
                    FROM support_messages 
                    WHERE conversation_id = ? AND message_type = 'customer_support'
                    ORDER BY created_at ASC
                ");
                
                $stmt2->bind_param("s", $conversation['conversation_id']);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                while ($row = $result2->fetch_assoc()) {
                    $messages[] = $row;
                }
                
                $stmt2->close();
            } else {
                // Debug: Show what subjects exist in the database
                $debugStmt = $conn->prepare("
                    SELECT subject, conversation_id, created_at
                    FROM support_messages 
                    WHERE message_type = 'customer_support' AND is_admin = 0 
                    AND subject LIKE '%ANON-2025-8006%'
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                $debugStmt->execute();
                $debugResult = $debugStmt->get_result();
                
                error_log("Searching specifically for ANON-2025-8006:");
                if ($debugResult->num_rows > 0) {
                    while ($debugRow = $debugResult->fetch_assoc()) {
                        error_log("Found: '" . $debugRow['subject'] . "' | Conversation: " . $debugRow['conversation_id'] . " | Date: " . $debugRow['created_at']);
                    }
                } else {
                    error_log("ANON-2025-8006 not found in support_messages table");
                }
                $debugStmt->close();
                
                // Also check support_tickets_messages table
                $debugStmt2 = $conn->prepare("
                    SELECT subject, conversation_id, created_at
                    FROM support_tickets_messages 
                    WHERE is_admin = 0 
                    AND (subject LIKE '%ANON-2025-8006%' OR conversation_id LIKE '%ANON-2025-8006%')
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                $debugStmt->execute();
                $debugResult = $debugStmt->get_result();
                
                error_log("Recent subjects in database:");
                while ($debugRow = $debugResult->fetch_assoc()) {
                    error_log("Subject: '" . $debugRow['subject'] . "' | Conversation: " . $debugRow['conversation_id']);
                }
                $debugStmt->close();
                
                $error = 'Ticket not found. Please check your ticket ID and try again.';
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error = 'An error occurred while searching for your ticket.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Support Ticket - 053 Prints</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f0f2f5;
            color: #1c1e21;
            line-height: 1.34;
        }
        
        .ticket-lookup-container {
            max-width: 680px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .page-title {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #e4e6ea;
        }
        
        .page-title h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1c1e21;
        }
        
        .page-title p {
            font-size: 14px;
            color: #65676b;
        }
        
        .ticket-form {
            padding: 20px;
            border-bottom: 1px solid #e4e6ea;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #1c1e21;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #dddfe2;
            border-radius: 6px;
            font-size: 15px;
            background: #f5f6f7;
            transition: all 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4267B2;
            background: white;
            box-shadow: 0 0 0 2px rgba(66, 103, 178, 0.2);
        }
        
        .btn-primary {
            background: #4267B2;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: #365899;
            transform: translateY(-1px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            border-left: 4px solid #f44336;
            font-size: 14px;
        }
        
        .ticket-info {
            background: #f5f6f7;
            padding: 16px 20px;
            border-bottom: 1px solid #e4e6ea;
        }
        
        .ticket-info h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1c1e21;
        }
        
        .ticket-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #65676b;
        }
        
        .conversation-container {
            background: white;
            max-height: 500px;
            overflow-y: auto;
            padding: 0;
        }
        
        .conversation-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e4e6ea;
            background: #f5f6f7;
            font-weight: 600;
            font-size: 16px;
            color: #1c1e21;
        }
        
        .messages-container {
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            height: 300px;
            max-height: 300px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .message {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            max-width: 70%;
        }
        
        .message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            color: white;
            flex-shrink: 0;
        }
        
        .message.admin .message-avatar {
            background: #4267B2;
        }
        
        .message.user .message-avatar {
            background: #42b883;
        }
        
        .message-bubble {
            background: #f0f0f0;
            border-radius: 18px;
            padding: 12px 16px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message.admin .message-bubble {
            background: #4267B2;
            color: white;
        }
        
        .message.user .message-bubble {
            background: #e4e6ea;
            color: #1c1e21;
        }
        
        .message-time {
            font-size: 11px;
            color: #65676b;
            margin-top: 4px;
            text-align: center;
        }
        
        .message.user .message-time {
            text-align: right;
        }
        
        .reply-section {
            padding: 16px 20px;
            border-top: 1px solid #e4e6ea;
            background: #f5f6f7;
        }
        
        .reply-section h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1c1e21;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .reply-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .reply-input-container {
            flex: 1;
            position: relative;
        }
        
        .reply-form textarea {
            width: 100%;
            min-height: 40px;
            max-height: 120px;
            padding: 12px 16px;
            border: 1px solid #dddfe2;
            border-radius: 20px;
            font-size: 15px;
            font-family: inherit;
            resize: none;
            background: white;
            transition: all 0.2s;
        }
        
        .reply-form textarea:focus {
            outline: none;
            border-color: #4267B2;
            box-shadow: 0 0 0 2px rgba(66, 103, 178, 0.2);
        }
        
        .reply-form textarea::placeholder {
            color: #8a8d91;
        }
        
        .send-button {
            background: #4267B2;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        
        .send-button:hover {
            background: #365899;
            transform: scale(1.05);
        }
        
        .send-button:disabled {
            background: #bcc0c4;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4267B2;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin: 20px;
            transition: all 0.2s;
        }
        
        .back-button:hover {
            color: #365899;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            flex-shrink: 0;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: #42b883;
            color: white;
        }
        
        .message-status {
            padding: 12px 20px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            margin: 16px 20px;
            border-radius: 4px;
            font-size: 14px;
            color: #1565c0;
        }
        
        @media (max-width: 768px) {
            .ticket-lookup-container {
                margin: 10px;
                border-radius: 0;
            }
            
            .message {
                max-width: 85%;
            }
            
            .header {
                padding: 16px;
            }
            
            .ticket-form, .messages-container, .reply-section {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        Back to Home
    </a>
    
    <div class="ticket-lookup-container">
        <div class="page-title">
            <h1>Check Support Ticket</h1>
            <p>Enter your ticket ID to view your support request and any replies from our team.</p>
        </div>
        
        <form method="POST" class="ticket-form">
            <div class="form-group">
                <label for="ticket_id">Ticket ID</label>
                <input 
                    type="text" 
                    id="ticket_id" 
                    name="ticket_id" 
                    value="<?= htmlspecialchars($ticket_id) ?>"
                    placeholder="e.g., ANON-2025-1234"
                    required
                >
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-search"></i> Look Up Ticket
            </button>
        </form>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($conversation): ?>
            <div class="ticket-info">
                <h2>Ticket: <?= htmlspecialchars($ticket_id) ?></h2>
                <div class="ticket-meta">
                    <span><strong>Subject:</strong> <?= htmlspecialchars(preg_replace('/^\[.*?\]\s*/', '', $conversation['subject'])) ?></span>
                    <span><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($conversation['created_at'])) ?></span>
                    <?php 
                    $status = $conversation['conversation_status'] ?? 'open';
                    $statusColors = [
                        'open' => '#0f62fe',
                        'solved' => '#059669',
                        'closed' => '#6b7280'
                    ];
                    $statusColor = $statusColors[$status] ?? '#0f62fe';
                    ?>
                    <span class="status-indicator" style="background: <?= $statusColor ?>">
                        <?= strtoupper($status) ?>
                    </span>
                </div>
            </div>
            
            <div class="conversation-container">
                <div class="conversation-header">
                    <i class="fas fa-comments"></i> Conversation
                </div>
                
                <div class="messages-container">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?= $message['is_admin'] ? 'admin' : 'user' ?>">
                            <div class="message-avatar">
                                <?php if ($message['is_admin']): ?>
                                    <i class="fas fa-headset"></i>
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="message-bubble">
                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                </div>
                                <div class="message-time">
                                    <?= date('M j, g:i A', strtotime($message['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                
                    <?php if (count($messages) === 1): ?>
                        <div class="message-status">
                            <i class="fas fa-info-circle"></i>
                            We've received your message and will respond soon. We usually reply within a few hours.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reply Form Section -->
            <?php if ($status === 'open'): ?>
                <div class="reply-section">
                    <h4>
                        <i class="fas fa-reply"></i>
                        Continue Conversation
                    </h4>
                    
                    <form id="replyForm" class="reply-form">
                        <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket_id) ?>">
                        <input type="hidden" name="conversation_id" value="<?= htmlspecialchars($conversation['conversation_id']) ?>">
                        
                        <div class="reply-input-container">
                            <textarea 
                                id="reply_message" 
                                name="message" 
                                required 
                                placeholder="Type your message..."
                            ></textarea>
                        </div>
                        
                        <button type="submit" class="send-button" id="replySubmitBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                    
                    <div id="replyMessage" style="margin-top: 12px; padding: 12px; border-radius: 6px; display: none;"></div>
                </div>
            <?php else: ?>
                <div class="reply-section" style="background: #f3f4f6; border: 1px solid #e5e7eb;">
                    <div style="text-align: center; padding: 20px; color: #6b7280;">
                        <i class="fas fa-<?= $status === 'solved' ? 'check-circle' : 'lock' ?>" style="font-size: 32px; margin-bottom: 12px; color: <?= $statusColor ?>"></i>
                        <h4 style="margin: 0 0 8px 0; color: #374151;">Ticket <?= ucfirst($status) ?></h4>
                        <p style="margin: 0; font-size: 14px;">
                            <?php if ($status === 'solved'): ?>
                                This ticket has been marked as solved by our support team.
                            <?php else: ?>
                                This ticket has been closed and no new messages can be sent.
                            <?php endif; ?>
                        </p>
                        <p style="margin: 8px 0 0 0; font-size: 13px;">
                            If you need further assistance, please submit a new support request.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh page every 30 seconds to check for new admin replies
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            // Only auto-refresh if we have a conversation loaded
            <?php if ($conversation): ?>
            autoRefreshInterval = setInterval(() => {
                // Check if user is not currently typing
                const replyTextarea = document.getElementById('reply_message');
                if (!replyTextarea || replyTextarea.value.trim() === '') {
                    window.location.reload();
                }
            }, 30000); // 30 seconds
            <?php endif; ?>
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        // Handle reply form submission
        document.addEventListener('DOMContentLoaded', function() {
            // Start auto-refresh
            startAutoRefresh();
            
            // Stop auto-refresh when user starts typing
            const replyTextarea = document.getElementById('reply_message');
            if (replyTextarea) {
                replyTextarea.addEventListener('focus', stopAutoRefresh);
                replyTextarea.addEventListener('blur', startAutoRefresh);
            }
            const replyForm = document.getElementById('replyForm');
            if (replyForm) {
                replyForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const submitBtn = document.getElementById('replySubmitBtn');
                    const messageDiv = document.getElementById('replyMessage');
                    const originalText = submitBtn.innerHTML;
                    
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    submitBtn.disabled = true;
                    
                    const formData = new FormData(this);
                    formData.append('action', 'anonymous_reply');
                    
                    try {
                        const response = await fetch('api/anonymous_reply.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Show success message
                            messageDiv.style.display = 'block';
                            messageDiv.style.background = '#d4edda';
                            messageDiv.style.color = '#155724';
                            messageDiv.style.border = '1px solid #c3e6cb';
                            messageDiv.textContent = data.message || 'Reply sent successfully!';
                            
                            // Clear form
                            document.getElementById('reply_message').value = '';
                            
                            // Reload page after 2 seconds to show new message
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                            
                        } else {
                            // Show error message
                            messageDiv.style.display = 'block';
                            messageDiv.style.background = '#f8d7da';
                            messageDiv.style.color = '#721c24';
                            messageDiv.style.border = '1px solid #f5c6cb';
                            messageDiv.textContent = data.message || 'Failed to send reply.';
                        }
                        
                    } catch (error) {
                        console.error('Error:', error);
                        messageDiv.style.display = 'block';
                        messageDiv.style.background = '#f8d7da';
                        messageDiv.style.color = '#721c24';
                        messageDiv.style.border = '1px solid #f5c6cb';
                        messageDiv.textContent = 'Network error. Please try again.';
                    } finally {
                        // Restore button
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>
