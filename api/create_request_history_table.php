<?php
// Script to create the request_history table
require_once '../config/database.php';

try {
    // Create request_history table
    $sql = "CREATE TABLE IF NOT EXISTS `request_history` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `original_request_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `category` varchar(50) NOT NULL,
        `size` varchar(50) NOT NULL,
        `quantity` int(11) NOT NULL,
        `name` varchar(100) NOT NULL,
        `contact_number` varchar(20) NOT NULL,
        `notes` text DEFAULT NULL,
        `image_path` varchar(255) DEFAULT NULL,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `admin_response` text DEFAULT NULL,
        `total_price` decimal(10,2) DEFAULT NULL,
        `downpayment_percentage` int(11) DEFAULT 30,
        `downpayment_amount` decimal(10,2) DEFAULT NULL,
        `paid_amount` decimal(10,2) DEFAULT 0.00,
        `payment_date` timestamp NULL DEFAULT NULL,
        `payment_status` enum('pending_pricing','awaiting_payment','partial_paid','fully_paid','failed','processing') DEFAULT 'pending_pricing',
        `payment_method` varchar(50) DEFAULT NULL,
        `paymongo_link_id` varchar(255) DEFAULT NULL,
        `pricing_set_at` timestamp NULL DEFAULT NULL,
        `request_created_at` timestamp NOT NULL,
        `request_updated_at` timestamp NOT NULL,
        `cleared_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `cleared_by` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_original_request_id` (`original_request_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`),K
        KEY `idx_cleared_at` (`cleared_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "request_history table created successfully!\n";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
