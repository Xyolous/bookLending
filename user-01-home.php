<?php
include 'authentication-user.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/general.css">
    <link rel="stylesheet" href="css/user.css">
    <link rel="stylesheet" href="css/background-slideshow-home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>User - Home</title>
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

    <header>
        <div class="welcome-banner">
            <h1>Welcome, <?php echo $_SESSION['first_name'] . " " . $_SESSION['last_name']; ?>!</h1>
        </div>
    </header>

    <main class="homepage-wrapper">
        <div class="content-card">
            <section class="library-details">
                <h2>About Us</h2>
                <p>Welcome to our library system! We aim to provide easy and organized access to a wide range of academic and non-academic books for students, faculty, and guests.</p>
            </section>

            <section class="library-details">
                <h2>About Overdue Penalty</h2>
                <p>Books not returned by the due date will incur a penalty of ₱10 per day. Please be mindful of return deadlines to avoid additional charges.</p>
            </section>

            <section class="library-details">
                <h2>About Lost or Damaged Books</h2>
                <p>If a book is lost or returned damaged, users are responsible for either replacing the book or paying its full value based on the library's valuation.</p>
            </section>
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
<div class="background-slideshow"></div>

</html>