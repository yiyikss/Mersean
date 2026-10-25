<?php
// Database connection + session start (every page that needs the database loads this).
// The shop's time zone, so "today" changes at midnight in the Philippines
// (PHP and MySQL must agree, otherwise the daily numbers reset at the wrong hour).
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$connect2db = mysqli_connect("localhost", "root", "", "merseans");

if (!$connect2db) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_query($connect2db, "SET time_zone = '+08:00'");