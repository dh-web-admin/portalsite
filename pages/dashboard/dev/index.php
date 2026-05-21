<?php
require_once __DIR__ . '/../../../session_init.php';

// Basic access control: only developer role may view
if (!isset($_SESSION['email'])) {
    header('Location: ' . base_url('/auth/login.php'));
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
if (isset($conn)) { $GLOBALS['conn'] = $conn; }

$email = $_SESSION['email'];
$role = 'laborer';
if ($stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1')) {
    $stmt->bind_param('s', $email);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $r = $res->fetch_assoc();
            if (!empty($r['role'])) $role = $r['role'];
        }
    }
    $stmt->close();
}

if ($role !== 'developer') {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You do not have access to the Dev Dashboard.</p>";
    exit();
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dev Dashboard</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/assets/css/base.css')); ?>">
</head>
<body>
    <?php include __DIR__ . '/../../../partials/portalheader.php'; ?>
    <main style="padding:24px;">
        <h1>Dev Dashboard</h1>
        <p>This is a developer-only area. Add your developer tools here.</p>
        <ul>
            <li><a href="<?php echo htmlspecialchars(base_url('/pages/dashboard/index.php')); ?>">Back to Dashboard</a></li>
        </ul>
    </main>
</body>
</html>
