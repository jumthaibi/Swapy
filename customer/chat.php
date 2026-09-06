<?php

include '../auth.php';
protect_page(0); // Only allow customers to access this page

$me = $_SESSION['swapy_session'];
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$chat_with = isset($_GET['user']) ? mysqli_real_escape_string($conn, $_GET['user']) : '';

$post_res = mysqli_query($conn, "SELECT * FROM posts WHERE id = $item_id");
$post = mysqli_fetch_assoc($post_res);

if (!$post) { die("Item not found!"); }

/**
 * DETERMINING THE RECEIVER
 * 1. If I am NOT the author, I am chatting with the author.
 * 2. If I AM the author, I am chatting with the person specified in the URL.
 */
if ($post['author'] !== $me) {
    $receiver = $post['author'];
} else {
    $receiver = $chat_with;
}

if (empty($receiver)) {
    $last_msg_q = mysqli_query($conn, "SELECT CASE WHEN sender = '$me' THEN receiver ELSE sender END as partner 
                                FROM chats WHERE item_id = $item_id 
                                AND (sender = '$me' OR receiver = '$me') 
                                ORDER BY id DESC LIMIT 1");
    $last_msg_data = mysqli_fetch_assoc($last_msg_q);
    $receiver = $last_msg_data['partner'] ?? '';
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && !empty($receiver)) {
    $msg = mysqli_real_escape_string($conn, $_POST['message']);
    if(!empty($msg)) {
        mysqli_query($conn, "INSERT INTO chats (item_id, sender, receiver, message) VALUES ($item_id, '$me', '$receiver', '$msg')");
    }
    header("Location: chat.php?item_id=$item_id&user=$receiver");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Swapy Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; margin: 0; display: flex; justify-content: center; height: 100vh; align-items: center; }
        .chat-container { background: white; width: 100%; max-width: 450px; height: 90vh; border-radius: 15px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .chat-header { padding: 15px; background: #fbbf24; color: white; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .chat-msgs { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: #fff; }
        .msg { padding: 10px 15px; border-radius: 12px; font-size: 14px; max-width: 80%; line-height: 1.4; }
        .me { align-self: flex-end; background: #fbbf24; color: white; border-bottom-right-radius: 2px; }
        .them { align-self: flex-start; background: #f1f1f1; color: #333; border-bottom-left-radius: 2px; }
        form { padding: 15px; display: flex; gap: 10px; border-top: 1px solid #eee; background: white; }
        input { flex: 1; border: 1px solid #ddd; padding: 12px; border-radius: 20px; outline: none; }
        button { background: #fbbf24; border: none; padding: 10px 20px; color: white; border-radius: 20px; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <a href="profile.php" style="color:white; text-decoration:none;">❮</a>
            <img src="<?php echo $post['img']; ?>" style="width:35px; height:35px; border-radius:50%; object-fit:cover;">
            <div>
                <?php echo $post['title']; ?> 
                <br><small>Chatting with @<?php echo $receiver ?: '...'; ?></small>
            </div>
        </div>
        <div class="chat-msgs" id="box">
            <?php
            if (!empty($receiver)) {
                // Fetch messages strictly between these two users for this item
                $query = "SELECT * FROM chats 
                        WHERE item_id = $item_id 
                        AND ((sender = '$me' AND receiver = '$receiver') OR (sender = '$receiver' AND receiver = '$me')) 
                        ORDER BY id ASC";
                $msgs = mysqli_query($conn, $query);
                while($m = mysqli_fetch_assoc($msgs)) {
                    $c = ($m['sender'] == $me) ? 'me' : 'them';


                    // Security Fix: Prevent Cross-Site Scripting (XSS) by escaping user outputs
                    $clean_sender = htmlspecialchars($m['sender']);
                    $clean_message = htmlspecialchars($m['message']);
                    echo "<div class='msg $c'><small style='display:block; font-size:9px; opacity:0.7;'>@{$clean_sender}</small>{$clean_message}</div>";
                   
                }
            } else {
                echo "<div style='text-align:center; padding:20px; color:#999;'>No active recipient identified.</div>";
            }
            ?>
        </div>
        <form method="POST">
            <input type="text" name="message" placeholder="Type a message..." required autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </div>
    <script>document.getElementById('box').scrollTop = document.getElementById('box').scrollHeight;</script>
</body>
</html>