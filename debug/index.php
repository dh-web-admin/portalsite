<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../config/config.php';

// Get user role
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

// Only allow admin access
if ($role !== 'admin') {
    header('Location: ../pages/dashboard/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Debug Tools - PortalSite</title>
  <link rel="stylesheet" href="../assets/css/base.css" />
  <link rel="stylesheet" href="../assets/css/admin-layout.css" />
  <style>
    .debug-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 40px 20px;
    }

    .debug-header {
      margin-bottom: 40px;
      padding-bottom: 20px;
      border-bottom: 3px solid #667eea;
    }

    .debug-header h1 {
      color: #667eea;
      font-size: 2.5rem;
      margin-bottom: 10px;
    }

    .debug-header p {
      color: #666;
      font-size: 1.1rem;
    }

    .debug-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    .debug-card {
      background: white;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border-left: 5px solid #667eea;
    }

    .debug-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .debug-card-icon {
      font-size: 3rem;
      margin-bottom: 15px;
    }

    .debug-card h2 {
      font-size: 1.5rem;
      color: #333;
      margin-bottom: 10px;
    }

    .debug-card p {
      color: #666;
      line-height: 1.6;
      margin-bottom: 20px;
    }

    .debug-btn {
      display: inline-block;
      padding: 12px 24px;
      background: #667eea;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .debug-btn:hover {
      background: #5568d3;
      transform: translateX(5px);
    }

    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .back-link:hover {
      transform: translateX(-5px);
    }

    .warning-box {
      background: #fef3c7;
      border-left: 4px solid #f59e0b;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 30px;
    }

    .warning-box h3 {
      color: #92400e;
      margin-bottom: 10px;
    }

    .warning-box p {
      color: #78350f;
      margin: 0;
    }
  </style>
</head>
<body>
  <div class="debug-container">
    <a href="../pages/dashboard/index.php" class="back-link">← Back to Dashboard</a>
    
    <div class="debug-header">
      <h1>🔧 Debug Tools</h1>
      <p>Developer utilities for debugging and testing PortalSite</p>
    </div>

    <div class="warning-box">
      <h3>⚠️ Admin Only</h3>
      <p>These tools are for development and debugging purposes. Use with caution in production environments.</p>
    </div>

    <div class="debug-grid">
      <!-- Session Test -->
      <div class="debug-card">
        <div class="debug-card-icon">🔐</div>
        <h2>Session Test</h2>
        <p>View current session variables and test session persistence across pages.</p>
        <a href="session_test.php" class="debug-btn">Open Tool →</a>
      </div>

      <!-- Debug Session -->
      <div class="debug-card">
        <div class="debug-card-icon">📊</div>
        <h2>Debug Session</h2>
        <p>Detailed session debugging information including all session data and cookies.</p>
        <a href="debug_session.php" class="debug-btn">Open Tool →</a>
      </div>

      <!-- Page Load Debug -->
      <div class="debug-card">
        <div class="debug-card-icon">⚡</div>
        <h2>Page Load Debug</h2>
        <p>Analyze page load times, included files, and performance metrics.</p>
        <a href="debug_page_load.php" class="debug-btn">Open Tool →</a>
      </div>

      <!-- Health Check -->
      <div class="debug-card">
        <div class="debug-card-icon">❤️</div>
        <h2>Health Check</h2>
        <p>System health status including database connectivity and server configuration.</p>
        <a href="health.php" class="debug-btn">Open Tool →</a>
      </div>

      <!-- Pages Health -->
      <div class="debug-card">
        <div class="debug-card-icon">📄</div>
        <h2>Pages Health</h2>
        <p>Check accessibility and status of all pages in the application.</p>
        <a href="pages_health.php" class="debug-btn">Open Tool →</a>
      </div>
    </div>
  </div>
</body>
</html>
