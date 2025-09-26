<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Log the request for debugging
error_log("API Request: $method " . $_SERVER['REQUEST_URI']);

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getProduct($_GET['id']);
            } else {
                getAllProducts();
            }
            break;
        case 'POST':
            createProduct($input);
            break;
        case 'PUT':
            if (isset($_GET['id'])) {
                updateProduct($_GET['id'], $input);
            }
            break;
        case 'DELETE':
            if (isset($_GET['id'])) {
                deleteProduct($_GET['id']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getAllProducts() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT *, 
            CASE 
                WHEN stock <= 0 THEN 'Out of Stock'
                WHEN stock <= min_stock THEN 'Low Stock'
                ELSE 'In Stock'
            END as stock_status
            FROM inventory WHERE status = 'active' ORDER BY stock DESC, name");
        $products = $stmt->fetchAll();
        
        // Add image URLs if missing and ensure stock is integer
        foreach ($products as &$product) {
            if (empty($product['image_url'])) {
                $product['image_url'] = 'images/placeholder.jpg';
            }
            // Ensure stock is treated as integer
            $product['stock'] = intval($product['stock']);
            $product['min_stock'] = intval($product['min_stock']);
        }
        
        echo json_encode(['success' => true, 'data' => $products]);
    } catch(PDOException $e) {
        error_log("Database error in getAllProducts: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getProduct($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT *, 
            CASE 
                WHEN stock = 0 THEN 'Out of Stock'
                WHEN stock <= min_stock THEN 'Low Stock'
                ELSE 'In Stock'
            END as stock_status
            FROM inventory WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            if (empty($product['image_url'])) {
                $product['image_url'] = 'images/placeholder.jpg';
            }
            echo json_encode(['success' => true, 'data' => $product]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
        }
    } catch(PDOException $e) {
        error_log("Database error in getProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createProduct($data) {
    global $pdo;
    try {
        if (!isset($data['name']) || !isset($data['category']) || !isset($data['price']) || !isset($data['stock'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO inventory (name, category, price, stock, min_stock, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['category'],
            floatval($data['price']),
            intval($data['stock']),
            intval($data['min_stock'] ?? 10),
            $data['description'] ?? '',
            $data['image_url'] ?? 'images/placeholder.jpg'
        ]);
        
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Product created successfully']);
    } catch(PDOException $e) {
        error_log("Database error in createProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateProduct($id, $data) {
    global $pdo;
    try {
        // Check if product exists
        $stmt = $pdo->prepare("SELECT id FROM inventory WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE inventory SET name = ?, category = ?, price = ?, stock = ?, min_stock = ?, description = ?, image_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['category'],
            floatval($data['price']),
            intval($data['stock']),
            intval($data['min_stock'] ?? 10),
            $data['description'] ?? '',
            $data['image_url'] ?? 'images/placeholder.jpg',
            $id
        ]);

        try {
            $checkStmt = $pdo->prepare("SELECT name, stock, min_stock FROM inventory WHERE id = ? AND status = 'active'");
            $checkStmt->execute([$id]);
            $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product && intval($product['stock']) <= intval($product['min_stock'])) {
                $notificationTitle = "Low Stock Alert: " . $product['name'];
                // Avoid duplicate unread notifications for the same product
                $existsStmt = $pdo->prepare("SELECT id FROM notifications WHERE title = ? AND is_read = FALSE");
                $existsStmt->execute([$notificationTitle]);
                
                if (!$existsStmt->fetch()) {
                    $message = intval($product['stock']) == 0 ?
                        $product['name'] . " is out of stock" :
                        $product['name'] . " stock is low (" . intval($product['stock']) . " remaining)";
                    
                    $notificationType = intval($product['stock']) == 0 ? 'error' : 'warning';
                    
                    $insStmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)");
                    $insStmt->execute([
                        $notificationTitle,
                        $message,
                        $notificationType
                    ]);
                    
                    // Send email notification to admins for low stock (non-blocking)
                    try {
                        require_once '../includes/email_notifications.php';
                        EmailNotifications::sendAdminNotification($notificationTitle, $message, $notificationType);
                    } catch (Exception $emailError) {
                        error_log("Email notification error: " . $emailError->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            // Log but do not fail the main update due to notification issues
            error_log("Inventory update: failed to create low-stock notification for product $id - " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } catch(PDOException $e) {
        error_log("Database error in updateProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteProduct($id) {
    global $pdo;
    try {
        // Soft delete 
        $stmt = $pdo->prepare("UPDATE inventory SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
        }
    } catch(PDOException $e) {
        error_log("Database error in deleteProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
