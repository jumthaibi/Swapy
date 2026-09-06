<?php
include '../auth.php';
protect_page(2); // Only allow Delivery personnel to access this page

$driver_username = $_SESSION['swapy_session'];

// =========================================================================
//   SESSION & CURRENT DRIVER INFORMATION SETUP
// =========================================================================
$driver_query = mysqli_query($conn, "SELECT * FROM users WHERE username='$driver_username'");
$driver_data = mysqli_fetch_assoc($driver_query);
$driver_id = $driver_data['id'];
$driver_name = $driver_data['name'];
$driver_pic = $driver_data['pic'];

// =========================================================================
//   DATABASE QUERY: GET COMPLETED DELIVERY STATS
// =========================================================================
$stats_query = mysqli_query($conn, "SELECT COUNT(*) AS total_completed FROM delivery_request WHERE driver_id='$driver_id' AND delivery_status='delivered'");
$stats_data = mysqli_fetch_assoc($stats_query);
$completed_deliveries = $stats_data['total_completed'];

// =========================================================================
//   DATABASE QUERY: FETCH NEW DELIVERY REQUESTS (WAITING DRIVER)
// =========================================================================
$pending_orders = mysqli_query($conn, "
    SELECT dr.*, p.title AS item_title, p.img AS item_img
    FROM delivery_request dr
    JOIN posts p ON dr.item_id = p.id
    WHERE dr.delivery_status = 'waiting_driver' AND dr.driver_id IS NULL
    ORDER BY dr.id DESC");

// =========================================================================
//   DATABASE QUERY: FETCH ACTIVE ORDERS FOR CURRENT DRIVER
// =========================================================================
$active_orders = mysqli_query($conn, "
    SELECT dr.*, p.title AS item_title, p.img AS item_img
    FROM delivery_request dr
    JOIN posts p ON dr.item_id = p.id
    WHERE dr.delivery_status = 'processing' AND dr.driver_id = '$driver_id'
    ORDER BY dr.id DESC
");

// =========================================================================
//   AJAX HANDLER: ACCEPTING DELIVERY REQUESTS
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'accept_order') {
    $delivery_id = mysqli_real_escape_string($conn, $_POST['delivery_id']);
   
    $update_query = mysqli_query($conn, "
        UPDATE delivery_request 
        SET delivery_status = 'processing', 
            driver_id = '$driver_id' 
        WHERE id = '$delivery_id' AND delivery_status = 'waiting_driver'
    ");
    if ($update_query) {
        echo 'success'; 
    } else {
        echo 'error'; 
    }
    exit(); 
}

// =========================================================================
//   AJAX HANDLER: COMPLETING ACTIVE DELIVERIES
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'finish_order') {
    $delivery_id = mysqli_real_escape_string($conn, $_POST['delivery_id']);

    $finish_query = mysqli_query($conn, "
        UPDATE delivery_request 
        SET delivery_status = 'delivered' 
        WHERE id = '$delivery_id' AND driver_id = '$driver_id'
    ");
    
    if ($finish_query) {
        echo 'success'; 
    } else {
        echo 'error'; 
    }
    exit(); 
}

// =========================================================================
//   AJAX HANDLER: FETCHING DYNAMIC ORDER DETAILS FOR POPUP
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'get_order_details') {
    $delivery_id = mysqli_real_escape_string($conn, $_POST['delivery_id']);
    
    $details_query = mysqli_query($conn, "
        SELECT dr.*, p.title AS item_title, p.img AS item_img
        FROM delivery_request dr 
        JOIN posts p ON dr.item_id = p.id 
        WHERE dr.id = '$delivery_id'
    ");
    
    if ($row = mysqli_fetch_assoc($details_query)) {
        echo json_encode($row);
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
<title>SWAPY | Delivery Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="index.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo">
        SWAPY
        <span style="font-weight:300; font-size:0.9rem; color:#64748b;">
            | DELIVERY
        </span>
    </a>
    <div class="nav-right">
        <a href="../staff_profile.php" class="profile-link">
            <span style="font-weight:600; font-size:14px;">
                <?php echo $driver_name; ?>
            </span>
            <?php if (!empty($driver_pic)): ?>
        <img class="header-avatar" 
             src="../uploads/<?php echo $driver_pic; ?>" 
             alt="Profile" 
             style="width:35px; height:35px; border-radius:50%; object-fit:cover;">
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
            <?php echo mb_substr($driver_name, 0, 2, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
        </a>
        <button onclick="location.href='../logout.php'"
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
            Logout
        </button>
    </div>
</nav>

<div class="container">

    <div class="driver-profile">
        <div class="driver-avatar">
            <?php echo strtoupper(substr($driver_name, 0, 2)); ?>
        </div>
        <div class="driver-info">
            <h2><?php echo $driver_name; ?></h2>
            <p><?php echo $driver_data['email']; ?></p>
        </div>
        <div class="driver-stats">
            <p>Completed Deliveries</p>
            <span><?php echo $completed_deliveries; ?></span>
        </div>
    </div>

    <div class="dashboard-tabs">
        <button class="tab-btn active" id="btn-pending" onclick="showTab(event,'pending')">
            New Requests
        </button>
        <button class="tab-btn" id="btn-active" onclick="showTab(event,'active')">
            In Progress
        </button>
    </div>

    <div id="pending" class="tab-content active">
        <div class="page-header">
            <h2>Delivery Requests</h2>
            <p>Accept and manage new delivery requests.</p>
        </div>

        <?php 
        if (mysqli_num_rows($pending_orders) > 0) {
            while ($row = mysqli_fetch_assoc($pending_orders)) { 
        ?>
                <div class="review-card">
                    <div class="item-image-placeholder" style="padding:0; overflow:hidden;">
                        <?php if(!empty($row['item_img'])): ?>
                            <img src="<?php echo htmlspecialchars($row['item_img']); ?>" alt="Order Image" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            Order
                        <?php endif; ?>
                    </div>

                    <div class="item-info">
                        <span class="meta-tag">Order # <?php echo $row['id']; ?></span>
                        <h3><?php echo htmlspecialchars($row['item_title']); ?></h3>
                        <p>📍 Pickup Location: <?php echo htmlspecialchars($row['sender_location']); ?></p>
                        <p>📍 Destination: <?php echo htmlspecialchars($row['receiver_location']); ?></p>
                    </div>

                    <div class="action-area">
                        <button class="btn btn-primary" onclick="acceptOrder(<?php echo $row['id']; ?>, this)">
                            Accept Order
                        </button>
                        <button class="btn btn-outline" onclick="openDetails(<?php echo $row['id']; ?>)">
                            View Details
                        </button>
                    </div>
                </div>
        <?php 
            } 
        } else {
            echo "<p style='text-align:center; color:#64748b; padding:20px;'>No new delivery requests available right now.</p>";
        }
        ?>
    </div>
       
    <div id="active" class="tab-content">
        <div class="page-header">
            <h2>Orders In Progress</h2>
            <p>Active deliveries currently on the road.</p>
        </div>

        <?php 
        if (mysqli_num_rows($active_orders) > 0) {
            while ($row = mysqli_fetch_assoc($active_orders)) { 
        ?>
                <div class="review-card">
                    <div class="item-image-placeholder" style="padding:0; overflow:hidden;">
                        <?php if(!empty($row['item_img'])): ?>
                            <img src="<?php echo htmlspecialchars($row['item_img']); ?>" alt="Order Image" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            Active
                        <?php endif; ?>
                    </div>

                    <div class="item-info">
                        <span class="meta-tag" style="background:#ecfdf5; color:#059669;">In Transit 🚚</span>
                        <span class="meta-tag" style="background:#f1f5f9; color:#475569;">Order #<?php echo $row['id']; ?></span>
                        <h3><?php echo htmlspecialchars($row['item_title']); ?></h3>
                        <p>📍 Pickup Location: <?php echo htmlspecialchars($row['sender_location']); ?></p>
                        <p>📍 Destination: <?php echo htmlspecialchars($row['receiver_location']); ?></p>
                    </div>

                    <div class="action-area">
                        <button class="btn btn-success" onclick="finishOrder(<?php echo $row['id']; ?>, this)">
                            Mark Delivered
                        </button>
                        <button class="btn btn-outline" onclick="openDetails(<?php echo $row['id']; ?>)">
                            View Details
                        </button>
                    </div>
                </div>
        <?php 
            } 
        } else {
            echo "<p style='text-align:center; color:#64748b; padding:20px;'>You don't have any active deliveries right now.</p>";
        }
        ?>
    </div> </div> <div class="details-popup" id="detailsPopup">
    <div class="popup-box">
        <div class="popup-header">
            <h3 id="popupTitle">Order Details</h3>
            <button class="close-popup" onclick="closeDetails()">✕</button>
        </div>
        <div class="popup-body">
            <p>📍 <strong>Pickup Location:</strong> <span id="popPickup">...</span></p>
            <p>📍 <strong>Destination:</strong> <span id="popDestination">...</span></p>
            <p>📞 <strong>Customer Phone:</strong> <span id="popPhone">...</span></p>
            <p>💰 <strong>Price to Collect:</strong> <span id="popPrice" style="color:#059669; font-weight:bold;">...</span></p>
            <p>📝 <strong>Notes:</strong> <span id="popNotes">...</span></p>
        </div>
    </div>
</div>

<script>

// حيلة ذكية: عند تحميل الصفحة، نفحص إذا كنا نريد فتح تبويب Active تلقائياً بعد القبول
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'active') {
        // محاكاة كبسة زر لفتح تبويب الـ In progress
        document.getElementById('btn-active').click();
    }
};

// -------------------------------------------------------------------------
//  FUNCTION: TOGGLE DASHBOARD TABS VIEW
// -------------------------------------------------------------------------
function showTab(event, tab){
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tab).classList.add('active');
    event.currentTarget.classList.add('active');
}

// -------------------------------------------------------------------------
//  FUNCTION: SUBMIT ACCEPT ORDER REQUEST VIA AJAX FETCH
// -------------------------------------------------------------------------
function acceptOrder(orderId, button) {
    let formData = new FormData();
    formData.append('action', 'accept_order');
    formData.append('delivery_id', orderId);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === 'success') {
            button.innerHTML = 'Accepted ✔';
            button.style.background = '#059669'; 

            setTimeout(() => {
                button.closest('.review-card').remove();
                alert('Order moved to In Progress!');
                // توجيه الصفحة لتعمل ريفريش وتفتح فوراً على تبويب active المحدث!
                window.location.href = 'index.php?tab=active';
            }, 500);
        } else {
            alert('Something went wrong, please try again.');
        }
    });
}

// -------------------------------------------------------------------------
//  FUNCTION: SUBMIT FINISH ORDER REQUEST VIA AJAX FETCH
// -------------------------------------------------------------------------
function finishOrder(orderId, button) {
    let formData = new FormData();
    formData.append('action', 'finish_order');
    formData.append('delivery_id', orderId);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === 'success') {
            button.innerHTML = 'Delivered 🎉';
            button.style.background = '#059669'; 

            setTimeout(() => {
                button.closest('.review-card').remove();
                alert('Delivery Completed Successfully!');
                // هنا تفتح تلقائياً على نفس صفحة الـ active المحدثة
                window.location.href = 'index.php?tab=active';
            }, 500);
        } else {
            alert('Something went wrong, please try again.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error updating delivery status.');
    });
}

// -------------------------------------------------------------------------
//  FUNCTION: FETCH ORDER DETAILS AND OPEN MODAL POPUP
// -------------------------------------------------------------------------
function openDetails(orderId){
    document.getElementById('detailsPopup').style.display = 'flex';
    document.getElementById('popupTitle').innerText = "Loading Details...";

    let formData = new FormData();
    formData.append('action', 'get_order_details');
    formData.append('delivery_id', orderId);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data !== 'error') {
            document.getElementById('popupTitle').innerText = data.item_title;
            document.getElementById('popPickup').innerText = data.sender_location;
            document.getElementById('popDestination').innerText = data.receiver_location;
            document.getElementById('popPhone').innerText = data.phone ? data.phone : 'No phone provided';
            document.getElementById('popPrice').innerText = data.price ? data.price + ' JOD' : '0.00 JOD';
            document.getElementById('popNotes').innerText = data.delivery_notes ? data.delivery_notes : 'No extra notes';
        } else {
            alert('Could not fetch details.');
            closeDetails();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error loading dynamic data.');
    });
}

// -------------------------------------------------------------------------
//  FUNCTION: CLOSE MODAL POPUP
// -------------------------------------------------------------------------
function closeDetails(){
    document.getElementById('detailsPopup').style.display = 'none';
}

</script>
</body>
</html>
