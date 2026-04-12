<?php
session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id  = $_SESSION['client_id'];
$username   = $_SESSION['client_user'];
$user_query = mysqli_query($conn,"SELECT first_name, last_name FROM clients WHERE id='$client_id'");
$user_data  = mysqli_fetch_assoc($user_query);
$active_nav = 'graphs';

// ── PERIOD FILTER ──
$period = isset($_GET['period']) && in_array($_GET['period'],['daily','weekly','monthly','yearly'])
    ? $_GET['period'] : 'monthly';

// ── GRAPH TYPE ──
$graph_type = isset($_GET['graph']) && in_array($_GET['graph'],['expense','income','balance'])
    ? $_GET['graph'] : 'expense';

// ── BUILD LABELS & DATA based on period ──
$labels = []; $exp_data = []; $inc_data = []; $bal_data = [];
// future_flags values: false = real past/present, 'real' = real future tx, 'budget' = budget projection only
$future_flags = [];

$today = date('Y-m-d');

if($period === 'daily'){
    // Past 30 days
    for($i = 29; $i >= 0; $i--){
        $d = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M j', strtotime($d));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND DATE(transaction_date)='$d'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND DATE(transaction_date)='$d'"))['t'];
        $inc_data[] = $inc; $exp_data[] = $exp; $bal_data[] = $inc - $exp;
        $future_flags[] = false;
    }
    // Future days: look ahead up to 30 days for real future-dated transactions
    for($i = 1; $i <= 30; $i++){
        $d = date('Y-m-d', strtotime("+$i days"));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND DATE(transaction_date)='$d'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND DATE(transaction_date)='$d'"))['t'];
        if($inc > 0 || $exp > 0){
            $labels[] = date('M j', strtotime($d)).' ✦';
            $inc_data[] = $inc; $exp_data[] = $exp; $bal_data[] = $inc - $exp;
            $future_flags[] = 'real';
        }
    }

} elseif($period === 'weekly'){
    // Past 12 weeks
    for($i = 11; $i >= 0; $i--){
        $wstart = date('Y-m-d', strtotime("monday -$i weeks"));
        $wend   = date('Y-m-d', strtotime("sunday -$i weeks"));
        $labels[] = 'Wk '.date('M j', strtotime($wstart));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND DATE(transaction_date) BETWEEN '$wstart' AND '$wend'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND DATE(transaction_date) BETWEEN '$wstart' AND '$wend'"))['t'];
        $inc_data[] = $inc; $exp_data[] = $exp; $bal_data[] = $inc - $exp;
        $future_flags[] = false;
    }
    // Future weeks: look ahead up to 12 weeks for real future-dated transactions
    for($i = 1; $i <= 12; $i++){
        $wstart = date('Y-m-d', strtotime("monday +$i weeks"));
        $wend   = date('Y-m-d', strtotime("sunday +$i weeks"));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND DATE(transaction_date) BETWEEN '$wstart' AND '$wend'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND DATE(transaction_date) BETWEEN '$wstart' AND '$wend'"))['t'];
        if($inc > 0 || $exp > 0){
            $labels[] = 'Wk '.date('M j', strtotime($wstart)).' ✦';
            $inc_data[] = $inc; $exp_data[] = $exp; $bal_data[] = $inc - $exp;
            $future_flags[] = 'real';
        }
    }

} elseif($period === 'monthly'){
    // ── Past 12 months (real transactions) ──
    for($i = 11; $i >= 0; $i--){
        $mo  = date('n', strtotime("-$i months"));
        $yr  = date('Y', strtotime("-$i months"));
        $labels[] = date('M Y', strtotime("-$i months"));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND MONTH(transaction_date)='$mo' AND YEAR(transaction_date)='$yr'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND MONTH(transaction_date)='$mo' AND YEAR(transaction_date)='$yr'"))['t'];
        $inc_data[] = $inc; $exp_data[] = $exp; $bal_data[] = $inc - $exp;
        $future_flags[] = false;
    }

    // ── Total monthly budget for projection ──
    $bres = mysqli_query($conn,"SELECT category, amount_limit FROM budgets WHERE client_id='$client_id' AND period='monthly'");
    $monthly_budget_total = 0;
    while($b = mysqli_fetch_assoc($bres)){
        $monthly_budget_total += (float)$b['amount_limit'];
    }

    // ── Future months: show real transactions AND/OR budget projections ──
    for($i = 1; $i <= 12; $i++){
        $future_ts  = strtotime("+$i months");
        $future_mo  = date('n', $future_ts);
        $future_yr  = date('Y', $future_ts);
        $future_lbl = date('M Y', $future_ts);

        $f_inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND MONTH(transaction_date)='$future_mo' AND YEAR(transaction_date)='$future_yr'"))['t'];
        $f_exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND MONTH(transaction_date)='$future_mo' AND YEAR(transaction_date)='$future_yr'"))['t'];
        $has_real_tx = ($f_inc > 0 || $f_exp > 0);

        if($has_real_tx){
            // Real future transactions — show actual values, flag as 'real'
            $labels[]      = $future_lbl.' ✦';
            $inc_data[]    = $f_inc;
            $exp_data[]    = $f_exp;
            $bal_data[]    = $f_inc - $f_exp;
            $future_flags[] = 'real';
        } elseif($monthly_budget_total > 0){
            // No transactions yet but budget exists — show projection
            $labels[]      = $future_lbl;
            $exp_data[]    = $monthly_budget_total;
            $inc_data[]    = 0;
            $bal_data[]    = -$monthly_budget_total;
            $future_flags[] = 'budget';
        }
    }

} else {
    // ── Yearly: past 6 years ──
    $current_yr = (int)date('Y');
    for($i = 5; $i >= 0; $i--){
        $yr = $current_yr - $i;
        $labels[] = (string)$yr;
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND YEAR(transaction_date)='$yr'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND YEAR(transaction_date)='$yr'"))['t'];
        $inc_data[] = $inc; $exp_data[] = $exp; $bal_data[] = $inc - $exp;
        $future_flags[] = false;
    }
    // Future years: look ahead up to 5 years for real future-dated transactions
    for($i = 1; $i <= 5; $i++){
        $yr = $current_yr + $i;
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND YEAR(transaction_date)='$yr'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND YEAR(transaction_date)='$yr'"))['t'];
        if($inc > 0 || $exp > 0){
            $labels[] = (string)$yr.' ✦';
            $inc_data[] = $inc; $exp_data[] = $exp; $bal_data[] = $inc - $exp;
            $future_flags[] = 'real';
        }
    }
}

// Pick active dataset
$active_data  = $graph_type === 'income' ? $inc_data : ($graph_type === 'balance' ? $bal_data : $exp_data);
$active_color = $graph_type === 'income' ? '#2ecc71' : ($graph_type === 'balance' ? '#5dade2' : '#ff6b6b');
$active_fill  = $graph_type === 'income' ? 'rgba(46,204,113,0.12)' : ($graph_type === 'balance' ? 'rgba(93,173,226,0.1)' : 'rgba(255,107,107,0.1)');

// Graph label
$period_labels = ['daily'=>'Daily (Last 30 Days + Future)','weekly'=>'Weekly (Last 12 Weeks + Future)','monthly'=>'Monthly','yearly'=>'Yearly (Last 6 Years + Future)'];
$graph_labels  = ['expense'=>'Total Expenses','income'=>'Total Income','balance'=>'Net Balance'];
$chart_title   = $graph_labels[$graph_type].' — '.$period_labels[$period];

// ── SUMMARY STATS (past/present data only, exclude all future) ──
$past_inc = []; $past_exp = [];
foreach($future_flags as $idx => $flag){
    if($flag === false){ $past_inc[] = $inc_data[$idx]; $past_exp[] = $exp_data[$idx]; }
}
$total_inc = array_sum($past_inc);
$total_exp = array_sum($past_exp);
$total_bal = $total_inc - $total_exp;
$avg_exp   = count($past_exp) > 0 ? array_sum($past_exp)/count($past_exp) : 0;
$avg_inc   = count($past_inc) > 0 ? array_sum($past_inc)/count($past_inc) : 0;
$peak_exp  = max($past_exp ?: [0]);
$peak_inc  = max($past_inc ?: [0]);

$has_future        = in_array('real',   $future_flags) || in_array('budget', $future_flags);
$has_future_real   = in_array('real',   $future_flags);
$has_future_budget = in_array('budget', $future_flags);
$future_real_count   = count(array_filter($future_flags, fn($f) => $f === 'real'));
$future_budget_count = count(array_filter($future_flags, fn($f) => $f === 'budget'));
$future_count = $future_real_count + $future_budget_count;

// ── CATEGORY BREAKDOWN ──
$cat_res = mysqli_query($conn,"SELECT category, type,
    COALESCE(SUM(amount),0) AS total
    FROM transactions WHERE client_id='$client_id'
    AND YEAR(transaction_date)=YEAR(NOW()) AND MONTH(transaction_date)=MONTH(NOW())
    GROUP BY category, type ORDER BY total DESC");
$cat_exp_rows = []; $cat_inc_rows = [];
while($r = mysqli_fetch_assoc($cat_res)){
    if($r['type']==='expense') $cat_exp_rows[] = $r;
    else $cat_inc_rows[] = $r;
}
$max_cat_exp = !empty($cat_exp_rows) ? max(array_column($cat_exp_rows,'total')) : 1;
$max_cat_inc = !empty($cat_inc_rows) ? max(array_column($cat_inc_rows,'total')) : 1;
$icons = ['transpo'=>'🚌','bills'=>'💡','food'=>'🍜','shopping'=>'🛍️','basic_needs'=>'🏠','salary'=>'💰','freelance'=>'💻','business'=>'🏢','others'=>'📦'];

// Build per-point colors for bar charts (real future = tinted, budget projection = lighter/dashed)
$exp_bg_colors = [];
$inc_bg_colors = [];
foreach($future_flags as $flag){
    if($flag === 'real'){
        $exp_bg_colors[] = 'rgba(255,107,107,0.45)';
        $inc_bg_colors[] = 'rgba(46,204,113,0.4)';
    } elseif($flag === 'budget'){
        $exp_bg_colors[] = 'rgba(255,107,107,0.2)';
        $inc_bg_colors[] = 'rgba(46,204,113,0.15)';
    } else {
        $exp_bg_colors[] = 'rgba(255,107,107,0.7)';
        $inc_bg_colors[] = 'rgba(46,204,113,0.7)';
    }
}
$exp_border_colors = [];
foreach($future_flags as $flag){
    if($flag === 'real')    $exp_border_colors[] = 'rgba(93,173,226,0.7)';
    elseif($flag === 'budget') $exp_border_colors[] = 'rgba(255,200,100,0.9)';
    else                    $exp_border_colors[] = 'rgba(255,107,107,0)';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Insights | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php include 'base_styles.php'; ?>
<style>
.insights-header{margin-bottom:28px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;}
.insights-header-text h1{font-size:2rem;font-weight:800;}
.insights-header-text p{color:rgba(255,255,255,.4);font-size:13px;margin-top:5px;}
/* Header action buttons area */
.header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
/* Import button */
.import-btn{background:linear-gradient(135deg,rgba(93,173,226,.18),rgba(52,152,219,.1));
    border:1px solid rgba(93,173,226,.35);color:#5dade2;padding:10px 18px;
    border-radius:12px;font-size:12px;font-weight:800;cursor:pointer;display:flex;align-items:center;
    gap:8px;transition:.22s;white-space:nowrap;text-transform:uppercase;letter-spacing:.5px;
    text-decoration:none;}
.import-btn:hover{background:rgba(93,173,226,.22);border-color:rgba(93,173,226,.6);color:#7ec8f0;}
/* Download dropdown */
.dl-dropdown{position:relative;display:inline-block;}
.dl-btn{background:linear-gradient(135deg,rgba(46,204,113,.18),rgba(31,157,99,.1));
    border:1px solid rgba(46,204,113,.35);color:var(--green-glow);padding:10px 18px;
    border-radius:12px;font-size:12px;font-weight:800;cursor:pointer;display:flex;align-items:center;
    gap:8px;transition:.22s;white-space:nowrap;text-transform:uppercase;letter-spacing:.5px;}
.dl-btn:hover{background:rgba(46,204,113,.22);border-color:rgba(46,204,113,.6);}
.dl-btn .arrow{font-size:10px;transition:.2s;}
.dl-dropdown.open .dl-btn .arrow{transform:rotate(180deg);}
.dl-menu{display:none;position:absolute;right:0;top:calc(100% + 8px);background:#1e2d3d;
    border:1px solid rgba(46,204,113,.25);border-radius:14px;min-width:220px;z-index:999;
    box-shadow:0 12px 40px rgba(0,0,0,.5);overflow:hidden;}
.dl-dropdown.open .dl-menu{display:block;}
.dl-menu a{display:flex;align-items:center;gap:10px;padding:13px 18px;font-size:12px;
    font-weight:700;color:rgba(255,255,255,.8);text-decoration:none;transition:.18s;}
.dl-menu a:hover{background:rgba(46,204,113,.1);color:var(--green-glow);}
.dl-menu-sep{height:1px;background:rgba(255,255,255,.06);margin:2px 0;}
.dl-menu-label{padding:8px 18px 4px;font-size:10px;text-transform:uppercase;
    letter-spacing:1.5px;color:rgba(255,255,255,.25);}
/* Import modal overlay */
.import-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);
    backdrop-filter:blur(6px);z-index:2000;align-items:center;justify-content:center;}
.import-modal-overlay.open{display:flex;}
.import-modal{background:#0f1e14;border:1px solid rgba(93,173,226,.25);border-radius:22px;
    padding:32px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;
    box-shadow:0 24px 80px rgba(0,0,0,.7);position:relative;}
.import-modal h2{font-size:1.3rem;font-weight:800;margin-bottom:6px;}
.import-modal .sub{font-size:12px;color:rgba(255,255,255,.4);margin-bottom:24px;}
.modal-close{position:absolute;top:18px;right:20px;background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.6);
    border-radius:8px;padding:4px 10px;font-size:14px;cursor:pointer;transition:.18s;}
.modal-close:hover{background:rgba(255,107,107,.15);color:var(--red);border-color:rgba(255,107,107,.3);}
.im-upload-zone{border:2px dashed rgba(93,173,226,.3);border-radius:14px;padding:36px 24px;
    text-align:center;cursor:pointer;background:rgba(93,173,226,.03);transition:.3s;}
.im-upload-zone:hover,.im-upload-zone.drag{border-color:#5dade2;background:rgba(93,173,226,.08);}
.im-upload-zone .iz-icon{font-size:42px;margin-bottom:10px;}
.im-upload-zone h3{font-weight:800;font-size:1rem;margin-bottom:5px;}
.im-upload-zone p{color:rgba(255,255,255,.4);font-size:12px;}
.im-file-chosen{margin-top:10px;font-size:12px;color:#5dade2;font-weight:700;word-break:break-all;}
.im-col-guide{background:rgba(93,173,226,.06);border:1px solid rgba(93,173,226,.18);
    border-radius:12px;padding:16px 18px;margin-bottom:18px;}
.im-col-guide h4{font-size:12px;font-weight:800;color:#5dade2;margin-bottom:8px;}
.im-col-list{display:flex;flex-wrap:wrap;gap:6px;}
.im-col-tag{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
    border-radius:7px;padding:3px 10px;font-size:11px;font-family:monospace;}
.im-col-tag.req{border-color:rgba(93,173,226,.4);color:#5dade2;}
.im-action-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:18px;}
/* Import results inside modal */
.im-results{margin-top:20px;}
.im-summary-bar{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;}
.im-pill{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
    border-radius:10px;padding:10px 16px;font-size:12px;font-weight:700;}
.im-err-list{background:rgba(255,107,107,.06);border:1px solid rgba(255,107,107,.2);
    border-radius:10px;padding:14px 16px;margin-bottom:14px;font-size:12px;}
.im-err-list li{color:rgba(255,150,150,.9);margin-bottom:3px;}
.im-result-table{width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px;}
.im-result-table th{background:#1a2a1e;padding:8px 12px;text-align:left;font-size:10px;
    text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.4);}
.im-result-table td{padding:8px 12px;border-bottom:1px solid rgba(255,255,255,.04);}
.badge-inc{background:rgba(46,204,113,.12);color:var(--green-glow);padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;}
.badge-exp{background:rgba(255,107,107,.1);color:var(--red);padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;}

/* CONTROLS */
.graph-controls{display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;align-items:center;}
.ctrl-group{display:flex;gap:4px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:4px;}
.ctrl-btn{padding:8px 18px;border-radius:9px;border:none;background:transparent;
    color:rgba(255,255,255,.45);font-size:12px;font-weight:700;cursor:pointer;
    text-transform:uppercase;letter-spacing:.4px;transition:.22s;text-decoration:none;display:inline-block;}
.ctrl-btn:hover{color:#fff;background:rgba(255,255,255,.06);}
.ctrl-btn.active{background:var(--green-main);color:#000;}
.ctrl-btn.exp.active{background:#ff6b6b;color:#fff;}
.ctrl-btn.inc.active{background:#2ecc71;color:#000;}
.ctrl-btn.bal.active{background:#5dade2;color:#fff;}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px;}
.stat-row .stat-card{background:rgba(8,20,14,.82);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);}

/* CHART CARD */
.chart-card{background:rgba(8,20,14,.82);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
    border:1px solid rgba(255,255,255,.07);
    border-radius:20px;padding:28px;margin-bottom:24px;}
.chart-title{font-size:14px;font-weight:800;color:rgba(255,255,255,.8);margin-bottom:6px;
    display:flex;align-items:center;gap:8px;}
.chart-subtitle{font-size:11px;color:rgba(255,255,255,.3);margin-bottom:18px;}
.chart-canvas-wrap{position:relative;height:280px;}

/* PROJECTION LEGEND */
.proj-legend{display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap;}
.proj-legend-item{display:flex;align-items:center;gap:6px;font-size:11px;color:rgba(255,255,255,.5);}
.proj-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0;}

/* INSIGHT BOX */
.insight-box{background:rgba(8,22,14,.75);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    border-left:3px solid var(--green-glow);
    border-radius:0 16px 16px 0;border-top:1px solid rgba(46,204,113,.15);
    border-right:1px solid rgba(46,204,113,.12);border-bottom:1px solid rgba(46,204,113,.12);
    padding:20px 24px;margin-bottom:24px;}
.insight-box .lbl{font-size:10px;text-transform:uppercase;letter-spacing:2px;
    color:var(--green-glow);margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.insight-box p{font-size:13px;color:rgba(255,255,255,.75);line-height:1.7;}
.insight-box .hi{color:var(--green-glow);font-weight:800;}

/* PROJECTION NOTICE */
.proj-notice{background:rgba(10,18,12,.8);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    border:1px solid rgba(255,200,100,.25);
    border-radius:14px;padding:14px 18px;margin-bottom:24px;
    font-size:12px;color:rgba(255,200,100,.9);display:flex;align-items:flex-start;gap:10px;}
.proj-notice span{font-size:18px;flex-shrink:0;margin-top:1px;}

/* CATEGORY BARS */
.cat-breakdown{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px;}
.cat-breakdown .card-section{background:rgba(8,20,14,.82);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.07);}
.cat-section h3{font-size:13px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:6px;}
.cat-bar-row{display:flex;align-items:center;gap:10px;padding:8px 0;
    border-bottom:1px solid rgba(255,255,255,.04);}
.cat-bar-row:last-child{border-bottom:none;}
.cat-bar-icon{font-size:16px;flex-shrink:0;}
.cat-bar-name{font-size:12px;width:90px;flex-shrink:0;}
.cat-bar-track{flex:1;height:5px;background:rgba(255,255,255,.07);border-radius:4px;}
.cat-bar-fill{height:5px;border-radius:4px;}
.cat-bar-amt{font-size:11px;font-weight:700;white-space:nowrap;min-width:70px;text-align:right;}

@media(max-width:700px){.cat-breakdown{grid-template-columns:1fr;}}
</style>
</head>
<body>
<?php include 'nav_include.php'; ?>
<div class="container">

    <div class="insights-header">
        <div class="insights-header-text">
            <h1>📊 Insights</h1>
            <p>Visual breakdown of your financial activity<?php echo $has_future ? ' — including budget projections' : ''; ?>.</p>
        </div>
        <div class="header-actions">
            <!-- Import Button -->
            <button class="import-btn" onclick="openImportModal()">
                📥 Import Data
            </button>
            <!-- Download Dropdown -->
            <div class="dl-dropdown" id="dlDropdown">
                <button class="dl-btn" onclick="toggleDlMenu()">
                    ⬇️ Download Report <span class="arrow">▾</span>
                </button>
                <div class="dl-menu">
                    <div class="dl-menu-label">Excel (.xls)</div>
                    <a href="download_report.php?format=excel&graph=<?php echo $graph_type; ?>&period=<?php echo $period; ?>">
                        📊 Current View (<?php echo ucfirst($period); ?> · <?php echo ucfirst($graph_type); ?>)
                    </a>
                    <a href="download_report.php?format=excel&graph=all&period=<?php echo $period; ?>">
                        📋 Full <?php echo ucfirst($period); ?> Report
                    </a>
                    <div class="dl-menu-sep"></div>
                    <div class="dl-menu-label">CSV</div>
                    <a href="download_report.php?format=csv&graph=<?php echo $graph_type; ?>&period=<?php echo $period; ?>">
                        📄 Current View (<?php echo ucfirst($period); ?> · <?php echo ucfirst($graph_type); ?>)
                    </a>
                    <a href="download_report.php?format=csv&graph=all&period=<?php echo $period; ?>">
                        📃 Full <?php echo ucfirst($period); ?> Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         IMPORT MODAL
         ══════════════════════════════════════════════════════════════ -->
    <div class="import-modal-overlay" id="importModalOverlay" onclick="handleOverlayClick(event)">
        <div class="import-modal" id="importModal">
            <button class="modal-close" onclick="closeImportModal()">✕</button>

            <!-- ── STEP 1: Upload form ── -->
            <div id="imStepUpload">
                <h2>📥 Import Financial Records</h2>
                <p class="sub">Upload a CSV or Excel file to bulk-import your transactions.</p>

                <!-- Column guide -->
                <div class="im-col-guide">
                    <h4>📄 Required &amp; Supported Columns</h4>
                    <div class="im-col-list" style="margin-bottom:8px;">
                        <span class="im-col-tag req">type</span>
                        <span class="im-col-tag req">category</span>
                        <span class="im-col-tag req">amount</span>
                        <span class="im-col-tag">date</span>
                        <span class="im-col-tag">note</span>
                        <span class="im-col-tag">account</span>
                    </div>
                    <p style="font-size:11px;color:rgba(255,255,255,.35);margin:0;">
                        <strong style="color:#5dade2;">type</strong> must be <code>income</code> or <code>expense</code>.
                        Use <em>Download CSV Template</em> below to get a ready-made format.
                    </p>
                </div>

                <!-- Drop zone -->
                <div class="im-upload-zone" id="imDropZone" onclick="document.getElementById('imFileInput').click()">
                    <div class="iz-icon">📂</div>
                    <h3>Drop your file here or click to browse</h3>
                    <p>Supports .csv, .xlsx, .xls</p>
                    <div class="im-file-chosen" id="imFileChosen"></div>
                </div>

                <form enctype="multipart/form-data" id="imUploadForm" onsubmit="handleImportSubmit(event)">
                    <input type="hidden" name="upload_csv" value="1">
                    <input type="file" id="imFileInput" name="tx_file"
                           accept=".csv,.xls,.xlsx" onchange="imShowFile(this)" style="display:none;">
                    <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;">
                        <button type="submit" id="imSubmitBtn"
                            class="btn-primary"
                            style="padding:12px 28px;border-radius:13px;font-size:13px;
                                   opacity:.4;cursor:not-allowed;flex:1;min-width:160px;" disabled>
                            📥 Import Transactions
                        </button>
                        <a href="download_report.php?format=csv&period=monthly"
                           class="btn-outline" style="font-size:12px;padding:12px 16px;border-radius:13px;text-align:center;">
                            ⬇️ CSV Template
                        </a>
                    </div>
                </form>
            </div>

            <!-- ── STEP 2: Loading ── -->
            <div id="imStepLoading" style="display:none;text-align:center;padding:40px 0;">
                <div style="font-size:36px;margin-bottom:14px;animation:spin 1s linear infinite;display:inline-block;">⚙️</div>
                <p style="color:rgba(255,255,255,.6);font-size:14px;font-weight:700;">Importing your records…</p>
                <p style="color:rgba(255,255,255,.3);font-size:12px;">Please wait</p>
            </div>

            <!-- ── STEP 3: Results ── -->
            <div id="imStepResults" style="display:none;">
                <!-- Filled by JS -->
            </div>
        </div>
    </div>
    <style>@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}</style>

    <!-- CONTROLS -->
    <div class="graph-controls">
        <div class="ctrl-group">
            <a href="?graph=expense&period=<?php echo $period; ?>" class="ctrl-btn exp <?php echo $graph_type==='expense'?'active':''; ?>">💸 Expenses</a>
            <a href="?graph=income&period=<?php echo $period; ?>"  class="ctrl-btn inc <?php echo $graph_type==='income' ?'active':''; ?>">💰 Income</a>
            <a href="?graph=balance&period=<?php echo $period; ?>" class="ctrl-btn bal <?php echo $graph_type==='balance'?'active':''; ?>">⚖️ Balance</a>
        </div>
        <div class="ctrl-group">
            <a href="?graph=<?php echo $graph_type; ?>&period=daily"   class="ctrl-btn <?php echo $period==='daily'  ?'active':''; ?>">Daily</a>
            <a href="?graph=<?php echo $graph_type; ?>&period=weekly"  class="ctrl-btn <?php echo $period==='weekly' ?'active':''; ?>">Weekly</a>
            <a href="?graph=<?php echo $graph_type; ?>&period=monthly" class="ctrl-btn <?php echo $period==='monthly'?'active':''; ?>">Monthly</a>
            <a href="?graph=<?php echo $graph_type; ?>&period=yearly"  class="ctrl-btn <?php echo $period==='yearly' ?'active':''; ?>">Yearly</a>
        </div>
    </div>

    <!-- PROJECTION NOTICE (when future data exists in any view) -->
    <?php if($has_future): ?>
    <div class="proj-notice">
        <span>📅</span>
        <div>
            <?php if($has_future_real && $has_future_budget): ?>
                <strong>Future Data Active</strong> — <?php echo $future_real_count; ?> upcoming period<?php echo $future_real_count > 1 ? 's' : ''; ?> with real logged transactions (✦), and <?php echo $future_budget_count; ?> month<?php echo $future_budget_count > 1 ? 's' : ''; ?> with budget projections shown.
            <?php elseif($has_future_real): ?>
                <strong>Future Transactions Detected</strong> — <?php echo $future_real_count; ?> upcoming period<?php echo $future_real_count > 1 ? 's' : ''; ?> contain real logged transactions (✦) and are shown with a tinted bar.
            <?php else: ?>
                <strong>Budget Projection Active</strong> — <?php echo $future_budget_count; ?> upcoming month<?php echo $future_budget_count > 1 ? 's' : ''; ?> shown based on your monthly budget limits. Faded bars represent projected spending, not actual transactions.
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-row">
        <div class="stat-card">
            <span class="stat-label">Period Income</span>
            <div class="stat-value" style="color:var(--green-glow);">₱<?php echo number_format($total_inc,2); ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Period Expenses</span>
            <div class="stat-value" style="color:var(--red);">₱<?php echo number_format($total_exp,2); ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Net Balance</span>
            <div class="stat-value" style="color:var(--blue);">₱<?php echo number_format($total_bal,2); ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Avg <?php echo ucfirst($graph_type); ?></span>
            <div class="stat-value" style="font-size:1.3rem;">₱<?php echo number_format($graph_type==='income'?$avg_inc:$avg_exp,2); ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Peak <?php echo ucfirst($graph_type); ?></span>
            <div class="stat-value" style="font-size:1.3rem;">₱<?php echo number_format($graph_type==='income'?$peak_inc:$peak_exp,2); ?></div>
        </div>
    </div>

    <!-- INSIGHT BOX -->
    <div class="insight-box">
        <div class="lbl">✨ Supreme Intelligence</div>
        <p>
        <?php
        $past_active = $graph_type === 'income' ? $past_inc : $past_exp;
        $val         = array_sum($past_active);
        $avg         = count($past_active) > 0 ? $val/count($past_active) : 0;
        $peak_val    = max($past_active ?: [0]);
        $peak_idx    = array_search($peak_val, $past_active);
        // Rebuild label list for past only
        $past_labels = array_values(array_filter($labels, function($k){ return !$GLOBALS['future_flags'][$k]; }, ARRAY_FILTER_USE_KEY));
        $peak_label  = isset($past_labels[$peak_idx]) ? $past_labels[$peak_idx] : '—';
        $graph_lbl   = strtolower($graph_labels[$graph_type]);

        if($val == 0){
            echo "No $graph_lbl data found for this period. Start logging transactions to see your insights here.";
        } else {
            echo "Your total <span class='hi'>$graph_lbl</span> for the selected period is <span class='hi'>₱".number_format($val,2)."</span>, averaging <span class='hi'>₱".number_format($avg,2)."</span> per month. ";
            echo "The highest point was <span class='hi'>₱".number_format($peak_val,2)."</span> during <span class='hi'>$peak_label</span>. ";
            if($graph_type === 'expense'){
                if($total_exp > $total_inc && $total_inc > 0)
                    echo "<span style='color:var(--red);font-weight:700;'>⚠️ Your expenses exceed your income for this period. Consider reducing discretionary spending.</span>";
                elseif($avg_exp > 0 && $avg_inc > 0 && ($avg_exp/$avg_inc) > 0.8)
                    echo "You're spending <span class='hi'>".round(($avg_exp/$avg_inc)*100)."%</span> of your average income. Try to keep this below 80%.";
                else
                    echo "Your spending is <span class='hi'>well within</span> your income range. Keep it up!";
            } elseif($graph_type === 'income'){
                echo "Your income flow looks <span class='hi'>active</span> for this period.";
            } else {
                if($total_bal >= 0)
                    echo "Your balance is <span class='hi'>positive</span> — you're earning more than you spend. Great financial health!";
                else
                    echo "<span style='color:var(--red);font-weight:700;'>⚠️ Your balance is negative for this period. Your spending exceeds your income.</span>";
            }
        }
        if($has_future_real){
            echo " <span style='color:rgba(93,173,226,.85);'>✦ ".number_format($future_real_count)." upcoming period".($future_real_count>1?'s':'')." contain real logged transactions and are highlighted on the chart.</span>";
        }
        if($has_future_budget && $period === 'monthly'){
            echo " <span style='color:rgba(255,200,100,.8);'>The faded bars ahead show your projected monthly budget of <strong>₱".number_format($monthly_budget_total ?? 0,2)."</strong>.</span>";
        }
        ?>
        </p>
    </div>

    <!-- MAIN CHART -->
    <div class="chart-card">
        <div class="chart-title">
            <?php echo $graph_type==='expense'?'💸':($graph_type==='income'?'💰':'⚖️'); ?>
            <?php echo $chart_title; ?>
        </div>
        <?php if($has_future): ?>
        <div class="proj-legend">
            <div class="proj-legend-item"><div class="proj-dot" style="background:<?php echo $active_color; ?>;opacity:.85;"></div> Actual</div>
            <?php if($has_future_real): ?><div class="proj-legend-item"><div class="proj-dot" style="background:<?php echo $active_color; ?>;opacity:.45;border:1px solid rgba(93,173,226,.7);"></div> Future Transaction (✦)</div><?php endif; ?>
            <?php if($has_future_budget): ?><div class="proj-legend-item"><div class="proj-dot" style="background:<?php echo $active_color; ?>;opacity:.2;border:1px dashed rgba(255,200,100,.8);"></div> Budget Projection</div><?php endif; ?>
        </div>
        <?php else: ?>
        <div style="margin-bottom:18px;"></div>
        <?php endif; ?>
        <div class="chart-canvas-wrap">
            <canvas id="mainChart"></canvas>
        </div>
    </div>

    <!-- COMBINED CHART (income vs expense + projections) -->
    <div class="chart-card">
        <div class="chart-title">📈 Income vs Expenses<?php echo $has_future ? ' + Future Data' : ''; ?></div>
        <?php if($has_future): ?>
        <div class="chart-subtitle">
            <?php if($has_future_real): ?>✦ Tinted bars = real future-dated transactions. <?php endif; ?>
            <?php if($has_future_budget): ?>Faded bars = projected budget spending. <?php endif; ?>
        </div>
        <div class="proj-legend">
            <div class="proj-legend-item"><div class="proj-dot" style="background:rgba(46,204,113,.7);"></div> Actual Income</div>
            <div class="proj-legend-item"><div class="proj-dot" style="background:rgba(255,107,107,.7);"></div> Actual Expenses</div>
            <?php if($has_future_real): ?><div class="proj-legend-item"><div class="proj-dot" style="background:rgba(255,107,107,.45);border:1px solid rgba(93,173,226,.6);"></div> Future Transactions (✦)</div><?php endif; ?>
            <?php if($has_future_budget): ?><div class="proj-legend-item"><div class="proj-dot" style="background:rgba(255,107,107,.2);border:1px dashed rgba(255,200,100,.7);"></div> Budget Projection</div><?php endif; ?>
        </div>
        <?php else: ?>
        <div style="margin-bottom:18px;"></div>
        <?php endif; ?>
        <div class="chart-canvas-wrap">
            <canvas id="compareChart"></canvas>
        </div>
    </div>

    <!-- CATEGORY BREAKDOWN THIS MONTH -->
    <div class="cat-breakdown">
        <div class="card-section">
            <h3>💸 Top Expenses This Month</h3>
            <?php if(!empty($cat_exp_rows)): foreach($cat_exp_rows as $c):
                $cat=strtolower($c['category']); $icon=$icons[$cat]??'📦';
                $lbl=ucwords(str_replace('_',' ',$c['category']));
                $pct=$max_cat_exp>0?round(($c['total']/$max_cat_exp)*100):0;
            ?>
            <div class="cat-bar-row">
                <div class="cat-bar-icon"><?php echo $icon; ?></div>
                <div class="cat-bar-name"><?php echo $lbl; ?></div>
                <div class="cat-bar-track"><div class="cat-bar-fill" style="width:<?php echo $pct; ?>%;background:var(--red);"></div></div>
                <div class="cat-bar-amt" style="color:var(--red);">₱<?php echo number_format($c['total'],0); ?></div>
            </div>
            <?php endforeach; else: ?>
            <div class="empty-state" style="padding:16px 0;font-size:12px;">No expenses this month.</div>
            <?php endif; ?>
        </div>
        <div class="card-section">
            <h3>💰 Income Sources This Month</h3>
            <?php if(!empty($cat_inc_rows)): foreach($cat_inc_rows as $c):
                $cat=strtolower($c['category']); $icon=$icons[$cat]??'📦';
                $lbl=ucwords(str_replace('_',' ',$c['category']));
                $pct=$max_cat_inc>0?round(($c['total']/$max_cat_inc)*100):0;
            ?>
            <div class="cat-bar-row">
                <div class="cat-bar-icon"><?php echo $icon; ?></div>
                <div class="cat-bar-name"><?php echo $lbl; ?></div>
                <div class="cat-bar-track"><div class="cat-bar-fill" style="width:<?php echo $pct; ?>%;background:var(--green-main);"></div></div>
                <div class="cat-bar-amt" style="color:var(--green-glow);">₱<?php echo number_format($c['total'],0); ?></div>
            </div>
            <?php endforeach; else: ?>
            <div class="empty-state" style="padding:16px 0;font-size:12px;">No income this month.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
const labels       = <?php echo json_encode($labels); ?>;
const expData      = <?php echo json_encode($exp_data); ?>;
const incData      = <?php echo json_encode($inc_data); ?>;
const balData      = <?php echo json_encode($bal_data); ?>;
const actData      = <?php echo json_encode($active_data); ?>;
const futureFlags  = <?php echo json_encode($future_flags); ?>;
const actColor     = '<?php echo $active_color; ?>';
const actFill      = '<?php echo $active_fill; ?>';
const expBgColors  = <?php echo json_encode($exp_bg_colors); ?>;
const incBgColors  = <?php echo json_encode($inc_bg_colors); ?>;
const expBorderColors = <?php echo json_encode($exp_border_colors); ?>;

// Per-point colors for main chart (active dataset) — three states: false, 'real', 'budget'
const mainBgColors = futureFlags.map(f => {
    if(f === 'real')   return actColor === '#ff6b6b' ? 'rgba(255,107,107,0.45)' : actColor === '#2ecc71' ? 'rgba(46,204,113,0.4)'  : 'rgba(93,173,226,0.4)';
    if(f === 'budget') return actColor === '#ff6b6b' ? 'rgba(255,107,107,0.2)'  : actColor === '#2ecc71' ? 'rgba(46,204,113,0.15)' : 'rgba(93,173,226,0.15)';
    return actColor === '#ff6b6b' ? 'rgba(255,107,107,0.75)' : actColor === '#2ecc71' ? 'rgba(46,204,113,0.7)' : 'rgba(93,173,226,0.65)';
});
const mainBorderPerPoint = futureFlags.map(f => f === 'real' ? 'rgba(93,173,226,0.7)' : f === 'budget' ? 'rgba(255,200,100,0.6)' : actColor);

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color:'rgba(255,255,255,.7)', font:{ family:'Plus Jakarta Sans', size:12 } } },
        tooltip: {
            callbacks: {
                label: function(ctx){
                    const flag = futureFlags[ctx.dataIndex];
                    let prefix = '';
                    if(flag === 'real')   prefix = '✦ Future: ';
                    if(flag === 'budget') prefix = '📅 Projected: ';
                    return prefix + '₱' + ctx.parsed.y.toLocaleString();
                }
            }
        }
    },
    scales: {
        y: { beginAtZero:true, grid:{ color:'rgba(255,255,255,.05)' }, ticks:{ color:'rgba(255,255,255,.5)', callback: v => '₱'+v.toLocaleString() } },
        x: { grid:{ display:false }, ticks:{ color:'rgba(255,255,255,.45)', maxRotation:45 } }
    }
};

// ── MAIN CHART ──
new Chart(document.getElementById('mainChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: '<?php echo addslashes($graph_labels[$graph_type]); ?>',
            data: actData,
            backgroundColor: mainBgColors,
            borderColor: mainBorderPerPoint,
            borderWidth: futureFlags.map(f => f !== false ? 1.5 : 0),
            borderRadius: 6,
        }]
    },
    options: chartDefaults
});

// ── COMPARE CHART (income vs expense, future projected) ──
new Chart(document.getElementById('compareChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Income',
                data: incData,
                backgroundColor: incBgColors,
                borderRadius: 4
            },
            {
                label: 'Expenses / Projected',
                data: expData,
                backgroundColor: expBgColors,
                borderColor: expBorderColors,
                borderWidth: futureFlags.map(f => f !== false ? 1.5 : 0),
                borderRadius: 4
            },
        ]
    },
    options: { ...chartDefaults }
});
</script>
<script>
/* ── Download dropdown ── */
function toggleDlMenu(){
    document.getElementById('dlDropdown').classList.toggle('open');
}
document.addEventListener('click', function(e){
    var d = document.getElementById('dlDropdown');
    if(d && !d.contains(e.target)) d.classList.remove('open');
});

/* ── Import Modal ── */
function openImportModal(){
    document.getElementById('importModalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeImportModal(){
    document.getElementById('importModalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
function handleOverlayClick(e){
    if(e.target === document.getElementById('importModalOverlay')) closeImportModal();
}

/* ── File picker / drag-drop ── */
var imDropZone   = document.getElementById('imDropZone');
var imFileInput  = document.getElementById('imFileInput');
var imSubmitBtn  = document.getElementById('imSubmitBtn');
var imFileChosen = document.getElementById('imFileChosen');

function imShowFile(input){
    if(input.files && input.files.length > 0){
        imFileChosen.textContent = '📎 ' + input.files[0].name;
        imSubmitBtn.disabled      = false;
        imSubmitBtn.style.opacity = '1';
        imSubmitBtn.style.cursor  = 'pointer';
    }
}
if(imDropZone){
    imDropZone.addEventListener('dragover', function(e){ e.preventDefault(); imDropZone.classList.add('drag'); });
    imDropZone.addEventListener('dragleave', function(){ imDropZone.classList.remove('drag'); });
    imDropZone.addEventListener('drop', function(e){
        e.preventDefault(); imDropZone.classList.remove('drag');
        var dt = e.dataTransfer;
        if(dt && dt.files && dt.files.length){ imFileInput.files = dt.files; imShowFile(imFileInput); }
    });
}

function showStep(step){
    document.getElementById('imStepUpload').style.display  = step === 'upload'  ? '' : 'none';
    document.getElementById('imStepLoading').style.display = step === 'loading' ? '' : 'none';
    document.getElementById('imStepResults').style.display = step === 'results' ? '' : 'none';
}

/* ── AJAX Submit ── */
function handleImportSubmit(e){
    e.preventDefault();
    if(!imFileInput.files || !imFileInput.files.length) return;

    var fd = new FormData(document.getElementById('imUploadForm'));
    showStep('loading');

    fetch('upload_transactions.php', { method:'POST', body: fd })
        .then(function(res){ return res.text(); })
        .then(function(html){
            // Parse the returned HTML
            var parser  = new DOMParser();
            var doc     = parser.parseFromString(html, 'text/html');

            // Pull data from the simplified result page
            var imported = 0, skipped = 0;

            // Try to get counts from the success heading (e.g. "5 Transactions Imported!")
            var heading = doc.querySelector('h2');
            if(heading){
                var hm = heading.textContent.match(/(\d+)\s+Transaction/i);
                if(hm) imported = parseInt(hm[1]);
            }
            // Skipped count from the warning span
            var skipSpan = doc.querySelector('[style*="ffc864"]');
            if(skipSpan){
                var sm = skipSpan.textContent.match(/(\d+)\s+row/i);
                if(sm) skipped = parseInt(sm[1]);
            }
            // Errors
            var errItems = doc.querySelectorAll('.err-list li');
            var errList  = [];
            errItems.forEach(function(li){ errList.push(li.textContent.trim()); });

            // Build result HTML
            var html = '';

            if(imported > 0){
                html += '<div style="text-align:center;padding:10px 0 20px;">'
                    + '<div style="font-size:48px;margin-bottom:10px;">✅</div>'
                    + '<h2 style="font-size:1.4rem;font-weight:800;margin-bottom:6px;">'
                    + imported + ' Transaction' + (imported !== 1 ? 's' : '') + ' Added!'
                    + '</h2>'
                    + '<p style="color:rgba(255,255,255,.45);font-size:13px;margin-bottom:0;">'
                    + 'Your financial records have been saved successfully.'
                    + (skipped > 0 ? '<br><span style="color:#ffc864;">⚠️ ' + skipped + ' row' + (skipped !== 1 ? 's' : '') + ' skipped (blank, zero, or unknown type).</span>' : '')
                    + '</p>'
                    + '</div>';
            } else {
                html += '<div style="text-align:center;padding:10px 0 20px;">'
                    + '<div style="font-size:48px;margin-bottom:10px;">⚠️</div>'
                    + '<h2 style="font-size:1.2rem;font-weight:800;margin-bottom:6px;">Nothing imported</h2>'
                    + '<p style="color:rgba(255,255,255,.45);font-size:13px;">No valid rows found. Check your file format and try again.</p>'
                    + '</div>';
            }

            // Show errors if any
            if(errList.length > 0){
                html += '<div style="background:rgba(255,107,107,.06);border:1px solid rgba(255,107,107,.2);'
                    + 'border-radius:10px;padding:14px 16px;margin-bottom:16px;">'
                    + '<div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;'
                    + 'color:var(--red);margin-bottom:8px;">Row Errors</div>'
                    + '<ul style="margin:0;padding-left:16px;">';
                errList.slice(0,5).forEach(function(e){
                    html += '<li style="font-size:12px;color:rgba(255,150,150,.9);margin-bottom:3px;">' + e + '</li>';
                });
                if(errList.length > 5) html += '<li style="font-size:11px;color:rgba(255,255,255,.3);">...and ' + (errList.length-5) + ' more</li>';
                html += '</ul></div>';
            }

            // Single action button
            if(imported > 0){
                html += '<div style="text-align:center;margin-top:4px;">'
                    + '<button onclick="location.reload()" class="btn-primary" '
                    + 'style="padding:12px 36px;border-radius:14px;font-size:14px;border:none;cursor:pointer;">'
                    + '🔄 Refresh Insights'
                    + '</button>'
                    + '</div>';
            } else {
                html += '<div style="text-align:center;margin-top:4px;">'
                    + '<button onclick="showStep(\'upload\')" class="btn-primary" '
                    + 'style="padding:12px 36px;border-radius:14px;font-size:14px;border:none;cursor:pointer;">'
                    + '↩ Try Again'
                    + '</button>'
                    + '</div>';
            }

            document.getElementById('imStepResults').innerHTML = html;
            showStep('results');
        })
        .catch(function(){
            document.getElementById('imStepResults').innerHTML =
                '<div style="text-align:center;padding:30px 0;">'
                + '<div style="font-size:40px;margin-bottom:12px;">❌</div>'
                + '<p style="color:var(--red);font-size:14px;font-weight:700;">Upload failed. Please try again.</p>'
                + '<button onclick="showStep(\'upload\')" class="btn-primary" '
                + 'style="margin-top:16px;padding:11px 28px;border-radius:13px;font-size:13px;border:none;cursor:pointer;">'
                + '↩ Try Again</button>'
                + '</div>';
            showStep('results');
        });
}
</script>
</body>
</html>