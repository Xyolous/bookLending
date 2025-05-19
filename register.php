<?php
session_start();
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $required_fields = ['first_name', 'last_name', 'email', 'contact', 'address', 'campus', 'user_role', 'password', 'confirm_password'];

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo "<script>alert('" . ucfirst(str_replace('_', ' ', $field)) . " is required!'); window.location='index-register.html';</script>";
            exit();
        }
    }

    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $user_role = htmlspecialchars(trim($_POST['user_role']));
    $email = htmlspecialchars(trim($_POST['email']));
    $contact = htmlspecialchars(trim($_POST['contact']));
    $address = htmlspecialchars(trim($_POST['address']));
    $campus = htmlspecialchars(trim($_POST['campus']));
    $password = $_POST['password'];
    $cpass = $_POST['confirm_password'];
    $school_id = NULL;
    $course = NULL;

    if ($user_role === "Student" || $user_role === "Faculty") {
        if (empty($_POST['school_id'])) {
            echo "<script>alert('School ID is required for " . $user_role . "!'); window.location='index-register.html';</script>";
            exit();
        }
        $school_id = htmlspecialchars(trim($_POST['school_id']));
    }

    if ($user_role === "Student") {
        if (empty($_POST['course'])) {
            echo "<script>alert('Course is required for Student!'); window.location='index-register.html';</script>";
            exit();
        }
        $course = htmlspecialchars(trim($_POST['course']));
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format!'); window.location='index-register.html';</script>";
        exit();
    }

    if ($password !== $cpass) {
        echo "<script>alert('Passwords do not match!'); window.location='index-register.html';</script>";
        exit();
    }

    if (strlen($password) < 8) {
        echo "<script>alert('Password must be at least 8 characters long!'); window.location='index-register.html';</script>";
        exit();
    }

    $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Email is already registered!'); window.location='index-register.html';</script>";
        $check->close();
        exit();
    }
    $check->close();

    $hash_pass = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, user_role, school_id, campus, course, email, contact, address, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssssss", $first_name, $last_name, $user_role, $school_id, $campus, $course, $email, $contact, $address, $hash_pass);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! You can now log in.'); window.location='index.html';</script>";
    } else {
        error_log("Registration failed: " . $stmt->error);
        echo "<script>alert('Registration failed. Please try again later.'); window.location='index-register.html';</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    header('Location: index-register.html');
    exit();
}
