<?php
/**
 * Simple Migration Script - user_requests to normalized tables
 * Simplified version to avoid 500 errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple database connection
$conn = new mysqli('localhost', 'root', '', 'users_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Simple migration function
function migrateData($conn) {
    $stats = ['migrated' => 0, 'errors' => 0];
    
    echo "Starting migration...\n";
    
    // Get all user requests
    $result = $conn->query("SELECT * FROM user_requests WHERE deleted = 0 ORDER BY id");
    
    if (!$result) {
        die("Error fetching user_requests: " . $conn->error);
    }
    
    $conn->autocommit(false); // Start transaction
    
    while ($row = $result->fetch_assoc()) {
        try {
            // 1. Insert into customer_requests
            $stmt = $conn->prepare("
                INSERT INTO customer_requests (
                    id, user_id, category, size, custom_size, quantity, name, 
                    contact_number, notes, status, admin_response, created_at, 
                    updated_at, is_read, deleted
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Map status
            $newStatus = 'pending';
            if (in_array($row['status'], ['approved', 'printing', 'ready_for_pickup', 'on_the_way', 'completed'])) {
                $newStatus = 'approved';
            } elseif ($row['status'] == 'rejected') {
                $newStatus = 'rejected';
            } elseif ($row['status'] == 'cancelled') {
                $newStatus = 'cancelled';
            }
            
            $stmt->bind_param('iisssissssssii',
                $row['id'], $row['user_id'], $row['category'], $row['size'], 
                $row['custom_size'], $row['quantity'], $row['name'], 
                $row['contact_number'], $row['notes'], $newStatus, 
                $row['admin_response'], $row['created_at'], $row['updated_at'], 
                $row['is_read'], $row['deleted']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Customer request insert failed: " . $stmt->error);
            }
            $stmt->close();
            
            // 2. Insert into approved_orders if needed
            if ($newStatus == 'approved') {
                $stmt2 = $conn->prepare("
                    INSERT INTO approved_orders (
                        request_id, total_price, downpayment_percentage, downpayment_amount,
                        paid_amount, payment_date, payment_status, payment_method,
                        paymongo_link_id, production_status, pricing_set_at,
                        production_started_at, ready_at, completed_at, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Map production status
                $prodStatus = 'pending';
                if ($row['status'] == 'printing') $prodStatus = 'printing';
                elseif ($row['status'] == 'ready_for_pickup') $prodStatus = 'ready_for_pickup';
                elseif ($row['status'] == 'on_the_way') $prodStatus = 'on_the_way';
                elseif ($row['status'] == 'completed') $prodStatus = 'completed';
                
                $stmt2->bind_param('idddssssssssssss',
                    $row['id'], $row['total_price'], $row['downpayment_percentage'],
                    $row['downpayment_amount'], $row['paid_amount'], $row['payment_date'],
                    $row['payment_status'], $row['payment_method'], $row['paymongo_link_id'],
                    $prodStatus, $row['pricing_set_at'], $row['production_started_at'],
                    $row['ready_at'], $row['completed_at'], $row['created_at'], $row['updated_at']
                );
                
                if (!$stmt2->execute()) {
                    throw new Exception("Approved order insert failed: " . $stmt2->error);
                }
                $stmt2->close();
            }
            
            // 3. Insert request details if needed
            if ($row['design_option'] || $row['tag_location'] || $row['size_breakdown']) {
                $stmt3 = $conn->prepare("
                    INSERT INTO request_details (
                        request_id, design_option, tag_location, size_breakdown, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt3->bind_param('isssss',
                    $row['id'], $row['design_option'], $row['tag_location'],
                    $row['size_breakdown'], $row['created_at'], $row['updated_at']
                );
                
                if (!$stmt3->execute()) {
                    throw new Exception("Request details insert failed: " . $stmt3->error);
                }
                $stmt3->close();
            }
            
            // 4. Insert attachments
            $attachments = [];
            
            if ($row['image_path']) {
                // Handle JSON array or single path
                $paths = json_decode($row['image_path'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $paths = [$row['image_path']];
                }
                foreach ($paths as $path) {
                    $attachments[] = ['path' => $path, 'type' => 'main'];
                }
            }
            
            if ($row['front_image_path']) {
                $attachments[] = ['path' => $row['front_image_path'], 'type' => 'front'];
            }
            if ($row['back_image_path']) {
                $attachments[] = ['path' => $row['back_image_path'], 'type' => 'back'];
            }
            if ($row['tag_image_path']) {
                $attachments[] = ['path' => $row['tag_image_path'], 'type' => 'tag'];
            }
            
            foreach ($attachments as $att) {
                $stmt4 = $conn->prepare("
                    INSERT INTO request_attachments (request_id, file_path, file_type, uploaded_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt4->bind_param('iss', $row['id'], $att['path'], $att['type']);
                $stmt4->execute();
                $stmt4->close();
            }
            
            $stats['migrated']++;
            echo "Migrated request ID: {$row['id']}\n";
            
        } catch (Exception $e) {
            $stats['errors']++;
            echo "Error migrating request {$row['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    $conn->commit();
    $conn->autocommit(true);
    
    return $stats;
}

// Web interface
if (isset($_GET['run'])) {
    header('Content-Type: text/plain');
    
    echo "=== SIMPLE MIGRATION STARTING ===\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    $stats = migrateData($conn);
    
    echo "\n=== MIGRATION COMPLETED ===\n";
    echo "Migrated: {$stats['migrated']}\n";
    echo "Errors: {$stats['errors']}\n";
    
    // Verify
    $original = $conn->query("SELECT COUNT(*) as c FROM user_requests WHERE deleted = 0")->fetch_assoc()['c'];
    $new = $conn->query("SELECT COUNT(*) as c FROM customer_requests WHERE deleted = 0")->fetch_assoc()['c'];
    
    echo "Original records: $original\n";
    echo "New records: $new\n";
    echo "Status: " . ($original == $new ? "SUCCESS" : "MISMATCH") . "\n";
    
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .button { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>Simple Migration Tool</h1>
    
    <div class="warning">
        <strong>Ready to migrate:</strong> Found 24 user_requests records to migrate.
    </div>
    
    <p>This simplified migration will:</p>
    <ul>
        <li>Migrate all 24 user_requests to customer_requests</li>
        <li>Create approved_orders for approved requests</li>
        <li>Add request_details for customization data</li>
        <li>Import all file attachments</li>
    </ul>
    
    <p>
        <a href="?run=1" class="button" onclick="return confirm('Start migration?')">
            Run Simple Migration
        </a>
    </p>
</body>
</html>
