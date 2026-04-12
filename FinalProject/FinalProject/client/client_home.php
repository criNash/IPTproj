<?php
session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id   = $_SESSION['client_id'];
$username    = $_SESSION['client_user'];
$user_query  = mysqli_query($conn,"SELECT first_name, last_name FROM clients WHERE id='$client_id'");
$user_data   = mysqli_fetch_assoc($user_query);
$full_name   = trim(($user_data['first_name']??'').' '.($user_data['last_name']??''));
$active_nav  = 'home';

$icons = ['transpo'=>'🚌','bills'=>'💡','food'=>'🍜','shopping'=>'🛍️','basic_needs'=>'🏠',
          'salary'=>'💰','freelance'=>'💻','business'=>'🏢','others'=>'📦'];

// ── STATS ──
$net_res   = mysqli_query($conn,"SELECT COALESCE(SUM(balance),0) AS nw FROM accounts WHERE client_id='$client_id'");
$net_worth = mysqli_fetch_assoc($net_res)['nw'];

$inc_res   = mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions
    WHERE client_id='$client_id' AND type='income'
    AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())");
$month_inc = mysqli_fetch_assoc($inc_res)['t'];

$exp_res   = mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions
    WHERE client_id='$client_id' AND type='expense'
    AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())");
$month_exp = mysqli_fetch_assoc($exp_res)['t'];

$month_bal = $month_inc - $month_exp;

// ── RECENT ──
$recent_res = mysqli_query($conn,"SELECT * FROM transactions WHERE client_id='$client_id'
    ORDER BY transaction_date DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<?php include 'base_styles.php'; ?>
<style>
.hero-greeting { margin-bottom:30px; }
.hero-greeting h1 { font-size:2rem; font-weight:800; }
.hero-greeting p  { color:rgba(255,255,255,.4); font-size:13px; margin-top:4px; }
.balance-hero { background:linear-gradient(135deg,rgba(31,157,99,.25),rgba(46,204,113,.08));
    border:1px solid rgba(46,204,113,.25); border-radius:24px; padding:32px;
    margin-bottom:24px; text-align:center; }
.balance-hero .lbl { font-size:11px; text-transform:uppercase; letter-spacing:2px; color:var(--green-glow); margin-bottom:8px; }
.balance-hero .val { font-size:3rem; font-weight:800; }
.inc-exp-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:16px; }
.inc-exp-card { border-radius:14px; padding:16px; text-align:center; }
.inc-exp-card.inc { background:rgba(46,204,113,.08); border:1px solid rgba(46,204,113,.15); }
.inc-exp-card.exp { background:rgba(255,107,107,.06); border:1px solid rgba(255,107,107,.15); }
.inc-exp-card .lbl { font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:.6; margin-bottom:4px; }
.inc-exp-card .val { font-size:1.25rem; font-weight:800; }
.actions-row { display:flex; gap:10px; margin-bottom:28px; flex-wrap:wrap; }
</style>
</head>
<body>
<?php include 'nav_include.php'; ?>
<div class="container">
    <div class="hero-greeting">
        <h1>Hello, <?php echo htmlspecialchars($user_data['first_name'] ?: $username); ?> 👋</h1>
        <p><?php echo date('l, F j, Y'); ?></p>
    </div>

    <!-- Net Worth Hero -->
    <div class="balance-hero">
        <div class="lbl">Total Net Worth</div>
        <div class="val">₱<?php echo number_format($net_worth,2); ?></div>
        <div class="inc-exp-row">
            <div class="inc-exp-card inc">
                <div class="lbl">Income this month</div>
                <div class="val" style="color:var(--green-glow);">₱<?php echo number_format($month_inc,2); ?></div>
            </div>
            <div class="inc-exp-card exp">
                <div class="lbl">Expenses this month</div>
                <div class="val" style="color:var(--red);">₱<?php echo number_format($month_exp,2); ?></div>
            </div>
        </div>
    </div>

    <div class="actions-row">
        <a href="client_transactions.php" class="btn-primary">＋ Add Transaction</a>
        <a href="client_account.php"      class="btn-outline">💳 Accounts</a>
        <a href="client_budget.php"       class="btn-outline">🎯 Budget</a>
    </div>

    <!-- Recent Transactions -->
    <div class="card-section">
        <h3>🕑 Recent Transactions
            <a href="client_transactions.php" style="margin-left:auto;font-size:11px;color:var(--green-glow);text-decoration:none;font-weight:600;">See all →</a>
        </h3>
        <?php if(mysqli_num_rows($recent_res) > 0):
            while($tx = mysqli_fetch_assoc($recent_res)):
                $cat   = strtolower($tx['category']);
                $icon  = $icons[$cat] ?? '📦';
                $lbl   = ucwords(str_replace('_',' ',$tx['category']));
                $dt    = date('M j · g:i A', strtotime($tx['transaction_date']));
                $itype = $tx['type'];
                $sign  = $itype === 'income' ? '+' : '−';
        ?>
        <div class="transaction-item">
            <div class="trans-icon <?php echo $itype; ?>"><?php echo $icon; ?></div>
            <div class="trans-info">
                <h4><?php echo $lbl; ?><?php if($tx['note']): ?> <span style="opacity:.4;font-weight:400;">— <?php echo htmlspecialchars($tx['note']); ?></span><?php endif; ?></h4>
                <span><?php echo $dt; ?></span>
            </div>
            <div class="trans-amount <?php echo $itype; ?>"><?php echo $sign; ?>₱<?php echo number_format($tx['amount'],2); ?></div>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state"><span>📭</span>No transactions yet. Start by adding one!</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
