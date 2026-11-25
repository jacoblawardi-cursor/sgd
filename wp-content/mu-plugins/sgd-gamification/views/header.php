<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$user_id = $current_user->ID;
$user_diamonds = sgd_get_diamonds( $user_id );
$is_admin = current_user_can( 'administrator' );
$active_tab = sanitize_text_field( $_GET['sgd_page'] ?? 'home' );
$active_theme = function_exists( 'sgd_get_current_theme' ) ? sgd_get_current_theme() : ( ( isset( $_COOKIE['sgd_theme'] ) && $_COOKIE['sgd_theme'] === 'genshin' ) ? 'genshin' : 'wow' );

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
    <?php
    $page_titles = [
        'hearthstone' => 'SGD Guild - Hearthstone Tavern & Mana Treasury',
        'genshin'     => 'SGD Guild - Teyvat Adventurers\' Guild & Primogem Treasury',
        'wow'         => 'SGD Guild - 16-Bit Gamification Portal & Goblin Treasury',
    ];
    $title_display = $page_titles[ $active_theme ] ?? $page_titles['wow'];
    ?>
    <title><?php echo esc_html( $title_display ); ?></title>
    <script>
    (function(){
        try {
            var m = document.cookie.match(/(^|; )sgd_theme=([^;]+)/);
            var cTheme = m ? decodeURIComponent(m[2]) : null;
            var lTheme = localStorage.getItem('sgd_active_theme');
            var t = lTheme || cTheme || '<?php echo esc_js( $active_theme ); ?>' || 'wow';
            if (t !== 'genshin' && t !== 'hearthstone' && t !== 'wow') t = 'wow';
            document.documentElement.setAttribute('data-sgd-theme', t);
        } catch(e) {}
    })();
    </script>
    <link rel="stylesheet" href="<?php echo esc_url( SGD_PLUGIN_URL . '/assets/style.css' ); ?>?v=<?php echo time(); ?>">
    <?php wp_head(); ?>
</head>
<body class="sgd-wow-body sgd-body-theme sgd-theme-<?php echo esc_attr( $active_theme ); ?>">

<!-- TOP STATUS HUD BAR -->
<header class="sgd-top-hud">
    <div class="sgd-container">
        <div class="sgd-hud-inner">
            
            <!-- Character Unit Frame -->
            <?php 
            $xp_data = sgd_get_user_xp_data( $user_id );
            $user_login = $current_user ? $current_user->user_login : '';
            $user_avatar = sgd_get_user_avatar( $user_id );
            $job_class = sgd_get_user_job_class( $user_id );
            $is_lvl30_plus = ( $xp_data['level'] >= 30 );
            $lvl30_candidates = $is_admin ? sgd_get_level_30_candidates() : [];
            ?>
            <div class="sgd-unit-frame">
                <div class="sgd-portrait sgd-fancy-portrait <?php echo $is_lvl30_plus ? 'sgd-portrait-lvl30' : ''; ?>" title="<?php echo $is_logged_in ? 'Click to Change Avatar' : ''; ?>">
                    <span class="sgd-portrait-icon"><?php echo $user_avatar; ?></span>
                    <?php if ( $is_logged_in ): ?>
                        <button type="button" class="sgd-avatar-modal-trigger" title="Change Hero Avatar">✎</button>
                    <?php endif; ?>
                    <span class="sgd-unit-level <?php echo $is_lvl30_plus ? 'sgd-level-gold-crest' : ''; ?>" title="Hero Level: <?php echo $xp_data['level']; ?><?php echo $is_lvl30_plus ? ' (Mythic Level 30+ Paragon!)' : ''; ?>">
                        <?php echo $xp_data['level']; ?>
                    </span>
                </div>
                <div class="sgd-unit-info">
                    <div class="sgd-unit-header-line">
                        <span class="sgd-unit-name">
                            <?php echo $is_logged_in ? esc_html( $current_user->display_name ) : 'Guest Wanderer'; ?>
                        </span>
                        <span class="sgd-job-class-pill <?php echo strtolower($job_class) === 'manager' ? 'sgd-job-class-manager' : 'sgd-job-class-staff'; ?>" title="Employee Job Class: <?php echo esc_attr( $job_class ); ?>">
                            <?php echo ( strtolower($job_class) === 'manager' ? '⭐ ' : '🎖️ ' ) . esc_html( $job_class ); ?>
                        </span>
                    </div>
                    <span class="sgd-unit-role" title="Squad: <?php echo esc_attr( $xp_data['faction'] ); ?>">
                        <?php if ( $is_logged_in ): ?>
                            <button type="button" class="sgd-title-badge-btn sgd-title-modal-trigger" title="Click to equip or customize your Hero Title">
                                👑 <?php echo esc_html( $xp_data['title'] ); ?> <span class="sgd-title-arrow">▾</span>
                            </button>
                        <?php else: ?>
                            <?php echo esc_html( $xp_data['title'] ); ?>
                        <?php endif; ?>
                        • <span style="color:#c69b3a;"><?php echo esc_html( $xp_data['faction'] ); ?></span>
                    </span>
                    <!-- FANCY EMPLOYEE XP PROGRESS BAR -->
                    <div class="sgd-fancy-xp-wrap" title="Experience: <?php echo number_format($xp_data['total_xp']); ?> Total XP • <?php echo number_format($xp_data['xp_in_level']); ?> / <?php echo number_format($xp_data['xp_needed']); ?> XP (<?php echo $xp_data['percent']; ?>%) to Level <?php echo min(60, $xp_data['level'] + 1); ?>">
                        <div class="sgd-fancy-xp-bar">
                            <div class="sgd-fancy-xp-fill" style="width:<?php echo $xp_data['percent']; ?>%;">
                                <span class="sgd-fancy-xp-gloss"></span>
                                <span class="sgd-fancy-xp-light-sweep"></span>
                                <span class="sgd-fancy-xp-spark">
                                    <span class="sgd-spark-star">✦</span>
                                </span>
                            </div>
                        </div>
                        <div class="sgd-fancy-xp-text">
                            <span class="sgd-fancy-xp-lvl">👑 LVL <?php echo $xp_data['level']; ?></span>
                            <span class="sgd-fancy-xp-mid"><?php echo number_format($xp_data['xp_in_level']); ?> / <?php echo number_format($xp_data['xp_needed']); ?> XP <strong style="color:#ffd700;">(<?php echo $xp_data['percent']; ?>%)</strong></span>
                            <span class="sgd-fancy-xp-next">NEXT: LVL <?php echo min(60, $xp_data['level'] + 1); ?> ➔</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Diamond / Primogem / Mana Crystal Wallet Badge -->
            <div class="sgd-diamond-wallet" title="Your Total Treasury Balance">
                <span class="sgd-diamond-icon">
                    <span class="sgd-theme-text-wow">💎</span>
                    <span class="sgd-theme-text-genshin">✦</span>
                    <span class="sgd-theme-text-hearthstone">💠</span>
                </span>
                <div class="sgd-diamond-details">
                    <span class="sgd-diamond-label">
                        <span class="sgd-theme-text-wow">Total Diamonds</span>
                        <span class="sgd-theme-text-genshin">Primogems</span>
                        <span class="sgd-theme-text-hearthstone">Arcane Mana</span>
                    </span>
                    <span class="sgd-diamond-count">
                        <?php echo number_format( $user_diamonds ); ?> 
                        <span class="sgd-theme-text-wow">💎</span>
                        <span class="sgd-theme-text-genshin">✦</span>
                        <span class="sgd-theme-text-hearthstone">💠</span>
                    </span>
                </div>
            </div>

            <!-- Daily Quest Streak HUD Badge -->
            <?php 
            $streak_info = sgd_get_user_streak( $user_id );
            ?>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" style="text-decoration:none;" title="Daily Streak: <?php echo $streak_info['current']; ?> Days. <?php echo $streak_info['is_active_today'] ? '✅ Completed for today!' : '⏳ Complete a daily task today to extend streak!'; ?>">
                <div class="sgd-streak-hud-badge <?php echo ( $streak_info['current'] >= 5 ) ? 'sgd-streak-on-fire' : ''; ?>">
                    <span class="sgd-streak-flame-icon">🔥</span>
                    <div class="sgd-streak-hud-details">
                        <span class="sgd-streak-hud-label">Daily Streak</span>
                        <span class="sgd-streak-hud-val">
                            <?php echo $streak_info['current']; ?> <?php echo $streak_info['current'] === 1 ? 'Day' : 'Days'; ?>
                            <?php if ( $streak_info['bonus_percent'] > 0 ): ?>
                                <span class="sgd-streak-bonus-pill">+<?php echo $streak_info['bonus_percent']; ?>% XP ⭐</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </a>

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
                <!-- Realm Theme Switcher -->
                <div class="sgd-theme-switcher-pill" id="sgd-theme-switcher" title="Switch Theme Realm: World of Warcraft, Genshin Impact, or Hearthstone Tavern">
                    <span class="sgd-theme-switcher-tag">REALM:</span>
                    <button type="button" class="sgd-theme-btn <?php echo ( $active_theme === 'wow' ) ? 'active' : ''; ?>" data-theme="wow" title="World of Warcraft (16-Bit Retro Guild)">
                        <span class="sgd-theme-icon">⚔️</span>
                        <span class="sgd-theme-name">WoW</span>
                    </button>
                    <button type="button" class="sgd-theme-btn <?php echo ( $active_theme === 'genshin' ) ? 'active' : ''; ?>" data-theme="genshin" title="Genshin Impact (Teyvat Elemental)">
                        <span class="sgd-theme-icon">✦</span>
                        <span class="sgd-theme-name">Genshin</span>
                    </button>
                    <button type="button" class="sgd-theme-btn <?php echo ( $active_theme === 'hearthstone' ) ? 'active' : ''; ?>" data-theme="hearthstone" title="Hearthstone (Cozy Tavern & Mana Crystals)">
                        <span class="sgd-theme-icon">🎴</span>
                        <span class="sgd-theme-name">Tavern</span>
                    </button>
                </div>

                <div class="sgd-sound-ctrl-wrap">
                    <button type="button" class="sgd-sfx-btn" id="sgd-sfx-toggle" title="Click to mute/unmute, or adjust volume">
                        <span id="sgd-sfx-icon">🔊</span>
                        <span id="sgd-sfx-text">SFX: 70%</span>
                    </button>
                    <div class="sgd-sound-popover" id="sgd-sound-popover">
                        <div class="sgd-sound-popover-title">
                            <span>VOLUME CONTROL</span>
                            <span id="sgd-vol-display" style="color:var(--wow-gold-light);">70%</span>
                        </div>
                        <input type="range" id="sgd-volume-slider" min="0" max="100" value="70" class="sgd-range-input">
                        <div class="sgd-sound-presets">
                            <button type="button" class="sgd-sound-preset-btn" data-vol="0">🔇 Mute</button>
                            <button type="button" class="sgd-sound-preset-btn" data-vol="50">🔉 50%</button>
                            <button type="button" class="sgd-sound-preset-btn" data-vol="100">🔊 100%</button>
                        </div>
                    </div>
                </div>
                
                <?php if ( $is_admin && ! empty( $lvl30_candidates ) ): ?>
                    <!-- LEVEL 30 PROMOTION ALERT BEACON FOR ADMIN -->
                    <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'admin_tab' => 'promotion' ], home_url( '/' ) ) ); ?>" class="sgd-lvl30-top-beacon" title="Review Level 30 Heroes for Job Class Promotion">
                        <span class="sgd-lvl30-beacon-pulse"></span>
                        👑 <strong>LVL 30 ALERT:</strong> <?php echo count($lvl30_candidates); ?> Hero(es) Ready for Promotion! ➔
                    </a>
                <?php endif; ?>

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
                        <button type="submit" class="sgd-switch-btn <?php echo ( $is_logged_in && ! $is_admin && $user_login === 'employee' ) ? 'active' : ''; ?>">⚔️ Arthas</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sgd_action" value="sgd_switch_user">
                        <input type="hidden" name="target_user" value="employee2">
                        <button type="submit" class="sgd-switch-btn <?php echo ( $is_logged_in && $user_login === 'employee2' ) ? 'active' : ''; ?>">🧙‍♀️ Jaina</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sgd_action" value="sgd_switch_user">
                        <input type="hidden" name="target_user" value="employee3">
                        <button type="submit" class="sgd-switch-btn sgd-switch-lvl30 <?php echo ( $is_logged_in && $user_login === 'employee3' ) ? 'active' : ''; ?>" title="Sylvanas (Level 30 Hero - Promotion Trial Eligible)">
                            🏹 Sylvanas (Lv.30)
                        </button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sgd_action" value="sgd_switch_user">
                        <input type="hidden" name="target_user" value="employee4">
                        <button type="submit" class="sgd-switch-btn <?php echo ( $is_logged_in && $user_login === 'employee4' ) ? 'active' : ''; ?>" title="Thrall (Staff Hero)">
                            ⚡ Thrall
                        </button>
                    </form>
                </div>

                <?php if ( $is_logged_in ): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sgd_action" value="sgd_logout">
                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red" style="font-size:8px;">
                            <span class="sgd-theme-text-wow">Hearthstone (Logout)</span>
                            <span class="sgd-theme-text-genshin">Waypoint (Logout)</span>
                            <span class="sgd-theme-text-hearthstone">Leave Table (Logout)</span>
                        </button>
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
            <a href="<?php echo esc_url( sgd_get_portal_url( 'leaderboard' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'leaderboard' ) ? 'active' : ''; ?>" style="color:#e056fd; border-color:#be2edd;">
                👑 HALL OF LEGENDS
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'daily' ) ? 'active' : ''; ?>">
                📜 DAILY TASK (+200 <span class="sgd-theme-text-wow">💎</span><span class="sgd-theme-text-genshin">✦</span><span class="sgd-theme-text-hearthstone">💠</span>)
                <?php if ( $daily_count > 0 ): ?>
                    <span class="sgd-badge-count"><?php echo $daily_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'laporan-harian' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'laporan-harian' ) ? 'active' : ''; ?>" style="color:#00e5ff; border-color:#00e5ff;">
                📋 LAPORAN HARIAN (+150 <span class="sgd-theme-text-wow">💎</span><span class="sgd-theme-text-genshin">✦</span><span class="sgd-theme-text-hearthstone">💠</span>)
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'urgent' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'urgent' ) ? 'active' : ''; ?>">
                ⚡ URGENT TASK (+1,000 <span class="sgd-theme-text-wow">💎</span><span class="sgd-theme-text-genshin">✦</span><span class="sgd-theme-text-hearthstone">💠</span>)
                <?php if ( $urgent_count > 0 ): ?>
                    <span class="sgd-badge-count" style="background:#ff4444;"><?php echo $urgent_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'quests' ) ? 'active' : ''; ?>" style="border-color:#00e5ff; color:#00e5ff;">
                🎯 QUESTS (KANBAN)
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'shop' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'shop' ) ? 'active' : ''; ?>">
                <span class="sgd-theme-text-wow">💎</span><span class="sgd-theme-text-genshin">✦</span><span class="sgd-theme-text-hearthstone">💠</span> CASH SHOP
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'wheel' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'wheel' ) ? 'active' : ''; ?>" style="color:#ffd700; border-color:#ffd700;">
                🎰 FORTUNE WHEEL
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'bounties' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'bounties' ) ? 'active' : ''; ?>" style="color:#ff9f43; border-color:#ff9f43;">
                🤝 BOUNTIES
            </a>
            <a href="<?php echo esc_url( sgd_get_portal_url( 'login' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'login' ) ? 'active' : ''; ?>">
                🔑 <?php echo $is_logged_in ? 'MY ACCOUNT' : 'LOG IN'; ?>
            </a>
            <?php if ( $is_admin ): ?>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" class="sgd-tab-btn <?php echo ( $active_tab === 'admin' ) ? 'active' : ''; ?>" style="border-color:#ffcc00;">
                    🛡️ ADMIN COMMAND
                    <?php if ( $sos_count > 0 ): ?>
                        <span class="sgd-badge-count" style="background:#d63031; animation:pulse-urgent 1s infinite;">SOS: <?php echo $sos_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <!-- HERO TITLE ARMORY MODAL -->
    <?php if ( $is_logged_in ): ?>
        <div class="sgd-modal-backdrop" id="sgd-title-modal">
            <div class="sgd-modal-box" style="max-width:480px;">
                <div class="sgd-modal-header">
                    <h3 class="sgd-modal-title">👑 Hero Title Armory</h3>
                    <button type="button" class="sgd-modal-close" id="sgd-title-modal-close">&times;</button>
                </div>
                <form method="post">
                    <input type="hidden" name="sgd_action" value="sgd_change_title">
                    <div class="sgd-modal-body">
                        <p style="font-size:13px; color:#c8d1e0; line-height:1.5; margin-bottom:14px;">
                            Equip any title earned through leveling, daily wheel spins, streak feats, or type an approved custom role title!
                        </p>

                        <div class="sgd-form-group">
                            <label class="sgd-form-label">Select from Your Earned Titles:</label>
                            <select name="hero_title" id="sgd-title-select" class="sgd-form-select">
                                <?php 
                                $unlocked_titles = sgd_get_user_unlocked_titles( $user_id );
                                $current_title = $xp_data['title'];
                                foreach ( $unlocked_titles as $ut ): 
                                ?>
                                    <option value="<?php echo esc_attr($ut); ?>" <?php selected( $current_title, $ut ); ?>>
                                        👑 <?php echo esc_html($ut); ?> <?php echo ($current_title === $ut) ? '★ (Currently Equipped)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="text-align:center; font-family:var(--font-pixel); font-size:8px; color:#ffd700; margin:14px 0 10px 0; letter-spacing:1px;">
                            — OR FORGE CUSTOM ROLE TITLE —
                        </div>

                        <div class="sgd-form-group">
                            <label class="sgd-form-label">Custom Title (Max 40 chars):</label>
                            <input type="text" name="custom_title" id="sgd-custom-title-input" class="sgd-form-input" placeholder="e.g. Master of Code, Bug Slayer, Sprint Paladin" maxlength="40">
                            <small style="color:#8fa0bd; font-size:10px; margin-top:4px; display:block;">Leave blank to use the selected title above.</small>
                        </div>
                    </div>
                    <div class="sgd-modal-footer">
                        <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-title-modal-cancel">Cancel</button>
                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">👑 Equip Hero Title</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- HERO AVATAR WARDROBE MODAL (FOR EMPLOYEES) -->
        <div id="sgd-avatar-modal" class="sgd-modal" style="display:none;">
            <div class="sgd-modal-dialog" style="max-width:480px;">
                <div class="sgd-modal-header">
                    <h3 class="sgd-modal-title">🎨 Hero Avatar Wardrobe</h3>
                    <button type="button" class="sgd-modal-close" id="sgd-avatar-modal-close">&times;</button>
                </div>
                <form method="post">
                    <input type="hidden" name="sgd_action" value="sgd_change_avatar">
                    <div class="sgd-modal-body">
                        <p style="font-size:13px; color:#c8d1e0; line-height:1.5; margin-bottom:14px;">
                            Choose your signature RPG hero portrait and crest to represent you on the quest board and leaderboard:
                        </p>

                        <div class="sgd-avatar-grid">
                            <?php 
                            $avatar_options = sgd_get_avatar_options();
                            $cur_avatar = sgd_get_user_avatar( $user_id );
                            foreach ( $avatar_options as $av_icon => $av_label ):
                                $is_active_av = ( $cur_avatar === $av_icon );
                            ?>
                                <label class="sgd-avatar-option-card <?php echo $is_active_av ? 'active' : ''; ?>">
                                    <input type="radio" name="avatar_icon" value="<?php echo esc_attr( $av_icon ); ?>" <?php checked( $is_active_av ); ?> style="display:none;">
                                    <span class="sgd-avatar-big-icon"><?php echo $av_icon; ?></span>
                                    <span class="sgd-avatar-opt-title"><?php echo esc_html( $av_label ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="sgd-modal-footer">
                        <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-avatar-modal-cancel">Cancel</button>
                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">🎨 Equip Hero Avatar</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Avatar Modal Controls
            var avTriggers = document.querySelectorAll('.sgd-avatar-modal-trigger, .sgd-fancy-portrait');
            var avModal = document.getElementById('sgd-avatar-modal');
            var avClose = document.getElementById('sgd-avatar-modal-close');
            var avCancel = document.getElementById('sgd-avatar-modal-cancel');
            
            if (avModal) {
                avTriggers.forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        avModal.style.display = 'flex';
                    });
                });
                if (avClose) avClose.addEventListener('click', function() { avModal.style.display = 'none'; });
                if (avCancel) avCancel.addEventListener('click', function() { avModal.style.display = 'none'; });
                avModal.addEventListener('click', function(e) {
                    if (e.target === avModal) avModal.style.display = 'none';
                });
                // Highlight selected card
                var avCards = document.querySelectorAll('.sgd-avatar-option-card');
                avCards.forEach(function(card) {
                    card.addEventListener('click', function() {
                        avCards.forEach(function(c) { c.classList.remove('active'); });
                        card.classList.add('active');
                        var rad = card.querySelector('input[type="radio"]');
                        if (rad) rad.checked = true;
                    });
                });
            }
        });
        </script>
    <?php endif; ?>
