<?php
include 'authentication-admin.php';

$sql = "SELECT
            t.TID,
            t.borrow_date,
            t.due_date,
            t.return_date,
            t.transaction_status,
            b.title AS book_title,
            u.first_name,
            u.last_name
        FROM
            transaction t
        JOIN
            books b ON t.BID = b.bid
        JOIN
            users u ON t.UID = u.UID
        ORDER BY
            t.borrow_date DESC";

$result = $conn->query($sql);

$all_transactions = [];
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {

    $display_status = $row['transaction_status'];

    if ($display_status === 'Borrowed' && strtotime($row['due_date']) < time()) {
      $display_status = 'Overdue';
    }
    $row['display_status'] = $display_status;

    $all_transactions[] = $row;
  }
}

$overdue_transactions = [];
$other_transactions = [];

foreach ($all_transactions as $transaction) {
  if ($transaction['display_status'] === 'Overdue') {
    $overdue_transactions[] = $transaction;
  } else {
    $other_transactions[] = $transaction;
  }
}


usort($overdue_transactions, function ($a, $b) {
  return strtotime($a['due_date']) - strtotime($b['due_date']);
});


usort($other_transactions, function ($a, $b) {
  return strtotime($b['borrow_date']) - strtotime($a['borrow_date']);
});

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin - Transaction History</title>
  <link rel="stylesheet" href="css/nav.css">
  <link rel="stylesheet" href="css/general.css">
  <link rel="stylesheet" href="css/admin.css">
  <link rel="stylesheet" href="css/table.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
  <nav>
    <div class="nav-logo">
      <img src="images/logo.png" alt="site-logo" />
      <h1>Lumawig</h1>
    </div>

    <div class="nav-links">
      <a href="admin-01-home.php" class="nav-btn" aria-label="Home">
        <i class="fas fa-home"></i> <span class="visually-hidden">Home</span>
      </a>
      <a href="admin-02-books.php" class="nav-btn" aria-label="Manage Books">
        <i class="fas fa-book"></i> <span class="visually-hidden">Manage Books</span>
      </a>
      <a href="admin-03-reservation.php" class="nav-btn" aria-label="Reservation">
        <i class="fas fa-clipboard-list"></i> <span class="visually-hidden">Reservation</span>
      </a>
      <a href="admin-04-history.php" class="nav-btn" aria-label="History">
        <i class="fas fa-history"></i> <span class="visually-hidden">History</span>
      </a>
      <a href="admin-05-users.php" class="nav-btn" aria-label="Users">
        <i class="fas fa-users"></i> <span class="visually-hidden">Users</span>
      </a>
      <a href="logout.php" class="nav-btn logout-btn" aria-label="Logout">
        <i class="fas fa-sign-out-alt"></i> <span class="visually-hidden">Logout</span>
      </a>
    </div>

    <button class="hamburger-icon" aria-label="Open menu">☰</button>

    <div class="sidebar">
      <div class="nav-links">
        <a href="admin-01-home.php" class="nav-btn" aria-label="Home">
          <i class="fas fa-home"></i> <span class="visually-hidden">Home</span>
        </a>
        <a href="admin-02-books.php" class="nav-btn" aria-label="Manage Books">
          <i class="fas fa-book"></i> <span class="visually-hidden">Manage Books</span>
        </a>
        <a href="admin-03-reservation.php" class="nav-btn" aria-label="Reservation">
          <i class="fas fa-clipboard-list"></i> <span class="visually-hidden">Reservation</span>
        </a>
        <a href="admin-04-history.php" class="nav-btn" aria-label="History">
          <i class="fas fa-history"></i> <span class="visually-hidden">History</span>
        </a>
        <a href="admin-05-users.php" class="nav-btn" aria-label="Users">
          <i class="fas fa-users"></i> <span class="visually-hidden">Users</span>
        </a>
        <a href="logout.php" class="nav-btn logout-btn" aria-label="Logout">
          <i class="fas fa-sign-out-alt"></i> <span class="visually-hidden">Logout</span>
        </a>
      </div>
    </div>
  </nav>

  <header>
    <h1>All Transaction History</h1>
  </header>

  <main>
    <section>
      <?php if (empty($all_transactions)):
      ?>
        <p>No transaction records found.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Book Title</th>
              <th>Borrower</th>
              <th>Borrow Date</th>
              <th>Due Date</th>
              <th>Return Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($overdue_transactions)):
            ?>
              <tr>
                <td colspan="7" class="transaction-header">OVERDUE TRANSACTIONS</td>
              </tr>
              <?php foreach ($overdue_transactions as $row):
              ?>
                <tr>
                  <td data-label="TID"><?php echo htmlspecialchars($row['TID']); ?></td>
                  <td data-label="Title"><?php echo htmlspecialchars($row['book_title']); ?></td>
                  <td data-label="Name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                  <td data-label="Borrow Date"><?php echo htmlspecialchars($row['borrow_date']); ?></td>
                  <td data-label="Due Date"><?php echo htmlspecialchars($row['due_date']); ?></td>
                  <td data-label="Return Date"><?php echo $row['return_date'] ? htmlspecialchars($row['return_date']) : 'N/A'; ?></td>
                  <td data-label="Status" class="transaction-status-overdue"> <?php echo htmlspecialchars($row['display_status']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($other_transactions)):
            ?>
              <?php if (!empty($overdue_transactions)):
              ?>
                <tr>
                  <td colspan="7" class="transaction-header">OTHER TRANSACTIONS</td>
                </tr>
              <?php endif; ?>

              <?php foreach ($other_transactions as $row):
              ?>
                <?php
                $status_class = '';
                if ($row['display_status'] === 'Returned') {
                  $status_class = 'transaction-status-returned';
                } elseif ($row['display_status'] === 'Borrowed') {
                  $status_class = 'transaction-status-borrowed';
                }
                ?>
                <tr>
                  <td data-label="TID"><?php echo htmlspecialchars($row['TID']); ?></td>
                  <td data-label="Title"><?php echo htmlspecialchars($row['book_title']); ?></td>
                  <td data-label="Name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                  <td data-label="Brrow Date"><?php echo htmlspecialchars($row['borrow_date']); ?></td>
                  <td data-label="Due Date"><?php echo htmlspecialchars($row['due_date']); ?></td>
                  <td data-label="Return Date"><?php echo $row['return_date'] ? htmlspecialchars($row['return_date']) : 'N/A'; ?></td>
                  <td data-label="Status" class="<?php echo $status_class; ?>"> <?php echo htmlspecialchars($row['display_status']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>

          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>

  <footer>
    <p>&copy; <span id="current-year"></span> All rights reserved.</p>
  </footer>

  <script>
    document.getElementById('current-year').textContent = new Date().getFullYear();
  </script>
  <script src="navigation.js"></script>
</body>

</html>