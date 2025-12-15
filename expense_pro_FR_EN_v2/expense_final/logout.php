<?php
/**
 * ExpensePro - Logout
 */

session_start();
session_destroy();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

header('Location: login.php');
exit;
