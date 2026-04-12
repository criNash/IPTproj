<?php
session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id  = $_SESSION['client_id'];
$username   = $_SESSION['client_user'];
$user_query = mysqli_query($conn,"SELECT * FROM clients WHERE id='$client_id'");
$user_data  = mysqli_fetch_assoc($user_query);
$active_nav = 'settings';
$success = ''; $error = '';

// ── UPDATE PROFILE ──
if(isset($_POST['update_profile'])){
    $fname   = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $mname   = mysqli_real_escape_string($conn, trim($_POST['middle_name']));
    $lname   = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $contact = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
    $check   = mysqli_query($conn,"SELECT id FROM clients WHERE email='$email' AND id!='$client_id'");
    if(mysqli_num_rows($check) > 0){ $error = "Email already in use by another account."; }
    else {
        mysqli_query($conn,"UPDATE clients SET first_name='$fname',middle_name='$mname',last_name='$lname',
            email='$email',contact_number='$contact' WHERE id='$client_id'");
        $success = "Profile updated successfully!";
        $user_query = mysqli_query($conn,"SELECT * FROM clients WHERE id='$client_id'");
        $user_data  = mysqli_fetch_assoc($user_query);
    }
}

// ── CHANGE PASSWORD ──
if(isset($_POST['change_password'])){
    $cur  = $_POST['current_password'];
    $new  = $_POST['new_password'];
    $conf = $_POST['confirm_password'];
    if(!password_verify($cur, $user_data['password']))      { $error = "Current password is incorrect."; }
    elseif(strlen($new) < 6)                                { $error = "New password must be at least 6 characters."; }
    elseif($new !== $conf)                                  { $error = "New passwords do not match."; }
    else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        mysqli_query($conn,"UPDATE clients SET password='$hashed' WHERE id='$client_id'");
        $success = "Password updated successfully!";
    }
}

// ── MANAGE CUSTOM CATEGORIES ──
if(isset($_POST['add_category'])){
    $cat_name = mysqli_real_escape_string($conn, trim($_POST['cat_name']));
    $cat_icon = mysqli_real_escape_string($conn, trim($_POST['cat_icon'] ?? '📦'));
    $cat_type = $_POST['cat_type'] === 'income' ? 'income' : 'expense';
    if($cat_name){
        $chk = mysqli_query($conn,"SELECT id FROM client_categories
            WHERE client_id='$client_id' AND LOWER(category_name)=LOWER('$cat_name') AND type='$cat_type'");
        if(mysqli_num_rows($chk)==0)
            mysqli_query($conn,"INSERT INTO client_categories (client_id,category_name,icon,type)
                VALUES ('$client_id','$cat_name','$cat_icon','$cat_type')");
        $success = "Category added!";
    }
}
if(isset($_GET['delete_cat'])){
    $cid = intval($_GET['delete_cat']);
    mysqli_query($conn,"DELETE FROM client_categories WHERE id='$cid' AND client_id='$client_id'");
    header("Location: client_settings.php#categories"); exit();
}

// ── FETCH custom categories ──
$exp_cats_res = mysqli_query($conn,"SELECT * FROM client_categories WHERE client_id='$client_id' AND type='expense' ORDER BY created_at ASC");
$inc_cats_res = mysqli_query($conn,"SELECT * FROM client_categories WHERE client_id='$client_id' AND type='income'  ORDER BY created_at ASC");
$exp_cats = []; while($r = mysqli_fetch_assoc($exp_cats_res)) $exp_cats[] = $r;
$inc_cats = []; while($r = mysqli_fetch_assoc($inc_cats_res)) $inc_cats[] = $r;

$full_name    = trim($user_data['first_name'].' '.$user_data['last_name']);
$first_letter = $user_data['first_name'] ? strtoupper(substr($user_data['first_name'],0,1)) : 'U';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<?php include 'base_styles.php'; ?>
<style>
.settings-layout { display:grid; grid-template-columns:200px 1fr; gap:24px; align-items:start; }
.settings-nav { background:var(--card-bg); border:1px solid var(--border); border-radius:18px; padding:12px; position:sticky; top:90px; }
.s-nav-link { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:12px;
    color:rgba(255,255,255,.55); text-decoration:none; font-size:13px; font-weight:600; transition:.22s; }
.s-nav-link:hover { background:rgba(255,255,255,.05); color:#fff; }
.s-nav-link.active { background:rgba(46,204,113,.1); color:var(--green-glow); }
.s-nav-link.danger { color:rgba(255,107,107,.7); }
.s-nav-link.danger:hover { background:rgba(255,107,107,.08); color:var(--red); }
.settings-section { display:none; }
.settings-section.active { display:block; animation:fadeIn .25s ease-out; }
.section-card { background:var(--card-bg); border:1px solid var(--border); border-radius:20px; padding:30px; margin-bottom:20px; }
.section-card h2 { font-size:1.2rem; font-weight:800; margin-bottom:6px; }
.section-card .sub { color:rgba(255,255,255,.35); font-size:13px; margin-bottom:24px; }
.profile-avatar-big { width:72px; height:72px; background:var(--green-main); border-radius:50%;
    display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:800;
    color:#000; margin-bottom:20px; }
.cat-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 14px;
    background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
    border-radius:30px; font-size:12px; margin:4px; }
.cat-chip .del-cat { background:none; border:none; color:rgba(255,107,107,.5);
    cursor:pointer; font-size:13px; padding:0 0 0 4px; line-height:1; transition:.2s; }
.cat-chip .del-cat:hover { color:var(--red); }
.emoji-row { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:14px; }
.emoji-opt { width:34px; height:34px; border-radius:9px; border:1px solid rgba(255,255,255,.1);
    background:transparent; cursor:pointer; font-size:16px; transition:.2s;
    display:flex; align-items:center; justify-content:center; }
.emoji-opt:hover, .emoji-opt.sel { border-color:var(--green-glow); background:rgba(46,204,113,.1); }
@media(max-width:700px){
    .settings-layout { grid-template-columns:1fr; }
    .settings-nav { position:static; display:flex; flex-wrap:wrap; gap:4px; }
}
</style>
</head>
<body>
<?php include 'nav_include.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>⚙️ Settings</h1>
    </div>

    <?php if($success): ?><div class="alert success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if($error):   ?><div class="alert error">❌ <?php echo $error; ?></div><?php endif; ?>

    <div class="settings-layout">
        <!-- SIDEBAR NAV -->
        <div class="settings-nav">
            <a href="#" class="s-nav-link active" data-section="profile"    onclick="showSection('profile',this)">👤 Profile</a>
            <a href="#" class="s-nav-link"         data-section="password"   onclick="showSection('password',this)">🔑 Password</a>
            <a href="#" class="s-nav-link"         data-section="categories" onclick="showSection('categories',this)">🏷️ Categories</a>
            <hr style="border:0;border-top:1px solid rgba(255,255,255,.06);margin:8px 0;">
            <a href="client_logout.php" class="s-nav-link danger">⏏️ Logout</a>
        </div>

        <div>
            <!-- PROFILE SECTION -->
            <div class="settings-section active" id="section-profile">
                <div class="section-card">
                    <div class="profile-avatar-big"><?php echo $first_letter; ?></div>
                    <h2>Profile Information</h2>
                    <div class="sub">Update your personal details.</div>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user_data['middle_name']??''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user_data['contact_number']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Username (cannot be changed)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled style="opacity:.4;cursor:not-allowed;">
                        </div>
                        <button type="submit" name="update_profile" class="btn-primary">💾 Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- PASSWORD SECTION -->
            <div class="settings-section" id="section-password">
                <div class="section-card">
                    <h2>Change Password</h2>
                    <div class="sub">Keep your account secure.</div>
                    <form method="POST" style="max-width:420px;">
                        <div class="form-group" style="position:relative;">
                            <label>Current Password</label>
                            <input type="password" name="current_password" id="curPw" placeholder="Enter current password" required>
                            <span style="position:absolute;right:14px;bottom:13px;cursor:pointer;" onclick="togglePw('curPw')">👁️</span>
                        </div>
                        <div class="form-group" style="position:relative;">
                            <label>New Password</label>
                            <input type="password" name="new_password" id="newPw" placeholder="At least 6 characters" required>
                            <span style="position:absolute;right:14px;bottom:13px;cursor:pointer;" onclick="togglePw('newPw')">👁️</span>
                        </div>
                        <div class="form-group" style="position:relative;">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" id="conPw" placeholder="Repeat new password" required>
                            <span style="position:absolute;right:14px;bottom:13px;cursor:pointer;" onclick="togglePw('conPw')">👁️</span>
                        </div>
                        <button type="submit" name="change_password" class="btn-primary">🔒 Update Password</button>
                    </form>
                </div>
            </div>

            <!-- CATEGORIES SECTION -->
            <div class="settings-section" id="section-categories">
                <div class="section-card">
                    <h2>🏷️ Custom Categories</h2>
                    <div class="sub">Add your own expense and income categories used across the app.</div>

                    <div style="margin-bottom:20px;">
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);margin-bottom:10px;">Expense Categories</div>
                        <?php foreach($exp_cats as $c): ?>
                        <span class="cat-chip"><?php echo $c['icon']; ?> <?php echo htmlspecialchars($c['category_name']); ?>
                            <button class="del-cat" onclick="if(confirm('Remove?')) window.location='client_settings.php?delete_cat=<?php echo $c['id']; ?>'">✕</button>
                        </span>
                        <?php endforeach; ?>
                        <?php if(empty($exp_cats)): ?><div style="font-size:12px;color:rgba(255,255,255,.2);">No custom expense categories yet.</div><?php endif; ?>
                    </div>

                    <div style="margin-bottom:24px;">
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);margin-bottom:10px;">Income Categories</div>
                        <?php foreach($inc_cats as $c): ?>
                        <span class="cat-chip"><?php echo $c['icon']; ?> <?php echo htmlspecialchars($c['category_name']); ?>
                            <button class="del-cat" onclick="if(confirm('Remove?')) window.location='client_settings.php?delete_cat=<?php echo $c['id']; ?>'">✕</button>
                        </span>
                        <?php endforeach; ?>
                        <?php if(empty($inc_cats)): ?><div style="font-size:12px;color:rgba(255,255,255,.2);">No custom income categories yet.</div><?php endif; ?>
                    </div>

                    <hr class="section-divider">
                    <div style="font-size:13px;font-weight:700;margin-bottom:14px;">Add New Category</div>
                    <form method="POST">
                        <input type="hidden" name="add_category" value="1">
                        <input type="hidden" name="cat_icon" id="newCatIcon" value="📦">
                        <div class="emoji-row" id="catEmojiRow">
                            <?php foreach(['📦','🎮','🚗','✈️','🏋️','📚','🎵','💊','🐾','🎨','💻','🔧','🧴','☕','🍺','💍','👗','🎁','🌱','⚽','🎬','🏖️','🔋','🧹'] as $e): ?>
                            <button type="button" class="emoji-opt" onclick="pickCatEmoji('<?php echo $e; ?>')"><?php echo $e; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Category Name</label>
                                <input type="text" name="cat_name" placeholder="e.g. Gym, Subscriptions..." required>
                            </div>
                            <div class="form-group">
                                <label>Type</label>
                                <select name="cat_type">
                                    <option value="expense">💸 Expense</option>
                                    <option value="income">💰 Income</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">＋ Add Category</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showSection(id, el){
    event.preventDefault();
    document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.s-nav-link').forEach(a => a.classList.remove('active'));
    document.getElementById('section-'+id).classList.add('active');
    el.classList.add('active');
}
function togglePw(id){
    const el = document.getElementById(id);
    el.type = el.type==='password' ? 'text' : 'password';
}
function pickCatEmoji(e){
    document.getElementById('newCatIcon').value = e;
    document.querySelectorAll('#catEmojiRow .emoji-opt').forEach(b => b.classList.toggle('sel', b.textContent===e));
}
// Restore section from hash
const hash = window.location.hash.replace('#','');
if(hash){
    const link = document.querySelector(`.s-nav-link[data-section="${hash}"]`);
    if(link) showSection(hash, link);
}
</script>
</body>
</html>
