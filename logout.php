<?php
require_once __DIR__ . '/db/connect.php';
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;