<?php
session_start();
// if user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
  header("Location: index.html");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Home - Library System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet" />
</head>

<body>

  <nav class="navbar">
    <div class="logo">Library System</div>
    <ul class="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="archive.php">Catalog</a></li>
      <li><a href="borrowed.php">Borrowed</a></li>
      <li><a href="history.php">History</a></li>
      <li><a href="profile.php">Profile</a></li>
    </ul>
  </nav>

  <div class="container">
    <div class="section">
      <h2>Welcome to Our School Library</h2>
      <p>
        Our library provides a wide selection of academic and non-academic resources to support your learning journey...
      </p>
    </div>

    <div class="section">
      <h2>Library Penalty Policy</h2>
      <p>
        Borrowed books must be returned on or before the due date. Overdue books will incur a daily fee...
      </p>
    </div>

    <div class="section">
      <h2>Lost or Damaged Books</h2>
      <p>
        Users are responsible for any lost or damaged items. A replacement copy or equivalent payment must be arranged...
      </p>
    </div>
  </div>

  <footer>
    © 2025 School Library System. All rights reserved.
  </footer>

</body>

</html>