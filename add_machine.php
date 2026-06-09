<?php
// add_machine.php — Disabled in single-machine mode.
// Redirects to machines.php with a notice.
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$_SESSION['flash'] = 'Adding machines is disabled in single-machine mode. It will be available during expansion.';
header("Location: machines.php");
exit();