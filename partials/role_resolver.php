<?php
/**
 * Role Resolver
 * Handles developer preview mode and returns the effective role
 */

if (!isset($_SESSION['email'])) {
    $role = 'laborer';
    $actualRole = 'laborer';
} else {
    require_once __DIR__ . '/../config/config.php';
    
    $email = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $actualRole = $userData['role'] ?? 'laborer';
    $stmt->close();
    
    // Check if developer is previewing as another role
    if ($actualRole === 'developer' && isset($_GET['preview_role'])) {
        $previewRole = $_GET['preview_role'];
        $allowedRoles = ['admin', 'projectmanager', 'estimator', 'accounting', 'superintendent', 'foreman', 'mechanic', 'operator', 'laborer'];
        
        if (in_array($previewRole, $allowedRoles)) {
            $role = $previewRole;
        } else {
            $role = $actualRole;
        }
    } else {
        $role = $actualRole;
    }
}
