<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$user_id = $current_user->ID;
$user_diamonds = sgd_get_diamonds( $user_id );
$is_admin = current_user_can( 'administrator' );
$active_tab = sanitize_text_field( $_GET['sgd_page'] ?? 'home' );

global $wpdb;
$table_tasks = $wpdb->prefix . 'sgd_tasks';
if ( $is_admin ) {
    $daily_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks WHERE type = 'daily' AND status != 'completed'" );
} else {
    $daily_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_tasks WHERE type = 'daily' AND status != 'completed' AND (assigned_to = 0 OR assigned_to = %d)",
        $user_id
    ) );
}
$urgent_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks WHERE type = 'urgent' AND status != 'completed'" );
$sos_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks WHERE help_status = 'requested'" );
$pending_orders_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_redemptions WHERE status = 'pending'" );

$flash = sgd_get_flash();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGD Guild - 16-Bit Gamification Portal & Goblin Treasury</title>
    <link rel="stylesheet" href="<?php echo esc_url( SGD_PLUGIN_URL . '/assets/style.css' ); ?>?v=<?php echo time(); ?>">
    <?php wp_head(); ?>
</head>
<body class="sgd-wow-body">

<!-- TOP STATUS HUD BAR -->
<header class="sgd-top-hud">
    <div class="sgd-container">
        <div class="sgd-hud-inner">
            
            <!-- Character Unit Frame -->
            <div class="sgd-unit-frame">
                <div class="sgd-portrait">
                    <?php if ( $is_admin ): ?>
                        👑
                    <?php elseif ( $is_logged_in ): ?>
                        🧙‍♂️
                    <?php else: ?>
                        👤
                    <?php endif; ?>
                    <span class="sgd-unit-level">60</span>
                </div>
                <div class="sgd-unit-info">
                    <span class="sgd-unit-name">
                        <?php echo $is_logged_in ? esc_html( $current_user->display_name ) : 'Guest Wanderer'; ?>
                    </span>
                    <span class="sgd-unit-role">
                        <?php 
                        if ( $is_admin ) {
                            echo '⚔️ Grand Marshal (Admin)';
                        } elseif ( $is_logged_in ) {
                            echo '🛡️ Level 60 Employee Hero';
                        } else {
                            echo 'Adventurer (Unlogged)';
                        }
                        ?>
                    </span>
                    <div class="sgd-bars">
                        <div class="sgd-bar" title="Health: 100%"><div class="sgd-bar-hp"></div></div>
                        <div class="sgd-bar" title="Mana: 85%"><div class="sgd-bar-mp"></div></div>
                    </div>
                </div>
            </div>

            <!-- Diamond Wallet Badge -->
            <div class="sgd-diamond-wallet" title="Your Total Treasury Balance">
                <span class="sgd-diamond-icon">💎</span>
                <div class="sgd-diamond-details">
                    <span class="sgd-diamond-label">Total Diamonds</span>
                    <span class="sgd-diamond-count"><?php echo number_format( $user_diamonds ); ?> 💎</span>
                </div>
            </div>

            <!-- ADMIN LIVE SOS HELP BEACON ALERT (If any employee needs help) -->
            <?php if ( $is_admin && $sos_count > 0 ): ?>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" style="text-decoration:none;" title="Click to open SOS Help Dispatch Board">
                    <div class="sgd-sos-active-alert" style="background:radial-gradient(circle, #5e1616 0%, #290808 100%); border:2px solid #ff4444; border-radius:6px; padding:6px 14px; display:flex; align-items:center; gap:10px; animation:pulse-urgent 1.2s infinite; box-shadow:0 0 15px rgba(255,68,68,0.5);">
                        <span style="font-size:20px;">🚨</span>
                        <div>
                            <div style="font-family:var(--font-pixel); font-size:7px; color:#ff9999; letter-spacing:1px;">SOS ALERT</div>
                            <div style="font-family:var(--font-pixel); font-size:10px; color:#fff; font-weight:bold;"><?php echo $sos_count; ?> NEED HELP ➔</div>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

            <!-- ADMIN CASH SHOP REDEMPTION ORDERS BADGE -->
            <?php if ( $is_admin && $pending_orders_count > 0 ): ?>
                <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'tab' => 'orders' ], home_url( '/' ) ) ); ?>" style="text-decoration:none;" title="Click to review pending Cash Shop orders">
                    <div style="background:radial-gradient(circle, #422d06 0%, #1a1202 100%); border:2px solid var(--wow-gold); border-radius:6px; padding:6px 14px; display:flex; align-items:center; gap:10px; box-shadow:0 0 15px rgba(255,215,0,0.4);">
                        <span style="font-size:20px;">🎁</span>
                        <div>
                            <div style="font-family:var(--font-pixel); font-size:7px; color:#ffd700; letter-spacing:1px;">SHOP ORDERS</div>
                            <div style="font-family:var(--font-pixel); font-size:10px; color:#fff; font-weight:bold;"><?php echo $pending_orders_count; ?> PENDING ➔</div>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

            <!-- Quick Character Switcher & Tools -->
            <div class="sgd-hud-actions">
                <button type="button" class="sgd-sfx-btn" id="sgd-sfx-toggle">🔊 SFX: ON</button>
                
                <div class="sgd-switch-pill" title="Quick 1-Click Role Switch for Demo Testing">
                    <span>DEMO:</span>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sgd_action" value="sgd_switch_user">
                        <input type="hidden" name="target_user" value="admin">
                        <button type="submit" class="sgd-switch-btn <?php echo ( $is_admin ) ? 'active' : ''; ?>">👑 Admin</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sgd_action" value="sgd_switch_user">
                        <input type="hidden" name="target_user" value="employee">
                        <button type="submit" class="sgd-switch-btn <?php echo ( $is_logged_in && ! $is_admin ) ? 'active' : ''; ?>">⚔️ Employee</button>
                    </form>
                </div>

                <?php if ( $is_logged_in ): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sgd_action" value="sgd_logout">
                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red" style="font-size:8px;">Hearthstone (Logout)</button>
                    </form>
                <?php else: ?>
                    <a href="<?php echo esc_url( sgd_get_portal_url( 'login' ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue">Log In</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</header>

<div class="sgd-container">

    <!-- Flash Notifications -->
    <?php if ( $flash ): ?>
        <div class="sgd-flash-banner sgd-flash-<?php echo esc_attr( $flash['type'] ); ?>">
            <?php 
            if ( $flash['type'] === 'celebration' ) echo '🎉 ';
            elseif ( $flash['type'] === 'error' ) echo '⚠️ ';
            else echo '⚔️ ';
            echo esc_html( $flash['msg'] ); 
            ?>
        </div>
    <?php endif; ?>

    <!-- Main Navigation Bar (Visible on subpages, with Back to Home) -->
    <?php if ( $active_tab !== 'home' ): ?>
        <nav class="sgd-nav-tabs">
            <a href="<?php echo esc_url( sgd_get_portal_url() ); ?>" class="sgd-tab-btn" style="border-color:var(--wow-gold-light); color:var(--wow-gold-light);">
                ◀ MAIN MENU
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'achievements' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'achievements' ) ? 'active' : ''; ?>">
                🏆 ACHIEVEMENT
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'daily' ) ? 'active' : ''; ?>">
                📜 DAILY TASK (+200 💎)
                <?php if ( $daily_count > 0 ): ?>
                    <span class="sgd-badge-count"><?php echo $daily_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'urgent' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'urgent' ) ? 'active' : ''; ?>">
                ⚡ URGENT TASK (+1,000 💎)
                <?php if ( $urgent_count > 0 ): ?>
                    <span class="sgd-badge-count" style="background:#ff4444;"><?php echo $urgent_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'shop' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'shop' ) ? 'active' : ''; ?>">
                💎 CASH SHOP
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'login' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'login' ) ? 'active' : ''; ?>">
                🔑 <?php echo $is_logged_in ? 'MY ACCOUNT' : 'LOG IN'; ?>
            </a>
            <?php if ( $is_admin ): ?>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'admin' ) ? 'active' : ''; ?>" style="border-color:#ffcc00; margin-left:auto;">
                    🛡️ ADMIN COMMAND
                    <?php if ( $sos_count > 0 ): ?>
                        <span class="sgd-badge-count" style="background:#d63031; animation:pulse-urgent 1s infinite;">SOS: <?php echo $sos_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
