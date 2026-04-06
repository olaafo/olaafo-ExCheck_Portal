<?php
require_once __DIR__ . '/config.php';

$admin = isset($_GET['admin']);

if ($admin) {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
} else {
    unset($_SESSION['student_id'], $_SESSION['student_name'], $_SESSION['student_idx']);
}

session_regenerate_id(true);
redirect(BASE_URL . '/index.php' . ($admin ? '?mode=admin' : ''));
