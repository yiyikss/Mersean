<?php
// Pages that change the shop itself (services, barbers) are for admins only.
// Staff can still use the appointments dashboard.
require_once __DIR__ . '/admin_guard.php';   // must be staff or admin first

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}