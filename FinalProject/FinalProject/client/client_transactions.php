<?php
session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id  = $_SESSION['client_id'];
$username   = $_SESSION['client_user'];
$user_query = mysqli_query($conn,"SELECT first_name, last_name FROM clients WHERE id='$client_id'");
$user_data  = mysqli_fetch_assoc($user_query);
$active_nav = 'transactions';

// ── VIEW MONTH ──
$view_year  = isset($_GET['year'])  ? intval($_GET['year'])  : intval(date('Y'));
$view_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
if($view_month < 1)  { $view_month = 12; $view_year--; }
if($view_month > 12) { $view_month = 1;  $view_year++; }
$view_month_str = str_pad($view_month, 2, '0', STR_PAD_LEFT);
$view_first     = "$view_year-$view_month_str-01";
$month_label    = date('M', mktime(0,0,0,$view_month,1,$view_year));

// ── VIEW MODE ──
$view_mode = isset($_GET['mode']) && $_GET['mode'] === 'monthly' ? 'monthly' : 'daily';

// ── SELECTED DATE ──
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if(substr($selected_date,0,7) !== "$view_year-$view_month_str"){
    $selected_date = date('Y')==$view_year && date('n')==$view_month
        ? date('Y-m-d') : $view_first;
}

// ── CALENDAR VISIBILITY ──
$cal_hidden = isset($_GET['cal']) && $_GET['cal'] === 'hide';

// ── HANDLE: Save multiple transactions ──────────────────────────────
if(isset($_POST['save_transaction'])){
    $tx_type    = $_POST['type'] === 'income' ? 'income' : 'expense';
    $tx_date    = mysqli_real_escape_string($conn, trim($_POST['tx_date']));
    $acc_id     = intval($_POST['account_id'] ?? 0);
    $categories = $_POST['category']  ?? [];
    $amounts    = $_POST['amount']    ?? [];
    $notes      = $_POST['note']      ?? [];

    for($i = 0; $i < count($categories); $i++){
        $cat    = mysqli_real_escape_string($conn, trim($categories[$i]));
        $amount = floatval($amounts[$i] ?? 0);
        $note   = mysqli_real_escape_string($conn, trim($notes[$i] ?? ''));
        if($cat && $amount > 0 && $tx_date){
            $acc_val = $acc_id > 0 ? "'$acc_id'" : 'NULL';
            $dt      = $tx_date.' '.date('H:i:s');
            mysqli_query($conn,"INSERT INTO transactions (client_id,account_id,type,category,amount,note,transaction_date)
                VALUES ('$client_id',$acc_val,'$tx_type','$cat','$amount','$note','$dt')");
            if($acc_id > 0){
                $dir = $tx_type==='income' ? '+' : '-';
                mysqli_query($conn,"UPDATE accounts SET balance=balance{$dir}{$amount}
                    WHERE id='$acc_id' AND client_id='$client_id'");
            }
        }
    }
    header("Location: client_transactions.php?year=$view_year&month=$view_month_str&mode=$view_mode&date=".urlencode($tx_date)."&cal=".($cal_hidden?'hide':'show'));
    exit();
}

// ── HANDLE: Update transaction ──
if(isset($_POST['update_transaction'])){
    $tid      = intval($_POST['edit_tx_id']);
    $new_type = $_POST['edit_type'] === 'income' ? 'income' : 'expense';
    $new_cat  = mysqli_real_escape_string($conn, trim($_POST['edit_category']));
    $new_amt  = floatval($_POST['edit_amount']);
    $new_note = mysqli_real_escape_string($conn, trim($_POST['edit_note']));
    $new_date = mysqli_real_escape_string($conn, trim($_POST['edit_date']));

    $old = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM transactions WHERE id='$tid' AND client_id='$client_id'"));
    if($old && $new_cat && $new_amt > 0 && $new_date){
        // Revert old effect on account balance
        if($old['account_id']){
            $revert = $old['type']==='income' ? '-' : '+';
            mysqli_query($conn,"UPDATE accounts SET balance=balance{$revert}{$old['amount']} WHERE id='{$old['account_id']}' AND client_id='$client_id'");
        }
        $new_acc  = intval($_POST['edit_account_id'] ?? 0);
        $acc_val  = $new_acc > 0 ? "'$new_acc'" : 'NULL';
        $new_dt   = $new_date.' '.date('H:i:s');
        mysqli_query($conn,"UPDATE transactions SET type='$new_type',category='$new_cat',amount='$new_amt',note='$new_note',account_id=$acc_val,transaction_date='$new_dt' WHERE id='$tid' AND client_id='$client_id'");
        // Apply new effect on account balance
        if($new_acc > 0){
            $apply = $new_type==='income' ? '+' : '-';
            mysqli_query($conn,"UPDATE accounts SET balance=balance{$apply}{$new_amt} WHERE id='$new_acc' AND client_id='$client_id'");
        }
    }
    header("Location: client_transactions.php?year=$view_year&month=$view_month_str&mode=$view_mode&date=".urlencode($new_date)."&cal=".($cal_hidden?'hide':'show'));
    exit();
}

// ── HANDLE: Delete transaction ──
if(isset($_GET['delete_tx'])){
    $tid = intval($_GET['delete_tx']);
    $old = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM transactions WHERE id='$tid' AND client_id='$client_id'"));
    if($old && $old['account_id']){
        $dir = $old['type']==='income' ? '-' : '+';
        mysqli_query($conn,"UPDATE accounts SET balance=balance{$dir}{$old['amount']} WHERE id='{$old['account_id']}'");
    }
    mysqli_query($conn,"DELETE FROM transactions WHERE id='$tid' AND client_id='$client_id'");
    header("Location: client_transactions.php?year=$view_year&month=$view_month_str&mode=$view_mode&date=".urlencode($selected_date)."&cal=".($cal_hidden?'hide':'show'));
    exit();
}

// ── FETCH ──
$acc_res  = mysqli_query($conn,"SELECT * FROM accounts WHERE client_id='$client_id' ORDER BY created_at ASC");
$accounts = [];
while($r = mysqli_fetch_assoc($acc_res)) $accounts[] = $r;

$cexp_res   = mysqli_query($conn,"SELECT * FROM client_categories WHERE client_id='$client_id' AND type='expense'");
$cinc_res   = mysqli_query($conn,"SELECT * FROM client_categories WHERE client_id='$client_id' AND type='income'");
$custom_exp = []; while($r = mysqli_fetch_assoc($cexp_res)) $custom_exp[] = $r;
$custom_inc = []; while($r = mysqli_fetch_assoc($cinc_res)) $custom_inc[] = $r;

$icons = ['transpo'=>'🚌','bills'=>'💡','food'=>'🍜','shopping'=>'🛍️','basic_needs'=>'🏠',
          'salary'=>'💰','freelance'=>'💻','business'=>'🏢','others'=>'📦'];

// ── TOTALS ──
$m_inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND YEAR(transaction_date)='$view_year' AND MONTH(transaction_date)='$view_month'"))['t'];
$m_exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND YEAR(transaction_date)='$view_year' AND MONTH(transaction_date)='$view_month'"))['t'];
$m_bal = $m_inc - $m_exp;
$d_inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income' AND DATE(transaction_date)='$selected_date'"))['t'];
$d_exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND DATE(transaction_date)='$selected_date'"))['t'];
$d_bal = $d_inc - $d_exp;
$sum_inc = $view_mode==='monthly' ? $m_inc : $d_inc;
$sum_exp = $view_mode==='monthly' ? $m_exp : $d_exp;
$sum_bal = $view_mode==='monthly' ? $m_bal : $d_bal;

// ── Per-day totals for calendar ──
$day_totals = [];
$dt_res = mysqli_query($conn,"SELECT DATE(transaction_date) AS d,
    COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS inc,
    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS exp
    FROM transactions WHERE client_id='$client_id'
    AND YEAR(transaction_date)='$view_year' AND MONTH(transaction_date)='$view_month'
    GROUP BY DATE(transaction_date)");
while($r = mysqli_fetch_assoc($dt_res)) $day_totals[$r['d']] = $r;

// ── Transaction list ──
if($view_mode === 'monthly'){
    $tx_res = mysqli_query($conn,"SELECT * FROM transactions WHERE client_id='$client_id'
        AND YEAR(transaction_date)='$view_year' AND MONTH(transaction_date)='$view_month'
        ORDER BY transaction_date DESC");
} else {
    $tx_res = mysqli_query($conn,"SELECT * FROM transactions WHERE client_id='$client_id'
        AND DATE(transaction_date)='$selected_date'
        ORDER BY transaction_date ASC");
}

// ── DRILL ──
$drill_type = isset($_GET['drill']) && in_array($_GET['drill'],['income','expense','balance']) ? $_GET['drill'] : null;
$drill_view = isset($_GET['dview']) && $_GET['dview']==='year' ? 'year' : 'month';
$drill_year = isset($_GET['dyear']) ? intval($_GET['dyear']) : $view_year;
$drill_rows=[]; $drill_totals=['income'=>0,'expense'=>0,'balance'=>0];
if($drill_type){
    $dt = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expense FROM transactions WHERE client_id='$client_id' AND YEAR(transaction_date)='$drill_year'"));
    $drill_totals=['income'=>(float)$dt['income'],'expense'=>(float)$dt['expense'],'balance'=>(float)$dt['income']-(float)$dt['expense']];
    if($drill_view==='month'){
        $dr=mysqli_query($conn,"SELECT MONTH(transaction_date) AS period, COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expense FROM transactions WHERE client_id='$client_id' AND YEAR(transaction_date)='$drill_year' GROUP BY MONTH(transaction_date) ORDER BY period DESC");
    } else {
        $dr=mysqli_query($conn,"SELECT YEAR(transaction_date) AS period, COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expense FROM transactions WHERE client_id='$client_id' GROUP BY YEAR(transaction_date) ORDER BY period DESC LIMIT 6");
    }
    while($r=mysqli_fetch_assoc($dr)) $drill_rows[]=$r;
}

$today_str     = date('Y-m-d');
$first_dow     = (int)date('N',strtotime($view_first))-1;
$days_in_month = (int)date('t',strtotime($view_first));
$month_names   = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

function txUrl($params=[]){
    global $view_year,$view_month_str,$view_mode,$selected_date,$cal_hidden,$drill_type,$drill_view,$drill_year;
    $base=['year'=>$view_year,'month'=>$view_month_str,'mode'=>$view_mode,'date'=>$selected_date,'cal'=>$cal_hidden?'hide':'show'];
    if($drill_type){$base['drill']=$drill_type;$base['dview']=$drill_view;$base['dyear']=$drill_year;}
    return 'client_transactions.php?'.http_build_query(array_merge($base,$params));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transactions | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<?php include 'base_styles.php'; ?>
<style>
.tx-controls{display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.view-toggle{display:flex;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:4px;gap:4px;}
.view-btn{padding:8px 20px;border-radius:9px;border:none;background:transparent;color:rgba(255,255,255,.45);font-size:12px;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:.5px;transition:.22s;text-decoration:none;display:inline-block;}
.view-btn:hover{color:#fff;background:rgba(255,255,255,.06);}
.view-btn.active{background:var(--green-main);color:#000;}
.month-nav{display:flex;align-items:center;gap:10px;margin-left:auto;}
.month-nav-btn{width:32px;height:32px;border-radius:9px;border:1px solid var(--border);background:rgba(255,255,255,.03);color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:.2s;}
.month-nav-btn:hover{border-color:var(--green-glow);background:rgba(46,204,113,.1);}
.month-label{font-size:15px;font-weight:800;min-width:110px;text-align:center;}
.eye-btn{width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:rgba(255,255,255,.03);color:rgba(255,255,255,.6);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:.2s;}
.eye-btn.open{border-color:var(--green-glow);color:var(--green-glow);background:rgba(46,204,113,.08);}
.calendar-wrap{margin-bottom:20px;}
.cal-header{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:4px;}
.cal-dow{text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.3);padding:6px 0;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;}
.cal-day{border-radius:12px;padding:8px 6px;text-align:center;border:1px solid transparent;transition:.22s;min-height:56px;display:flex;flex-direction:column;align-items:center;gap:2px;text-decoration:none;color:#fff;}
.cal-day:hover{border-color:rgba(46,204,113,.35);background:rgba(46,204,113,.06);}
.cal-day.selected{border-color:var(--green-glow);background:rgba(46,204,113,.14);box-shadow:0 0 14px rgba(46,204,113,.18);}
.cal-day.today .day-num{color:var(--green-glow);font-weight:800;}
.cal-day.other-month{opacity:.22;pointer-events:none;}
.day-num{font-size:14px;font-weight:700;}
.day-amounts{font-size:9px;line-height:1.4;}
.day-inc{color:var(--green-glow);}
.day-exp{color:var(--red);}
.summary-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:20px;}
.sum-card{border-radius:16px;padding:16px;text-align:center;cursor:pointer;transition:.25s;border:1px solid transparent;text-decoration:none;display:block;}
.sum-card:hover{transform:translateY(-2px);}
.sum-card.inc{background:rgba(46,204,113,.08);border-color:rgba(46,204,113,.18);}
.sum-card.exp{background:rgba(255,107,107,.06);border-color:rgba(255,107,107,.15);}
.sum-card.bal{background:rgba(93,173,226,.06);border-color:rgba(93,173,226,.15);}
.sum-card .lbl{font-size:10px;text-transform:uppercase;letter-spacing:1px;opacity:.6;margin-bottom:4px;}
.sum-card .val{font-size:1.3rem;font-weight:800;}
.sum-card.inc .val{color:var(--green-glow);}
.sum-card.exp .val{color:var(--red);}
.sum-card.bal .val{color:var(--blue);}
.sum-card.active-drill{box-shadow:0 0 0 2px var(--green-glow);}
.panel-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;}
.panel-title{font-size:14px;font-weight:800;color:var(--green-glow);}
.delete-tx-btn{padding:4px 10px;border-radius:8px;border:1px solid rgba(255,107,107,.25);background:transparent;color:rgba(255,107,107,.6);font-size:10px;cursor:pointer;text-decoration:none;transition:.2s;}
.delete-tx-btn:hover{background:rgba(255,107,107,.1);color:var(--red);}
.edit-tx-btn{padding:4px 10px;border-radius:8px;border:1px solid rgba(93,173,226,.25);background:transparent;color:rgba(93,173,226,.65);font-size:10px;cursor:pointer;text-decoration:none;transition:.2s;}
.edit-tx-btn:hover{background:rgba(93,173,226,.1);color:var(--blue);}

/* TYPE TOGGLE */
.type-toggle{display:flex;gap:8px;margin-bottom:18px;}
.type-btn{flex:1;padding:10px;border-radius:9px;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.5);font-size:12px;font-weight:700;cursor:pointer;transition:.22s;text-align:center;text-decoration:none;}
.type-btn.active{background:var(--green-main);color:#000;border-color:var(--green-main);}
.type-btn:hover:not(.active){background:rgba(255,255,255,.05);color:#fff;}

/* MULTI-ITEM ROWS */
.tx-items-wrap{margin-bottom:14px;}
.tx-item-row{display:flex;gap:10px;align-items:flex-start;padding:14px;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);
    border-radius:14px;margin-bottom:8px;position:relative;flex-wrap:wrap;}
.tx-item-row .form-group{margin-bottom:0;}
.tx-item-row .cat-col{flex:2;min-width:140px;}
.tx-item-row .amt-col{flex:1;min-width:100px;}
.tx-item-row .note-col{flex:2;min-width:130px;}
.remove-row-btn{background:none;border:none;color:rgba(255,107,107,.5);font-size:18px;
    cursor:pointer;padding:4px 6px;line-height:1;transition:.2s;align-self:flex-end;margin-bottom:2px;}
.remove-row-btn:hover{color:var(--red);}
.add-more-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;
    border-radius:12px;border:1px dashed rgba(46,204,113,.4);background:transparent;
    color:var(--green-glow);font-size:13px;font-weight:700;cursor:pointer;transition:.25s;
    width:100%;justify-content:center;margin-bottom:16px;}
.add-more-btn:hover{background:rgba(46,204,113,.08);border-color:var(--green-glow);}

/* DRILL */
.drill-panel{background:rgba(8,22,14,.95);border:1px solid rgba(46,204,113,.2);border-radius:20px;padding:24px;margin-bottom:20px;}
.drill-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;}
.drill-title{font-size:15px;font-weight:800;}
.drill-controls{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.drill-toggle{display:flex;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;padding:3px;gap:3px;}
.drill-btn{padding:7px 16px;border-radius:8px;border:none;background:transparent;color:rgba(255,255,255,.45);font-size:11px;font-weight:700;cursor:pointer;text-transform:uppercase;transition:.2s;text-decoration:none;}
.drill-btn.active{background:var(--green-main);color:#000;}
.drill-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px;}
.drill-stat{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:14px;text-align:center;}
.drill-stat .lbl{font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.5;margin-bottom:4px;}
.drill-stat .val{font-weight:800;font-size:1rem;}
.drill-close{padding:7px 16px;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:.2s;}
.drill-close:hover{border-color:var(--red);color:var(--red);}
.drill-table{width:100%;border-collapse:collapse;margin-top:8px;}
.drill-table th{font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.3);padding:10px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.06);}
.drill-table td{padding:11px 12px;border-bottom:1px solid rgba(255,255,255,.04);font-size:13px;}
.drill-table tr:last-child td{border-bottom:none;}
.drill-table .avg-row td{color:rgba(255,255,255,.4);font-style:italic;border-top:1px solid rgba(255,255,255,.08);border-bottom:none;}
.year-sel{background:rgba(255,255,255,.06);border:1px solid var(--border);color:#fff;padding:7px 12px;border-radius:10px;font-family:inherit;font-size:12px;outline:none;cursor:pointer;}
@media(max-width:700px){.summary-row{grid-template-columns:1fr;}.cal-day{min-height:44px;padding:6px 4px;}.day-num{font-size:12px;}.drill-stats{grid-template-columns:1fr;}.tx-item-row{flex-direction:column;}}
</style>
</head>
<body>
<?php include 'nav_include.php'; ?>
<div class="container">

    <div class="tx-controls">
        <div class="view-toggle">
            <a href="<?php echo txUrl(['mode'=>'daily','drill'=>null,'dview'=>null,'dyear'=>null]); ?>" class="view-btn <?php echo $view_mode==='daily'?'active':''; ?>">Daily</a>
            <a href="<?php echo txUrl(['mode'=>'monthly','drill'=>null,'dview'=>null,'dyear'=>null]); ?>" class="view-btn <?php echo $view_mode==='monthly'?'active':''; ?>">Monthly</a>
        </div>
        <div class="month-nav">
            <?php $py=($view_month==1)?$view_year-1:$view_year;$pm=($view_month==1)?12:$view_month-1;$ny=($view_month==12)?$view_year+1:$view_year;$nm=($view_month==12)?1:$view_month+1; ?>
            <a href="client_transactions.php?year=<?php echo $py; ?>&month=<?php echo str_pad($pm,2,'0',STR_PAD_LEFT); ?>&mode=<?php echo $view_mode; ?>&cal=<?php echo $cal_hidden?'hide':'show'; ?>" class="month-nav-btn">‹</a>
            <span class="month-label"><?php echo "$month_label $view_year"; ?></span>
            <a href="client_transactions.php?year=<?php echo $ny; ?>&month=<?php echo str_pad($nm,2,'0',STR_PAD_LEFT); ?>&mode=<?php echo $view_mode; ?>&cal=<?php echo $cal_hidden?'hide':'show'; ?>" class="month-nav-btn">›</a>
        </div>
        <a href="<?php echo txUrl(['cal'=>$cal_hidden?'show':'hide']); ?>" class="eye-btn <?php echo !$cal_hidden?'open':''; ?>"><?php echo !$cal_hidden?'👁':'🙈'; ?></a>
        <button class="btn-primary" onclick="openAddModal('expense')" style="padding:10px 18px;font-size:12px;">＋ Add</button>
    </div>

    <?php if(!$cal_hidden): ?>
    <div class="calendar-wrap">
        <div class="card-section" style="padding:18px;">
            <div class="cal-header">
                <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dn): ?><div class="cal-dow"><?php echo $dn; ?></div><?php endforeach; ?>
            </div>
            <div class="cal-grid">
                <?php
                for($b=0;$b<$first_dow;$b++){$pd=date('j',strtotime($view_first.' -'.($first_dow-$b).' days'));echo "<div class='cal-day other-month'><span class='day-num'>$pd</span></div>";}
                for($d=1;$d<=$days_in_month;$d++){
                    $ds="$view_year-$view_month_str-".str_pad($d,2,'0',STR_PAD_LEFT);
                    $cls=($ds===$today_str?'today ':'').($ds===$selected_date&&$view_mode==='daily'?'selected':'');
                    $dt=$day_totals[$ds]??null;
                    $il=($dt&&$dt['inc']>0)?'<div class="day-inc">+'.number_format($dt['inc'],0).'</div>':'';
                    $el=($dt&&$dt['exp']>0)?'<div class="day-exp">-'.number_format($dt['exp'],0).'</div>':'';
                    $url=txUrl(['date'=>$ds,'mode'=>'daily']);
                    echo "<a href='$url' class='cal-day $cls'><span class='day-num'>$d</span><div class='day-amounts'>$il$el</div></a>";
                }
                $trail=(7-(($first_dow+$days_in_month)%7))%7;
                for($t=1;$t<=$trail;$t++) echo "<div class='cal-day other-month'><span class='day-num'>$t</span></div>";
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="summary-row">
        <?php
        $slabels=$view_mode==='monthly'?['Month Income','Month Expense','Month Balance']:['Income','Expense','Balance'];
        $svals=[number_format($sum_inc,2),number_format($sum_exp,2),number_format($sum_bal,2)];
        $scls=['inc','exp','bal']; $sdrill=['income','expense','balance'];
        foreach($sdrill as $i=>$dk):
            $active=$drill_type===$dk?'active-drill':'';
            $durl=txUrl(['drill'=>$dk,'dview'=>$drill_view,'dyear'=>$drill_year]);
        ?>
        <a href="<?php echo $durl; ?>" class="sum-card <?php echo $scls[$i].' '.$active; ?>">
            <div class="lbl"><?php echo $slabels[$i]; ?></div>
            <div class="val">₱<?php echo $svals[$i]; ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if($drill_type): ?>
    <div class="drill-panel">
        <div class="drill-header">
            <div class="drill-title"><?php $dlbls=['income'=>'📈 Income','expense'=>'📉 Expense','balance'=>'⚖️ Balance']; echo $dlbls[$drill_type]; ?> — Breakdown</div>
            <div class="drill-controls">
                <div class="drill-toggle">
                    <a href="<?php echo txUrl(['drill'=>$drill_type,'dview'=>'month','dyear'=>$drill_year]); ?>" class="drill-btn <?php echo $drill_view==='month'?'active':''; ?>">Monthly</a>
                    <a href="<?php echo txUrl(['drill'=>$drill_type,'dview'=>'year','dyear'=>$drill_year]); ?>" class="drill-btn <?php echo $drill_view==='year'?'active':''; ?>">Yearly</a>
                </div>
                <?php if($drill_view==='month'): ?>
                <form method="GET" style="display:inline;">
                    <?php foreach(['year'=>$view_year,'month'=>$view_month_str,'mode'=>$view_mode,'date'=>$selected_date,'cal'=>$cal_hidden?'hide':'show','drill'=>$drill_type,'dview'=>'month'] as $k=>$v): ?>
                    <input type="hidden" name="<?php echo $k; ?>" value="<?php echo $v; ?>">
                    <?php endforeach; ?>
                    <select name="dyear" class="year-sel" onchange="this.form.submit()">
                        <?php for($y=date('Y');$y>=date('Y')-5;$y--): ?><option value="<?php echo $y; ?>" <?php echo $drill_year==$y?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?>
                    </select>
                </form>
                <?php endif; ?>
                <a href="<?php echo txUrl(['drill'=>null,'dview'=>null,'dyear'=>null]); ?>" class="drill-close">✕ Close</a>
            </div>
        </div>
        <div class="drill-stats">
            <div class="drill-stat"><div class="lbl">Income</div><div class="val" style="color:var(--green-glow);">₱<?php echo number_format($drill_totals['income'],2); ?></div></div>
            <div class="drill-stat"><div class="lbl">Expense</div><div class="val" style="color:var(--red);">₱<?php echo number_format($drill_totals['expense'],2); ?></div></div>
            <div class="drill-stat"><div class="lbl">Balance</div><div class="val" style="color:var(--blue);">₱<?php echo number_format($drill_totals['balance'],2); ?></div></div>
        </div>
        <table class="drill-table">
            <thead><tr><th><?php echo $drill_view==='month'?'Month':'Year'; ?></th><th>Income</th><th>Expense</th><th>Balance</th></tr></thead>
            <tbody>
            <?php $ti=0;$te=0;$cnt=count($drill_rows);
            foreach($drill_rows as $row):
                $lbl=$drill_view==='month'?$month_names[(int)$row['period']]:$row['period'];
                $bal=(float)$row['income']-(float)$row['expense'];
                $ti+=(float)$row['income'];$te+=(float)$row['expense'];
            ?>
            <tr><td style="font-weight:700;"><?php echo $lbl; ?></td><td style="color:var(--green-glow);">₱<?php echo number_format($row['income'],2); ?></td><td style="color:var(--red);">₱<?php echo number_format($row['expense'],2); ?></td><td style="color:var(--blue);">₱<?php echo number_format($bal,2); ?></td></tr>
            <?php endforeach;
            if($cnt>0):$ai=$ti/$cnt;$ae=$te/$cnt; ?>
            <tr class="avg-row"><td>Average</td><td>₱<?php echo number_format($ai,2); ?></td><td>₱<?php echo number_format($ae,2); ?></td><td>₱<?php echo number_format($ai-$ae,2); ?></td></tr>
            <?php endif; if($cnt===0): ?><tr><td colspan="4" style="text-align:center;color:rgba(255,255,255,.25);padding:20px;">No data found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="card-section">
        <div class="panel-header">
            <div class="panel-title">
                <?php if($view_mode==='monthly'): ?>📋 All Transactions — <?php echo "$month_label $view_year";
                else: ?>📅 <?php echo date('F j, Y',strtotime($selected_date)); endif; ?>
            </div>
            <button class="btn-primary" onclick="openAddModal('expense')" style="padding:8px 16px;font-size:12px;">＋ Add</button>
        </div>
        <?php if(mysqli_num_rows($tx_res)>0):
            while($tx=mysqli_fetch_assoc($tx_res)):
                $cat=strtolower($tx['category']); $icon=$icons[$cat]??'📦';
                $lbl=ucwords(str_replace('_',' ',$tx['category']));
                $dt=$view_mode==='monthly'?date('M j · g:i A',strtotime($tx['transaction_date'])):date('g:i A',strtotime($tx['transaction_date']));
                $sign=$tx['type']==='income'?'+':'−';
                $del=txUrl(['delete_tx'=>$tx['id']]);
        ?>
        <div class="transaction-item">
            <div class="trans-icon <?php echo $tx['type']; ?>"><?php echo $icon; ?></div>
            <div class="trans-info">
                <h4><?php echo $lbl; ?><?php if($tx['note']): ?> <span style="opacity:.4;font-weight:400;">— <?php echo htmlspecialchars($tx['note']); ?></span><?php endif; ?></h4>
                <span><?php echo $dt; ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <div class="trans-amount <?php echo $tx['type']; ?>"><?php echo $sign; ?>₱<?php echo number_format($tx['amount'],2); ?></div>
                <button class="edit-tx-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($tx)); ?>)">✏️</button>
                <a href="<?php echo $del; ?>" class="delete-tx-btn" onclick="return confirm('Delete this transaction?')">✕</a>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state"><span><?php echo $view_mode==='monthly'?'📭':'📅'; ?></span><?php echo $view_mode==='monthly'?'No transactions this month.':'No transactions on this date.'; ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- ADD TRANSACTION MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:560px;">
        <button class="modal-close" onclick="closeAddModal()">✕</button>
        <h3>＋ Add Transaction</h3>
        <form method="POST" action="<?php echo 'client_transactions.php?year='.$view_year.'&month='.$view_month_str.'&mode='.$view_mode.'&date='.urlencode($selected_date).'&cal='.($cal_hidden?'hide':'show'); ?>">
            <input type="hidden" name="save_transaction" value="1">
            <div class="type-toggle">
                <a href="#" class="type-btn active" id="typeBtnExp" onclick="setType('expense');return false;">💸 Expense</a>
                <a href="#" class="type-btn"        id="typeBtnInc" onclick="setType('income');return false;">💰 Income</a>
            </div>
            <input type="hidden" name="type" id="txType" value="expense">
            <div class="form-row" style="margin-bottom:14px;">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="tx_date" id="txDate" value="<?php echo $view_mode==='daily'?$selected_date:date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Account</label>
                    <select name="account_id">
                        <option value="0">— None —</option>
                        <?php foreach($accounts as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo $a['icon'].' '.htmlspecialchars($a['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- MULTI-ITEM ROWS -->
            <div class="tx-items-wrap" id="txItemsWrap">
                <!-- Row 1 (default) -->
                <div class="tx-item-row" id="txRow0">
                    <div class="form-group cat-col">
                        <label>Category</label>
                        <select name="category[]" class="cat-select" required>
                            <option value="">— Select —</option>
                        </select>
                    </div>
                    <div class="form-group amt-col">
                        <label>Amount (₱)</label>
                        <input type="number" name="amount[]" placeholder="0.00" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group note-col">
                        <label>Note <span style="opacity:.4;">(optional)</span></label>
                        <input type="text" name="note[]" placeholder="Description...">
                    </div>
                </div>
            </div>

            <button type="button" class="add-more-btn" id="addMoreBtn" onclick="addRow()">
                ＋ Add More
            </button>

            <button type="submit" class="btn-primary" style="width:100%;margin-top:4px;">💾 Save All</button>
        </form>
    </div>
</div>

<!-- EDIT TRANSACTION MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:520px;">
        <button class="modal-close" onclick="closeEditModal()">✕</button>
        <h3>✏️ Edit Transaction</h3>
        <form method="POST" action="<?php echo 'client_transactions.php?year='.$view_year.'&month='.$view_month_str.'&mode='.$view_mode.'&date='.urlencode($selected_date).'&cal='.($cal_hidden?'hide':'show'); ?>">
            <input type="hidden" name="update_transaction" value="1">
            <input type="hidden" name="edit_tx_id" id="editTxId">
            <div class="type-toggle" style="margin-bottom:18px;">
                <a href="#" class="type-btn active" id="editTypeBtnExp" onclick="setEditType('expense');return false;">💸 Expense</a>
                <a href="#" class="type-btn"         id="editTypeBtnInc" onclick="setEditType('income');return false;">💰 Income</a>
            </div>
            <input type="hidden" name="edit_type" id="editTxType" value="expense">
            <div class="form-row" style="margin-bottom:14px;">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="edit_date" id="editTxDate" required>
                </div>
                <div class="form-group">
                    <label>Account</label>
                    <select name="edit_account_id" id="editTxAccount">
                        <option value="0">— None —</option>
                        <?php foreach($accounts as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo $a['icon'].' '.htmlspecialchars($a['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row" style="margin-bottom:14px;">
                <div class="form-group">
                    <label>Category</label>
                    <select name="edit_category" id="editTxCategory" required>
                        <option value="">— Select —</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (₱)</label>
                    <input type="number" name="edit_amount" id="editTxAmount" placeholder="0.00" step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="form-group">
                <label>Note <span style="opacity:.4;">(optional)</span></label>
                <input type="text" name="edit_note" id="editTxNote" placeholder="Description...">
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="button" onclick="closeEditModal()" class="btn-outline" style="flex:1;">Cancel</button>
                <button type="submit" class="btn-primary" style="flex:2;">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const EXP_CUSTOM = <?php echo json_encode(array_map(function($c){return['key'=>$c['category_name'],'icon'=>$c['icon']];},$custom_exp)); ?>;
const INC_CUSTOM = <?php echo json_encode(array_map(function($c){return['key'=>$c['category_name'],'icon'=>$c['icon']];},$custom_inc)); ?>;
let currentType = 'expense';
let rowCount = 1;

function getCatOptions(t){
    const fixed = t==='expense'
        ? [['food','🍜 Food'],['transpo','🚌 Transportation'],['bills','💡 Bills'],['shopping','🛍️ Shopping'],['basic_needs','🏠 Basic Needs'],['others','📦 Others']]
        : [['salary','💰 Salary'],['freelance','💻 Freelance'],['business','🏢 Business'],['others','📦 Others']];
    const custom = t==='expense' ? EXP_CUSTOM : INC_CUSTOM;
    let html = '<option value="">— Select —</option><optgroup label="Fixed">';
    fixed.forEach(f => html += `<option value="${f[0]}">${f[1]}</option>`);
    html += '</optgroup>';
    if(custom.length){
        html += '<optgroup label="My Categories">';
        custom.forEach(c => html += `<option value="${c.key}">${c.icon} ${c.key}</option>`);
        html += '</optgroup>';
    }
    return html;
}

function setType(t){
    currentType = t;
    document.getElementById('txType').value = t;
    document.getElementById('typeBtnExp').classList.toggle('active', t==='expense');
    document.getElementById('typeBtnInc').classList.toggle('active', t==='income');
    // Rebuild all category selects
    document.querySelectorAll('.cat-select').forEach(s => s.innerHTML = getCatOptions(t));
}

function addRow(){
    const wrap = document.getElementById('txItemsWrap');
    const idx  = rowCount++;
    const row  = document.createElement('div');
    row.className = 'tx-item-row';
    row.id = 'txRow'+idx;
    row.innerHTML = `
        <div class="form-group cat-col">
            <label>Category</label>
            <select name="category[]" class="cat-select" required>${getCatOptions(currentType)}</select>
        </div>
        <div class="form-group amt-col">
            <label>Amount (₱)</label>
            <input type="number" name="amount[]" placeholder="0.00" step="0.01" min="0.01" required>
        </div>
        <div class="form-group note-col">
            <label>Note <span style="opacity:.4;">(optional)</span></label>
            <input type="text" name="note[]" placeholder="Description...">
        </div>
        <button type="button" class="remove-row-btn" onclick="removeRow('txRow${idx}')" title="Remove">✕</button>`;
    wrap.appendChild(row);
}

function removeRow(id){
    const row = document.getElementById(id);
    if(row) row.remove();
}

function openAddModal(type){
    document.getElementById('addModal').classList.add('active');
    setType(type || 'expense');
    // Reset to 1 row
    const wrap = document.getElementById('txItemsWrap');
    wrap.innerHTML = `<div class="tx-item-row" id="txRow0">
        <div class="form-group cat-col"><label>Category</label><select name="category[]" class="cat-select" required>${getCatOptions(currentType)}</select></div>
        <div class="form-group amt-col"><label>Amount (₱)</label><input type="number" name="amount[]" placeholder="0.00" step="0.01" min="0.01" required></div>
        <div class="form-group note-col"><label>Note <span style="opacity:.4;">(optional)</span></label><input type="text" name="note[]" placeholder="Description..."></div>
    </div>`;
    rowCount = 1;
}
function closeAddModal(){ document.getElementById('addModal').classList.remove('active'); }
document.getElementById('addModal').addEventListener('click',function(e){ if(e.target===this) closeAddModal(); });

// ── EDIT MODAL ──
function setEditType(t){
    document.getElementById('editTxType').value = t;
    document.getElementById('editTypeBtnExp').classList.toggle('active', t==='expense');
    document.getElementById('editTypeBtnInc').classList.toggle('active', t==='income');
    const sel = document.getElementById('editTxCategory');
    const cur = sel.value;
    sel.innerHTML = getCatOptions(t);
    // Restore selection if still valid
    if([...sel.options].some(o => o.value === cur)) sel.value = cur;
}

function openEditModal(tx){
    document.getElementById('editModal').classList.add('active');
    document.getElementById('editTxId').value      = tx.id;
    document.getElementById('editTxAmount').value  = tx.amount;
    document.getElementById('editTxNote').value    = tx.note || '';
    // Date: extract YYYY-MM-DD from timestamp
    document.getElementById('editTxDate').value    = tx.transaction_date.substring(0,10);
    // Account
    const accSel = document.getElementById('editTxAccount');
    accSel.value = tx.account_id || 0;
    // Type + category
    setEditType(tx.type);
    const catSel = document.getElementById('editTxCategory');
    catSel.value = tx.category;
}

function closeEditModal(){ document.getElementById('editModal').classList.remove('active'); }
document.getElementById('editModal').addEventListener('click',function(e){ if(e.target===this) closeEditModal(); });

// Init first row options
setType('expense');
</script>
</body>
</html>