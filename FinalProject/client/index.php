<?php session_start(); ?>
<?php 
session_start(); 

if(isset($_GET['new'])){
    session_unset();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Budget Supreme</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">

<style>
:root {
    --green-main: #1f9d63;
    --green-glow: #2ecc71;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #02110a;
    position: relative;
    overflow: hidden;
}

/* BACKGROUND */
body::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(rgba(0,40,25,0.2), rgba(0,25,15,0.25)),
        url('graph.jpg') no-repeat center center;
    background-size: cover;
    z-index: 0;
}

/* ORB */
.orb {
    position: absolute;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(46,204,113,0.25), transparent);
    filter: blur(120px);
    animation: floatOrb 25s infinite alternate ease-in-out;
    z-index: 1;
}

@keyframes floatOrb {
    from { transform: translate(-20%, -15%); }
    to { transform: translate(20%, 15%); }
}

/* CARD */
.login-card {
    width: 95%;
    max-width: 1100px;
    display: flex;
    border-radius: 30px;
    overflow: hidden;
    background: rgba(8,18,14,0.85);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(46,204,113,0.25);
    z-index: 2;
    position: relative;
    transition: transform 0.3s;
}

.login-card:hover {
    transform: translateY(-5px) scale(1.01);
}

/* DIVIDER EFFECT */
.login-card::after {
    content: "";
    position: absolute;
    top: 10%;
    bottom: 10%;
    left: 50%;
    width: 1px;
    background: linear-gradient(to bottom, transparent, rgba(46,204,113,0.6), transparent);
    box-shadow: 0 0 15px rgba(46,204,113,0.5);
    opacity: 0.5;
}

/* BRAND */
.brand-side {
    flex: 1;
    padding: 80px 50px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.company-logo {
    max-width: 260px;
    filter: drop-shadow(0 0 40px var(--green-glow));
    animation: floatLogo 4s infinite ease-in-out;
}

@keyframes floatLogo {
    50% { transform: translateY(-10px); }
}

/* TITLE */
.brand-side h1 {
    margin-top: 25px;
    font-size: 64px;
    font-weight: 800;
    text-align: center;
    background: linear-gradient(90deg, #2ecc71, #a8ffdc, #2ecc71);
    background-size: 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: transparent;
    animation: gradientMove 6s linear infinite;
}

@keyframes gradientMove {
    0% { background-position: 0%; }
    100% { background-position: 200%; }
}

.brand-side span {
    display: block;
    font-size: 14px;
    letter-spacing: 10px;
    color: #2ecc71;
}

/* FORM */
.form-side {
    flex: 1;
    padding: 80px 60px;
}

.form-header h2 {
    color: #fff;
    font-size: 32px;
}

.form-header h4 {
    color: var(--green-main);
    font-size: 11px;
    margin-bottom: 35px;
}

/* INPUT */
.input-group {
    position: relative;
    margin-bottom: 25px;
}

.input-group input {
    width: 100%;
    padding: 14px;
    background: transparent;
    border: none;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    color: #fff;
    outline: none;
    transition: 0.3s;
}

.input-group input:focus {
    border-bottom: 1px solid var(--green-main);
    box-shadow: 0 5px 15px rgba(46,204,113,0.2);
}

.input-group label {
    display: block;
    font-size: 11px;
    color: #aaa;
    margin-bottom: 5px;
}

/* TOGGLE */
.toggle {
    position: absolute;
    right: 10px;
    top: 38px;
    cursor: pointer;
    user-select: none;
}

/* BUTTONS */
.btn-login {
    width: 100%;
    padding: 14px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, var(--green-main), var(--green-glow));
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-login:hover {
    box-shadow: 0 10px 25px rgba(46,204,113,0.5);
    transform: scale(1.02);
}

.btn-reset {
    margin-top: 10px;
    width: 100%;
    padding: 12px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2);
    color: #aaa;
    border-radius: 10px;
    cursor: pointer;
}

/* RESPONSIVE */
@media(max-width: 850px){
    .login-card { flex-direction: column; }
    .login-card::after { display: none; }
}

/* MODAL OVERLAY */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 999;
}

/* MODAL CONTENT */
.modal-content {
    background: #161a19;
    padding: 30px;
    border-radius: 20px;
    border: 1px solid #2ecc71;
    width: 90%;
    max-width: 400px;
    text-align: center;
    position: relative;
    box-shadow: 0 0 30px rgba(46, 204, 113, 0.2);
}

.close-modal {
    position: absolute;
    top: 15px; right: 20px;
    color: #888; cursor: pointer;
    font-size: 20px;
}
</style>
</head>

<body>

<div class="orb"></div>

<div class="login-card">

<div class="brand-side">
    <img src="newlogo.png" class="company-logo" alt="Logo">
    <h1>BUDGET <span>SUPREME</span></h1>
</div>

<div class="form-side">
    <div class="form-header">
        <h2>Client Login</h2>
        <h4>SUPREME BUDGET TRACKER</h4>
    </div>

    <form action="login_logic.php" method="POST">
    <div class="input-group">
        <label>Username</label>
        <input type="text" name="username" required>
    </div>

    <div class="input-group">
        <label>Password</label>
        <input type="password" name="password" id="pass" required>
        <span class="toggle" onclick="togglePass('pass')">👁️</span>
    </div>

    <button class="btn-login" id="loginBtn" type="submit" name="login">Execute Login</button>
    <button type="reset" class="btn-reset">Clear Protocol</button>

    <div style="text-align: center; margin-top: 15px;">
        <a href="javascript:void(0)" onclick="openModal()" style="color: #aaa; text-decoration: none; font-size: 11px; transition: 0.3s;" onmouseover="this.style.color='#2ecc71'" onmouseout="this.style.color='#aaa'">
            Unable to access account? <span style="font-weight: 800; color: var(--green-glow);">RECOVER PASSWORD</span>
        </a>
    </div>

    <p style="text-align: center; margin-top: 20px; font-size: 12px; color: #aaa;">
        Don't have an account?
        <a href="client_register.php" style="color: var(--green-glow); text-decoration: none; font-weight: 800; letter-spacing: 1px;">SIGN UP</a>
    </p>

</form>
</div>

</div>

<!-- RECOVERY MODAL -->
<div class="modal-overlay" id="recoveryModal" <?php echo (isset($_SESSION['step']) && !isset($_GET['new'])) ? 'style="display:flex;"' : 'style="display:none;"'; ?>>
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <div style="margin-top: 10px;">
            <?php include('forgot_content.php'); ?>
        </div>
     <a href="index.php?new=1" style="display: block; margin-top: 15px; font-size: 10px; color: #555; text-decoration: none;">Cancel & Restart</a>
    </div>
</div>

<script>
function togglePass(id){
    const input = document.getElementById(id);
    if(input){
        input.type = input.type === "password" ? "text" : "password";
    }
}

function openModal() {
    document.getElementById('recoveryModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('recoveryModal').style.display = 'none';
}
</script>

</body>
</html>
