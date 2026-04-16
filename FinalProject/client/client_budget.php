<?php
session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id  = $_SESSION['client_id'];
$username   = $_SESSION['client_user'];
$user_query = mysqli_query($conn,"SELECT first_name, last_name FROM clients WHERE id='$client_id'");
$user_data  = mysqli_fetch_assoc($user_query);
$active_nav = 'budget';
$success = ''; $error = '';

// ── HANDLE: Save/Update budget ──
if(isset($_POST['save_budget'])){
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $period   = in_array($_POST['period'],['daily','weekly','monthly','yearly']) ? $_POST['period'] : 'monthly';
    $limit    = floatval($_POST['amount_limit']);
    if($category && $limit > 0){
        // Upsert
        mysqli_query($conn,"INSERT INTO budgets (client_id,category,period,amount_limit)
            VALUES ('$client_id','$category','$period','$limit')
            ON DUPLICATE KEY UPDATE amount_limit='$limit', period='$period'");
        $success = "Budget saved!";
    }
}
// ── HANDLE: Delete budget ──
if(isset($_GET['delete_budget'])){
    $bid = intval($_GET['delete_budget']);
    mysqli_query($conn,"DELETE FROM budgets WHERE id='$bid' AND client_id='$client_id'");
    header("Location: client_budget.php"); exit();
}

// ── FETCH: Custom expense categories ──
$cc_res = mysqli_query($conn,"SELECT * FROM client_categories WHERE client_id='$client_id' AND type='expense'");
$custom_cats = [];
while($r = mysqli_fetch_assoc($cc_res)) $custom_cats[] = $r;

// ── FETCH: All budgets with current spending ──
$period_filter = in_array($_GET['period'] ?? '', ['daily','weekly','monthly','yearly'])
    ? $_GET['period'] : 'monthly';

$budget_res = mysqli_query($conn,"SELECT * FROM budgets WHERE client_id='$client_id' AND period='$period_filter' ORDER BY category ASC");
$budgets = [];

// Date ranges per period
$now = date('Y-m-d H:i:s');
$date_conditions = [
    'daily'   => "DATE(transaction_date)=CURDATE()",
    'weekly'  => "DATE(transaction_date) BETWEEN '".date('Y-m-d',strtotime('monday this week'))."' AND '".date('Y-m-d',strtotime('sunday this week'))."'",
    'monthly' => "MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())",
    'yearly'  => "YEAR(transaction_date)=YEAR(NOW())",
];
$cond = $date_conditions[$period_filter];

while($b = mysqli_fetch_assoc($budget_res)){
    $cat  = mysqli_real_escape_string($conn, $b['category']);
    $sres = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS spent
        FROM transactions WHERE client_id='$client_id' AND type='expense'
        AND LOWER(category)=LOWER('$cat') AND $cond"));
    $b['spent']   = (float)$sres['spent'];
    $b['pct']     = $b['amount_limit'] > 0 ? min(100, round(($b['spent'] / $b['amount_limit']) * 100)) : 0;
    $b['remaining'] = max(0, $b['amount_limit'] - $b['spent']);
    $budgets[] = $b;
}

// ── OVERALL SPEND this period ──
$overall_res   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t
    FROM transactions WHERE client_id='$client_id' AND type='expense' AND $cond"));
$overall_spent = (float)$overall_res['t'];
$total_limit   = array_sum(array_column($budgets,'amount_limit'));

$icons = ['transpo'=>'🚌','bills'=>'💡','food'=>'🍜','shopping'=>'🛍️','basic_needs'=>'🏠','others'=>'📦'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Budget | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<?php include 'base_styles.php'; ?>
<style>
.period-tabs { display:flex; gap:6px; margin-bottom:28px; background:rgba(255,255,255,.03);
    border:1px solid var(--border); border-radius:14px; padding:5px; width:fit-content; }
.period-tab { padding:9px 22px; border-radius:10px; border:none; background:transparent;
    color:rgba(255,255,255,.45); font-size:12px; font-weight:700; cursor:pointer;
    text-transform:uppercase; letter-spacing:.5px; transition:.22s; text-decoration:none; }
.period-tab:hover { color:#fff; background:rgba(255,255,255,.05); }
.period-tab.active { background:var(--green-main); color:#000; }

.budget-summary { background:linear-gradient(135deg,rgba(31,157,99,.18),rgba(46,204,113,.06));
    border:1px solid rgba(46,204,113,.2); border-radius:20px; padding:24px; margin-bottom:24px; }
.overall-bar-wrap { height:8px; background:rgba(255,255,255,.08); border-radius:6px; margin:12px 0 8px; }
.overall-bar { height:8px; border-radius:6px; background:var(--green-main); transition:width .5s ease; }
.overall-bar.warn  { background:var(--orange); }
.overall-bar.over  { background:var(--red); }

.budget-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; margin-bottom:28px; }
.budget-card { background:var(--card-bg); border:1px solid var(--border); border-radius:18px; padding:22px; transition:.28s; }
.budget-card:hover { border-color:rgba(46,204,113,.25); }
.budget-card.warn { border-color:rgba(255,179,71,.3); }
.budget-card.over { border-color:rgba(255,107,107,.35); background:rgba(255,107,107,.03); }
.bc-top { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.bc-icon { font-size:26px; }
.bc-name { font-size:14px; font-weight:800; }
.bc-period { font-size:10px; text-transform:uppercase; letter-spacing:1px;
    color:var(--green-glow); background:rgba(46,204,113,.1); border-radius:20px; padding:2px 10px; margin-left:auto; }
.bc-bar-wrap { height:6px; background:rgba(255,255,255,.07); border-radius:5px; margin-bottom:10px; }
.bc-bar { height:6px; border-radius:5px; background:var(--green-main); transition:width .5s ease; }
.bc-bar.warn { background:var(--orange); }
.bc-bar.over { background:var(--red); }
.bc-amounts { display:flex; justify-content:space-between; font-size:12px; }
.bc-spent { font-weight:700; }
.bc-limit { color:rgba(255,255,255,.4); }
.bc-pct { font-size:11px; font-weight:800; margin-bottom:8px; }
.bc-actions { display:flex; gap:8px; margin-top:12px; }
.bc-btn { padding:6px 14px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; border:none; transition:.2s; }
.bc-btn.edit { background:rgba(46,204,113,.1); border:1px solid rgba(46,204,113,.2); color:var(--green-glow); }
.bc-btn.del  { background:rgba(255,107,107,.07); border:1px solid rgba(255,107,107,.2); color:var(--red); }

.add-budget-card { border-style:dashed; display:flex; flex-direction:column;
    align-items:center; justify-content:center; gap:8px; cursor:pointer;
    min-height:160px; color:rgba(255,255,255,.22); transition:.25s; }
.add-budget-card:hover { border-color:rgba(46,204,113,.4); color:var(--green-glow); }
</style>
</head>
<body>
<?php include 'nav_include.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>🎯 Budget</h1>
        <p>Set spending limits and track your progress per category.</p>
    </div>

    <?php if($success): ?><div class="alert success">✅ <?php echo $success; ?></div><?php endif; ?>

    <!-- PERIOD TABS -->
    <div class="period-tabs">
        <?php foreach(['daily','weekly','monthly','yearly'] as $p): ?>
        <a href="client_budget.php?period=<?php echo $p; ?>"
           class="period-tab <?php echo $period_filter===$p?'active':''; ?>">
            <?php echo ucfirst($p); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- OVERALL SUMMARY -->
    <?php if(!empty($budgets)): 
        $overall_pct = $total_limit > 0 ? min(100, round(($overall_spent/$total_limit)*100)) : 0;
        $bar_class   = $overall_pct >= 100 ? 'over' : ($overall_pct >= 75 ? 'warn' : '');
    ?>
    <div class="budget-summary">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div>
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:var(--green-glow);margin-bottom:4px;">
                    <?php echo ucfirst($period_filter); ?> Budget Overview
                </div>
                <div style="font-size:1.5rem;font-weight:800;">
                    ₱<?php echo number_format($overall_spent,2); ?>
                    <span style="font-size:1rem;font-weight:400;opacity:.5;"> / ₱<?php echo number_format($total_limit,2); ?></span>
                </div>
            </div>
            <div style="font-size:2rem;font-weight:800;color:<?php echo $bar_class==='over'?'var(--red)':($bar_class==='warn'?'var(--orange)':'var(--green-glow)'); ?>">
                <?php echo $overall_pct; ?>%
            </div>
        </div>
        <div class="overall-bar-wrap">
            <div class="overall-bar <?php echo $bar_class; ?>" style="width:<?php echo $overall_pct; ?>%;"></div>
        </div>
        <div style="font-size:11px;color:rgba(255,255,255,.4);">
            ₱<?php echo number_format($total_limit - $overall_spent, 2); ?> remaining across all budgets
        </div>
    </div>
    <?php endif; ?>

    <!-- BUDGET CARDS -->
    <div class="budget-grid">
        <?php foreach($budgets as $b):
            $cat      = strtolower($b['category']);
            $icon     = $icons[$cat] ?? '📦';
            $label    = ucwords(str_replace('_',' ',$b['category']));
            $pct      = $b['pct'];
            $bc_class = $pct >= 100 ? 'over' : ($pct >= 75 ? 'warn' : '');
        ?>
        <div class="budget-card <?php echo $bc_class; ?>">
            <div class="bc-top">
                <div class="bc-icon"><?php echo $icon; ?></div>
                <div class="bc-name"><?php echo $label; ?></div>
                <div class="bc-period"><?php echo ucfirst($b['period']); ?></div>
            </div>
            <div class="bc-pct" style="color:<?php echo $bc_class==='over'?'var(--red)':($bc_class==='warn'?'var(--orange)':'var(--green-glow)'); ?>">
                <?php echo $pct; ?>% used
                <?php if($pct >= 100): ?> ⚠️ Over budget<?php elseif($pct >= 75): ?> — nearing limit<?php endif; ?>
            </div>
            <div class="bc-bar-wrap">
                <div class="bc-bar <?php echo $bc_class; ?>" style="width:<?php echo $pct; ?>%;"></div>
            </div>
            <div class="bc-amounts">
                <div class="bc-spent" style="color:<?php echo $bc_class==='over'?'var(--red)':'#fff'; ?>">
                    Spent: ₱<?php echo number_format($b['spent'],2); ?>
                </div>
                <div class="bc-limit">Limit: ₱<?php echo number_format($b['amount_limit'],2); ?></div>
            </div>
            <div style="font-size:11px;color:rgba(255,255,255,.3);margin-top:4px;">
                ₱<?php echo number_format($b['remaining'],2); ?> left
            </div>
            <div class="bc-actions">
                <button class="bc-btn edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($b)); ?>)">✏️ Edit</button>
                <button class="bc-btn del"  onclick="if(confirm('Remove this budget?')) window.location='client_budget.php?delete_budget=<?php echo $b['id']; ?>&period=<?php echo $period_filter; ?>'">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ADD BUDGET CARD -->
        <div class="budget-card add-budget-card" onclick="openAddModal()">
            <span style="font-size:30px;">＋</span>
            <span style="font-size:13px;font-weight:700;letter-spacing:.5px;">Set Budget</span>
        </div>
    </div>

    <?php if(empty($budgets)): ?>
    <div class="card-section" style="text-align:center;padding:40px;">
        <div style="font-size:40px;margin-bottom:12px;">🎯</div>
        <p style="font-size:14px;font-weight:600;margin-bottom:6px;">No budgets set for <?php echo ucfirst($period_filter); ?></p>
        <p style="font-size:12px;color:rgba(255,255,255,.3);margin-bottom:20px;">Create a budget to start tracking your spending limits.</p>
        <button class="btn-primary" onclick="openAddModal()">＋ Set First Budget</button>
    </div>
    <?php endif; ?>
</div>

<!-- ADD BUDGET MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <button class="modal-close" onclick="closeAddModal()">✕</button>
        <h3>🎯 Set Budget</h3>
        <form method="POST">
            <input type="hidden" name="save_budget" value="1">
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="">— Select category —</option>
                    <optgroup label="Fixed">
                        <option value="food">🍜 Food</option>
                        <option value="transpo">🚌 Transportation</option>
                        <option value="bills">💡 Bills</option>
                        <option value="shopping">🛍️ Shopping</option>
                        <option value="basic_needs">🏠 Basic Needs</option>
                        <option value="others">📦 Others</option>
                    </optgroup>
                    <?php if(!empty($custom_cats)): ?>
                    <optgroup label="My Categories">
                        <?php foreach($custom_cats as $cc): ?>
                        <option value="<?php echo htmlspecialchars($cc['category_name']); ?>">
                            <?php echo $cc['icon'].' '.htmlspecialchars($cc['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Period</label>
                <select name="period">
                    <option value="daily"   <?php echo $period_filter==='daily'  ?'selected':''; ?>>Daily</option>
                    <option value="weekly"  <?php echo $period_filter==='weekly' ?'selected':''; ?>>Weekly</option>
                    <option value="monthly" <?php echo $period_filter==='monthly'?'selected':''; ?>>Monthly</option>
                    <option value="yearly"  <?php echo $period_filter==='yearly' ?'selected':''; ?>>Yearly</option>
                </select>
            </div>
            <div class="form-group">
                <label>Budget Limit (₱)</label>
                <input type="number" name="amount_limit" placeholder="e.g. 5000" step="0.01" min="1" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">💾 Save Budget</button>
        </form>
    </div>
</div>

<!-- EDIT BUDGET MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <button class="modal-close" onclick="closeEditModal()">✕</button>
        <h3>✏️ Edit Budget</h3>
        <form method="POST">
            <input type="hidden" name="save_budget" value="1">
            <input type="hidden" name="category" id="editCat">
            <div class="form-group">
                <label>Category</label>
                <input type="text" id="editCatDisplay" disabled style="opacity:.5;">
            </div>
            <div class="form-group">
                <label>Period</label>
                <select name="period" id="editPeriod">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div class="form-group">
                <label>Budget Limit (₱)</label>
                <input type="number" name="amount_limit" id="editLimit" step="0.01" min="1" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">💾 Update</button>
        </form>
    </div>
</div>

<script>
function openAddModal(){ document.getElementById('addModal').classList.add('active'); }
function closeAddModal(){ document.getElementById('addModal').classList.remove('active'); }
function openEditModal(b){
    document.getElementById('editModal').classList.add('active');
    document.getElementById('editCat').value        = b.category;
    document.getElementById('editCatDisplay').value = b.category.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
    document.getElementById('editPeriod').value     = b.period;
    document.getElementById('editLimit').value      = b.amount_limit;
}
function closeEditModal(){ document.getElementById('editModal').classList.remove('active'); }
document.getElementById('addModal').addEventListener('click',  function(e){ if(e.target===this) closeAddModal(); });
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) closeEditModal(); });
</script>
</body>
</html>
