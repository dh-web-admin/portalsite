<?php<?php

require_once __DIR__ . '/../session_init.php';require_once __DIR__ . '/../session_init.php';



// Check if user is logged in and is admin// Check if user is logged in and is admin

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {

    header("Location: ../auth/login.php");    header("Location: ../auth/login.php");

    exit();    exit();

}}



// Include database configuration// Include database configuration

require_once '../config/config.php';require_once '../config/config.php';



// Get admin information// Get admin information

$email = $_SESSION['email'];$email = $_SESSION['email'];

$query = "SELECT role FROM users WHERE email='$email'";$query = "SELECT role FROM users WHERE email='$email'";

$result = $conn->query($query);$result = $conn->query($query);

$user = $result->fetch_assoc();$user = $result->fetch_assoc();



// Verify user is admin// Verify user is admin

if ($user['role'] !== 'admin') {if ($user['role'] !== 'admin') {

    header("Location: ../auth/login.php");    header("Location: ../auth/login.php");

    exit();    exit();

}}

?>?>



<!DOCTYPE html><!DOCTYPE html>

<html lang="en"><html lang="en">

<head><head>

    <meta charset="UTF-8">    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Bid Tracking</title>    <title>Equipments</title>

    <link rel="stylesheet" href="../assets/css/base.css">    <link rel="stylesheet" href="../assets/css/base.css">

    <link rel="stylesheet" href="../assets/css/admin-layout.css">    <link rel="stylesheet" href="../assets/css/admin-layout.css">

    <link rel="stylesheet" href="../assets/css/dashboard.css">    <link rel="stylesheet" href="../assets/css/dashboard.css">

</head></head>

<body class="admin-page"><body class="admin-page">

    <div class="admin-container">    <div class="admin-container">

        <?php include __DIR__ . '/../partials/portalheader.php'; ?>        <?php include __DIR__ . '/../partials/portalheader.php'; ?>

        <div class="admin-layout">        <div class="admin-layout">

            <?php include __DIR__ . '/../partials/admin_sidebar.php'; ?>            <?php include __DIR__ . '/../partials/admin_sidebar.php'; ?>

            <main class="content-area">            <main class="content-area">

                <div class="main-content">                <div class="main-content">

                    <h1>Bid Tracking</h1>                    <h1>Equipments</h1>

                    <!-- Bid Tracking content will go here -->                    <!-- Equipment content will go here -->

                </div>                </div>

            </main>            </main>

        </div>        </div>

    </div>    </div>

    <script>    <script>

    (function(){    (function(){

        var usersToggle = document.getElementById('usersToggle');        // Toggle users sub-nav

        var usersGroup = document.getElementById('usersGroup');        var usersToggle = document.getElementById('usersToggle');

        if (usersToggle && usersGroup) {        var usersGroup = document.getElementById('usersGroup');

            usersToggle.addEventListener('click', function(){        if (usersToggle && usersGroup) {

                usersGroup.classList.toggle('open');            usersToggle.addEventListener('click', function(){

            });                usersGroup.classList.toggle('open');

        }            });

    })();        }

    </script>    })();

</body>    </script>

</html></body>

</html>
