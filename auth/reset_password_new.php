<?php
// This page is deprecated. The reset flow now uses `reset_password.php` (single-page flow).
// Redirect users back to the new flow.
require_once __DIR__ . '/../session_init.php';
session_write_close();
header('Location: reset_password.php');
exit();
