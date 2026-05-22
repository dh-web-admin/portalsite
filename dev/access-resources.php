<?php require_once __DIR__ . '/auth_check.php'; ?>
<?php require_once __DIR__ . '/../partials/url.php'; ?>
<?php
// Show request details to developer and provide a Grant Access button
$requester_email = isset($_GET['requester_email']) ? $_GET['requester_email'] : '';
$requester_name = isset($_GET['requester_name']) ? $_GET['requester_name'] : '';
$github_username = isset($_GET['github_username']) ? $_GET['github_username'] : '';
$railway_email = isset($_GET['railway_email']) ? $_GET['railway_email'] : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Access Resources</title>
    <style>
        body{font-family:Segoe UI,Arial;background:radial-gradient(circle at 25% 25%, #1e293b 0%, #0f172a 70%);color:#e6eef8;padding:28px}
        .card{background:rgba(255,255,255,0.03);padding:22px;border-radius:12px;border:1px solid rgba(255,255,255,0.06);max-width:900px;margin:0 auto}
        .actions{margin-top:18px}
        .btn{display:inline-block;padding:10px 16px;background:#10b981;color:#fff;border-radius:8px;text-decoration:none}
    </style>
</head>
<body>
    <div class="card">
        <h2>Grant Access</h2>
        <p><strong><?php echo htmlspecialchars($requester_name ?: $requester_email); ?></strong> has requested access.</p>
        <ul>
            <li>GitHub username: <?php echo htmlspecialchars($github_username); ?></li>
            <li>Railway email: <?php echo htmlspecialchars($railway_email); ?></li>
            <li>Requester email: <?php echo htmlspecialchars($requester_email); ?></li>
        </ul>
        <div class="actions">
            <form method="post" action="grant_access.php">
                <input type="hidden" name="requester_email" value="<?php echo htmlspecialchars($requester_email); ?>">
                <input type="hidden" name="requester_name" value="<?php echo htmlspecialchars($requester_name); ?>">
                <input type="hidden" name="github_username" value="<?php echo htmlspecialchars($github_username); ?>">
                <input type="hidden" name="railway_email" value="<?php echo htmlspecialchars($railway_email); ?>">
                <button type="submit" class="btn">Grant access</button>
            </form>
        </div>
    </div>
</body>
</html>
