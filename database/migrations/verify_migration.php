<?php
/**
 * Migration Verification Script
 * 
 * This script verifies that the migration was successful by checking:
 * - Table existence
 * - Record counts
 * - Data integrity
 * - Foreign key relationships
 */

require_once __DIR__ . '/../../config/database.php';

class MigrationVerifier {
    private $pdo;
    private $results = [];
    private $errors = [];
    private $warnings = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Run all verification checks
     */
    public function verify() {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║         MIGRATION VERIFICATION REPORT                     ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $this->checkTablesExist();
        $this->checkRecordCounts();
        $this->checkDataIntegrity();
        $this->checkForeignKeys();
        $this->checkIndexes();
        $this->compareWithOriginal();
        
        $this->displayResults();
    }

    /**
     * Check if all new tables exist
     */
    private function checkTablesExist() {
        echo "1. Checking table existence...\n";
        
        $tables = [
            'customer_requests',
            'request_details',
            'request_attachments',
            'approved_orders'
        ];
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->query("SELECT 1 FROM $table LIMIT 1");
                $this->results[] = "✓ Table '$table' exists";
            } catch (PDOException $e) {
                $this->errors[] = "✗ Table '$table' does not exist";
            }
        }
        echo "\n";
    }

    /**
     * Check record counts in all tables
     */
    private function checkRecordCounts() {
        echo "2. Checking record counts...\n";
        
        $tables = [
            'customer_requests' => 'Core requests',
            'request_details' => 'Request details',
            'request_attachments' => 'Attachments',
            'approved_orders' => 'Approved orders'
        ];
        
        foreach ($tables as $table => $description) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                $this->results[] = "✓ $description: $count records";
            } catch (PDOException $e) {
                $this->errors[] = "✗ Could not count records in '$table'";
            }
        }
        echo "\n";
    }

    /**
     * Check data integrity
     */
    private function checkDataIntegrity() {
        echo "3. Checking data integrity...\n";
        
        // Check 1: All requests should have details
        try {
            $sql = "SELECT COUNT(*) FROM customer_requests cr
                    LEFT JOIN request_details rd ON cr.id = rd.request_id
                    WHERE rd.id IS NULL";
            $stmt = $this->pdo->query($sql);
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $this->results[] = "✓ All requests have details";
            } else {
                $this->warnings[] = "⚠ $count requests missing details";
            }
        } catch (PDOException $e) {
            $this->errors[] = "✗ Could not verify request details";
        }
        
        // Check 2: No orphaned details
        try {
            $sql = "SELECT COUNT(*) FROM request_details rd
                    LEFT JOIN customer_requests cr ON rd.request_id = cr.id
                    WHERE cr.id IS NULL";
            $stmt = $this->pdo->query($sql);
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $this->results[] = "✓ No orphaned request details";
            } else {
                $this->errors[] = "✗ $count orphaned request details found";
            }
        } catch (PDOException $e) {
            $this->errors[] = "✗ Could not check for orphaned details";
        }
        
        // Check 3: No orphaned attachments
        try {
            $sql = "SELECT COUNT(*) FROM request_attachments ra
                    LEFT JOIN customer_requests cr ON ra.request_id = cr.id
                    WHERE cr.id IS NULL";
            $stmt = $this->pdo->query($sql);
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $this->results[] = "✓ No orphaned attachments";
            } else {
                $this->errors[] = "✗ $count orphaned attachments found";
            }
        } catch (PDOException $e) {
            $this->errors[] = "✗ Could not check for orphaned attachments";
        }
        
        // Check 4: No orphaned orders
        try {
            $sql = "SELECT COUNT(*) FROM approved_orders ao
                    LEFT JOIN customer_requests cr ON ao.request_id = cr.id
                    WHERE cr.id IS NULL";
            $stmt = $this->pdo->query($sql);
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $this->results[] = "✓ No orphaned orders";
            } else {
                $this->errors[] = "✗ $count orphaned orders found";
            }
        } catch (PDOException $e) {
            $this->errors[] = "✗ Could not check for orphaned orders";
        }
        
        echo "\n";
    }

    /**
     * Check foreign key relationships
     */
    private function checkForeignKeys() {
        echo "4. Checking foreign key relationships...\n";
        
        try {
            // Check request_details foreign key
            $sql = "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                    AND TABLE_NAME = 'request_details'
                    AND TABLE_SCHEMA = DATABASE()";
            $stmt = $this->pdo->query($sql);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $this->results[] = "✓ request_details has foreign key constraints";
            } else {
                $this->warnings[] = "⚠ request_details missing foreign key constraints";
            }
            
            // Check request_attachments foreign key
            $sql = "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                    AND TABLE_NAME = 'request_attachments'
                    AND TABLE_SCHEMA = DATABASE()";
            $stmt = $this->pdo->query($sql);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $this->results[] = "✓ request_attachments has foreign key constraints";
            } else {
                $this->warnings[] = "⚠ request_attachments missing foreign key constraints";
            }
            
            // Check approved_orders foreign key
            $sql = "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                    AND TABLE_NAME = 'approved_orders'
                    AND TABLE_SCHEMA = DATABASE()";
            $stmt = $this->pdo->query($sql);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $this->results[] = "✓ approved_orders has foreign key constraints";
            } else {
                $this->warnings[] = "⚠ approved_orders missing foreign key constraints";
            }
            
        } catch (PDOException $e) {
            $this->warnings[] = "⚠ Could not verify foreign keys: " . $e->getMessage();
        }
        
        echo "\n";
    }

    /**
     * Check if indexes exist
     */
    private function checkIndexes() {
        echo "5. Checking indexes...\n";
        
        $expectedIndexes = [
            'customer_requests' => ['idx_user_id', 'idx_status', 'idx_created_at'],
            'request_details' => ['idx_request_id'],
            'request_attachments' => ['idx_request_id', 'idx_attachment_type'],
            'approved_orders' => ['idx_payment_status', 'idx_payment_date']
        ];
        
        foreach ($expectedIndexes as $table => $indexes) {
            foreach ($indexes as $index) {
                try {
                    $sql = "SELECT COUNT(*) FROM information_schema.STATISTICS
                            WHERE TABLE_NAME = ?
                            AND INDEX_NAME = ?
                            AND TABLE_SCHEMA = DATABASE()";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$table, $index]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $this->results[] = "✓ Index '$index' exists on '$table'";
                    } else {
                        $this->warnings[] = "⚠ Index '$index' missing on '$table'";
                    }
                } catch (PDOException $e) {
                    $this->warnings[] = "⚠ Could not check index '$index' on '$table'";
                }
            }
        }
        
        echo "\n";
    }

    /**
     * Compare with original table
     */
    private function compareWithOriginal() {
        echo "6. Comparing with original table...\n";
        
        try {
            // Check if original table still exists
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM user_requests");
            $originalCount = $stmt->fetchColumn();
            
            // Get new table count
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM customer_requests");
            $newCount = $stmt->fetchColumn();
            
            if ($originalCount == $newCount) {
                $this->results[] = "✓ Record count matches ($originalCount = $newCount)";
            } else {
                $this->warnings[] = "⚠ Record count mismatch (original: $originalCount, new: $newCount)";
            }
            
            // Sample data comparison
            $sql = "SELECT id, name, category, status FROM user_requests LIMIT 1";
            $stmt = $this->pdo->query($sql);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($original) {
                $sql = "SELECT cr.id, cr.name, cr.category, cr.status 
                        FROM customer_requests cr 
                        WHERE cr.id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$original['id']]);
                $new = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($new && $original['name'] == $new['name'] && $original['category'] == $new['category']) {
                    $this->results[] = "✓ Sample data matches between tables";
                } else {
                    $this->errors[] = "✗ Sample data mismatch detected";
                }
            }
            
        } catch (PDOException $e) {
            $this->warnings[] = "⚠ Could not compare with original table (may not exist)";
        }
        
        echo "\n";
    }

    /**
     * Display verification results
     */
    private function displayResults() {
        echo "═══════════════════════════════════════════════════════════\n";
        echo "                    VERIFICATION SUMMARY                    \n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        // Display successes
        if (!empty($this->results)) {
            echo "✓ PASSED CHECKS:\n";
            foreach ($this->results as $result) {
                echo "  $result\n";
            }
            echo "\n";
        }
        
        // Display warnings
        if (!empty($this->warnings)) {
            echo "⚠ WARNINGS:\n";
            foreach ($this->warnings as $warning) {
                echo "  $warning\n";
            }
            echo "\n";
        }
        
        // Display errors
        if (!empty($this->errors)) {
            echo "✗ ERRORS:\n";
            foreach ($this->errors as $error) {
                echo "  $error\n";
            }
            echo "\n";
        }
        
        // Overall status
        echo "═══════════════════════════════════════════════════════════\n";
        if (empty($this->errors)) {
            if (empty($this->warnings)) {
                echo "✓ MIGRATION SUCCESSFUL - All checks passed!\n";
            } else {
                echo "⚠ MIGRATION COMPLETED - With warnings (review above)\n";
            }
        } else {
            echo "✗ MIGRATION ISSUES DETECTED - Please review errors above\n";
        }
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        // Statistics
        $total = count($this->results) + count($this->warnings) + count($this->errors);
        $passed = count($this->results);
        $warned = count($this->warnings);
        $failed = count($this->errors);
        
        echo "Statistics:\n";
        echo "  Total Checks: $total\n";
        echo "  Passed: $passed\n";
        echo "  Warnings: $warned\n";
        echo "  Failed: $failed\n\n";
    }

    /**
     * Get detailed table information
     */
    public function getTableInfo($tableName) {
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "  TABLE INFORMATION: $tableName\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        try {
            // Get column information
            $sql = "DESCRIBE $tableName";
            $stmt = $this->pdo->query($sql);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Columns:\n";
            foreach ($columns as $col) {
                echo "  - {$col['Field']} ({$col['Type']})";
                if ($col['Key'] == 'PRI') echo " [PRIMARY KEY]";
                if ($col['Key'] == 'MUL') echo " [FOREIGN KEY]";
                if ($col['Key'] == 'UNI') echo " [UNIQUE]";
                echo "\n";
            }
            
            // Get record count
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM $tableName");
            $count = $stmt->fetchColumn();
            echo "\nTotal Records: $count\n";
            
            // Get sample data
            $stmt = $this->pdo->query("SELECT * FROM $tableName LIMIT 3");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($samples)) {
                echo "\nSample Data (first 3 records):\n";
                foreach ($samples as $i => $sample) {
                    echo "  Record " . ($i + 1) . ":\n";
                    foreach ($sample as $key => $value) {
                        $displayValue = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                        echo "    $key: $displayValue\n";
                    }
                    echo "\n";
                }
            }
            
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

// Run verification if executed directly
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    try {
        $verifier = new MigrationVerifier($pdo);
        $verifier->verify();
        
        // Optionally show detailed table info
        if (isset($argv[1]) && $argv[1] === '--detailed') {
            $verifier->getTableInfo('customer_requests');
            $verifier->getTableInfo('request_details');
            $verifier->getTableInfo('request_attachments');
            $verifier->getTableInfo('approved_orders');
        }
        
        echo "Run with --detailed flag to see table structures\n";
        echo "Example: php verify_migration.php --detailed\n\n";
        
    } catch (Exception $e) {
        echo "✗ Verification failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
