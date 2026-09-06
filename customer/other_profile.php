<?php

include '../auth.php';
protect_page(0); // Only allow customers to access this page


if (!isset($_GET['user'])) {
    header("Location: index.php");
    exit();
}

$currentUser = $_SESSION['swapy_session'];
$targetUser = mysqli_real_escape_string($conn, $_GET['user']);
$user_res = mysqli_query($conn, "SELECT * FROM users WHERE username = '$targetUser'");
$user_data = mysqli_fetch_assoc($user_res);

if (!$user_data) {
    echo "User not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user_data['name']); ?>'s Profile | Swapy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css"> 
    <style>
        :root {
            --primary: #fbbf24;
            --primary-dark: #b45309;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f5f9;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        .header-nav {
            background: white;
            padding: 18px 8%;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .back-link {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--primary-dark);
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
        }

        .profile-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.04);
            height: fit-content;
        }

        .profile-cover {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            height: 100px;
        }

        .profile-info {
            padding: 0 24px 30px;
            text-align: center;
            margin-top: -50px;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            background: white;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .profile-info h3 {
            margin: 12px 0 4px;
            font-size: 20px;
            font-weight: 600;
        }

        .profile-username {
            color: var(--text-muted);
            font-size: 13px;
            margin: 0 0 16px 0;
            font-weight: 500;
        }

        .bio-text {
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
            background: var(--bg-light);
            padding: 12px 16px;
            border-radius: 16px;
            margin: 0;
        }

        .listings-container {
            background: white;
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.04);
        }

        .listings-title {
            margin: 0 0 25px 0;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 2px solid var(--bg-light);
            padding-bottom: 12px;
        }

        /* MATCHES FEED GRID STRUCTURE FROM INDEX */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        /* COHESIVE FIXES FOR THE ITEM CARD EXTENDED OVERRIDES */
        .items-grid .item-card {
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .items-grid .item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }

        .items-grid .item-card .item-info h4 {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 42px; /* Uniform height constraints */
            margin: 0 0 8px 0 !important;
        }

        /* Modal & Chat Frames */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            justify-content: center; align-items: center;
            z-index: 1000;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 900px;
            height: 80vh;
            border-radius: 24px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        @media (max-width: 640px) {
            .modal-content {
                flex-direction: column;
                height: 90vh;
            }
        }

        .details-pane {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
        }

        .chat-pane {
            width: 380px;
            display: flex;
            flex-direction: column;
            background: white;
        }

        @media (max-width: 640px) {
            .chat-pane {
                width: 100%;
                flex: 1;
            }
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        #chat-title {
            font-weight: 600;
            font-size: 15px;
        }

        .close-btn {
            background-color: #F6D6D6; 
            color:#ef4444 ; 
            font-weight: bold; 
            border: none; 
            border-radius: 10px; 
            padding: 10px;
        }
        .close-btn:hover {
            transform: scale(0.95); 
            background-color: #FEF2F2;
        }

        #msgs-box {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: var(--bg-light);
        }

        .msg-container {
            display: flex;
            width: 100%;
        }

        .msg-container.me { justify-content: flex-end; }
        .msg-container.them { justify-content: flex-start; }

        .msg-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
        }

        .me .msg-bubble {
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 2px;
        }

        .them .msg-bubble {
            background: #e2e8f0;
            color: #0f172a;
            border-bottom-left-radius: 2px;
        }

        .chat-footer {
            display: flex;
            gap: 10px;
            padding: 15px;
            border-top: 1px solid var(--border-color);
            background: white;
        }

        .chat-footer input {
            flex: 1;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            outline: none;
            font-family: inherit;
            font-size: 14px;
        }

        .chat-footer button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 16px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .chat-footer button:hover {
            background: #f59e0b;
        }
        .report-btn {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .report-btn:hover {
            background: #fee2e2;
            transform: scale(0.96);
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.15);
        }
        .report-btn:hover {
            transform: scale(0.95);
            background-color: #FEF2F2;
        }
        .report-btn {
            transition: transform 0.2s ease, background-color 0.2s ease;
        }
    </style>
</head>
<body>

    <div class="header-nav">
        <a href="index.php" class="back-link">❮ Back to Marketplace</a>
    </div>

    <div class="container">
        <div class="profile-card">
            <div class="profile-cover"></div>
            <div class="profile-info">
                <img src="<?php echo $user_data['pic'] ?: 'https://ui-avatars.com/api/?name='.urlencode($user_data['name']).'&background=fbbf24&color=fff'; ?>" class="profile-img">
                <h3><?php echo htmlspecialchars($user_data['name']); ?></h3>
                <p class="profile-username">@<?php echo htmlspecialchars($targetUser); ?></p>
                <div class="bio-text"><?php echo $user_data['bio'] ? htmlspecialchars($user_data['bio']) : 'No bio available.'; ?></div>
            </div>
        </div>
        
        <div class="listings-container">
            <h3 class="listings-title">📦 Items by <?php echo htmlspecialchars($user_data['name']); ?></h3>
            
            <?php
            $items = mysqli_query($conn, "SELECT * FROM posts WHERE author = '$targetUser' AND (status='approved' OR status='reported') ORDER BY id DESC");
            if(mysqli_num_rows($items) == 0): 
                echo "<p style='color:var(--text-muted); font-size:14px; text-align:center; padding:40px 0;'>No listings available yet.</p>";
            else:
            ?>
                <div class="items-grid">
                    <?php 
                    while($row = mysqli_fetch_assoc($items)): 
                        $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class='item-card' onclick='openItem(<?php echo $json_data; ?>)'>
                        <div class='card-tag'>
                            <?php echo htmlspecialchars($row['category']); ?>
                        </div>
                        <div class='item-img-wrap'>
                            <img src='<?php echo $row['img']; ?>' class='item-img'>
                        </div>
                        <div class='item-info' style='padding:10px;'>
                            <h4 style='margin:0 0 10px 0; font-size:15px;'>
                                <?php echo htmlspecialchars($row['title']); ?>
                            </h4>
                            <div style='font-weight:700; color:var(--primary-dark);'>
                                <?php echo ($row['type'] == 'Sell' ? $row['price'].' JD' : '🔄 Swap'); ?>
                            </div>
                            <small style='color:var(--text-muted);'>
                                @<?php echo htmlspecialchars($row['author']); ?>
                            </small>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="itemModal" class="modal" onclick="if(event.target==this) closeModals()">
        <div class="modal-content">
            
            <div class="details-pane" id="details-view"></div>
            
            <div class="chat-pane">
                <div class="chat-header">
                    <span id="chat-title">Chat</span>
                    <button onclick="closeModals()" class="close-btn">Close</button>
                </div>
                <div id="chat-dynamic-area" style="flex:1; display:flex; flex-direction:column; overflow:hidden;"></div>
            </div>

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
    OPEN ITEM DETAILS
    ========================= */
    function openItem(post) {
        activeItem = post;
        const isOwner = (post.author === myUsername);
        
        document.getElementById('details-view').innerHTML = `
            <img src="${post.img}" style="width:100%; border-radius:18px; margin-bottom:20px; max-height:350px; object-fit:contain; background:#f1f5f9;">
            <h2 style="margin:0 0 5px 0; font-size:22px;">${post.title}</h2>
            <p style="color:var(--text-muted); font-size:14px; margin:0 0 15px 0;">Category: <b>${post.category}</b></p>
            <div style="padding:15px; background:var(--bg-light); border-radius:12px; line-height:1.6; font-size:14px; color:#334155; margin-bottom:20px;">
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

        if (isOwner) { 
            showItemInbox(post.id); 
        } else { 
            activeTarget = post.author; 
            showChatArea(post.id, post.author, false); 
        }
    }

    /* =========================
    SHOW INBOX (IF OWNER VIEWING OWN POST)
    ========================= */
    function showItemInbox(itemId) {
        document.getElementById('chat-title').innerText = "Item Inbox";
        const area = document.getElementById('chat-dynamic-area');
        area.innerHTML = `<p style="padding:20px; color:var(--text-muted); font-size:14px;">Loading buyers...</p>`;
        
        const fd = new FormData();
        fd.append("fetch_item_inbox", "1");
        fd.append("item_id", itemId);

        fetch("index.php", { method: "POST", body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.length === 0){
                area.innerHTML = `<p style="padding:20px; color:var(--text-muted); font-size:14px;">No messages yet.</p>`;
                return;
            }
            area.innerHTML = "";
            data.forEach(user => {
                area.innerHTML += `
                    <div onclick="showChatArea(${itemId}, '${user}', true)" style="padding:15px; border-bottom:1px solid var(--border-color); cursor:pointer; font-weight:600; font-size:14px; display:flex; justify-content:space-between; align-items:center;">
                        <span>@${user}</span>
                        <span style="color:var(--primary-dark);">❯</span>
                    </div>`;
            });
        });
    }

    /* =========================
    RENDER CHAT ROOM INTERFACE
    ========================= */
    function showChatArea(itemId, target, canGoBack) {
        activeTarget = target;
        document.getElementById('chat-title').innerText = "@" + target;
        const area = document.getElementById('chat-dynamic-area');
        
        const backBtn = canGoBack ? `
            <button onclick="showItemInbox(${itemId})" style="margin:10px; background:none; border:none; color:var(--primary-dark); cursor:pointer; font-size:13px; font-weight:600; text-align:left;">
                ❮ Back to Inbox
            </button>` : "";

        area.innerHTML = `
            ${backBtn}
            <div id="msgs-box"></div>
            <div class="chat-footer">
                <input type="text" id="msg-inp" placeholder="Type a message..." onkeypress="if(event.key==='Enter') sendMsg()">
                <button onclick="sendMsg()">Send</button>
            </div>`;
        
        loadMsgs(itemId, target);

        if(chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(() => { loadMsgs(itemId, target); }, 2000);
    }

    /* =========================
    POLL & RENDER INCOMING CHATS
    ========================= */
    function loadMsgs(itemId, target) {
        const fd = new FormData();
        fd.append('fetch_msgs', '1');
        fd.append('item_id', itemId);
        fd.append('target_user', target);
        
        fetch('index.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('msgs-box');
            if(!box) return;
            box.innerHTML = "";
            data.forEach(m => { 
                const isMe = (m.sender === myUsername);
                box.innerHTML += `
                    <div class="msg-container ${isMe ? 'me' : 'them'}">
                        <div class="msg-bubble">${m.message}</div>
                    </div>`; 
            });
            box.scrollTop = box.scrollHeight;
        });
    }

    /* =========================
    SEND DISPATCH MESSAGE
    ========================= */
    function sendMsg() {
        const inp = document.getElementById('msg-inp');
        if(!inp || !inp.value.trim()) return;
        
        const fd = new FormData();
        fd.append('send_msg', '1');
        fd.append('item_id', activeItem.id);
        fd.append('message', inp.value.trim());
        fd.append('target_user', activeTarget);
        
        fetch('index.php', { method: 'POST', body: fd })
        .then(() => { 
            inp.value = ""; 
            loadMsgs(activeItem.id, activeTarget); 
        });
    }

    /* =========================
    REPORT LISTING AJAX
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
    CLOSE CONTROL INTERFACES
    ========================= */
    function closeModals() { 
        document.getElementById('itemModal').style.display = 'none'; 
        if(chatInterval) clearInterval(chatInterval);
    }
    </script>
</body>
</html>