<?php
include 'authentication-user.php';

$logged_in_user_id = $_SESSION['UID'];
$transaction_sql = "
    SELECT
        b.title,
        b.author,
        t.borrow_date,
        t.due_date,
        t.return_date,
        t.transaction_status
    FROM transaction t
    JOIN books b ON t.BID = b.BID
    WHERE t.UID = ?
    ORDER BY t.borrow_date DESC;
";

$transaction_stmt = $conn->prepare($transaction_sql);
$transaction_stmt->bind_param("i", $logged_in_user_id);
$transaction_stmt->execute();
$transaction_result = $transaction_stmt->get_result();

$transaction_books = [];
if ($transaction_result->num_rows > 0) {
  while ($row = $transaction_result->fetch_assoc()) {
    $display_status = $row['transaction_status'];
    if ($display_status === 'Borrowed' && strtotime($row['due_date']) < time()) {
      $display_status = 'Overdue';
    }
    $row['display_status'] = $display_status;

    if ($display_status === 'Borrowed' || $display_status === 'Overdue') {
      $transaction_books[] = $row;
    }
  }
}
$transaction_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="css/nav.css">
  <link rel="stylesheet" href="css/general.css">
  <link rel="stylesheet" href="css/user.css">
  <link rel="stylesheet" href="css/table.css">
  <title>User - Borrowed Books</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      overflow-x: hidden;
    }

    /* Default Table Styles */
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #fff;
      border-radius: 8px;
      font-size: 1rem;
    }

    th,
    td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }

    th {
      background-color: #333;
      color: white;
    }

    .onhand-status {
      font-weight: bold;
      color: green;
    }

    /* Responsive Table */
    @media (max-width: 768px) {

      table,
      thead,
      tbody,
      th,
      td,
      tr {
        display: block;
        width: 100%;
      }

      thead {
        display: none;
      }

      tr {
        margin-bottom: 1.5rem;
        border: 1px solid #ccc;
        border-radius: 10px;
        background-color: #f9f9f9;
        padding: 10px;
      }

      td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #eee;
        word-wrap: break-word;
      }

      td::before {
        content: attr(data-label);
        font-weight: bold;
        flex-basis: 40%;
        color: white;
      }

      td:last-child {
        border-bottom: none;
      }
    }
  </style>
</head>

<body>
  <nav>
    <div class="nav-logo">
      <img src="assets/logo.png" alt="site-logo" />
      <h1>Library mo 'to</h1>
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
    <h1>My Currently Borrowed Books</h1>
  </header>

  <main>
    <section>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Author</th>
              <th>Status</th>
              <th>Borrow Date</th>
              <th>Due Date</th>
            </tr>

          </thead>
          <tbody>
            <?php if (empty($transaction_books)) : ?>
              <tr>
                <td colspan="5" style="text-align: center;">No books currently borrowed or overdue.</td>
              </tr>
            <?php else : ?>
              <?php foreach ($transaction_books as $book) : ?>
                <tr>
                  <td data-label="Title"><?= htmlspecialchars($book['title']) ?></td>
                  <td data-label="Author"><?= htmlspecialchars($book['author']) ?></td>
                  <td data-label="Status" class="onhand-status"><?= htmlspecialchars($book['display_status']) ?></td>
                  <td data-label="Borrow Date"><?= htmlspecialchars($book['borrow_date']) ?></td>
                  <td data-label="Due Date"><?= htmlspecialchars($book['due_date']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
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