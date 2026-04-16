<?php
include 'dbconnect.php';
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: index.php");
    exit();
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'income';
$order_type = ($filter == 'income') ? 'Income' : 'Expense';

// 1. DATA PARA SA TABLE AT GRAPH
$sql = "SELECT c.first_name, c.last_name, SUM(t.amount) as total_amount 
        FROM clients c 
        JOIN transactions t ON c.id = t.client_id 
        WHERE t.type = '$filter' 
        GROUP BY c.id 
        ORDER BY total_amount DESC";

$result = mysqli_query($conn, $sql);

// Mag-prepare tayo ng arrays para sa Chart.js
$names = [];
$amounts = [];
// --- ADDED: Variables for Insights ---
$total_sum = 0; 

// Re-run the query result to fill arrays (or use fetch_all)
$chart_result = mysqli_query($conn, $sql);
while($row = mysqli_fetch_assoc($chart_result)){
    $names[] = $row['first_name'] . " " . $row['last_name'];
    $amounts[] = (float)$row['total_amount'];
    // --- ADDED: Calculate total for average ---
    $total_sum += (float)$row['total_amount'];
}

// Highest amount for progress bar
$highest_amount = !empty($amounts) ? max($amounts) : 1;

// --- ADDED: Insight Logic Calculations ---
$count_users = count($names);
$average = ($count_users > 0) ? $total_sum / $count_users : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Statistics | Budget Supreme</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --green-main: #1f9d63; --green-glow: #2ecc71; --bg-dark: #02110a; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-dark); color: #fff; min-height: 100vh; }
        body::before { content: ""; position: fixed; inset: 0; background: linear-gradient(rgba(0,40,25,0.4), rgba(0,25,15,0.4)), url('graph.jpg') no-repeat center center fixed; background-size: cover; z-index: -2; }

        /* NAVBAR */
        nav { padding: 15px 60px; display: flex; justify-content: space-between; align-items: center; background: rgba(8,18,14,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(46,204,113,0.2); position: sticky; top: 0; z-index: 100; }
        .logo { font-weight: 800; letter-spacing: 3px; }
        .logo span { color: var(--green-glow); }
        
        .nav-links { display: flex; gap: 25px; }
        .nav-links a { 
            text-decoration: none; 
            color: rgba(255,255,255,0.6); 
            font-size: 12px; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            transition: 0.3s;
            position: relative;
        }
        .nav-links a:hover { color: var(--green-glow); }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--green-glow);
            transition: width 0.3s ease;
        }
        .nav-links a:hover::after { width: 100%; }

        /* ADMIN MENU */
        .admin-menu { position: relative; }
        .admin-trigger { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 15px; border-radius: 6px; background: rgba(46,204,113,0.05); border: 1px solid rgba(46,204,113,0.2); }
        .admin-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--green-main); color: #000; display: flex; align-items: center; justify-content: center; font-weight: 800; }
        .admin-dropdown { position: absolute; top: 45px; right: 0; width: 180px; background: rgba(8,18,14,0.95); border: 1px solid var(--green-main); border-radius: 8px; display: none; flex-direction: column; overflow: hidden; }
        .admin-dropdown a { padding: 12px; text-decoration: none; color: #fff; font-size: 13px; }
        .admin-dropdown a:hover { background: var(--green-main); color: #000; }

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        
        /* --- ADDED: Insights Card Styles --- */
        .insights-card {
            background: rgba(31, 157, 99, 0.05);
            backdrop-filter: blur(15px);
            border-left: 4px solid var(--green-glow);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-top: 1px solid rgba(46, 204, 113, 0.1);
            border-right: 1px solid rgba(46, 204, 113, 0.1);
            border-bottom: 1px solid rgba(46, 204, 113, 0.1);
        }
        .insights-card h3 { font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: var(--green-glow); margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
        .insights-card p { font-size: 14px; line-height: 1.6; color: rgba(255,255,255,0.8); }
        .highlight-text { color: var(--green-glow); font-weight: 800; }

        /* GRAPH SECTION */
        .graph-container { 
            background: rgba(8,18,14,0.8); backdrop-filter: blur(20px); border-radius: 20px; 
            border: 1px solid rgba(46,204,113,0.2); padding: 30px; margin-bottom: 30px;
            display: none; 
        }

        .btn-toggle-graph {
            background: transparent; color: var(--green-glow); border: 1px solid var(--green-glow);
            padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 800; font-size: 11px;
            text-transform: uppercase; transition: 0.3s; margin-bottom: 20px;
        }
        .btn-toggle-graph:hover { background: var(--green-glow); color: #000; }

        /* TABLE STYLES */
        .table-wrapper { background: rgba(8,18,14,0.7); border-radius: 20px; border: 1px solid rgba(46,204,113,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 20px; text-align: left; background: rgba(31, 157, 99, 0.1); color: var(--green-glow); font-size: 10px; text-transform: uppercase; }
        td { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .bar-container { width: 100%; height: 4px; background: rgba(255,255,255,0.1); border-radius: 10px; margin-top: 8px; }
        .bar-fill { height: 100%; background: var(--green-glow); border-radius: 10px; }
    </style>
</head>
<body>

<nav>
    <div class="logo">BUDGET <span>SUPREME</span></div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="statistics.php">Statistics</a>
        <a href="client_list.php">Total Users</a>
    </div>
    <div class="admin-menu">
        <div class="admin-trigger" onclick="toggleMenu()">
            <div class="admin-avatar"><?php echo strtoupper($_SESSION['admin'][0]); ?></div>
            <span class="admin-name" style="font-size: 12px; margin-left: 5px; color: var(--green-glow);"><?php echo $_SESSION['admin']; ?></span>
        </div>
        <div class="admin-dropdown" id="adminDropdown">
            <a href="profile_settings.php">Profile Settings</a>
            <a href="logout.php" style="color:#ff6b6b;">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="font-size: 24px;">Financial <span style="color:var(--green-glow)">Statistics</span></h1>
        <form method="GET" id="filterForm">
            <select name="filter" onchange="this.form.submit()" style="background:#02110a; color:var(--green-glow); border:1px solid var(--green-main); padding:8px; border-radius:5px;">
                <option value="income" <?php if($filter == 'income') echo 'selected'; ?>>Income Ranking</option>
                <option value="expense" <?php if($filter == 'expense') echo 'selected'; ?>>Expense Ranking</option>
            </select>
        </form>
    </div>

    <div class="insights-card">
        <h3>✨ Supreme Intelligence Feedback</h3>
        <p>
            <?php if($count_users > 0): ?>
                Analysis shows that <span class="highlight-text"><?php echo $names[0]; ?></span> currently leads the ranking with a total of 
                <span class="highlight-text">₱<?php echo number_format($amounts[0], 2); ?></span>. 
                The average <?php echo strtolower($order_type); ?> across all <?php echo $count_users; ?> users is 
                <span class="highlight-text">₱<?php echo number_format($average, 2); ?></span>.
                
                <?php if($amounts[0] > ($average * 1.5)): ?>
                    A significant outlier is detected: The top contributor is <span class="highlight-text">50% higher</span> than the average trend.
                <?php else: ?>
                    The financial distribution among users appears to be <span class="highlight-text">balanced and consistent</span> with no unusual spikes.
                <?php endif; ?>
            <?php else: ?>
                No transaction data available yet to generate supreme financial insights.
            <?php endif; ?>
        </p>
    </div>

    <button class="btn-toggle-graph" onclick="toggleGraph()">📊 View Analytics Graph</button>

    <div class="graph-container" id="graphSection">
        <canvas id="supremeChart"></canvas>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>User</th>
                    <th style="text-align: right;">Total <?php echo $order_type; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                mysqli_data_seek($result, 0); 
                while($row = mysqli_fetch_assoc($result)): 
                    $pct = ($row['total_amount'] / $highest_amount) * 100;
                ?>
                <tr>
                    <td style="color:var(--green-glow); font-weight:800">#<?php echo $rank++; ?></td>
                    <td>
                        <?php echo $row['first_name'] . " " . $row['last_name']; ?>
                        <div class="bar-container"><div class="bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                    </td>
                    <td style="text-align: right; font-weight: 800;">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Toggle Graph Visibility
function toggleGraph() {
    const section = document.getElementById('graphSection');
    section.style.display = (section.style.display === 'block') ? 'none' : 'block';
}

// Chart.js Configuration
const ctx = document.getElementById('supremeChart').getContext('2d');
const supremeChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($names); ?>,
        datasets: [{
            label: 'Total <?php echo $order_type; ?> (₱)',
            data: <?php echo json_encode($amounts); ?>,
            borderColor: '#2ecc71',
            backgroundColor: 'rgba(46, 204, 113, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4, 
            pointBackgroundColor: '#fff',
            pointBorderColor: '#2ecc71',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { color: '#fff', font: { family: 'Plus Jakarta Sans' } } }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: { color: 'rgba(255,255,255,0.5)' }
            },
            x: {
                grid: { display: false },
                ticks: { color: 'rgba(255,255,255,0.5)' }
            }
        }
    }
});

function toggleMenu(){
    const menu = document.getElementById("adminDropdown");
    menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
}
</script>

</body>
</html>
