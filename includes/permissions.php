<?php
/**
 * Role-Based Access Control (RBAC) System
 * Defines permissions for different user roles
 */

class RolePermissions {
    
    // Define role permissions
    private static $permissions = [
        'admin' => [
            'dashboard_access' => true,
            'user_management' => true,
            'product_management' => true,
            'inventory_management' => true,
            'pos_operations' => true,
            'customer_support' => true,
            'reports_analytics' => true,
            'system_settings' => true,
            'view_all_data' => true,
            'delete_users' => true,
            'modify_roles' => true
        ],
        'cashier' => [
            'dashboard_access' => true,
            'user_management' => false,
            'product_management' => false,
            'inventory_management' => false, // View only
            'inventory_view' => true,
            'pos_operations' => true,
            'customer_support' => false,
            'reports_analytics' => false,
            'system_settings' => false,
            'view_all_data' => false,
            'delete_users' => false,
            'modify_roles' => false,
            'process_transactions' => true,
            'view_requests' => true
        ]
    ];
    
    /**
     * Check if a user has a specific permission
     */
    public static function hasPermission($role, $permission) {
        if (!isset(self::$permissions[$role])) {
            return false;
        }
        
        return self::$permissions[$role][$permission] ?? false;
    }
    
    /**
     * Get all permissions for a role
     */
    public static function getRolePermissions($role) {
        return self::$permissions[$role] ?? [];
    }
    
    /**
     * Check if user can access a specific section
     */
    public static function canAccessSection($role, $section) {
        $section_permissions = [
            'dashboard' => 'dashboard_access',
            'inventory' => 'inventory_view',
            'pos' => 'pos_operations',
            'sales-management' => 'product_management',
            'notifications' => 'dashboard_access',
            'requests' => 'view_requests',
            'customersupport' => 'customer_support',
            'user-management' => 'user_management'
        ];
        
        $required_permission = $section_permissions[$section] ?? null;
        
        if (!$required_permission) {
            return false;
        }
        
        return self::hasPermission($role, $required_permission);
    }
    
    /**
     * Require specific permission or exit with error
     */
    public static function requirePermission($role, $permission) {
        if (!self::hasPermission($role, $permission)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Insufficient permissions.']);
            exit();
        }
    }
    
    /**
     * Require admin role or exit with error
     */
    public static function requireAdmin($role) {
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Admin privileges required.']);
            exit();
        }
    }
    
    /**
     * Get user's current role from session
     */
    public static function getCurrentUserRole() {
        if (isset($_SESSION['admin_role'])) {
            return $_SESSION['admin_role'];
        } elseif (isset($_SESSION['role'])) {
            return $_SESSION['role'];
        }
        return null;
    }
    
    /**
     * Check if current user is logged in with valid role
     */
    public static function isValidUser() {
        $role = self::getCurrentUserRole();
        return $role && in_array($role, ['admin', 'cashier']);
    }
}

/**
 * Middleware function to check access to admin sections
 */
function checkAdminAccess($required_permission = null) {
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
        header("Location: index.php");
        exit();
    }
    
    if ($required_permission && !RolePermissions::hasPermission($_SESSION['admin_role'], $required_permission)) {
        header("Location: admin_page.php");
        exit();
    }
}

/**
 * Middleware function to check access to cashier sections
 */
function checkCashierAccess($required_permission = null) {
    $role = RolePermissions::getCurrentUserRole();
    
    if (!$role || !in_array($role, ['admin', 'cashier'])) {
        header("Location: index.php");
        exit();
    }
    
    if ($required_permission && !RolePermissions::hasPermission($role, $required_permission)) {
        header("Location: index.php");
        exit();
    }
}
?>
