<?php
include 'dbconnect.php';
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: index.php");
    exit();
}

// Kunin lahat ng clients (Oldest to Latest base sa gusto mo)
$sql = "SELECT id, first_name, last_name, username, email, contact_number FROM clients ORDER BY id ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Registry | Budget Supreme</title>
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
            overflow-x: hidden;
        }

        body::before {
            content: ""; position: fixed; inset: 0;
            background: linear-gradient(rgba(0,40,25,0.2), rgba(0,25,15,0.25)), url('graph.jpg') no-repeat center center fixed;
            background-size: cover; z-index: -2;
        }

        /* NAVIGATION BAR */
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

        /* TABLE CONTAINER */
        .container { max-width: 1200px; margin: 50px auto; padding: 0 20px; }
        
        .header-section { 
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 30px; border-left: 4px solid var(--green-glow); padding-left: 20px;
        }
        .header-section h1 { font-size: 32px; font-weight: 800; }
        .header-section p { color: rgba(255,255,255,0.5); font-size: 14px; }

        /* TABLE STYLING */
        .table-wrapper {
            background: rgba(8,18,14,0.7); backdrop-filter: blur(25px);
            border-radius: 20px; border: 1px solid rgba(46,204,113,0.1);
            overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { 
            background: rgba(31, 157, 99, 0.1); padding: 20px; 
            font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: var(--green-glow);
            border-bottom: 1px solid rgba(46,204,113,0.2);
        }
        td { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; color: rgba(255,255,255,0.8); }
        tr:hover { background: rgba(46,204,113,0.03); }

        /* ACTION BUTTONS */
        .action-btns { display: flex; gap: 8px; }
        .btn { 
            padding: 8px 14px; border-radius: 6px; text-decoration: none; 
            font-size: 10px; font-weight: 800; text-transform: uppercase; transition: 0.3s;
        }
        .btn-view { background: transparent; border: 1px solid var(--green-glow); color: var(--green-glow); }
        .btn-edit { background: var(--green-main); color: #000; border: 1px solid var(--green-main); }
        .btn-delete { background: rgba(255,107,107,0.1); border: 1px solid #ff6b6b; color: #ff6b6b; }
        
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); opacity: 0.9; }
        .btn-view:hover { background: var(--green-glow); color: #000; }
    </style>
</head>
<body>

<nav>
    <div class="logo">BUDGET <span>SUPREME</span></div>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="statistics.php">Statistics</a>
        <a href="client_list.php" style="color: var(--green-glow);">Total Users</a>
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

<div class="container">
    <div class="header-section">
        <div>
            <h1>Client <span>Registry</span></h1>
            <p>Managing all authorized budget supreme accounts.</p>
        </div>
        <a href="dashboard.php" style="color: var(--green-glow); text-decoration: none; font-size: 12px; font-weight: 800;">← BACK TO SYSTEM</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Account ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email Address</th>
                    <th>Management</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td style="font-family: monospace; color: var(--green-glow);">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td style="font-weight: 600; color: #fff;"><?php echo $row['first_name'] . " " . $row['last_name']; ?></td>
                    <td>@<?php echo $row['username']; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td class="action-btns">
                        <a href="view_client.php?id=<?php echo $row['id']; ?>" class="btn btn-view">View</a>
                        <a href="edit_client.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">Edit</a>
                        <a href="delete_client.php?id=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('WARNING: Are you sure you want to purge this account?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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
