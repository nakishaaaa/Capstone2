<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get form data
    $priority = $_POST['priority'] ?? 'medium';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['user_name'] ?? '';
    
    // Validate required fields
    if (empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        exit();
    }
    
    // Validate priority
    $valid_priorities = ['low', 'medium', 'high'];
    if (!in_array($priority, $valid_priorities)) {
        $priority = 'medium';
    }
    
    // Handle file attachment if present
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/support_attachments/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = 'support_' . $user_id . '_' . time() . '.' . $file_extension;
            $attachment_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
                $attachment_path = null;
            }
        }
    }
    
    // Get user's IP address
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Insert support ticket into database
    $stmt = $conn->prepare("
        INSERT INTO support_tickets (
            user_id, username, subject, message, priority, status, 
            attachment_path, ip_address, user_agent, created_at
        ) VALUES (?, ?, ?, ?, ?, 'open', ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param(
        "isssssss", 
        $user_id, $username, $subject, $message, $priority, 
        $attachment_path, $ip_address, $user_agent
    );
    
    if ($stmt->execute()) {
        $ticket_id = $conn->insert_id;
        
        // Try to log the support ticket creation in audit logs (optional)
        try {
            $audit_stmt = $conn->prepare("
                INSERT INTO audit_logs (user_id, username, action, description, ip_address, user_agent, created_at) 
                VALUES (?, ?, 'support_ticket', ?, ?, ?, NOW())
            ");
            
            if ($audit_stmt) {
                $audit_description = "Support ticket created: #{$ticket_id} - {$subject}";
                $audit_stmt->bind_param("issss", $user_id, $username, $audit_description, $ip_address, $user_agent);
                $audit_stmt->execute();
                $audit_stmt->close();
            }
        } catch (Exception $audit_error) {
            // Log audit error but don't fail the ticket submission
            error_log("Audit log error (non-critical): " . $audit_error->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Support ticket submitted successfully!',
            'ticket_id' => $ticket_id
        ]);
    } else {
        throw new Exception('Failed to submit support ticket');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Support ticket submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
