<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['UID']) || $_SESSION['user_type'] !== 'User') {
    header("Location: index.html");
    exit();
}
