<?php
// Simple log viewer for password reset mail log
// Shows the last 200 lines from debug/password_reset_mail.log

$logFile = __DIR__ . '/password_reset_mail.log';
$maxLines = 200;

function tailLines($file, $lines)
{
    if (!is_readable($file)) {
        return ["Log file not found or not readable: $file"];
    }
    $f = fopen($file, 'r');
    if (!$f) {
        return ["Unable to open log file: $file"];
    }
    $buffer = '';
    $chunkSize = 4096;
    $pos = -1;
    $lineCount = 0;
    $stat = fstat($f);
    $size = $stat['size'] ?? 0;
    $cursor = $size;

    while ($cursor > 0 && $lineCount <= $lines) {
        $seek = max(0, $cursor - $chunkSize);
        $chunkLen = $cursor - $seek;
        fseek($f, $seek);
        $chunk = fread($f, $chunkLen);
        $buffer = $chunk . $buffer;
        $lineCount = substr_count($buffer, "\n");
        $cursor = $seek;
    }

    fclose($f);
    $allLines = explode("\n", trim($buffer));
    return array_slice($allLines, -$lines);
}

$lines = tailLines($logFile, $maxLines);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password Reset Mail Log</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f8f9fb; color: #1f2d3d; margin: 0; padding: 20px; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 960px; margin: 0 auto; }
    h1 { margin: 0 0 10px; font-size: 22px; }
    .meta { color: #64748b; font-size: 14px; margin-bottom: 12px; }
    pre { background: #0b1220; color: #e5e7eb; padding: 14px; border-radius: 6px; overflow-x: auto; white-space: pre-wrap; word-break: break-word; }
    .empty { color: #94a3b8; font-style: italic; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Password Reset Mail Log</h1>
    <div class="meta">File: <?php echo htmlspecialchars($logFile); ?> — showing last <?php echo $maxLines; ?> lines</div>
    <?php if (empty($lines)): ?>
      <div class="empty">Log is empty.</div>
    <?php else: ?>
      <pre><?php echo htmlspecialchars(implode("\n", $lines)); ?></pre>
    <?php endif; ?>
  </div>
</body>
</html>
