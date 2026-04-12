<?php
include 'dbconnect.php';
session_start();

// Security Guard
if(!isset($_SESSION['admin'])){
    header("Location: index.php");
    exit();
}

// Kunin ang ID mula sa URL
if(isset($_GET['id'])){
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "SELECT * FROM clients WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    $client = mysqli_fetch_assoc($result);

    if(!$client){
        echo "<script>alert('Client not found!'); window.location='client_list.php';</script>";
        exit();
    }
} else {
    header("Location: client_list.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile | Budget Supreme</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-main: #1f9d63;
            --green-glow: #2ecc71;
            --bg-dark: #02110a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        body::before {
            content: ""; position: fixed; inset: 0;
            background: linear-gradient(rgba(0,40,25,0.4), rgba(0,25,15,0.4)), url('graph.jpg') no-repeat center center fixed;
            background-size: cover; z-index: -2;
        }

        /* NAVBAR */
        nav {
            padding: 15px 60px;
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(8,18,14,0.85); backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(46,204,113,0.2);
            position: sticky; top: 0; z-index: 100;
        }

        .logo { font-weight: 800; letter-spacing: 3px; }
        .logo span { color: var(--green-glow); text-shadow: 0 0 10px var(--green-glow); }

        .nav-links { display: flex; gap: 25px; }
        .nav-links a {
            text-decoration: none; color: rgba(255,255,255,0.6);
            font-size: 12px; text-transform: uppercase; letter-spacing: 2px; transition: 0.3s;
        }
        .nav-links a:hover { color: var(--green-glow); }

        /* ADMIN MENU */
        .admin-menu { position: relative; }
        .admin-trigger {
            display: flex; align-items: center; gap: 10px; cursor: pointer;
            padding: 6px 15px; border-radius: 6px; background: rgba(46,204,113,0.05);
            border: 1px solid rgba(46,204,113,0.2); transition: 0.3s;
        }
        .admin-avatar {
            width: 28px; height: 28px; border-radius: 50%; background: var(--green-main);
            color: #000; display: flex; align-items: center; justify-content: center; font-weight: 800;
        }
        .admin-dropdown {
            position: absolute; top: 45px; right: 0; width: 180px;
            background: rgba(8,18,14,0.95); border: 1px solid var(--green-main);
            border-radius: 8px; display: none; flex-direction: column; overflow: hidden;
        }
        .admin-dropdown a { padding: 12px; text-decoration: none; color: #fff; font-size: 13px; transition: 0.2s; }
        .admin-dropdown a:hover { background: var(--green-main); color: #000; }

        /* PROFILE CARD */
        .profile-container {
            flex: 1;
            display: flex; justify-content: center; align-items: center;
            padding: 40px 20px;
        }

        .profile-card {
            width: 100%; max-width: 500px;
            background: rgba(8,18,14,0.8); backdrop-filter: blur(30px);
            border: 1px solid rgba(46,204,113,0.2); border-radius: 25px;
            padding: 40px; position: relative; overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }

        .profile-card::before {
            content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, var(--green-main), var(--green-glow));
        }

        .profile-header { text-align: center; margin-bottom: 30px; }
        .large-avatar {
            width: 80px; height: 80px; background: var(--green-main); color: #000;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 800; margin: 0 auto 15px;
            box-shadow: 0 0 20px rgba(46,204,113,0.3);
        }

        /* DATA GROUPS */
        .info-group { margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; }
        .info-label { font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.4); letter-spacing: 1.5px; margin-bottom: 5px; display: block; }
        .info-value { font-size: 16px; color: #fff; font-weight: 600; }

        .footer-actions { margin-top: 30px; display: flex; gap: 15px; }
        .btn { flex: 1; padding: 12px; border-radius: 10px; text-align: center; text-decoration: none; font-size: 12px; font-weight: 800; text-transform: uppercase; transition: 0.3s; }
        .btn-back { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
        .btn-edit { background: var(--green-main); color: #000; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
    </style>
</head>
<body>

<nav>
    <div class="logo">BUDGET <span>SUPREME</span></div>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="#">Users</a>
        <a href="client_list.php">Clients</a>
    </div>

    <div class="admin-menu">
        <div class="admin-trigger" onclick="toggleMenu()">
            <div class="admin-avatar">
                <?php echo strtoupper($_SESSION['admin'][0]); ?>
            </div>
            <span class="admin-name" style="font-size: 12px; margin-left: 5px; color: var(--green-glow);">
                <?php echo $_SESSION['admin']; ?>
            </span>
        </div>

        <div class="admin-dropdown" id="adminDropdown">
            <a href="profile_settings.php">Profile Settings</a>
            <a href="change_password.php">Change Password</a>
            <a href="logout.php" style="color:#ff6b6b;">Logout</a>
        </div>
    </div>
</nav>

<div class="profile-container">
    <div class="profile-card">
        <div class="profile-header">
            <div class="large-avatar"><?php echo strtoupper(substr($client['first_name'], 0, 1)); ?></div>
            <h2><?php echo $client['first_name'] . " " . $client['last_name']; ?></h2>
            <p style="color: var(--green-glow); font-size: 12px; font-weight: 700;">ACCOUNT ID: #<?php echo str_pad($client['id'], 4, '0', STR_PAD_LEFT); ?></p>
        </div>

        <div class="info-group">
            <span class="info-label">Username</span>
            <div class="info-value">@<?php echo $client['username']; ?></div>
        </div>

        <div class="info-group">
            <span class="info-label">Email Address</span>
            <div class="info-value"><?php echo $client['email']; ?></div>
        </div>

        <div class="info-group">
            <span class="info-label">Contact Number</span>
            <div class="info-value"><?php echo $client['contact_number'] ?: 'N/A'; ?></div>
        </div>

        <div class="footer-actions">
            <a href="client_list.php" class="btn btn-back">Back to List</a>
            <a href="edit_client.php?id=<?php echo $client['id']; ?>" class="btn btn-edit">Edit Profile</a>
        </div>
    </div>
</div>

<script>
function toggleMenu(){
    const menu = document.getElementById("adminDropdown");
    menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
}
window.onclick = function(e){
    if(!e.target.closest('.admin-menu')){
        document.getElementById("adminDropdown").style.display = "none";
    }
}
</script>

</body>
</html>