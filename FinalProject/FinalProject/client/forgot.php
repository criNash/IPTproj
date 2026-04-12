<?php 
session_start(); 
if(isset($_GET['new'])){
    session_unset();
    header("Location: forgot.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Supreme | Account Recovery</title>
    <style>
        body { background-color: #0b0d0c; color: white; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .recovery-card { background: #161a19; padding: 40px; border-radius: 15px; width: 100%; max-width: 350px; text-align: center; border: 1px solid #2ecc71; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .logo { color: #2ecc71; font-size: 24px; font-weight: bold; margin-bottom: 10px; letter-spacing: 2px; }
        h3 { margin-bottom: 20px; font-weight: 300; color: #eee; }
        input, select { width: 100%; padding: 12px; margin: 10px 0; background: #0b0d0c; border: 1px solid #333; color: white; border-radius: 5px; box-sizing: border-box; }
        input:focus { border-color: #2ecc71; outline: none; }
        button { width: 100%; padding: 12px; background: #2ecc71; border: none; color: #0b0d0c; font-weight: bold; cursor: pointer; border-radius: 5px; transition: 0.3s; margin-top: 10px; }
        button:hover { background: #27ae60; transform: translateY(-2px); }
        .step-indicator { font-size: 12px; color: #888; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
        a { color: #2ecc71; text-decoration: none; font-size: 13px; display: block; margin-top: 20px; }
    </style>
</head>
<body>

<div class="recovery-card">
    <div class="logo">BUDGET SUPREME</div>
    
    <?php if(!isset($_SESSION['step'])): ?>
        <div class="step-indicator">Step 1: Identify Account</div>
        <h3>Recover Password</h3>
        <form action="process_recovery.php" method="POST">
            <input type="email" name="email" placeholder="Enter your registered email" required>
            <button type="submit" name="btn_email">Find Account</button>
        </form>

<?php elseif($_SESSION['step'] == 'verify'): ?>
    <div class="step-indicator">Step 2: Verification Protocol</div>
    <h3>Security Check</h3>
    <p style="font-size: 12px; color: #888; margin-bottom: 20px;">
        To protect your account, please provide the security credential you set during registration.
    </p>

    <form action="process_recovery.php" method="POST" id="verifyForm">
        <div style="background: rgba(46,204,113,0.05); border: 1px solid #333; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <p style="text-align: left; font-size: 11px; color: #2ecc71; margin: 0 0 10px 0; text-transform: uppercase;">Security Question</p>
            <p style="text-align: left; font-size: 14px; margin-bottom: 10px; font-weight: bold; color: #fff;">
                <?php echo $_SESSION['user_q']; ?>
            </p>
            <input type="text" name="ans" placeholder="Type your answer here..." style="margin: 0;">
        </div>

        <div style="margin: 15px 0; color: #555; font-size: 12px; font-weight: bold;">— OR USE RECOVERY PIN —</div>

        <input type="text" name="pin" placeholder="Enter 6-Digit PIN" maxlength="6" style="text-align: center; letter-spacing: 5px; font-weight: bold;">
        
        <button type="submit" name="btn_verify">Authorize Identity</button>
    </form>

    <?php elseif($_SESSION['step'] == 'reset'): ?>
        <div class="step-indicator">Step 3: New Password</div>
        <h3>Reset Password</h3>
        <form action="process_recovery.php" method="POST">
            <input type="password" name="pass1" placeholder="New Password" required>
            <input type="password" name="pass2" placeholder="Confirm New Password" required>
            <button type="submit" name="btn_reset">Update Password</button>
        </form>
    <?php endif; ?>

    <a href="index.php">Back to Login</a>
</div>

</body>
</html>