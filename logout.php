<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
// destroy session and redirect before any output
session_unset();
session_destroy();
header('Location: ' . BASE_PATH . '/'); exit;
