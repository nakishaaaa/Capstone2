<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session_helper.php';

// Check if user is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? $_GET['type'] ?? '';

try {
    switch ($action) {
        case 'get_system_performance':
        case 'performance':
            $performanceData = getSystemPerformance($conn);
            echo json_encode(formatPerformanceResponse($performanceData));
            break;
            
        case 'get_user_analytics':
        case 'users':
            $userData = getUserAnalytics($conn);
            echo json_encode(formatUserResponse($userData));
            break;
            
        case 'get_database_analytics':
        case 'database':
            $databaseData = getDatabaseAnalytics($conn);
            echo json_encode(formatDatabaseResponse($databaseData));
            break;
            
        case 'get_security_analytics':
        case 'security':
            $securityData = getSecurityAnalytics($conn);
            echo json_encode(formatSecurityResponse($securityData));
            break;
            
        case 'get_error_analytics':
            $errorData = getErrorAnalytics($conn);
            echo json_encode(['success' => true, 'data' => $errorData]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getDatabaseAnalytics($conn) {
    $data = [];
    
    // Get time range from request parameter
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $interval = '30 DAY';
    
    switch($timeRange) {
        case '7d':
            $interval = '7 DAY';
            break;
        case '90d':
            $interval = '90 DAY';
            break;
        case '1y':
            $interval = '365 DAY';
            break;
        default:
            $interval = '30 DAY';
    }
    
    // Database table sizes
    $stmt = $conn->prepare("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
            table_rows
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY size_mb DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['table_sizes'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Database growth over time (using audit logs as proxy)
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as activity_count
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['daily_activity'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Total database size
    $stmt = $conn->prepare("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_size_mb,
            COUNT(*) as total_tables
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['database_summary'] = $result->fetch_assoc();
    
    return $data;
}

function getSecurityAnalytics($conn) {
    $data = [];
    
    // Get time range from request parameter
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $interval = '30 DAY';
    
    switch($timeRange) {
        case '7d':
            $interval = '7 DAY';
            break;
        case '90d':
            $interval = '90 DAY';
            break;
        case '1y':
            $interval = '365 DAY';
            break;
        default:
            $interval = '30 DAY';
    }
    
    // Login attempts over time
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, 
               COUNT(*) as login_attempts,
               COUNT(CASE WHEN action LIKE '%failed%' OR action LIKE '%error%' THEN 1 END) as failed_attempts
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
          AND (action LIKE '%login%' OR action LIKE '%authentication%')
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['daily_login_attempts'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Security events by type
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN action LIKE '%login%' THEN 'Login Events'
                WHEN action LIKE '%logout%' THEN 'Logout Events'
                WHEN action LIKE '%password%' THEN 'Password Events'
                WHEN action LIKE '%failed%' OR action LIKE '%error%' THEN 'Failed Attempts'
                ELSE 'Other Security Events'
            END as event_type,
            COUNT(*) as count
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
        GROUP BY event_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['security_events'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // User activity by role
    $stmt = $conn->prepare("
        SELECT 
            u.role,
            COUNT(al.id) as activity_count,
            COUNT(DISTINCT al.user_id) as active_users
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL $interval)
        GROUP BY u.role
        ORDER BY activity_count DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['activity_by_role'] = $result->fetch_all(MYSQLI_ASSOC);
    
    return $data;
}


function getUserAnalytics($conn) {
    $data = [];
    
    // Get time range from request parameter
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $interval = '30 DAY';
    
    switch($timeRange) {
        case '7d':
            $interval = '7 DAY';
            break;
        case '90d':
            $interval = '90 DAY';
            break;
        case '1y':
            $interval = '365 DAY';
            break;
        default:
            $interval = '30 DAY';
    }
    
    // User registrations over time
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as registrations
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['daily_registrations'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Users by role
    $stmt = $conn->prepare("
        SELECT role, COUNT(*) as count
        FROM users
        GROUP BY role
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['users_by_role'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Active users (logged in within last 30 days)
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as active_today,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_week,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_month
        FROM users
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['activity_summary'] = $result->fetch_assoc();
    
    return $data;
}

function getSystemPerformance($conn) {
    $data = [];
    
    // Get time range from request parameter
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $interval = '30 DAY';
    
    switch($timeRange) {
        case '7d':
            $interval = '7 DAY';
            break;
        case '90d':
            $interval = '90 DAY';
            break;
        case '1y':
            $interval = '365 DAY';
            break;
        default:
            $interval = '30 DAY';
    }
    
    // Database size
    $stmt = $conn->prepare("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY size_mb DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['table_sizes'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // System activity (from audit logs)
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as activity_count
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['daily_activity'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Resource usage simulation
    $data['resource_usage'] = [
        'cpu_usage' => rand(20, 80),
        'memory_usage' => rand(30, 70),
        'disk_usage' => rand(40, 85),
        'network_usage' => rand(10, 60)
    ];
    
    return $data;
}

function getErrorAnalytics($conn) {
    $data = [];
    
    // Error trends over time
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, 
               error_type,
               COUNT(*) as error_count
        FROM console_errors
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at), error_type
        ORDER BY date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['error_trends'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Error types distribution
    $stmt = $conn->prepare("
        SELECT error_type, COUNT(*) as count
        FROM console_errors
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY error_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['error_types'] = $result->fetch_all(MYSQLI_ASSOC);
    
    return $data;
}

// Format response functions to match frontend expectations
function formatDatabaseResponse($data) {
    $labels = [];
    $chartData = [];
    $totalSize = 0;
    $totalTables = 0;
    
    // Get the time range to determine how many days to show
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $daysToShow = 30;
    
    switch($timeRange) {
        case '7d':
            $daysToShow = 7;
            break;
        case '90d':
            $daysToShow = 90;
            break;
        case '1y':
            $daysToShow = 365;
            break;
        default:
            $daysToShow = 30;
    }
    
    // Create a complete date range for database activity
    $dateMap = [];
    for ($i = $daysToShow - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dateMap[$date] = 0;
    }
    
    // Fill in actual activity data
    if (isset($data['daily_activity']) && !empty($data['daily_activity'])) {
        foreach ($data['daily_activity'] as $activity) {
            $activityDate = date('Y-m-d', strtotime($activity['date']));
            if (isset($dateMap[$activityDate])) {
                $dateMap[$activityDate] = intval($activity['activity_count'] ?? 0);
            }
        }
    }
    
    // Convert to arrays for chart
    foreach ($dateMap as $date => $count) {
        $labels[] = date('M j', strtotime($date));
        $chartData[] = $count;
    }
    
    // Get summary data
    if (isset($data['database_summary'])) {
        $totalSize = floatval($data['database_summary']['total_size_mb'] ?? 0);
        $totalTables = intval($data['database_summary']['total_tables'] ?? 0);
    }
    
    return [
        'success' => true,
        'labels' => $labels,
        'data' => $chartData,
        'total_size_mb' => $totalSize,
        'total_tables' => $totalTables,
        'table_sizes' => $data['table_sizes'] ?? []
    ];
}

function formatSecurityResponse($data) {
    $labels = [];
    $loginData = [];
    $failedData = [];
    
    // Get the time range to determine how many days to show
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $daysToShow = 30;
    
    switch($timeRange) {
        case '7d':
            $daysToShow = 7;
            break;
        case '90d':
            $daysToShow = 90;
            break;
        case '1y':
            $daysToShow = 365;
            break;
        default:
            $daysToShow = 30;
    }
    
    // Create a complete date range
    $dateMap = [];
    $failedMap = [];
    for ($i = $daysToShow - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dateMap[$date] = 0;
        $failedMap[$date] = 0;
    }
    
    // Fill in actual login data
    if (isset($data['daily_login_attempts']) && !empty($data['daily_login_attempts'])) {
        foreach ($data['daily_login_attempts'] as $attempt) {
            $attemptDate = date('Y-m-d', strtotime($attempt['date']));
            if (isset($dateMap[$attemptDate])) {
                $dateMap[$attemptDate] = intval($attempt['login_attempts'] ?? 0);
                $failedMap[$attemptDate] = intval($attempt['failed_attempts'] ?? 0);
            }
        }
    }
    
    // Convert to arrays for chart
    foreach ($dateMap as $date => $count) {
        $labels[] = date('M j', strtotime($date));
        $loginData[] = $count;
        $failedData[] = $failedMap[$date];
    }
    
    return [
        'success' => true,
        'labels' => $labels,
        'login_data' => $loginData,
        'failed_data' => $failedData,
        'security_events' => $data['security_events'] ?? [],
        'activity_by_role' => $data['activity_by_role'] ?? []
    ];
}

function formatUserResponse($data) {
    $labels = [];
    $chartData = [];
    $activeUsers = 0;
    $usersChange = 0;
    
    // Get the time range to determine how many days to show
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $daysToShow = 30;
    
    switch($timeRange) {
        case '7d':
            $daysToShow = 7;
            break;
        case '90d':
            $daysToShow = 90;
            break;
        case '1y':
            $daysToShow = 365;
            break;
        default:
            $daysToShow = 30;
    }
    
    // Create a complete date range for user registrations
    $dateMap = [];
    for ($i = $daysToShow - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dateMap[$date] = 0;
    }
    
    // Fill in actual registration data
    if (isset($data['daily_registrations']) && !empty($data['daily_registrations'])) {
        foreach ($data['daily_registrations'] as $reg) {
            $regDate = date('Y-m-d', strtotime($reg['date']));
            if (isset($dateMap[$regDate])) {
                $dateMap[$regDate] = intval($reg['registrations'] ?? 0);
            }
        }
    }
    
    // Convert to arrays for chart
    foreach ($dateMap as $date => $count) {
        $labels[] = date('M j', strtotime($date));
        $chartData[] = $count;
    }
    
    if (isset($data['activity_summary'])) {
        $activeUsers = intval($data['activity_summary']['active_month'] ?? 0);
    }
    
    // Calculate user growth percentage
    if (isset($data['users_by_role'])) {
        $totalUsers = 0;
        foreach ($data['users_by_role'] as $role) {
            $totalUsers += intval($role['count'] ?? 0);
        }
        
        // Simple growth calculation (could be enhanced with historical data)
        if ($totalUsers > 0 && count($chartData) > 0) {
            $recentGrowth = array_sum(array_slice($chartData, -7)); // Last 7 days
            $usersChange = ($recentGrowth / max(1, $totalUsers)) * 100;
        }
    }
    
    return [
        'success' => true,
        'labels' => $labels,
        'data' => $chartData,
        'active_users' => $activeUsers,
        'users_change' => $usersChange
    ];
}

function formatPerformanceResponse($data) {
    $labels = [];
    $chartData = [];
    
    // Get the time range to determine how many days to show
    $timeRange = $_GET['time_range'] ?? $_GET['range'] ?? '30d';
    $daysToShow = 30;
    
    switch($timeRange) {
        case '7d':
            $daysToShow = 7;
            break;
        case '90d':
            $daysToShow = 90;
            break;
        case '1y':
            $daysToShow = 365;
            break;
        default:
            $daysToShow = 30;
    }
    
    // Create a complete date range for performance data
    $dateMap = [];
    for ($i = $daysToShow - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dateMap[$date] = 0;
    }
    
    // Fill in actual activity data
    if (isset($data['daily_activity']) && !empty($data['daily_activity'])) {
        foreach ($data['daily_activity'] as $activity) {
            $activityDate = date('Y-m-d', strtotime($activity['date']));
            if (isset($dateMap[$activityDate])) {
                $dateMap[$activityDate] = intval($activity['activity_count'] ?? 0);
            }
        }
    }
    
    // Convert to arrays for chart
    foreach ($dateMap as $date => $count) {
        $labels[] = date('M j', strtotime($date));
        // Simulate response time based on activity count
        $responseTime = max(50, min(500, $count * 2));
        $chartData[] = $responseTime;
    }
    
    return [
        'success' => true,
        'labels' => $labels,
        'data' => $chartData
    ];
}

// Removed sample data insertion functions - using real database data
?>
