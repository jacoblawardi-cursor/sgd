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
$pending_orders_count = $is_admin ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_redemptions WHERE status = 'pending'" ) : 0;
?>

<div class="sgd-main-menu-container">
    
    <!-- 16-BIT / WOW / GENSHIN / HEARTHSTONE GAME TITLE BANNER -->
    <div class="sgd-game-header">
        <div class="sgd-realm-badge">
            <span class="sgd-theme-text-wow">⚔️ 16-BIT GAMIFICATION REALM • WORLD OF WARCRAFT ⚔️</span>
            <span class="sgd-theme-text-genshin">✦ TEYVAT ADVENTURERS' GUILD • GENSHIN REALM ✦</span>
            <span class="sgd-theme-text-hearthstone">🍺 THE INNKEEPER'S TAVERN • HEARTHSTONE REALM 🍺</span>
        </div>
        <h1 class="sgd-game-title">
            <span class="sgd-theme-text-wow">EMPLOYEE QUEST & CASH SHOP</span>
            <span class="sgd-theme-text-genshin">ADVENTURER COMMISSIONS & SOUVENIR SHOP</span>
            <span class="sgd-theme-text-hearthstone">TAVERN QUEST LOG & CARD PACK STORE</span>
        </h1>
        <div class="sgd-player-summary-bar">
            <?php 
            $home_xp = sgd_get_user_xp_data( $user_id ); 
            $home_streak = sgd_get_user_streak( $user_id );
            $home_job_class = sgd_get_user_job_class( $user_id );
            $home_avatar = sgd_get_user_avatar( $user_id );
            $lvl30_candidates = $is_admin ? sgd_get_level_30_candidates() : [];
            ?>
            <span>
                <span class="sgd-theme-text-wow">PLAYER:</span>
                <span class="sgd-theme-text-genshin">TRAVELER:</span> 
                <span class="sgd-theme-text-hearthstone">CHALLENGER:</span>
                <strong style="color:#fff;"><?php echo $is_logged_in ? esc_html($current_user->display_name) : 'GUEST'; ?></strong>
                <span class="sgd-job-class-pill <?php echo strtolower($home_job_class) === 'manager' ? 'sgd-job-class-manager' : 'sgd-job-class-staff'; ?>" style="margin-left:6px;">
                    <?php echo ( strtolower($home_job_class) === 'manager' ? '⭐ ' : '🎖️ ' ) . esc_html($home_job_class); ?>
                </span>
            </span>
            <span style="color:#ffd700;">
                ★ LEVEL <?php echo $home_xp['level']; ?> 
                (<span class="sgd-theme-text-wow"><?php echo esc_html($home_xp['faction']); ?></span><span class="sgd-theme-text-genshin">AR <?php echo $home_xp['level']; ?> • <?php echo esc_html($home_xp['faction']); ?></span><span class="sgd-theme-text-hearthstone">Rank <?php echo max(1, 26 - $home_xp['level']); ?> • <?php echo esc_html($home_xp['faction']); ?></span>)
            </span>
            <span style="color:#00e5ff;">
                <span class="sgd-theme-text-wow">💎 TOTAL DIAMONDS:</span>
                <span class="sgd-theme-text-genshin">✦ PRIMOGEMS:</span> 
                <span class="sgd-theme-text-hearthstone">💠 ARCANE MANA:</span>
                <strong style="color:#fff;"><?php echo number_format($diamonds); ?></strong>
            </span>
            <span style="color:#ff9f43;">
                🔥 <span class="sgd-theme-text-wow">STREAK:</span><span class="sgd-theme-text-genshin">COMMISSION STREAK:</span><span class="sgd-theme-text-hearthstone">WIN STREAK:</span> 
                <strong style="color:#fff;"><?php echo $home_streak['current']; ?> DAYS</strong>
                <?php if ( $home_streak['bonus_percent'] > 0 ) echo ' <span style="background:rgba(255,159,67,0.25); border:1px solid #ff9f43; padding:1px 6px; border-radius:3px; font-size:9px; color:#ffc048;">+' . $home_streak['bonus_percent'] . '% XP BONUS ⭐</span>'; ?>
            </span>
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

    <!-- LEVEL 30 PROMOTION ALERT BANNER (FOR ADMIN) -->
    <?php if ( $is_admin && ! empty( $lvl30_candidates ) ): ?>
        <div class="sgd-lvl30-home-banner" style="background:radial-gradient(circle at 50% 20%, #2e240a 0%, #151105 100%); border:3px solid #ffd700; border-radius:8px; padding:16px 20px; margin-bottom:20px; box-shadow:0 0 25px rgba(255,215,0,0.5); animation:pulse-urgent 2s infinite; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <span style="font-size:34px;">👑</span>
                <div>
                    <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; letter-spacing:1px; text-transform:uppercase;">
                        ⭐ LEVEL 30 PROMOTION NOTICE: <?php echo count($lvl30_candidates); ?> HERO(ES) REACHED LEVEL 30!
                    </div>
                    <div style="font-size:13px; color:#fff; margin-top:3px;">
                        <?php 
                        $names_list = [];
                        foreach ( $lvl30_candidates as $c ) {
                            $names_list[] = '<strong>' . esc_html($c['display_name']) . '</strong> (' . esc_html($c['job_class']) . ')';
                        }
                        echo implode(', ', $names_list);
                        ?> reached Level 30! Assign special custom task and promote them from Staff to Manager.
                    </div>
                </div>
            </div>
            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'admin_tab' => 'promotion' ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold" style="white-space:nowrap;">
                👑 Open Promotion Hall ➔
            </a>
        </div>
    <?php endif; ?>

    <!-- FANCY HERO PROGRESSION & XP SHOWCASE WIDGET (FOR EMPLOYEES) -->
    <?php if ( $is_logged_in && ! $is_admin ): 
        $p_status = get_user_meta( $user_id, 'sgd_promotion_status', true );
        $is_lvl30 = ( $home_xp['level'] >= 30 );
    ?>
        <div class="sgd-employee-hero-showcase <?php echo $is_lvl30 ? 'sgd-hero-lvl30-active' : ''; ?>">
            <div class="sgd-showcase-left">
                <div class="sgd-showcase-avatar-ring <?php echo $is_lvl30 ? 'ring-gold-mythic' : ''; ?>">
                    <span class="sgd-showcase-avatar-icon"><?php echo $home_avatar; ?></span>
                    <span class="sgd-showcase-lvl-badge <?php echo $is_lvl30 ? 'badge-gold-30' : ''; ?>">
                        <?php echo $home_xp['level']; ?>
                    </span>
                </div>
                <div class="sgd-showcase-meta">
                    <div class="sgd-showcase-name-row">
                        <span class="sgd-showcase-name"><?php echo esc_html( $current_user->display_name ); ?></span>
                        <span class="sgd-job-class-pill <?php echo strtolower($home_job_class) === 'manager' ? 'sgd-job-class-manager' : 'sgd-job-class-staff'; ?>">
                            <?php echo ( strtolower($home_job_class) === 'manager' ? '⭐ MANAGER' : '🎖️ STAFF' ); ?>
                        </span>
                        <?php if ( $is_lvl30 ): ?>
                            <span class="sgd-status-badge" style="background:#ffd700; color:#000; font-weight:bold; font-size:7px;">
                                👑 LEVEL 30 MASTER
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="sgd-showcase-title-row">
                        <span style="color:#ffd700; font-family:var(--font-title); font-size:13px;">👑 <?php echo esc_html( $home_xp['title'] ); ?></span>
                        <span style="color:#7f8fa6;">• <?php echo esc_html( $home_xp['faction'] ); ?></span>
                    </div>

                    <!-- Level 30 Promotion Status Notice for Employee -->
                    <?php if ( $is_lvl30 ): ?>
                        <div class="sgd-promo-status-banner">
                            <?php if ( strtolower($home_job_class) === 'manager' || $p_status === 'promoted' ): ?>
                                <span style="color:#2ecc71;">🎉 <strong>PROMOTED TO MANAGER!</strong> You have successfully earned executive guild status!</span>
                            <?php elseif ( $p_status === 'task_completed' ): ?>
                                <span style="color:#ffd700;">✨ <strong>SPECIAL PROMOTION TRIAL SUBMITTED!</strong> Grand Marshal Admin is reviewing your leadership proof for Manager promotion.</span>
                            <?php elseif ( $p_status === 'task_assigned' ): ?>
                                <span style="color:#00e5ff;">🌟 <strong>SPECIAL PROMOTION TRIAL ASSIGNED!</strong> Complete your custom task on the Daily Tasks board to qualify for Manager!</span>
                            <?php else: ?>
                                <span style="color:#f39c12;">🎓 <strong>LEVEL 30 MILESTONE REACHED!</strong> Grand Marshal Admin notified for your Manager promotion evaluation.</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FANCY ANIMATED GOLDEN XP BAR -->
            <div class="sgd-showcase-xp-block">
                <div class="sgd-showcase-xp-top">
                    <span class="sgd-showcase-xp-tag">👑 HERO EXPERIENCE PROGRESSION (GOLDEN PATH):</span>
                    <strong class="sgd-showcase-xp-ratio">
                        <?php echo number_format($home_xp['xp_in_level']); ?> / <?php echo number_format($home_xp['xp_needed']); ?> XP 
                        <span class="sgd-gold-xp-pill"><?php echo $home_xp['percent']; ?>%</span>
                    </strong>
                </div>
                <div class="sgd-showcase-xp-bar-outer">
                    <div class="sgd-showcase-xp-bar-fill" style="width:<?php echo $home_xp['percent']; ?>%;">
                        <span class="sgd-showcase-xp-gloss"></span>
                        <span class="sgd-showcase-xp-light-sweep"></span>
                        <span class="sgd-showcase-xp-spark">
                            <span class="sgd-spark-star">✦</span>
                        </span>
                    </div>
                </div>
                <div class="sgd-showcase-xp-bottom">
                    <span>Current Floor: <strong>Level <?php echo $home_xp['level']; ?></strong></span>
                    <span class="sgd-total-xp-counter">✨ <?php echo number_format($home_xp['total_xp']); ?> Total XP Earned</span>
                    <span>Target: <strong>Level <?php echo min(60, $home_xp['level'] + 1); ?></strong> ➔</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- THE MAIN HOMEPAGE BUTTONS -->
    <div class="sgd-main-menu-list">
        
        <!-- 1. ACHIEVEMENT BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'achievements' ) ); ?>" class="sgd-wow-menu-btn btn-achievement">
            <span class="sgd-menu-btn-icon">🏆</span>
            <div class="sgd-menu-btn-content">
                <span class="sgd-menu-btn-label">
                    <span class="sgd-theme-text-wow">ACHIEVEMENT</span>
                    <span class="sgd-theme-text-genshin">✦ WONDERS OF THE WORLD</span>
                    <span class="sgd-theme-text-hearthstone">🎴 HALL OF FAME FEATS</span>
                </span>
                <span class="sgd-menu-btn-desc">
                    <span class="sgd-theme-text-wow">List of employee achievements, badges & feats of honor</span>
                    <span class="sgd-theme-text-genshin">Adventurer feats, Primogem milestones & memories of Teyvat</span>
                    <span class="sgd-theme-text-hearthstone">Legendary card achievements, tavern milestones & golden glory</span>
                </span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 2. DAILY TASK BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-wow-menu-btn btn-daily">
            <span class="sgd-menu-btn-icon">📜</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label">
                        <span class="sgd-theme-text-wow">DAILY TASK</span>
                        <span class="sgd-theme-text-genshin">✦ DAILY COMMISSIONS</span>
                        <span class="sgd-theme-text-hearthstone">📜 TAVERN QUEST LOG</span>
                    </span>
                    <span class="sgd-pill-reward">
                        <span class="sgd-theme-text-wow">+200 XP ⭐ REWARD</span>
                        <span class="sgd-theme-text-genshin">+200 XP ⭐ REWARD</span>
                        <span class="sgd-theme-text-hearthstone">+200 XP ⭐ REWARD</span>
                    </span>
                    <?php if ( $daily_count > 0 ): ?>
                        <span class="sgd-pill-count"><?php echo $daily_count; ?> Available</span>
                    <?php endif; ?>
                </div>
                <span class="sgd-menu-btn-desc">
                    <span class="sgd-theme-text-wow">Contains jobs to do • "Need Help" & "Finish Job" with photo proof</span>
                    <span class="sgd-theme-text-genshin">Katheryne's Guild • Complete daily commissions & claim XP</span>
                    <span class="sgd-theme-text-hearthstone">Daily tavern duties • Complete quests to earn experience & level up</span>
                </span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 2.5 LAPORAN HARIAN (DAILY WORK REPORT) BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'laporan-harian' ) ); ?>" class="sgd-wow-menu-btn btn-laporan" style="border-color:#00e5ff; background:radial-gradient(ellipse at 10% 50%, rgba(0, 229, 255, 0.15) 0%, rgba(13, 20, 38, 0.95) 75%);">
            <span class="sgd-menu-btn-icon" style="filter:drop-shadow(0 0 10px #00e5ff);">📋</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span class="sgd-menu-btn-label" style="color:#00e5ff;">
                        <span class="sgd-theme-text-wow">LAPORAN HARIAN (DAILY REPORT)</span>
                        <span class="sgd-theme-text-genshin">✦ EXPEDITION & FIELD CHRONICLE</span>
                        <span class="sgd-theme-text-hearthstone">📋 INNKEEPER'S DAILY CHRONICLE</span>
                    </span>
                    <span class="sgd-pill-reward" style="background:rgba(0,229,255,0.2); border-color:#00e5ff; color:#00e5ff;">
                        <span class="sgd-theme-text-wow">+200 XP ⭐ REWARD</span>
                        <span class="sgd-theme-text-genshin">+200 XP ⭐ REWARD</span>
                        <span class="sgd-theme-text-hearthstone">+200 XP ⭐ REWARD</span>
                    </span>
                    <span style="font-family:var(--font-pixel); font-size:8px; background:#70a1ff; color:#0b1933; padding:2px 6px; border-radius:3px;">🎙️ VOICE & AI DECIPHER</span>
                </div>
                <span class="sgd-menu-btn-desc">Input via teks / suara • Smart AI decipher: cuaca per jam, tenaga kerja, material masuk, alat, K3 & 3 foto progress</span>
            </div>
            <span class="sgd-menu-btn-arrow" style="color:#00e5ff;">➔</span>
        </a>

        <!-- 3. URGENT TASK BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'urgent' ) ); ?>" class="sgd-wow-menu-btn btn-urgent">
            <span class="sgd-menu-btn-icon" style="animation: pulse-urgent 1.2s infinite;">⚡</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label" style="color:#ff8577;">
                        <span class="sgd-theme-text-wow">URGENT TASK</span>
                        <span class="sgd-theme-text-genshin">✦ TROUNCE DOMAIN & CRISIS</span>
                        <span class="sgd-theme-text-hearthstone">⚔️ HEROIC TAVERN BRAWL</span>
                    </span>
                    <span class="sgd-pill-reward urgent-reward">
                        <span class="sgd-theme-text-wow">+1,000 💎 REWARD</span>
                        <span class="sgd-theme-text-genshin">+1,000 ✦ PRIMOGEMS</span>
                        <span class="sgd-theme-text-hearthstone">+1,000 💠 MANA</span>
                    </span>
                    <?php if ( $urgent_count > 0 ): ?>
                        <span class="sgd-pill-count urgent-count"><?php echo $urgent_count; ?> Critical</span>
                    <?php endif; ?>
                </div>
                <span class="sgd-menu-btn-desc">
                    <span class="sgd-theme-text-wow">Emergency jobs anyone could take • "Take Job" & "Finish Job" with photo proof</span>
                    <span class="sgd-theme-text-genshin">High priority domain challenges • Take job & submit proof for rewards</span>
                    <span class="sgd-theme-text-hearthstone">Heroic tavern boss challenges • Slay urgent emergencies for massive rewards</span>
                </span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 4. CASH SHOP BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'shop' ) ); ?>" class="sgd-wow-menu-btn btn-cash-shop">
            <span class="sgd-menu-btn-icon">💎</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label" style="color:#80deea;">
                        <span class="sgd-theme-text-wow">CASH SHOP</span>
                        <span class="sgd-theme-text-genshin">✦ PAIMON'S BARGAINS & SOUVENIRS</span>
                        <span class="sgd-theme-text-hearthstone">💎 TAVERN VAULT & DUST CRAFT</span>
                    </span>
                    <span class="sgd-pill-reward shop-reward">
                        <span class="sgd-theme-text-wow">TRADE DIAMONDS</span>
                        <span class="sgd-theme-text-genshin">TRADE PRIMOGEMS</span>
                        <span class="sgd-theme-text-hearthstone">SPEND MANA</span>
                    </span>
                </div>
                <span class="sgd-menu-btn-desc">IDR 200k Voucher (1k 💎) • 1 Day Paid Leave (20k 💎) • Holiday Trip (100k 💎)</span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- 5. HALL OF LEGENDS & GUILDS BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'leaderboard' ) ); ?>" class="sgd-wow-menu-btn btn-leaderboard" style="background:linear-gradient(180deg, #2b134d 0%, #0f071f 100%); border-color:#9b59b6;">
            <span class="sgd-menu-btn-icon" style="border-color:#9b59b6;">👑</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label" style="color:#e056fd;">
                        <span class="sgd-theme-text-wow">HALL OF LEGENDS & GUILDS</span>
                        <span class="sgd-theme-text-genshin">✦ SPIRAL ABYSS & ADVENTURER RANKINGS</span>
                        <span class="sgd-theme-text-hearthstone">👑 LEGEND LADDER & TAVERN DECKS</span>
                    </span>
                    <span class="sgd-pill-reward" style="background:#8e44ad; border-color:#be2edd;">RANKINGS & SQUADS</span>
                </div>
                <span class="sgd-menu-btn-desc">Guild Standings • Department Squad Vaults • Peer Mana & Kudos (50 💎)</span>
            </div>
            <span class="sgd-menu-btn-arrow" style="color:#e056fd;">➔</span>
        </a>

        <!-- 6. GOBLIN DAILY FORTUNE WHEEL -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'wheel' ) ); ?>" class="sgd-wow-menu-btn btn-wheel" style="background:linear-gradient(180deg, #3d2c07 0%, #171003 100%); border-color:#ffd700;">
            <span class="sgd-menu-btn-icon" style="border-color:#ffd700;">🎰</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label" style="color:#ffd700;">
                        <span class="sgd-theme-text-wow">GOBLIN FORTUNE WHEEL</span>
                        <span class="sgd-theme-text-genshin">✦ STELLA FORTUNA (WISH PRAYER)</span>
                        <span class="sgd-theme-text-hearthstone">🎴 GOLDEN PACK OPENING (WHEEL)</span>
                    </span>
                    <span class="sgd-pill-reward" style="background:#d49a00; color:#000; font-weight:bold;">
                        <span class="sgd-theme-text-wow">1 FREE DAILY SPIN</span>
                        <span class="sgd-theme-text-genshin">1 FREE DAILY WISH</span>
                        <span class="sgd-theme-text-hearthstone">1 FREE DAILY DRAW</span>
                    </span>
                </div>
                <span class="sgd-menu-btn-desc">Spin for +500 💎 Jackpots, Streak Shields, Coffee Tokens & Arcane XP</span>
            </div>
            <span class="sgd-menu-btn-arrow" style="color:#ffd700;">➔</span>
        </a>

        <!-- 7. PEER BOUNTY BOARD -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'bounties' ) ); ?>" class="sgd-wow-menu-btn btn-bounties" style="background:linear-gradient(180deg, #351c08 0%, #140b03 100%); border-color:#ff9f43;">
            <span class="sgd-menu-btn-icon" style="border-color:#ff9f43;">🤝</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="sgd-menu-btn-label" style="color:#ff9f43;">
                        <span class="sgd-theme-text-wow">PEER BOUNTY BOARD</span>
                        <span class="sgd-theme-text-genshin">✦ TEYVAT REPUTATION BOUNTIES</span>
                        <span class="sgd-theme-text-hearthstone">🤝 MERCENARY CONTRACTS & FAVORS</span>
                    </span>
                    <span class="sgd-pill-reward" style="background:#e67e22; border-color:#f39c12;">COMMISSIONS</span>
                </div>
                <span class="sgd-menu-btn-desc">Post a bounty from your own wallet to hire a teammate, or complete favors for bonus diamonds</span>
            </div>
            <span class="sgd-menu-btn-arrow" style="color:#ff9f43;">➔</span>
        </a>

        <!-- 8. LOG IN BUTTON -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'login' ) ); ?>" class="sgd-wow-menu-btn btn-login">
            <span class="sgd-menu-btn-icon">🔑</span>
            <div class="sgd-menu-btn-content">
                <span class="sgd-menu-btn-label"><?php echo $is_logged_in ? 'MY ACCOUNT & LOG IN' : 'LOG IN'; ?></span>
                <span class="sgd-menu-btn-desc"><?php echo $is_logged_in ? 'Logged in as ' . esc_html($current_user->display_name) . ' • 1-Click Demo Switcher • Admin Command' : 'Authenticate hero account • Demo quick-login'; ?></span>
            </div>
            <span class="sgd-menu-btn-arrow">➔</span>
        </a>

        <!-- QUESTS (KANBAN) SHORTCUT -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-wow-menu-btn" style="background:linear-gradient(180deg, #072a38 0%, #03131c 100%); border-color:#00e5ff; box-shadow:0 0 15px rgba(0,229,255,0.25);">
            <span class="sgd-menu-btn-icon" style="border-color:#00e5ff; color:#00e5ff;">🎯</span>
            <div class="sgd-menu-btn-content">
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span class="sgd-menu-btn-label" style="color:#00e5ff;">QUESTS (KANBAN)</span>
                    <span class="sgd-pill-reward" style="background:rgba(0,229,255,0.2); border-color:#00e5ff; color:#00e5ff;">🏢 HQ • 👥 TIM • 🚀 PROYEK</span>
                    <span style="font-family:var(--font-pixel); font-size:8px; background:#00e5ff; color:#03131c; padding:2px 6px; border-radius:3px;">4 KOLOM KANBAN</span>
                </div>
                <span class="sgd-menu-btn-desc">Sistem manajemen tugas: Buat grup tugas (HQ, Tim, Proyek), 4 kolom Kanban (To do list, Dikerjakan, Selesai, Batal), WYSIWYG editor & diskusi obrolan</span>
            </div>
            <span class="sgd-menu-btn-arrow" style="color:#00e5ff;">➔</span>
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

            <!-- ADMIN DIRECT SHORTCUT TO CASH SHOP ORDERS -->
            <a href="<?php echo esc_url( add_query_arg(['sgd_page' => 'admin', 'tab' => 'orders'], home_url('/')) ); ?>" class="sgd-wow-menu-btn" style="background:linear-gradient(180deg, #2b1f07 0%, #110c02 100%); border-color:#ffd700;">
                <span class="sgd-menu-btn-icon" style="border-color:#ffd700;">🎁</span>
                <div class="sgd-menu-btn-content">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="sgd-menu-btn-label" style="color:#ffd700;">CASH SHOP ORDERS & APPROVALS</span>
                        <?php if ( $pending_orders_count > 0 ): ?>
                            <span class="sgd-pill-count" style="background:#e67e22; border-color:#f39c12;"><?php echo $pending_orders_count; ?> Pending</span>
                        <?php endif; ?>
                    </div>
                    <span class="sgd-menu-btn-desc">Review employee reward claims (IDR 200k, leave, holidays), fulfill orders, or refund diamonds</span>
                </div>
                <span class="sgd-menu-btn-arrow" style="color:#ffd700;">➔</span>
            </a>

            <!-- ADMIN DIRECT SHORTCUT TO SUBMITTED PHOTO PROOFS -->
            <a href="<?php echo esc_url( add_query_arg(['sgd_page' => 'admin', 'tab' => 'proofs'], home_url('/')) ); ?>" class="sgd-wow-menu-btn" style="background:linear-gradient(180deg, #172417 0%, #0a140a 100%); border-color:#2ecc71;">
                <span class="sgd-menu-btn-icon" style="border-color:#2ecc71;">📸</span>
                <div class="sgd-menu-btn-content">
                    <span class="sgd-menu-btn-label" style="color:#2ecc71;">REVIEW PHOTO PROOFS</span>
                    <span class="sgd-menu-btn-desc">Inspect all photos and screenshots submitted by employees for completed jobs</span>
                </div>
                <span class="sgd-menu-btn-arrow" style="color:#2ecc71;">➔</span>
            </a>
        <?php endif; ?>

    </div>

    <!-- QUICK DEMO SWITCHER FOOTNOTE -->
    <div class="sgd-menu-demo-helper">
        <span style="font-family:var(--font-pixel); font-size:8px; color:#8fa0bd;">QUICK DEMO ROLE SWITCHER:</span>
        <form method="post" style="display:inline;">
            <input type="hidden" name="sgd_action" value="sgd_switch_user">
            <input type="hidden" name="target_user" value="employee">
            <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-blue">⚔️ Play as Arthas (Emp 1)</button>
        </form>
        <form method="post" style="display:inline;">
            <input type="hidden" name="sgd_action" value="sgd_switch_user">
            <input type="hidden" name="target_user" value="employee2">
            <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-purple" style="background:#8e44ad; border-color:#be2edd; color:#fff;">🧙‍♀️ Play as Jaina (Emp 2)</button>
        </form>
        <form method="post" style="display:inline;">
            <input type="hidden" name="sgd_action" value="sgd_switch_user">
            <input type="hidden" name="target_user" value="admin">
            <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">👑 Play as Admin (Admin123!)</button>
        </form>
    </div>

</div>
