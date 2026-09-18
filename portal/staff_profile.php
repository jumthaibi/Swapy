<?php
include '../auth.php';
require_once '../image_storage.php';

$me = $_SESSION['swapy_session'];
$my_role = $_SESSION['role'];

// Role names mapping
$role_names = [
    1 => 'Admin (Content Moderator)',
    2 => 'Delivery Coordination',
    3 => 'System Administrator',
    4 => 'Customer Support'
];

// Role icons mapping
$role_icons = [
    1 => '⚖️',
    2 => '🚚',
    3 => '👔',
    4 => '🧑🏼‍💻'
];

// Fetch employee data from database
$user_res = mysqli_query($conn, "SELECT * FROM users WHERE username='$me'");
$user_data = mysqli_fetch_assoc($user_res);

$message = "";
$success = "";

/* ==================================
UPDATE PROFILE (bio + picture)
================================== */
if (isset($_POST['update_profile'])) {
    $newBio = mysqli_real_escape_string($conn, $_POST['bio']);
    $newPic = trim($_POST['pic_base64'] ?? '');
    $newPicPath = '';
    $newPicData = '';
    $newPicMime = '';

    $sql = "UPDATE users SET bio='$newBio'";

    if ($newPic !== '') {
        $savedPic = save_data_uri_fallback(
            $newPic,
            __DIR__ . '/../customer/uploads',
            'customer/uploads/'
        );
        if ($savedPic === null) {
            header("Location: staff_profile.php?error=invalid_image");
            exit();
        }
        $newPicPath = $savedPic['path'];
        $newPicData = $savedPic['image_data'];
        $newPicMime = $savedPic['mime_type'];
        $sql .= ", pic='" . mysqli_real_escape_string($conn, $newPicPath) . "'";
    }

    $sql .= " WHERE username='$me'";
    if (mysqli_query($conn, $sql) && $newPicData !== '') {
        save_image_blob($conn, 'user', (int)$user_data['id'], $newPicMime, $newPicData);
    }

    header("Location: staff_profile.php?success=profile_updated");
    exit();
}

/* ==================================
CHANGE PASSWORD
================================== */
if (isset($_POST['change_password'])) {
    $current_pass = mysqli_real_escape_string($conn, $_POST['current_password']);
    $new_pass     = mysqli_real_escape_string($conn, $_POST['new_password']);
    $confirm_pass = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // Check if current password is correct
    $check = mysqli_query($conn, "SELECT * FROM users WHERE username='$me' AND password='$current_pass'");

    if (mysqli_num_rows($check) == 0) {
        $message = "Current password is incorrect!";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "New passwords do not match!";
    } elseif (strlen($new_pass) < 6) {
        $message = "Password must be at least 6 characters!";
    } else {
        mysqli_query($conn, "UPDATE users SET password='$new_pass' WHERE username='$me'");
        header("Location: staff_profile.php?success=password_changed");
        exit();
    }
}

// Success messages
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'profile_updated') $success = "Profile updated successfully!";
    if ($_GET['success'] === 'password_changed') $success = "Password changed successfully!";
}
// Error messages
if (isset($_GET['error']) && $_GET['error'] === 'invalid_image') {
    $message = "Only image files (PNG, JPG, JPEG, WEBP) are allowed!";
}

$role_label = $role_names[$my_role] ?? 'Employee';
$role_icon  = $role_icons[$my_role] ?? '👤';
$dashboard_links = [
    1 => '../AdminCM/index.php',
    2 => '../delivery/index.php',
    3 => '../SystemAdmin/index.php',
    4 => '../CustSupport/index.php'
];
$dashboard_link = $dashboard_links[$my_role] ?? 'index.php';
$user_pic   = !empty($user_data['pic'])
    ? "../image.php?entity=user&id=" . (int)$user_data['id']
    : "https://ui-avatars.com/api/?name=" . urlencode($user_data['name']) . "&background=fbbf24&color=fff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Swapy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../customer/profile.css">
    <style>
        :root {
            --primary: #fbbf24;
            --primary-light: #fef3c7;
            --text: #1e293b;
            --bg: #f1f5f9;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            margin: 0;
            color: var(--text);
        }

        /* ---- NAVBAR ---- */
        .header-nav {
            background: white;
            padding: 15px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .nav-logo {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
        }

        /* ---- LAYOUT ---- */
        .container {
            max-width: 1000px;
            margin: 40px auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            padding: 0 20px;
        }

        /* ---- SIDEBAR ---- */
        .sidebar-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .sidebar-header {
            background: var(--primary);
            height: 80px;
        }

        .profile-info {
            padding: 0 20px 30px;
            text-align: center;
            margin-top: -50px;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            background: white;
        }

        .bio-text {
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
            margin: 12px 0;
        }

        /* Role badge */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-light);
            color: #92400e;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 8px 0;
        }

        /* ---- CARDS ---- */
        .content-section {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .card h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
        }

        /* ---- INFO ROWS ---- */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .info-row:last-child { border-bottom: none; }

        .info-label {
            color: #94a3b8;
            font-weight: 500;
        }

        .info-value {
            font-weight: 600;
            color: var(--text);
        }

        /* ---- BUTTONS ---- */
        .btn {
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--text);
        }

        .btn-primary:hover { background: #f59e0b; }

        .btn-outline {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-danger {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        /* ---- FORM ---- */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }

        /* ---- ALERTS ---- */
        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        /* ---- MODAL ---- */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 { margin: 0; }

        /* Shared profile workspace layout */
        html,
        body {
            min-height: 100%;
            height: auto;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .header-nav {
            position: fixed;
            inset: 0 0 auto;
            z-index: 1000;
            height: var(--profile-nav-height);
            padding: 14px clamp(16px, 5vw, 96px);
            background: #fff;
        }

        .staff-profile-page {
            min-height: 100vh;
            padding: calc(var(--profile-nav-height) + 24px) clamp(16px, 5vw, 96px) 40px;
            overflow: visible;
        }

        .staff-profile-page .profile-hero {
            grid-template-columns: minmax(250px, 0.8fr) minmax(0, 1.7fr);
        }

        .staff-profile-page .profile-shortcuts {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .staff-profile-page .profile-about .bio-text {
            background: transparent;
            padding: 0;
        }

        .staff-role-badge {
            align-self: flex-start;
            margin: 0 0 18px;
        }

        .staff-alerts {
            display: grid;
            gap: 10px;
            margin-top: 22px;
        }

        .staff-content {
            margin-top: 22px;
        }

        .staff-panel {
            display: none;
            margin: 0;
        }

        .staff-panel.is-open {
            display: block;
        }

        .staff-panel h2 {
            margin: 0 0 18px;
            font-size: 18px;
        }

        @media (max-width: 800px) {
            .staff-profile-page {
                padding: calc(var(--profile-nav-height) + 16px) 12px 24px;
            }

            .staff-profile-page .profile-hero {
                grid-template-columns: 1fr;
            }
        }

    </style>
</head>
<body>

    <div class="header-nav">
        <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="back-link">
            ❮ Back to Dashboard
        </a>
        <button class="profile-logout" onclick="location.href='../logout.php'">
            Log Out
        </button>
    </div>

    <main class="profile-page staff-profile-page">
        <section class="profile-hero">
            <div class="profile-identity">
                <img
                    src="<?php echo htmlspecialchars($user_pic, ENT_QUOTES, 'UTF-8'); ?>"
                    class="profile-img"
                    id="preview-img"
                    alt="<?php echo htmlspecialchars($user_data['name']); ?>'s profile picture"
                >
                <div>
                    <h1><?php echo htmlspecialchars($user_data['name']); ?></h1>
                    <p class="profile-handle">@<?php echo htmlspecialchars($me); ?></p>
                </div>
            </div>

            <div class="profile-about">
                <div class="bio-heading">
                    <span class="eyebrow">About you</span>
                    <span class="profile-status"><span></span> Active member</span>
                </div>
                <p class="bio-text">
                    <?php echo htmlspecialchars($user_data['bio'] ?: 'Add a bio to tell people about yourself.'); ?>
                </p>
                <div class="role-badge staff-role-badge">
                    <?php echo $role_icon; ?>
                    <?php echo htmlspecialchars($role_label); ?>
                </div>
                <button
                    class="btn btn-primary edit-profile-btn"
                    type="button"
                    onclick="document.getElementById('editProfileModal').style.display='flex'"
                >
                    Edit Profile
                </button>
            </div>
        </section>

        <section class="profile-shortcuts" aria-label="Profile sections">
            <button type="button" class="section-button is-active" data-panel="staff-account-panel" onclick="openStaffSection('staff-account-panel', this)">
                <span class="section-button-icon">👤</span>
                <span>
                    <strong>Account Information</strong>
                    <small>Staff details</small>
                </span>
            </button>
            <button type="button" class="section-button" data-panel="staff-password-panel" onclick="openStaffSection('staff-password-panel', this)">
                <span class="section-button-icon">🔒</span>
                <span>
                    <strong>Change Password</strong>
                    <small>Keep it secure</small>
                </span>
            </button>
        </section>

        <?php if ($success || $message): ?>
            <div class="staff-alerts">
                <?php if ($success): ?>
                    <div class="alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($message): ?>
                    <div class="alert-error">⚠ <?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="staff-content">
            <section class="staff-panel card is-open" id="staff-account-panel">
                <h2>👤 Account Information</h2>
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Username</span>
                    <span class="info-value">@<?php echo htmlspecialchars($me); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value"><?php echo $role_icon . ' ' . htmlspecialchars($role_label); ?></span>
                </div>
            </section>

            <section class="staff-panel card" id="staff-password-panel">
                <h2>🔒 Change Password</h2>
                <form method="POST" action="staff_profile.php">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        Update Password
                    </button>
                </form>
            </section>
        </div>
    </main>

    <!-- ===== EDIT PROFILE MODAL ===== -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">

            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button
                    class="btn btn-outline"
                    onclick="document.getElementById('editProfileModal').style.display='none'"
                >
                    ✕
                </button>
            </div>

            <form method="POST" action="staff_profile.php">

                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" rows="3" placeholder="Tell people about yourself..."><?php echo htmlspecialchars($user_data['bio']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" accept="image/png,image/jpeg, image/webp, image/jpg" onchange="previewImage(this)">
                    <input type="hidden" name="pic_base64" id="pic_base64">
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary" style="flex:1;">
                        Save Changes
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline"
                        onclick="document.getElementById('editProfileModal').style.display='none'"
                    >
                        Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>
        function openStaffSection(panelId, button) {
            document.querySelectorAll('.staff-panel').forEach(function (panel) {
                panel.classList.toggle('is-open', panel.id === panelId);
            });

            document.querySelectorAll('.profile-shortcuts .section-button').forEach(function (item) {
                item.classList.toggle('is-active', item === button);
            });
        }

        // Preview profile picture before upload
        function previewImage(input) {
            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onloadend = function () {
                document.getElementById('pic_base64').value = reader.result;
                document.getElementById('preview-img').src = reader.result;
            };
            reader.readAsDataURL(file);
        }
    </script>

</body>
</html>