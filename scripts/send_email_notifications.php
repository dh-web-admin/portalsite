<?php
// scripts/send_email_notifications.php

require_once __DIR__ . '/../config/config.php'; // $conn (mysqli)

// ----------------------------
// Logger (use yours if exists, else fallback)
// ----------------------------

$loggerPath = __DIR__ . '/../partials/logger.php';
$logFile = __DIR__ . '/../debug/bids_email_cron.log';

if (file_exists($loggerPath)) {
    require_once $loggerPath; // expects logit($msg)
} else {

    function logit($msg) {
        global $logFile;

        @file_put_contents(
            $logFile,
            '[' . date('c') . '] ' . $msg . PHP_EOL,
            FILE_APPEND
        );
    }
}


// Ensure debug directory exists

$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}


// ----------------------------
// Mailer (Mailjet)
// ----------------------------

$mailerCandidates = [
    __DIR__ . '/../auth/mailjet_helper.php',
    __DIR__ . '/../partials/mailer.php',
    __DIR__ . '/../partials/mailer_helper.php'
];


$mailerPath = null;

foreach ($mailerCandidates as $cand) {

    if (file_exists($cand)) {
        $mailerPath = $cand;
        break;
    }

}


if (!$mailerPath) {

    logit(
        'Cron error: Mailer helper not found. Checked: '
        . implode(', ', $mailerCandidates)
    );

    throw new RuntimeException(
        'Mailer helper missing'
    );
}


require_once $mailerPath;



/**
 * Normalize mail function
 * Expected:
 * sendMail($to, $subject, $text, $html)
 */

if (!function_exists('sendMail')) {


    if (function_exists('send_mailjet')) {


        function sendMail($to, $subject, $text, $html)
        {
            return send_mailjet(
                $to,
                $subject,
                $text,
                $html
            );
        }


    } elseif (function_exists('sendMailjet')) {


        function sendMail($to, $subject, $text, $html)
        {
            return sendMailjet(
                $to,
                $subject,
                $text,
                $html
            );
        }


    } elseif (function_exists('send_email')) {


        function sendMail($to, $subject, $text, $html)
        {
            return send_email(
                $to,
                $subject,
                $text,
                $html
            );
        }


    } else {


        logit(
            'Cron error: No recognized mail function found. '
            . 'Expected sendMail/send_mailjet/sendMailjet/send_email'
        );


        throw new RuntimeException(
            'No mail sending function available'
        );

    }

}


// ----------------------------
// Replacement Daily Summary Script
// ----------------------------

$new = __DIR__ . '/../api/send_daily_bid_summaries.php';


if (file_exists($new)) {

    logit(
        'Starting daily bid summary sender: ' . $new
    );

    require_once $new;

    logit(
        'Daily bid summary sender completed'
    );

    return;

}



// Fallback if missing

logit(
    'ERROR: Replacement daily summary script missing'
);


http_response_code(500);


echo json_encode([
    'success' => false,
    'error' => 'Replacement daily summary script not found'
]);


exit(1);