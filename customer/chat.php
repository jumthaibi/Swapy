<?php

include '../auth.php';
require_once '../image_storage.php';
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

// Handle sending a text message, an image, or both.
$sendError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($receiver)) {
    $msg = trim($_POST['message'] ?? '');
    $uploadedImage = read_uploaded_image($_FILES['image'] ?? null);

    if ($uploadedImage === false) {
        $sendError = 'Please choose a JPG, PNG, GIF, or WebP image smaller than 8 MB.';
    } elseif ($msg !== '' || $uploadedImage !== null) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO chats (item_id, sender, receiver, message)
            VALUES (?, ?, ?, ?)
        ");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isss', $item_id, $me, $receiver, $msg);
            $saved = mysqli_stmt_execute($stmt);
            $messageId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            if ($saved && $uploadedImage !== null) {
                if (!save_chat_image_blob(
                    $conn,
                    $messageId,
                    $uploadedImage['mime_type'],
                    $uploadedImage['image_data']
                )) {
                    $sendError = 'The message was saved, but the image could not be stored.';
                }
            }
        } else {
            $sendError = 'The message could not be sent. Please try again.';
        }
    }

    $redirect = "chat.php?item_id=$item_id&user=" . rawurlencode($receiver);
    if ($sendError !== '') {
        $redirect .= '&error=' . rawurlencode($sendError);
    }
    header("Location: $redirect");
    exit();
}

$queryError = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$hasChatImageTable = ensure_image_blob_table($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swapy Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --chat-yellow: #fbbf24;
            --chat-yellow-light: #fef3c7;
            --chat-border: #e5e7eb;
            --chat-bg: #f0f2f5;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--chat-bg);
            margin: 0;
            min-width: 280px;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .chat-container {
            background: white;
            width: min(100%, 760px);
            height: min(900px, 100dvh);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .chat-header {
            flex: 0 0 auto;
            min-width: 0;
            padding: 15px clamp(14px, 3vw, 24px);
            background: var(--chat-yellow);
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: bold;
        }

        .chat-header a {
            flex: 0 0 auto;
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border-radius: 50%;
            font-size: 22px;
            line-height: 1;
        }

        .chat-header a:hover,
        .chat-header a:focus-visible {
            background: rgba(255, 255, 255, 0.18);
            outline: none;
        }

        .chat-header img {
            flex: 0 0 auto;
        }

        .chat-title {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .chat-title small {
            display: block;
            margin-top: 2px;
            font-size: 0.75rem;
            font-weight: 400;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-msgs {
            flex: 1 1 auto;
            min-height: 0;
            padding: clamp(14px, 3vw, 24px);
            overflow-y: auto;
            overscroll-behavior: contain;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fff;
        }

        .msg {
            width: fit-content;
            max-width: min(78%, 560px);
            padding: 10px 15px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .me {
            align-self: flex-end;
            background: var(--chat-yellow);
            color: white;
            border-bottom-right-radius: 2px;
        }

        .them {
            align-self: flex-start;
            background: #f1f1f1;
            color: #333;
            border-bottom-left-radius: 2px;
        }

        .chat-error {
            flex: 0 0 auto;
            margin: 12px 15px 0;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .msg-image {
            display: block;
            width: auto;
            max-width: min(100%, 320px);
            max-height: min(42dvh, 320px);
            object-fit: contain;
            border-radius: 10px;
            margin-top: 6px;
        }

        .chat-form {
            flex: 0 0 auto;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            grid-template-areas:
                "attach input send"
                "name name name";
            gap: 7px 10px;
            padding: 12px max(14px, env(safe-area-inset-right)) max(12px, env(safe-area-inset-bottom)) max(14px, env(safe-area-inset-left));
            border-top: 1px solid var(--chat-border);
            background: white;
        }

        .chat-form input[type="text"] {
            grid-area: input;
            min-width: 0;
            width: 100%;
            border: 1px solid #d1d5db;
            padding: 11px 14px;
            border-radius: 20px;
            outline: none;
            font: inherit;
        }

        .chat-form input[type="text"]:focus {
            border-color: var(--chat-yellow);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
        }

        .attach-button {
            grid-area: attach;
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 50%;
            background: var(--chat-yellow-light);
            color: #b45309;
            font-size: 18px;
            cursor: pointer;
        }

        .attach-button:hover,
        .attach-button:focus-within {
            background: #fde68a;
        }

        .file-name {
            grid-area: name;
            min-width: 0;
            max-width: 100%;
            min-height: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 11px;
            color: #64748b;
        }

        .chat-form button {
            grid-area: send;
            min-height: 42px;
            background: var(--chat-yellow);
            border: none;
            padding: 10px 18px;
            color: white;
            border-radius: 20px;
            cursor: pointer;
            font: inherit;
            font-weight: 600;
            white-space: nowrap;
        }

        .chat-form button:hover,
        .chat-form button:focus-visible {
            background: #f59e0b;
            outline: none;
        }

        @media (min-width: 521px) {
            .chat-container {
                height: calc(100dvh - 24px);
                max-height: 900px;
                border-radius: 20px;
            }
        }

        @media (max-width: 520px) {
            body {
                align-items: stretch;
                background: white;
            }

            .chat-container {
                width: 100%;
                height: 100dvh;
                min-height: 100svh;
                border-radius: 0;
                box-shadow: none;
            }

            .chat-header {
                padding: 10px max(10px, env(safe-area-inset-right)) 10px max(10px, env(safe-area-inset-left));
                gap: 8px;
                font-size: 0.88rem;
            }

            .chat-header a {
                width: 30px;
                height: 30px;
                font-size: 20px;
            }

            .chat-header img {
                width: 32px !important;
                height: 32px !important;
            }

            .chat-msgs {
                padding: 12px 10px;
                gap: 8px;
            }

            .msg {
                max-width: 88%;
                padding: 8px 11px;
                font-size: 12.5px;
            }

            .msg-image {
                max-width: min(62vw, 220px);
                max-height: min(38dvh, 220px);
            }

            .chat-form {
                grid-template-columns: auto minmax(0, 1fr) auto;
                gap: 6px;
                padding: 9px max(10px, env(safe-area-inset-right)) max(9px, env(safe-area-inset-bottom)) max(10px, env(safe-area-inset-left));
            }

            .chat-form input[type="text"] {
                padding: 10px 11px;
                font-size: 13px;
            }

            .attach-button,
            .chat-form button {
                min-height: 38px;
            }

            .attach-button {
                width: 38px;
                height: 38px;
                font-size: 16px;
            }

            .chat-form button {
                padding: 8px 13px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <a href="profile.php" style="color:white; text-decoration:none;">❮</a>
            <img src="<?php echo '../image.php?entity=post&amp;id=' . (int)$post['id']; ?>" style="width:35px; height:35px; border-radius:50%; object-fit:cover;" alt="">
            <div class="chat-title">
                <?php echo $post['title']; ?> 
                <br><small>Chatting with @<?php echo $receiver ?: '...'; ?></small>
            </div>
        </div>
        <?php if ($queryError !== ''): ?>
            <div class="chat-error"><?php echo $queryError; ?></div>
        <?php endif; ?>
        <div class="chat-msgs" id="box">
            <?php
            if (!empty($receiver)) {
                // Fetch messages strictly between these two users for this item
                 $query = "SELECT c.*" . ($hasChatImageTable ? ", ib.entity_id AS chat_image_id" : "") . " FROM chats c "
                        . ($hasChatImageTable ? "LEFT JOIN swapy_image_blobs ib ON ib.entity_type = 'chat' AND ib.entity_id = c.id " : "")
                        . "WHERE c.item_id = $item_id 
                        AND ((c.sender = '$me' AND c.receiver = '$receiver') OR (c.sender = '$receiver' AND c.receiver = '$me')) 
                        ORDER BY c.id ASC";
                $msgs = mysqli_query($conn, $query);
                while($m = mysqli_fetch_assoc($msgs)) {
                    $c = ($m['sender'] == $me) ? 'me' : 'them';


                    // Security Fix: Prevent Cross-Site Scripting (XSS) by escaping user outputs
                    $clean_sender = htmlspecialchars($m['sender'], ENT_QUOTES, 'UTF-8');
                    $clean_message = nl2br(htmlspecialchars($m['message'] ?? '', ENT_QUOTES, 'UTF-8'));
                    $imageMarkup = !empty($m['chat_image_id'])
                        ? "<img class='msg-image' src='../image.php?entity=chat&amp;id=" . (int) $m['id'] . "' alt='Attached image' loading='lazy'>"
                        : '';
                    echo "<div class='msg $c'><small style='display:block; font-size:9px; opacity:0.7;'>@{$clean_sender}</small>{$imageMarkup}{$clean_message}</div>";
                   
                }
            } else {
                echo "<div style='text-align:center; padding:20px; color:#999;'>No active recipient identified.</div>";
            }
            ?>
        </div>
        <form class="chat-form" method="POST" enctype="multipart/form-data">
            <label class="attach-button" title="Attach an image">
                📎
                <input id="chat-image" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            </label>
            <span class="file-name" id="chat-image-name" aria-live="polite"></span>
            <input type="text" name="message" placeholder="Type a message..." autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </div>
    <script>
        document.getElementById('box').scrollTop = document.getElementById('box').scrollHeight;
        document.getElementById('chat-image').addEventListener('change', function () {
            document.getElementById('chat-image-name').textContent =
                this.files.length ? this.files[0].name : '';
        });
    </script>
</body>
</html>