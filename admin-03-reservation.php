<?php
include 'authentication-admin.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

function get_reservations_by_role($conn, $role)
{
    $sql = "SELECT r.RID, r.reservation_date, b.title AS book_title, u.first_name, u.last_name, b.bid as BID, u.UID as UID
            FROM reservations r
            JOIN books b ON r.BID = b.bid
            JOIN users u ON r.UID = u.UID
            WHERE u.user_role = ?
            ORDER BY r.reservation_date ASC";

    $query_result = execute_query($conn, $sql, "s", [$role]);
    return $query_result ? $query_result['result'] : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['RID'])) {
    $action = $_POST['action'];
    $rid = (int)$_POST['RID'];

    if ($rid <= 0) {
        set_js_alert('warning', 'Invalid Reservation ID.');
        header("Location: admin-03-reservation.php");
        exit();
    }

    if (!$conn || !is_object($conn) || !method_exists($conn, 'begin_transaction')) {
        error_log("Database connection not available to start transaction.");
        set_js_alert('error', 'Database connection error. Cannot perform action.');
        header("Location: admin-03-reservation.php");
        exit();
    }
    $conn->begin_transaction();

    try {
        $get_reservation_sql = "SELECT UID, BID FROM reservations WHERE RID = ?";
        $reservation_query_result = execute_query($conn, $get_reservation_sql, "i", [$rid]);

        if (!$reservation_query_result || mysqli_num_rows($reservation_query_result['result']) == 0) {
            mysqli_rollback($conn);
            set_js_alert('warning', 'Reservation not found.');
            header("Location: admin-03-reservation.php");
            exit();
        }

        $reservation_details = mysqli_fetch_assoc($reservation_query_result['result']);
        mysqli_free_result($reservation_query_result['result']);
        mysqli_stmt_close($reservation_query_result['stmt']);

        $uid = $reservation_details['UID'];
        $bid = $reservation_details['BID'];

        $check_book_status_sql = "SELECT status FROM books WHERE bid = ?";
        $book_status_query_result = execute_query($conn, $check_book_status_sql, "i", [$bid]);

        if (!$book_status_query_result || mysqli_num_rows($book_status_query_result['result']) == 0) {
            mysqli_rollback($conn);
            set_js_alert('error', 'Associated book not found.');
            header("Location: admin-03-reservation.php");
            exit();
        }

        $book_status_row = mysqli_fetch_assoc($book_status_query_result['result']);
        mysqli_free_result($book_status_query_result['result']);
        mysqli_stmt_close($book_status_query_result['stmt']);
        $current_book_status = $book_status_row['status'];

        if ($action === 'handover') {
            if ($current_book_status === 'Reserved') {
                $borrow_date = date('Y-m-d');
                $due_date = date('Y-m-d', strtotime($borrow_date . ' + 14 days'));

                $insert_transaction_sql = "INSERT INTO transaction (UID, BID, borrow_date, due_date, transaction_status) VALUES (?, ?, ?, ?, 'Borrowed')";
                $insert_transaction_result = execute_query($conn, $insert_transaction_sql, "iiss", [$uid, $bid, $borrow_date, $due_date]);
                if (!$insert_transaction_result) throw new Exception("Failed to insert transaction.");
                mysqli_stmt_close($insert_transaction_result['stmt']);

                $update_book_sql = "UPDATE books SET status = 'Borrowed' WHERE bid = ?";
                $update_book_result = execute_query($conn, $update_book_sql, "i", [$bid]);
                if (!$update_book_result) throw new Exception("Failed to update book status.");
                mysqli_stmt_close($update_book_result['stmt']);

                $delete_reservation_sql = "DELETE FROM reservations WHERE RID = ?";
                $delete_reservation_result = execute_query($conn, $delete_reservation_sql, "i", [$rid]);
                if (!$delete_reservation_result) throw new Exception("Failed to delete reservation.");
                mysqli_stmt_close($delete_reservation_result['stmt']);

                $conn->commit();
                set_js_alert('success', 'Reservation successfully fulfilled (Handovered).');
            } else {
                $conn->rollback();
                set_js_alert('warning', 'Cannot handover: The book is no longer reserved or its status has changed.');
            }
        } elseif ($action === 'cancel') {
            $delete_reservation_sql = "DELETE FROM reservations WHERE RID = ?";
            $delete_reservation_result = execute_query($conn, $delete_reservation_sql, "i", [$rid]);
            if (!$delete_reservation_result) throw new Exception("Failed to delete reservation.");
            mysqli_stmt_close($delete_reservation_result['stmt']);

            if ($current_book_status === 'Reserved') {
                $update_book_sql = "UPDATE books SET status = 'Available' WHERE bid = ?";
                $update_book_result = execute_query($conn, $update_book_sql, "i", [$bid]);
                if ($update_book_result) {
                    mysqli_stmt_close($update_book_result['stmt']);
                } else {
                    error_log("Warning: Failed to set book status to Available after cancellation for BID: " . $bid);
                }
            }

            $conn->commit();
            set_js_alert('success', 'Reservation successfully cancelled.');
        } else {
            $conn->rollback();
            set_js_alert('warning', 'Invalid action requested.');
        }
    } catch (Exception $e) {
        if ($conn && is_object($conn) && method_exists($conn, 'rollback') && $conn->in_transaction) {
            $conn->rollback();
        }
        error_log("Transaction failed: " . $e->getMessage());
        set_js_alert('error', 'An error occurred during the reservation action. Please try again.');
    }

    header("Location: admin-03-reservation.php");
    exit();
}

$reservations_by_role = [
    'Guest' => get_reservations_by_role($conn, 'Guest'),
    'Student' => get_reservations_by_role($conn, 'Student'),
    'Faculty' => get_reservations_by_role($conn, 'Faculty'),
];

$fetch_error = false;
if ($reservations_by_role['Guest'] === false || $reservations_by_role['Student'] === false || $reservations_by_role['Faculty'] === false) {
    $fetch_error = true;
    set_js_alert('error', 'Error loading reservations.');
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
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/general.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Admin - Reservations</title>
</head>

<body>
    <nav>
        <div class="nav-logo">
            <img src="images/logo.png" alt="site-logo" />
            <h1>Lumawig</h1>
        </div>

        <div class="nav-links">
            <a href="admin-01-home.php" class="nav-btn" aria-label="Home">
                <i class="fas fa-home"></i>
                <span class="visually-hidden">Home</span>
            </a>
            <a href="admin-02-books.php" class="nav-btn" aria-label="Manage Books">
                <i class="fas fa-book"></i>
                <span class="visually-hidden">Manage Books</span>
            </a>
            <a href="admin-03-reservation.php" class="nav-btn active" aria-label="Reservation">
                <i class="fas fa-clipboard-list"></i>
                <span class="visually-hidden">Reservation</span>
            </a>
            <a href="admin-04-history.php" class="nav-btn" aria-label="History">
                <i class="fas fa-history"></i>
                <span class="visually-hidden">History</span>
            </a>
            <a href="admin-05-users.php" class="nav-btn" aria-label="Users">
                <i class="fas fa-users"></i>
                <span class="visually-hidden">Users</span>
            </a>
            <a href="logout.php" class="nav-btn logout-btn" aria-label="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="visually-hidden">Logout</span>
            </a>
        </div>

        <button class="hamburger-icon" aria-label="Open menu">☰</button>

        <div class="sidebar">
            <div class="nav-links">
                <a href="admin-01-home.php" class="nav-btn" aria-label="Home"><i class="fas fa-home"></i></a>
                <a href="admin-02-books.php" class="nav-btn" aria-label="Manage Books"><i class="fas fa-book"></i></a>
                <a href="admin-03-reservation.php" class="nav-btn active" aria-label="Reservation"><i class="fas fa-clipboard-list"></i></a>
                <a href="admin-04-history.php" class="nav-btn" aria-label="History"><i class="fas fa-history"></i></a>
                <a href="admin-05-users.php" class="nav-btn" aria-label="Users"><i class="fas fa-users"></i></a>
                <a href="logout.php" class="nav-btn logout-btn" aria-label="Logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <header>
        <div>
            <h1>Book Reservations</h1>
        </div>
    </header>

    <main>
        <?php if ($fetch_error): ?>
            <div class="message error">Error loading reservations. Please try refreshing.</div>
        <?php endif; ?>

        <?php foreach ($reservations_by_role as $role => $result): if ($result === false) continue; ?>
            <section>
                <h2><?= htmlspecialchars($role); ?> User Reservations</h2>
                <section class="user-card">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Reservation ID</th>
                                    <th>Book Title</th>
                                    <th>User Name</th>
                                    <th>Reservation Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td data-label="RID"><?= htmlspecialchars($row['RID']); ?></td>
                                        <td data-label="Title"><?= htmlspecialchars($row['book_title']); ?></td>
                                        <td data-label="User"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td data-label="Date Reserved"><?= htmlspecialchars($row['reservation_date']); ?></td>
                                        <td class="actions-cell">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Handover this book?');">
                                                <input type="hidden" name="RID" value="<?= $row['RID']; ?>">
                                                <input type="hidden" name="action" value="handover">
                                                <button type="submit">Handover</button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this reservation?');">
                                                <input type="hidden" name="RID" value="<?= $row['RID']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit">Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center;">No reservations from <?= htmlspecialchars($role) . 's'; ?>.</p>
                    <?php endif; ?>
                </section>
            </section>
        <?php mysqli_free_result($result);
        endforeach; ?>
    </main>

    <footer>
        <p>&copy; <span id="current-year"></span> All rights reserved.</p>
    </footer>

    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>
    <script src="navigation.js"></script>

    <?php if (isset($_SESSION['js_alert'])): $alert_data = $_SESSION['js_alert'];
        unset($_SESSION['js_alert']); ?>
        <script>
            alert("<?= htmlspecialchars($alert_data['message'], ENT_QUOTES, 'UTF-8') ?>");
        </script>
    <?php endif; ?>
</body>

</html>