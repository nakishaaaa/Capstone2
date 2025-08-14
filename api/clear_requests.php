<?php
include '../config/database.php'; 

// Only clear approved and rejected requests, keep pending ones
mysqli_query($conn, "
    INSERT INTO request_history (user_id, category, size, quantity, name, contact_number, notes, image_path, status, admin_response, created_at, updated_at, cleared_at)
    SELECT user_id, category, size, quantity, name, contact_number, notes, image_path, status, admin_response, created_at, updated_at, NOW()
    FROM user_requests
    WHERE status IN ('approved', 'rejected')
");

mysqli_query($conn, "DELETE FROM user_requests WHERE status IN ('approved', 'rejected')");

header("Location: ../admin_page.php#requests");
exit();
?>
