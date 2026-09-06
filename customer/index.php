<?php

include '../auth.php';
protect_page(0); // Only allow customers to access this page


$currentUser = $_SESSION['swapy_session'];

$user_query = mysqli_query($conn, "SELECT * FROM users WHERE username='$currentUser'");
$userData = mysqli_fetch_assoc($user_query);

$userPic = !empty($userData['pic'])
    ? $userData['pic']
    : "https://ui-avatars.com/api/?name=".urlencode($userData['name'])."&background=fbbf24&color=fff";


/* =========================
FETCH ITEM INBOX
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fetch_item_inbox'])) {

    $item_id = (int)$_POST['item_id'];

    $query = "
        SELECT DISTINCT sender 
        FROM chats 
        WHERE item_id = $item_id 
        AND receiver = '$currentUser'
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

    $query = "
        SELECT * FROM chats
        WHERE item_id = $item_id
        AND (
            (sender='$currentUser' AND receiver='$target')
            OR
            (sender='$target' AND receiver='$currentUser')
        )
        ORDER BY id ASC
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

    $message = mysqli_real_escape_string($conn, $_POST['message']);

    $target = mysqli_real_escape_string($conn, $_POST['target_user']);

    if(!empty($message) && !empty($target)) {

        mysqli_query($conn, "
            INSERT INTO chats (
                item_id,
                sender,
                receiver,
                message
            )
            VALUES (
                $item_id,
                '$currentUser',
                '$target',
                '$message'
            )
        ");
    }

    exit;
}


/* =========================
PUBLISH POST
========================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['publish_post'])) {

    $title = mysqli_real_escape_string($conn, $_POST['title']);

    $category = mysqli_real_escape_string($conn, $_POST['category']);

    $type = mysqli_real_escape_string($conn, $_POST['type']);

    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $price = max(0, (float)$_POST['price']);

    $swap_item = mysqli_real_escape_string($conn, $_POST['swap_req']);

    $image_data = $_POST['image_base64'];

    mysqli_query($conn, "
        INSERT INTO posts (
            author,
            title,
            category,
            type,
            price,
            swap_req,
            description,
            img,
            status
        )
        VALUES (
            '$currentUser',
            '$title',
            '$category',
            '$type',
            '$price',
            '$swap_item',
            '$description',
            '$image_data',
            'pending'
        )
    ");

    header("Location: index.php");
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

    $receiverData = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT id FROM users WHERE username='$target_user'"
    ));

    $sender_id = $senderData['id'];
    $receiver_id = $receiverData['id'];

    mysqli_query($conn, "
        INSERT INTO delivery_request (
            sender_id,
            receiver_id,
            item_id,
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
    <link rel="stylesheet" href="index.css">
</head>

<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo">SWAPY</a>
    <div class="nav-right">
        <a href="profile.php" class="profile-link">
            <span style="font-weight:600; font-size:14px;">
                <?php echo $userData['name']; ?>
            </span>
            <img src="<?php echo $userPic; ?>" class="header-avatar">
        </a>
        <button 
            onclick="location.href='../logout.php'"
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

<div class="search-wrapper">
    <div class="search-container">
        <div class="search-form">
            <div class="input-group">
                <span class="search-icon">🔍</span>
                <input 
                    type="text"
                    id="instantSearch"
                    placeholder="Search for treasures..."
                    autocomplete="off"
                    oninput="applyFilters()"
                >
            </div>
        </div>
    </div>
</div>

<div class="main-layout">
    <aside class="sidebar">
        <h4>Explore</h4>
        <div class="filter-group">
            <label>Category</label>
            <select id="filterCategory" onchange="applyFilters()">
                <option value="">All Categories</option>
                <option value="Electronics">Electronics</option>
                <option value="Fashion">Fashion</option>
                <option value="Home">Home</option>
                <option value="Others">Others</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Offer Type</label>
            <select id="filterType" onchange="applyFilters()">
                <option value="">All Types</option>
                <option value="Sell">Sell</option>
                <option value="Swap">Swap</option>
            </select>
        </div>
    </aside>

    <div class="feed" id="feed-container">
        <?php
        $posts = mysqli_query($conn, "
            SELECT * FROM posts
            WHERE status='approved'
            OR status='reported'
            ORDER BY id DESC
        ");

        while($row = mysqli_fetch_assoc($posts)) {
            $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            echo "
            <div class='item-card' data-category='{$row['category']}' data-type='{$row['type']}' onclick='openItem($json_data)'>
                <div class='card-tag'>
                    {$row['category']}
                </div>
                <div class='item-img-wrap'>
                    <img src='{$row['img']}' class='item-img'>
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
                    ".(
                        $row['status'] == 'reported'
                        ?
                        "<div style='margin-top:10px; color:#ef4444; font-size:12px; font-weight:600;'>
                            ⚠ Reported Post
                        </div>"
                        :
                        ""
                    )."
                </div>
            </div>
            ";
        }
        ?>
    </div>
</div>

<div id="itemModal" class="modal" onclick="if(event.target==this) closeModals()">
    <div class="modal-content">
        <div class="details-pane" id="details-view"></div>
        <div class="chat-pane">
            <div class="chat-header">
                <span id="chat-title">Chat</span>
                <button onclick="closeModals()" class="close-btn">
                    Close
                </button>
            </div>
            <div 
                id="chat-dynamic-area"
                style="
                    flex:1;
                    display:flex;
                    flex-direction:column;
                    overflow:hidden;
                "
            ></div>
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

        <form action="index.php" method="POST">
            <input type="hidden" name="publish_post" value="1">
            <div class="filter-group">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>

            <div class="filter-group">
                <label>Item Image</label>
                <input type="file" accept="image/*" required onchange="encodeImageFileAsURL(this)">
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
            <h3 style="margin:0;">Complaint Center</h3>
        </div>

        <form method="POST" action="index.php">
            <input type="hidden" name="submit_complaint" value="1">
            <div class="filter-group">
                <label>Complaint Title</label>
                <input type="text" name="complaint_title" required>
            </div>

            <div class="filter-group">
                <label>Description</label>
                <textarea name="complaint_description" rows="5" required></textarea>
            </div>

            <button type="submit" style="width:100%; background:#ef4444; color:white; border:none; padding:12px; border-radius:10px; font-weight:600; cursor:pointer;">Submit Complaint</button>
        </form>
    </div>
</div>

<div style="width:100%; padding:40px 0; display:flex; justify-content:center;">
    <button onclick="openComplaintModal()" 
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
        ⚠ Complaint  Center
    </button>
</div>

<div id="deliveryModal" class="modal" onclick="if(event.target==this) closeDeliveryModal()">
    <div class="modal-content" style="max-width:500px; padding:30px; display:block; border-radius:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
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

            <button type="submit" style="width:100%; background:#fbbf24; color:white; border:none; padding:14px; border-radius:16px; font-size:15px; font-weight:600; cursor:pointer; margin-top:10px;">
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

/* =========================
OPEN ITEM
========================= */
function openItem(post) {
    activeItem = post;
    const isOwner = (post.author === myUsername);

    document.getElementById('details-view').innerHTML = `
        <img 
            src="${post.img}"
            style="
                width:100%;
                border-radius:18px;
                margin-bottom:20px;
                max-height:400px;
                object-fit:contain;
                background:#f1f5f9;
            "
        >
        <h2 style="margin-bottom:10px;">
            ${post.title}
        </h2>
        <div 
            style="
                padding:15px;
                background:#f8fafc;
                border-radius:12px;
                line-height:1.6;
                margin-bottom:15px;
            "
        >
            ${post.description}
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

    document.getElementById('itemModal').style.display = 'flex';

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
            href="other_profile.php?user=${targetUser}"
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
        <button 
            onclick="showItemInbox(${itemId})"
            style="
                margin:10px;
                background:none;
                border:none;
                color:blue;
                cursor:pointer;
                font-size:12px;
                text-align:left;
            "
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

        <div 
            style="
                display:flex;
                gap:10px;
                padding:15px;
                border-top:1px solid #e2e8f0;
                align-items:flex-end;
                background:white;
            "
        >
            <input 
                type="text"
                id="msgInput"
                placeholder="Type message..."
                style="
                    flex:1;
                    padding:12px;
                    border-radius:10px;
                    border:1px solid #cbd5e1;
                    outline:none;
                "
                onkeypress="if(event.key==='Enter'){sendMsg(${itemId})}"
            >
            <button 
                onclick="sendMsg(${itemId})"
                style="
                    background:var(--primary);
                    color:white;
                    border:none;
                    padding:10px 18px;
                    border-radius:10px;
                    cursor:pointer;
                "
            >
                Send
            </button>
        </div>

        ${activeItem.author === myUsername ? `
        <div style="width:100%; display:flex; justify-content:center; align-items:center; padding:10px 0;">
            <button 
                onclick="openDeliveryForm(${itemId}, '${targetUser}')"
                onmouseover="
                    this.style.background='#facc15';
                    this.style.transform='translateY(-2px)';
                    this.style.boxShadow='0 8px 18px rgba(250,204,21,0.30)';
                "
                onmousedown="
                    this.style.background='#eab308';
                    this.style.transform='scale(0.98)';
                "
                onmouseup="
                    this.style.background='#facc15';
                    this.style.transform='scale(1)';
                "
                onmouseleave="
                    this.style.background='#fbbf24';
                    this.style.transform='scale(1)';
                    this.style.boxShadow='0 4px 12px rgba(251,191,36,0.22)';
                "
                style="
                    width:400px;
                    height:45px;
                    border:none;
                    border-radius:14px;
                    background:#fbbf24;
                    color:white;
                    font-size:15px;
                    font-weight:600;
                    cursor:pointer;
                    box-shadow:0 4px 12px rgba(251,191,36,0.22);
                    transition:0.2s;
                "
            >
                Request Delivery Coordination
            </button>
        </div>
        ` : ""}
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
                        ${msg.message}
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
    if(message === "") return;

    const fd = new FormData();
    fd.append("send_msg", "1");
    fd.append("item_id", itemId);
    fd.append("target_user", activeTarget);
    fd.append("message", message);

    fetch("index.php", { method:"POST", body:fd })
    .then(() => {
        input.value = "";
        loadMessages(itemId, activeTarget);
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
