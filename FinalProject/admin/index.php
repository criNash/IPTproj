
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
    -webkit-background-clip: text; /* for WebKit browsers */
    -webkit-text-fill-color: transparent; /* makes the gradient visible */
    background-clip: text; /* optional fallback, may not work in all browsers */
    color: transparent; /* fallback for non-supporting browsers */
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
        <h2>Admin Login</h2>
        <h4>SUPREME BUDGET TRACKER</h4>
    </div>

    <form method="POST" action="login_process.php">
        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" id="password" required>
            <span class="toggle" onclick="togglePass('pass')">👁️</span>
        </div>

        <div class="input-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
            <span class="toggle" onclick="togglePass('confirm')">👁️</span>
        </div>

        <button class="btn-login" name="login" id="login" type="submit">Execute Login</button>
        <button type="reset" class="btn-reset">Clear Protocol</button>
    </form>
</div>

</div>

<script>
function togglePass(id){
    const input = document.getElementById(id);
    if(input){
        input.type = input.type === "password" ? "text" : "password";
    }
}

function fakeLogin(e){
    e.preventDefault();
    const btn = document.getElementById("loginBtn");
    if(!btn) return;

    btn.innerText = "Authenticating...";
    btn.disabled = true;

    setTimeout(()=>{
        btn.innerText = "Access Granted ✓";
        btn.style.background = "#2ecc71";
    },1500);
}
</script>

</body>
</html>
