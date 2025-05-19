<?php

include 'authentication-admin.php'; // Assuming this file handles database connection ($conn) and admin authentication

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to set a JavaScript alert message in the session
function set_js_alert($type, $message)
{
    $_SESSION['js_alert'] = ['type' => $type, 'message' => $message];
}

// --- Handle POST Requests (Update and Delete) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Handle user update
    if (isset($_POST["update_user"])) {
        $required_fields = ["uid", "first_name", "last_name", "user_role", "email"];
        $missing_fields = false;
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') { // Also check for empty strings after trimming
                $missing_fields = true;
                break;
            }
        }

        if (!$missing_fields) {
            $uid = $_POST["uid"];
            $first_name = trim($_POST["first_name"]);
            $last_name = trim($_POST["last_name"]);
            $user_role = $_POST["user_role"]; // Role is selected from dropdown, no trim needed
            $email = trim($_POST["email"]);
            $school_id = trim($_POST["school_id"] ?? '');
            $campus = $_POST["campus"] ?? ''; // Campus is selected from dropdown, no trim needed
            $course = $_POST["course"] ?? ''; // Course is selected from dropdown, no trim needed

            try {
                // Determine user_type based on user_role
                $user_type = ($user_role === 'Admin') ? 'Admin' : 'User';

                // Prepare the update query
                $query = "UPDATE users SET first_name=?, last_name=?, user_type=?, user_role=?, school_id=?, campus=?, course=?, email=? WHERE UID=?";
                $stmt = $conn->prepare($query);
                // Bind parameters: ssssssssi corresponds to (string, string, string, string, string, string, string, string, integer)
                $stmt->bind_param("sssssssi", $first_name, $last_name, $user_type, $user_role, $school_id, $campus, $course, $email, $uid);
                $stmt->execute();
                $stmt->close();

                // Check if any rows were affected
                if ($conn->affected_rows > 0) {
                    set_js_alert('success', 'User updated successfully!');
                } else {
                    // This could happen if no data was changed, or UID was not found
                    set_js_alert('info', 'User data submitted, but no changes were made or user not found.');
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Error updating user (UID: {$uid}): " . $e->getMessage());
                // Display a more user-friendly error message
                set_js_alert('error', 'Failed to update user. Please check the input and try again. (' . $e->getCode() . ')');
            }
        } else {
            set_js_alert('warning', 'Required fields are missing or empty for the update.');
        }
        // Redirect back to the users page after update attempt
        header("Location: admin-05-users.php");
        exit;

        // Handle user deletion
    } elseif (isset($_POST["delete_user"])) {
        $uid = (int)($_POST["uid"] ?? 0);

        if ($uid <= 0) {
            set_js_alert('warning', 'Invalid User ID for deletion.');
            header("Location: admin-05-users.php");
            exit;
        }

        // Prevent deleting the currently logged-in admin
        if (isset($_SESSION['UID']) && $uid == $_SESSION['UID']) {
            set_js_alert('warning', 'Cannot delete the currently logged-in administrator.');
            header("Location: admin-05-users.php");
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE UID = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();

            // Check if any rows were affected
            if ($conn->affected_rows > 0) {
                set_js_alert('success', 'User deleted successfully!');
            } else {
                set_js_alert('info', 'No user found with the provided ID to delete.');
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Error deleting user (UID: {$uid}): " . $e->getMessage());
            // Check for foreign key constraint violation (MySQL error code 1451)
            if ($e->getCode() == 1451) {
                set_js_alert('warning', 'Cannot delete user because they have associated records (e.g., reservations, history).');
            } else {
                set_js_alert('error', 'Failed to delete user: ' . $e->getMessage());
            }
        }
        // Redirect back to the users page after delete attempt
        header("Location: admin-05-users.php");
        exit;
    }
}

// --- Fetch and Filter Users ---
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$role_filter = $_GET['role'] ?? '';

$query = "SELECT * FROM users WHERE 1=1 "; // Base query
$params = []; // Array to hold parameter values
$types = ""; // String to hold parameter types

// Add search filter
if (!empty($search)) {
    $query .= "AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR school_id LIKE ?) "; // Added school_id to search
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%"; // Added school_id param
    $types .= "ssss"; // Added 's' for the new school_id param
}

// Add user type filter
if ($type_filter !== '') {
    $query .= "AND user_type = ? ";
    $params[] = $type_filter;
    $types .= "s";
}

// Add user role filter (only apply if type filter is not 'Admin' or if type filter is empty)
// Admins typically don't have the 'Guest', 'Student', 'Faculty' roles in the user_role column in this context
if ($role_filter !== '' && $type_filter !== 'Admin') {
    $query .= "AND user_role = ? ";
    $params[] = $role_filter;
    $types .= "s";
}


$query .= "ORDER BY user_type DESC, user_role, first_name"; // Order Admins first, then by role and name

$users = [];
$fetch_error_message = null;

try {
    // Ensure database connection is valid before preparing statement
    if ($conn && is_object($conn) && method_exists($conn, 'prepare')) {
        $stmt = $conn->prepare($query);

        // Bind parameters if there are any
        if (!empty($params)) {
            // Use the variadic operator (...) to pass array elements as individual arguments
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            $users = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            // Handle case where get_result() fails (unlikely with prepared statements but good practice)
            $fetch_error_message = "Failed to get result set from database.";
            error_log($fetch_error_message);
        }

        $stmt->close();
    } else {
        $fetch_error_message = "Database connection not available or invalid to load users.";
        error_log($fetch_error_message);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $fetch_error_message = "Error loading users: " . $e->getMessage();
}

// Group users after fetching
$grouped_users = [
    'Admin' => [],
    'User' => [
        'Guest' => [],
        'Student' => [],
        'Faculty' => []
    ]
];

if (!empty($users)) {
    foreach ($users as $user) {
        if ($user['user_type'] === 'Admin') {
            $grouped_users['Admin'][] = $user;
        } elseif ($user['user_type'] === 'User' && isset($grouped_users['User'][$user['user_role']])) {
            $grouped_users['User'][$user['user_role']][] = $user;
        }
        // Note: Users with user_type 'User' but an unexpected user_role will not be grouped here.
    }
}

// Define lists for dropdowns
$courses_list = ['BS Medical Technology', 'BS Nursing', 'BS Nutrition & Dietetics', 'BS Pharmacy', 'BS Physical Therapy', 'BS Psychology', 'BS Radiologic Technology', 'BS Criminology', 'BS Accountancy', 'BS Business Administration', 'BS Information Technology', 'BS Hospitality Management', 'BS Tourism Management', 'BA Communication', 'BP Administration'];
$campus_list = ['Antipolo', 'Binalonan', 'Guimba', 'North Manila', 'Quezon City'];
$user_roles_list = ['Admin', 'Guest', 'Student', 'Faculty']; // List of all possible roles for the modal

// --- Function to render a single user card (with improved internal layout) ---
function render_user_card($user, $current_admin_uid)
{
    // Determine the role to display (Admin type should show 'Admin' role)
    $displayed_role = ($user['user_type'] === 'Admin') ? 'Admin' : $user['user_role'];
    $is_current_admin = $current_admin_uid !== null && $user['UID'] == $current_admin_uid;
    $delete_disabled_attr = $is_current_admin ? 'disabled' : '';

?>
    <div class="user-card">
        <div class="user-header">
            <h3 class="user-name"><?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?></h3>
            <span class="type-role"><?= htmlspecialchars($user['user_type']) ?> | <?= htmlspecialchars($displayed_role) ?></span>
        </div>

        <div class="user-details-grid">
            <div class="detail-group">
                <p><strong>Email:</strong></p>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>

            <?php if (!empty($user['campus']) || !empty($user['school_id']) || !empty($user['course'])): ?>
                <div class="detail-group">
                    <?php if (!empty($user['campus'])): ?>
                        <p><strong>Campus:</strong></p>
                        <p><?= htmlspecialchars($user['campus']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($user['school_id'])): ?>
                        <p><strong>School ID:</strong></p>
                        <p><?= htmlspecialchars($user['school_id']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($user['course'])): ?>
                        <p><strong>Course:</strong></p>
                        <p><?= htmlspecialchars($user['course']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="detail-group">
                <p><strong>Registered:</strong></p>
                <p><?= htmlspecialchars($user['date_registered']) ?></p>
            </div>

            <div class="detail-group">
                <p><strong>Last Login:</strong></p>
                <p><?= htmlspecialchars($user['date_last_login']) ?></p>
            </div>


        </div>

        <div class="user-actions">
            <button
                class="edit-button"
                data-uid="<?= $user['UID'] ?>"
                data-first_name="<?= htmlspecialchars($user['first_name']) ?>"
                data-last_name="<?= htmlspecialchars($user['last_name']) ?>"
                data-email="<?= htmlspecialchars($user['email']) ?>"
                data-user_role="<?= htmlspecialchars($user['user_role']) ?>"
                data-school_id="<?= htmlspecialchars($user['school_id']) ?>"
                data-campus="<?= htmlspecialchars($user['campus']) ?>"
                data-course="<?= htmlspecialchars($user['course']) ?>">
                Edit
            </button>

            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                <input type="hidden" name="uid" value="<?= htmlspecialchars($user['UID']) ?>">
                <input type="hidden" name="delete_user" value="1">
                <button type="submit" class="delete-button" <?= $delete_disabled_attr ?>>Delete</button>
            </form>
        </div>
    </div>
<?php
}

// Close the database connection after all operations are done
if ($conn && is_object($conn) && method_exists($conn, 'close') && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Users</title>
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/general.css">
    <link rel="stylesheet" href="css/admin.css">
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
            <a href="admin-05-users.php" class="nav-btn active" aria-label="Users">
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
                <a href="admin-05-users.php" class="nav-btn active" aria-label="Users">
                    <i class="fas fa-users"></i> <span class="visually-hidden">Users</span>
                </a>
                <a href="logout.php" class="nav-btn logout-btn" aria-label="Logout">
                    <i class="fas fa-sign-out-alt"></i> <span class="visually-hidden">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <header>
        <h1>User Management</h1>
    </header>

    <main>
        <section id="filter-controls">
            <h2>Filter Users</h2>
            <form method="GET">
                <input type="text" name="search" placeholder="Search name, email, or ID" value="<?= htmlspecialchars($search) ?>" />
                <select name="type">
                    <option value="">All Types</option>
                    <option value="Admin" <?= $type_filter == 'Admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="User" <?= $type_filter == 'User' ? 'selected' : '' ?>>User</option>
                </select>
                <select name="role" <?= $type_filter == 'Admin' ? 'disabled' : '' ?>>
                    <option value="">All Roles</option>
                    <?php foreach (['Guest', 'Student', 'Faculty'] as $role_option): ?>
                        <option value="<?= htmlspecialchars($role_option) ?>" <?= $role_filter == $role_option ? 'selected' : '' ?>><?= htmlspecialchars($role_option) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Filter</button>
                <button type="button" onclick="window.location.href='admin-05-users.php'">Reset</button>
            </form>
        </section>

        <?php if ($fetch_error_message): ?>
            <p class="error-message"><?= htmlspecialchars($fetch_error_message) ?></p>
        <?php endif; ?>

        <section id="admin-user">
            <h2>Admin Users</h2>
            <?php if (empty($grouped_users['Admin'])): ?>
                <p>No Admin users found matching the criteria.</p>
            <?php else: ?>
                <?php
                $current_admin_uid = $_SESSION['UID'] ?? null;
                foreach ($grouped_users['Admin'] as $user):
                    render_user_card($user, $current_admin_uid);
                endforeach;
                ?>
            <?php endif; ?>
        </section>

        <section id="normal-user">
            <h2>Normal Users</h2>
            <?php
            $normal_roles = ['Guest', 'Student', 'Faculty'];
            $has_displayed_normal_section_header = false;
            $has_displayed_any_normal_user = false;

            foreach ($normal_roles as $role):
                // Check if the current role should be displayed based on filters and if there are users in this role
                if (($role_filter === '' || $role_filter === $role) && !empty($grouped_users['User'][$role])):
                    $has_displayed_any_normal_user = true;
                    if (!$has_displayed_normal_section_header):
                        $has_displayed_normal_section_header = true;
                    endif;
            ?>
                    <div id="<?= strtolower($role) ?>-user" class="<?= strtolower($role) ?>-user-group">
                        <h3 class="user-role"><?= htmlspecialchars($role) ?> Users</h3>
                        <?php foreach ($grouped_users['User'][$role] as $user):
                            render_user_card($user, $current_admin_uid);
                        endforeach; ?>
                    </div>
                <?php
                // Display message if filter is applied but no users found for this specific role
                elseif (($role_filter === '' || $role_filter === $role) && empty($grouped_users['User'][$role]) && (!empty($search) || $type_filter !== '' || $role_filter !== '')): ?>
                    <?php if (!$has_displayed_normal_section_header): $has_displayed_normal_section_header = true;
                    endif; ?>
                    <div id="<?= strtolower($role) ?>-user" class="<?= strtolower($role) ?>-user-group">
                        <h3><?= htmlspecialchars($role) ?> Users</h3>
                        <p>No <?= htmlspecialchars($role) ?> users found matching the criteria.</p>
                    </div>
                <?php endif;
            endforeach;

            // Display a general message if no normal users were found at all based on filters
            if (!$has_displayed_any_normal_user && (empty($search) && $type_filter === '' && $role_filter === '')): ?>
                <p>No Normal users found.</p>
            <?php elseif (!$has_displayed_any_normal_user && (!empty($search) || $type_filter !== '' || $role_filter !== '')): ?>
                <p>No Normal users found matching the criteria.</p>
            <?php endif;
            ?>
        </section>
    </main>

    <div class="modal-container">
        <div id="edit-user-modal" class="modal">
            <div class="modal-content">
                <h3>Edit User
                    <span class="close-button">&times;</span>
                </h3>
                <form id="edit-user-form" method="POST" class="form-modal">
                    <input type="hidden" name="uid" id="modal-uid" value="">
                    <input type="hidden" name="update_user" value="1">
                    <div class="modal-details">
                        <label>First Name: <input type="text" name="first_name" id="modal-first-name" required></label>
                        <label>Last Name: <input type="text" name="last_name" id="modal-last-name" required></label>
                        <label>Email: <input type="email" name="email" id="modal-email" required></label>
                        <label>User Role:
                            <select name="user_role" id="modal-user-role">
                                <?php foreach ($user_roles_list as $role): // Use the combined list for the modal 
                                ?>
                                    <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>School ID: <input type="text" name="school_id" id="modal-school-id"></label>
                        <label>Campus:
                            <select name="campus" id="modal-campus">
                                <option value="">Select Campus</option>
                                <?php foreach ($campus_list as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Course:
                            <select name="course" id="modal-course">
                                <option value="">Select Course</option>
                                <?php foreach ($courses_list as $course_option): ?>
                                    <option value="<?= htmlspecialchars($course_option) ?>"><?= htmlspecialchars($course_option) ?></option>
                                <?php endforeach; ?>
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
        // --- Modal JavaScript ---
        const modal = document.getElementById("edit-user-modal");
        const span = modal.querySelector(".close-button");
        const cancelButton = modal.querySelector(".cancel-button");
        const modalContainer = document.querySelector(".modal-container");
        const modalUserRoleSelect = document.getElementById("modal-user-role");
        const modalSchoolIdInput = document.getElementById("modal-school-id");
        const modalCampusSelect = document.getElementById("modal-campus");
        const modalCourseSelect = document.getElementById("modal-course");


        // Function to open the modal and populate with user data
        function openEditModal(button) {
            const uid = button.dataset.uid;
            const firstName = button.dataset.first_name;
            const lastName = button.dataset.last_name;
            const email = button.dataset.email;
            const userRole = button.dataset.user_role; // Get the actual user_role from data attribute
            const schoolId = button.dataset.school_id;
            const campus = button.dataset.campus;
            const course = button.dataset.course;

            document.getElementById("modal-uid").value = uid;
            document.getElementById("modal-first-name").value = firstName;
            document.getElementById("modal-last-name").value = lastName;
            document.getElementById("modal-email").value = email;
            document.getElementById("modal-user-role").value = userRole; // Set the role value
            document.getElementById("modal-school-id").value = schoolId;
            document.getElementById("modal-campus").value = campus;
            document.getElementById("modal-course").value = course;

            // Show/hide academic fields based on role
            toggleAcademicFields(userRole);

            modal.classList.add("show");
            modalContainer.classList.add("show");
        }

        // Function to close the modal
        function closeModal() {
            modal.classList.remove("show");
            modalContainer.classList.remove("show");
        }

        // Function to show/hide academic fields based on selected role
        function toggleAcademicFields(role) {
            const isAcademicRole = (role === 'Student' || role === 'Faculty');
            const academicFields = [
                modalSchoolIdInput.closest('label'),
                modalCampusSelect.closest('label'),
                modalCourseSelect.closest('label')
            ];

            academicFields.forEach(label => {
                if (label) { // Check if label exists
                    label.style.display = isAcademicRole ? 'block' : 'none';
                }
            });

            // Clear values if fields are hidden
            if (!isAcademicRole) {
                modalSchoolIdInput.value = '';
                modalCampusSelect.value = '';
                modalCourseSelect.value = '';
            }
        }


        // Event listeners for closing modal
        span.onclick = closeModal;
        cancelButton.onclick = closeModal;

        // Close modal when clicking outside the modal content
        window.onclick = function(event) {
            if (event.target === modalContainer) {
                closeModal();
            }
        }

        // Add click listeners to all "Edit" buttons
        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', function() {
                openEditModal(this);
            });
        });

        // Listen for changes in the user role dropdown in the modal
        modalUserRoleSelect.addEventListener('change', function() {
            toggleAcademicFields(this.value);
        });


        // --- Footer Year ---
        document.getElementById("current-year").textContent = new Date().getFullYear();

        // --- Navigation Script ---
        // Assuming navigation.js handles the hamburger menu and sidebar toggle
    </script>
    <script src="navigation.js"></script>

    <?php
    // --- Display JavaScript Alerts ---
    // Check if a JS alert is set in the session and display it
    if (isset($_SESSION['js_alert'])) {
        $alert_data = $_SESSION['js_alert'];
        // Unset the session variable so the alert is only shown once
        unset($_SESSION['js_alert']);
    ?>
        <script>
            // Use a simple alert for now, can be replaced with a custom modal/toaster
            const alertMessage = "<?= htmlspecialchars($alert_data['message'], ENT_QUOTES, 'UTF-8') ?>";
            // Optional: Add type to alert or use a custom function for different styles
            // const alertType = "<?= htmlspecialchars($alert_data['type'], ENT_QUOTES, 'UTF-8') ?>";
            alert(alertMessage);
        </script>
    <?php
    }
    ?>

    <script>
        // Script to manage the visibility of the role filter dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const typeFilterSelect = document.querySelector('select[name="type"]');
            const roleFilterSelect = document.querySelector('select[name="role"]');

            function updateRoleFilterState() {
                // Disable and reset role filter if 'Admin' type is selected
                if (typeFilterSelect.value === 'Admin') {
                    roleFilterSelect.disabled = true;
                    roleFilterSelect.value = ''; // Reset selected role
                } else {
                    roleFilterSelect.disabled = false;
                }
            }

            // Call on page load
            updateRoleFilterState();

            // Add event listener for future changes
            typeFilterSelect.addEventListener('change', updateRoleFilterState);
        });
    </script>

</body>

</html>