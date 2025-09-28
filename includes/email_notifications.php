<?php
/**
 * Email Notifications Helper Class
 * Handles sending email notifications for order status updates
 */

require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/enhanced_audit.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailNotifications {
    
    /**
     * Send email notification
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email message (HTML)
     * @return bool Success status
     */
    public static function send($to, $subject, $message) {
        try {
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // SMTP Configuration (same as your existing mailer.php)
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Host = "smtp.gmail.com";
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->Username = "053printsaturservice@gmail.com";
            $mail->Password = "ipwy mhmi isoe musc"; // ipwy mhmi isoe musc //
            
            // Email settings
            $mail->isHTML(true);
            $mail->setFrom("053printsaturservice@gmail.com", "053 PRINTS");
            $mail->addReplyTo("053printsaturservice@gmail.com", "053 PRINTS Support");
            $mail->addAddress($to);
            
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Send email
            $mail->send();
            error_log("Email sent successfully to: $to");
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            logEmailError($to, $subject, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send order status update notification
     * @param array $order Order details
     * @param string $newStatus New order status
     * @param string $note Optional note
     * @return bool Success status
     */
    public static function sendOrderStatusUpdate($order, $newStatus, $note = '') {
        if (!$order['customer_email']) {
            return false;
        }
        
        $statusLabels = [
            'printing' => 'Printing in Progress',
            'ready_for_pickup' => 'Ready for Pickup',
            'on_the_way' => 'Out for Delivery',
            'completed' => 'Order Completed'
        ];
        
        $statusLabel = $statusLabels[$newStatus] ?? ucfirst(str_replace('_', ' ', $newStatus));
        $subject = "Order Update: Your Order #" . $order['id'] . " is " . $statusLabel;
        
        $message = self::buildOrderUpdateTemplate($order, $newStatus, $statusLabel, $note);
        
        return self::send($order['customer_email'], $subject, $message);
    }
    
    /**
     * Build HTML email template for order updates
     * @param array $order Order details
     * @param string $newStatus New status
     * @param string $statusLabel Formatted status label
     * @param string $note Optional note
     * @return string HTML email content
     */
    private static function buildOrderUpdateTemplate($order, $newStatus, $statusLabel, $note) {
        $customerName = htmlspecialchars($order['customer_name'] ?: $order['name']);
        $serviceName = htmlspecialchars(ucwords(str_replace('-', ' ', $order['category'])));
        $totalPrice = number_format($order['total_price'], 2);
        
        // Status-specific messages
        $statusMessage = '';
        switch ($newStatus) {
            case 'printing':
                $statusMessage = "Great news! Your order is now being printed. We'll notify you once it's ready for pickup.";
                break;
            case 'ready_for_pickup':
                $statusMessage = "🎉 Your order is ready for pickup! Please visit our store during business hours.";
                break;
            case 'on_the_way':
                $statusMessage = "🚚 Your order is on the way! Our delivery team will contact you shortly.";
                break;
            case 'completed':
                $statusMessage = "✅ Your order has been completed! Thank you for choosing 053 PRINTS.";
                break;
        }
        
        $noteSection = $note ? "<p><strong>Update Note:</strong> " . htmlspecialchars($note) . "</p>" : '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Order Status Update - 053 PRINTS</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: #1a1a1a; 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                    font-weight: 600; 
                }
                .content { 
                    padding: 30px 20px; 
                }
                .status-badge { 
                    display: inline-block; 
                    padding: 10px 20px; 
                    background: #1a1a1a; 
                    color: white; 
                    border-radius: 25px; 
                    font-weight: bold; 
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .order-details { 
                    background: #f8f9fa; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                }
                .order-details h3 { 
                    margin: 0 0 15px 0; 
                    color: #333; 
                    font-size: 18px;
                }
                .detail-row { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 8px; 
                    padding: 5px 0;
                    border-bottom: 1px solid #eee;
                }
                .detail-row:last-child {
                    border-bottom: none;
                    font-weight: bold;
                    font-size: 16px;
                }
                .status-message { 
                    background: #e8f5e8; 
                    border: 1px solid #4caf50; 
                    border-radius: 8px; 
                    padding: 15px; 
                    margin: 20px 0; 
                    color: #2e7d32;
                }
                .note-section {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 20px 0;
                    color: #856404;
                }
                .footer { 
                    background: #f8f9fa; 
                    text-align: center; 
                    padding: 20px; 
                    color: #666; 
                    font-size: 14px; 
                    border-top: 1px solid #eee;
                }
                .footer p { 
                    margin: 5px 0; 
                }
                .contact-info {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #ddd;
                }
                @media (max-width: 600px) {
                    .container { 
                        margin: 10px; 
                        border-radius: 8px; 
                    }
                    .content { 
                        padding: 20px 15px; 
                    }
                    .detail-row { 
                        flex-direction: column; 
                        align-items: flex-start; 
                    }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>053 PRINTS</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Order Status Update</p>
                </div>
                
                <div class='content'>
                    <p style='font-size: 16px; margin-bottom: 20px;'>Dear <strong>$customerName</strong>,</p>
                    
                    <p style='font-size: 16px;'>Your order status has been updated!</p>
                    
                    <div style='text-align: center; margin: 25px 0;'>
                        <span class='status-badge'>$statusLabel</span>
                    </div>
                    
                    <div class='order-details'>
                        <h3>Order #" . $order['id'] . "</h3>
                        <div class='detail-row'>
                            <span>Service:</span>
                            <span>$serviceName</span>
                        </div>
                        <div class='detail-row'>
                            <span>Size:</span>
                            <span>" . htmlspecialchars($order['size']) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span>Quantity:</span>
                            <span>" . $order['quantity'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span>Total Amount:</span>
                            <span>₱$totalPrice</span>
                        </div>
                    </div>
                    
                    $noteSection
                    
                    <div class='status-message'>
                        <p style='margin: 0; font-weight: 500;'>$statusMessage</p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p><strong>Thank you for choosing 053 PRINTS!</strong></p>
                    <p>Your trusted partner for quality printing services.</p>
                    
                    <div class='contact-info'>
                        <p>📧 Email: support@053prints.com</p>
                        <p>📞 Phone: +63 XXX XXX XXXX</p>
                        <p>🏪 Visit our store for pickup and inquiries</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Send order approval notification
     * @param array $order Order details
     * @return bool Success status
     */
    public static function sendOrderApprovalNotification($order) {
        if (!$order['customer_email']) {
            return false;
        }
        
        $subject = "Order Approved: Order #" . $order['id'] . " - Payment Required";
        $message = self::buildApprovalTemplate($order);
        
        return self::send($order['customer_email'], $subject, $message);
    }
    
    /**
     * Build approval notification template
     * @param array $order Order details
     * @return string HTML email content
     */
    private static function buildApprovalTemplate($order) {
        $customerName = htmlspecialchars($order['customer_name'] ?: $order['name']);
        $serviceName = htmlspecialchars(ucwords(str_replace('-', ' ', $order['category'])));
        $totalPrice = number_format($order['total_price'], 2);
        $downpayment = number_format($order['total_price'] * 0.7, 2);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Order Approved - 053 PRINTS</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { background: #22c55e; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .approval-badge { display: inline-block; padding: 10px 20px; background: #22c55e; color: white; border-radius: 25px; font-weight: bold; }
                .payment-section { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .footer { background: #f8f9fa; text-align: center; padding: 20px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>053 PRINTS</h1>
                    <p>Order Approved!</p>
                </div>
                <div class='content'>
                    <p>Dear <strong>$customerName</strong>,</p>
                    <p>Great news! Your order has been approved and is ready for payment.</p>
                    
                    <div style='text-align: center; margin: 25px 0;'>
                        <span class='approval-badge'>✅ APPROVED</span>
                    </div>
                    
                    <div class='payment-section'>
                        <h3>Payment Information</h3>
                        <p><strong>Total Amount:</strong> ₱$totalPrice</p>
                        <p><strong>Required Downpayment (70%):</strong> ₱$downpayment</p>
                        <p>Please proceed with payment to start production of your order.</p>
                    </div>
                    
                    <p>Once payment is received, we'll immediately start working on your order!</p>
                </div>
                <div class='footer'>
                    <p>Thank you for choosing 053 PRINTS!</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Send notification to all admin users
     * @param string $notificationTitle Notification title
     * @param string $notificationMessage Notification message
     * @param string $notificationType Notification type (info, warning, error, success)
     * @return bool Success status
     */
    public static function sendAdminNotification($notificationTitle, $notificationMessage, $notificationType = 'info') {
        global $pdo;
        
        try {
            // Ensure database connection is available
            if (!isset($pdo)) {
                require_once __DIR__ . '/../config/database.php';
            }
            // Get all admin email addresses
            $stmt = $pdo->prepare("SELECT email, firstname, lastname FROM users WHERE role = 'admin' AND status = 'active'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($admins) . " admin users for notification");
            
            if (empty($admins)) {
                error_log("No active admin users found for notification");
                return false;
            }
            
            $subject = "New Notification - " . $notificationTitle;
            $success = true;
            
            foreach ($admins as $admin) {
                $adminName = trim($admin['firstname'] . ' ' . $admin['lastname']);
                if (empty($adminName)) {
                    $adminName = 'Admin';
                }
                
                $message = self::buildAdminNotificationTemplate($adminName, $notificationTitle, $notificationMessage, $notificationType);
                
                error_log("Attempting to send email to: " . $admin['email']);
                if (!self::send($admin['email'], $subject, $message)) {
                    $success = false;
                    error_log("Failed to send admin notification to: " . $admin['email']);
                } else {
                    error_log("Successfully sent email to: " . $admin['email']);
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Admin notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build HTML email template for admin notifications
     * @param string $adminName Admin name
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @return string HTML email content
     */
    private static function buildAdminNotificationTemplate($adminName, $title, $message, $type) {
        // Type-specific styling
        $typeColors = [
            'info' => ['bg' => '#e3f2fd', 'border' => '#2196f3', 'icon' => 'ℹ️'],
            'warning' => ['bg' => '#fff3e0', 'border' => '#ff9800', 'icon' => '⚠️'],
            'error' => ['bg' => '#ffebee', 'border' => '#f44336', 'icon' => '❌'],
            'success' => ['bg' => '#e8f5e8', 'border' => '#4caf50', 'icon' => '✅']
        ];
        
        $colors = $typeColors[$type] ?? $typeColors['info'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Admin Notification - 053 PRINTS</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: #1a1a1a; 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                    font-weight: 600; 
                }
                .content { 
                    padding: 30px 20px; 
                }
                .notification-alert { 
                    background: {$colors['bg']}; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 20px 0; 
                }
                .notification-title { 
                    font-size: 18px; 
                    font-weight: bold; 
                    margin-bottom: 10px; 
                    color: #333;
                }
                .notification-message { 
                    font-size: 16px; 
                    color: #555; 
                    margin-bottom: 15px;
                }
                .notification-type { 
                    display: inline-block; 
                    padding: 5px 12px; 
                    background: {$colors['border']}; 
                    color: white; 
                    border-radius: 15px; 
                    font-size: 12px; 
                    font-weight: bold; 
                    text-transform: uppercase;
                }
                .action-section { 
                    background: #f8f9fa; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 20px 0; 
                    text-align: center;
                }
                .admin-btn { 
                    display: inline-block; 
                    padding: 12px 24px; 
                    background: #1a1a1a; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 6px; 
                    font-weight: bold; 
                    margin: 5px;
                }
                .footer { 
                    background: #f8f9fa; 
                    text-align: center; 
                    padding: 20px; 
                    color: #666; 
                    font-size: 14px; 
                    border-top: 1px solid #eee;
                }
                .timestamp { 
                    color: #999; 
                    font-size: 12px; 
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>053 PRINTS</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Admin Notification</p>
                </div>
                
                <div class='content'>
                    <p style='font-size: 16px; margin-bottom: 20px;'>Hello <strong>$adminName</strong>,</p>
                    
                    <p style='font-size: 16px;'>You have received a new notification:</p>
                    
                    <div class='notification-alert'>
                        <div class='notification-title'>" . htmlspecialchars($title) . "</div>
                        <div class='notification-message'>" . htmlspecialchars($message) . "</div>
                        <span class='notification-type'>$type</span>
                    </div>
                    
                    
                    <div class='timestamp'>
                        <p>Notification sent: " . date('Y-m-d H:i:s') . "</p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p><strong>053 PRINTS Admin System</strong></p>
                    <p>This is an automated notification. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>
