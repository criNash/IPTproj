<?php
/**
 * notifications_engine.php
 * Included by nav_include.php on every page.
 * Generates smart notifications based on user data, then fetches them.
 * Notifications pile up — no dedup. Latest always on top.
 */

if(!function_exists('generate_notifications')){
function generate_notifications($conn, $client_id){

    // Helper: always insert a new notification (no dedup — they pile up)
    // Guard against redeclaration fatal error (PHP inner functions are global)
    if(!function_exists('push_notif')){
        function push_notif($conn, $client_id, $title, $message, $type){
            $t = mysqli_real_escape_string($conn, $title);
            $m = mysqli_real_escape_string($conn, $message);
            $y = mysqli_real_escape_string($conn, $type);
            mysqli_query($conn,
                "INSERT INTO notifications (client_id,title,message,type)
                 VALUES ('$client_id','$t','$m','$y')");
        }
    }

    // ── 1. BUDGET WARNINGS ──────────────────────────────────────────
    $date_conds = [
        'daily'   => "DATE(transaction_date)=CURDATE()",
        'weekly'  => "DATE(transaction_date) BETWEEN '".date('Y-m-d',strtotime('monday this week'))."' AND '".date('Y-m-d',strtotime('sunday this week'))."'",
        'monthly' => "MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())",
        'yearly'  => "YEAR(transaction_date)=YEAR(NOW())",
    ];

    $budgets = mysqli_query($conn,
        "SELECT * FROM budgets WHERE client_id='$client_id'");
    while($b = mysqli_fetch_assoc($budgets)){
        $cat   = mysqli_real_escape_string($conn, $b['category']);
        $cond  = $date_conds[$b['period']];
        $spent = (float)mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(amount),0) AS s FROM transactions
             WHERE client_id='$client_id' AND type='expense'
             AND LOWER(category)=LOWER('$cat') AND $cond"))['s'];
        $limit = (float)$b['amount_limit'];
        $pct   = $limit > 0 ? ($spent / $limit) * 100 : 0;
        $lbl   = ucwords(str_replace('_',' ',$b['category']));
        $per   = ucfirst($b['period']);

        if($pct >= 100){
            push_notif($conn, $client_id,
                "🚨 Over Budget: $lbl",
                "You have exceeded your $per budget for $lbl. Spent ₱".number_format($spent,2)." of ₱".number_format($limit,2)." limit.",
                'danger');
        } elseif($pct >= 90){
            push_notif($conn, $client_id,
                "⚠️ 90% Budget Used: $lbl",
                "You've used 90% of your $per $lbl budget. Only ₱".number_format($limit-$spent,2)." remaining.",
                'danger');
        } elseif($pct >= 80){
            push_notif($conn, $client_id,
                "⚠️ 80% Budget Reached: $lbl",
                "You've reached 80% of your $per $lbl budget. Spent ₱".number_format($spent,2)." of ₱".number_format($limit,2).".",
                'warning');
        } elseif($pct >= 50){
            push_notif($conn, $client_id,
                "📊 Halfway There: $lbl",
                "You're 50% through your $per $lbl budget. ₱".number_format($limit-$spent,2)." left to spend.",
                'info');
        }
    }

    // ── 2. NO TRANSACTIONS TODAY ──────────────────────────────────────
    $today_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM transactions
         WHERE client_id='$client_id' AND DATE(transaction_date)=CURDATE()"))['c'];
    if($today_count === 0){
        push_notif($conn, $client_id,
            "📝 No Entries Today",
            "You haven't logged any transactions today. Keep your records up to date!",
            'info');
    }

    // ── 3. MONTHLY EXPENSE SPIKE ─────────────────────────────────────
    $this_month = (float)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(amount),0) AS t FROM transactions
         WHERE client_id='$client_id' AND type='expense'
         AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())"))['t'];
    $last_month = (float)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(amount),0) AS t FROM transactions
         WHERE client_id='$client_id' AND type='expense'
         AND MONTH(transaction_date)=MONTH(NOW()-INTERVAL 1 MONTH)
         AND YEAR(transaction_date)=YEAR(NOW()-INTERVAL 1 MONTH)"))['t'];
    if($last_month > 0 && $this_month > $last_month * 1.3){
        push_notif($conn, $client_id,
            "📈 Spending Up 30% vs Last Month",
            "Your expenses this month (₱".number_format($this_month,2).") are more than 30% higher than last month (₱".number_format($last_month,2)."). Consider reviewing your spending.",
            'warning');
    }

    // ── 4. EXPENSES EXCEED INCOME THIS MONTH ─────────────────────────
    $m_inc = (float)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(amount),0) AS t FROM transactions
         WHERE client_id='$client_id' AND type='income'
         AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())"))['t'];
    if($m_inc > 0 && $this_month > $m_inc){
        push_notif($conn, $client_id,
            "🔴 Expenses Exceed Income This Month",
            "Your expenses (₱".number_format($this_month,2).") have exceeded your income (₱".number_format($m_inc,2).") this month. Your balance is -₱".number_format($this_month-$m_inc,2).".",
            'danger');
    }

    // ── 5. NO INCOME LOGGED THIS MONTH ───────────────────────────────
    if($m_inc == 0 && date('j') >= 5){
        push_notif($conn, $client_id,
            "💰 No Income Recorded This Month",
            "You haven't logged any income for ".date('F')." yet. Don't forget to track your earnings!",
            'info');
    }

    // ── 6. HIGH SINGLE EXPENSE TODAY ─────────────────────────────────
    $big_tx = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT amount, category FROM transactions
         WHERE client_id='$client_id' AND type='expense'
         AND DATE(transaction_date)=CURDATE()
         ORDER BY amount DESC LIMIT 1"));
    if($big_tx && (float)$big_tx['amount'] >= 5000){
        $lbl = ucwords(str_replace('_',' ',$big_tx['category']));
        push_notif($conn, $client_id,
            "💸 Large Expense Logged Today",
            "You logged a large expense of ₱".number_format($big_tx['amount'],2)." under $lbl today.",
            'warning');
    }

    // ── 7. SAVINGS MILESTONE ─────────────────────────────────────────
    $net = (float)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(balance),0) AS nw FROM accounts WHERE client_id='$client_id'"))['nw'];
    foreach([1000,5000,10000,25000,50000,100000] as $milestone){
        if($net >= $milestone){
            push_notif($conn, $client_id,
                "🎉 Net Worth Milestone: ₱".number_format($milestone,0),
                "Congratulations! Your total net worth has reached ₱".number_format($milestone,0).". Keep it up!",
                'success');
            break;
        }
    }

    // ── 8. WEEKLY SPENDING SUMMARY (every Monday) ────────────────────
    if(date('N') == 1){
        $week_exp = (float)mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(amount),0) AS t FROM transactions
             WHERE client_id='$client_id' AND type='expense'
             AND DATE(transaction_date) BETWEEN '".date('Y-m-d',strtotime('last monday'))."'
             AND '".date('Y-m-d',strtotime('last sunday'))."'"))['t'];
        if($week_exp > 0){
            push_notif($conn, $client_id,
                "📅 Last Week's Spending: ₱".number_format($week_exp,2),
                "You spent a total of ₱".number_format($week_exp,2)." last week. Review your transactions to stay on track.",
                'info');
        }
    }

    // ── 9. ACCOUNT LOW BALANCE ───────────────────────────────────────
    $low_accs = mysqli_query($conn,
        "SELECT account_name, balance FROM accounts
         WHERE client_id='$client_id' AND balance > 0 AND balance < 500");
    while($a = mysqli_fetch_assoc($low_accs)){
        push_notif($conn, $client_id,
            "⚡ Low Balance: ".htmlspecialchars($a['account_name']),
            "Your ".htmlspecialchars($a['account_name'])." account balance is low at ₱".number_format($a['balance'],2).". Consider topping it up.",
            'warning');
    }

    // ── 10. FIRST TRANSACTION WELCOME ────────────────────────────────
    $total_tx = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM transactions WHERE client_id='$client_id'"))['c'];
    if($total_tx === 1){
        push_notif($conn, $client_id,
            "🌟 Welcome to Budget Supreme!",
            "You've logged your first transaction. Great start! Keep tracking to gain full insights into your finances.",
            'success');
    }
}

} // end if(!function_exists)

// ── RUN GENERATOR ──
generate_notifications($conn, $client_id);

// ── HANDLE: Mark all as read via AJAX ──
// Called silently by JS when the bell is opened
if(isset($_GET['notif_read']) && $_GET['notif_read'] == '1'){
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE client_id='$client_id'");
    exit();
}

// ── FETCH: Any unread? (just true/false for the red dot) ──
$notif_unread = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM notifications WHERE client_id='$client_id' AND is_read=0"))['c'];

// ── FETCH: Latest 20 notifications, newest first ──
$notif_res = mysqli_query($conn,
    "SELECT * FROM notifications WHERE client_id='$client_id'
     ORDER BY created_at DESC LIMIT 20");
$notifications = [];
while($r = mysqli_fetch_assoc($notif_res)) $notifications[] = $r;
?>
