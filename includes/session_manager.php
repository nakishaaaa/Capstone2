<?php
// Session Manager for SSE Real-time Updates
require_once __DIR__ . '/../config/database.php';

class SessionManager {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->createTableIfNotExists();
    }
    
    private function createTableIfNotExists() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS sse_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    session_id VARCHAR(255) NOT NULL UNIQUE,
                    user_id INT NOT NULL,
                    user_role VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL,
                    INDEX idx_session_id (session_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_expires (expires_at)
                )
            ");
        } catch (PDOException $e) {
            error_log("SessionManager: Error creating table: " . $e->getMessage());
        }
    }
    
    public function storeSession($sessionId, $userId, $userRole, $expiresInHours = 24) {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInHours * 3600));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO sse_sessions (session_id, user_id, user_role, expires_at) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                user_id = VALUES(user_id),
                user_role = VALUES(user_role),
                expires_at = VALUES(expires_at),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$sessionId, $userId, $userRole, $expiresAt]);
            error_log("SessionManager: Stored session - ID: $sessionId, User: $userId, Role: $userRole");
            return true;
            
        } catch (PDOException $e) {
            error_log("SessionManager: Error storing session: " . $e->getMessage());
            return false;
        }
    }
    
    public function getSession($sessionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id, user_role, expires_at 
                FROM sse_sessions 
                WHERE session_id = ? AND expires_at > NOW()
            ");
            $stmt->execute([$sessionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("SessionManager: Error getting session: " . $e->getMessage());
            return false;
        }
    }
    
    public function removeSession($sessionId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sse_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            error_log("SessionManager: Removed session: $sessionId");
            return true;
            
        } catch (PDOException $e) {
            error_log("SessionManager: Error removing session: " . $e->getMessage());
            return false;
        }
    }
    
    public function cleanExpiredSessions() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sse_sessions WHERE expires_at < NOW()");
            $stmt->execute();
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("SessionManager: Cleaned $count expired sessions");
            }
            return $count;
            
        } catch (PDOException $e) {
            error_log("SessionManager: Error cleaning expired sessions: " . $e->getMessage());
            return false;
        }
    }
}

// Helper functions for easy use
function storeUserSession($userId, $userRole, $expiresInHours = 24) {
    if (!session_id()) {
        session_start();
    }
    
    $sessionManager = new SessionManager();
    return $sessionManager->storeSession(session_id(), $userId, $userRole, $expiresInHours);
}

function removeUserSession() {
    if (!session_id()) {
        session_start();
    }
    
    $sessionManager = new SessionManager();
    return $sessionManager->removeSession(session_id());
}

function cleanExpiredSessions() {
    $sessionManager = new SessionManager();
    return $sessionManager->cleanExpiredSessions();
}
?>
