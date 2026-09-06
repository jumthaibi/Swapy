<?php
include 'auth.php';

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
    $newPic = $_POST['pic_base64'];

 $sql = "UPDATE users SET bio='$newBio'";

    if (!empty($newPic) && strpos($newPic, 'data:image/') !== 0) {
        header("Location: staff_profile.php?error=invalid_image");
        exit();
    }
   

    if (!empty($newPic)) {
        $sql .= ", pic='$newPic'";
    }

    $sql .= " WHERE username='$me'";
    mysqli_query($conn, $sql);

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
$user_pic   = !empty($user_data['pic'])
    ? $user_data['pic']
    : "https://ui-avatars.com/api/?name=" . urlencode($user_data['name']) . "&background=fbbf24&color=fff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Swapy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
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
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="header-nav">
        <div class="nav-logo">Swapy</div>
        <button class="btn btn-danger" onclick="location.href='logout.php'">
            Log Out
        </button>
    </div>

    <div class="container">

        <!-- ===== SIDEBAR ===== -->
        <div class="sidebar-card">
            <div class="sidebar-header"></div>
            <div class="profile-info">

                <img src="<?php echo $user_pic; ?>" class="profile-img" id="preview-img">

                <h3 style="margin:10px 0 0;">
                    <?php echo htmlspecialchars($user_data['name']); ?>
                </h3>

                <p style="color:#94a3b8; font-size:13px; margin:4px 0;">
                    @<?php echo $me; ?>
                </p>

                <!-- Role Badge -->
                <div class="role-badge">
                    <?php echo $role_icon; ?>
                    <?php echo $role_label; ?>
                </div>

                <div class="bio-text">
                    <?php echo $user_data['bio'] ?: 'Add a bio to tell people about yourself.'; ?>
                </div>

                <button
                    class="btn btn-primary"
                    style="width:100%;"
                    onclick="document.getElementById('editProfileModal').style.display='flex'"
                >
                    ✏️ Edit Profile
                </button>

            </div>
        </div>

        <!-- ===== CONTENT ===== -->
        <div class="content-section">

            <?php if ($success): ?>
                <div class="alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert-error">⚠ <?php echo $message; ?></div>
            <?php endif; ?>

            <!-- ROLE INFO CARD -->
            <div class="card">
                <h3>👤 Account Information</h3>

                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['name']); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">Username</span>
                    <span class="info-value">@<?php echo $me; ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value"><?php echo $role_icon . ' ' . $role_label; ?></span>
                </div>

            </div>

            <!-- CHANGE PASSWORD CARD -->
            <div class="card">
                <h3>🔒 Change Password</h3>

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
            </div>

        </div>
    </div>

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