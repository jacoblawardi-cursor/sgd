<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$is_admin = current_user_can( 'administrator' );
$current_user = wp_get_current_user();

// Allow admin to view any employee's achievements
$view_user_id = $current_user->ID;
if ( $is_admin && isset( $_GET['view_employee'] ) ) {
    $view_user_id = (int) $_GET['view_employee'];
}
$view_user = get_user_by( 'id', $view_user_id );
if ( ! $view_user ) {
    $view_user = $current_user;
    $view_user_id = $current_user->ID;
}

$diamonds = sgd_get_diamonds( $view_user_id );

$table_ach = $wpdb->prefix . 'sgd_achievements';
$table_user_ach = $wpdb->prefix . 'sgd_user_achievements';
$table_tasks = $wpdb->prefix . 'sgd_tasks';

$all_achievements = $wpdb->get_results( "SELECT * FROM $table_ach ORDER BY id ASC" );
$unlocked_raw = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_user_ach WHERE user_id = %d", $view_user_id ) );

$unlocked_map = [];
foreach ( $unlocked_raw as $u ) {
    $unlocked_map[ $u->achievement_id ] = $u->unlocked_at;
}

// User stats
$completed_daily = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_tasks WHERE completed_by = %d AND type = 'daily'", $view_user_id ) );
$completed_urgent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_tasks WHERE completed_by = %d AND type = 'urgent'", $view_user_id ) );
$total_unlocked = count( $unlocked_map );
$total_ach = count( $all_achievements );

$all_users = get_users();
?>

<!-- ADMIN COMMAND BAR ON ACHIEVEMENTS PAGE -->
<?php if ( $is_admin ): ?>
    <div style="background:linear-gradient(135deg, #261f0e 0%, #120e06 100%); border:2px solid var(--wow-gold); border-radius:8px; padding:16px 20px; margin-bottom:25px; box-shadow:0 6px 20px rgba(0,0,0,0.8);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
            <div>
                <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; letter-spacing:1px;">
                    👑 GRAND MARSHAL ADMIN CONTROLS (ACHIEVEMENTS SUITE)
                </div>
                <div style="font-size:13px; color:#e0d2b4; margin-top:3px;">
                    Currently inspecting achievements for: <strong style="color:#fff;"><?php echo esc_html( $view_user->display_name ); ?></strong>
                </div>
            </div>

            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <!-- Employee Switcher -->
                <form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                    <input type="hidden" name="sgd_page" value="achievements">
                    <span style="font-family:var(--font-pixel); font-size:8px; color:#aaa;">VIEW HERO:</span>
                    <select name="view_employee" onchange="this.form.submit();" class="sgd-form-select" style="padding:4px 8px; font-size:12px; width:220px;">
                        <?php foreach ( $all_users as $u ): ?>
                            <option value="<?php echo $u->ID; ?>" <?php echo ( $u->ID == $view_user_id ) ? 'selected' : ''; ?>>
                                <?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Link to Full Admin Manager -->
                <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'view_employee' => $view_user_id ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold">
                    ⚙️ Open Full Admin Manager
                </a>
            </div>
        </div>

        <!-- Inline Quick Add Achievement Form Toggle -->
        <details style="margin-top:14px; border-top:1px dashed #4d3d19; padding-top:12px;">
            <summary style="font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light); cursor:pointer;">
                ➕ CLICK TO QUICK-ADD A NEW ACHIEVEMENT DEFINITION
            </summary>
            <form method="post" style="margin-top:12px; background:#0c0e17; padding:15px; border-radius:6px; border:1px solid #443719;">
                <input type="hidden" name="sgd_action" value="sgd_admin_save_achievement">
                <input type="hidden" name="redirect_to" value="achievements">
                <div style="display:grid; grid-template-columns:80px 1fr 140px; gap:10px;">
                    <div>
                        <label class="sgd-form-label">Icon</label>
                        <input type="text" name="icon" class="sgd-form-input" value="🏆" required>
                    </div>
                    <div>
                        <label class="sgd-form-label">Achievement Title</label>
                        <input type="text" name="title" class="sgd-form-input" placeholder="e.g. Bug Master, Fast Delivery" required>
                    </div>
                    <div>
                        <label class="sgd-form-label">Diamond Reward</label>
                        <input type="number" name="reward_diamonds" class="sgd-form-input" value="150" required>
                    </div>
                </div>
                <div class="sgd-form-group" style="margin-top:10px;">
                    <label class="sgd-form-label">Description / Criteria</label>
                    <input type="text" name="description" class="sgd-form-input" placeholder="Explain what the employee did to earn this..." required>
                </div>
                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-green" style="margin-top:6px;">
                    ✨ Create Achievement
                </button>
            </form>
        </details>
    </div>
<?php endif; ?>

<div class="sgd-section-header">
    <div>
        <h1 class="sgd-section-title">
            <span>🏆</span> Feats of Strength & Employee Achievements
        </h1>
        <p style="color:#9cb3c9; font-size:13px; margin-top:4px;">
            Showing achievements for: <strong style="color:#ffd700;"><?php echo esc_html( $view_user->display_name ); ?></strong>
        </p>
    </div>
    <div style="background:#111522; border:2px solid var(--wow-gold); padding:10px 18px; border-radius:6px; display:flex; gap:20px; align-items:center;">
        <div>
            <span style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">UNLOCKED:</span>
            <strong style="color:#ffd700; font-family:var(--font-pixel); font-size:13px; margin-left:4px;"><?php echo $total_unlocked; ?> / <?php echo $total_ach; ?></strong>
        </div>
        <div style="border-left:1px solid #333; padding-left:15px;">
            <span style="font-family:var(--font-pixel); font-size:8px; color:#00bcd4;">TOTAL DIAMONDS:</span>
            <strong style="color:#fff; font-family:var(--font-pixel); font-size:13px; margin-left:4px;"><?php echo number_format($diamonds); ?> 💎</strong>
        </div>
    </div>
</div>

<!-- STATS SUMMARY CARDS -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px;">
    <div style="background:#131726; border:1px solid var(--wow-gold-border); border-radius:6px; padding:15px; text-align:center;">
        <div style="font-size:24px;">📜</div>
        <div style="font-family:var(--font-pixel); font-size:9px; color:#8fa0bd; margin-top:6px;">DAILY COMPLETED</div>
        <div style="font-family:var(--font-title); font-size:20px; color:#fff; font-weight:bold;"><?php echo $completed_daily; ?> Quests</div>
    </div>
    <div style="background:#1a1118; border:1px solid #e74c3c; border-radius:6px; padding:15px; text-align:center;">
        <div style="font-size:24px;">⚡</div>
        <div style="font-family:var(--font-pixel); font-size:9px; color:#ff9999; margin-top:6px;">URGENT BOUNTIES</div>
        <div style="font-family:var(--font-title); font-size:20px; color:#ff7675; font-weight:bold;"><?php echo $completed_urgent; ?> Resolved</div>
    </div>
    <div style="background:#191d12; border:1px solid #2ecc71; border-radius:6px; padding:15px; text-align:center;">
        <div style="font-size:24px;">💎</div>
        <div style="font-family:var(--font-pixel); font-size:9px; color:#a2f5bf; margin-top:6px;">TREASURY BALANCE</div>
        <div style="font-family:var(--font-title); font-size:20px; color:#2ecc71; font-weight:bold;"><?php echo number_format($diamonds); ?> 💎</div>
    </div>
</div>

<!-- QUICK ACCESS TO QUEST REWARD TASKS & GILDED CELEBRATION DEMO -->
<div style="background:#0d111d; border:2px solid var(--wow-gold); border-radius:8px; padding:16px 20px; margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
    <div>
        <div style="font-family:var(--font-pixel); font-size:9px; color:var(--wow-gold-light);">QUEST BOARD ACTIONS & MORALE SUITE:</div>
        <div style="font-size:13px; color:#a8b6cc; margin-top:3px;">Earn diamonds by conquering Daily Duties (+200 💎) or Urgent Bounties (+1,000 💎)! Unlocking achievements triggers the gilded celebration!</div>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <!-- 1-Click Gilded Celebration Fanfare Test / Demo -->
        <form method="post" style="display:inline;">
            <input type="hidden" name="sgd_action" value="sgd_demo_achievement_alert">
            <input type="hidden" name="redirect_to" value="achievements">
            <button type="submit" class="sgd-btn sgd-btn-gold" style="box-shadow:0 0 14px rgba(255,215,0,0.6);" title="Preview the gilded celebration popup and audio fanfare">
                ✨ Preview Gilded Celebration
            </button>
        </form>

        <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-btn sgd-btn-blue">
            📜 DAILY TASK (+200 💎)
        </a>
        <a href="<?php echo esc_url( sgd_get_portal_url( 'urgent' ) ); ?>" class="sgd-btn sgd-btn-red">
            ⚡ URGENT TASK (+1,000 💎)
        </a>
        <a href="<?php echo esc_url( sgd_get_portal_url( 'shop' ) ); ?>" class="sgd-btn sgd-btn-gold">
            💎 CASH SHOP
        </a>
    </div>
</div>

<!-- ACHIEVEMENTS LIST -->
<div class="sgd-ach-grid">
    <?php foreach ( $all_achievements as $ach ): 
        $is_unlocked = isset( $unlocked_map[ $ach->id ] );
        $unlock_date = $is_unlocked ? $unlocked_map[ $ach->id ] : null;
    ?>
        <div class="sgd-ach-card <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>">
            <div class="sgd-ach-icon">
                <?php echo esc_html( $ach->icon ); ?>
            </div>
            <div class="sgd-ach-details">
                <h3 class="sgd-ach-title"><?php echo esc_html( $ach->title ); ?></h3>
                <div class="sgd-ach-desc"><?php echo esc_html( $ach->description ); ?></div>
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; flex-wrap:wrap; gap:6px;">
                    <span style="font-family:var(--font-pixel); font-size:8px; color:#00e5ff;">
                        Reward: +<?php echo (int) $ach->reward_diamonds; ?> 💎
                    </span>
                    <?php if ( $is_unlocked ): ?>
                        <span class="sgd-ach-badge">
                            ✨ UNLOCKED (<?php echo date( 'M d, Y', strtotime( $unlock_date ) ); ?>)
                        </span>
                    <?php else: ?>
                        <span style="font-family:var(--font-pixel); font-size:8px; color:#777;">
                            🔒 LOCKED
                        </span>
                    <?php endif; ?>
                </div>

                <!-- ADMIN 1-CLICK GRANT / REVOKE BUTTONS -->
                <?php if ( $is_admin ): ?>
                    <div style="margin-top:10px; border-top:1px dashed #443719; padding-top:8px; display:flex; justify-content:flex-end;">
                        <?php if ( $is_unlocked ): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Revoke this achievement from <?php echo esc_js($view_user->display_name); ?>?');">
                                <input type="hidden" name="sgd_action" value="sgd_admin_toggle_user_achievement">
                                <input type="hidden" name="target_user_id" value="<?php echo $view_user_id; ?>">
                                <input type="hidden" name="achievement_id" value="<?php echo $ach->id; ?>">
                                <input type="hidden" name="toggle_action" value="revoke">
                                <input type="hidden" name="redirect_to" value="achievements">
                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red" style="font-size:7px; padding:3px 8px;">
                                    ❌ Revoke from Hero
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="sgd_action" value="sgd_admin_toggle_user_achievement">
                                <input type="hidden" name="target_user_id" value="<?php echo $view_user_id; ?>">
                                <input type="hidden" name="achievement_id" value="<?php echo $ach->id; ?>">
                                <input type="hidden" name="toggle_action" value="grant">
                                <input type="hidden" name="award_bonus" value="yes">
                                <input type="hidden" name="redirect_to" value="achievements">
                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-green" style="font-size:7px; padding:3px 8px;">
                                    ✅ Grant to Hero
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>
</div>
