<?php
/**
 * nav_include.php
 * Shared navigation bar. Calling page must set $active_nav before including.
 * Also runs notifications_engine.php to generate + fetch notifications.
 */
$nav_first  = $user_data['first_name'] ?? ($_SESSION['client_user'] ?? 'User');
$nav_letter = $nav_first ? strtoupper(substr($nav_first,0,1)) : 'U';
$nav_cid    = $_SESSION['client_id'] ?? '';

// Run notification engine
include_once 'notifications_engine.php';

// Notification type colors & icons
$notif_colors = [
    'danger'  => '#ff6b6b',
    'warning' => '#ffb347',
    'success' => '#2ecc71',
    'info'    => '#5dade2',
];
$notif_icons = [
    'danger'  => '🚨',
    'warning' => '⚠️',
    'success' => '🎉',
    'info'    => '💡',
];
?>
<nav>
    <div class="logo">BUDGET <span>SUPREME</span></div>

    <div class="nav-links">
        <a href="client_home.php"         class="nav-link <?php echo ($active_nav==='home')        ?'active':''; ?>">🏠 Home</a>
        <a href="client_account.php"      class="nav-link <?php echo ($active_nav==='account')     ?'active':''; ?>">💳 Account</a>
        <a href="client_transactions.php" class="nav-link <?php echo ($active_nav==='transactions')?'active':''; ?>">📋 Transactions</a>
        <a href="client_budget.php"       class="nav-link <?php echo ($active_nav==='budget')      ?'active':''; ?>">🎯 Budget</a>
        <a href="client_graphs.php"       class="nav-link <?php echo ($active_nav==='graphs')      ?'active':''; ?>">📊 Insights</a>
        <a href="client_settings.php"     class="nav-link <?php echo ($active_nav==='settings')    ?'active':''; ?>">⚙️ Settings</a>
    </div>

    <div class="nav-right" style="display:flex;align-items:center;gap:14px;">

        <!-- NOTIFICATION BELL -->
        <div class="notif-wrap" id="notifToggle">
            <div class="notif-bell">
                🔔
                <?php if($notif_unread > 0): ?>
                <span class="notif-dot" id="notifDot"></span>
                <?php endif; ?>
            </div>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                </div>
                <div class="notif-list">
                    <?php if(empty($notifications)): ?>
                    <div class="notif-empty">No notifications yet.</div>
                    <?php else: foreach($notifications as $n):
                        $color = $notif_colors[$n['type']] ?? '#5dade2';
                        $icon  = $notif_icons[$n['type']]  ?? '💡';
                        $time  = date('M j, Y · g:i A', strtotime($n['created_at']));
                    ?>
                    <div class="notif-item">
                        <div class="notif-icon" style="color:<?php echo $color; ?>;"><?php echo $icon; ?></div>
                        <div class="notif-body">
                            <div class="notif-title" style="color:<?php echo $color; ?>;"><?php echo htmlspecialchars($n['title']); ?></div>
                            <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                            <div class="notif-time"><?php echo $time; ?></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- PROFILE DROPDOWN -->
        <div class="user-profile" id="profileToggle">
            <div class="profile-trigger">
                <div class="avatar"><?php echo $nav_letter; ?></div>
                <span style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($nav_first); ?></span>
                <span style="font-size:9px;opacity:.5;">▼</span>
            </div>
            <div class="client-dropdown" id="clientDropdown">
                <div class="dropdown-header">
                    <p>Client Protocol</p>
                    <span style="font-size:10px;opacity:.5;">ID: <?php echo $nav_cid; ?></span>
                </div>
                <a href="client_settings.php">⚙️ Settings</a>
                <hr style="border:0;border-top:1px solid rgba(255,255,255,.05);margin:5px 0;">
                <a href="client_logout.php" style="color:#ff6b6b;">⏏️ Logout</a>
            </div>
        </div>
    </div>
</nav>

<style>
/* NOTIFICATION BELL */
.notif-wrap { position:relative; cursor:pointer; }
.notif-bell { width:36px; height:36px; border-radius:10px; border:1px solid var(--border);
    background:rgba(255,255,255,.04); display:flex; align-items:center; justify-content:center;
    font-size:16px; position:relative; transition:.25s; }
.notif-bell:hover { border-color:rgba(46,204,113,.4); background:rgba(46,204,113,.08); }
.notif-dot { position:absolute; top:-4px; right:-4px; width:10px; height:10px;
    background:#ff6b6b; border-radius:50%; border:2px solid var(--bg-dark); }
.notif-dropdown { position:absolute; top:46px; right:0; width:320px;
    background:rgba(8,18,14,.98); backdrop-filter:blur(25px);
    border:1px solid rgba(46,204,113,.2); border-radius:18px; padding:0;
    display:none; z-index:1000; box-shadow:0 20px 40px rgba(0,0,0,.7);
    overflow:hidden; }
.notif-dropdown.active { display:block; animation:slideDown .25s ease-out; }
.notif-header { padding:14px 18px; border-bottom:1px solid rgba(255,255,255,.06);
    font-size:13px; font-weight:700; }
.notif-list { max-height:360px; overflow-y:auto; }
.notif-list::-webkit-scrollbar { width:4px; }
.notif-list::-webkit-scrollbar-track { background:transparent; }
.notif-list::-webkit-scrollbar-thumb { background:rgba(46,204,113,.3); border-radius:4px; }
.notif-item { display:flex; gap:12px; padding:14px 18px;
    border-bottom:1px solid rgba(255,255,255,.04); transition:.2s; }
.notif-item:last-child { border-bottom:none; }
.notif-item:hover { background:rgba(255,255,255,.03); }
.notif-icon { font-size:18px; flex-shrink:0; margin-top:2px; }
.notif-body { flex:1; }
.notif-title { font-size:12px; font-weight:700; margin-bottom:3px; }
.notif-msg { font-size:11px; color:rgba(255,255,255,.55); line-height:1.5; margin-bottom:4px; }
.notif-time { font-size:10px; color:rgba(255,255,255,.25); }
.notif-empty { padding:24px; text-align:center; color:rgba(255,255,255,.25); font-size:12px; }
</style>

<script>
(function(){
    // Profile dropdown
    const t = document.getElementById('profileToggle');
    const d = document.getElementById('clientDropdown');
    if(t && d){
        t.addEventListener('click', e => {
            e.stopPropagation();
            d.classList.toggle('active');
            document.getElementById('notifDropdown').classList.remove('active');
        });
        window.addEventListener('click', e => { if(!t.contains(e.target)) d.classList.remove('active'); });
    }

    // Notification dropdown
    const nb = document.getElementById('notifToggle');
    const nd = document.getElementById('notifDropdown');
    const dot = document.getElementById('notifDot');

    if(nb && nd){
        nb.addEventListener('click', e => {
            e.stopPropagation();
            const isOpening = !nd.classList.contains('active');
            nd.classList.toggle('active');
            if(d) d.classList.remove('active');

            // When opening: hide dot immediately and silently mark all read
            if(isOpening && dot){
                dot.style.display = 'none';
                fetch(window.location.pathname + '?notif_read=1').catch(()=>{});
            }
        });
        window.addEventListener('click', e => { if(!nb.contains(e.target)) nd.classList.remove('active'); });
    }
})();
</script>
