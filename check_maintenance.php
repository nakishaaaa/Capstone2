<?php
// Quick script to check and disable maintenance mode
require_once 'includes/config.php';

echo "<h2>Maintenance Mode Status Check</h2>";

// Check current maintenance mode status
$result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_status = $row['setting_value'];
    echo "<p><strong>Current maintenance mode:</strong> " . ($current_status === 'true' ? 'ENABLED' : 'DISABLED') . "</p>";
    
    if ($current_status === 'true') {
        echo "<p style='color: red;'><strong>⚠️ MAINTENANCE MODE IS ENABLED!</strong></p>";
        echo "<p>This is why you're being redirected to maintenance.php</p>";
        
        // Disable maintenance mode
        $update = $conn->prepare("UPDATE system_settings SET setting_value = 'false' WHERE setting_key = 'maintenance_mode'");
        if ($update->execute()) {
            echo "<p style='color: green;'>✅ <strong>Maintenance mode has been DISABLED</strong></p>";
            echo "<p>You should now be able to access index.php normally.</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to disable maintenance mode</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Maintenance mode is already disabled</p>";
    }
} else {
    echo "<p>No maintenance mode setting found in database</p>";
    
    // Create the setting as disabled
    $insert = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', 'false')");
    if ($insert->execute()) {
        echo "<p style='color: green;'>✅ Created maintenance mode setting as DISABLED</p>";
    }
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Index</a></p>";
echo "<p><a href='super_admin_dashboard.php'>← Back to Super Admin Dashboard</a></p>";
?>
