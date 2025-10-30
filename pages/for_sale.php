<?php<?php<?php

require_once __DIR__ . '/../session_init.php';

require_once __DIR__ . '/../session_init.php';require_once __DIR__ . '/../session_init.php';

// Check if user is logged in and is admin

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {

    header("Location: ../auth/login.php");

    exit();// Check if user is logged in and is admin// Check if user is logged in and is admin

}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {

// Include database configuration

require_once __DIR__ . '/../config/config.php';    header("Location: ../auth/login.php");    header("Location: ../auth/login.php");



// Get admin information    exit();    exit();

$email = $_SESSION['email'];

$query = "SELECT role FROM users WHERE email='$email'";}}

$result = $conn->query($query);

$user = $result->fetch_assoc();



// Verify user is admin// Include database configuration// Include database configuration

if ($user['role'] !== 'admin') {

    header("Location: ../auth/login.php");require_once '../config/config.php';require_once '../config/config.php';

    exit();

}

?>

// Get admin information// Get admin information

<!DOCTYPE html>

<html lang="en">$email = $_SESSION['email'];$email = $_SESSION['email'];

<head>

    <meta charset="UTF-8">$query = "SELECT role FROM users WHERE email='$email'";$query = "SELECT role FROM users WHERE email='$email'";

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>For Sale</title>$result = $conn->query($query);$result = $conn->query($query);

    <link rel="stylesheet" href="../assets/css/base.css">

    <link rel="stylesheet" href="../assets/css/admin-layout.css">$user = $result->fetch_assoc();$user = $result->fetch_assoc();

    <link rel="stylesheet" href="../assets/css/dashboard.css">

</head>

<body class="admin-page">

    <div class="admin-container">// Verify user is admin// Verify user is admin

        <?php include __DIR__ . '/../partials/portalheader.php'; ?>

        <div class="admin-layout">if ($user['role'] !== 'admin') {if ($user['role'] !== 'admin') {

            <?php include __DIR__ . '/../partials/admin_sidebar.php'; ?>

            <main class="content-area">    header("Location: ../auth/login.php");    header("Location: ../auth/login.php");

                <div class="main-content">

                    <h1>For Sale</h1>    exit();    exit();

                    <!-- For Sale content will go here -->

                </div>}}

            </main>

        </div>?>?>

    </div>

    <script>

    (function(){

        var usersToggle = document.getElementById('usersToggle');<!DOCTYPE html><!DOCTYPE html>

        var usersGroup = document.getElementById('usersGroup');

        if (usersToggle && usersGroup) {<html lang="en"><html lang="en">

            usersToggle.addEventListener('click', function(){

                usersGroup.classList.toggle('open');<head><head>

            });

        }    <meta charset="UTF-8">    <meta charset="UTF-8">

    })();

    </script>    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <meta name="viewport" content="width=device-width, initial-scale=1.0">

</body>

</html>    <title>For Sale</title>    <title>Equipments</title>


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

                    <h1>For Sale</h1>                    <h1>Equipments</h1>

                    <!-- For Sale content will go here -->                    <!-- Equipment content will go here -->

                </div>                </div>

            </main>            </main>

        </div>        </div>

    </div>    </div>

    <script>    <script>

    (function(){    (function(){

        // Toggle users sub-nav        // Toggle users sub-nav

        var usersToggle = document.getElementById('usersToggle');        var usersToggle = document.getElementById('usersToggle');

        var usersGroup = document.getElementById('usersGroup');        var usersGroup = document.getElementById('usersGroup');

        if (usersToggle && usersGroup) {        if (usersToggle && usersGroup) {

            usersToggle.addEventListener('click', function(){            usersToggle.addEventListener('click', function(){

                usersGroup.classList.toggle('open');                usersGroup.classList.toggle('open');

            });            });

        }        }

    })();    })();

    </script>    </script>

</body></body>

</html></html>

