<?php
session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id  = $_SESSION['client_id'];
$username   = $_SESSION['client_user'];
$user_query = mysqli_query($conn,"SELECT first_name, last_name FROM clients WHERE id='$client_id'");
$user_data  = mysqli_fetch_assoc($user_query);
$active_nav = 'account';
$success = ''; $error = '';

// ── HANDLE: Add account ──
if(isset($_POST['add_account'])){
    $name    = mysqli_real_escape_string($conn, trim($_POST['acc_name']));
    $icon    = mysqli_real_escape_string($conn, trim($_POST['acc_icon'] ?? '💳'));
    $balance = floatval($_POST['acc_balance']);
    if($name){
        mysqli_query($conn,"INSERT INTO accounts (client_id,account_name,icon,balance)
            VALUES ('$client_id','$name','$icon','$balance')");
        $success = "Account '$name' created!";
    }
}
// ── HANDLE: Edit account ──
if(isset($_POST['edit_account'])){
    $aid     = intval($_POST['acc_id']);
    $name    = mysqli_real_escape_string($conn, trim($_POST['acc_name']));
    $icon    = mysqli_real_escape_string($conn, trim($_POST['acc_icon'] ?? '💳'));
    $balance = floatval($_POST['acc_balance']);
    mysqli_query($conn,"UPDATE accounts SET account_name='$name',icon='$icon',balance='$balance'
        WHERE id='$aid' AND client_id='$client_id'");
    $success = "Account updated!";
}
// ── HANDLE: Delete account ──
if(isset($_GET['delete_acc'])){
    $aid = intval($_GET['delete_acc']);
    mysqli_query($conn,"DELETE FROM accounts WHERE id='$aid' AND client_id='$client_id'");
    header("Location: client_account.php"); exit();
}

// ── FETCH accounts ──
$acc_res   = mysqli_query($conn,"SELECT * FROM accounts WHERE client_id='$client_id' ORDER BY created_at ASC");
$accounts  = [];
while($row = mysqli_fetch_assoc($acc_res)) $accounts[] = $row;
$net_worth = array_sum(array_column($accounts,'balance'));

// ── Per-account transaction summary ──
foreach($accounts as &$acc){
    $aid = $acc['id'];
    $r = mysqli_query($conn,"SELECT
        COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS total_in,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS total_out
        FROM transactions WHERE account_id='$aid'");
    $s = mysqli_fetch_assoc($r);
    $acc['total_in']  = $s['total_in'];
    $acc['total_out'] = $s['total_out'];
}
unset($acc);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<?php include 'base_styles.php'; ?>
<style>
.net-worth-banner { background:linear-gradient(135deg,rgba(31,157,99,.22),rgba(46,204,113,.07));
    border:1px solid rgba(46,204,113,.25); border-radius:22px; padding:28px 32px;
    margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.net-worth-banner .lbl { font-size:11px; text-transform:uppercase; letter-spacing:2px; color:var(--green-glow); margin-bottom:6px; }
.net-worth-banner .val { font-size:2.4rem; font-weight:800; }
.accounts-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; margin-bottom:28px; }
.account-card { background:var(--card-bg); border:1px solid var(--border); border-radius:20px; padding:24px; transition:.28s; position:relative; }
.account-card:hover { border-color:rgba(46,204,113,.3); transform:translateY(-3px); }
.account-card .acc-icon { font-size:32px; margin-bottom:12px; }
.account-card .acc-name { font-size:16px; font-weight:800; margin-bottom:4px; }
.account-card .acc-balance { font-size:1.6rem; font-weight:800; color:var(--green-glow); margin-bottom:14px; }
.acc-stats { display:flex; gap:16px; }
.acc-stat { font-size:11px; }
.acc-stat .lbl { color:rgba(255,255,255,.4); margin-bottom:2px; }
.acc-stat .val { font-weight:700; }
.acc-actions { display:flex; gap:8px; margin-top:16px; }
.acc-btn { padding:8px 16px; border-radius:10px; font-size:11px; font-weight:700; cursor:pointer; border:none; transition:.22s; text-transform:uppercase; letter-spacing:.4px; }
.acc-btn.edit { background:rgba(46,204,113,.1); border:1px solid rgba(46,204,113,.2); color:var(--green-glow); }
.acc-btn.edit:hover { background:rgba(46,204,113,.2); }
.acc-btn.del  { background:rgba(255,107,107,.07); border:1px solid rgba(255,107,107,.2); color:var(--red); }
.acc-btn.del:hover { background:rgba(255,107,107,.15); }
.add-account-card { border-style:dashed; display:flex; flex-direction:column; align-items:center;
    justify-content:center; gap:8px; cursor:pointer; min-height:180px;
    color:rgba(255,255,255,.25); transition:.25s; }
.add-account-card:hover { border-color:rgba(46,204,113,.4); color:var(--green-glow); }
/* emoji grid */
.emoji-row { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:14px; }
.emoji-opt { width:36px; height:36px; border-radius:9px; border:1px solid rgba(255,255,255,.1);
    background:transparent; cursor:pointer; font-size:17px; transition:.2s;
    display:flex; align-items:center; justify-content:center; }
.emoji-opt:hover, .emoji-opt.sel { border-color:var(--green-glow); background:rgba(46,204,113,.1); }
</style>
</head>
<body>
<?php include 'nav_include.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>💳 Accounts</h1>
        <p>Manage your wallets and track your net worth.</p>
    </div>

    <?php if($success): ?><div class="alert success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if($error):   ?><div class="alert error">❌ <?php echo $error; ?></div><?php endif; ?>

    <!-- NET WORTH BANNER -->
    <div class="net-worth-banner">
        <div>
            <div class="lbl">Total Net Worth</div>
            <div class="val">₱<?php echo number_format($net_worth,2); ?></div>
        </div>
        <div style="font-size:12px;color:rgba(255,255,255,.35);">
            <?php echo count($accounts); ?> account<?php echo count($accounts)!==1?'s':''; ?>
        </div>
    </div>

    <!-- ACCOUNTS GRID -->
    <div class="accounts-grid">
        <?php foreach($accounts as $acc): ?>
        <div class="account-card">
            <div class="acc-icon"><?php echo $acc['icon']; ?></div>
            <div class="acc-name"><?php echo htmlspecialchars($acc['account_name']); ?></div>
            <div class="acc-balance">₱<?php echo number_format($acc['balance'],2); ?></div>
            <div class="acc-stats">
                <div class="acc-stat">
                    <div class="lbl">Total In</div>
                    <div class="val" style="color:var(--green-glow);">₱<?php echo number_format($acc['total_in'],2); ?></div>
                </div>
                <div class="acc-stat">
                    <div class="lbl">Total Out</div>
                    <div class="val" style="color:var(--red);">₱<?php echo number_format($acc['total_out'],2); ?></div>
                </div>
            </div>
            <div class="acc-actions">
                <button class="acc-btn edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($acc)); ?>)">✏️ Edit</button>
                <button class="acc-btn del"  onclick="if(confirm('Delete this account?')) window.location='client_account.php?delete_acc=<?php echo $acc['id']; ?>'">🗑️ Delete</button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ADD CARD -->
        <div class="account-card add-account-card" onclick="openAddModal()">
            <span style="font-size:32px;">＋</span>
            <span style="font-size:13px;font-weight:700;letter-spacing:.5px;">Add Account</span>
        </div>
    </div>
</div>

<!-- ADD ACCOUNT MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <button class="modal-close" onclick="closeAddModal()">✕</button>
        <h3>➕ New Account</h3>
        <form method="POST">
            <input type="hidden" name="add_account" value="1">
            <div style="margin-bottom:14px;">
                <label style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);display:block;margin-bottom:8px;">Icon</label>
                <div class="emoji-row" id="addEmojiRow">
                    <?php foreach(['💳','💵','🏦','📱','💰','🪙','💼','🏧','💸','🎁'] as $e): ?>
                    <button type="button" class="emoji-opt" onclick="pickAddEmoji('<?php echo $e; ?>')"><?php echo $e; ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="acc_icon" id="addIcon" value="💳">
            </div>
            <div class="form-group">
                <label>Account Name</label>
                <input type="text" name="acc_name" placeholder="e.g. Cash, GCash, BDO" required>
            </div>
            <div class="form-group">
                <label>Current Balance (₱)</label>
                <input type="number" name="acc_balance" value="0" step="0.01">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">Create Account</button>
        </form>
    </div>
</div>

<!-- EDIT ACCOUNT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <button class="modal-close" onclick="closeEditModal()">✕</button>
        <h3>✏️ Edit Account</h3>
        <form method="POST">
            <input type="hidden" name="edit_account" value="1">
            <input type="hidden" name="acc_id" id="editAccId">
            <div style="margin-bottom:14px;">
                <label style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);display:block;margin-bottom:8px;">Icon</label>
                <div class="emoji-row" id="editEmojiRow">
                    <?php foreach(['💳','💵','🏦','📱','💰','🪙','💼','🏧','💸','🎁'] as $e): ?>
                    <button type="button" class="emoji-opt" onclick="pickEditEmoji('<?php echo $e; ?>')"><?php echo $e; ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="acc_icon" id="editIcon" value="💳">
            </div>
            <div class="form-group">
                <label>Account Name</label>
                <input type="text" name="acc_name" id="editAccName" required>
            </div>
            <div class="form-group">
                <label>Balance (₱)</label>
                <input type="number" name="acc_balance" id="editAccBalance" step="0.01">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

<script>
function openAddModal(){ document.getElementById('addModal').classList.add('active'); }
function closeAddModal(){ document.getElementById('addModal').classList.remove('active'); }
function openEditModal(acc){
    document.getElementById('editModal').classList.add('active');
    document.getElementById('editAccId').value      = acc.id;
    document.getElementById('editAccName').value    = acc.account_name;
    document.getElementById('editAccBalance').value = acc.balance;
    document.getElementById('editIcon').value       = acc.icon;
    document.querySelectorAll('#editEmojiRow .emoji-opt').forEach(b => {
        b.classList.toggle('sel', b.textContent === acc.icon);
    });
}
function closeEditModal(){ document.getElementById('editModal').classList.remove('active'); }

function pickAddEmoji(e){
    document.getElementById('addIcon').value = e;
    document.querySelectorAll('#addEmojiRow .emoji-opt').forEach(b => b.classList.toggle('sel', b.textContent===e));
}
function pickEditEmoji(e){
    document.getElementById('editIcon').value = e;
    document.querySelectorAll('#editEmojiRow .emoji-opt').forEach(b => b.classList.toggle('sel', b.textContent===e));
}

document.getElementById('addModal').addEventListener('click',  function(e){ if(e.target===this) closeAddModal(); });
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) closeEditModal(); });
</script>
</body>
</html>
