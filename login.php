<?php
session_start();
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['email'], $_POST['password'])) {

        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT UID, first_name, last_name, password, user_type FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            echo "<script>alert('No user found with that email!'); window.location='index.html';</script>";
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['UID'] = $user['UID'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['user_type'] = $user['user_type'];

            $update_stmt = $conn->prepare("UPDATE users SET date_last_login = NOW() WHERE UID = ?");
            $update_stmt->bind_param("i", $user['UID']);
            $update_stmt->execute();
            $update_stmt->close();

            if ($user['user_type'] === 'Admin') {
                header("Location: admin-01-home.php");
            } else {
                header("Location: user-01-home.php");
            }
            exit();
        } else {
            echo "<script>alert('Incorrect password!'); window.location='index.html';</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('Email and password are required!'); window.location='index.html';</script>";
    }
}
