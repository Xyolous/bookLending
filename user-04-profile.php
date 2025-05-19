<?php
include 'authentication-user.php';

$uid = $_SESSION['UID'];

$query = "SELECT first_name, last_name, school_id, email, address, contact, user_role, campus, course FROM users WHERE UID = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        $first_name = $user['first_name'];
        $last_name = $user['last_name'];
        $school_id = $user['school_id'];
        $email = $user['email'];
        $address = $user['address'];
        $contact = $user['contact'];
        $user_role = $user['user_role'];
        $campus = $user['campus'];
        $course = $user['course'];
    } else {
        error_log("User with UID $uid not found in the database after authentication.");
        session_unset();
        session_destroy();
        echo "Error: User data not found. Please log in again.";
        exit();
    }
    mysqli_stmt_close($stmt);
} else {
    // Error preparing the statement
    error_log("Error preparing user query: " . mysqli_error($conn));
    echo "Error fetching user data. Please try again later.";
    exit();
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Profile</title>
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/general.css">
    <link rel="stylesheet" href="css/user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <nav>
        <div class="nav-logo">
            <img src="images/logo.png" alt="site-logo" />
            <h1>Lumawig</h1>
        </div>

        <div class="nav-links">
            <a href="user-01-home.php" class="nav-btn" aria-label="Home">
                <i class="fas fa-home"></i> <span class="visually-hidden">Home</span>
            </a>
            <a href="user-02-books.php" class="nav-btn" aria-label="Catalog">
                <i class="fas fa-book"></i> <span class="visually-hidden">Catalog</span>
            </a>
            <a href="user-03-rented.php" class="nav-btn" aria-label="On Hand Books">
                <i class="fas fa-book-open"></i> <span class="visually-hidden">On Hand</span>
            </a>
            <a href="user-04-profile.php" class="nav-btn" aria-label="Profile">
                <i class="fas fa-user"></i> <span class="visually-hidden">Profile</span>
            </a>
            <a href="logout.php" class="nav-btn logout-btn" aria-label="Logout">
                <i class="fas fa-sign-out-alt"></i> <span class="visually-hidden">Logout</span>
            </a>
        </div>

        <button class="hamburger-icon" aria-label="Open menu">☰</button>

        <div class="sidebar">
            <div class="nav-links">
                <a href="user-01-home.php" class="nav-btn" aria-label="Home">
                    <i class="fas fa-home"></i> <span class="visually-hidden">Home</span>
                </a>
                <a href="user-02-books.php" class="nav-btn" aria-label="Catalog">
                    <i class="fas fa-book"></i> <span class="visually-hidden">Catalog</span>
                </a>
                <a href="user-03-rented.php" class="nav-btn" aria-label="On Hand Books">
                    <i class="fas fa-book-open"></i> <span class="visually-hidden">On Hand</span>
                </a>
                <a href="user-04-profile.php" class="nav-btn" aria-label="Profile">
                    <i class="fas fa-user"></i> <span class="visually-hidden">Profile</span>
                </a>
                <a href="logout.php" class="nav-btn logout-btn" aria-label="Logout">
                    <i class="fas fa-sign-out-alt"></i> <span class="visually-hidden">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <main>
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="images/boy.png" alt="User Avatar">
                </div>
                <h2><?= htmlspecialchars($first_name . ' ' . $last_name) ?></h2>
            </div>

            <div class="profile-info">
                <dl>
                    <dt><i class="fas fa-user-tag"></i> Role:</dt>
                    <dd><?= htmlspecialchars($user_role) ?></dd>

                    <?php if ($user_role === 'Student' && !empty($course)) : ?>
                        <dt><i class="fas fa-book"></i> Course:</dt>
                        <dd><?= htmlspecialchars($course) ?></dd>
                    <?php endif; ?>

                    <?php if (($user_role === 'Student' || $user_role === 'Faculty') && !empty($school_id)) : ?>
                        <dt><i class="fas fa-id-card"></i> School ID:</dt>
                        <dd><?= htmlspecialchars($school_id) ?></dd>
                    <?php endif; ?>

                    <dt><i class="fas fa-envelope"></i> Email:</dt>
                    <dd><?= htmlspecialchars($email) ?></dd>

                    <dt><i class="fas fa-map-marker-alt"></i> Address:</dt>
                    <dd><?= htmlspecialchars($address) ?: 'N/A' ?></dd>

                    <dt><i class="fas fa-phone"></i> Contact:</dt>
                    <dd><?= htmlspecialchars($contact) ?: 'N/A' ?></dd>


                    <dt><i class="fas fa-school"></i> Campus:</dt>
                    <dd><?= htmlspecialchars($campus) ?></dd>
                </dl>
            </div>
        </div>
    </main>


    <footer>
        <p>&copy; <span id="current-year"></span> Library mo 'to. All rights reserved.</p>
    </footer>

    <script>
        document.getElementById("current-year").textContent = new Date().getFullYear();
    </script>
    <script src="navigation.js"></script>
</body>

</html>