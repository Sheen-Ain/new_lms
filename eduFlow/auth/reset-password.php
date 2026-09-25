<?php
// reset-password.php is no longer a standalone page.
// The full flow (email → OTP → new password) lives in forgot-password.php as a single AJAX page.
session_start();
header('Location: ' . BASE_PATH . '/auth/forgot-password.php');
exit;