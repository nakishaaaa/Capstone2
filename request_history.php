<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_page.php#requests');
    exit();
}

require_once 'config/database.php';

// Get request history
try {
    $sql = "SELECT * FROM request_history ORDER BY cleared_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $history = [];
    $error_message = "Error loading request history: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request History - Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
        }
        
        .back-button:hover {
            background: #0056b3;
            color: white;
            text-decoration: none;
        }
        
        .history-table {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .history-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #dee2e6;
        }
        
        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .history-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="history-header">
            <div>
                <h1>Request History</h1>
                <p style="color: #666; margin: 0;">View all cleared customer requests</p>
            </div>
            <a href="admin_page.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="history-table">
            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No Request History</h3>
                    <p>No requests have been cleared yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Category</th>
                            <th>Size/Type</th>
                            <th>Quantity</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Admin Response</th>
                            <th>Submitted</th>
                            <th>Cleared</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= htmlspecialchars($row['size']) ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td><?= htmlspecialchars($row['contact_number']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $row['status'] ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['admin_response']) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($row['created_at'])) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($row['cleared_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
