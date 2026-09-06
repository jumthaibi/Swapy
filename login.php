<?php
session_start();
include 'db.php';

$message = "";

// Maps portal names in the URL to role numbers in the database
$portals = [
    'AdminCM'     => 1,
    'Delivery'    => 2,
    'SystemAdmin' => 3,
    'CustSupport' => 4
];
// Get portal name from URL, null if not provided
if (isset($_GET['portal'])){
    $portal = $_GET['portal']; 
}
else {$portal = null;}

// Convert portal name to role number
if ($portal !== null && isset($portals[$portal])) {
    $required_role = $portals[$portal];
} else {
    $required_role = null; 
}

// Sanitize inputs to prevent SQL Injection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['user']);
    $password = mysqli_real_escape_string($conn, $_POST['pass']);

    // LOGIN LOGIC
    if (isset($_POST['login_mode'])) {
        $query = "SELECT * FROM users WHERE username='$username' AND password='$password'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            $_SESSION['swapy_session'] = $user['username'];
            $_SESSION['role'] = (int)$user['role'];

            if ($required_role !== null) {
                if ($_SESSION['role'] != $required_role) {
                    $message = "You are not authorized for this portal!";
                } else {
                    if ($_SESSION['role'] == 1) { header("Location: AdminCM/index.php"); exit(); }
                    elseif ($_SESSION['role'] == 2) { header("Location: delivery/index.php"); exit(); }
                    elseif ($_SESSION['role'] == 3) { header("Location: SystemAdmin/index.php"); exit(); }
                    elseif ($_SESSION['role'] == 4) { header("Location: CustSupport/index.php"); exit(); }
                }
            } else {
                if ($_SESSION['role'] == 0) {
                    header("Location: customer/index.php");
                    exit();
                } else {
                    $message = "You are not authorized!";
                }
            }

        } else {
            $message = "Invalid username or password!";
        }

    // SIGNUP LOGIC - available for customers only
    } elseif (isset($_POST['signup_mode']) && $required_role === null) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $fullname = mysqli_real_escape_string($conn, $_POST['name']);

        $sql = "INSERT INTO users (username, password, email, name, role) 
                VALUES ('$username', '$password', '$email', '$fullname', 0)";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['swapy_session'] = $username;
            $_SESSION['role'] = 0;
            header("Location: customer/index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Swapy | Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body class="<?php echo isset($_GET['show_splash']) ? '' : 'no-splash'; ?>">

    <div id="splash">
        <div class="splash-content">
            <h1>Swapy</h1>
            <p>The Art of Modern Exchange</p>
        </div>
    </div>

    <div class="login-container" id="login-UI">
        <div class="login-box">
            <h1 style="color:var(--primary); margin:0;">Swapy</h1>
            <p style="color:#666; margin-bottom:30px;">Access your marketplace</p>
            
            <?php if($message): ?>
                <div class="alert"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div id="signup-fields" style="display:none;">
                    <input type="text" name="name" class="form-input" placeholder="Full Name">
                    <input type="email" name="email" class="form-input" placeholder="Email Address">
                </div>
                <input type="text" name="user" class="form-input" placeholder="Username" required>
                <input type="password" name="pass" class="form-input" placeholder="Password" required>
                <button type="submit" id="submit-btn" class="primary-btn">Login</button>
                <input type="hidden" name="login_mode" id="form-mode" value="1">
                <?php if ($required_role === null) :?>
                <p id="toggle-link" style="margin-top:20px; cursor:pointer; font-size:14px; color:#555;" onclick="toggleForm()">
                    New here? <b>Create an account</b>
                </p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        window.onload = () => {
            const hasSplash = <?php echo isset($_GET['show_splash']) ? 'true' : 'false'; ?>;
            
            if (hasSplash) {
                setTimeout(() => {
                    const splash = document.getElementById('splash');
                    const loginUI = document.getElementById('login-UI');
                    splash.style.opacity = '0';
                    setTimeout(() => {
                        splash.style.display = 'none';
                        loginUI.style.opacity = '1';
                    }, 800);
                }, 1200);
            } else {
                document.getElementById('splash').style.display = 'none';
                document.getElementById('login-UI').style.opacity = '1';
            }
        };

        function toggleForm() {
            const signupFields = document.getElementById('signup-fields');
            const submitBtn = document.getElementById('submit-btn');
            const toggleLink = document.getElementById('toggle-link');
            const formMode = document.getElementById('form-mode');
            if (signupFields.style.display === 'none') {
                signupFields.style.display = 'block';
                submitBtn.innerText = 'Sign Up';
                toggleLink.innerHTML = 'Already have an account? <b>Login</b>';
                formMode.name = 'signup_mode';
            } else {
                signupFields.style.display = 'none';
                submitBtn.innerText = 'Login';
                toggleLink.innerHTML = 'New here? <b>Create an account</b>';
                formMode.name = 'login_mode';
            }
        }
    </script>
</body>
</html>