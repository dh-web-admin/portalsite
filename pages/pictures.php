<?php<?php<?php

require_once __DIR__ . '/../session_init.php';

require_once __DIR__ . '/../session_init.php';require_once __DIR__ . '/../session_init.php';

// Check if user is logged in

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {

    header('Location: ../auth/login.php');

    exit();// Check if user is logged in and is admin// Check if user is logged in and is admin

}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {

// Include database configuration

require_once __DIR__ . '/../config/config.php';    header("Location: ../auth/login.php");    header("Location: ../auth/login.php");



// Get user role for sidebar    exit();    exit();

$email = $_SESSION['email'];

$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');}}

$stmt->bind_param('s', $email);

$stmt->execute();

$res = $stmt->get_result();

$user = $res ? $res->fetch_assoc() : null;// Include database configuration// Include database configuration

$role = $user ? $user['role'] : 'laborer';

$stmt->close();require_once __DIR__ . '/../config/config.php';require_once __DIR__ . '/../config/config.php';

?>

<!DOCTYPE html>

<html lang="en">

<head>// Get admin information// Get admin information

  <meta charset="UTF-8" />

  <meta name="viewport" content="width=device-width, initial-scale=1.0" />$email = $_SESSION['email'];$email = $_SESSION['email'];

  <title>pictures</title>

  <link rel="stylesheet" href="../assets/css/base.css" />$query = "SELECT role FROM users WHERE email='$email'";$query = "SELECT role FROM users WHERE email='$email'";

  <link rel="stylesheet" href="../assets/css/admin-layout.css" />

  <link rel="stylesheet" href="../assets/css/dashboard.css" />$result = $conn->query($query);$result = $conn->query($query);

</head>

<body class="admin-page">$user = $result->fetch_assoc();$user = $result->fetch_assoc();

  <div class="admin-container">

    <?php include __DIR__ . '/../partials/portalheader.php'; ?>

    <div class="admin-layout">

      <?php include __DIR__ . '/../partials/sidebar.php'; ?>// Verify user is admin// Verify user is admin

      <main class="content-area">

        <div class="main-content">if ($user['role'] !== 'admin') {if ($user['role'] !== 'admin') {

          <h1>pictures</h1>

          <!-- pictures content will go here -->    header("Location: ../auth/login.php");    header("Location: ../auth/login.php");

        </div>

      </main>    exit();    exit();

    </div>

  </div>}}

  <script>

    (function(){?>?>

      var usersToggle = document.getElementById('usersToggle');

      var usersGroup = document.getElementById('usersGroup');

      if (usersToggle && usersGroup) {

        usersToggle.addEventListener('click', function(){<!DOCTYPE html><!DOCTYPE html>

          usersGroup.classList.toggle('open');

        });<html lang="en"><html lang="en">

      }

    })();<head><head>

  </script>

</body>    <meta charset="UTF-8">    <meta charset="UTF-8">

</html>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Equipments</title>    <title>Equipments</title>

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

                    <h1>Equipments</h1>                    <h1>Equipments</h1>

                    <!-- Equipment content will go here -->                    <!-- Equipment content will go here -->

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

