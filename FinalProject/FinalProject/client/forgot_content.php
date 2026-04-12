<?php 
if(!isset($_SESSION)) { session_start(); } 

if(isset($_GET['new'])){
    session_unset();
    header("Location: index.php");
    exit();
}
?>

<div id="recovery_inner">
    <?php if(!isset($_SESSION['step'])): ?>
        <h3 style="color: #fff; margin-bottom: 10px;">Recover Password</h3>
        <p style="font-size: 11px; color: #888; margin-bottom: 20px;">Enter your registered email to begin.</p>
        <form action="process_recovery.php" method="POST">
            <input type="email" name="email" placeholder="Email Address" required 
                   style="width: 100%; padding: 12px; background: #02110a; border: 1px solid #333; color: #fff; border-radius: 8px; margin-bottom: 15px;">
            <button type="submit" name="btn_email" 
                    style="width: 100%; padding: 12px; background: #1f9d63; border: none; color: #fff; font-weight: bold; border-radius: 8px; cursor: pointer;">
                Find Account
            </button>
        </form>

 <?php elseif($_SESSION['step'] == 'verify'): ?>
    <h3 style="color: #fff; margin-bottom: 10px;">Security Check</h3>
    <p style="font-size: 11px; color: #888; margin-bottom: 20px;">How would you like to verify your identity?</p>

    <div id="choiceButtons">
        <button onclick="showVerify('question')" style="width: 100%; padding: 12px; background: rgba(46,204,113,0.1); border: 1px solid #2ecc71; color: #2ecc71; border-radius: 8px; cursor: pointer; margin-bottom: 10px; font-weight: bold;">
            ANSWER SECURITY QUESTION
        </button>
        <button onclick="showVerify('pin')" style="width: 100%; padding: 12px; background: rgba(46,204,113,0.1); border: 1px solid #2ecc71; color: #2ecc71; border-radius: 8px; cursor: pointer; font-weight: bold;">
            USE RECOVERY PIN
        </button>
    </div>

    <form action="process_recovery.php" method="POST" id="verifyForm" style="display: none; margin-top: 15px;">
        <div id="questionInput" style="display: none;">
            <p style="text-align: left; font-size: 11px; color: #2ecc71; margin-bottom: 5px; text-transform: uppercase;">
                Question: <?php echo $_SESSION['user_q']; ?>
            </p>
            <input type="text" name="ans" placeholder="Your Secret Answer" 
                   style="width: 100%; padding: 12px; background: #02110a; border: 1px solid #333; color: #fff; border-radius: 8px; margin-bottom: 10px;">
        </div>

        <div id="pinInput" style="display: none;">
            <input type="text" name="pin" placeholder="Enter 6-Digit PIN" maxlength="6" 
                   style="width: 100%; padding: 12px; background: #02110a; border: 1px solid #333; color: #fff; border-radius: 8px; text-align: center; letter-spacing: 5px; font-weight: bold;">
        </div>

        <button type="submit" name="btn_verify" 
                style="width: 100%; padding: 12px; background: #1f9d63; border: none; color: #fff; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 10px;">
            Authorize Identity
        </button>
        <br>
        <a href="javascript:void(0)" onclick="resetChoice()" style="color: #555; font-size: 10px; text-decoration: none; display: block; margin-top: 15px;">← Back to choices</a>
    </form>

    <script>
    function showVerify(type) {
        document.getElementById('choiceButtons').style.display = 'none';
        document.getElementById('verifyForm').style.display = 'block';
        if(type === 'question') {
            document.getElementById('questionInput').style.display = 'block';
            document.getElementById('pinInput').style.display = 'none';
        } else {
            document.getElementById('pinInput').style.display = 'block';
            document.getElementById('questionInput').style.display = 'none';
        }
    }
    function resetChoice() {
        document.getElementById('choiceButtons').style.display = 'block';
        document.getElementById('verifyForm').style.display = 'none';
    }
    </script>

    <?php elseif($_SESSION['step'] == 'reset'): ?>
        <h3 style="color: #fff; margin-bottom: 10px;">New Password</h3>
        <form action="process_recovery.php" method="POST">
            <input type="password" name="pass1" placeholder="New Password" required 
                   style="width: 100%; padding: 12px; background: #02110a; border: 1px solid #333; color: #fff; border-radius: 8px; margin-bottom: 10px;">
            <input type="password" name="pass2" placeholder="Confirm Password" required 
                   style="width: 100%; padding: 12px; background: #02110a; border: 1px solid #333; color: #fff; border-radius: 8px; margin-bottom: 15px;">
            <button type="submit" name="btn_reset" 
                    style="width: 100%; padding: 12px; background: #2ecc71; border: none; color: #02110a; font-weight: bold; border-radius: 8px; cursor: pointer;">
                Update Password
            </button>
        </form>
    <?php endif; ?>
</div>