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
error_log("Raw Materials API Request: $method " . $_SERVER['REQUEST_URI']);

// Create raw materials table if it doesn't exist
createRawMaterialsTable();

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getRawMaterial($_GET['id']);
            } else {
                getAllRawMaterials();
            }
            break;
        case 'POST':
            createRawMaterial($input);
            break;
        case 'PUT':
            if (isset($_GET['id'])) {
                updateRawMaterial($_GET['id'], $input);
            }
            break;
        case 'DELETE':
            if (isset($_GET['id'])) {
                deleteRawMaterial($_GET['id']);
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

function createRawMaterialsTable() {
    global $pdo;
    try {
        $sql = "CREATE TABLE IF NOT EXISTS raw_materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            supplier VARCHAR(255) DEFAULT NULL,
            unit_type VARCHAR(50) NOT NULL DEFAULT 'pieces',
            unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            current_stock DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            min_stock DECIMAL(10,2) NOT NULL DEFAULT 10.00,
            max_stock DECIMAL(10,2) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT 'images/placeholder.jpg',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_status (status),
            INDEX idx_stock_level (current_stock, min_stock)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
    } catch(PDOException $e) {
        error_log("Error creating raw_materials table: " . $e->getMessage());
    }
}

function getAllRawMaterials() {
    global $pdo;
    try {
        // Check for view type parameter
        $onlyDeleted = isset($_GET['only_deleted']) && $_GET['only_deleted'] === 'true';
        $includeDeleted = isset($_GET['include_deleted']) && $_GET['include_deleted'] === 'true';
        
        // Build WHERE clause based on view type
        if ($onlyDeleted) {
            $whereClause = "WHERE deleted_at IS NOT NULL";
        } elseif ($includeDeleted) {
            $whereClause = "WHERE status = 'active'";
        } else {
            $whereClause = "WHERE deleted_at IS NULL AND status = 'active'";
        }
        
        $stmt = $pdo->query("SELECT *, 
            CASE 
                WHEN current_stock <= 0 THEN 'Out of Stock'
                WHEN current_stock <= min_stock THEN 'Low Stock'
                WHEN max_stock IS NOT NULL AND current_stock >= max_stock THEN 'Overstocked'
                ELSE 'In Stock'
            END as stock_status,
            ROUND((current_stock / NULLIF(min_stock, 0)) * 100, 1) as stock_percentage
            FROM raw_materials $whereClause ORDER BY current_stock ASC, name");
        $materials = $stmt->fetchAll();
        
        // Add image URLs if missing and ensure stock is decimal
        foreach ($materials as &$material) {
            if (empty($material['image_url'])) {
                $material['image_url'] = 'images/placeholder.jpg';
            }
            // Ensure stock values are treated as decimals
            $material['current_stock'] = floatval($material['current_stock']);
            $material['min_stock'] = floatval($material['min_stock']);
            $material['max_stock'] = $material['max_stock'] ? floatval($material['max_stock']) : null;
            $material['unit_cost'] = floatval($material['unit_cost']);
        }
        
        echo json_encode(['success' => true, 'data' => $materials]);
    } catch(PDOException $e) {
        error_log("Database error in getAllRawMaterials: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getRawMaterial($id) {
    global $pdo;
    try {
        // Allow retrieval of deleted items for admin restore functionality
        $includeDeleted = isset($_GET['include_deleted']) && $_GET['include_deleted'] === 'true';
        $whereClause = $includeDeleted ? "WHERE id = ?" : "WHERE id = ? AND deleted_at IS NULL";
        
        $stmt = $pdo->prepare("SELECT *, 
            CASE 
                WHEN current_stock <= 0 THEN 'Out of Stock'
                WHEN current_stock <= min_stock THEN 'Low Stock'
                WHEN max_stock IS NOT NULL AND current_stock >= max_stock THEN 'Overstocked'
                ELSE 'In Stock'
            END as stock_status,
            ROUND((current_stock / NULLIF(min_stock, 0)) * 100, 1) as stock_percentage
            FROM raw_materials $whereClause");
        $stmt->execute([$id]);
        $material = $stmt->fetch();
        
        if ($material) {
            if (empty($material['image_url'])) {
                $material['image_url'] = 'images/placeholder.jpg';
            }
            // Ensure stock values are treated as decimals
            $material['current_stock'] = floatval($material['current_stock']);
            $material['min_stock'] = floatval($material['min_stock']);
            $material['max_stock'] = $material['max_stock'] ? floatval($material['max_stock']) : null;
            $material['unit_cost'] = floatval($material['unit_cost']);
            
            echo json_encode(['success' => true, 'data' => $material]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Raw material not found']);
        }
    } catch(PDOException $e) {
        error_log("Database error in getRawMaterial: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createRawMaterial($data) {
    global $pdo;
    try {
        if (!isset($data['name']) || !isset($data['category']) || !isset($data['unit_type']) || !isset($data['unit_cost'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO raw_materials (name, category, supplier, unit_type, unit_cost, current_stock, min_stock, max_stock, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['category'],
            $data['supplier'] ?? '',
            $data['unit_type'],
            floatval($data['unit_cost']),
            floatval($data['current_stock'] ?? 0),
            floatval($data['min_stock'] ?? 10),
            isset($data['max_stock']) && $data['max_stock'] !== '' ? floatval($data['max_stock']) : null,
            $data['description'] ?? '',
            $data['image_url'] ?? 'images/placeholder.jpg'
        ]);
        
        $newId = $pdo->lastInsertId();
        
        // Check if we need to create a low stock notification
        $currentStock = floatval($data['current_stock'] ?? 0);
        $minStock = floatval($data['min_stock'] ?? 10);
        
        if ($currentStock <= $minStock) {
            createStockNotification($newId, $data['name'], $currentStock, $minStock);
        }
        
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Raw material created successfully']);
    } catch(PDOException $e) {
        error_log("Database error in createRawMaterial: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateRawMaterial($id, $data) {
    global $pdo;
    try {
        // Check if raw material exists and is not soft deleted
        $stmt = $pdo->prepare("SELECT id FROM raw_materials WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Raw material not found or has been deleted']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE raw_materials SET name = ?, category = ?, supplier = ?, unit_type = ?, unit_cost = ?, current_stock = ?, min_stock = ?, max_stock = ?, description = ?, image_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['category'],
            $data['supplier'] ?? '',
            $data['unit_type'],
            floatval($data['unit_cost']),
            floatval($data['current_stock']),
            floatval($data['min_stock'] ?? 10),
            isset($data['max_stock']) && $data['max_stock'] !== '' ? floatval($data['max_stock']) : null,
            $data['description'] ?? '',
            $data['image_url'] ?? 'images/placeholder.jpg',
            $id
        ]);

        // Check for stock level notifications
        try {
            $checkStmt = $pdo->prepare("SELECT name, current_stock, min_stock FROM raw_materials WHERE id = ? AND status = 'active'");
            $checkStmt->execute([$id]);
            $material = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($material && floatval($material['current_stock']) <= floatval($material['min_stock'])) {
                createStockNotification($id, $material['name'], floatval($material['current_stock']), floatval($material['min_stock']));
            }
        } catch (Exception $e) {
            // Log but do not fail the main update due to notification issues
            error_log("Raw materials update: failed to create low-stock notification for material $id - " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Raw material updated successfully']);
    } catch(PDOException $e) {
        error_log("Database error in updateRawMaterial: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteRawMaterial($id) {
    global $pdo;
    try {
        // Check for action parameter
        $action = $_GET['action'] ?? 'delete';
        
        switch ($action) {
            case 'restore':
                return restoreRawMaterial($id);
            case 'permanent':
                return permanentDeleteRawMaterial($id);
            default:
                return softDeleteRawMaterial($id);
        }
    } catch(PDOException $e) {
        error_log("Database error in deleteRawMaterial: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function softDeleteRawMaterial($id) {
    global $pdo;
    try {
        // Check if material exists and is not already deleted
        $stmt = $pdo->prepare("SELECT name FROM raw_materials WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $material = $stmt->fetch();
        
        if (!$material) {
            echo json_encode(['success' => false, 'error' => 'Raw material not found or already deleted']);
            return;
        }
        
        // Soft delete by setting deleted_at timestamp
        $stmt = $pdo->prepare("UPDATE raw_materials SET deleted_at = CURRENT_TIMESTAMP, status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Raw material moved to trash successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete raw material']);
        }
    } catch(PDOException $e) {
        error_log("Database error in softDeleteRawMaterial: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function restoreRawMaterial($id) {
    global $pdo;
    try {
        // Check if material exists and is deleted
        $stmt = $pdo->prepare("SELECT name FROM raw_materials WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        $material = $stmt->fetch();
        
        if (!$material) {
            echo json_encode(['success' => false, 'error' => 'Raw material not found in trash']);
            return;
        }
        
        // Restore by clearing deleted_at timestamp and setting status to active
        $stmt = $pdo->prepare("UPDATE raw_materials SET deleted_at = NULL, status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Raw material restored successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to restore raw material']);
        }
    } catch(PDOException $e) {
        error_log("Database error in restoreRawMaterial: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function permanentDeleteRawMaterial($id) {
    global $pdo;
    try {
        // Check if material exists and is soft deleted
        $stmt = $pdo->prepare("SELECT name, image_url FROM raw_materials WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        $material = $stmt->fetch();
        
        if (!$material) {
            echo json_encode(['success' => false, 'error' => 'Raw material not found in trash']);
            return;
        }
        
        // Delete associated image file if it exists and is not the placeholder
        if (!empty($material['image_url']) && $material['image_url'] !== 'images/placeholder.jpg') {
            $imagePath = '../' . $material['image_url'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // Permanently delete from database
        $stmt = $pdo->prepare("DELETE FROM raw_materials WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Raw material permanently deleted']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to permanently delete raw material']);
        }
    } catch(PDOException $e) {
        error_log("Database error in permanentDeleteRawMaterial: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createStockNotification($materialId, $materialName, $currentStock, $minStock) {
    global $pdo;
    try {
        $notificationTitle = "Low Raw Material Stock: " . $materialName;
        
        // Avoid duplicate unread notifications for the same material
        $existsStmt = $pdo->prepare("SELECT id FROM notifications WHERE title = ? AND is_read = FALSE");
        $existsStmt->execute([$notificationTitle]);
        
        if (!$existsStmt->fetch()) {
            $message = $currentStock == 0 ?
                $materialName . " is out of stock" :
                $materialName . " stock is low (" . $currentStock . " remaining)";
            
            $notificationType = $currentStock == 0 ? 'error' : 'warning';
            
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
    } catch (Exception $e) {
        error_log("Failed to create stock notification for raw material $materialId: " . $e->getMessage());
    }
}
?>
