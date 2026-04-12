<?php
include 'dbconnect.php';

if(isset($_POST['register'])){
    $fname    = mysqli_real_escape_string($conn, $_POST['first_name']);
    $mname    = mysqli_real_escape_string($conn, $_POST['middle_name']);
    $lname    = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $contact  = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $user     = mysqli_real_escape_string($conn, $_POST['username']);
    $pass     = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    // Hybrid Security fields
    $question = mysqli_real_escape_string($conn, $_POST['security_question']);
    $answer   = mysqli_real_escape_string($conn, $_POST['security_answer']);
    $pin      = mysqli_real_escape_string($conn, $_POST['recovery_pin']);

    if($pass !== $confirm){
        echo "<script>alert('Passwords do not match!');</script>";
    } else {
        $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO clients (first_name, middle_name, last_name, username, password, email, contact_number, security_question, security_answer, recovery_pin)
                VALUES ('$fname', '$mname', '$lname', '$user', '$hashed_pass', '$email', '$contact', '$question', '$answer', '$pin')";

        if(mysqli_query($conn, $sql)){
            echo "<script>alert('Registration Successful!'); window.location='index.php';</script>";
        } else {
            echo "<script>alert('Error: Data could not be saved.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Budget Supreme</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --green-main: #1f9d63; --green-glow: #2ecc71; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #02110a; position: relative; overflow-y: auto; padding: 40px 0; }
        body::before { content: ""; position: fixed; inset: 0; background: linear-gradient(rgba(0,40,25,0.2), rgba(0,25,15,0.25)), url('graph.jpg') no-repeat center center; background-size: cover; z-index: 0; }

        .login-card { width: 95%; max-width: 650px; padding: 50px; border-radius: 30px; background: rgba(8,18,14,0.85); backdrop-filter: blur(25px); border: 1px solid rgba(46,204,113,0.25); z-index: 2; position: relative; text-align: center; }
        h2 { color: #fff; font-size: 32px; margin-bottom: 10px; }
        h4 { color: var(--green-main); font-size: 11px; margin-bottom: 35px; letter-spacing: 2px; }

        .input-row { display: flex; gap: 15px; flex-wrap: wrap; }
        .input-row .input-group { flex: 1; min-width: 180px; }

        .input-group { position: relative; margin-bottom: 25px; text-align: left; }
        .input-group label { display: block; font-size: 11px; color: #aaa; margin-bottom: 5px; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; font-family: 'Plus Jakarta Sans', sans-serif; }

        .input-group input, .input-group select {
            width: 100%;
            padding: 14px;
            background: transparent;
            border: none;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            outline: none;
            transition: 0.3s;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
        }
        .input-group input:focus, .input-group select:focus { border-bottom: 1px solid var(--green-main); }

        .input-group select option { background: #161a19; color: #fff; }

        .section-divider { margin: 10px 0 20px; border: none; border-top: 1px solid rgba(46,204,113,0.15); }
        .section-label { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: rgba(46,204,113,0.6); margin-bottom: 16px; text-align: left; }

        .btn-login { width: 100%; padding: 16px; border-radius: 12px; border: none; background: linear-gradient(135deg, var(--green-main), var(--green-glow)); font-weight: 800; color: #000; cursor: pointer; transition: all 0.3s; margin-top: 10px; text-transform: uppercase; letter-spacing: 1px; font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-login:hover { box-shadow: 0 10px 25px rgba(46,204,113,0.5); transform: scale(1.02); }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Create Account</h2>
        <h4>BE A BUDGET SUPREME</h4>

        <form method="POST">
            <div class="input-row">
                <div class="input-group"><label>First Name</label><input type="text" name="first_name" required></div>
                <div class="input-group"><label>Middle Name</label><input type="text" name="middle_name"></div>
                <div class="input-group"><label>Last Name</label><input type="text" name="last_name" required></div>
            </div>

            <div class="input-row">
                <div class="input-group"><label>Email Address</label><input type="email" name="email" required></div>
                <div class="input-group"><label>Contact Number</label><input type="text" name="contact_number" required></div>
            </div>

            <div class="input-group"><label>System Username</label><input type="text" name="username" required></div>

            <div class="input-row">
                <div class="input-group"><label>Secure Password</label><input type="password" name="password" required></div>
                <div class="input-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
            </div>

            <hr class="section-divider">
            <p class="section-label">🔐 Account Recovery Setup</p>

            <div class="input-row">
                <div class="input-group">
                    <label>Security Question</label>
                    <select name="security_question" required>
                        <option value="" disabled selected>Select a question...</option>
                        <option value="In what city did your parents meet?">In what city did your parents meet?</option>
                        <option value="What was the name of your first pet?">What was the name of your first pet?</option>
                        <option value="What was the name of your first elementary teacher?">What was the name of your first elementary teacher?</option>
                        <option value="What is the middle name of your bestfriend?">What is the middle name of your bestfriend?</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Security Answer</label>
                    <input type="text" name="security_answer" placeholder="Your Answer" required>
                </div>
            </div>

            <div class="input-group">
                <label>Recovery PIN (6 Digits)</label>
                <input type="text" name="recovery_pin" placeholder="Ex: 123456" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
            </div>

            <button class="btn-login" type="submit" name="register">Initialize Activation</button>
            <a href="index.php" style="display:block; margin-top:20px; color:#aaa; font-size:11px; text-decoration:none; letter-spacing: 1px;">ALREADY HAVE AN ACCOUNT? LOGIN HERE</a>
        </form>
    </div>
</body>
</html>
