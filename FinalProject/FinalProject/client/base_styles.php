<?php /* base_styles.php — echo shared CSS variables and nav styles */ ?>
<style>
:root {
    --green-main: #1f9d63;
    --green-glow: #2ecc71;
    --bg-dark: #020b06;
    --card-bg: rgba(255,255,255,0.03);
    --border: rgba(46,204,113,0.12);
    --red: #ff6b6b;
    --orange: #ffb347;
    --blue: #5dade2;
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; }
body { background:var(--bg-dark); color:#fff; min-height:100vh; overflow-x:hidden; }
body::before {
    content:""; position:fixed; inset:0;
    background: radial-gradient(circle at top right, rgba(46,204,113,0.06), transparent),
                url('graph.jpg') no-repeat center/cover;
    z-index:-2; opacity:.35;
}

/* NAV */
nav { padding:18px 60px; display:flex; justify-content:space-between; align-items:center;
    background:rgba(8,18,14,.9); backdrop-filter:blur(22px);
    border-bottom:1px solid var(--border); position:sticky; top:0; z-index:200; }
.logo { font-weight:800; letter-spacing:3px; font-size:1.1rem; text-decoration:none; color:#fff; }
.logo span { color:var(--green-glow); }
.nav-links { display:flex; gap:4px; align-items:center; }
.nav-link { padding:9px 16px; border-radius:10px; border:1px solid transparent;
    color:rgba(255,255,255,.55); text-decoration:none; font-size:12px; font-weight:700;
    letter-spacing:.3px; transition:.22s; }
.nav-link:hover { color:#fff; border-color:var(--border); background:rgba(255,255,255,.04); }
.nav-link.active { color:var(--green-glow); border-color:rgba(46,204,113,.3); background:rgba(46,204,113,.08); }
.nav-right { display:flex; align-items:center; }
.user-profile { position:relative; cursor:pointer; }
.profile-trigger { display:flex; align-items:center; gap:10px; padding:6px 16px;
    background:rgba(255,255,255,.05); border-radius:50px; border:1px solid rgba(46,204,113,.2); transition:.3s; }
.profile-trigger:hover { background:rgba(46,204,113,.12); border-color:var(--green-glow); }
.avatar { width:28px; height:28px; background:var(--green-main); border-radius:50%;
    display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; color:#000; }
.client-dropdown { position:absolute; top:52px; right:0; width:200px;
    background:rgba(8,18,14,.98); backdrop-filter:blur(25px);
    border:1px solid rgba(46,204,113,.2); border-radius:18px; padding:10px;
    display:none; z-index:1000; box-shadow:0 20px 40px rgba(0,0,0,.6); }
.client-dropdown.active { display:block; animation:slideDown .25s ease-out; }
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
.dropdown-header { padding:12px 15px; border-bottom:1px solid rgba(255,255,255,.05); margin-bottom:8px; }
.dropdown-header p { font-size:10px; color:var(--green-glow); font-weight:800; text-transform:uppercase; letter-spacing:1px; }
.client-dropdown a { display:block; padding:10px 15px; color:rgba(255,255,255,.8);
    text-decoration:none; font-size:13px; border-radius:10px; transition:.22s; }
.client-dropdown a:hover { background:rgba(46,204,113,.1); color:var(--green-glow); padding-left:20px; }

/* CONTAINER */
.container { max-width:1100px; margin:0 auto; padding:36px 24px; }

/* STAT CARDS */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:28px; }
.stat-card { background:var(--card-bg); border:1px solid var(--border); padding:24px;
    border-radius:20px; backdrop-filter:blur(10px); transition:.3s; }
.stat-card:hover { border-color:rgba(46,204,113,.35); transform:translateY(-3px); }
.stat-label { font-size:10px; text-transform:uppercase; letter-spacing:2px;
    color:var(--green-glow); margin-bottom:10px; display:block; }
.stat-value { font-size:1.7rem; font-weight:800; }

/* CARD SECTION */
.card-section { background:var(--card-bg); border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,.05); }
.card-section h3 { margin-bottom:16px; font-size:14px; letter-spacing:1px;
    color:rgba(255,255,255,.85); display:flex; align-items:center; gap:8px; }
.section-divider { border:0; border-top:1px solid rgba(255,255,255,.06); margin:20px 0; }

/* TRANSACTION ITEM */
.transaction-item { display:flex; justify-content:space-between; align-items:center;
    padding:12px 0; border-bottom:1px solid rgba(255,255,255,.05); }
.transaction-item:last-child { border-bottom:none; }
.trans-icon { width:36px; height:36px; border-radius:10px; background:rgba(46,204,113,.1);
    border:1px solid rgba(46,204,113,.2); display:flex; align-items:center; justify-content:center;
    font-size:16px; flex-shrink:0; }
.trans-icon.income { background:rgba(46,204,113,.12); border-color:rgba(46,204,113,.3); }
.trans-icon.expense { background:rgba(255,107,107,.08); border-color:rgba(255,107,107,.2); }
.trans-info { flex:1; margin-left:12px; }
.trans-info h4 { font-size:13px; font-weight:600; }
.trans-info span { font-size:11px; color:rgba(255,255,255,.35); }
.trans-amount { font-weight:800; font-size:13px; }
.trans-amount.income  { color:var(--green-glow); }
.trans-amount.expense { color:var(--red); }

/* BUTTONS */
.btn-primary { padding:13px 26px; border-radius:12px; border:none; background:var(--green-main);
    color:#000; font-weight:800; font-size:13px; text-transform:uppercase; cursor:pointer;
    transition:.3s; text-decoration:none; display:inline-flex; align-items:center; gap:8px; letter-spacing:.4px; }
.btn-primary:hover { background:var(--green-glow); box-shadow:0 0 22px rgba(46,204,113,.4); }
.btn-outline { padding:12px 22px; border-radius:12px; border:1px solid rgba(46,204,113,.35);
    background:transparent; color:#fff; font-weight:700; font-size:13px; cursor:pointer;
    transition:.25s; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-outline:hover { background:rgba(46,204,113,.1); border-color:var(--green-glow); }
.btn-danger { padding:12px 22px; border-radius:12px; border:1px solid rgba(255,107,107,.3);
    background:transparent; color:var(--red); font-weight:700; font-size:13px; cursor:pointer; transition:.25s; }
.btn-danger:hover { background:rgba(255,107,107,.1); }

/* FORM ELEMENTS */
.form-group { margin-bottom:20px; }
.form-group label { display:block; font-size:10px; text-transform:uppercase; letter-spacing:1px;
    color:rgba(255,255,255,.45); margin-bottom:8px; }
.form-group input, .form-group select, .form-group textarea {
    width:100%; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1);
    color:#fff; padding:12px 16px; border-radius:12px; font-size:13px;
    outline:none; transition:.25s; font-family:inherit; }
.form-group input:focus, .form-group select:focus { border-color:var(--green-glow); background:rgba(46,204,113,.05); }
.form-group input::placeholder { color:rgba(255,255,255,.2); }
.form-group select option { background:#0d1f15; color:#fff; }
.form-row { display:flex; gap:14px; flex-wrap:wrap; }
.form-row .form-group { flex:1; min-width:160px; }

/* ALERTS */
.alert { padding:14px 18px; border-radius:12px; margin-bottom:20px; font-size:13px; font-weight:600; }
.alert.success { background:rgba(31,157,99,.15); border:1px solid rgba(46,204,113,.3); color:var(--green-glow); }
.alert.error   { background:rgba(255,100,100,.1);  border:1px solid rgba(255,100,100,.3); color:var(--red); }

/* EMPTY STATE */
.empty-state { text-align:center; color:rgba(255,255,255,.2); font-size:12px; padding:32px 0; }
.empty-state span { font-size:36px; display:block; margin-bottom:10px; }

/* PAGE HEADER */
.page-header { margin-bottom:28px; }
.page-header h1 { font-size:2rem; font-weight:800; }
.page-header p  { color:rgba(255,255,255,.4); font-size:13px; margin-top:5px; }

/* MODAL */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.72); backdrop-filter:blur(8px);
    z-index:500; display:none; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal { background:rgba(8,22,14,.97); border:1px solid rgba(46,204,113,.22);
    border-radius:24px; padding:32px; width:90%; max-width:460px;
    animation:modalIn .3s ease-out; max-height:90vh; overflow-y:auto; }
@keyframes modalIn { from{opacity:0;transform:scale(.93)} to{opacity:1;transform:scale(1)} }
.modal h3 { margin-bottom:20px; font-size:17px; }
.modal-close { float:right; background:none; border:none; color:rgba(255,255,255,.4);
    font-size:20px; cursor:pointer; line-height:1; }
.modal-close:hover { color:#fff; }

/* BACK LINK */
.back-link { display:inline-flex; align-items:center; gap:6px; color:rgba(255,255,255,.35);
    text-decoration:none; font-size:13px; margin-bottom:22px; transition:.2s; }
.back-link:hover { color:var(--green-glow); }

@media(max-width:900px){
    nav { padding:16px 20px; }
    .nav-links { display:none; }
    .container { padding:24px 16px; }
}
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
</style>
