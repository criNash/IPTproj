<?php
session_start();
include 'dbconnect.php';

if(!isset($_SESSION['admin'])){
    header("Location: index.php");
    exit();
}

if(isset($_POST['change'])){
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $username = $_SESSION['admin'];
    $sql = "SELECT * FROM admin_users WHERE username='$username'";
    $result = mysqli_query($conn,$sql);
    $row = mysqli_fetch_assoc($result);

    if(password_verify($current, $row['password'])){
        if($new === $confirm){
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $update = "UPDATE admin_users SET password='$hashed' WHERE username='$username'";
            mysqli_query($conn,$update);
            echo "<script>alert('Password changed successfully!'); window.location='dashboard.php';</script>";
        } else {
            echo "<script>alert('New passwords do not match');</script>";
        }
    } else {
        echo "<script>alert('Current password incorrect');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">

<style>
:root {
    --green-main: #1f9d63;
    --green-glow: #2ecc71;
}

* { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans', sans-serif; }

body {
    background:#02110a;
    min-height:100vh;
    color:#fff;
    display:flex;
    flex-direction:column;
    align-items:center;
    overflow-x:hidden;
}

body::before{
    content:""; position:fixed; inset:0;
    background: linear-gradient(rgba(0,40,25,0.2), rgba(0,25,15,0.25)),
                url('graph.jpg') no-repeat center center fixed;
    background-size:cover; z-index:-2;
}

.particles{
    position:fixed; top:0; left:0; width:100%; height:100%;
    pointer-events:none; background: radial-gradient(circle, rgba(46,204,113,0.1) 1px, transparent 1px);
    background-size: 50px 50px; z-index:-1;
}

/* --- SUPREME NAVBAR --- */
nav{
    width:100%; padding:15px 60px; display:flex; justify-content:space-between; align-items:center;
    background: rgba(8,18,14,0.85); backdrop-filter:blur(25px); border-bottom:1px solid rgba(46,204,113,0.2);
    position:sticky; top:0; z-index:100;
}
nav .logo{ font-weight:800; letter-spacing:4px; font-size:18px; text-transform:uppercase; }
nav .logo span{ color: var(--green-glow); text-shadow:0 0 10px var(--green-glow); }

.nav-links{ display:flex; align-items: center; gap:30px; }
.nav-links a{ 
    text-decoration:none; font-size:12px; color:rgba(255,255,255,0.6); 
    text-transform:uppercase; letter-spacing:2px; font-weight:700; 
    transition:0.3s; position: relative; padding: 5px 0;
}

/* Underline Animation */
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
.nav-links a:hover{ color: var(--green-glow); }
.nav-links a:hover::after { width: 100%; }

/* ADMIN MENU */
.admin-menu{ position:relative; margin-left: 10px; }
.admin-trigger{
    display:flex; align-items:center; gap:10px; cursor:pointer; padding:6px 15px;
    border-radius:6px; background: rgba(46,204,113,0.05); border:1px solid rgba(46,204,113,0.2); transition:0.3s;
}
.admin-trigger:hover{ border-color: var(--green-glow); background: rgba(46,204,113,0.1); }
.admin-avatar{ width:28px; height:28px; border-radius:50%; background: var(--green-main); color:#000; display:flex; align-items:center; justify-content:center; font-weight:800; }
.admin-name{ font-size:12px; color:var(--green-glow); }
.admin-dropdown{
    position:absolute; top:45px; right:0; width:180px; background: rgba(8,18,14,0.95); border:1px solid var(--green-main);
    border-radius:8px; display:none; flex-direction:column; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.8);
}
.admin-dropdown a{ padding:12px; text-decoration:none; color:#fff; transition:0.2s; border-bottom:1px solid rgba(46,204,113,0.1); font-size: 13px; }
.admin-dropdown a:hover{ background:var(--green-main); color:#000; }

/* FORM CARD STYLES */
.main-wrapper{ flex:1; display:flex; justify-content:center; align-items:center; width:100%; padding:40px 20px; }
.form-card{
    background: rgba(15,15,15,0.5); backdrop-filter:blur(30px); padding:60px 50px;
    border-radius:20px; max-width:480px; width:100%; border:1px solid rgba(46,204,113,0.15);
    box-shadow:0 40px 80px rgba(0,0,0,0.8); text-align:center; position:relative; overflow:hidden;
}
.form-card::before{
    content:''; position:absolute; top:-50%; left:-50%; width:200%; height:200%;
    background: conic-gradient(from 0deg, transparent 0%, #fff 5%, var(--green-glow) 10%, transparent 20%);
    animation: spin 8s linear infinite; z-index:-1; opacity:0.2;
}
@keyframes spin{100%{ transform:rotate(360deg); }}
.inner-mask{ position:absolute; top:2px; left:2px; right:2px; bottom:2px; background: rgba(10,10,10,0.95); border-radius:18px; z-index:0; }
.form-content{ position:relative; z-index:1; }
h2{ margin-bottom:40px; font-size:22px; color:var(--green-glow); text-transform:uppercase; letter-spacing:4px; }

/* FLOATING INPUTS */
.input-group{ margin-bottom:35px; position:relative; text-align:left; }
.input-group input{
    width:100%; padding:12px 0; background:transparent; border:none; border-bottom:1px solid rgba(46,204,113,0.3);
    color:#fff; font-size:16px; outline:none; transition:0.3s;
}
.input-group input:focus{ border-bottom-color:var(--green-glow); }
.input-group label{
    position:absolute; top:12px; left:0; font-size:10px; color:rgba(46,204,113,0.5);
    text-transform:uppercase; letter-spacing:2px; font-weight:800; pointer-events:none; transition:0.3s;
}
.input-group input:focus + label, .input-group input:not(:placeholder-shown) + label{ top:-15px; font-size:9px; color:var(--green-glow); }

/* BUTTONS */
.btn-group{ display:flex; gap:10px; margin-top:20px; }
.btn-base{
    flex:1; padding:16px; border-radius:4px; font-weight:800; text-transform:uppercase; letter-spacing:2px; cursor:pointer;
    font-size:11px; transition:0.4s; display:flex; align-items:center; justify-content:center; text-decoration:none;
}
.btn-update{ border:1px solid var(--green-glow); background:var(--green-glow); color:#000; }
.btn-update:hover{ background:#fff; border-color:#fff; box-shadow:0 0 20px var(--green-glow); }
.btn-cancel{ border:1px solid rgba(255,255,255,0.2); background:transparent; color:#fff; }
.btn-cancel:hover{ border-color:#fff; background: rgba(255,255,255,0.05); }
</style>
</head>
<body>
<div class="particles"></div>

<nav>
    <div class="logo">BUDGET <span>SUPREME</span></div>
    
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="statistics.php">Statistics</a>
        <a href="client_list.php">Total Users</a>

        <div class="admin-menu">
            <div class="admin-trigger" onclick="toggleMenu()">
                <div class="admin-avatar"><?php echo strtoupper($_SESSION['admin'][0]); ?></div>
                <span class="admin-name"><?php echo $_SESSION['admin']; ?></span>
            </div>
            <div class="admin-dropdown" id="adminDropdown">
                <a href="profile_settings.php">Profile Settings</a>
                <a href="change_password.php">Change Password</a>
                <a href="logout.php" style="color:#ff6b6b;">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="main-wrapper">
    <div class="form-card">
        <div class="inner-mask"></div>
        <div class="form-content">
            <h2>Change Password</h2>
            <form method="POST" onsubmit="return confirm('Confirm password change?');">
                <div class="input-group">
                    <input type="password" name="current_password" required placeholder=" ">
                    <label>Current Password</label>
                </div>
                <div class="input-group">
                    <input type="password" name="new_password" required placeholder=" ">
                    <label>New Password</label>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" required placeholder=" ">
                    <label>Confirm Password</label>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="change" class="btn-base btn-update">Commit</button>
                    <a href="dashboard.php" class="btn-base btn-cancel">Cancel</a>
                </div>
            </form>
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
        const menu = document.getElementById("adminDropdown");
        if(menu) menu.style.display = "none";
    }
}
</script>

</body>
</html>
