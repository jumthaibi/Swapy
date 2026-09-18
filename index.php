<?php

include 'auth.php';
require_once 'image_storage.php';
protect_page(0); // Only allow customers to access this page


$currentUser = $_SESSION['swapy_session'];

$user_query = mysqli_query($conn, "SELECT * FROM users WHERE username='$currentUser'");
$userData = mysqli_fetch_assoc($user_query);

$userPic = !empty($userData['pic'])
    ? "image.php?entity=user&id=" . (int)$userData['id'] . "&v=" . time()
    : "https://ui-avatars.com/api/?name=".urlencode($userData['name'])."&background=fbbf24&color=fff";


/* =========================
FETCH ITEM INBOX
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fetch_item_inbox'])) {

    $item_id = (int)$_POST['item_id'];

    $query = "
        SELECT DISTINCT c.sender
        FROM chats c
        WHERE c.item_id = $item_id
        AND c.receiver = '$currentUser'
    ";

    $res = mysqli_query($conn, $query);

    $buyers = [];

    while($row = mysqli_fetch_assoc($res)) {
        $buyers[] = $row['sender'];
    }

    header('Content-Type: application/json');
    echo json_encode($buyers);
    exit;
}


/* =========================
FETCH MESSAGES
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fetch_msgs'])) {

    $item_id = (int)$_POST['item_id'];

    $target = mysqli_real_escape_string($conn, $_POST['target_user']);

    $hasChatImageTable = ensure_image_blob_table($conn);
    $query = "
        SELECT c.*" . ($hasChatImageTable ? ", ib.entity_id AS image_id" : ", 0 AS image_id") . " FROM chats c
        " . ($hasChatImageTable ? "LEFT JOIN swapy_image_blobs ib ON ib.entity_type = 'chat' AND ib.entity_id = c.id" : "") . "
        WHERE c.item_id = $item_id
        AND (
            (c.sender='$currentUser' AND c.receiver='$target')
            OR
            (c.sender='$target' AND c.receiver='$currentUser')
        )
        ORDER BY c.id ASC
    ";

    $res = mysqli_query($conn, $query);

    $msgs = [];

    while($row = mysqli_fetch_assoc($res)) {
        $msgs[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit;
}


/* =========================
SEND MESSAGE
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])) {

    $item_id = (int)$_POST['item_id'];

    $message = trim($_POST['message'] ?? '');
    $target = mysqli_real_escape_string($conn, $_POST['target_user']);
    $uploadedImage = read_uploaded_image($_FILES['image'] ?? null);

    header('Content-Type: application/json');
    if ($uploadedImage === false) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Please choose a JPG, PNG, GIF, or WebP image smaller than 8 MB.'
        ]);
        exit;
    }

    if ($message === '' && $uploadedImage === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Write a message or attach an image.']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "
        INSERT INTO chats (item_id, sender, receiver, message)
        VALUES (?, ?, ?, ?)
    ");

    $saved = false;
    if ($stmt && !empty($target)) {
        mysqli_stmt_bind_param($stmt, 'isss', $item_id, $currentUser, $target, $message);
        $saved = mysqli_stmt_execute($stmt);
        $messageId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($saved && $uploadedImage !== null) {
            $saved = save_chat_image_blob(
                $conn,
                $messageId,
                $uploadedImage['mime_type'],
                $uploadedImage['image_data']
            );
        }
    }

    if (!$saved) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'The message could not be sent. Please try again.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}


/* =========================
PUBLISH POST
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['publish_post'])) {

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $swap_item = trim($_POST['swap_req'] ?? '');
    $price = max(0, (float)($_POST['price'] ?? 0));
    $image_data = '';
    $image_blob = '';
    $image_mime = '';

    if ($title === '' || $description === '' || !in_array($type, ['Sell', 'Swap'], true)) {
        $_SESSION['publish_error'] = 'Please complete the required listing fields.';
        header("Location: index.php?publish=error");
        exit();
    }

    // Keep a small compatible path in posts.img, and store the actual bytes in
    // swapy_image_blobs after the post is created. A full base64 value in
    // posts.img can exceed a VARCHAR/TEXT column and silently prevent the
    // pending row from being created.
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
        && $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['publish_error'] = 'The image upload failed. Please choose a smaller image and try again.';
        header("Location: index.php?publish=error");
        exit();
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_info = @getimagesize($_FILES['image']['tmp_name']);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!$image_info || !in_array($image_info['mime'], $allowed_mimes, true)) {
            $_SESSION['publish_error'] = 'Please upload a valid JPG, PNG, GIF, or WebP image.';
            header("Location: index.php?publish=error");
            exit();
        }

        $image_blob = file_get_contents($_FILES['image']['tmp_name']);
        $image_mime = $image_info['mime'];
        if ($image_blob === false || $image_blob === '') {
            $_SESSION['publish_error'] = 'The image could not be read. Please try again.';
            header("Location: index.php?publish=error");
            exit();
        }

        $upload_dir = __DIR__ . '/customer/uploads';
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
            $_SESSION['publish_error'] = 'The image could not be saved. Please try again.';
            header("Location: index.php?publish=error");
            exit();
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$image_info['mime']];
        $destination = $upload_dir . '/' . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $_SESSION['publish_error'] = 'The image could not be saved. Please try again.';
            header("Location: index.php?publish=error");
            exit();
        }

        $image_data = 'customer/uploads/' . $filename;
    } elseif (!empty($_POST['image_base64'])) {
        // Backward-compatible fallback for older clients that still send the
        // hidden base64 field. Convert it to a file and keep the bytes in the
        // durable image table instead of putting a huge data URL in posts.img.
        $legacy_image = save_data_uri_fallback(
            $_POST['image_base64'],
            __DIR__ . '/customer/uploads',
            'customer/uploads/'
        );
        if ($legacy_image !== null) {
            $image_data = $legacy_image['path'];
            $image_blob = $legacy_image['image_data'];
            $image_mime = $legacy_image['mime_type'];
        }
    }

    if ($image_data === '') {
        $_SESSION['publish_error'] = 'Please choose an image for your listing.';
        header("Location: index.php?publish=error");
        exit();
    }

    $publish_query = mysqli_prepare($conn, "
        INSERT INTO posts (
            author, title, category, type, price, swap_req, description, img, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    if ($publish_query) {
        mysqli_stmt_bind_param(
            $publish_query,
            'ssssdsss',
            $currentUser,
            $title,
            $category,
            $type,
            $price,
            $swap_item,
            $description,
            $image_data
        );
        $published = mysqli_stmt_execute($publish_query);
        mysqli_stmt_close($publish_query);
    } else {
        $published = false;
    }

    if ($published) {
        if ($image_blob !== '') {
            $post_id = (int) mysqli_insert_id($conn);
            if (!save_image_blob($conn, 'post', $post_id, $image_mime, $image_blob)) {
                error_log('Swapy image blob save failed for post ' . $post_id . '; filesystem fallback retained.');
            }
        }
        $_SESSION['publish_success'] = 'Your listing was sent to content moderation.';
        header("Location: index.php?publish=success");
    } else {
        error_log('Swapy listing publish failed: ' . mysqli_error($conn));
        $_SESSION['publish_error'] = 'Your listing could not be sent. Please try again.';
        header("Location: index.php?publish=error");
    }
    exit();
}


/* =========================
REPORT POST
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_post'])) {

    $post_id = (int)$_POST['post_id'];

    mysqli_query($conn, "
        UPDATE posts 
        SET status='reported'
        WHERE id=$post_id
    ");

    echo "success";
    exit;
}


/* =========================
SEND COMPLAINT
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {

    $title = mysqli_real_escape_string($conn, $_POST['complaint_title']);

    $description = mysqli_real_escape_string($conn, $_POST['complaint_description']);

    $user_id = $userData['id'];

    mysqli_query($conn, "
        INSERT INTO complaint (
            user_id,
            title,
            description,
            status
        )
        VALUES (
            '$user_id',
            '$title',
            '$description',
            'pending'
        )
    ");

    header("Location: index.php");
    exit();
}

/* =========================
CREATE DELIVERY
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_delivery'])) {

    $item_id = (int)$_POST['item_id'];
    $target_user = mysqli_real_escape_string($conn, $_POST['target_user']);
    $sender_location = mysqli_real_escape_string($conn, $_POST['sender_location']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $delivery_notes = mysqli_real_escape_string($conn, $_POST['delivery_notes']);

    $senderData = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT id FROM users WHERE username='$currentUser'"
    ));

    $itemData = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT type, price FROM posts WHERE id='$item_id'"
    ));

    $receiverData = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT id FROM users WHERE username='$target_user'"
    ));

    if (!$itemData || !$receiverData || !$senderData) {
        header("Location:index.php?delivery=error");
        exit();
    }

    $sender_id = $senderData['id'];
    $receiver_id = $receiverData['id'];
    $amount_to_collect = $itemData['type'] === 'Sell'
        ? max(0, (float)($itemData['price'] ?? 0))
        : 0;

    mysqli_query($conn, "
        INSERT INTO delivery_request (
            sender_id,
            receiver_id,
            item_id,
            amount_to_collect,
            sender_location,
            phone,
            delivery_notes,
            delivery_status,
            sender_done
        )
        VALUES (
            '$sender_id',
            '$receiver_id',
            '$item_id',
            '$amount_to_collect',
            '$sender_location',
            '$phone',
            '$delivery_notes',
            'waiting_for_other_user',
            1
        )
    ");

    $delivery_id = mysqli_insert_id($conn);

    mysqli_query($conn, "
        INSERT INTO notification (
            user_id,
            message,
            type,
            related_id
        )
        VALUES (
            '$receiver_id',
            'You have a new incoming delivery request. Please confirm and enter your details.',
            'delivery',
            '$delivery_id'
        )
    ");

    mysqli_query($conn, "
        INSERT INTO notification (
            user_id,
            message,
            type,
            related_id
        )
        VALUES (
            '$sender_id',
            'Your delivery request has been sent successfully! Waiting for @$target_user to confirm.',
            'delivery',
            '$delivery_id'
        )
    ");

    header("Location:index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swapy | Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="customer/index.css">
</head>

<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo">SWAPY</a>
    <div class="nav-right">
        <div class="navbar-tools" aria-label="Customer tools">
            <div class="nav-search">
                <span class="search-icon" aria-hidden="true">🔍</span>
                <input
                    type="text"
                    id="instantSearch"
                    placeholder="Search for treasures..."
                    autocomplete="off"
                    oninput="applyFilters()"
                    aria-label="Search listings"
                >
            </div>

            <div class="filter-control">
                <button type="button" class="nav-action" id="filterToggle" onclick="toggleFilterMenu(event)" aria-expanded="false" aria-controls="filterMenu">
                    <span aria-hidden="true">☷</span>
                    <span class="nav-action-label">Filter</span>
                </button>
                <div class="filter-menu" id="filterMenu">
                    <div class="filter-menu-title">Filter listings</div>
                    <div class="filter-group">
                        <label for="filterCategory">Category</label>
                        <select id="filterCategory" onchange="applyFilters()">
                            <option value="">All Categories</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Fashion">Fashion</option>
                            <option value="Home">Home</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filterType">Offer Type</label>
                        <select id="filterType" onchange="applyFilters()">
                            <option value="">All Types</option>
                            <option value="Sell">Sell</option>
                            <option value="Swap">Swap</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="button" class="nav-action nav-action-report" onclick="openComplaintModal()" title="Open Reports Center">
                <span aria-hidden="true">⚠</span>
                <span class="nav-action-label">Reports Center</span>
            </button>
        </div>
        <a href="customer/profile.php" class="profile-link">
            <span style="font-weight:600; font-size:14px;">
                <?php echo $userData['name']; ?>
            </span>
            <img src="<?php echo $userPic; ?>" class="header-avatar">
        </a>
        <button
             class="nav-logout"
            onclick="location.href='logout.php'"
            style="
                background:#fee2e2;
                border:none;
                color:#ef4444;
                padding:8px 15px;
                border-radius:10px;
                cursor:pointer;
                font-size:12px;
                font-weight:600;
            "
        >
            Log Out
        </button>
    </div>
</nav>

<?php
$publish_success = $_SESSION['publish_success'] ?? '';
$publish_error = $_SESSION['publish_error'] ?? '';
unset($_SESSION['publish_success'], $_SESSION['publish_error']);
?>

<?php if ($publish_success || $publish_error): ?>
    <div class="publish-notice <?php echo $publish_success ? 'is-success' : 'is-error'; ?>" role="status">
        <?php echo htmlspecialchars($publish_success ?: $publish_error); ?>
    </div>
<?php endif; ?>

<div class="main-layout">
    <div class="feed" id="feed-container">
        <?php
        $posts = mysqli_query($conn, "
            SELECT * FROM posts
            WHERE status='approved'
            OR status='reported'
            ORDER BY id DESC
        ");

        while($row = mysqli_fetch_assoc($posts)) {
            // Never put image bytes/data URLs into the page or into the
            // JavaScript payload. The image endpoint streams them by ID.
            $post_for_js = $row;
            unset($post_for_js['img']);
            $json_data = htmlspecialchars(json_encode($post_for_js), ENT_QUOTES, 'UTF-8');
            echo "
            <div class='item-card' data-category='{$row['category']}' data-type='{$row['type']}' onclick='openItem($json_data)'>
                <div class='card-tag'>
                    {$row['category']}
                </div>
                <div class='item-img-wrap'>
                    <img src='image.php?entity=post&amp;id=" . (int)$row['id'] . "' class='item-img' alt=''>
                </div>
                <div class='item-info' style='padding:10px;'>
                    <h4 style='margin:0 0 10px 0; font-size:15px;'>
                        {$row['title']}
                    </h4>
                    <div style='font-weight:700; color:var(--primary-dark);'>
                        ".($row['type']=='Sell' ? $row['price'].' JD' : '🔄 Swap')."
                    </div>
                    <small style='color:var(--text-muted);'>
                        @{$row['author']}
                    </small>
                </div>
            </div>
            ";
        }
        ?>
    </div>
</div>

<div id="itemModal" class="modal" onclick="if(event.target==this) closeModals()">
    <div class="modal-content item-modal-content">
        <div class="details-pane" id="details-view"></div>
        <button
            type="button"
            id="chat-resizer"
            class="chat-resizer"
            aria-label="Resize chat area"
            aria-valuemin="40"
            aria-valuemax="75"
            aria-valuenow="60"
            title="Drag to resize"
        ></button>
        <div class="chat-pane">
            <div class="chat-header">
                <span id="chat-title">Chat</span>
                <button onclick="closeModals()" class="close-btn">
                    Close
                </button>
            </div>
            <div id="chat-dynamic-area"></div>
        </div>
    </div>
</div>

<button class="add-btn" onclick="openAddModal()">+</button>
<div id="addModal" class="modal" onclick="if(event.target==this) closeAddModal()">
    <div class="modal-content no-scrollbar" style="max-width:550px; height:500px; padding:25px; display:block; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0;">Publish New Item</h3>
            <button onclick="closeAddModal()" class="close-btn">X</button>
        </div>

        <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="publish_post" value="1">
            <div class="filter-group">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>

            <div class="filter-group">
                <label>Item Image</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                <input type="hidden" name="image_base64" id="image_base64_input">
            </div>

            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option>Electronics</option>
                    <option>Fashion</option>
                    <option>Home</option>
                    <option>Others</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Type</label>
                <select name="type" id="postType" onchange="togglePrice(this.value)">
                    <option value="Sell">Sell</option>
                    <option value="Swap">Swap</option>
                </select>
            </div>

            <div id="priceField" class="filter-group">
                <label>Price</label>
                <input type="number" name="price" min="0" step="0.01" oninput="if(this.value < 0) this.value = 0;">
            </div>

            <div id="swapField" class="filter-group" style="display:none;">
                <label>Swap Requirements</label>
                <input type="text" name="swap_req">
            </div>

            <div class="filter-group">
                <label>Description</label>
                <textarea name="description" rows="3" required></textarea>
            </div>

            <button type="submit" style="width:100%; background:var(--primary); color:white; border:none; padding:12px; border-radius:10px; font-weight:600; cursor:pointer;">Publish</button>
        </form>
    </div>
</div>

<div id="complaintModal" class="modal" onclick="if(event.target==this) closeComplaintModal()">
    <div class="modal-content" style="max-width:450px; height:auto; padding:25px; display:block;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <div>
                <h3 style="margin:0;">Reports Center</h3>
                <p style="margin:5px 0 0; color:#64748b; font-size:13px;">Send a complaint or report an issue to the Swapy team.</p>
            </div>
            <button type="button" onclick="closeComplaintModal()" class="close-btn" aria-label="Close Reports Center">×</button>
        </div>

        <form method="POST" action="index.php">
            <input type="hidden" name="submit_complaint" value="1">
            <div class="filter-group">
                <label>Report Title</label>
                <input type="text" name="complaint_title" required>
            </div>

            <div class="filter-group">
                <label>Description</label>
                <textarea name="complaint_description" rows="5" required></textarea>
            </div>

            <button type="submit" style="width:100%; background:#ef4444; color:white; border:none; padding:12px; border-radius:10px; font-weight:600; cursor:pointer;">Submit Report</button>
        </form>
    </div>
</div>

<div id="deliveryModal" class="modal" onclick="if(event.target==this) closeDeliveryModal()">
    <div class="modal-content delivery-modal-content">
        <div class="delivery-modal-header">
            <div>
                <h2 style="margin:0;">Delivery Request</h2>
                <p style="margin-top:6px; color:#64748b; font-size:14px;">Fill your delivery information</p>
            </div>
            <button onclick="closeDeliveryModal()" style="border:none; background:#f1f5f9; width:38px; height:38px; border-radius:12px; cursor:pointer; font-weight:bold;">✕</button>
        </div>

        <form method="POST" action="index.php">
            <input type="hidden" name="create_delivery" value="1">
            <input type="hidden" name="item_id" id="delivery_item_id">
            <input type="hidden" name="target_user" id="delivery_target_user">

            <div class="filter-group">
                <label>Pickup Location</label>
                <input type="text" name="sender_location" placeholder="Enter your location" required>
            </div>

            <div class="filter-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="07xxxxxxxx" required>
            </div>

            <div class="filter-group">
                <label>Delivery Notes</label>
                <textarea name="delivery_notes" rows="4" placeholder="Building number, preferred time, extra details..."></textarea>
            </div>

            <button type="submit" class="delivery-submit-btn">
                Send Delivery Request
            </button>
        </form>
    </div>
</div>
<div id="confirmReportModal" class="modal" style="display: none; justify-content: center; align-items: center; z-index: 2000;">
    <div class="modal-content" style="max-width: 400px; height: auto; padding: 30px; display: block; text-align: center; border-radius: 20px;">
        <h3 style="margin-top: 0; color: #1e293b;">Are you sure?</h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 25px;">Do you really want to report this post? This action cannot be undone.</p>
        
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button id="cancelReportBtn" style="flex: 1; background: #f1f5f9; color: #475569; border: none; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer;">
                Cancel
            </button>
            <button id="confirmReportBtn" style="flex: 1; background: #ef4444; color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer;">
                Yes, Report
            </button>
        </div>
    </div>
</div>

<script>
let activeItem = null;
let activeTarget = "";
let chatInterval = null;
const myUsername = "<?php echo $currentUser; ?>";
const itemModal = document.getElementById("itemModal");
const itemModalContent = document.querySelector(".item-modal-content");
const chatResizer = document.getElementById("chat-resizer");
const defaultChatPanePercent = 60;
let chatPanePercent = defaultChatPanePercent;
let resizingChatPane = false;

function setChatPanePercent(percent) {
    chatPanePercent = Math.max(40, Math.min(75, Number(percent) || defaultChatPanePercent));
    itemModalContent.style.setProperty("--chat-pane-size", `${chatPanePercent}%`);
    chatResizer.setAttribute("aria-valuenow", String(Math.round(chatPanePercent)));
}

function updateChatPaneFromPointer(event) {
    const rect = itemModalContent.getBoundingClientRect();
    const stackedLayout = window.matchMedia("(max-width: 900px)").matches;
    const nextPercent = stackedLayout
        ? ((rect.bottom - event.clientY) / rect.height) * 100
        : ((rect.right - event.clientX) / rect.width) * 100;
    setChatPanePercent(nextPercent);
}

function stopChatPaneResize(event) {
    if (!resizingChatPane) return;
    resizingChatPane = false;
    document.body.classList.remove("chat-pane-resizing");
    if (event && chatResizer.hasPointerCapture?.(event.pointerId)) {
        chatResizer.releasePointerCapture(event.pointerId);
    }
}

chatResizer.addEventListener("pointerdown", event => {
    event.preventDefault();
    resizingChatPane = true;
    document.body.classList.add("chat-pane-resizing");
    chatResizer.setPointerCapture?.(event.pointerId);
    updateChatPaneFromPointer(event);
});

chatResizer.addEventListener("pointermove", event => {
    if (resizingChatPane) updateChatPaneFromPointer(event);
});

chatResizer.addEventListener("pointerup", stopChatPaneResize);
chatResizer.addEventListener("pointercancel", stopChatPaneResize);
chatResizer.addEventListener("dblclick", () => setChatPanePercent(defaultChatPanePercent));
chatResizer.addEventListener("keydown", event => {
    const stackedLayout = window.matchMedia("(max-width: 900px)").matches;
    const increaseChat = stackedLayout ? event.key === "ArrowUp" : event.key === "ArrowLeft";
    const decreaseChat = stackedLayout ? event.key === "ArrowDown" : event.key === "ArrowRight";

    if (event.key === "Home") {
        event.preventDefault();
        setChatPanePercent(40);
    } else if (event.key === "End") {
        event.preventDefault();
        setChatPanePercent(75);
    } else if (increaseChat || decreaseChat) {
        event.preventDefault();
        setChatPanePercent(chatPanePercent + (increaseChat ? 5 : -5));
    }
});
setChatPanePercent(defaultChatPanePercent);

/* =========================
OPEN ITEM
========================= */
function openItem(post) {
    activeItem = post;
    const isOwner = (post.author === myUsername);
    const isSwapListing = post.type === "Swap";
    const listingType = isSwapListing ? "Swap" : "Sell";
    const listingValue = isSwapListing
        ? (post.swap_req || "Open to suitable swaps")
        : `${Number(post.price || 0).toFixed(2)} JD`;

    document.getElementById('details-view').innerHTML = `
        <img 
            src="image.php?entity=post&id=${Number(post.id)}"
            style="
                width:100%;
                border-radius:18px;
                margin-bottom:20px;
                max-height:400px;
                object-fit:contain;
                background:#f1f5f9;
            "
        >
        <div class="item-detail-title-row">
            <h2>${post.title}</h2>
            <span class="item-detail-type ${isSwapListing ? "is-swap" : "is-sell"}">
                ${listingType}
            </span>
        </div>

        <div class="item-detail-summary">
            <div class="item-detail-field">
                <span class="item-detail-label">Offer type</span>
                <strong>${listingType}</strong>
            </div>
            <div class="item-detail-field">
                <span class="item-detail-label">${isSwapListing ? "Looking for" : "Price"}</span>
                <strong>${listingValue}</strong>
            </div>
            <div class="item-detail-field">
                <span class="item-detail-label">Category</span>
                <strong>${post.category || "Other"}</strong>
            </div>
            <div class="item-detail-field">
                <span class="item-detail-label">Seller</span>
                <strong>@${post.author}</strong>
            </div>
        </div>

        <div class="item-detail-description">
            <div class="item-detail-section-title">About this item</div>
            <div class="item-detail-description-text">${post.description}</div>
        </div>
        ${post.author !== myUsername ? `
            <div style="display:flex; justify-content:flex-end; padding-top: 10px;">
                <button onclick="reportPost(${post.id})" id="reportBtn"
                        style="background: #fef2f2 !important; 
                            color: #dc2626 !important; 
                            border: 1px solid #fecaca !important; 
                            padding: 10px 18px !important; 
                            border-radius: 12px !important; 
                            font-size: 13px !important; 
                            font-weight: 700 !important; 
                            cursor: pointer !important; 
                            display: inline-block !important; 
                            font-family: 'Poppins', sans-serif !important; 
                            text-align: center !important; 
                            transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease !important;"
                        onmouseover="this.style.backgroundColor='#fee2e2'; this.style.transform='scale(0.95)'; this.style.boxShadow='0 4px 10px rgba(220, 38, 38, 0.15)';"
                        onmouseout="this.style.backgroundColor='#fef2f2'; this.style.transform='scale(1)'; this.style.boxShadow='none';">
                    Report Item
                </button>
            </div>
        ` : ""}
    `;

    itemModal.style.display = 'flex';

    if(isOwner){
        showItemInbox(post.id);
    } else {
        activeTarget = post.author;
        showChatArea(post.id, post.author, false);
    }
}

/* =========================
SHOW ITEM INBOX
========================= */
function showItemInbox(itemId){
    document.getElementById('chat-title').innerText = "Item Inbox";
    const area = document.getElementById('chat-dynamic-area');
    area.innerHTML = `<p style="padding:20px;">Loading...</p>`;

    const fd = new FormData();
    fd.append("fetch_item_inbox", "1");
    fd.append("item_id", itemId);

    fetch("index.php", { method:"POST", body:fd })
    .then(res => res.json())
    .then(data => {
        if(data.length === 0){
            area.innerHTML = `<p style="padding:20px; color:#64748b;">No messages yet</p>`;
            return;
        }
        area.innerHTML = "";
        data.forEach(user => {
            area.innerHTML += `
                <div 
                    class="buyer-row"
                    onclick="showChatArea(${itemId}, '${user}', true)"
                    style="
                        padding:15px;
                        border-bottom:1px solid #e2e8f0;
                        cursor:pointer;
                        font-weight:600;
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                    "
                >
                    <span>@${user}</span>
                    <span style="color:var(--primary);">❯</span>
                </div>`;
        });
    });
}

/* =========================
SHOW CHAT
========================= */
function showChatArea(itemId, targetUser, canGoBack){
    activeTarget = targetUser;
    const profileLink = `
        <a 
            href="customer/other_profile.php?user=${targetUser}"
            style="
                text-decoration:none;
                font-size:11px;
                background:#fef3c7;
                color:#b45309;
                padding:5px 10px;
                border-radius:999px;
                margin-left:10px;
                font-weight:600;
            "
        >
            View Profile
        </a>`;

    document.getElementById('chat-title').innerHTML = "@" + targetUser + profileLink;
    const area = document.getElementById('chat-dynamic-area');

    const backBtn = canGoBack
        ? `
        <button class="chat-back"
            onclick="showItemInbox(${itemId})"
        >
            ❮ Back
        </button>`
        : "";

    area.innerHTML = `
        ${backBtn}
        <div 
            id="msgs-box"
            style="
                flex:1;
                overflow-y:auto;
                padding:15px;
                display:flex;
                flex-direction:column;
                gap:10px;
                background:#f8fafc;
            "
        ></div>

        <div class="chat-bottom-area">
        <div class="chat-composer">
            <label class="chat-attach" title="Attach an image">
                📎
                <input
                    type="file"
                    id="msgImage"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    onchange="showSelectedChatImage(this)"
                    style="display:none;"
                >
            </label>
            <span id="msgImageName" class="chat-file-name"></span>
            <input class="chat-input"
                type="text"
                id="msgInput"
                placeholder="Type message..."
                onkeypress="if(event.key==='Enter'){sendMsg(${itemId})}"
            >
            <button class="chat-send"
                onclick="sendMsg(${itemId})"
            >
                Send
            </button>
        </div>

        ${activeItem.author === myUsername ? `
        <div class="delivery-chat-action">
            <button class="delivery-chat-button"
                onclick="openDeliveryForm(${itemId}, '${targetUser}')"
            >
                <span aria-hidden="true">🚚</span>
                Request Delivery Coordination
            </button>
        </div>
        ` : ""}
        </div>
    `;

    loadMessages(itemId, targetUser);

    if(chatInterval){
        clearInterval(chatInterval);
    }
    chatInterval = setInterval(() => {
        loadMessages(itemId, targetUser);
    }, 2000);
}

/* =========================
LOAD MESSAGES
========================= */
function loadMessages(itemId, targetUser){
    const fd = new FormData();
    fd.append("fetch_msgs", "1");
    fd.append("item_id", itemId);
    fd.append("target_user", targetUser);

    fetch("index.php", { method:"POST", body:fd })
    .then(res => res.json())
    .then(data => {
        const msgsBox = document.getElementById("msgs-box");
        if(!msgsBox) return;
        msgsBox.innerHTML = "";

        data.forEach(msg => {
            const isMe = msg.sender === myUsername;
            const safeMessage = escapeChatHtml(msg.message || "").replace(/\n/g, "<br>");
            const imageMarkup = Number(msg.image_id) > 0
                ? `<img
                    src="image.php?entity=chat&id=${Number(msg.image_id)}"
                    alt="Attached image"
                    loading="lazy"
                    style="display:block; max-width:230px; max-height:230px; object-fit:cover; border-radius:10px; margin-bottom:${safeMessage ? '8px' : '0'};"
                >`
                : "";
            msgsBox.innerHTML += `
                <div style="display:flex; justify-content:${isMe ? 'flex-end' : 'flex-start'}; width:100%;">
                    <div style="
                        max-width:75%;
                        padding:12px 14px;
                        border-radius:14px;
                        word-break:break-word;
                        background:${isMe ? '#fbbf24' : '#e2e8f0'};
                        color:${isMe ? 'white' : '#111'};
                        font-size:14px;
                    ">
                        ${imageMarkup}${safeMessage}
                    </div>
                </div>`;
        });
        msgsBox.scrollTop = msgsBox.scrollHeight;
    })
    .catch(err => { console.log(err); });
}

/* =========================
SEND MESSAGE
========================= */
function sendMsg(itemId){
    const input = document.getElementById("msgInput");
    if(!input) return;
    const message = input.value.trim();
    const imageInput = document.getElementById("msgImage");
    const image = imageInput && imageInput.files.length ? imageInput.files[0] : null;
    if(message === "" && !image) return;

    if(image && image.size > 8 * 1024 * 1024){
        alert("Please choose an image smaller than 8 MB.");
        return;
    }

    const fd = new FormData();
    fd.append("send_msg", "1");
    fd.append("item_id", itemId);
    fd.append("target_user", activeTarget);
    fd.append("message", message);
    if(image) fd.append("image", image);

    fetch("index.php", { method:"POST", body:fd })
    .then(res => res.json())
    .then(data => {
        if(!data.ok){
            alert(data.error || "The message could not be sent.");
            return;
        }
        input.value = "";
        if(imageInput) imageInput.value = "";
        loadMessages(itemId, activeTarget);
    })
    .catch(() => alert("The message could not be sent. Please try again."));
}

function showSelectedChatImage(input){
    const imageName = document.getElementById("msgImageName");
    if(!imageName) return;
    imageName.textContent = input.files.length ? input.files[0].name : "";
}

function escapeChatHtml(value){
    return String(value).replace(/[&<>"']/g, function(character){
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[character];
    });
}

/* =========================
REPORT LISTING VIA AJAX
========================= */
function reportPost(postId) {
    const confirmModal = document.getElementById('confirmReportModal');
    confirmModal.style.display = 'flex';

    const confirmBtn = document.getElementById('confirmReportBtn');
    const cancelBtn = document.getElementById('cancelReportBtn');

    const newConfirmBtn = confirmBtn.cloneNode(true);
    const newCancelBtn = cancelBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

    newCancelBtn.onclick = function() {
        confirmModal.style.display = 'none';
    };

    newConfirmBtn.onclick = function() {
        confirmModal.style.display = 'none';

        const fd = new FormData();
        fd.append('report_post', '1');
        fd.append('post_id', postId);

        fetch('index.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(data => {
            if(data.trim() === "success") {
                const btn = document.getElementById('reportBtn');
                if(btn) {
                    btn.innerText = "⚠ Reported";
                    btn.disabled = true;
                    btn.style.opacity = "0.6";
                    btn.style.background = "#fee2e2";
                }
            }
        });
    };
}

/* =========================
MODALS MANAGEMENT
========================= */
function openAddModal(){
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal(){
    document.getElementById('addModal').style.display = 'none';
}

function closeModals(){
    document.getElementById('itemModal').style.display = 'none';
    resizingChatPane = false;
    document.body.classList.remove("chat-pane-resizing");
    if(chatInterval){
        clearInterval(chatInterval);
    }
}

/* =========================
POST TYPE TOGGLE
========================= */
function togglePrice(type){
    if(type === 'Sell'){
        document.getElementById('priceField').style.display = 'block';
        document.getElementById('swapField').style.display = 'none';
    } else {
        document.getElementById('priceField').style.display = 'none';
        document.getElementById('swapField').style.display = 'block';
    }
}

/* =========================
IMAGE BASE64
========================= */
function encodeImageFileAsURL(element){
    const file = element.files[0];
    const reader = new FileReader();
    reader.onloadend = function(){
        document.getElementById('image_base64_input').value = reader.result;
    }
    if(file){
        reader.readAsDataURL(file);
    }
}

function openComplaintModal(){
    document.getElementById('complaintModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeComplaintModal(){
    document.getElementById('complaintModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

/* =========================
FILTER + SEARCH
========================= */
function toggleFilterMenu(event) {
    event.stopPropagation();

    const filterMenu = document.getElementById('filterMenu');
    const filterToggle = document.getElementById('filterToggle');
    const isOpen = filterMenu.classList.toggle('is-open');

    filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function applyFilters() {
    const category = document.getElementById('filterCategory').value;
    const type = document.getElementById('filterType').value;
    const search = document.getElementById('instantSearch').value.toLowerCase().trim();

    document.querySelectorAll('.item-card').forEach(card => {
        const matchCategory = !category || card.dataset.category === category;
        const matchType = !type || card.dataset.type === type;
        const title = card.querySelector('h4') ? card.querySelector('h4').innerText.toLowerCase() : '';
        const matchSearch = !search || title.includes(search);

        card.style.display = (matchCategory && matchType && matchSearch) ? '' : 'none';
    });
}

document.addEventListener('click', function(event) {
    const filterControl = document.querySelector('.filter-control');
    const filterMenu = document.getElementById('filterMenu');
    const filterToggle = document.getElementById('filterToggle');

    if (filterControl && !filterControl.contains(event.target)) {
        filterMenu.classList.remove('is-open');
        filterToggle.setAttribute('aria-expanded', 'false');
    }
});

function openDeliveryForm(itemId, targetUser){
    document.getElementById("deliveryModal").style.display = "flex";
    document.getElementById("delivery_item_id").value = itemId;
    document.getElementById("delivery_target_user").value = targetUser;
}

function closeDeliveryModal(){
    document.getElementById("deliveryModal").style.display = "none";
}
</script>
</body>
</html>