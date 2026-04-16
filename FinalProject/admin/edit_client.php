<?php
include 'dbconnect.php';
session_start();

// 🔐 SECURITY
if(!isset($_SESSION['admin'])){
    header("Location: index.php");
    exit();
}

// 1. KUNIN ANG DATA NG CLIENT PARA SA FORM
if(isset($_GET['id'])){
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "SELECT * FROM clients WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    $client = mysqli_fetch_assoc($result);

    if(!$client){
        echo "<script>alert('Client not found!'); window.location='client_list.php';</script>";
        exit();
    }
}

// 2. LOGIC PARA SA PAG-UPDATE NG DATA
if(isset($_POST['update_client'])){
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact_number']);

    // --- ADDED: ERROR HANDLING PARA SA DUPLICATE USERNAME ---
    // I-check muna natin kung may kaparehong username pero HINDI ito yung current ID na ini-edit natin
    $checkUser = "SELECT * FROM clients WHERE username = '$username' AND id != '$id'";
    $checkResult = mysqli_query($conn, $checkUser);

    if(mysqli_num_rows($checkResult) > 0) {
        // Kapag may nahanap, ibig sabihin taken na ang username
        echo "<script>alert('Error: Username \'$username\' is already taken! Please choose another one.');</script>";
    } else {
        // Kapag wala, safe na i-proceed ang update
        $updateQuery = "UPDATE clients SET 
                        first_name = '$first_name', 
                        last_name = '$last_name', 
                        username = '$username', 
                        email = '$email', 
                        contact_number = '$contact' 
                        WHERE id = '$id'";

        if(mysqli_query($conn, $updateQuery)){
            echo "<script>alert('Client updated successfully!'); window.location='client_list.php';</script>";
        } else {
            echo "<script>alert('Error updating client: " . mysqli_error($conn) . "');</script>";
        }
    }
    // -------------------------------------------------------
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client | Budget Supreme</title>
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
            border: 1px solid rgba(46,204,113,0.2);
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

        /* FORM CONTAINER */
        .form-container {
            max-width: 600px;
            margin: 60px auto;
            background: rgba(8,18,14,0.8);
            backdrop-filter: blur(25px);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(46,204,113,0.2);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .form-header { margin-bottom: 30px; border-left: 4px solid var(--green-glow); padding-left: 15px; }
        .form-header h2 { font-size: 24px; }
        .form-header p { font-size: 12px; color: rgba(255,255,255,0.5); }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--green-glow); margin-bottom: 8px; }
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(46,204,113,0.2);
            border-radius: 8px;
            color: #fff;
            outline: none;
            transition: 0.3s;
        }
        .input-group input:focus { border-color: var(--green-glow); background: rgba(46,204,113,0.05); }

        .btn-update {
            width: 100%;
            padding: 15px;
            background: var(--green-main);
            color: #000;
            border: none;
            border-radius: 10px;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-update:hover { background: var(--green-glow); box-shadow: 0 0 20px var(--green-glow); transform: translateY(-2px); }

        .cancel-link { display: block; text-align: center; margin-top: 20px; color: rgba(255,255,255,0.4); text-decoration: none; font-size: 12px; }
        .cancel-link:hover { color: #ff6b6b; }
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
            <div class="admin-avatar"><?php echo strtoupper($_SESSION['admin'][0]); ?></div>
            <span class="admin-name" style="font-size: 12px; margin-left: 5px; color: var(--green-glow);"><?php echo $_SESSION['admin']; ?></span>
        </div>
        <div class="admin-dropdown" id="adminDropdown">
            <a href="profile_settings.php">Profile Settings</a>
            <a href="change_password.php">Change Password</a>
            <a href="logout.php" style="color:#ff6b6b;">Logout</a>
        </div>
    </div>
</nav>

<div class="form-container">
    <div class="form-header">
        <h2>Edit Client <span>Profile</span></h2>
        <p>Updating information for ID #<?php echo str_pad($client['id'], 4, '0', STR_PAD_LEFT); ?></p>
    </div>

    <form method="POST">
        <div style="display: flex; gap: 20px;">
            <div class="input-group" style="flex: 1;">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?php echo $client['first_name']; ?>" required>
            </div>
            <div class="input-group" style="flex: 1;">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?php echo $client['last_name']; ?>" required>
            </div>
        </div>

        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo $client['username']; ?>" required>
        </div>

        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo $client['email']; ?>" required>
        </div>

        <div class="input-group">
            <label>Contact Number</label>
            <input type="text" name="contact_number" value="<?php echo $client['contact_number']; ?>">
        </div>

        <button type="submit" name="update_client" class="btn-update">Update Profile</button>
        <a href="client_list.php" class="cancel-link">Cancel and Go Back</a>
    </form>
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
