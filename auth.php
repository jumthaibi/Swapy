<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include $_SERVER['DOCUMENT_ROOT'] . '/Swapy/db.php';

function protect_page($required_role) {
    if (!isset($_SESSION['swapy_session'])) {
        header("Location: /Swapy/login.php");
        exit();
    }
    
    if ($_SESSION['role'] != $required_role) {
        die("you dont have access");
    }
}
