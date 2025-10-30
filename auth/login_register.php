<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';

if(isset($_POST['login'])){
    $email = trim($_POST['email']);  
    $password = $_POST['password'];
   
    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
   
    if($result->num_rows > 0){
        $user = $result->fetch_assoc();
       
        // // DEBUGGING - to check if user is found and password verification
        // echo "User found: " . $user['email'] . "<br>";
        // echo "Role: " . $user['role'] . "<br>";
        // echo "Password verify: " . (password_verify($password, $user['password']) ? 'SUCCESS' : 'FAILED') . "<br>";
        // die(); // Stop here to see the output
       
        if(password_verify($password, $user['password'])){
            // Strengthen session handling to persist reliably on Railway
            // Regenerate session ID to prevent fixation and force cookie set
            if (function_exists('session_regenerate_id')) {
                @session_regenerate_id(true);
            }

            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];

            // Ensure session data is written before redirect
            if (function_exists('session_write_close')) {
                @session_write_close();
            }

            if ($user['role'] === 'admin') {
                header("Location: ../pages/dashboard.php");
            }
            else if ($user['role'] === 'projectmanager') {
                header("Location: project_manager_dashboard.php");
            }
            else if ($user['role'] === 'estimator') {
                header("Location: estimator_dashboard.php");
            }
            else if ($user['role'] === 'accounting') {
                header("Location: accounting_dashboard.php");
            }
            else if ($user['role'] === 'superintendent') {
                header("Location: superintendent_dashboard.php");
            }
            else if ($user['role'] === 'foreman') {
                header("Location: foreman_dashboard.php");
            }
            else if ($user['role'] === 'mechanic') {
                header("Location: mechanic_dashboard.php");
            }
            else if ($user['role'] === 'operator') {
                header("Location: operator_dashboard.php");
            }
            else if ($user['role'] === 'laborer') {
                header("Location: laborer_dashboard.php");
            }
            exit();
        }
    }
   
    // Check if email exists at all
    if($result->num_rows === 0){
        $_SESSION['login_error'] = 'Email address not found.';
    } else {
        $_SESSION['login_error'] = 'Invalid password.';
    }
    
    $_SESSION['active_form'] = 'login';
    // Persist error session data before redirect
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    header("Location: login.php");
    exit();
}
?>
 
 