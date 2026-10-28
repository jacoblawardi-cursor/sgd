<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id = get_current_user_id();
$diamonds = sgd_get_diamonds( $user_id );
$is_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();

global $wpdb;
$table_tasks = $wpdb->prefix . 'sgd_tasks';
if ( current_user_can( 'administrator' ) ) {
    $daily_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks WHERE type = 'daily' AND status != 'completed'" );
} else {
    $daily_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_tasks WHERE type = 'daily' AND status != 'completed' AND (assigned_to = 0 OR assigned_to = %d)",
        $user_id
    ) );
}
$urgent_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks WHERE type = 'urgent' AND status != 'completed'" );
$is_admin = current_user_can( 'administrator' );
$sos_requests = $is_admin ? $wpdb->get_results( "SELECT * FROM $table_tasks WHERE help_status = 'requested' ORDER BY need_help_time DESC" ) : [];
?>

<div class="sgd-main-menu-container">
    
    <!-- 16-BIT / WOW GAME TITLE BANNER -->
    <div class="sgd-game-header">
        <div class="sgd-realm-badge">⚔️ 16-BIT GAMIFICATION REALM • WORLD OF WARCRAFT ⚔️</div>
        <h1 class="sgd-game-title">EMPLOYEE QUEST & CASH SHOP</h1>
        <div class="sgd-player-summary-bar">
            <span>PLAYER: <strong style="color:#fff;"><?php echo $is_logged_in ? esc_html($current_user->display_name) : 'GUEST'; ?></strong></span>
            <span style="color:#ffd700;">★ LEVEL 60</span>
            <span style="color:#00e5ff;">💎 TOTAL DIAMONDS: <strong style="color:#fff;"><?php echo number_format($diamonds); ?></strong></span>
        </div>
    </div>

    <!-- FLASHING EMERGENCY SOS HELP ALERT BANNER (FOR ADMIN) -->
    <?php if ( $is_admin && ! empty( $sos_requests ) ): ?>
        <div class="sgd-sos-active-alert" style="background:radial-gradient(circle at 50% 20%, #4a1010 0%, #200505 100%); border:3px solid #ff4444; border-radius:8px; padding:16px 20px; margin-bottom:20px; box-shadow:0 0 25px rgba(255,68,68,0.5); animation:pulse-urgent 1.2s infinite; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <span style="font-size:32px;">🚨</span>
                <div>
                    <div style="font-family:var(--font-pixel); font-size:9px; color:#ff9999; letter-spacing:1px; text-transform:uppercase;">
                        EMERGENCY SOS: <?php echo count($sos_requests); ?> EMPLOYEE(S) NEED HELP!
                    </div>
                    <div style="font-size:13px; color:#fff; margin-top:3px;">
                        <?php 
                        $first_sos = $sos_requests[0];
                        $sos_emp = get_user_by('id', $first_sos->need_help_by);
                        echo '<strong>' . ($sos_emp ? esc_html($sos_emp->display_name) : 'An employee') . '</strong> requested help on: <em>' . esc_html($first_sos->title) . '</em> (' . esc_html($first_sos->need_help_time) . ')';
                        ?>
                    </div>
                </div>
            </div>
            <a href="<?php echo esc_url( sgd_get_portal_url('admin') ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold" style="white-space:nowrap;">
                🛡️ Open SOS Dispatch Board ➔
            </a>
        </div>
    <?php endif; ?>

    <!-- THE 5 MAIN HOMEPAGE BUTTONS -->
    <div class="sgd-main-menu-list">
        
        <!-- 1. ACHIEVEMENT BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'achievements' ) ); ?>" class="sgd-wow-menu-btn btn-achievement">
            <span class="sgd-menu-btn-icon">🏆</span>
            <div class="sgd-menu-btn-content">
                <span class="sgd-menu-btn-label">ACHIEVEMENT</span>
                <span class="sgd-menu-btn-desc">List of employee achievements, badges & feats of honor</span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 2. DAILY TASK BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-wow-menu-btn btn-daily">
            <span class="sgd-menu-btn-icon">📜</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label">DAILY TASK</span>
                    <span class="sgd-pill-reward">+200 💎 REWARD</span>
                    <?php if ( $daily_count > 0 ): ?>
                        <span class="sgd-pill-count"><?php echo $daily_count; ?> Available</span>
                    <?php endif; ?>
                </div>
                <span class="sgd-menu-btn-desc">Contains jobs to do • "Need Help" & "Finish Job" with photo proof</span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 3. URGENT TASK BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'urgent' ) ); ?>" class="sgd-wow-menu-btn btn-urgent">
            <span class="sgd-menu-btn-icon" style="animation: pulse-urgent 1.2s infinite;">⚡</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label" style="color:#ff8577;">URGENT TASK</span>
                    <span class="sgd-pill-reward urgent-reward">+1,000 💎 REWARD</span>
                    <?php if ( $urgent_count > 0 ): ?>
                        <span class="sgd-pill-count urgent-count"><?php echo $urgent_count; ?> Critical</span>
                    <?php endif; ?>
                </div>
                <span class="sgd-menu-btn-desc">Emergency jobs anyone could take • "Take Job" & "Finish Job" with photo proof</span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 4. CASH SHOP BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'shop' ) ); ?>" class="sgd-wow-menu-btn btn-cash-shop">
            <span class="sgd-menu-btn-icon">💎</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label" style="color:#80deea;">CASH SHOP</span>
                    <span class="sgd-pill-reward shop-reward">TRADE DIAMONDS</span>
                </div>
                <span class="sgd-menu-btn-desc">IDR 200k Voucher (1k 💎) • 1 Day Paid Leave (20k 💎) • Holiday Trip (100k 💎)</span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 5. LOG IN BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'login' ) ); ?>" class="sgd-wow-menu-btn btn-login">
            <span class="sgd-menu-btn-icon">🔑</span>
            <div class="sgd-menu-btn-content">
                <span class="sgd-menu-btn-label"><?php echo $is_logged_in ? 'MY ACCOUNT & LOG IN' : 'LOG IN'; ?></span>
                <span class="sgd-menu-btn-desc"><?php echo $is_logged_in ? 'Logged in as ' . esc_html($current_user->display_name) . ' • 1-Click Demo Switcher • Admin Command' : 'Authenticate hero account • Demo quick-login'; ?></span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <?php if ( current_user_can('administrator') ): ?>
            <!-- ADMIN COMMAND CENTER SHORTCUT -->
            <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" class="sgd-wow-menu-btn" style="background:linear-gradient(180deg, #3d2b07 0%, #171003 100%); border-color:#ffd700;">
                <span class="sgd-menu-btn-icon" style="border-color:#ffd700;">🛡️</span>
                <div class="sgd-menu-btn-content">
                    <span class="sgd-menu-btn-label" style="color:#ffd700;">ADMIN COMMAND CENTER</span>
                    <span class="sgd-menu-btn-desc">Add/Edit/Delete Tasks • Dispatch Help • Manage Employee Achievements & Diamonds</span>
                </div>
                <span class="sgd-menu-btn-arrow" style="color:#ffd700;">➔</span>
            </a>
        <?php endif; ?>

    </div>

    <!-- QUICK DEMO SWITCHER FOOTNOTE -->
    <div class="sgd-menu-demo-helper">
        <span style="font-family:var(--font-pixel); font-size:8px; color:#8fa0bd;">QUICK DEMO ROLE SWITCHER:</span>
        <form method="post" style="display:inline;">
            <input type="hidden" name="sgd_action" value="sgd_switch_user">
            <input type="hidden" name="target_user" value="employee">
            <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-blue">⚔️ Play as Employee (Emp123!)</button>
        </form>
        <form method="post" style="display:inline;">
            <input type="hidden" name="sgd_action" value="sgd_switch_user">
            <input type="hidden" name="target_user" value="admin">
            <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">👑 Play as Admin (Admin123!)</button>
        </form>
    </div>

</div>
