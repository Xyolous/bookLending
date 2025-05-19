<?php
include 'authentication-admin.php';

function set_js_alert($type, $message)
{
    $_SESSION['js_alert'] = ['type' => $type, 'message' => $message];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST["update_user"])) {
        $required_fields = ["uid", "first_name", "last_name", "user_role", "email"];
        $missing_fields = false;
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $missing_fields = true;
                break;
            }
        }

        if (!$missing_fields) {
            $uid = $_POST["uid"];

            $check_stmt = $conn->prepare("SELECT UID FROM users WHERE UID = ?");
            $check_stmt->bind_param("i", $uid);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                $check_stmt->close();
                set_js_alert('warning', 'User not found. Cannot update.');
                header("Location: admin-05-users.php");
                exit;
            }
            $check_stmt->close();


            $first_name = trim($_POST["first_name"]);
            $last_name = trim($_POST["last_name"]);
            $user_role = $_POST["user_role"];
            $email = trim($_POST["email"]);

            $school_id = trim($_POST["school_id"] ?? '');
            $campus = trim($_POST["campus"] ?? '');
            $course = trim($_POST["course"] ?? '');
            $address = trim($_POST["address"] ?? '');
            $contact = trim($_POST["contact"] ?? '');

            $school_id = ($school_id === '') ? null : $school_id;
            $campus = ($campus === '') ? null : $campus;
            $course = ($course === '') ? null : $course;
            $address = ($address === '') ? null : $address;
            $contact = ($contact === '') ? null : $contact;


            try {
                $user_type = ($user_role === 'Admin') ? 'Admin' : 'User';

                $query = "UPDATE users SET first_name=?, last_name=?, user_type=?, user_role=?, school_id=?, campus=?, course=?, email=?, address=?, contact=? WHERE UID=?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssssssssi", $first_name, $last_name, $user_type, $user_role, $school_id, $campus, $course, $email, $address, $contact, $uid);
                $stmt->execute();
                $affected_rows = $stmt->affected_rows;
                $stmt->close();

                if ($affected_rows > 0) {
                    set_js_alert('success', 'User updated successfully!');
                } else {
                    set_js_alert('info', 'User data submitted, but no changes were detected.');
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Error updating user (UID: {$uid}): " . $e->getMessage());
                set_js_alert('error', 'Failed to update user. Please check the input and try again. (' . $e->getCode() . ')');
            }
        } else {
            set_js_alert('warning', 'Required fields are missing or empty for the update.');
        }
        header("Location: admin-05-users.php");
        exit;
    } elseif (isset($_POST["delete_user"])) {
        $uid = (int)($_POST["uid"] ?? 0);

        if ($uid <= 0) {
            set_js_alert('warning', 'Invalid User ID for deletion.');
            header("Location: admin-05-users.php");
            exit;
        }

        if (isset($_SESSION['UID']) && $uid == $_SESSION['UID']) {
            set_js_alert('warning', 'Cannot delete the currently logged-in administrator.');
            header("Location: admin-05-users.php");
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE UID = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                set_js_alert('success', 'User deleted successfully!');
            } else {
                set_js_alert('info', 'No user found with the provided ID to delete.');
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            error_log("Error deleting user (UID: {$uid}): " . $e->getMessage());
            if ($e->getCode() == 1451) {
                set_js_alert('warning', 'Cannot delete user because they have associated records (e.g., reservations, history).');
            } else {
                set_js_alert('error', 'Failed to delete user: ' . $e->getMessage());
            }
        }
        header("Location: admin-05-users.php");
        exit;
    }
}

$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$role_filter = $_GET['role'] ?? '';

$query = "SELECT *, address, contact FROM users WHERE 1=1 ";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= "AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR school_id LIKE ? OR address LIKE ? OR contact LIKE ?) ";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $types .= "ssssss";
}

if ($type_filter !== '') {
    $query .= "AND user_type = ? ";
    $params[] = $type_filter;
    $types .= "s";
}

if ($role_filter !== '' && $type_filter !== 'Admin') {
    $query .= "AND user_role = ? ";
    $params[] = $role_filter;
    $types .= "s";
}


$query .= "ORDER BY user_type DESC, user_role, first_name";

$users = [];
$fetch_error_message = null;

try {
    if ($conn && is_object($conn) && method_exists($conn, 'prepare')) {
        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            $users = $result->fetch_all(MYSQLI_ASSOC);
        } else {
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
    }
}

$courses_list = ['BS Medical Technology', 'BS Nursing', 'BS Nutrition & Dietetics', 'BS Pharmacy', 'BS Physical Therapy', 'BS Psychology', 'BS Radiologic Technology', 'BS Criminology', 'BS Accountancy', 'BS Business Administration', 'BS Information Technology', 'BS Hospitality Management', 'BS Tourism Management', 'BA Communication', 'BP Administration'];
$campus_list = ['Antipolo', 'Binalonan', 'Guimba', 'North Manila', 'Quezon City'];
$user_roles_list = ['Admin', 'Guest', 'Student', 'Faculty'];

function render_user_card($user, $current_admin_uid)
{
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

            <?php if (!empty($user['address'])): ?>
                <div class="detail-group">
                    <p><strong>Address:</strong></p>
                    <p><?= htmlspecialchars($user['address']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($user['contact'])): ?>
                <div class="detail-group">
                    <p><strong>Contact:</strong></p>
                    <p><?= htmlspecialchars($user['contact']) ?></p>
                </div>
            <?php endif; ?>


            <?php
            $has_academic_details = !empty($user['campus']) || !empty($user['school_id']) || !empty($user['course']);
            if ($has_academic_details):
            ?>
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
                data-school_id="<?= htmlspecialchars($user['school_id'] ?? '') ?>"
                data-campus="<?= htmlspecialchars($user['campus'] ?? '') ?>"
                data-course="<?= htmlspecialchars($user['course'] ?? '') ?>"
                data-address="<?= htmlspecialchars($user['address'] ?? '') ?>"
                data-contact="<?= htmlspecialchars($user['contact'] ?? '') ?>">
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
                <input type="text" name="search" placeholder="Search name, email, ID, address, or contact" value="<?= htmlspecialchars($search) ?>" />
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

        <?php if ($type_filter === '' || $type_filter === 'Admin'): ?>
            <section id="admin-users">
                <h2>Admin Users</h2>
                <?php if (empty($grouped_users['Admin'])): ?>
                    <p>No Admin users found<?= (!empty($search) || $type_filter !== '' || $role_filter !== '') ? ' matching the criteria.' : '.' ?></p>
                <?php else: ?>
                    <?php
                    $current_admin_uid = $_SESSION['UID'] ?? null;
                    foreach ($grouped_users['Admin'] as $user):
                        render_user_card($user, $current_admin_uid);
                    endforeach;
                    ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php
        $normal_roles = ['Guest', 'Student', 'Faculty'];
        $any_normal_user_displayed = false;

        if ($type_filter === '' || $type_filter === 'User'):
            foreach ($normal_roles as $role):
                $role_group = $grouped_users['User'][$role] ?? [];
                $display_role_section = ($role_filter === '' || $role_filter === $role);

                if ($display_role_section):
                    if (!empty($role_group) || ($role_filter === '' && empty($search) && $type_filter === '')):
                        $any_normal_user_displayed = true;
        ?>
                        <section id="<?= strtolower($role) ?>-users">
                            <h2><?= htmlspecialchars($role) ?> Users</h2>
                            <?php if (empty($role_group)): ?>
                                <p>No <?= htmlspecialchars($role) ?> users found<?= (!empty($search) || $type_filter !== '' || $role_filter !== '') ? ' matching the criteria.' : '.' ?></p>
                            <?php else: ?>
                                <?php foreach ($role_group as $user):
                                    render_user_card($user, $_SESSION['UID'] ?? null);
                                endforeach; ?>
                            <?php endif; ?>
                        </section>
                    <?php
                    elseif ($role_filter === $role):
                        $any_normal_user_displayed = true;
                    ?>
                        <section id="<?= strtolower($role) ?>-users">
                            <h2><?= htmlspecialchars($role) ?> Users</h2>
                            <p>No <?= htmlspecialchars($role) ?> users found matching the criteria.</p>
                        </section>
                <?php
                    endif;
                endif;
            endforeach;

            if (!$any_normal_user_displayed && ($type_filter === 'User') && (!empty($search) || $role_filter !== '')):
                ?>
                <p>No normal users found matching the criteria.</p>
            <?php
            elseif (!$any_normal_user_displayed && empty($search) && $type_filter === '' && $role_filter === ''):
            ?>
                <p>No normal users found.</p>
            <?php endif; ?>
        <?php endif; ?>


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
                        <label>User Role:
                            <select name="user_role" id="modal-user-role">
                                <?php foreach ($user_roles_list as $role):
                                ?>
                                    <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label id="modal-course-label">Course:
                            <select name="course" id="modal-course">
                                <option value="">Select Course</option>
                                <?php foreach ($courses_list as $course_option): ?>
                                    <option value="<?= htmlspecialchars($course_option) ?>"><?= htmlspecialchars($course_option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label id="modal-school-id-label">School ID: <input type="text" name="school_id" id="modal-school-id"></label>
                        <label>Email: <input type="email" name="email" id="modal-email" required></label>
                        <label>Address: <input type="text" name="address" id="modal-address"></label>
                        <label>Contact Info: <input type="text" name="contact" id="modal-contact-info"></label>
                        <label id="modal-campus-label">Campus:
                            <select name="campus" id="modal-campus">
                                <option value="">Select Campus</option>
                                <?php foreach ($campus_list as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
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
        const modal = document.getElementById("edit-user-modal");
        const span = modal.querySelector(".close-button");
        const cancelButton = modal.querySelector(".cancel-button");
        const modalContainer = document.querySelector(".modal-container");
        const modalUserRoleSelect = document.getElementById("modal-user-role");

        const modalSchoolIdLabel = document.getElementById("modal-school-id-label");
        const modalCampusLabel = document.getElementById("modal-campus-label");
        const modalCourseLabel = document.getElementById("modal-course-label");

        const modalSchoolIdInput = document.getElementById("modal-school-id");
        const modalCampusSelect = document.getElementById("modal-campus");
        const modalCourseSelect = document.getElementById("modal-course");

        const modalAddressInput = document.getElementById("modal-address");
        const modalContactInfoInput = document.getElementById("modal-contact-info");

        const editUserForm = document.getElementById("edit-user-form");


        function openEditModal(button) {
            const uid = button.dataset.uid;
            const firstName = button.dataset.first_name;
            const lastName = button.dataset.last_name;
            const email = button.dataset.email;
            const userRole = button.dataset.user_role;
            const schoolId = button.dataset.school_id;
            const campus = button.dataset.campus;
            const course = button.dataset.course;
            const address = button.dataset.address;
            const contactInfo = button.dataset.contact;

            document.getElementById("modal-uid").value = uid;
            document.getElementById("modal-first-name").value = firstName;
            document.getElementById("modal-last-name").value = lastName;
            document.getElementById("modal-email").value = email;
            document.getElementById("modal-user-role").value = userRole;
            document.getElementById("modal-school-id").value = schoolId;
            document.getElementById("modal-campus").value = campus;
            document.getElementById("modal-course").value = course;
            modalAddressInput.value = address;
            modalContactInfoInput.value = contactInfo;


            toggleAcademicFields(userRole);

            modal.classList.add("show");
            modalContainer.classList.add("show");
        }

        function closeModal() {
            modal.classList.remove("show");
            modalContainer.classList.remove("show");
        }

        function toggleAcademicFields(role) {
            function setFieldVisibility(label, inputElement, isVisible) {
                if (label) {
                    label.style.display = isVisible ? 'block' : 'none';
                }
            }

            if (role === 'Student') {
                setFieldVisibility(modalSchoolIdLabel, modalSchoolIdInput, true);
                setFieldVisibility(modalCampusLabel, modalCampusSelect, true);
                setFieldVisibility(modalCourseLabel, modalCourseSelect, true);
            } else if (role === 'Faculty') {
                setFieldVisibility(modalSchoolIdLabel, modalSchoolIdInput, true);
                setFieldVisibility(modalCampusLabel, modalCampusSelect, true);
                setFieldVisibility(modalCourseLabel, modalCourseSelect, false);
            } else {
                setFieldVisibility(modalSchoolIdLabel, modalSchoolIdInput, false);
                setFieldVisibility(modalCampusLabel, modalCampusSelect, false);
                setFieldVisibility(modalCourseLabel, modalCourseSelect, false);
            }
        }


        span.onclick = closeModal;
        cancelButton.onclick = closeModal;

        window.onclick = function(event) {
            if (event.target === modalContainer) {
                closeModal();
            }
        }

        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', function() {
                openEditModal(this);
            });
        });

        modalUserRoleSelect.addEventListener('change', function() {
            toggleAcademicFields(this.value);
        });

        if (editUserForm) {
            editUserForm.addEventListener('submit', function(event) {
                event.preventDefault();

                const isConfirmed = confirm('Are you sure you want to save these changes?');

                if (isConfirmed) {
                    this.submit();
                } else {
                    console.log("Update cancelled by user.");
                }
            });
        }


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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeFilterSelect = document.querySelector('select[name="type"]');
            const roleFilterSelect = document.querySelector('select[name="role"]');

            function updateRoleFilterState() {
                if (typeFilterSelect.value === 'Admin') {
                    roleFilterSelect.disabled = true;
                    roleFilterSelect.value = '';
                } else {
                    roleFilterSelect.disabled = false;
                }
            }

            updateRoleFilterState();

            typeFilterSelect.addEventListener('change', updateRoleFilterState);
        });
    </script>

</body>

</html>