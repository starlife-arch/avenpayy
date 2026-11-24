<?php
// index.php
session_start();

// Redirect to login if not logged in
 {
    header("Location: /dashboard");
    exit();
}


?>