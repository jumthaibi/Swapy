<?php
include '../auth.php';
protect_page(3); // Only allow SystemAdmin to access this page


// =========================================================================
//   AJAX / POST HANDLER: INSERT NEW EMPLOYEE INTO DATABASE
// =========================================================================
$msg = "";
if (isset($_POST['add_employee_submit'])) {
    $emp_username = mysqli_real_escape_string($conn, $_POST['username']);
    $emp_name = mysqli_real_escape_string($conn, $_POST['name']);
    $emp_email = mysqli_real_escape_string($conn, $_POST['email']);
    $emp_password = mysqli_real_escape_string($conn, $_POST['password']); 
    $emp_role = intval($_POST['role']); 

    
   // $hashed_password = md5($emp_password); 

    $check_user = mysqli_query($conn, "SELECT * FROM users WHERE username='$emp_username' OR email='$emp_email'");
    if (mysqli_num_rows($check_user) > 0) {
        $msg = "error_exists";
    } else {
      
        $insert_query = mysqli_query($conn, "
            INSERT INTO users (username, name, email, password, role) 
            VALUES ('$emp_username', '$emp_name', '$emp_email', '$emp_password', '$emp_role')
        ");
        
        if ($insert_query) {
            $msg = "success";
            header("refresh:2; url=index.php"); 
        } else {
            $msg = "error_db";
        }
    }
}



// =================================================
//   1. SUMMARY CARDS DATA: FETCHING & CALCULATING
// =================================================

// total employees (Users with role 1 or 2 or 4 )
$emp_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role IN (1, 2, 4)");
$emp_data = mysqli_fetch_assoc($emp_query);
$total_employees = $emp_data['total'];

// total customers (Users with role 0)
$cust_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 0");
$cust_data = mysqli_fetch_assoc($cust_query);
$total_customers = $cust_data['total'];

// total posts
$posts_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM posts");
$posts_data = mysqli_fetch_assoc($posts_query);
$total_posts = $posts_data['total'];

// total complaints
$complaints_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM complaint");
$complaints_data = mysqli_fetch_assoc($complaints_query);
$total_complaints = $complaints_data['total'];


// =========================================
//   2. CHARTS DATA: FETCHING & FORMATTING 
// ==========================================

// Employees Distribution (Pie Chart)
$emp_dist_query = mysqli_query($conn, "SELECT role, COUNT(*) AS count FROM users WHERE role IN (1, 2, 4) GROUP BY role");
$emp_labels = [];
$emp_counts = [];
while($row = mysqli_fetch_assoc($emp_dist_query)) {
    if($row['role'] == 1) {
        $emp_labels[] = 'Content Admins';
    } elseif ($row['role'] == 2) {
        $emp_labels[] = 'Delivery Personnel';
    } elseif ($row['role'] == 4) {
        $emp_labels[] = 'Customer Support';
    }
    $emp_counts[] = $row['count'];
}




//Posts Type: Sale vs Swap (Doughnut Chart)
$types_query = mysqli_query($conn, "SELECT type, COUNT(*) AS count FROM posts GROUP BY type");
$type_labels = [];
$type_counts = [];
while($row = mysqli_fetch_assoc($types_query)) {
    $type_labels[] = ($row['type'] === 'Sell') ? 'Item for Sale' : 'Item for Swap';
    $type_counts[] = $row['count'];
}


//Delivery Status (Bar Chart)
$delivery_stat_query = mysqli_query($conn, "SELECT delivery_status, COUNT(*) AS count FROM delivery_request GROUP BY delivery_status");
$delivery_labels = [];
$delivery_counts = [];
while($row = mysqli_fetch_assoc($delivery_stat_query)) {
    $delivery_labels[] = $row['delivery_status']; 
    $delivery_counts[] = $row['count'];
}


// Complaints Status (Bar Chart)
$complaints_stat_query = mysqli_query($conn, "SELECT status, COUNT(*) AS count FROM complaint GROUP BY status");
$complaints_labels = [];
$complaints_counts = [];
while($row = mysqli_fetch_assoc($complaints_stat_query)) {
    $complaints_labels[] = $row['status'];
    $complaints_counts[] = $row['count'];
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SWAPY | Admin Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="index.css">


</head>

<body>

<!-- ===== NAVBAR ===== -->

<nav class="navbar">

    <div class="logo">

        SWAPY

        <span>
            | ADMIN DASHBOARD
        </span>



        
    </div>

   <div class="nav-actions" style="display: flex; gap: 12px; align-items: center;">
        
        <button onclick="toggleEmployeeForm()" class="add-emp-btn"
            style="
                background: #fef08a;
                border: none;
                color: #854d0e;
                padding: 8px 16px;
                border-radius: 10px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
                transition: background 0.2s, color 0.2s;
            "
            onmouseover="this.style.background='#fde047'; this.style.color='#000';"
            onmouseout="this.style.background='#fef08a'; this.style.color='#854d0e';"
        >
            ➕ Add Employee
        </button>

    <button onclick="location.href='../logout.php'" class="logout-btn">

        Log Out

    </button>
</div>
</nav>

<!-- ===== CONTAINER ===== -->

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <h1>
            System Analytics Dashboard
        </h1>

        <p>
            Monitor employees, posts, complaints and delivery performance.
        </p>

    </div>

    <!-- STATS -->

    <div class="stats-grid">

        <div class="stat-card">

            <h3>Total Employees</h3>

            <span><?php echo $total_employees; ?></span>


        </div>

        <div class="stat-card">

            <h3>Total Customers</h3>

            <span><?php echo $total_customers; ?></span>


        </div>

        <div class="stat-card">

            <h3>Total Posts</h3>

            <span><?php echo $total_posts; ?></span>


        </div>

        <div class="stat-card">

            <h3>Complaints</h3>

            <span><?php echo $total_complaints; ?></span>

            <p style="color:#ef4444;">
                Needs Review
            </p>

        </div>

    </div>

    <!-- CHARTS -->

    <div class="chart-grid">

        <!-- PIE -->

        <div class="chart-box">

            <h2>
                Employees Distribution
            </h2>

            <canvas id="pieChart"></canvas>

        </div>

        <!-- BAR -->

        <div class="chart-box">

            <h2>
                Employees vs Customers
            </h2>

            <canvas id="barChart"></canvas>

        </div>

        <!-- COMPLAINTS -->

        <div class="chart-box">

            <h2>
                Complaints
            </h2>

            <canvas id="complaintsChart"></canvas>

        </div>
      

        <!-- POSTS TYPES -->

        <div class="chart-box">

            <h2>
                Posts Type
            </h2>

            <canvas id="typesChart"></canvas>

        </div>

        

        <!-- DELIVERY -->

        <div class="chart-box">

            <h2>
                Delivery Status
            </h2>

            <canvas id="deliveryChart"></canvas>

        </div>

    </div>

</div>


<div id="employeeFormSection" class="chart-box" style="display: none; margin-top: 30px; max-width: 600px; margin-left: auto; margin-right: auto; padding: 25px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: #fff;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
        <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;">Create New Employee Profile</h2>
        <button onclick="toggleEmployeeForm()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8;">✕</button>
    </div>

    <?php if($msg === "success"): ?>
        <div style="background: #ecfdf5; color: #059669; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">🎉 Employee added successfully! Refreshing dashboard...</div>
    <?php elseif($msg === "error_exists"): ?>
        <div style="background: #fef2f2; color: #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">⚠️ Username or Email already exists in system.</div>
    <?php elseif($msg === "error_db"): ?>
        <div style="background: #fef2f2; color: #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">❌ Database Error. Please try again.</div>
    <?php endif; ?>

    <form action="index.php" method="POST" style="display: flex; flex-direction: column; gap: 15px;">
        
        <div style="display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Full Name</label>
            <input type="text" name="name" required placeholder="John Doe" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
        </div>

        <div style="display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Username</label>
            <input type="text" name="username" required placeholder="johndoe123" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
        </div>

        <div style="display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Email Address</label>
            <input type="email" name="email" required placeholder="john@swapy.com" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
        </div>

        <div style="display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Password</label>
            <input type="password" name="password" required placeholder="••••••••" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
        </div>

        <div style="display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Employee Role / Permissions</label>
            <select name="role" required style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #fff;">
                <option value="1">Content Admin</option>
                <option value="2">Delivery Personnel</option>
                <option value="4">Customer Support</option>
            </select>
        </div>

        <button type="submit" name="add_employee_submit" style="background: #fbbf24; border: none; color: #000; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; margin-top: 10px; transition: background 0.2s;">
            Save & Deploy Employee
        </button>

    </form>
</div>



<!-- ===== CHARTS ===== -->

<script>

/* ===== PIE CHART : EMPLOYEES DISTRIBUTION ===== */

new Chart(document.getElementById('pieChart'),{

    type:'pie',

    data:{
        labels:<?php echo json_encode($emp_labels); ?>,
        datasets:[{

            data:<?php echo json_encode($emp_counts); ?>,

            backgroundColor:[
                '#fde68a',
                '#fbbf24',
                '#d97706'
            ]

        }]
    }

});

/* ===== BAR CHART : EMPLOYEES VS CUSTOMERS ===== */

new Chart(document.getElementById('barChart'),{

    type:'bar',

    data:{
        labels:[
            'Employees',
            'Customers'
        ],

        datasets:[{

            label:'Total Employees vs Customers',

            data:[<?php echo $total_employees; ?>, <?php echo $total_customers; ?>],

            backgroundColor:[
                '#fbbf24',
                '#fde68a'
            ],

            borderRadius:10

        }]
    }

});



/* ===== DOUGHNUT CHART : POSTS TYPE (SALE VS SWAP) ===== */

new Chart(document.getElementById('typesChart'),{

    type:'doughnut',

    data:{
        labels:<?php echo json_encode($type_labels); ?>,

        datasets:[{

            data:<?php echo json_encode($type_counts); ?>,

            backgroundColor:[
                '#fbbf24',
                '#fde68a'
            ]

        }]
    }

});

/* ===== BAR CHART : COMPLAINTS STATUS ===== */

new Chart(document.getElementById('complaintsChart'),{

    type:'bar',

    data:{
        labels:<?php echo json_encode($complaints_labels); ?>,

        datasets:[{

            label:'Complaints Status',

            data:<?php echo json_encode($complaints_counts); ?>,

            backgroundColor:['#ef4444'],

            borderRadius:10

        }]
    }

});

/* ===== BAR CHART : DELIVERY STATUS DISTRIBUTION ===== */

new Chart(document.getElementById('deliveryChart'),{

    type:'bar',

    data:{
        labels:<?php echo json_encode($delivery_labels); ?>,
        datasets:[{

            label:'Orders',

            data:<?php echo json_encode($delivery_counts); ?>,

            backgroundColor:[
                '#94a3b8',
                '#22c55e'
            ],

            borderRadius:10

        }]
    }

});

// Function to toggle the employee form and smooth scroll to it when opened
function toggleEmployeeForm() {
    const formSection = document.getElementById('employeeFormSection');
    
    if (formSection.style.display === 'none') {
        formSection.style.display = 'block';
        formSection.scrollIntoView({ behavior: 'smooth' });
    } else {
        formSection.style.display = 'none';
    }
}
<?php if(!empty($msg)): ?>
    document.getElementById('employeeFormSection').style.display = 'block';
    document.getElementById('employeeFormSection').scrollIntoView({ behavior: 'smooth' });
<?php endif; ?>

</script>

</body>
</html>
