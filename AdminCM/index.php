<?php
include '../auth.php';
protect_page(1); // Only allow AdminCM to access this page


// ==========================================================
//   SESSION & CURRENT Content Moderator INFORMATION SETUP
// ===========================================================

$admin_username = $_SESSION['swapy_session'];

$admin_query = mysqli_query($conn, "SELECT * FROM users WHERE username='$admin_username'");
$admin_data = mysqli_fetch_assoc($admin_query);
$admin_id = $admin_data['id'];
$admin_name = $admin_data['name'];
$admin_pic = $admin_data['pic'];

// Let an already-open moderator dashboard detect new submissions.
if (isset($_GET['pending_count'])) {
    $count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM posts WHERE status = 'pending'");
    $count_row = $count_query ? mysqli_fetch_assoc($count_query) : ['total' => 0];
    header('Content-Type: application/json');
    echo json_encode(['count' => (int)$count_row['total']]);
    exit();
}

// =========================
//   1. get PENDING POSTS 
// =========================
$pending_query = mysqli_query($conn, "
    SELECT * FROM posts 
    WHERE status = 'pending' 
    ORDER BY id DESC");

// =======================================================================================================
//   2. get APPROVED POSTS for this moderator (to show in the Approved tab and allow deletion if needed)
// ========================================================================================================
$approved_query = mysqli_query($conn, "
    SELECT * FROM posts 
    WHERE status = 'approved' AND approved_by = '$admin_id'
    ORDER BY id DESC");


// =======================================================================================================
//   3. get REJECTED POSTS for this moderator 
// ========================================================================================================
$rejected_query = mysqli_query($conn, "
    SELECT * FROM posts 
    WHERE status = 'rejected' AND approved_by = '$admin_id'
    ORDER BY id DESC");


// =======================================================================================================
//   4. get REPORTED POSTS
// ========================================================================================================
$reported_query = mysqli_query($conn, "
    SELECT * FROM posts 
    WHERE status = 'reported' 
    ORDER BY id DESC");


// =======================================================================================================
//   5. AJAX HANDLER: PROCESS APPROVE / REJECT / DELETE ACTIONS
// ========================================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'moderate_post') {
   
    $post_id = mysqli_real_escape_string($conn, $_POST['post_id']);
    $action_type = mysqli_real_escape_string($conn, $_POST['action_type']);

    $new_status = ($action_type === 'approve') ? 'approved' : 'rejected';

    $update_query = mysqli_query($conn, "
        UPDATE posts 
        SET status = '$new_status', 
            approved_by = '$admin_id' 
        WHERE id = '$post_id'
    ");

    if ($update_query) {
        echo 'success'; 
    } else {
        echo 'error'; 
    }
    exit(); 
} 

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swapy | Admin Moderation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="nav-logo">SWAPY
            <span class="nav-context">| Content Moderation</span>
        </a>
        <div class="nav-right">
            <a href="../portal/staff_profile.php" class="profile-link">
                <span style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($admin_name); ?></span>
                
        <?php if (!empty($admin_pic)): ?>
        <img src="<?php echo !empty($admin_pic)
            ? '../image.php?entity=user&amp;id=' . (int)$admin_data['id']
            : 'https://ui-avatars.com/api/?name=' . urlencode($admin_name); ?>" class="header-avatar" alt="">
    <?php else: ?>
        <div class="header-avatar" style="
            width: 35px; 
            height: 35px; 
            border-radius: 50%; 
            background: #1e293b; 
            color: #ffffff; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold; 
            font-size: 12px;
            text-transform: uppercase;">
            <?php echo mb_substr($admin_name, 0, 2, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
            </a>
            <button onclick="location.href='../logout.php'" style="background:#fee2e2; border:none; color:#ef4444; padding:8px 15px; border-radius:10px; cursor:pointer; font-size:12px; font-weight:600;">Log Out</button>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-tabs">
            <button class="tab-btn active" onclick="switchTab('pending', this)">
                <span class="section-button-icon">🕒</span>
                <span class="section-button-copy">
                    <strong>Pending Review</strong>
                    <small>Items waiting for review</small>
                </span>
            </button>
            <button class="tab-btn" onclick="switchTab('approved', this)">
                <span class="section-button-icon">✅</span>
                <span class="section-button-copy">
                    <strong>Approved Posts</strong>
                    <small>Live marketplace items</small>
                </span>
            </button>
            <button class="tab-btn" onclick="switchTab('rejected', this)">
                <span class="section-button-icon">↩️</span>
                <span class="section-button-copy">
                    <strong>Rejected Posts</strong>
                    <small>Archived items</small>
                </span>
            </button>
            <button class="tab-btn" onclick="switchTab('reported', this)">
                <span class="section-button-icon">⚠️</span>
                <span class="section-button-copy">
                    <strong>Reported Content</strong>
                    <small>Items needing attention</small>
                </span>
            </button>
        </div>


        <!-- Pending posts section -->
        <div id="pending-section" class="tab-content" data-pending-count="<?php echo mysqli_num_rows($pending_query); ?>">
    <?php 
// Pending posts loop
    if (mysqli_num_rows($pending_query) > 0) {
      
        while($post = mysqli_fetch_assoc($pending_query)) { 
           
           
    ?>
            <div class="review-card" id="post-card-<?php echo $post['id']; ?>">
                
                <div class="item-image-placeholder">
    <?php if(!empty($post['img'])): ?>
        <img src="../image.php?entity=post&amp;id=<?php echo (int)$post['id']; ?>" alt="Item Image">
    <?php else: ?>
        <span>No Image</span>
    <?php endif; ?>
</div>
                
                <div class="item-info">
                    <span class="meta-tag meta-tag-pending">Waiting Review</span>
                    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p><?php echo htmlspecialchars($post['description']); ?></p>
                </div>
                
                <div class="action-area">
                    <button class="btn btn-approve" onclick="moderatePost(<?php echo $post['id']; ?>, 'approve', this)">Approve Post</button>
                    <button class="btn btn-reject" onclick="moderatePost(<?php echo $post['id']; ?>, 'reject', this)">Reject Item</button>
                </div>

            </div>
    <?php 
        }
    } else {
      
        echo "<p style='text-align:center; color:#64748b; margin-top:20px;'>🎉 No pending posts to review!</p>";
    }
    ?>
</div>
            
            
<!-- Approved posts section -->
        <div id="approved-section" class="tab-content">
    <?php 
     // Approved posts loop
    if (mysqli_num_rows($approved_query) > 0) {
        
        while($post = mysqli_fetch_assoc($approved_query)) { 
            
    ?>
            <div class="review-card" id="post-card-<?php echo $post['id']; ?>">
                <div class="item-image-placeholder">
    <?php if(!empty($post['img'])): ?>
        <img src="../image.php?entity=post&amp;id=<?php echo (int)$post['id']; ?>" alt="Item Image">
    <?php else: ?>
        <span>Image</span>
    <?php endif; ?>
</div>
                
                <div class="item-info">
                    <span class="meta-tag meta-tag-approved">Live on Store</span>
                    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p><?php echo htmlspecialchars($post['description']); ?></p>
                </div>
                
                <div class="action-area">
                    <button class="btn btn-delete-live" onclick="moderatePost(<?php echo $post['id']; ?>, 'reject', this)">Delete Post</button>
                </div>
            </div>
    <?php 
        }
    } else {
        echo "<p style='text-align:center; color:#64748b; margin-top:20px;'>No items approved by you yet.</p>";
    }
    ?>
</div>
            
<!-- Rejected posts section -->
        <div id="rejected-section" class="tab-content">
            <?php 
            // Rejected posts loop
            if (mysqli_num_rows($rejected_query) > 0) {
              
                while($post = mysqli_fetch_assoc($rejected_query)) { 
                    
            ?>
                    <div class="review-card" id="post-card-<?php echo $post['id']; ?>">
                        <div class="item-image-placeholder">
    <?php if(!empty($post['img'])): ?>
        <img src="../image.php?entity=post&amp;id=<?php echo (int)$post['id']; ?>" alt="Item Image">
    <?php else: ?>
        <span>Image</span>
    <?php endif; ?>
</div>
                        <div class="item-info">
                            <span class="meta-tag meta-tag-rejected">Rejected</span>
                            <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                            <p><?php echo htmlspecialchars($post['description']); ?></p>
                        </div>
                        
                        <div class="action-area">
                            <button class="btn btn-approve" onclick="moderatePost(<?php echo $post['id']; ?>, 'approve', this)">Approve back</button>
                        </div>
                    </div>
            <?php 
                } 
            } else {
                echo "<p style='text-align:center; color:#64748b; margin-top:20px;'>No items rejected by you yet.</p>";
            }
            ?>
        </div>

<!-- Reported posts section -->
        <div id="reported-section" class="tab-content">
            <?php 
            // Reported posts loop
            if (mysqli_num_rows($reported_query) > 0) {
              
                while($post = mysqli_fetch_assoc($reported_query)) { 
                    
            ?>
                    <div class="review-card" id="post-card-<?php echo $post['id']; ?>">
                        <div class="item-image-placeholder">
    <?php if(!empty($post['img'])): ?>
        <img src="../image.php?entity=post&amp;id=<?php echo (int)$post['id']; ?>" alt="Item Image">
    <?php else: ?>
        <span>Image</span>
    <?php endif; ?>
</div>
                        <div class="item-info">
                            <span class="meta-tag meta-tag-reported">Reported</span>
                            <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                            <p><?php echo htmlspecialchars($post['description']); ?></p>
                        </div>
                        
                        <div class="action-area">
                            <button class="btn btn-approve" onclick="moderatePost(<?php echo $post['id']; ?>, 'approve', this)">Keep Post</button>
                            <button class="btn btn-reject" onclick="moderatePost(<?php echo $post['id']; ?>, 'reject', this)">Remove Post</button>
                        </div>
                    </div>
            <?php 
                } 
            } else {
                echo "<p style='text-align:center; color:#64748b; margin-top:20px;'>No reported posts at the moment.</p>";
            }
            ?>
        </div>

    <script>
        function switchTab(tab, clickedButton) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            const section = document.getElementById(tab + '-section');
            if (section) section.style.display = 'block';
            if (clickedButton) clickedButton.classList.add('active');
            const container = document.querySelector('.container');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

function moderatePost(postId, actionType, button) {
    let formData = new FormData();
    formData.append('action', 'moderate_post');
    formData.append('post_id', postId);
    formData.append('action_type', actionType);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === 'success') {
            const card = button.parentElement.parentElement;
            if (card) {
                card.style.transition = "all 0.4s ease";
                card.style.opacity = "0";
                setTimeout(() => {
                    card.remove();
                }, 400);
            }
        } else {
            alert('An error occurred while updating the post status, please try again.');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('There was an error connecting to the server.');
    });
}

const pendingSection = document.getElementById('pending-section');
let pendingCount = Number(pendingSection?.dataset.pendingCount || 0);

// Refresh only when the number of pending listings changes, so moderation
// actions and the currently selected tab are not disturbed unnecessarily.
setInterval(() => {
    fetch('index.php?pending_count=1', { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            const nextCount = Number(data.count || 0);
            if (nextCount !== pendingCount) {
                window.location.reload();
            }
        })
        .catch(() => {
            // A temporary network failure should not interrupt moderation.
        });
}, 5000);


    </script>
</body>
</html>