<?php
/**
 * Database Connection for Swapy Project
 * This file acts as the central bridge between the server and MySQL.
 */

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "swapy_db";

// Establishing connection using MySQLi
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Checking connection status
if (!$conn) {
    die("CRITICAL ERROR: Database Connection Failed. " . mysqli_connect_error());
}

// Set character set to utf8mb4 to support Arabic and Emojis correctly
mysqli_set_charset($conn, "utf8mb4");

// Set the default timezone for the application
date_default_timezone_set('Asia/Amman');

