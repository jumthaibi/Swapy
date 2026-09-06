<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swapy | Welcome</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,600&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
</head>
<body>

    <nav class="navbar">
        <div class="nav-logo" onclick="location.reload()">Swapy
            <span style="font-weight:300; font-size: 0.9rem; color: #64748b;">| Marketplace Portal</span>
        </div>
    </nav>

    <section id="roles-view">
        <div class="section-header">
            <h2>Select Access Point</h2>
            <p>Choose your workspace to continue</p>
        </div>
        
        <div class="roles-grid">

            <div class="role-card" onclick="location.href='login.php?portal=AdminCM';">
                <i>⚖️</i>
                <h4>Admin (Content Moderator)</h4>
            </div>
            
            <div class="role-card" onclick="location.href='login.php?portal=Delivery';">
                <i>🚚</i>
                <h4>Delivery Coordination</h4>
            </div>
            
            <div class="role-card" onclick="location.href='login.php?portal=SystemAdmin';">
                <i>👔</i>
                <h4>System Administrator</h4>
            </div>
            
            <div class="role-card" onclick="location.href='login.php?portal=CustSupport';">
                <i>🧑🏼‍💻</i>
                <h4>Customer Support</h4>
            </div>
        </div>
    </section>
</body>
</html>