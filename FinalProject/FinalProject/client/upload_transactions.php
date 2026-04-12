<?php
// Show errors as HTML instead of blank page (remove or set to 0 in production)
@ini_set('display_errors', 1);
@error_reporting(E_ALL);

session_start();
include 'dbconnect.php';
if(!isset($_SESSION['client_user'])){ header("Location: index.php"); exit(); }

$client_id  = $_SESSION['client_id'];
$username   = $_SESSION['client_user'];
$user_query = mysqli_query($conn,"SELECT first_name, last_name FROM clients WHERE id='$client_id'");
$user_data  = mysqli_fetch_assoc($user_query);
$active_nav = 'transactions';

$results  = array();
$errors   = array();
$imported = 0;
$skipped  = 0;

// ── Helper: get cell value with column name aliases ─────────────────────────
function get_col($row, $names, $default=''){
    foreach($names as $n){
        if(isset($row[$n]) && trim($row[$n]) !== '') return trim($row[$n]);
    }
    return $default;
}

// ── Parse CSV ──────────────────────────────────────────────────────────────
function is_tx_header($row){
    $r = array_map('strtolower', array_map('trim', $row));
    return in_array('type', $r) && (in_array('category', $r) || in_array('amount', $r));
}

function parse_csv($tmp){
    $rows         = array();
    $header       = null;
    $header_count = 0;
    $handle = fopen($tmp, 'r');
    if(!$handle) return $rows;

    while(($line = fgetcsv($handle, 2000, ',')) !== false){
        // Sanitize nulls
        $line = array_map(function($v){ return $v === null ? '' : $v; }, $line);
        // Skip fully blank lines
        if(count(array_filter($line, function($v){ return trim($v) !== ''; })) === 0) continue;

        if($header === null){
            // Haven't found the header yet — check if this row is a transaction header
            if(is_tx_header($line)){
                $header       = array_map('strtolower', array_map('trim', $line));
                $header_count = count($header);
            }
            // Either way, skip to next line (header row is not a data row)
            continue;
        }

        // We have a header. Check if this line is a new section marker — if so, reset
        $first = trim($line[0]);
        if(strpos($first, '===') !== false){
            $header = null;
            $header_count = 0;
            continue;
        }

        // Skip rows with fewer than 2 non-empty values
        if(count(array_filter($line, function($v){ return trim($v) !== ''; })) < 2) continue;

        // Align to header length
        $padded = array_pad($line, $header_count, '');
        if(count($padded) > $header_count) $padded = array_slice($padded, 0, $header_count);
        $rows[] = array_combine($header, $padded);
    }
    fclose($handle);
    return $rows;
}

// ── Parse XLSX via ZIP+XML ─────────────────────────────────────────────────
function col_letter_to_index($ref){
    // Extract column letters from cell ref like "A1", "BC3"
    preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
    if(empty($m[1])) return 0;
    $letters = $m[1];
    $col = 0;
    for($i = 0; $i < strlen($letters); $i++){
        $col = $col * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $col - 1; // 0-based
}

function xlsx_serial_to_date($serial){
    // Excel date serial to Y-m-d string
    if(!is_numeric($serial) || $serial < 1) return (string)$serial;
    $unix = ($serial - 25569) * 86400;
    return date('Y-m-d', $unix);
}

function parse_xlsx($tmp){
    if(!class_exists('ZipArchive')){
        return array('__error__' => 'XLSX not supported on this server. Please upload a .csv file instead.');
    }
    $zip = new ZipArchive();
    if($zip->open($tmp) !== true){
        return array('__error__' => 'Could not open XLSX file.');
    }
    // Shared strings
    $strings = array();
    $ssXml   = $zip->getFromName('xl/sharedStrings.xml');
    if($ssXml){
        $ssDom = new DOMDocument();
        @$ssDom->loadXML($ssXml);
        foreach($ssDom->getElementsByTagName('si') as $si) $strings[] = $si->textContent;
    }
    // Styles — detect date-formatted cells (numFmtId 14-17 or custom date patterns)
    $date_style_ids = array();
    $stylesXml = $zip->getFromName('xl/styles.xml');
    if($stylesXml){
        $stDom = new DOMDocument();
        @$stDom->loadXML($stylesXml);
        $numFmts = array();
        foreach($stDom->getElementsByTagName('numFmt') as $nf){
            $id  = (int)$nf->getAttribute('numFmtId');
            $fmt = strtolower($nf->getAttribute('formatCode'));
            if(strpos($fmt,'y')!==false || strpos($fmt,'d')!==false){
                $numFmts[$id] = true;
            }
        }
        $xfList = $stDom->getElementsByTagName('xf');
        foreach($xfList as $idx => $xf){
            $numFmtId = (int)$xf->getAttribute('numFmtId');
            // Built-in date formats: 14-17, 22, 164+ custom if detected above
            if(($numFmtId >= 14 && $numFmtId <= 17) || $numFmtId == 22 || isset($numFmts[$numFmtId])){
                $date_style_ids[$idx] = true;
            }
        }
    }
    $shXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if(!$shXml) return array('__error__' => 'Could not read sheet data from XLSX.');

    $shDom = new DOMDocument();
    @$shDom->loadXML($shXml);
    $rows   = array();
    $header = null;
    $header_count = 0;

    foreach($shDom->getElementsByTagName('row') as $rowEl){
        // Use a sparse array keyed by column index to handle gaps
        $sparse = array();
        foreach($rowEl->getElementsByTagName('c') as $c){
            $ref   = $c->getAttribute('r');   // e.g. "B3"
            $t     = $c->getAttribute('t');   // type: s=string, b=bool, n/''=number
            $s     = (int)$c->getAttribute('s'); // style index
            $vEl   = $c->getElementsByTagName('v');
            $raw   = ($vEl->length > 0) ? $vEl->item(0)->textContent : '';
            // Inline string
            $isEl  = $c->getElementsByTagName('is');
            if($isEl->length > 0) $raw = $isEl->item(0)->textContent;

            if($t === 's'){
                // Shared string
                $val = isset($strings[(int)$raw]) ? $strings[(int)$raw] : '';
            } elseif($t === 'b'){
                $val = $raw ? 'TRUE' : 'FALSE';
            } elseif($raw !== ''){
                // Number or date
                if(isset($date_style_ids[$s]) && is_numeric($raw)){
                    $val = xlsx_serial_to_date($raw);
                } else {
                    $val = $raw; // plain number or formula result
                }
            } else {
                $val = '';
            }
            $col_idx = col_letter_to_index($ref);
            $sparse[$col_idx] = $val;
        }

        if(empty($sparse)) continue;
        $max_col = max(array_keys($sparse));
        // Fill gaps
        $cells = array();
        for($i = 0; $i <= $max_col; $i++){
            $cells[] = isset($sparse[$i]) ? $sparse[$i] : '';
        }

        if($header === null){
            // Scan for transaction header row (must have 'type' + category/amount)
            if(is_tx_header($cells)){
                $header       = array_map('strtolower', array_map('trim', $cells));
                $header_count = count($header);
            }
            continue; // header row itself is not a data row
        }
        if(count(array_filter($cells, function($v){ return trim($v) !== ''; })) < 2) continue;
        // Reset if we hit a section marker
        $firstVal = trim($cells[0] ?? '');
        if(strpos($firstVal, '===') !== false){
            $header = null;
            $header_count = 0;
            continue;
        }
        $padded = array_pad($cells, $header_count, '');
        if(count($padded) > $header_count) $padded = array_slice($padded, 0, $header_count);
        $rows[] = array_combine($header, $padded);
    }
    return $rows;
}

// ── Parse XLS (SpreadsheetML XML or tab/comma delimited) ──────────────────
function parse_xls($tmp){
    $rows = array();
    $content = file_get_contents($tmp);
    if($content === false) return $rows;

    // Detect SpreadsheetML (the format our report exports)
    if(strpos($content, 'Workbook') !== false && strpos($content, 'ss:Type') !== false){
        // Parse as SpreadsheetML XML — find the "Raw Transactions" worksheet first
        $dom = new DOMDocument();
        @$dom->loadXML($content);
        $worksheets = $dom->getElementsByTagName('Worksheet');
        $targetSheet = null;
        // Prefer "Raw Transactions" sheet, fall back to first sheet
        foreach($worksheets as $ws){
            $name = strtolower($ws->getAttribute('ss:Name'));
            if(strpos($name, 'raw') !== false || strpos($name, 'transaction') !== false){
                $targetSheet = $ws; break;
            }
        }
        if(!$targetSheet && $worksheets->length > 0) $targetSheet = $worksheets->item(0);
        if(!$targetSheet) return $rows;

        $header = null; $header_count = 0;
        foreach($targetSheet->getElementsByTagName('Row') as $rowEl){
            $cells = array();
            foreach($rowEl->getElementsByTagName('Cell') as $cell){
                $dataEl = $cell->getElementsByTagName('Data');
                $val = $dataEl->length > 0 ? $dataEl->item(0)->textContent : '';
                $cells[] = $val;
            }
            if(empty($cells)) continue;
            if(!$header){
                if(is_tx_header($cells)){
                    $header = array_map('strtolower', array_map('trim', $cells));
                    $header_count = count($header);
                }
                continue;
            }
            if(count(array_filter($cells, fn($v) => trim($v) !== '')) < 2) continue;
            $padded = array_pad($cells, $header_count, '');
            if(count($padded) > $header_count) $padded = array_slice($padded, 0, $header_count);
            $rows[] = array_combine($header, $padded);
        }
        return $rows;
    }

    // Fallback: plain text with tab or comma delimiter
    $handle = fopen($tmp, 'r');
    if($handle){
        $first = fgets($handle);
        rewind($handle);
        $sep    = (substr_count($first, "\t") > substr_count($first, ',')) ? "\t" : ',';
        $header = null; $header_count = 0;
        while(($line = fgetcsv($handle, 2000, $sep)) !== false){
            $line = array_map(function($v){ return $v === null ? '' : $v; }, $line);
            if(!$header){
                if(is_tx_header($line)){
                    $header = array_map('strtolower', array_map('trim', $line));
                    $header_count = count($header);
                }
                continue;
            }
            if(count(array_filter($line, fn($v) => trim($v) !== '')) < 2) continue;
            $padded = array_pad($line, $header_count, '');
            if(count($padded) > $header_count) $padded = array_slice($padded, 0, $header_count);
            $rows[] = array_combine($header, $padded);
        }
        fclose($handle);
    }
    return $rows;

// ── HANDLE UPLOAD ──────────────────────────────────────────────────────────
if(isset($_POST['upload_csv']) && isset($_FILES['tx_file']) && $_FILES['tx_file']['error'] === UPLOAD_ERR_OK){
    $file    = $_FILES['tx_file'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tmp     = $file['tmp_name'];
    $allowed = array('csv','xls','xlsx');

    if(!in_array($ext, $allowed)){
        $errors[] = "Unsupported file type '.$ext'. Please upload .csv, .xlsx, or .xls.";
    } else {
        // Parse the file
        $rows = array();
        if($ext === 'csv'){
            $rows = parse_csv($tmp);
        } elseif($ext === 'xlsx'){
            $rows = parse_xlsx($tmp);
        } else {
            // .xls — SpreadsheetML (our report format) or plain text
            $rows = parse_xls($tmp);
        }

        // Check for parse-level errors
        if(isset($rows['__error__'])){
            $errors[] = $rows['__error__'];
            $rows = array();
        }

        // DEBUG: show first 3 parsed rows in errors temporarily
        if(count($rows) === 0){
            $errors[] = "Parser found 0 rows. File ext: $ext";
        } else {
            // Show first row keys and values so we can verify columns
            $first_row = $rows[0];
            $errors[] = "DEBUG — parsed ".count($rows)." rows. First row keys: ".implode(', ', array_keys($first_row));
            $errors[] = "DEBUG — First row values: ".implode(' | ', array_values($first_row));
        }

        // Fetch account name → id map
        $acc_map = array();
        $acc_res = mysqli_query($conn,"SELECT id, account_name FROM accounts WHERE client_id='$client_id'");
        while($a = mysqli_fetch_assoc($acc_res)){
            $acc_map[strtolower(trim($a['account_name']))] = $a['id'];
        }

        $line_num = 1;
        foreach($rows as $raw_row){
            $line_num++;
            // Normalize keys to lowercase
            $row = array();
            foreach($raw_row as $k => $v) $row[strtolower(trim($k))] = $v;

            $type     = strtolower(get_col($row, array('type','transaction_type','tx_type')));
            $category = get_col($row, array('category','cat','label'));
            $amount   = floatval(str_replace(array(',','₱','$'), '', get_col($row, array('amount','amt','value'), '0')));
            $note     = get_col($row, array('note','notes','description','memo'));
            $date_raw = get_col($row, array('date','transaction_date','tx_date','period'), date('Y-m-d'));
            $acc_name = strtolower(get_col($row, array('account','account_name','wallet')));

            // Skip meta / blank / summary rows (e.g. from our own CSV template)
            if($type === '' || $type === 'type' || $type === 'metric' || $type === '=== summary ===' || $type === '=== period breakdown ===' || $type === '=== category breakdown ===') { $skipped++; continue; }
            if($category === '' || $category === 'category' || $category === 'period') { $skipped++; continue; }
            if($amount <= 0) { $skipped++; continue; }

            if(!in_array($type, array('income','expense'))){
                $errors[] = "Row $line_num: unknown type '".htmlspecialchars($type)."' — must be income or expense.";
                $skipped++;
                continue;
            }

            // Parse date safely
            $ts          = strtotime($date_raw);
            $parsed_date = ($ts !== false && $ts > 0) ? date('Y-m-d', $ts) : date('Y-m-d');

            // Resolve account
            $acc_id_sql = 'NULL';
            if($acc_name !== '' && isset($acc_map[$acc_name])){
                $acc_id_sql = "'".intval($acc_map[$acc_name])."'";
            }

            $cat_esc  = mysqli_real_escape_string($conn, $category);
            $note_esc = mysqli_real_escape_string($conn, $note);
            $dt       = $parsed_date.' '.date('H:i:s');

            $ok = mysqli_query($conn,
                "INSERT INTO transactions (client_id, account_id, type, category, amount, note, transaction_date)
                 VALUES ('$client_id', $acc_id_sql, '$type', '$cat_esc', '$amount', '$note_esc', '$dt')"
            );

            if($ok){
                if($acc_id_sql !== 'NULL'){
                    $dir = ($type === 'income') ? '+' : '-';
                    mysqli_query($conn,
                        "UPDATE accounts SET balance=balance{$dir}{$amount}
                         WHERE id={$acc_id_sql} AND client_id='$client_id'"
                    );
                }
                $results[] = array('row'=>$line_num,'type'=>$type,'category'=>$category,'amount'=>$amount,'date'=>$parsed_date);
                $imported++;
            } else {
                $errors[] = "Row $line_num: DB insert failed — ".mysqli_error($conn);
                $skipped++;
            }
        }
    } // end allowed ext
} // end upload handler

// Fetch account names for the info panel
$acc_res2  = mysqli_query($conn,"SELECT account_name FROM accounts WHERE client_id='$client_id' ORDER BY created_at ASC");
$acc_names = array();
while($a = mysqli_fetch_assoc($acc_res2)) $acc_names[] = $a['account_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import Transactions | Budget Supreme</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<?php include 'base_styles.php'; ?>
<style>
.upload-card{background:var(--card-bg);border:1px solid var(--border);border-radius:22px;padding:32px;margin-bottom:24px;}
.upload-zone{border:2px dashed rgba(46,204,113,.3);border-radius:16px;padding:48px 32px;text-align:center;
    transition:.3s;cursor:pointer;background:rgba(46,204,113,.03);}
.upload-zone:hover,.upload-zone.drag{border-color:var(--green-glow);background:rgba(46,204,113,.07);}
.upload-zone .icon{font-size:48px;margin-bottom:12px;}
.upload-zone h3{font-weight:800;font-size:1.1rem;margin-bottom:6px;}
.upload-zone p{color:rgba(255,255,255,.4);font-size:12px;}
#fileInput{display:none;}
.file-chosen{margin-top:12px;font-size:12px;color:var(--green-glow);font-weight:700;word-break:break-all;}
.template-box{background:rgba(93,173,226,.06);border:1px solid rgba(93,173,226,.2);
    border-radius:14px;padding:20px 24px;margin-bottom:20px;}
.template-box h4{font-size:13px;font-weight:800;margin-bottom:8px;color:var(--blue);}
.col-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
.col-tag{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
    border-radius:8px;padding:4px 12px;font-size:11px;font-family:monospace;}
.col-tag.req{border-color:rgba(46,204,113,.4);color:var(--green-glow);}
.result-table{width:100%;border-collapse:collapse;font-size:12px;}
.result-table th{background:#243040;padding:10px 14px;text-align:left;font-size:10px;
    text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.5);}
.result-table td{padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.05);}
.result-table tr:hover td{background:rgba(255,255,255,.03);}
.badge-inc{background:rgba(46,204,113,.12);color:var(--green-glow);
    padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;}
.badge-exp{background:rgba(255,107,107,.1);color:var(--red);
    padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;}
.summary-bar{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;}
.summary-pill{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
    border-radius:12px;padding:12px 20px;font-size:13px;font-weight:700;}
.err-list{background:rgba(255,107,107,.06);border:1px solid rgba(255,107,107,.2);
    border-radius:12px;padding:16px 20px;margin-bottom:20px;}
.err-list li{font-size:12px;color:rgba(255,150,150,.9);margin-bottom:4px;}
.action-row{margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
.btn-outline{text-decoration:none;background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.8);
    padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;transition:.2s;display:inline-block;}
.btn-outline:hover{background:rgba(255,255,255,.1);color:#fff;}
</style>
</head>
<body>
<?php include 'nav_include.php'; ?>
<div class="container">

    <div class="page-header">
        <h1>📥 Import Transactions</h1>
        <p>Upload a CSV or Excel file to bulk-import your financial records.</p>
    </div>

    <!-- ── RESULTS (shown after upload) ── -->
    <?php if($imported > 0 || count($errors) > 0): ?>
    <div class="upload-card">

        <?php if($imported > 0): ?>
        <!-- Success banner -->
        <div style="text-align:center;padding:16px 0 24px;">
            <div style="font-size:52px;margin-bottom:12px;">✅</div>
            <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:6px;">
                <?php echo $imported; ?> Transaction<?php echo $imported !== 1 ? 's' : ''; ?> Imported!
            </h2>
            <p style="color:rgba(255,255,255,.45);font-size:13px;">
                Your records have been saved and your insights are now updated.
                <?php if($skipped > 0): ?>
                <br><span style="color:#ffc864;">⚠️ <?php echo $skipped; ?> row<?php echo $skipped !== 1 ? 's' : ''; ?> were skipped (blank, zero amount, or unrecognized type).</span>
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:16px 0 24px;">
            <div style="font-size:48px;margin-bottom:12px;">⚠️</div>
            <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:6px;">Nothing was imported</h2>
            <p style="color:rgba(255,255,255,.45);font-size:13px;">No valid rows were found in the file. Check the format and try again.</p>
        </div>
        <?php endif; ?>

        <?php if(count($errors) > 0): ?>
        <div class="err-list">
            <strong style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--red);">Row Errors:</strong>
            <ul style="margin:8px 0 0 16px;padding:0;">
                <?php foreach($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:8px;">
            <a href="client_graphs.php" class="btn-primary"
               style="text-decoration:none;padding:12px 32px;border-radius:14px;font-size:14px;display:inline-block;">
                📊 Back to Insights
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── UPLOAD FORM (hidden after successful import) ── -->
    <?php if($imported === 0): ?>
    <div class="upload-card">

        <!-- Column guide -->
        <div class="template-box">
            <h4>📄 Required &amp; Supported Columns</h4>
            <p style="font-size:12px;color:rgba(255,255,255,.5);margin-bottom:0;">
                Your file must have a <strong>header row</strong> with these column names (case-insensitive):
            </p>
            <div class="col-list">
                <span class="col-tag req">type</span>
                <span class="col-tag req">category</span>
                <span class="col-tag req">amount</span>
                <span class="col-tag">date</span>
                <span class="col-tag">note</span>
                <span class="col-tag">account</span>
            </div>
            <p style="font-size:11px;color:rgba(255,255,255,.35);margin-top:10px;margin-bottom:0;">
                <strong style="color:var(--green-glow);">type</strong> must be
                <code>income</code> or <code>expense</code>.&nbsp;
                <?php if(count($acc_names) > 0): ?>
                <strong style="color:rgba(255,255,255,.5);">account</strong> must match one of your accounts:
                <?php foreach($acc_names as $aname): ?>
                <code><?php echo htmlspecialchars($aname); ?></code>
                <?php endforeach; ?>
                <?php else: ?>
                <strong style="color:rgba(255,255,255,.5);">account</strong>:
                <em>no accounts yet — this column will be ignored.</em>
                <?php endif; ?>
            </p>
        </div>

        <!-- Drop zone -->
        <div class="upload-zone" id="dropZone"
             onclick="document.getElementById('fileInput').click()">
            <div class="icon">📂</div>
            <h3>Drop your file here or click to browse</h3>
            <p>Supports .csv, .xlsx, .xls</p>
            <div class="file-chosen" id="fileChosen"></div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="file" id="fileInput" name="tx_file"
                   accept=".csv,.xls,.xlsx" onchange="showFile(this)">
            <div class="action-row">
                <button type="submit" name="upload_csv" id="submitBtn"
                    class="btn-primary"
                    style="padding:12px 28px;border-radius:14px;font-size:14px;
                           opacity:.4;cursor:not-allowed;" disabled>
                    📥 Import Transactions
                </button>
                <a href="download_report.php?format=csv&period=monthly"
                   class="btn-outline">
                    ⬇️ Download CSV Template
                </a>
            </div>
        </form>
    </div>

    <!-- Tips -->
    <div class="upload-card" style="padding:24px 28px;">
        <h4 style="margin-bottom:14px;font-size:11px;font-weight:800;
            color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:1.5px;">
            💡 Tips
        </h4>
        <ul style="font-size:13px;color:rgba(255,255,255,.55);line-height:2.1;
                   padding-left:20px;margin:0;">
            <li>Go to <strong>Insights → Download Report → CSV</strong> to get a ready-made template from your existing data.</li>
            <li>Dates accept any common format: <code>2025-01-15</code>, <code>Jan 15 2025</code>, <code>01/15/2025</code>.</li>
            <li>Rows with blank category, zero amount, or unknown type are automatically skipped.</li>
            <li>If an <code>account</code> column matches one of your account names, the balance is updated automatically.</li>
            <li>There is no duplicate check — uploading the same file twice will create duplicate entries.</li>
            <?php if(!class_exists('ZipArchive')): ?>
            <li style="color:rgba(255,200,100,.85);">
                ⚠️ This server does not support XLSX parsing.
                Please export as <strong>.csv</strong> from Excel/Google Sheets before uploading.
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

</div><!-- /container -->

<script>
var dropZone   = document.getElementById('dropZone');
var fileInput  = document.getElementById('fileInput');
var submitBtn  = document.getElementById('submitBtn');
var fileChosen = document.getElementById('fileChosen');

function showFile(input){
    if(input.files && input.files.length > 0){
        fileChosen.textContent = '\uD83D\uDCCE ' + input.files[0].name;
        submitBtn.disabled          = false;
        submitBtn.style.opacity     = '1';
        submitBtn.style.cursor      = 'pointer';
    }
}

if(dropZone){
    dropZone.addEventListener('dragover', function(e){
        e.preventDefault();
        dropZone.classList.add('drag');
    });
    dropZone.addEventListener('dragleave', function(){
        dropZone.classList.remove('drag');
    });
    dropZone.addEventListener('drop', function(e){
        e.preventDefault();
        dropZone.classList.remove('drag');
        var dt = e.dataTransfer;
        if(dt && dt.files && dt.files.length){
            fileInput.files = dt.files;
            showFile(fileInput);
        }
    });
}
</script>
</body>
</html>