<?php
/**
 * Mailjet Helper for Password Reset Code Emails
 * 
 * Sends a password reset code email via Mailjet v3.1 API
 * Requires: MAILJET_API_KEY and MAILJET_API_SECRET environment variables
 */

function sendResetCode($email, $code) {
    $api_key = getenv('MAILJET_API_KEY');
    $api_secret = getenv('MAILJET_API_SECRET');

    // Lightweight file logger for troubleshooting Mailjet delivery issues
    $logFile = __DIR__ . '/../debug/password_reset_mail.log';
    $log = function ($message) use ($logFile) {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        // Suppress errors if the file system is read-only; best-effort logging only
        @file_put_contents($logFile, $line, FILE_APPEND);
    };

    // Fallback if env vars not set (for local testing; use caution)
    if (!$api_key || !$api_secret) {
        $msg = 'Mailjet credentials not configured.';
        $log($msg . ' Email: ' . $email . ', Code: ' . $code);
        return ['success' => false, 'error' => $msg];
    }

    $from_email = "noreply@darkhorsespreader.com";
    $from_name = "Dark Horse Spreader";

    $payload = [
        'Messages' => [[
            'From' => [
                'Email' => $from_email,
                'Name' => $from_name
            ],
            'To' => [[
                'Email' => $email
            ]],
            'Subject' => 'Your Password Reset Code',
            'TextPart' => "Your password reset code is: $code\n\nThis code expires in 15 minutes.\n\nIf you did not request this, please ignore this email.",
            'HTMLPart' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>Password Reset Request</h2>
                    <p>Your password reset code is:</p>
                    <div style='background-color: #f0f0f0; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;'>
                        <h1 style='letter-spacing: 5px; color: #333;'>$code</h1>
                    </div>
                    <p><strong>This code expires in 15 minutes.</strong></p>
                    <p>If you did not request a password reset, please ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #ccc; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #666;'>&copy; Dark Horse Spreader. All rights reserved.</p>
                </div>
            "
        ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mailjet.com/v3.1/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':' . $api_secret);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return ['success' => true, 'error' => ''];
    }

    $msg = 'Mailjet API error. Code: ' . $http_code . ', CurlError: ' . $curl_error . ', Response: ' . $response;
    $log($msg . ', Email: ' . $email);
    return ['success' => false, 'error' => $msg];
}

/**
 * Generic email sender using Mailjet
 * Returns ['success'=>bool,'error'=>string]
 */
function sendMail($toEmail, $subject, $textPart, $htmlPart = null) {
    $api_key = getenv('MAILJET_API_KEY');
    $api_secret = getenv('MAILJET_API_SECRET');

    $logFile = __DIR__ . '/../debug/mailjet_send.log';
    $log = function ($message) use ($logFile) {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    };

    if (!$api_key || !$api_secret) {
        $msg = 'Mailjet credentials not configured.';
        $log($msg . ' Email: ' . $toEmail . ', Subject: ' . $subject);
        return ['success' => false, 'error' => $msg];
    }

    $from_email = "noreply@darkhorsespreader.com";
    $from_name = "Dark Horse Spreader";

    $payload = [
        'Messages' => [[
            'From' => [ 'Email' => $from_email, 'Name' => $from_name ],
            'To' => [[ 'Email' => $toEmail ]],
            'Subject' => $subject,
            'TextPart' => $textPart,
            'HTMLPart' => $htmlPart ?: nl2br(htmlspecialchars($textPart))
        ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mailjet.com/v3.1/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':' . $api_secret);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return ['success' => true, 'error' => ''];
    }

    $msg = 'Mailjet API error. Code: ' . $http_code . ', CurlError: ' . $curl_error . ', Response: ' . $response;
    $log($msg . ', Email: ' . $toEmail . ', Subject: ' . $subject);
    return ['success' => false, 'error' => $msg];
}

?>
