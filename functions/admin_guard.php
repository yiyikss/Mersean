<?php
// Only staff and admins may open admin pages.
require_once __DIR__ . '/auth_guard.php';   // must be logged in first

if (!in_array($_SESSION['user']['role'] ?? '', ['admin', 'staff'], true)) {
    header('Location: dashboard.php');      // customers go to their own dashboard
    exit;
}