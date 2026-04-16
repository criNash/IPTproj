<?php
session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id = $_SESSION['client_id'];

// ── PARAMS ──
$period     = isset($_GET['period']) && in_array($_GET['period'],['daily','weekly','monthly','yearly']) ? $_GET['period'] : 'monthly';
$graph_type = isset($_GET['graph'])  && in_array($_GET['graph'],['expense','income','balance','all'])   ? $_GET['graph']  : 'all';
$format     = isset($_GET['format']) && $_GET['format'] === 'excel' ? 'excel' : 'csv';

// ── FETCH CLIENT NAME ──
$user_data = mysqli_fetch_assoc(mysqli_query($conn,"SELECT first_name, last_name FROM clients WHERE id='$client_id'"));
$full_name = trim($user_data['first_name'].' '.$user_data['last_name']) ?: 'Client';

// ── BUILD PERIOD DATA (same logic as client_graphs.php) ──
$rows   = [];  // [ [label, income, expense, balance] ]
$today  = date('Y-m-d');

if($period === 'daily'){
    for($i = 29; $i >= 0; $i--){
        $d   = date('Y-m-d', strtotime("-$i days"));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income'  AND DATE(transaction_date)='$d'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND DATE(transaction_date)='$d'"))['t'];
        $rows[] = [date('M j, Y', strtotime($d)), $inc, $exp, $inc - $exp];
    }
} elseif($period === 'weekly'){
    for($i = 11; $i >= 0; $i--){
        $ws  = date('Y-m-d', strtotime("monday -$i weeks"));
        $we  = date('Y-m-d', strtotime("sunday -$i weeks"));
        $lbl = 'Week of '.date('M j', strtotime($ws));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income'  AND DATE(transaction_date) BETWEEN '$ws' AND '$we'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND DATE(transaction_date) BETWEEN '$ws' AND '$we'"))['t'];
        $rows[] = [$lbl, $inc, $exp, $inc - $exp];
    }
} elseif($period === 'monthly'){
    for($i = 11; $i >= 0; $i--){
        $mo  = date('n', strtotime("-$i months"));
        $yr  = date('Y', strtotime("-$i months"));
        $lbl = date('F Y', strtotime("-$i months"));
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income'  AND MONTH(transaction_date)='$mo' AND YEAR(transaction_date)='$yr'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND MONTH(transaction_date)='$mo' AND YEAR(transaction_date)='$yr'"))['t'];
        $rows[] = [$lbl, $inc, $exp, $inc - $exp];
    }
} else { // yearly
    $current_yr = (int)date('Y');
    for($i = 5; $i >= 0; $i--){
        $yr  = $current_yr - $i;
        $inc = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='income'  AND YEAR(transaction_date)='$yr'"))['t'];
        $exp = (float)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) AS t FROM transactions WHERE client_id='$client_id' AND type='expense' AND YEAR(transaction_date)='$yr'"))['t'];
        $rows[] = [(string)$yr, $inc, $exp, $inc - $exp];
    }
}

// ── FETCH CATEGORY BREAKDOWN ──
$period_map = [
    'daily'   => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
    'weekly'  => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)",
    'monthly' => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
    'yearly'  => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 6 YEAR)",
];
$where    = $period_map[$period];
$cat_res  = mysqli_query($conn,"SELECT type, category, SUM(amount) AS total FROM transactions WHERE client_id='$client_id' AND $where GROUP BY type, category ORDER BY type, total DESC");
$cat_rows = [];
while($r = mysqli_fetch_assoc($cat_res)) $cat_rows[] = $r;

// ── SUMMARY STATS ──
$total_inc = array_sum(array_column($rows, 1));
$total_exp = array_sum(array_column($rows, 2));
$total_bal = $total_inc - $total_exp;
$avg_inc   = count($rows) > 0 ? $total_inc / count($rows) : 0;
$avg_exp   = count($rows) > 0 ? $total_exp / count($rows) : 0;

$period_label_map = ['daily'=>'Daily (Last 30 Days)','weekly'=>'Weekly (Last 12 Weeks)','monthly'=>'Monthly (Last 12 Months)','yearly'=>'Yearly (Last 6 Years)'];
$generated_at     = date('F j, Y \a\t g:i A');
$filename_date    = date('Y-m-d');
$period_slug      = str_replace(' ','_', strtolower($period));
$filename         = "BudgetSupreme_{$period_slug}_report_{$filename_date}";

// ══════════════════════════════════════════════
//  CSV OUTPUT
// ══════════════════════════════════════════════
if($format === 'csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $out = fopen('php://output','w');

    // Meta header
    fputcsv($out, ['Budget Supreme — Financial Report']);
    fputcsv($out, ['Client',  $full_name]);
    fputcsv($out, ['Period',  $period_label_map[$period]]);
    fputcsv($out, ['Generated', $generated_at]);
    fputcsv($out, []);

    // Summary
    fputcsv($out, ['=== SUMMARY ===']);
    fputcsv($out, ['Total Income',   '₱'.number_format($total_inc,2)]);
    fputcsv($out, ['Total Expenses', '₱'.number_format($total_exp,2)]);
    fputcsv($out, ['Net Balance',    '₱'.number_format($total_bal,2)]);
    fputcsv($out, ['Avg Income',     '₱'.number_format($avg_inc,2)]);
    fputcsv($out, ['Avg Expenses',   '₱'.number_format($avg_exp,2)]);
    fputcsv($out, []);

    // Period breakdown
    fputcsv($out, ['=== PERIOD BREAKDOWN ===']);
    fputcsv($out, ['Period','Income (₱)','Expenses (₱)','Net Balance (₱)']);
    foreach($rows as $r){
        fputcsv($out, [$r[0], number_format($r[1],2), number_format($r[2],2), number_format($r[3],2)]);
    }
    fputcsv($out, []);

    // Category breakdown
    fputcsv($out, ['=== CATEGORY BREAKDOWN ===']);
    fputcsv($out, ['Type','Category','Total (₱)']);
    foreach($cat_rows as $cr){
        fputcsv($out, [ucfirst($cr['type']), $cr['category'], number_format($cr['total'],2)]);
    }
    fputcsv($out, []);

    // ── RAW TRANSACTIONS (importable) ──
    fputcsv($out, ['=== RAW TRANSACTIONS ===']);
    fputcsv($out, ['type','category','amount','date','note']);
    $period_map_tx = [
        'daily'   => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        'weekly'  => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)",
        'monthly' => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
        'yearly'  => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 6 YEAR)",
    ];
    $tx_res2 = mysqli_query($conn,"SELECT type, category, amount, DATE(transaction_date) AS date, note FROM transactions WHERE client_id='$client_id' AND {$period_map_tx[$period]} ORDER BY transaction_date DESC");
    while($tx = mysqli_fetch_assoc($tx_res2)){
        fputcsv($out, [$tx['type'], $tx['category'], $tx['amount'], $tx['date'], $tx['note'] ?? '']);
    }

    fclose($out);
    exit();
}

// ══════════════════════════════════════════════
//  EXCEL OUTPUT  (SpreadsheetML / .xls)
// ══════════════════════════════════════════════
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'.xls"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Colors
$green  = '#1F9D63';
$red    = '#FF6B6B';
$blue   = '#5DADE2';
$dark   = '#1A2332';
$mid    = '#243040';
$light  = '#E8F5EE';
$white  = '#FFFFFF';

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:html="http://www.w3.org/TR/REC-html40">

<Styles>
  <Style ss:ID="title">
    <Font ss:Bold="1" ss:Size="16" ss:Color="<?php echo $white; ?>"/>
    <Interior ss:Color="<?php echo $green; ?>" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="meta_label">
    <Font ss:Bold="1" ss:Size="10" ss:Color="#AAAAAA"/>
    <Interior ss:Color="<?php echo $dark; ?>" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Right"/>
  </Style>
  <Style ss:ID="meta_val">
    <Font ss:Size="10" ss:Color="<?php echo $white; ?>"/>
    <Interior ss:Color="<?php echo $dark; ?>" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="section_header">
    <Font ss:Bold="1" ss:Size="11" ss:Color="<?php echo $white; ?>"/>
    <Interior ss:Color="<?php echo $mid; ?>" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="<?php echo $green; ?>"/>
    </Borders>
  </Style>
  <Style ss:ID="col_header">
    <Font ss:Bold="1" ss:Size="10" ss:Color="<?php echo $white; ?>"/>
    <Interior ss:Color="#2D3F55" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="<?php echo $green; ?>"/>
    </Borders>
  </Style>
  <Style ss:ID="row_even">
    <Font ss:Size="10" ss:Color="<?php echo $white; ?>"/>
    <Interior ss:Color="#1E2D3D" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="row_odd">
    <Font ss:Size="10" ss:Color="<?php echo $white; ?>"/>
    <Interior ss:Color="#243040" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="money_green">
    <Font ss:Bold="1" ss:Size="10" ss:Color="<?php echo $green; ?>"/>
    <Interior ss:Color="#1E2D3D" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Right"/>
    <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="money_red">
    <Font ss:Bold="1" ss:Size="10" ss:Color="<?php echo $red; ?>"/>
    <Interior ss:Color="#1E2D3D" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Right"/>
    <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="money_blue">
    <Font ss:Bold="1" ss:Size="10" ss:Color="<?php echo $blue; ?>"/>
    <Interior ss:Color="#1E2D3D" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Right"/>
    <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="money_right">
    <Font ss:Size="10" ss:Color="<?php echo $white; ?>"/>
    <Interior ss:Color="#1E2D3D" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Right"/>
    <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="summary_label">
    <Font ss:Bold="1" ss:Size="10" ss:Color="rgba(255,255,255,.6)"/>
    <Interior ss:Color="<?php echo $mid; ?>" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="blank">
    <Interior ss:Color="<?php echo $dark; ?>" ss:Pattern="Solid"/>
  </Style>
</Styles>

<Worksheet ss:Name="Financial Report">
<Table ss:DefaultColumnWidth="100">
  <Column ss:Width="160"/>
  <Column ss:Width="130"/>
  <Column ss:Width="130"/>
  <Column ss:Width="130"/>

  <!-- TITLE ROW -->
  <Row ss:Height="36">
    <Cell ss:MergeAcross="3" ss:StyleID="title">
      <Data ss:Type="String">📊 Budget Supreme — Financial Report</Data>
    </Cell>
  </Row>

  <!-- META -->
  <Row><Cell ss:StyleID="meta_label"><Data ss:Type="String">Client:</Data></Cell><Cell ss:StyleID="meta_val"><Data ss:Type="String"><?php echo htmlspecialchars($full_name); ?></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell></Row>
  <Row><Cell ss:StyleID="meta_label"><Data ss:Type="String">Period:</Data></Cell><Cell ss:StyleID="meta_val"><Data ss:Type="String"><?php echo $period_label_map[$period]; ?></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell></Row>
  <Row><Cell ss:StyleID="meta_label"><Data ss:Type="String">Generated:</Data></Cell><Cell ss:StyleID="meta_val"><Data ss:Type="String"><?php echo $generated_at; ?></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell></Row>
  <Row><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell></Row>

  <!-- SUMMARY SECTION -->
  <Row ss:Height="28">
    <Cell ss:MergeAcross="3" ss:StyleID="section_header"><Data ss:Type="String">  SUMMARY</Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Metric</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Amount (₱)</Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="row_even"><Data ss:Type="String">Total Income</Data></Cell>
    <Cell ss:StyleID="money_green"><Data ss:Type="Number"><?php echo $total_inc; ?></Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="row_odd"><Data ss:Type="String">Total Expenses</Data></Cell>
    <Cell ss:StyleID="money_red"><Data ss:Type="Number"><?php echo $total_exp; ?></Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="row_even"><Data ss:Type="String">Net Balance</Data></Cell>
    <Cell ss:StyleID="money_blue"><Data ss:Type="Number"><?php echo $total_bal; ?></Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="row_odd"><Data ss:Type="String">Average Income</Data></Cell>
    <Cell ss:StyleID="money_right"><Data ss:Type="Number"><?php echo round($avg_inc,2); ?></Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="row_even"><Data ss:Type="String">Average Expenses</Data></Cell>
    <Cell ss:StyleID="money_right"><Data ss:Type="Number"><?php echo round($avg_exp,2); ?></Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <Row><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell></Row>

  <!-- PERIOD BREAKDOWN -->
  <Row ss:Height="28">
    <Cell ss:MergeAcross="3" ss:StyleID="section_header"><Data ss:Type="String">  PERIOD BREAKDOWN</Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Period</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Income (₱)</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Expenses (₱)</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Net Balance (₱)</Data></Cell>
  </Row>
  <?php foreach($rows as $i => $r): $s = $i%2===0?'row_even':'row_odd'; ?>
  <Row>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($r[0]); ?></Data></Cell>
    <Cell ss:StyleID="money_green"><Data ss:Type="Number"><?php echo $r[1]; ?></Data></Cell>
    <Cell ss:StyleID="money_red"><Data ss:Type="Number"><?php echo $r[2]; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $r[3]>=0?'money_green':'money_red'; ?>"><Data ss:Type="Number"><?php echo $r[3]; ?></Data></Cell>
  </Row>
  <?php endforeach; ?>
  <Row><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell></Row>

  <!-- CATEGORY BREAKDOWN -->
  <Row ss:Height="28">
    <Cell ss:MergeAcross="3" ss:StyleID="section_header"><Data ss:Type="String">  CATEGORY BREAKDOWN</Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Type</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Category</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Total (₱)</Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <?php foreach($cat_rows as $i => $cr): $s = $i%2===0?'row_even':'row_odd'; $ms = $cr['type']==='income'?'money_green':'money_red'; ?>
  <Row>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="String"><?php echo ucfirst(htmlspecialchars($cr['type'])); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($cr['category']); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $ms; ?>"><Data ss:Type="Number"><?php echo $cr['total']; ?></Data></Cell>
    <Cell ss:StyleID="blank"><Data ss:Type="String"></Data></Cell>
  </Row>
  <?php endforeach; ?>

</Table>
</Worksheet>

<Worksheet ss:Name="Raw Transactions">
<Table ss:DefaultColumnWidth="110">
  <Column ss:Width="60"/>
  <Column ss:Width="90"/>
  <Column ss:Width="90"/>
  <Column ss:Width="130"/>
  <Column ss:Width="120"/>
  <Column ss:Width="200"/>

  <Row ss:Height="28">
    <Cell ss:MergeAcross="5" ss:StyleID="section_header"><Data ss:Type="String">  ALL TRANSACTIONS — <?php echo $period_label_map[$period]; ?></Data></Cell>
  </Row>
  <Row>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">ID</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Date</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Type</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Category</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Amount (₱)</Data></Cell>
    <Cell ss:StyleID="col_header"><Data ss:Type="String">Note</Data></Cell>
  </Row>
  <?php
  $period_map2 = [
      'daily'   => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
      'weekly'  => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)",
      'monthly' => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
      'yearly'  => "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 6 YEAR)",
  ];
  $tx_res = mysqli_query($conn,"SELECT id, transaction_date, type, category, amount, note FROM transactions WHERE client_id='$client_id' AND {$period_map2[$period]} ORDER BY transaction_date DESC");
  $tx_i = 0;
  while($tx = mysqli_fetch_assoc($tx_res)):
      $s  = $tx_i%2===0?'row_even':'row_odd';
      $ms = $tx['type']==='income'?'money_green':'money_red';
      $tx_i++;
  ?>
  <Row>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="Number"><?php echo $tx['id']; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="String"><?php echo date('M j, Y', strtotime($tx['transaction_date'])); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="String"><?php echo ucfirst($tx['type']); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($tx['category']); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $ms; ?>"><Data ss:Type="Number"><?php echo $tx['amount']; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $s; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($tx['note'] ?? ''); ?></Data></Cell>
  </Row>
  <?php endwhile; ?>
</Table>
</Worksheet>

</Workbook>
<?php exit(); ?>
