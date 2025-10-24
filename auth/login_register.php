<?php
session_start();
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
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
           
            if ($user['role'] === 'admin') {
                header("Location: ../admin/dashboard.php");
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
    header("Location: login.php");
    exit();
}
?>
 
 