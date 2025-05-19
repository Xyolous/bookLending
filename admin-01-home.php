<?php
include 'authentication-admin.php';

function fetchCount($conn, $sql, $column)
{
    $result = $conn->query($sql);
    $count = 0; // Default value

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row[$column];
    }
    return (int)$count;
}

function fetchActivityList($conn, $sql)
{
    $result = $conn->query($sql);
    $list = []; // Default empty array

    if ($result) { // Check if query was successful
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

// Total Books
$sql_total_books = "SELECT COUNT(*) AS total_books FROM books";
$total_books = fetchCount($conn, $sql_total_books, 'total_books');

// Total Registered Users (excluding admin based on user_type = 'User')
$sql_total_registered_users = "SELECT COUNT(*) AS total_users FROM users WHERE user_type = 'User'";
$total_registered_users = fetchCount($conn, $sql_total_registered_users, 'total_users');

// Total Guest Users (based on user_role = 'Guest') - Assuming user_role exists in users table
$sql_guest_users = "SELECT COUNT(*) AS guest_count FROM users WHERE user_role = 'Guest'";
$guest_count = fetchCount($conn, $sql_guest_users, 'guest_count');

// Currently Borrowed Books (from transaction table)
$sql_leased_books = "SELECT COUNT(*) AS leased_books FROM transaction WHERE transaction_status = 'Borrowed'";
$leased_books = fetchCount($conn, $sql_leased_books, 'leased_books');

// Overdue Books (Count from transaction table)
$sql_overdue_books = "SELECT COUNT(*) AS overdue_books FROM transaction WHERE transaction_status = 'Borrowed' AND due_date < CURDATE()";
$overdue_books = fetchCount($conn, $sql_overdue_books, 'overdue_books');

// Reserved Books (Count from the separate 'reservations' table)
$sql_reserved_books = "SELECT COUNT(*) AS reserved_books FROM reservations";
$reserved_books = fetchCount($conn, $sql_reserved_books, 'reserved_books');

$sql_recent_borrows = "SELECT
                            b.title,
                            u.first_name,
                            u.last_name,
                            t.borrow_date
                        FROM
                            transaction t
                        JOIN
                            books b ON t.BID = b.bid
                        JOIN
                            users u ON t.UID = u.UID
                        WHERE
                            t.transaction_status = 'Borrowed'
                        ORDER BY
                            t.borrow_date DESC
                        LIMIT 5";
$recent_borrows = fetchActivityList($conn, $sql_recent_borrows);

$sql_recent_overdues = "SELECT
                            b.title,
                            u.first_name,
                            u.last_name,
                            t.due_date,
                            t.borrow_date
                           FROM
                            transaction t
                           JOIN
                            books b ON t.BID = b.bid
                           JOIN
                            users u ON t.UID = u.UID
                           WHERE
                             t.transaction_status = 'Borrowed' AND t.due_date < CURDATE()
                           ORDER BY
                             t.borrow_date DESC -- Or t.due_date ASC if you want oldest overdues first
                           LIMIT 5";
$recent_overdues = fetchActivityList($conn, $sql_recent_overdues);

$sql_recent_returns = "SELECT
                            b.title,
                            u.first_name,
                            u.last_name,
                            t.return_date
                           FROM
                            transaction t
                           JOIN
                            books b ON t.BID = b.bid
                           JOIN
                            users u ON t.UID = u.UID
                           WHERE
                            t.transaction_status = 'Returned' AND t.return_date IS NOT NULL
                           ORDER BY
                            t.return_date DESC
                           LIMIT 5";
$recent_returns = fetchActivityList($conn, $sql_recent_returns);

$sql_recent_logins = "SELECT
                            first_name,
                            last_name,
                            date_last_login AS login_timestamp
                        FROM
                            users
                        WHERE
                            date_last_login IS NOT NULL
                        ORDER BY
                            date_last_login DESC
                        LIMIT 5";
$recent_logins = fetchActivityList($conn, $sql_recent_logins);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/general.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Admin - Home</title>
</head>

<body>
    <nav>
        <div class="nav-logo">
            <img src="" alt="site-logo" />
            <h1>Library mo 'to</h1>
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
        <div>
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] . " " . $_SESSION['last_name']); ?>!</h1>
        </div>
    </header>

    <main>
        <section class="library-details">
            <h1>Library Overview</h1>
            <ul>
                <li>Total number of books: <strong><?php echo $total_books; ?></strong></li>
                <li>Total registered users: <strong><?php echo $total_registered_users; ?></strong></li>
                <li>Total guest users: <strong><?php echo $guest_count; ?></strong></li>
                <li>Currently borrowed books: <strong><?php echo $leased_books; ?></strong></li>
                <li>Overdue books: <strong><?php echo $overdue_books; ?></strong></li>
                <li>Reserved books: <strong><?php echo $reserved_books; ?></strong></li>
            </ul>
        </section>

        <section>
            <h1>Recent Activity Feed</h1>

            <div class="recent-activities-grid">
                <section class="activity-item">
                    <h2>Recent Borrows</h2>
                    <ul>
                        <?php if (count($recent_borrows) > 0): ?>
                            <?php foreach ($recent_borrows as $activity): ?>
                                <li>
                                    <h3>
                                        <?php echo htmlspecialchars($activity['title']); ?> | issued to <?php echo htmlspecialchars($activity['first_name'] . " " . $activity['last_name']); ?>
                                    </h3>
                                    <p>
                                        Borrowed date: <?php echo htmlspecialchars(date('M d, Y', strtotime($activity['borrow_date']))); ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>
                                <h3>No recent borrow activity.</h3>
                            </li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="activity-item">
                    <h2>Recently Overdue</h2>
                    <ul>
                        <?php if (count($recent_overdues) > 0): ?>
                            <?php foreach ($recent_overdues as $activity): ?>
                                <li>
                                    <h3>
                                        <?php echo htmlspecialchars($activity['title']); ?> | issued to <?php echo htmlspecialchars($activity['first_name'] . " " . $activity['last_name']); ?>
                                    </h3>
                                    <p>
                                        Due date: <strong style="color: red;"><?php echo htmlspecialchars(date('M d, Y', strtotime($activity['due_date']))); ?></strong>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>
                                <h3>No books currently overdue from recent borrows.</h3>
                            </li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="activity-item">
                    <h2>Recent Returns</h2>
                    <ul>
                        <?php if (count($recent_returns) > 0): ?>
                            <?php foreach ($recent_returns as $activity): ?>
                                <li>
                                    <h3>
                                        <?php echo htmlspecialchars($activity['title']); ?> | returned by <?php echo htmlspecialchars($activity['first_name'] . " " . $activity['last_name']); ?>
                                    </h3>
                                    <p>
                                        Returned date: <?php echo htmlspecialchars(date('M d, Y', strtotime($activity['return_date']))); ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>
                                <h3>No recent return activity.</h3>
                            </li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="activity-item">
                    <h2>Recent User Logins</h2>
                    <ul>
                        <?php if (count($recent_logins) > 0): ?>
                            <?php foreach ($recent_logins as $activity): ?>
                                <li>
                                    <h3>
                                        <?php echo htmlspecialchars($activity['first_name'] . " " . $activity['last_name']); ?>
                                    </h3>
                                    <p>
                                        Logged in on <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($activity['login_timestamp']))); ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>
                                <h3>No recent user login activity.</h3>
                            </li>
                        <?php endif; ?>
                    </ul>
                </section>

            </div>
        </section>

    </main>

    <footer>
        <p>
            &copy;
            <span id="current-year"></span>
            All rights reserved.
        </p>
    </footer>

    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>
    <script src="navigation.js"></script>
</body>

</html>