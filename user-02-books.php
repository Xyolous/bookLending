<?php

include 'authentication-user.php';

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['UID'])) {
  header("Location: login.php");
  exit();
}

$logged_in_user_id = $_SESSION['UID'];

function set_js_alert($type, $message)
{
  $_SESSION['js_alert'] = ['type' => $type, 'message' => $message];
}

function execute_query($conn, $sql, $types = '', $params = [])
{
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    error_log("Query Prepare failed: " . mysqli_error($conn) . " SQL: " . $sql);
    return false;
  }

  if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
  }

  $success = mysqli_stmt_execute($stmt);
  if (!$success) {
    error_log("Query Execute failed: " . mysqli_stmt_error($stmt) . " SQL: " . $sql);
    mysqli_stmt_close($stmt);
    return false;
  }

  $result = mysqli_stmt_get_result($stmt);
  return ['stmt' => $stmt, 'result' => $result];
}

function handle_reservation_actions($conn, $user_id)
{
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['book_id'])) {
    $action = $_POST['action'];
    $book_id = (int)$_POST['book_id'];

    if ($book_id <= 0) {
      set_js_alert('warning', 'Invalid book ID.');
      header("Location: user-02-books.php");
      exit();
    }

    if (!$conn || !is_object($conn) || !method_exists($conn, 'begin_transaction')) {
      error_log("Database connection not available to start transaction.");
      set_js_alert('error', 'Database connection error. Cannot perform action.');
      header("Location: user-02-books.php");
      exit();
    }
    $conn->begin_transaction();

    try {
      if ($action === 'reserve') {
        $status_check_result = execute_query($conn, "SELECT status FROM books WHERE bid = ?", "i", [$book_id]);

        if (!$status_check_result || mysqli_num_rows($status_check_result['result']) == 0) {
          mysqli_rollback($conn);
          set_js_alert('warning', 'Book not found.');
          header("Location: user-02-books.php");
          exit();
        }

        $status = mysqli_fetch_assoc($status_check_result['result'])['status'];
        mysqli_free_result($status_check_result['result']);
        mysqli_stmt_close($status_check_result['stmt']);

        if ($status === 'Available') {
          $update_book_result = execute_query($conn, "UPDATE books SET status = 'Reserved' WHERE bid = ?", "i", [$book_id]);
          if (!$update_book_result) throw new Exception("Failed to update book status.");
          mysqli_stmt_close($update_book_result['stmt']);

          $insert_reservation_result = execute_query($conn, "INSERT INTO reservations (UID, BID, reservation_date) VALUES (?, ?, NOW())", "ii", [$user_id, $book_id]);
          if (!$insert_reservation_result) throw new Exception("Failed to create reservation record.");
          mysqli_stmt_close($insert_reservation_result['stmt']);

          $conn->commit();
          set_js_alert('success', 'Book reserved successfully!');
        } else {
          mysqli_rollback($conn);
          set_js_alert('warning', 'Book is not available for reservation.');
        }
      } elseif ($action === 'cancel') {
        $reservation_check_result = execute_query($conn, "SELECT RID, BID FROM reservations WHERE UID = ? AND BID = ?", "ii", [$user_id, $book_id]);

        if (!$reservation_check_result || mysqli_num_rows($reservation_check_result['result']) == 0) {
          mysqli_rollback($conn);
          set_js_alert('warning', 'Reservation not found for this book and user.');
          header("Location: user-02-books.php");
          exit();
        }

        $reservation_details = mysqli_fetch_assoc($reservation_check_result['result']);
        $reserved_book_id = $reservation_details['BID']; // Get BID from the reservation record
        mysqli_free_result($reservation_check_result['result']);
        mysqli_stmt_close($reservation_check_result['stmt']);

        // Update book status only if it's currently marked as Reserved (safety check)
        $book_status_result = execute_query($conn, "SELECT status FROM books WHERE bid = ?", "i", [$reserved_book_id]);
        if ($book_status_result && mysqli_num_rows($book_status_result['result']) > 0) {
          $current_book_status = mysqli_fetch_assoc($book_status_result['result'])['status'];
          mysqli_free_result($book_status_result['result']);
          mysqli_stmt_close($book_status_result['stmt']);

          if ($current_book_status === 'Reserved') {
            $update_book_result = execute_query($conn, "UPDATE books SET status = 'Available' WHERE bid = ?", "i", [$reserved_book_id]);
            if (!$update_book_result) {
              error_log("Warning: Failed to set book status to Available after cancellation for BID: " . $reserved_book_id);
              // Don't throw exception here, main action is deletion
            } else {
              mysqli_stmt_close($update_book_result['stmt']);
            }
          }
        }


        $delete_reservation_result = execute_query($conn, "DELETE FROM reservations WHERE UID = ? AND BID = ?", "ii", [$user_id, $book_id]);
        if (!$delete_reservation_result) throw new Exception("Failed to delete reservation record.");
        mysqli_stmt_close($delete_reservation_result['stmt']);


        $conn->commit();
        set_js_alert('success', 'Reservation cancelled successfully!');
      } else {
        mysqli_rollback($conn);
        set_js_alert('warning', 'Invalid action requested.');
      }
    } catch (Exception $e) {
      if ($conn && is_object($conn) && method_exists($conn, 'rollback') && $conn->in_transaction) {
        $conn->rollback();
      }
      error_log("Reservation action failed: " . $e->getMessage());
      set_js_alert('error', 'An error occurred during the reservation action. Please try again.');
    }
    header("Location: user-02-books.php");
    exit();
  }
}

handle_reservation_actions($conn, $logged_in_user_id);

$search = trim($_GET['search'] ?? '');
$main_type = $_GET['main_type'] ?? '';
$specific_type = $_GET['specific_type'] ?? '';
$status = $_GET['status'] ?? '';

$where = ["1"];
$params = [$logged_in_user_id];
$types = "i";

if (!empty($search)) {
  $where[] = "(title LIKE ? OR author LIKE ?)";
  $params[] = "%" . $search . "%";
  $params[] = "%" . $search . "%";
  $types .= "ss";
}
if (!empty($main_type)) {
  $where[] = "main_type = ?";
  $params[] = $main_type;
  $types .= "s";
}
if (!empty($specific_type)) {
  $where[] = "specific_type = ?";
  $params[] = $specific_type;
  $types .= "s";
}
if (!empty($status)) {
  $where[] = "status = ?";
  $params[] = $status;
  $types .= "s";
} else {
  $where[] = "b.status != 'Borrowed'"; // Exclude borrowed books by default
}

$where_sql = implode(" AND ", $where);
$sql = "
    SELECT b.*, r.RID IS NOT NULL AS is_reserved_by_user
    FROM books b
    LEFT JOIN reservations r ON b.bid = r.BID AND r.UID = ?
    WHERE $where_sql
    ORDER BY b.title ASC
";

$books = false;
$fetch_books_error = null;

try {
  if ($conn && is_object($conn)) {
    $query_result = execute_query($conn, $sql, $types, $params);
    if ($query_result) {
      $books = $query_result['result'];
      mysqli_stmt_close($query_result['stmt']); // Close the statement after getting result
    } else {
      $fetch_books_error = "Error loading books.";
    }
  } else {
    $fetch_books_error = "Database connection not available to load books.";
    error_log($fetch_books_error);
  }
} catch (Exception $e) {
  error_log("General error fetching books: " . $e->getMessage());
  $fetch_books_error = "General error loading books.";
}


$specific_types = [];
$fetch_types_error = null;
$type_query = "SELECT DISTINCT specific_type FROM books WHERE specific_type IS NOT NULL AND specific_type != '' ORDER BY specific_type"; // Added check for not empty/null

try {
  if ($conn && is_object($conn) && $conn->ping()) {
    $type_result = mysqli_query($conn, $type_query);
    if ($type_result) {
      while ($row = mysqli_fetch_assoc($type_result)) {
        $specific_types[] = $row['specific_type'];
      }
      mysqli_free_result($type_result);
    } else {
      $fetch_types_error = "Error loading specific types.";
      error_log("Error fetching specific types: " . mysqli_error($conn));
    }
  } else {
    $fetch_types_error = "Database connection not available to load specific types.";
    error_log($fetch_types_error);
  }
} catch (Exception $e) {
  error_log("General error fetching specific types: " . $e->getMessage());
  $fetch_types_error = "General error loading specific types.";
}

if ($fetch_books_error || $fetch_types_error) {
  // Prioritize books error message if both fail
  $error_message = $fetch_books_error ?? $fetch_types_error;
  set_js_alert('error', $error_message);
}


if ($conn && is_object($conn) && method_exists($conn, 'close') && $conn->ping()) {
  $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User - Book Catalog</title>
  <link rel="stylesheet" href="css/nav.css">
  <link rel="stylesheet" href="css/general.css">
  <link rel="stylesheet" href="css/user.css">
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
    <h1>Book Catalog</h1>
  </header>

  <main>
    <section>
      <form method="GET" class="filter-form">
        <input type="text" name="search" placeholder="Search book..." value="<?= htmlspecialchars($search) ?>">
        <select name="main_type">
          <option value="">Main Type</option>
          <option value="Academic" <?= $main_type === 'Academic' ? 'selected' : '' ?>>Academic</option>
          <option value="Non-academic" <?= $main_type === 'Non-academic' ? 'selected' : '' ?>>Non-Academic</option>
        </select>
        <select name="specific_type">
          <option value="">Specific Type</option>
          <?php foreach ($specific_types as $type): ?>
            <option value="<?= htmlspecialchars($type) ?>" <?= $specific_type === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status">
          <option value="">Status</option>
          <option value="Available" <?= $status === 'Available' ? 'selected' : '' ?>>Available</option>
          <option value="Reserved" <?= $status === 'Reserved' ? 'selected' : '' ?>>Reserved</option>
          <option value="Borrowed" <?= $status === 'Borrowed' ? 'selected' : '' ?>>Borrowed</option>
        </select>
        <button type="submit">Filter</button>
        <button type="button" onclick="window.location.href='user-02-books.php'">Reset</button>
      </form>
    </section>

    <section>
      <?php if ($books && mysqli_num_rows($books) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Author</th>
              <th>Date Published</th>
              <th>Main Type</th>
              <th>Specific Type</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($b = mysqli_fetch_assoc($books)): ?>
              <tr>
                <td data-label="Title"><?= htmlspecialchars($b['title']) ?></td>
                <td data-label="Author"><?= htmlspecialchars($b['author']) ?></td>
                <td data-label="Date Published"><?= htmlspecialchars($b['date_published']) ?></td>
                <td data-label="Main Type"><?= htmlspecialchars($b['main_type']) ?></td>
                <td data-label="Specific Type"><?= htmlspecialchars($b['specific_type']) ?></td>
                <td data-label="Status" class="status-<?= strtolower($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></td>
                <td data-label="" class="actions-cell">
                  <?php if ($b['status'] === 'Available'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Reserve the book?');">
                      <input type="hidden" name="book_id" value="<?= $b['bid'] ?>">
                      <input type="hidden" name="action" value="reserve">
                      <button type="submit">Reserve</button>
                    </form>
                  <?php elseif ($b['status'] === 'Reserved' && $b['is_reserved_by_user']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel reservation?');">
                      <input type="hidden" name="book_id" value="<?= $b['bid'] ?>">
                      <input type="hidden" name="action" value="cancel">
                      <button type="submit">Cancel</button>
                    </form>
                  <?php elseif ($b['status'] === 'Reserved'): ?>
                    <span>Reserved by others</span>
                  <?php else: ?>
                    <span>No Action</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php elseif ($books !== false): ?>
        <p style="text-align:center;">No books found matching your criteria.</p>
      <?php endif; ?>
    </section>
  </main>

  <footer>
    <p>&copy; <span id="current-year"></span> Library mo 'to. All rights reserved.</p>
  </footer>

  <script>
    document.getElementById("current-year").textContent = new Date().getFullYear();
  </script>
  <script src="navigation.js"></script>

  <?php
  if (isset($_SESSION['js_alert'])) {
    $alert_data = $_SESSION['js_alert'];
    unset($_SESSION['js_alert']);
  ?>
    <script>
      const alertMessage = "<?= htmlspecialchars($alert_data['message'], ENT_QUOTES, 'UTF-8') ?>";
      alert(alertMessage);
    </script>
  <?php
  }
  ?>

</body>

</html>
<?php
// Free the result set for the main books query after use
if ($books && is_object($books) && method_exists($books, 'free')) {
  mysqli_free_result($books);
}
?>