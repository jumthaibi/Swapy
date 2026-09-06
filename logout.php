<?php

//open the session to access session variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Store the user's role before destroying the session
$user_role = isset($_SESSION['role']) ? (int)$_SESSION['role'] : 0;

session_unset();
session_destroy();

if($user_role === 0){
    header("Location: login.php"); 
    exit();
}
else{header("Location: index.php"); 
exit();}
?>
