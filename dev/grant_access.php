<?php require_once __DIR__ . '/auth_check.php'; ?>
<?php require_once __DIR__ . '/../partials/url.php'; ?>
<?php
// Placeholder for granting access. Currently just shows confirmation and details.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: access-resources.php');
    exit();
}

$requester_email = $_POST['requester_email'] ?? '';
$requester_name = $_POST['requester_name'] ?? '';
$github_username = $_POST['github_username'] ?? '';
$railway_email = $_POST['railway_email'] ?? '';

// TODO: Implement actual granting actions (add to GitHub team, invite to Railway, etc.)

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Access Granted</title>
    <style>
        body{font-family:Segoe UI,Arial;background:radial-gradient(circle at 25% 25%, #1e293b 0%, #0f172a 70%);color:#e6eef8;padding:28px}
        .card{background:rgba(255,255,255,0.03);padding:22px;border-radius:12px;border:1px solid rgba(255,255,255,0.06);max-width:900px;margin:0 auto}
    </style>
</head>
<body>
    <div class="card">
        <h2>Grant Access — Placeholder</h2>
        <p>Request for <strong><?php echo htmlspecialchars($requester_name ?: $requester_email); ?></strong> processed (placeholder).</p>
        <p>GitHub username: <?php echo htmlspecialchars($github_username); ?></p>
        <p>Railway email: <?php echo htmlspecialchars($railway_email); ?></p>
        <p>Implement the actual grant steps here.</p>
    </div>
</body>
</html>
