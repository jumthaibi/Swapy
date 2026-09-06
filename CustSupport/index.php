<?php
include '../auth.php';
protect_page(4); // Only allow CustSupport to access this page


// ==========================================================
//   SESSION & CURRENT Support Staff INFORMATION SETUP
// ===========================================================

$staff_username = $_SESSION['swapy_session'];

$staff_query = mysqli_query($conn, "SELECT * FROM users WHERE username='$staff_username'");
$staff_data = mysqli_fetch_assoc($staff_query);
$staff_id = $staff_data['id'];
$staff_name = $staff_data['name'];
$staff_pic = $staff_data['pic'];


// =======================================================================================================
//   AJAX HANDLER: CLOSE COMPLAINT
// ========================================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'close_complaint') {
    $complaint_id = mysqli_real_escape_string($conn, $_POST['complaint_id']);

    $update = mysqli_query($conn, "
        UPDATE complaint SET status = 'closed' WHERE id = '$complaint_id'
    ");

    if ($update) {
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}


// =======================================================================================================
//   AJAX HANDLER: SEND NOTIFICATION TO USER
// ========================================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'send_notification') {
    $complaint_id  = mysqli_real_escape_string($conn, $_POST['complaint_id']);
    $user_id       = mysqli_real_escape_string($conn, $_POST['user_id']);
    $notif_type    = mysqli_real_escape_string($conn, $_POST['notif_type']);
    $notif_message = mysqli_real_escape_string($conn, $_POST['notif_message']);
    $notif_title   = mysqli_real_escape_string($conn, $_POST['notif_title']);

    // Prepend the complaint title to the message
    $full_message = "[" . $notif_title . "] " . $notif_message;

    $insert = mysqli_query($conn, "
        INSERT INTO notification (user_id, message, type, related_id)
        VALUES ('$user_id', '$full_message', '$notif_type', '$complaint_id')
    ");

    // Also close the complaint after sending notification
    if ($insert) {
        $close = mysqli_query($conn, "
            UPDATE complaint SET status = 'closed' WHERE id = '$complaint_id'
        ");
        echo $close ? 'success' : 'error';
    } else {
        echo 'error';
    }
    exit();
}


// =======================================================================================================
//   GET COMPLAINTS WITH USER INFO
// ========================================================================================================
$complaints_query = mysqli_query($conn, "
    SELECT c.*, u.name AS user_name, u.username AS user_username
    FROM complaint c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWAPY | Customer Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
</head>

<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo">
        SWAPY
        <span style="font-weight:300; font-size:0.9rem; color:#64748b;">
            | Customer Support
        </span>
    </a>

    <div class="nav-right">
        <a href="../staff_profile.php" class="profile-link">
            <span style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($staff_name); ?></span>
            <?php if (!empty($staff_pic)): ?>
                <img src="<?php echo $staff_pic; ?>" class="header-avatar" style="object-fit:cover;">
            <?php else: ?>
                <div class="header-avatar" style="
                    display:inline-flex; align-items:center; justify-content:center;
                    background:#1e293b; color:#fff; font-weight:bold; font-size:12px;
                    text-transform:uppercase;">
                    <?php echo mb_substr($staff_name, 0, 2, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        </a>
        <button onclick="location.href='../logout.php'" style="background:#fee2e2; border:none; color:#ef4444; padding:8px 15px; border-radius:10px; cursor:pointer; font-size:12px; font-weight:600;">
            Log Out
        </button>
    </div>
</nav>

<div class="container">

    <div class="dashboard-tabs">
        <button class="tab-btn active" style="cursor: default;">
            Reports <span class="badge"><?php echo mysqli_num_rows($complaints_query); ?></span>
        </button>
    </div>

    <div id="reports" class="tab-content active">
        <div class="page-header">
            <h2>Customer Reports</h2>
            <p>Review customer complaints and support requests.</p>
        </div>

        <?php if (mysqli_num_rows($complaints_query) > 0): ?>
            <?php while ($complaint = mysqli_fetch_assoc($complaints_query)): ?>
                <div class="review-card" id="complaint-card-<?php echo $complaint['id']; ?>">
                    <div class="item-image-placeholder">Issue</div>
                    <div class="item-info">
                        <span class="meta-tag" style="<?php echo $complaint['status'] === 'closed' ? 'background:#ecfdf5; color:#059669;' : ''; ?>">
                            <?php echo $complaint['status'] === 'closed' ? '✅ Closed' : ucfirst($complaint['status']); ?>
                        </span>
                        <h3><?php echo htmlspecialchars($complaint['title']); ?></h3>
                        <p><?php echo htmlspecialchars($complaint['description']); ?></p>
                        <p style="font-size:12px; color:#94a3b8; margin-top:6px;">
                            By: <strong><?php echo htmlspecialchars($complaint['user_name']); ?></strong>
                            (@<?php echo htmlspecialchars($complaint['user_username']); ?>)
                            &nbsp;|&nbsp; <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?>
                        </p>
                    </div>
                    <?php if ($complaint['status'] !== 'closed'): ?>
                    <div class="action-area">
                        <button class="btn btn-chat" onclick="openNotificationForm(
                            '<?php echo htmlspecialchars($complaint['user_name'], ENT_QUOTES); ?>',
                            <?php echo $complaint['id']; ?>,
                            <?php echo $complaint['user_id']; ?>,
                            '<?php echo htmlspecialchars($complaint['title'], ENT_QUOTES); ?>'
                        )">
                            Send Notification
                        </button>
                        <button class="btn btn-close" onclick="closeComplaint(<?php echo $complaint['id']; ?>, this)">Ignore</button>
                    </div>
                    <?php else: ?>
                    <div class="action-area">
                        <span style="color:#94a3b8; font-size:13px; font-weight:500;">Resolved</span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style='text-align:center; color:#64748b; margin-top:20px;'>🎉 No complaints at the moment!</p>
        <?php endif; ?>

    </div>

</div>

<div class="chat-popup" id="notificationPopup" style="display: none;">
    <div class="chat-box" style="max-width: 450px;">
        <div class="chat-header">
            <h3>Notify: <span id="targetUser">User</span></h3>
            <button class="close-chat-btn" onclick="closeNotificationForm()">✕</button>
        </div>

        <div style="padding: 20px; display: flex; flex-direction: column; gap: 15px;">
            <input type="hidden" id="hiddenComplaintId">
            <input type="hidden" id="hiddenUserId">
            <input type="hidden" id="hiddenComplaintTitle">

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #475569;">
                    Complaint Title
                </label>
                <input type="text" id="notifTitleDisplay" readonly style="
                    width: 100%; padding: 10px; border-radius: 8px;
                    border: 1px solid #e2e8f0; font-family: inherit;
                    background: #f8fafc; color: #64748b; font-size: 13px;
                ">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #475569;">
                    Choose Notification Type
                </label>
                <select id="notifType" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;">
                    <option value="Issue Resolved">✅ Issue Resolved</option>
                    <option value="Delivery Update">🚚 Delivery Update</option>
                    <option value="Account Alert">⚠️ Account Alert</option>
                    <option value="Custom Message">📝 Custom Message</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #475569;">
                    Message Details
                </label>
                <textarea id="notifMessage" rows="4" placeholder="Type the message here..." 
                    style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit; resize: none;"></textarea>
            </div>

            <button class="btn btn-chat" onclick="sendNotification()" style="width: 100%; padding: 12px; margin-top: 5px;">
                Confirm & Send
            </button>
        </div>
    </div>
</div>

<script>
function openNotificationForm(userName, complaintId, userId, complaintTitle) {
    document.getElementById('notificationPopup').style.display = 'flex';
    document.getElementById('targetUser').innerText = userName;
    document.getElementById('hiddenComplaintId').value = complaintId;
    document.getElementById('hiddenUserId').value = userId;
    document.getElementById('hiddenComplaintTitle').value = complaintTitle;
    document.getElementById('notifTitleDisplay').value = complaintTitle;
}

function closeNotificationForm() {
    document.getElementById('notificationPopup').style.display = 'none';
    document.getElementById('notifMessage').value = '';
}

window.onclick = function(event) {
    const popup = document.getElementById('notificationPopup');
    if (event.target == popup) {
        closeNotificationForm();
    }
}

function sendNotification() {
    const complaintId    = document.getElementById('hiddenComplaintId').value;
    const userId         = document.getElementById('hiddenUserId').value;
    const notifType      = document.getElementById('notifType').value;
    const notifMsg       = document.getElementById('notifMessage').value.trim();
    const complaintTitle = document.getElementById('hiddenComplaintTitle').value;

    if (!notifMsg) {
        alert('Please write a message before sending.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'send_notification');
    formData.append('complaint_id', complaintId);
    formData.append('user_id', userId);
    formData.append('notif_type', notifType);
    formData.append('notif_message', notifMsg);
    formData.append('notif_title', complaintTitle);

    fetch('index.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(data => {
            if (data.trim() === 'success') {
                alert('Notification sent successfully!');
                closeNotificationForm();

                // Update the card UI to reflect closed status without reload
                const card = document.getElementById('complaint-card-' + complaintId);
                if (card) {
                    // Update the status badge
                    const badge = card.querySelector('.meta-tag');
                    if (badge) {
                        badge.textContent = '✅ Closed';
                        badge.style.background = '#ecfdf5';
                        badge.style.color = '#059669';
                    }
                    // Replace action buttons with "Resolved" label
                    const actionArea = card.querySelector('.action-area');
                    if (actionArea) {
                        actionArea.innerHTML = '<span style="color:#94a3b8; font-size:13px; font-weight:500;">Resolved</span>';
                    }
                }
            } else {
                alert('Error sending notification. Please try again.');
            }
        })
        .catch(() => alert('Server connection error.'));
}

function closeComplaint(complaintId, button) {
    const formData = new FormData();
    formData.append('action', 'close_complaint');
    formData.append('complaint_id', complaintId);

    fetch('index.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(data => {
            if (data.trim() === 'success') {
                const card = button.closest('.review-card');
                if (card) {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 400);
                }
            } else {
                alert('Error closing complaint. Please try again.');
            }
        })
        .catch(() => alert('Server connection error.'));
}
</script>

</body>
</html>
