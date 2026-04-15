<?php
include 'dbconnect.php';
session_start();

// 🔐 SECURITY
if(!isset($_SESSION['admin'])){
    header("Location: index.php");
    exit();
}

// 📊 TOTAL ADMINS
$userQuery = "SELECT COUNT(*) AS totalUsers FROM admin_users";
$userResult = mysqli_query($conn, $userQuery);
$userData = mysqli_fetch_assoc($userResult);
$userCount = $userData ? $userData['totalUsers'] : 0;

// 📊 TOTAL CLIENTS
$clientQuery = "SELECT COUNT(*) AS totalClients FROM clients";
$clientResult = mysqli_query($conn, $clientQuery);
$clientData = mysqli_fetch_assoc($clientResult);
$clientCount = $clientData ? $clientData['totalClients'] : 0;

// 📊 NEW ACCOUNTS TODAY
// Kukunin natin ang bilang ng clients na nag-register ngayong araw
$today = date('Y-m-d');
$newAccountsQuery = "SELECT COUNT(*) AS newToday FROM clients WHERE DATE(created_at) = '$today'";
$newAccountsResult = mysqli_query($conn, $newAccountsQuery);
$newAccountsData = mysqli_fetch_assoc($newAccountsResult);
$newAccountsCount = $newAccountsData ? $newAccountsData['newToday'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Budget Supreme</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green-main: #1f9d63;
            --green-glow: #2ecc71;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: #02110a;
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* BACKGROUND */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: 
                linear-gradient(rgba(0,40,25,0.2), rgba(0,25,15,0.25)),
                url('graph.jpg') no-repeat center center;
            background-size: cover;
            z-index: -2;
        }

        /* ORB EFFECT */
        .orb {
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(46,204,113,0.25), transparent);
            filter: blur(120px);
            animation: floatOrb 25s infinite alternate ease-in-out;
            z-index: -1;
        }

        @keyframes floatOrb {
            from { transform: translate(-20%, -15%); }
            to { transform: translate(20%, 15%); }
        }

        /* --- ENHANCED NAVIGATION --- */
        nav {
            padding: 15px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(8,18,14,0.85);
            backdrop-filter: blur(25px);
            border-bottom: 1px solid rgba(46,204,113,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: all 0.4s ease;
        }

        .logo {
            font-weight: 800;
            letter-spacing: 3px;
            transition: 0.3s;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo span {
            color: var(--green-glow);
            text-shadow: 0 0 10px var(--green-glow);
            animation: logoPulse 2s infinite alternate;
        }

        @keyframes logoPulse {
            from { text-shadow: 0 0 10px var(--green-glow); }
            to { text-shadow: 0 0 25px var(--green-glow), 0 0 5px #fff; }
        }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-links a {
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: 0.3s;
            position: relative;
            padding: 5px 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--green-glow);
            box-shadow: 0 0 10px var(--green-glow);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--green-glow);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* --- ADMIN MENU --- */
        .admin-menu { position: relative; }

        .admin-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 6px 15px;
            border-radius: 6px;
            background: rgba(46,204,113,0.05);
            border: 1px solid rgba(46,204,113,0.2);
            transition: 0.3s;
        }

        .admin-trigger:hover {
            border-color: var(--green-glow);
            background: rgba(46,204,113,0.1);
            box-shadow: 0 0 15px rgba(46,204,113,0.2);
        }

        .admin-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--green-main);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .admin-name { font-size: 12px; color: var(--green-glow); }

        .admin-dropdown {
            position: absolute;
            top: 45px;
            right: 0;
            width: 180px;
            background: rgba(8,18,14,0.95);
            border: 1px solid var(--green-main);
            border-radius: 8px;
            display: none;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }

        .admin-dropdown a { padding: 12px; text-decoration: none; color: #fff; transition: 0.2s; font-size: 13px; }
        .admin-dropdown a:hover { background: var(--green-main); color: #000; }

        /* --- CONTENT --- */
        .container {
            max-width: 1200px;
            margin: 80px auto;
            text-align: center;
        }

        .header-section h1 {
            font-size: 48px;
            letter-spacing: 5px;
            font-weight: 800;
        }

        .header-section span {
            display: block;
            color: var(--green-glow);
            text-shadow: 0 0 20px var(--green-glow);
        }

        /* --- ULTRA SUPREME CARDS --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 60px;
            padding: 0 20px;
        }

        .stat-card {
            height: 250px;
            border-radius: 20px;
            background: rgba(8,18,14,0.7);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(46,204,113,0.1);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            width: 150%;
            height: 150%;
            background: conic-gradient(transparent, transparent, transparent, var(--green-glow));
            animation: rotate 4s linear infinite;
            opacity: 0;
            transition: 0.5s;
        }

        .stat-card:hover::before { opacity: 1; }

        .stat-card::after {
            content: "";
            position: absolute;
            inset: 3px;
            background: rgba(8,18,14,0.95);
            border-radius: 18px;
            z-index: 0;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .stat-card:hover {
            transform: translateY(-15px) scale(1.05);
            box-shadow: 0 0 30px rgba(46,204,113,0.3);
            border-color: var(--green-glow);
        }

        .stat-card > * { position: relative; z-index: 1; }

        .stat-title {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 60px;
            color: var(--green-glow);
            font-weight: 800;
            text-shadow: 0 0 15px rgba(46,204,113,0.5);
            animation: floating 3s infinite ease-in-out;
        }

        /* ICONS */
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
            filter: drop-shadow(0 0 10px var(--green-glow));
        }

        @keyframes floating {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .footer {
            margin-top: 100px;
            font-size: 10px;
            letter-spacing: 5px;
            opacity: 0.4;
        }
    </style>
</head>

<body>

<div class="orb"></div>

<nav>
    <div class="logo">BUDGET <span>SUPREME</span></div>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="statistics.php">Statistics</a>
        <a href="client_list.php">Total Users</a>
    </div>

    <div class="admin-menu">
        <div class="admin-trigger" onclick="toggleMenu()">
            <div class="admin-avatar">
                <?php echo strtoupper($_SESSION['admin'][0]); ?>
            </div>
            <span class="admin-name">
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

<div class="container">

    <div class="header-section">
        <h1>EXECUTIVE <span>DASHBOARD</span></h1>
    </div>

    <div class="stats-grid">
        <a href="statistics.php" style="text-decoration: none; color: inherit;">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-title">System Insights</div>
                <div class="stat-number" style="font-size: 20px;">VIEW STATS</div>
            </div>
        </a>
        
        <a href="client_list.php" style="text-decoration: none; color: inherit;">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-title">Total User Accounts</div>
                <div class="stat-number"><?php echo $clientCount; ?></div>
            </div>
        </a>

        <div class="stat-card">
            <div class="stat-icon">✨</div>
            <div class="stat-title">New Accounts Today</div>
            <div class="stat-number"><?php echo $newAccountsCount; ?></div>
        </div>
    </div>

    <div class="footer">
        BUDGET SUPREME SYSTEM © 2026
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
