<?php

include 'authentication-admin.php';

function set_js_alert($type, $message)
{
    $_SESSION['js_alert'] = ['type' => $type, 'message' => $message];
}

function get_book_post_data()
{
    return [
        'title' => trim($_POST['title'] ?? ''),
        'author' => trim($_POST['author'] ?? ''),
        'date_published' => $_POST['date_published'] ?? '',
        'main_type' => $_POST['main_type'] ?? '',
        'specific_type' => $_POST['specific_type'] ?? '',
        'book_location' => $_POST['book_location'] ?? ''
    ];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$conn || !is_object($conn) || !method_exists($conn, 'prepare')) {
        set_js_alert('error', 'Database connection not available. Cannot perform action.');
        header("Location: admin-02-books.php");
        exit();
    }

    $is_delete_attempt = isset($_POST['delete_book']);
    $is_return_attempt = isset($_POST['return_book']);

    try {
        $book_data = [];
        if (isset($_POST['insert_book']) || isset($_POST['update_book'])) {
            $book_data = get_book_post_data();

            if (
                empty($book_data['title']) || empty($book_data['author']) || empty($book_data['date_published']) ||
                empty($book_data['main_type']) || empty($book_data['specific_type']) || empty($book_data['book_location'])
            ) {
                set_js_alert('warning', 'All book fields are required.');
                header("Location: admin-02-books.php");
                exit();
            }
        }

        if (isset($_POST['insert_book'])) {
            $stmt = $conn->prepare("INSERT INTO books (title, author, date_published, main_type, specific_type, book_location, date_added, date_updated) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssssss", $book_data['title'], $book_data['author'], $book_data['date_published'], $book_data['main_type'], $book_data['specific_type'], $book_data['book_location']);
            $stmt->execute();
            $stmt->close();

            set_js_alert('success', 'Book inserted successfully!');
            header("Location: admin-02-books.php");
            exit();
        } elseif (isset($_POST['update_book'])) {
            $bid = (int)($_POST['BID'] ?? 0);

            if ($bid <= 0) {
                set_js_alert('warning', 'Invalid Book ID for update.');
                header("Location: admin-02-books.php");
                exit();
            }

            $stmt = $conn->prepare("UPDATE books SET title=?, author=?, date_published=?, main_type=?, specific_type=?, book_location=?, date_updated=NOW() WHERE BID=?");
            if (!$stmt) {
                throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssssssi", $book_data['title'], $book_data['author'], $book_data['date_published'], $book_data['main_type'], $book_data['specific_type'], $book_data['book_location'], $bid);
            $stmt->execute();
            $stmt->close();

            set_js_alert('success', 'Book updated successfully!');
            header("Location: admin-02-books.php");
            exit();
        } elseif ($is_delete_attempt) {
            $bid = (int)($_POST['BID'] ?? 0);

            if ($bid <= 0) {
                set_js_alert('warning', 'Invalid Book ID for deletion.');
                header("Location: admin-02-books.php");
                exit();
            }

            $check_sql = "(SELECT 1 FROM transaction WHERE BID = ?) UNION ALL (SELECT 1 FROM reservations WHERE BID = ?) LIMIT 1";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new mysqli_sql_exception("Check Prepare failed: " . $conn->error);
            }
            $check_stmt->bind_param("ii", $bid, $bid);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $check_stmt->close();
                set_js_alert('warning', 'Cannot delete book: It is currently borrowed or reserved, or has transaction history. Please address these first.');
                header("Location: admin-02-books.php");
                exit();
            }
            $check_stmt->close();

            $stmt = $conn->prepare("DELETE FROM books WHERE BID = ?");
            if (!$stmt) {
                throw new mysqli_sql_exception("Delete Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $bid);
            $stmt->execute();
            $stmt->close();

            set_js_alert('success', 'Book deleted successfully!');
            header("Location: admin-02-books.php");
            exit();
        } elseif ($is_return_attempt) {
            $bid = (int)($_POST['BID'] ?? 0);

            if ($bid <= 0) {
                set_js_alert('warning', 'Invalid Book ID for return.');
                header("Location: admin-02-books.php");
                exit();
            }

            if (!$conn->begin_transaction()) {
                throw new mysqli_sql_exception("Beginning transaction failed: " . $conn->error);
            }

            try {
                $update_book_sql = "UPDATE books SET status = 'Available' WHERE bid = ?";
                $stmt_update_book = $conn->prepare($update_book_sql);
                if (!$stmt_update_book) {
                    throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                }
                $stmt_update_book->bind_param("i", $bid);
                $stmt_update_book->execute();
                $stmt_update_book->close();

                $return_date = date('Y-m-d');
                $update_transaction_sql = "UPDATE transaction SET return_date = ?, transaction_status = 'Returned' WHERE BID = ? AND (transaction_status = 'Borrowed' OR transaction_status = 'Overdue') ORDER BY borrow_date DESC LIMIT 1";
                $stmt_update_transaction = $conn->prepare($update_transaction_sql);
                if (!$stmt_update_transaction) {
                    throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                }
                $stmt_update_transaction->bind_param("si", $return_date, $bid);
                $stmt_update_transaction->execute();
                $stmt_update_transaction->close();

                $conn->commit();
                set_js_alert('success', 'Book returned successfully!');
                header("Location: admin-02-books.php");
                exit();
            } catch (mysqli_sql_exception $e) {
                if ($conn->in_transaction) {
                    $conn->rollback();
                }
                throw $e;
            }
        }
    } catch (mysqli_sql_exception $e) {
        if ($conn && is_object($conn) && method_exists($conn, 'rollback') && $conn->in_transaction) {
            $conn->rollback();
        }

        error_log("Database error during POST action: " . $e->getMessage());

        $user_message = "A database error occurred during the operation.";

        if ($is_delete_attempt && $e->getCode() == 1451) {
            $user_message = "Database Error: Cannot delete book because it is still referenced by other records in the system (Code 1451).";
        } else {
            $user_message = "A database error occurred: " . $e->getMessage();
        }

        set_js_alert('error', $user_message);
        header("Location: admin-02-books.php");
        exit();
    } catch (Exception $e) {
        if ($conn && is_object($conn) && method_exists($conn, 'rollback') && $conn->in_transaction) {
            $conn->rollback();
        }
        error_log("General error during POST action: " . $e->getMessage());
        set_js_alert('error', 'An unexpected server error occurred.');
        header("Location: admin-02-books.php");
        exit();
    }
}

$search = $_GET['search'] ?? '';
$main_type_filter = $_GET['main_type'] ?? '';
$specific_type_filter = $_GET['specific_type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$location_filter = $_GET['book_location'] ?? '';

$query = "SELECT * FROM books WHERE 1=1 ";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= "AND (title LIKE ? OR author LIKE ?) ";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $types .= 'ss';
}

if (!empty($main_type_filter)) {
    $query .= "AND main_type = ? ";
    $params[] = $main_type_filter;
    $types .= 's';
}

if (!empty($specific_type_filter)) {
    $query .= "AND specific_type = ? ";
    $params[] = $specific_type_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $query .= "AND status = ? ";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($location_filter)) {
    $query .= "AND book_location = ? ";
    $params[] = $location_filter;
    $types .= 's';
}

$query .= "ORDER BY BID ASC";

$books_result = false;
$fetch_error = null;

try {
    if ($conn && is_object($conn) && $conn->ping()) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $books_result = $stmt->get_result();
        $stmt->close();
    } else {
        $fetch_error = "Database connection not available to load books.";
        error_log($fetch_error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching books: " . $e->getMessage());
    $fetch_error = "Error loading books: " . $e->getMessage();
} catch (Exception $e) {
    error_log("General error fetching books: " . $e->getMessage());
    $fetch_error = "General error loading books: " . $e->getMessage();
}

$locations = [];
if ($conn && is_object($conn) && $conn->ping()) {
    $location_query = "SELECT DISTINCT book_location FROM books WHERE book_location IS NOT NULL AND book_location != '' ORDER BY book_location";
    try {
        if ($conn && is_object($conn) && $conn->ping()) {
            $location_result = $conn->query($location_query);
            if ($location_result) {
                while ($row = $location_result->fetch_assoc()) {
                    $locations[] = $row['book_location'];
                }
                $location_result->free();
            }
        } else {
            error_log("Database connection not available to fetch locations after potential close.");
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching distinct locations: " . $e->getMessage());
    }
} else {
    error_log("Database connection not available to fetch locations initially.");
}


if ($conn && is_object($conn) && method_exists($conn, 'close') && $conn->ping()) {
    try {
        $conn->query("SELECT 1");
        $conn->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Error closing database connection: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Manage Books</title>
    <link rel="stylesheet" href="css/nav.css" />
    <link rel="stylesheet" href="css/general.css" />
    <link rel="stylesheet" href="css/admin.css" />
    <link rel="stylesheet" href="css/table.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        main table td {
            white-space: normal;

            :nth-child(1),
            :nth-child(2),
            :nth-child(3) {
                width: 100%;
            }
        }
    </style>
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
            <a href="admin-02-books.php" class="nav-btn active" aria-label="Manage Books">
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
                <a href="admin-02-books.php" class="nav-btn active" aria-label="Manage Books">
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
        <h1>Manage Books</h1>
    </header>

    <main>
        <section id="insert-book">
            <h2>Insert Book</h2>
            <form method="POST" id="insert-book-form" onsubmit="return confirm('Are you sure you want to insert this book?');">
                <input type="text" name="title" placeholder="Title" required />
                <input type="text" name="author" placeholder="Author" required />
                <input type="date" name="date_published" required />
                <select name="main_type" required>
                    <option value="" selected hidden>Main Type</option>
                    <option value="Academic">Academic</option>
                    <option value="Non-academic">Non-academic</option>
                </select>
                <select name="specific_type" required>
                    <option value="" selected hidden>Specific Type</option>
                    <optgroup label="Non-Fiction">
                        <option value="Textbook">Textbook</option>
                        <option value="Reference Book">Reference Book</option>
                        <option value="Dissertation">Dissertation</option>
                        <option value="Thesis">Thesis</option>
                        <option value="Biography">Biography</option>
                        <option value="Autobiography">Autobiography</option>
                        <option value="Business Book">Business Book</option>
                    </optgroup>
                    <optgroup label="Fiction">
                        <option value="Novel">Novel</option>
                        <option value="Short Story">Short Story</option>
                        <option value="Poem Collection">Poem Collection</option>
                    </optgroup>
                </select>
                <select name="book_location" required>
                    <option value="" selected hidden>Location</option>
                    <?php foreach (['Antipolo', 'Binalonan', 'Guimba', 'North Manila', 'Quezon City'] as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="insert_book">Insert</button>
            </form>
        </section>

        <section id="filter-controls">
            <h2>Search & Filter</h2>
            <form method="GET">
                <input type="text" name="search" id="search-input" placeholder="Search by title or author" value="<?= htmlspecialchars($search) ?>">

                <select name="main_type" id="filter-main-type">
                    <option value="">All Main Types</option>
                    <option value="Academic" <?= $main_type_filter == 'Academic' ? 'selected' : '' ?>>Academic</option>
                    <option value="Non-academic" <?= $main_type_filter == 'Non-academic' ? 'selected' : '' ?>>Non-academic</option>
                </select>

                <select name="specific_type" id="filter-specific-type">
                    <option value="">All Specific Types</option>
                    <optgroup label="Non-Fiction">
                        <option value="Textbook" <?= $specific_type_filter == 'Textbook' ? 'selected' : '' ?>>Textbook</option>
                        <option value="Reference Book" <?= $specific_type_filter == 'Reference Book' ? 'selected' : '' ?>>Reference Book</option>
                        <option value="Dissertation" <?= $specific_type_filter == 'Dissertation' ? 'selected' : '' ?>>Dissertation</option>
                        <option value="Thesis" <?= $specific_type_filter == 'Thesis' ? 'selected' : '' ?>>Thesis</option>
                        <option value="Biography" <?= $specific_type_filter == 'Biography' ? 'selected' : '' ?>>Biography</option>
                        <option value="Autobiography" <?= $specific_type_filter == 'Autobiography' ? 'selected' : '' ?>>Autobiography</option>
                        <option value="Business Book" <?= $specific_type_filter == 'Business Book' ? 'selected' : '' ?>>Business Book</option>
                    </optgroup>
                    <optgroup label="Fiction">
                        <option value="Novel" <?= $specific_type_filter == 'Novel' ? 'selected' : '' ?>>Novel</option>
                        <option value="Short Story" <?= $specific_type_filter == 'Short Story' ? 'selected' : '' ?>>Short Story</option>
                        <option value="Poem Collection" <?= $specific_type_filter == 'Poem Collection' ? 'selected' : '' ?>>Poem Collection</option>
                    </optgroup>
                </select>

                <select name="status" id="filter-status">
                    <option value="">All Status</option>
                    <option value="Available" <?= $status_filter == 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Reserved" <?= $status_filter == 'Reserved' ? 'selected' : '' ?>>Reserved</option>
                    <option value="Borrowed" <?= $status_filter == 'Borrowed' ? 'selected' : '' ?>>Borrowed</option>
                </select>

                <select name="book_location" id="filter-location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" <?= $location_filter == $loc ? 'selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Filter</button>
                <button type="button" onclick="window.location.href='admin-02-books.php'">Reset</button>
            </form>
        </section>

        <section id="table-control">
            <h2>Manage Books</h2>
            <table class="manage-table">
                <thead>
                    <tr>
                        <th>BID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Published</th>
                        <th>Main Type</th>
                        <th>Specific Type</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Date Added</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    if ($books_result && $books_result->num_rows > 0):
                        while ($row = $books_result->fetch_assoc()) :
                    ?>
                            <tr>
                                <td data-label="BID"><?= htmlspecialchars($row['bid']) ?></td>
                                <td data-label="Title" class="book-title"><?= htmlspecialchars($row['title']) ?></td>
                                <td data-label="Author" class="book-author"><?= htmlspecialchars($row['author']) ?></td>
                                <td data-label="Date Published"><?= htmlspecialchars($row['date_published']) ?></td>
                                <td data-label="Main Type" class="book-main"><?= htmlspecialchars($row['main_type']) ?></td>
                                <td data-label="Specific Type" class="book-specific"><?= htmlspecialchars($row['specific_type']) ?></td>
                                <td data-label="Status" class="book-status"><?= htmlspecialchars($row['status']) ?></td>
                                <td data-label="Location" class="book-location"><?= htmlspecialchars($row['book_location']) ?></td>
                                <td data-label="Added"><?= htmlspecialchars($row['date_added']) ?></td>
                                <td data-label="Updated"><?= htmlspecialchars($row['date_updated']) ?></td>
                                <td class="actions-cell">
                                    <button onclick='openModal(<?= json_encode($row) ?>)' type="button">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                        <input type="hidden" name="BID" value="<?= htmlspecialchars($row['bid']) ?>">
                                        <button type="submit" name="delete_book">Delete</button>
                                    </form>
                                    <?php
                                    $is_returnable = ($row['status'] === 'Borrowed' || $row['status'] === 'Overdue');
                                    $disabled_attr = $is_returnable ? '' : 'disabled';
                                    ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Confirm book return?');">
                                        <input type="hidden" name="BID" value="<?= htmlspecialchars($row['bid']) ?>">
                                        <button type="submit" name="return_book" class="return-button" <?= $disabled_attr ?>>Returned</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile;
                    elseif (isset($fetch_error)):
                        ?>
                        <tr>
                            <td colspan="11" style="text-align: center; color: red;"><?= htmlspecialchars($fetch_error) ?></td>
                        </tr>
                    <?php
                    else:
                    ?>
                        <tr>
                            <td colspan="11" style="text-align: center;">No books found matching your criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal-container">
        <div id="edit-user-modal" class="modal">
            <div class="modal-content">
                <h3>Edit Book
                    <span class="close-button">&times;</span>
                </h3>
                <form id="edit-book-form" method="POST" class="form-modal" onsubmit="return confirm('Are you sure you want to save changes to this book?');">
                    <input type="hidden" name="BID" id="modal-bid">
                    <input type="hidden" name="update_book" value="1">
                    <div class="modal-details">
                        <label>Title: <input type="text" name="title" id="modal-title" required></label>
                        <label>Author: <input type="text" name="author" id="modal-author" required></label>
                        <label>Date Published: <input type="date" name="date_published" id="modal-date-published" required></label>
                        <label>Main Type:
                            <select name="main_type" id="modal-main-type" required>
                                <option value="Academic">Academic</option>
                                <option value="Non-academic">Non-academic</option>
                            </select>
                        </label>
                        <label>Specific Type:
                            <select name="specific_type" id="modal-specific-type" required>
                                <optgroup label="Non-Fiction">
                                    <option value="Textbook">Textbook</option>
                                    <option value="Reference Book">Reference Book</option>
                                    <option value="Dissertation">Dissertation</option>
                                    <option value="Thesis">Thesis</option>
                                    <option value="Biography">Biography</option>
                                    <option value="Autobiography">Autobiography</option>
                                    <option value="Business Book">Business Book</option>
                                </optgroup>
                                <optgroup label="Fiction">
                                    <option value="Novel">Novel</option>
                                    <option value="Short Story">Short Story</option>
                                    <option value="Poem Collection">Poem Collection</option>
                                </optgroup>
                            </select>
                        </label>
                        <label>Location:
                            <select name="book_location" id="modal-book-location" required>
                                <option value="Antipolo">Antipolo</option>
                                <option value="Binalonan">Binalonan</option>
                                <option value="Guimba">Guimba</option>
                                <option value="North Manila">North Manila</option>
                                <option value="Quezon City">Quezon City</option>
                            </select>
                        </label>
                    </div>
                    <div class="modal-buttons">
                        <button type="submit">Save</button>
                        <button type="button" class="cancel-button">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <span id="current-year"></span> All rights reserved.</p>
    </footer>

    <script>
        const modal = document.getElementById("edit-user-modal");
        const span = modal.querySelector(".close-button");
        const cancelButton = modal.querySelector(".cancel-button");
        const modalContainer = document.querySelector(".modal-container");

        function openModal(bookData) {
            document.getElementById("modal-bid").value = bookData.bid;
            document.getElementById("modal-title").value = bookData.title;
            document.getElementById("modal-author").value = bookData.author;
            document.getElementById("modal-date-published").value = bookData.date_published;
            document.getElementById("modal-main-type").value = bookData.main_type;
            document.getElementById("modal-specific-type").value = bookData.specific_type;
            document.getElementById("modal-book-location").value = bookData.book_location;

            modal.classList.add("show");
            modalContainer.classList.add("show");
        }

        function closeModal() {
            modal.classList.remove("show");
            modalContainer.classList.remove("show");
        }

        span.onclick = closeModal;
        cancelButton.onclick = closeModal;

        window.onclick = function(event) {
            if (event.target === modalContainer) {
                closeModal();
            }
        }

        document.getElementById('current-year').textContent = new Date().getFullYear();
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