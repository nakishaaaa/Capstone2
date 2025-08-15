<?php
/**
 * Session Helper Functions
 * Provides role-specific session management with legacy fallback
 */

function getUserSessionData($role = null) {
    if (!session_id()) {
        session_start();
    }
    
    $userData = [
        'user_id' => null,
        'name' => null,
        'email' => null,
        'role' => null,
        'is_logged_in' => false
    ];
    
    // If specific role requested, check role-specific session first
    if ($role) {
        $rolePrefix = $role . '_';
        if (isset($_SESSION[$rolePrefix . 'user_id']) && isset($_SESSION[$rolePrefix . 'name'])) {
            $userData['user_id'] = $_SESSION[$rolePrefix . 'user_id'];
            $userData['name'] = $_SESSION[$rolePrefix . 'name'];
            $userData['email'] = $_SESSION[$rolePrefix . 'email'] ?? '';
            $userData['role'] = $_SESSION[$rolePrefix . 'role'];
            $userData['is_logged_in'] = true;
            return $userData;
        }
    }
    
    // Fallback to legacy session variables
    if (isset($_SESSION['user_id']) && isset($_SESSION['name'])) {
        $userData['user_id'] = $_SESSION['user_id'];
        $userData['name'] = $_SESSION['name'];
        $userData['email'] = $_SESSION['email'] ?? '';
        $userData['role'] = $_SESSION['role'] ?? 'user';
        $userData['is_logged_in'] = true;
        
        // If role specified, check if it matches
        if ($role && $userData['role'] !== $role) {
            $userData['is_logged_in'] = false;
        }
    }
    
    return $userData;
}

function isUserLoggedIn($role = null) {
    $userData = getUserSessionData($role);
    return $userData['is_logged_in'];
}

function getCurrentUserId($role = null) {
    $userData = getUserSessionData($role);
    return $userData['is_logged_in'] ? $userData['user_id'] : null;
}

function getCurrentUserRole() {
    $userData = getUserSessionData();
    return $userData['is_logged_in'] ? $userData['role'] : null;
}
?>
