<?php

include '../auth.php';
protect_page(0); // Only allow customers to access this page


$me = $_SESSION['swapy_session'];

$user_res = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE username = '$me'"
);

$user_data = mysqli_fetch_assoc($user_res);

if (isset($_POST['update_profile'])) {

    $newBio = mysqli_real_escape_string(
        $conn,
        $_POST['bio']
    );

    $newPic = $_POST['pic_base64'];

    $sql = "UPDATE users SET bio='$newBio'";

    if (!empty($newPic)) {
        $sql .= ", pic='$newPic'";
    }

    $sql .= " WHERE username='$me'";

    mysqli_query($conn, $sql);

    header("Location: profile.php");
    exit();
}

if (isset($_POST['edit_item'])) {

    $p_id = (int)$_POST['p_id'];

    $title = mysqli_real_escape_string(
        $conn,
        $_POST['title']
    );

    $desc = mysqli_real_escape_string(
        $conn,
        $_POST['description']
    );

    mysqli_query(
        $conn,
        "
        UPDATE posts
        SET title='$title',
            description='$desc'
        WHERE id=$p_id
        AND author='$me'
        "
    );

    header("Location: profile.php");
    exit();
}

if (isset($_GET['delete_id'])) {

    $del_id = (int)$_GET['delete_id'];

    mysqli_query(
        $conn,
        "
        DELETE FROM posts
        WHERE id = $del_id
        AND author = '$me'
        "
    );

    header("Location: profile.php");
    exit();
}
    /* ==================================
    CONFIRM DELIVERY & UPDATE STATUS
    ================================== */
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delivery'])) {
        $delivery_id = (int)$_POST['delivery_id'];
        $receiver_location = mysqli_real_escape_string($conn, $_POST['receiver_location']);
        $receiver_phone = mysqli_real_escape_string($conn, $_POST['receiver_phone']); 

        $delivery_info_query = mysqli_query($conn, "SELECT sender_id FROM delivery_request WHERE id = $delivery_id");
        $delivery_info = mysqli_fetch_assoc($delivery_info_query);
        $sender_id = $delivery_info['sender_id'];

        $update_query = "
            UPDATE delivery_request 
            SET receiver_location = '$receiver_location',
                delivery_status = 'waiting_driver',
                receiver_done = 1
            WHERE id = $delivery_id
        ";

        if (mysqli_query($conn, $update_query)) {

    // Fetch item_id to update post status
    $post_id_query = mysqli_query($conn, "SELECT item_id FROM delivery_request WHERE id = $delivery_id");
    $post_id_row = mysqli_fetch_assoc($post_id_query);
    $post_id = $post_id_row['item_id'];

    // Update post status to sold
    mysqli_query($conn, "UPDATE posts SET status = 'sold' WHERE id = '$post_id'");
    
            mysqli_query($conn, "
                INSERT INTO notification (
                    user_id,
                    message,
                    type,
                    related_id
                )
                VALUES (
                    '$sender_id',
                    '@$me confirmed the delivery request! Your order has been sent to delivery coordination.',
                    'delivery',
                    '$delivery_id'
                )
            ");

            header("Location: profile.php?success=delivery_confirmed");
        } else {
            header("Location: profile.php?error=failed_to_confirm");
        }
        exit();
    }

    /* ==================================
    AJAX: FETCH DELIVERY DETAILS FOR MODAL
    ================================== */
    if (isset($_POST['get_delivery_details'])) {

        header('Content-Type: application/json');

        $delivery_id = (int)$_POST['delivery_id'];

        $res = mysqli_query(
            $conn,
            "SELECT * FROM delivery_request WHERE id = $delivery_id"
        );

        if (!$res) {
            echo json_encode([
                "error" => mysqli_error($conn)
            ]);
            exit();
        }

        $details = mysqli_fetch_assoc($res);

        if (!$details) {
            echo json_encode([
                "error" => "No delivery found"
            ]);
            exit();
        }

        echo json_encode($details);
        exit();
    }
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>My Profile | Swapy</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="profile.css">

</head>

<body>

    <div class="header-nav">

        <a href="index.php" class="back-link">
            ❮ Back to Marketplace
        </a>

        <button
            class="btn btn-danger"
            onclick="location.href='../logout.php'"
        >
            Log Out
        </button>

    </div>

    <div class="container">

        <div class="sidebar">

            <div class="sidebar-card">

                <div class="sidebar-header"></div>

                <div class="profile-info">

                    <img
                        src="<?php echo $user_data['pic'] ?: 'https://ui-avatars.com/api/?name='.urlencode($user_data['name']); ?>"
                        class="profile-img"
                    >

                    <h3 style="margin:10px 0 0;">
                        <?php echo $user_data['name']; ?>
                    </h3>

                    <p style="
                        color:#94a3b8;
                        font-size:13px;
                        margin:0;
                    ">
                        @<?php echo $me; ?>
                    </p>

                    <div class="bio-text">

                        <?php
                        echo $user_data['bio']
                        ?: 'Add a bio to tell people about yourself.';
                        ?>

                    </div>

                    <button
                        class="btn btn-primary"
                        style="width:100%;"
                        onclick="
                            document.getElementById(
                                'pModal'
                            ).style.display='flex'
                        "
                    >
                        Edit Profile
                    </button>

                </div>

            </div>

        </div>

        <div id="pModal" class="modal">

            <div class="modal-content">

                <h3>Edit Profile</h3>

                <form method="POST" action="profile.php">

                    <label style="
                        font-size:13px;
                        color:#64748b;
                    ">
                        Bio
                    </label>

                    <textarea
                        name="bio"
                        rows="4"
                    ><?php echo htmlspecialchars($user_data['bio']); ?></textarea>

                    <label style="
                        font-size:13px;
                        color:#64748b;
                    ">
                        Profile Picture
                    </label>

                    <input
                        type="file"
                        accept="image/*"
                        onchange="encodeImage(this)"
                    >

                    <input
                        type="hidden"
                        name="pic_base64"
                        id="pic_base64"
                    >

                    <div style="
                        display:flex;
                        gap:10px;
                        margin-top:10px;
                    ">

                        <button
                            type="submit"
                            name="update_profile"
                            class="btn btn-primary"
                            style="flex:1;"
                        >
                            Save Changes
                        </button>

                        <button
                            type="button"
                            class="btn btn-outline"
                            onclick="
                                document.getElementById(
                                    'pModal'
                                ).style.display='none'
                            "
                        >
                            Cancel
                        </button>

                    </div>

                </form>

            </div>

        </div>

        <div class="content-section">

            <div class="card">

                <h3>📦 My Listings</h3>

                <?php

                $my_posts = mysqli_query(
                    $conn,
                    "
                    SELECT *
                    FROM posts
                    WHERE author = '$me'
                    "
                );

                if(mysqli_num_rows($my_posts) == 0){

                    echo "
                    <p style='color:gray; font-size:13px;'>
                        You haven't posted anything yet.
                    </p>
                    ";

                }

                while($p = mysqli_fetch_assoc($my_posts)):

                ?>

                <div class="item-row">

                    <img src="<?php echo $p['img']; ?>">

                    <div style="flex:1;">

                        <b><?php echo $p['title']; ?></b>

                        <small style="color:gray;">
                            <?php echo $p['category']; ?>
                        </small>

                    </div>

                    <button
                        class="btn btn-outline"
                        onclick='openEditModal(<?php echo json_encode($p); ?>)'
                    >
                        Edit
                    </button>

                    <button
                        class="btn btn-danger"
                        onclick="
                            if(confirm('Delete?'))
                            location.href='profile.php?delete_id=<?php echo $p['id']; ?>'
                        "
                    >
                        Delete
                    </button>

                </div>

                <?php endwhile; ?>

            </div>

            <div class="card">

                <h3>💬 Active Discussions</h3>

                <?php

                $inbox_res = mysqli_query(
                    $conn,
                    "
                    SELECT
                        c.item_id,
                        p.title,
                        p.img,
                        CASE
                            WHEN c.sender = '$me'
                            THEN c.receiver
                            ELSE c.sender
                        END as chat_partner
                    FROM chats c
                    JOIN posts p
                    ON c.item_id = p.id
                    WHERE c.sender = '$me'
                    OR c.receiver = '$me'
                    GROUP BY c.item_id, chat_partner
                    ORDER BY c.id DESC
                    "
                );

                if(mysqli_num_rows($inbox_res) == 0){

                    echo "
                    <p style='color:gray; font-size:13px;'>
                        No active chats yet.
                    </p>
                    ";

                }

                while($row = mysqli_fetch_assoc($inbox_res)):

                ?>

                <div
                    class="item-row"
                    style="cursor:pointer;"
                    onclick='openInbox(
                        <?php echo json_encode($row); ?>,
                        "<?php echo $row['chat_partner']; ?>"
                    )'
                >

                    <img src="<?php echo $row['img']; ?>">

                    <div style="flex:1;">

                        <b><?php echo $row['title']; ?></b>

                        <small style="color:gray;">
                            Chatting with
                            @<?php echo $row['chat_partner']; ?>
                        </small>

                    </div>

                    <span style="
                        color:var(--primary);
                        font-size:20px;
                    ">
                        ❯
                    </span>

                </div>

                <?php endwhile; ?>

            </div>

            <div class="card">

                <h3>🔔 Notifications</h3>

                <?php

                $notif_res = mysqli_query($conn, "
                    SELECT n.*, d.delivery_status, d.sender_id, d.receiver_id 
                    FROM notification n
                    LEFT JOIN delivery_request d ON n.related_id = d.id AND n.type = 'delivery'
                    WHERE n.user_id = (SELECT id FROM users WHERE username='$me')
                    ORDER BY n.id DESC
                ");

                if(!$notif_res){
                    die(mysqli_error($conn));
                }

                if(mysqli_num_rows($notif_res) == 0){

                    echo "
                    <p style='color:gray; font-size:13px;'>
                        No notifications yet.
                    </p>
                    ";

                }

            while($n = mysqli_fetch_assoc($notif_res)): 
                $my_id = $user_data['id']; 
            ?>

            <div style="padding: 15px 20px; border: 1px solid #e2e8f0; border-radius: 16px; margin-bottom: 12px; background: #fffdf5; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                <div>
                    <div style="font-weight: 600; margin-bottom: 4px; color: #1e293b;">
                        <?php 
                            echo htmlspecialchars($n['message']); 
                        ?>
                    </div>
                    <small style="color: gray; font-size: 11px;"><?php echo $n['created_at']; ?></small>
                </div>

                <?php if($n['type'] === 'delivery'): ?>
                    
                    <?php if($my_id == $n['sender_id']): ?>
                        <?php if($n['delivery_status'] === 'waiting_for_other_user'): ?>
                        <?php elseif($n['delivery_status'] === 'waiting_driver'): ?>
                        <?php endif; ?>

                    <?php elseif($my_id == $n['receiver_id']): ?>
                        <?php if($n['delivery_status'] === 'waiting_for_other_user'): ?>
                            <button type="button" class="btn btn-outline" style="padding: 6px 14px; font-size: 13px; white-space: nowrap;"
                                    onclick="openIncomingDeliveryModal(<?php echo $n['related_id']; ?>)">
                                Confirm & Enter Details
                            </button>
                        <?php elseif($n['delivery_status'] === 'waiting_driver'): ?>
                            <span style="color: #10b981; font-size: 13px; font-weight: 600; white-space: nowrap;">✓ Confirmed</span>
                        <?php endif; ?>
                        
                    <?php endif; ?>

                <?php endif; ?>
            </div>

            <?php endwhile; ?>

            </div>

        </div>

    </div>

    <div id="inboxModal" class="modal">

        <div
            class="modal-content"
            style="max-width:550px;"
        >

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:15px;
            ">

                <h3
                    id="inbox_title"
                    style="margin:0;"
                >
                    Chat
                </h3>

                <button
                    class="btn btn-danger"
                    onclick="
                        this.closest('.modal').style.display='none'
                    "
                >
                    Close
                </button>

            </div>

            <div id="inbox_msgs"></div>

            <div style="
                display:flex;
                gap:10px;
                margin-top:15px;
            ">

                <input
                    type="text"
                    id="inbox_input"
                    placeholder="Type your reply..."
                    style="margin-bottom:0;"
                >

                <button
                    onclick="sendInboxMsg()"
                    id="send-btn"
                    class="btn btn-primary"
                >
                    Send
                </button>

            </div>

        </div>

    </div>

    <div id="editItemModal" class="modal">

        <div class="modal-content">

            <h3>Edit Listing</h3>

            <form method="POST" action="profile.php">

                <input
                    type="hidden"
                    name="p_id"
                    id="edit_p_id"
                >

                <label style="
                    font-size:13px;
                    color:#64748b;
                ">
                    Item Title
                </label>

                <input
                    type="text"
                    name="title"
                    id="edit_title"
                    required
                >

                <label style="
                    font-size:13px;
                    color:#64748b;
                ">
                    Description
                </label>

                <textarea
                    name="description"
                    id="edit_desc"
                    rows="4"
                    required
                ></textarea>

                <div style="
                    display:flex;
                    gap:10px;
                    margin-top:10px;
                ">

                    <button
                        type="submit"
                        name="edit_item"
                        class="btn btn-primary"
                        style="flex:1;"
                    >
                        Update Item
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline"
                        onclick="
                            document.getElementById(
                                'editItemModal'
                            ).style.display='none'
                        "
                    >
                        Cancel
                    </button>

                </div>

            </form>

        </div>

    </div>

    <div id="incomingDeliveryModal" class="modal" onclick="if(event.target==this) closeIncomingModal()">
        <div class="modal-content" style="max-width:500px; padding:30px; display:block; border-radius:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h3 style="margin:0;">Delivery Request Details</h3>
                    <p style="margin-top:4px; color:#64748b; font-size:13px; font-weight: normal;">Provide your details to confirm the delivery</p>
                </div>
                <button type="button" onclick="closeIncomingModal()" style="border:none; background:#f1f5f9; width:34px; height:34px; border-radius:10px; cursor:pointer; font-weight:bold;">✕</button>
            </div>

            <div style="background: #f8fafc; padding: 12px; border-radius: 12px; margin-bottom: 15px; border: 1px dashed #cbd5e1;">
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">Sender's Info (Read-only):</span>
                <p style="margin: 5px 0 2px 0; font-size: 13px;"><b>Pickup From:</b> <span id="view_sender_location"></span></p>
                <p style="margin: 0; font-size: 13px;"><b>Notes:</b> <span id="view_delivery_notes"></span></p>
            </div>

            <form method="POST" action="profile.php">
                <input type="hidden" name="confirm_delivery" value="1">
                <input type="hidden" name="delivery_id" id="inc_delivery_id">
                
                <div style="margin-bottom: 15px; text-align: left;">
                    <label style="font-size:13px; color:#64748b; display:block; margin-bottom:5px; font-weight:600;">Your Drop-off Location (Receiver Location)</label>
                    <input type="text" name="receiver_location" placeholder="Enter your full address" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:10px; outline:none; font-family:inherit;">
                </div>

                <div style="margin-bottom: 20px; text-align: left;">
                    <label style="font-size:13px; color:#64748b; display:block; margin-bottom:5px; font-weight:600;">Your Contact Phone Number</label>
                    <input type="text" name="receiver_phone" placeholder="07xxxxxxxx" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:10px; outline:none; font-family:inherit;">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-weight:600; font-size:14px; background: #10b981; border: none; border-radius:12px;">
                    Confirm & Request Driver
                </button>
            </form>
        </div>
    </div>

    <div id="receiptModal" class="modal" onclick="if(event.target==this) closeReceiptModal()">
        <div class="modal-content" style="max-width:450px; padding:30px; display:block; border-radius:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h3 style="margin:0;">Delivery Receipt</h3>
                    <p style="margin-top:4px; color:#10b981; font-size:13px; font-weight: 600;">✓ Confirmed & Waiting Driver</p>
                </div>
                <button type="button" onclick="closeReceiptModal()" style="border:none; background:#f1f5f9; width:34px; height:34px; border-radius:10px; cursor:pointer; font-weight:bold;">✕</button>
            </div>

            <div style="margin-bottom: 15px; text-align: left;">
                <label style="font-size:13px; color:#64748b; display:block; margin-bottom:5px;">Your Saved Location</label>
                <input type="text" id="rec_user_location" readonly style="background:#f8fafc; color:#1e293b; width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:10px; outline:none;">
            </div>

            <div style="margin-bottom: 15px; text-align: left;">
                <label style="font-size:13px; color:#64748b; display:block; margin-bottom:5px;">Delivery Notes</label>
                <textarea id="rec_delivery_notes" rows="3" readonly style="background:#f8fafc; color:#1e293b; width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:10px; outline:none; resize:none;"></textarea>
            </div>
            
            <button type="button" onclick="closeReceiptModal()" class="btn btn-outline" style="width:100%; padding:11px; border-radius:12px;">Close</button>
        </div>
    </div>
    <script>

    let currentInboxId = null;

    let currentPartner = "";

    const messageInput =
        document.getElementById("inbox_input");

    messageInput.addEventListener(
        "keydown",
        function(event){

            if(event.key == "Enter"){

                event.preventDefault();

                sendInboxMsg();

            }

        }
    );

    function openInbox(item, partner) {

        currentInboxId = item.item_id;

        currentPartner = partner;

        const profileLink = `
        <a
            href="other_profile.php?user=${partner}"
            style="
                text-decoration:none;
                font-size:12px;
                color:var(--primary);
                margin-left:10px;
                font-weight:normal;
            "
        >
            (View Profile)
        </a>
        `;

        document.getElementById(
            'inbox_title'
        ).innerHTML =
            item.title +
            " (@" +
            partner +
            ")" +
            profileLink;

        fetchInbox();

        document.getElementById(
            'inboxModal'
        ).style.display = 'flex';
    }

    function fetchInbox() {

        if(!currentInboxId) return;

        const fd = new FormData();

        fd.append('fetch_msgs', '1');

        fd.append('item_id', currentInboxId);

        fd.append('target_user', currentPartner);

        fetch(
            'index.php',
            {
                method: 'POST',
                body: fd
            }
        )

        .then(r => r.json())

        .then(data => {

            const box =
                document.getElementById('inbox_msgs');

            box.innerHTML = "";

            data.forEach(m => {

                const isMe =
                    m.sender === "<?php echo $me; ?>";

                box.innerHTML += `
                <div class="
                    msg-bubble
                    ${isMe ? 'msg-me' : 'msg-them'}
                ">

                    <small style="
                        opacity:0.6;
                        display:block;
                        font-size:10px;
                    ">
                        @${m.sender}
                    </small>

                    ${m.message}

                </div>
                `;

            });

            box.scrollTop = box.scrollHeight;

        });

    }

    function sendInboxMsg() {

        const txt =
            document.getElementById(
                'inbox_input'
            ).value;

        if(!txt || !currentPartner) return;

        const fd = new FormData();

        fd.append('send_msg', '1');

        fd.append('item_id', currentInboxId);

        fd.append('message', txt);

        fd.append('target_user', currentPartner);

        fetch(
            'index.php',
            {
                method: 'POST',
                body: fd
            }
        )

        .then(() => {

            document.getElementById(
                'inbox_input'
            ).value = "";

            fetchInbox();

        });

    }

    function encodeImage(input) {

        if (input.files && input.files[0]) {

            const reader = new FileReader();

            reader.onload = function(e) {

                document.getElementById(
                    'pic_base64'
                ).value = e.target.result;

            }

            reader.readAsDataURL(input.files[0]);

        }

    }

    function openEditModal(item) {

        document.getElementById(
            'edit_p_id'
        ).value = item.id;

        document.getElementById(
            'edit_title'
        ).value = item.title;

        document.getElementById(
            'edit_desc'
        ).value = item.description;

        document.getElementById(
            'editItemModal'
        ).style.display = 'flex';

    }

    function openIncomingDeliveryModal(deliveryId) {

        const fd = new FormData();

        fd.append("get_delivery_details", "1");

        fd.append("delivery_id", deliveryId);

        fetch("profile.php", {
            method: "POST",
            body: fd
        })

        .then(res => res.text())

        .then(data => {

            console.log(data);

            const json = JSON.parse(data);

            if(json.error){
                alert(json.error);
                return;
            }

            document.getElementById(
                "view_sender_location"
            ).innerText = json.sender_location;

            document.getElementById(
                "view_delivery_notes"
            ).innerText =
                json.delivery_notes
                ? json.delivery_notes
                : "No notes.";

            document.getElementById(
                "inc_delivery_id"
            ).value = json.id;

            document.getElementById(
                "incomingDeliveryModal"
            ).style.display = "flex";

        })

        .catch(err => console.error(err));
    }


    function closeIncomingModal() {
        document.getElementById("incomingDeliveryModal").style.display = "none";
    }

    const currentSessionUser = "<?php echo $me; ?>";

    function openReceiptModal(deliveryId) {
        const fd = new FormData();
        fd.append("get_delivery_details", "1");
        fd.append("delivery_id", deliveryId);

        fetch("profile.php", {
            method: "POST",
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if(data) {
                if(currentSessionUser === data.sender_username) {
                    document.getElementById("rec_user_location").value = data.sender_location;
                } else {
                    document.getElementById("rec_user_location").value = data.receiver_location;
                }
                
                document.getElementById("rec_delivery_notes").value = data.delivery_notes ? data.delivery_notes : "No notes provided.";
                
                document.getElementById("receiptModal").style.display = "flex";
            }
        })
        .catch(err => console.error("Error loading receipt details:", err));
    }

    function closeReceiptModal() {
        document.getElementById("receiptModal").style.display = "none";
    }
    </script>

</body>

</html>