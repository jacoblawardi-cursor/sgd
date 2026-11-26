<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'administrator' ) ) {
    echo '<div class="sgd-flash-banner sgd-flash-error">⛔ Access Denied: Only Grand Marshals (Admins) may enter the Command Hall.</div>';
    return;
}

global $wpdb;
$table_tasks = $wpdb->prefix . 'sgd_tasks';
$table_ach = $wpdb->prefix . 'sgd_achievements';
$table_user_ach = $wpdb->prefix . 'sgd_user_achievements';
$table_redemptions = $wpdb->prefix . 'sgd_redemptions';

// SOS Help requests
$sos_requests = $wpdb->get_results( "SELECT * FROM $table_tasks WHERE help_status = 'requested' ORDER BY need_help_time DESC" );
$dispatched_requests = $wpdb->get_results( "SELECT * FROM $table_tasks WHERE help_status = 'dispatched' ORDER BY id DESC LIMIT 5" );

// All tasks for CRUD
$all_tasks = $wpdb->get_results( "SELECT * FROM $table_tasks ORDER BY type DESC, id DESC" );

// Completed photo proofs (loaded from persistent completion log)
$table_task_logs = $wpdb->prefix . 'sgd_task_logs';
$completed_proofs = $wpdb->get_results(
    "SELECT id, task_id, task_title as title, user_id as completed_by, diamonds_awarded as reward_diamonds, streak_count, streak_bonus, proof_image_url, proof_notes, completed_time 
     FROM $table_task_logs 
     WHERE proof_image_url IS NOT NULL AND proof_image_url != '' 
     ORDER BY completed_time DESC"
);
if ( empty( $completed_proofs ) ) {
    $completed_proofs = $wpdb->get_results( "SELECT * FROM $table_tasks WHERE status = 'completed' AND proof_image_url IS NOT NULL AND proof_image_url != '' ORDER BY completed_time DESC" );
}
$admin_tab = sanitize_text_field( $_GET['admin_tab'] ?? ( $_GET['tab'] ?? '' ) );
$lvl30_candidates = sgd_get_level_30_candidates();

// Cash Shop Redemptions
$all_orders = $wpdb->get_results( "SELECT * FROM $table_redemptions ORDER BY id DESC" );
$pending_orders_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_redemptions WHERE status = 'pending'" );

// All achievements
$achievements = $wpdb->get_results( "SELECT * FROM $table_ach ORDER BY id ASC" );

// All users
$users = get_users();

// Task being edited
$edit_task_id = (int) ( $_GET['edit_task'] ?? 0 );
$edit_task = $edit_task_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_tasks WHERE id = %d", $edit_task_id ) ) : null;

// Achievement definition being edited
$edit_ach_id = (int) ( $_GET['edit_ach'] ?? 0 );
$edit_ach = $edit_ach_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_ach WHERE id = %d", $edit_ach_id ) ) : null;

// Employee being viewed for achievements
$selected_emp_id = (int) ( $_GET['view_employee'] ?? 0 );
if ( ! $selected_emp_id ) {
    foreach ( $users as $u ) {
        if ( in_array( 'employee', (array) $u->roles ) || $u->user_login === 'employee' ) {
            $selected_emp_id = $u->ID;
            break;
        }
    }
    if ( ! $selected_emp_id && ! empty( $users ) ) {
        $selected_emp_id = $users[0]->ID;
    }
}
$selected_emp = get_user_by( 'id', $selected_emp_id );

// Get selected employee's unlocked achievement IDs
$emp_unlocked_raw = $selected_emp_id ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_user_ach WHERE user_id = %d", $selected_emp_id ) ) : [];
$emp_unlocked_map = [];
foreach ( $emp_unlocked_raw as $eu ) {
    $emp_unlocked_map[ $eu->achievement_id ] = $eu->unlocked_at;
}
?>

<div class="sgd-section-header">
    <div>
        <h1 class="sgd-section-title" style="color:#ffd700;">
            <span>🛡️</span> Grand Marshal Command Center (Admin Hall)
        </h1>
        <p style="color:#bda57b; font-size:13px; margin-top:4px;">
            Add/edit/delete daily & urgent quests, see & manage employee achievements, dispatch emergency reinforcements, and review photo proofs.
        </p>
    </div>
    <div style="background:#1e1a0b; border:2px solid var(--wow-gold); padding:8px 16px; border-radius:6px;">
        <span style="font-family:var(--font-pixel); font-size:9px; color:#ffd700;">ROLE:</span>
        <strong style="color:#fff; font-family:var(--font-pixel); font-size:11px; margin-left:6px;">ADMINISTRATOR</strong>
    </div>
</div>

<div class="sgd-admin-panel">
    
    <!-- SUBTAB NAV -->
    <div class="sgd-admin-tab-nav">
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'promotion' || ( empty( $admin_tab ) && ! empty( $lvl30_candidates ) && ! $edit_task && ! $edit_ach ) ) ? 'active' : ''; ?>" data-target="admin-sec-promotion" style="color:#ffd700; border-color:#ffd700;">
            👑 Level 30 Promotions (<?php echo count($lvl30_candidates); ?>)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( ! $edit_task && ! $edit_ach && $admin_tab !== 'proofs' && $admin_tab !== 'promotion' && ! empty( $sos_requests ) && empty( $lvl30_candidates ) ) ? 'active' : ''; ?>" data-target="admin-sec-sos">
            🚨 SOS Help Dispatch (<?php echo count( $sos_requests ); ?>)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $edit_task ) ? 'active' : ''; ?>" data-target="admin-sec-tasks">
            📜 Manage Quests (Add / Edit / Delete)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $edit_ach || isset($_GET['view_employee']) || ( empty( $sos_requests ) && empty( $lvl30_candidates ) && ! $edit_task && $admin_tab !== 'proofs' && $admin_tab !== 'promotion' ) ) ? 'active' : ''; ?>" data-target="admin-sec-achievements">
            🏆 Employee Achievements Manager
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'orders' ) ? 'active' : ''; ?>" data-target="admin-sec-orders">
            🎁 Cash Shop Orders (<?php echo $pending_orders_count; ?>)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'users' ) ? 'active' : ''; ?>" data-target="admin-sec-users" style="color:#00e5ff; border-color:#00e5ff;">
            👥 Hero Roster & Rank Stats (S-F)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'proofs' ) ? 'active' : ''; ?>" data-target="admin-sec-proofs">
            📸 Submitted Photo Proofs (<?php echo count( $completed_proofs ); ?>)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'cron' ) ? 'active' : ''; ?>" data-target="admin-sec-cron">
            🔄 Daily Reset & WP-Cron
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'api' ) ? 'active' : ''; ?>" data-target="admin-sec-api" style="color:#00e5ff; border-color:#00e5ff;">
            🤖 AI & Cicle Automation
        </button>
        <a href="<?php echo esc_url( sgd_get_portal_url( 'laporan-harian' ) ); ?>" class="sgd-admin-subtab" style="color:#00e5ff; border-color:#00e5ff; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            📋 Laporan Harian Proyek (AI Site Reports) ➔
        </a>
    </div>

    <!-- 0. LEVEL 30 PROMOTION CENTER & EMPLOYEE JOB CLASS ADVANCEMENT -->
    <div id="admin-sec-promotion" class="sgd-admin-section" style="<?php echo ( $admin_tab === 'promotion' || ( empty( $admin_tab ) && ! empty( $lvl30_candidates ) && ! $edit_task && ! $edit_ach ) ) ? 'display:block;' : 'display:none;'; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <h3 style="font-family:var(--font-title); font-size:20px; color:#ffd700; margin:0; display:flex; align-items:center; gap:8px;">
                    <span>👑</span> Level 30 Employee Promotions & Job Class Advancement
                </h3>
                <p style="color:#c8d6e5; font-size:13px; margin:4px 0 0 0;">
                    When an employee reaches Level 30, evaluate their leadership with a Special Custom Promotion Task. Once they submit verified proof, officially upgrade their Employee Job Class (e.g. from <strong>Staff</strong> to <strong>Manager</strong>)!
                </p>
            </div>
            <span style="background:#111522; border:2px solid #ffd700; padding:6px 14px; border-radius:6px; font-family:var(--font-pixel); font-size:9px; color:#ffd700;">
                ⭐ ELIGIBLE HEROES: <?php echo count( $lvl30_candidates ); ?>
            </span>
        </div>

        <?php if ( empty( $lvl30_candidates ) ): ?>
            <div style="background:#161c28; border:1px solid #2f3c55; padding:35px; text-align:center; border-radius:8px; color:#8fa0bd;">
                🛡️ No employees have reached Level 30 yet. As heroes complete daily tasks and achievements, their XP rises toward Level 30!
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:20px; margin-bottom:30px;">
                <?php foreach ( $lvl30_candidates as $cand ): 
                    $cand_user_id = $cand['user_id'];
                    $promo_status = $cand['promotion_status'];
                    $promo_task_id = $cand['promotion_task_id'];
                    $promo_task = $promo_task_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_tasks WHERE id = %d", $promo_task_id ) ) : null;
                    $is_manager = ( strtolower( $cand['job_class'] ) === 'manager' );
                ?>
                    <div class="sgd-promo-candidate-card <?php echo $is_manager ? 'promoted-card' : ''; ?>" style="background:#141724; border:2px solid #ffd700; border-radius:10px; padding:22px; box-shadow:0 8px 24px rgba(0,0,0,0.6);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px; border-bottom:1px solid #2d3852; padding-bottom:16px; margin-bottom:18px;">
                            <div style="display:flex; align-items:center; gap:16px;">
                                <div style="width:58px; height:58px; border-radius:50%; border:3px solid #ffd700; background:#0b0d14; display:flex; align-items:center; justify-content:center; font-size:32px; box-shadow:0 0 15px rgba(255,215,0,0.5); position:relative;">
                                    <?php echo esc_html( $cand['avatar'] ); ?>
                                    <span style="position:absolute; bottom:-4px; right:-4px; background:#ffd700; color:#000; font-family:var(--font-pixel); font-size:8px; font-weight:bold; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid #000;">
                                        30
                                    </span>
                                </div>
                                <div>
                                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                        <h4 style="font-family:var(--font-title); font-size:18px; color:#fff; margin:0;">
                                            <?php echo esc_html( $cand['display_name'] ); ?>
                                        </h4>
                                        <span class="sgd-job-class-pill <?php echo $is_manager ? 'sgd-job-class-manager' : 'sgd-job-class-staff'; ?>">
                                            <?php echo ( $is_manager ? '⭐ ' : '🎖️ ' ) . esc_html( $cand['job_class'] ); ?>
                                        </span>
                                    </div>
                                    <div style="font-size:12px; color:#8fa0bd; margin-top:4px;">
                                        Username: <code><?php echo esc_html( $cand['user_login'] ); ?></code> • Total XP: <strong style="color:#00e5ff;"><?php echo number_format( $cand['total_xp'] ); ?> XP</strong> (Level <?php echo $cand['level']; ?>)
                                    </div>
                                </div>
                            </div>

                            <!-- PIPELINE BADGE -->
                            <div>
                                <?php if ( $is_manager || $promo_status === 'promoted' ): ?>
                                    <span class="sgd-status-badge" style="background:#27ae60; color:#fff; font-size:9px; padding:6px 12px;">
                                        ✅ PROMOTED TO MANAGER
                                    </span>
                                <?php elseif ( $promo_status === 'task_completed' ): ?>
                                    <span class="sgd-status-badge" style="background:#be2edd; color:#fff; font-size:9px; padding:6px 12px; animation:pulse-urgent 1.5s infinite;">
                                        🌟 PROMOTION TRIAL COMPLETED (READY TO PROMOTE!)
                                    </span>
                                <?php elseif ( $promo_status === 'task_assigned' ): ?>
                                    <span class="sgd-status-badge" style="background:#e67e22; color:#fff; font-size:9px; padding:6px 12px;">
                                        ⏳ SPECIAL TASK IN PROGRESS
                                    </span>
                                <?php else: ?>
                                    <span class="sgd-status-badge" style="background:#ffd700; color:#000; font-size:9px; font-weight:bold; padding:6px 12px;">
                                        🎓 LEVEL 30 REACHED: ASSIGN TRIAL
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- STAGE 1: ASSIGN SPECIAL CUSTOM TASK -->
                        <?php if ( $promo_status === 'eligible' || ! $promo_task_id ): ?>
                            <div style="background:#0c0f18; border:1px solid #2d3852; border-radius:8px; padding:18px;">
                                <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; margin-bottom:8px;">
                                    📝 STEP 1: ASSIGN SPECIAL LEVEL 30 PROMOTION TASK TO <?php echo strtoupper(esc_html($cand['display_name'])); ?>
                                </div>
                                <p style="font-size:12px; color:#aaa; margin-bottom:14px;">
                                    Give this employee a special leadership or audit task. When they finish and upload photo proof, you will be able to promote them to Manager.
                                </p>

                                <form method="post">
                                    <input type="hidden" name="sgd_action" value="sgd_admin_assign_promotion_task">
                                    <input type="hidden" name="target_user_id" value="<?php echo $cand_user_id; ?>">

                                    <div style="display:grid; grid-template-columns:1fr 180px; gap:15px; margin-bottom:12px;">
                                        <div class="sgd-form-group" style="margin:0;">
                                            <label class="sgd-form-label">Special Promotion Quest Title *</label>
                                            <input type="text" name="title" class="sgd-form-input" value="Special Promotion Trial: Lead Departmental Sprint Review & Quality Audit" required>
                                        </div>
                                        <div class="sgd-form-group" style="margin:0;">
                                            <label class="sgd-form-label">XP Reward</label>
                                            <input type="number" name="reward_xp" class="sgd-form-input" value="1000" required>
                                        </div>
                                    </div>

                                    <div class="sgd-form-group">
                                        <label class="sgd-form-label">Task Deliverables & Leadership Criteria *</label>
                                        <textarea name="description" rows="2" class="sgd-form-textarea" required>Lead the departmental sprint review, audit documentation quality, coordinate deliverables across the squad, and upload photo/screenshot proof of verified completion.</textarea>
                                    </div>

                                    <button type="submit" class="sgd-btn sgd-btn-gold">
                                        🚀 Dispatch Special Level 30 Promotion Task
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- STAGE 2: SPECIAL TASK IN PROGRESS -->
                        <?php if ( $promo_status === 'task_assigned' && $promo_task && $promo_task->status !== 'completed' ): ?>
                            <div style="background:#0c0f18; border:1px solid #e67e22; border-radius:8px; padding:18px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                                    <div style="font-family:var(--font-pixel); font-size:9px; color:#e67e22;">
                                        ⏳ STEP 2: SPECIAL TRIAL IN PROGRESS (AWAITING EMPLOYEE SUBMISSION)
                                    </div>
                                    <span style="font-size:11px; color:#aaa;">Task ID: #<?php echo $promo_task->id; ?></span>
                                </div>
                                <div style="font-size:14px; font-weight:bold; color:#fff;"><?php echo esc_html( $promo_task->title ); ?></div>
                                <div style="font-size:12px; color:#aaa; margin-top:4px;"><?php echo nl2br( esc_html( $promo_task->description ) ); ?></div>
                                <div style="margin-top:12px; font-size:12px; color:#ffd700;">
                                    Reward: <strong>+<?php echo (int) $promo_task->reward_xp; ?> XP ⭐</strong> • Status: <span style="color:#e67e22;">Open (Employee must upload photo proof on their quest board)</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- STAGE 3: SPECIAL TASK COMPLETED -> PROMOTE EMPLOYEE TO MANAGER -->
                        <?php if ( ( $promo_status === 'task_completed' || ( $promo_task && $promo_task->status === 'completed' ) ) && ! $is_manager ): ?>
                            <div style="background:#0c101a; border:2px solid #2ecc71; border-radius:8px; padding:20px; box-shadow:0 0 15px rgba(46,204,113,0.3);">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                                    <div style="font-family:var(--font-pixel); font-size:9px; color:#2ecc71;">
                                        🎉 STEP 3: TRIAL FINISHED WITH VERIFIED PROOF! PROMOTE TO MANAGER:
                                    </div>
                                    <span class="sgd-status-badge" style="background:#27ae60; color:#fff;">✅ PROOF VERIFIED</span>
                                </div>

                                <?php if ( $promo_task && ! empty( $promo_task->proof_image_url ) ): ?>
                                    <div style="background:#111522; border:1px solid #28334a; border-radius:6px; padding:12px; margin-bottom:16px; display:flex; gap:14px; align-items:center;">
                                        <a href="<?php echo esc_url( $promo_task->proof_image_url ); ?>" target="_blank" title="View Full Proof Photo">
                                            <img src="<?php echo esc_url( $promo_task->proof_image_url ); ?>" alt="Proof" style="width:70px; height:70px; object-fit:cover; border-radius:4px; border:1px solid #ffd700;">
                                        </a>
                                        <div>
                                            <div style="font-size:13px; font-weight:bold; color:#fff;">Submitted Photo Proof & Notes:</div>
                                            <div style="font-size:12px; color:#aaa; font-style:italic; margin-top:2px;">
                                                "<?php echo esc_html( $promo_task->proof_notes ?: 'Task finished successfully.' ); ?>"
                                            </div>
                                            <a href="<?php echo esc_url( $promo_task->proof_image_url ); ?>" target="_blank" style="font-size:11px; color:#00e5ff; text-decoration:none; display:inline-block; margin-top:4px;">
                                                🔍 View Full Resolution Proof Photo ➔
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- PROMOTION FORM -->
                                <form method="post" onsubmit="return confirm('Promote <?php echo esc_js($cand['display_name']); ?> and update their Employee Job Class?');">
                                    <input type="hidden" name="sgd_action" value="sgd_admin_change_job_class">
                                    <input type="hidden" name="target_user_id" value="<?php echo $cand_user_id; ?>">

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                                        <div class="sgd-form-group" style="margin:0;">
                                            <label class="sgd-form-label">Promote to Job Class (Select Role) *</label>
                                            <select name="new_job_class_select" class="sgd-form-select">
                                                <option value="Manager" selected>⭐ Manager (Recommended - Full Department Leadership)</option>
                                                <option value="Senior Manager">👑 Senior Manager (Executive Strategist)</option>
                                                <option value="Team Lead">⚡ Team Lead (Squad Commander)</option>
                                                <option value="Assistant Manager">🎖️ Assistant Manager (Junior Officer)</option>
                                                <option value="Director">🛡️ Director / Guild Marshal</option>
                                                <option value="Senior Staff">Senior Staff</option>
                                                <option value="Staff">Staff</option>
                                            </select>
                                        </div>

                                        <div class="sgd-form-group" style="margin:0;">
                                            <label class="sgd-form-label">Custom Job Class Title (Optional Override)</label>
                                            <input type="text" name="custom_job_class" class="sgd-form-input" placeholder="e.g. Operations Manager, Engineering Lead">
                                        </div>
                                    </div>

                                    <button type="submit" class="sgd-btn sgd-btn-gold sgd-btn-lg" style="box-shadow:0 0 15px rgba(255,215,0,0.5);">
                                        👑 Promote Employee & Change Job Class to Manager
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- STAGE 4: ALREADY PROMOTED TO MANAGER -->
                        <?php if ( $is_manager || $promo_status === 'promoted' ): ?>
                            <div style="background:#09120c; border:1px solid #27ae60; border-radius:8px; padding:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                <div>
                                    <div style="color:#2ecc71; font-weight:bold; font-size:14px;">
                                        👑 Promoted to <?php echo esc_html( $cand['job_class'] ); ?>!
                                    </div>
                                    <div style="font-size:12px; color:#8fa0bd; margin-top:2px;">
                                        This employee has mastered Level 30 and holds official Manager job class standing.
                                    </div>
                                </div>
                                
                                <!-- Fast Job Class Re-adjustment -->
                                <form method="post" style="display:flex; gap:8px; align-items:center;">
                                    <input type="hidden" name="sgd_action" value="sgd_admin_change_job_class">
                                    <input type="hidden" name="target_user_id" value="<?php echo $cand_user_id; ?>">
                                    <select name="new_job_class_select" class="sgd-form-select" style="width:160px; font-size:11px; padding:4px 8px;">
                                        <option value="Manager" <?php selected( $cand['job_class'], 'Manager' ); ?>>Manager</option>
                                        <option value="Senior Manager" <?php selected( $cand['job_class'], 'Senior Manager' ); ?>>Senior Manager</option>
                                        <option value="Team Lead" <?php selected( $cand['job_class'], 'Team Lead' ); ?>>Team Lead</option>
                                        <option value="Staff" <?php selected( $cand['job_class'], 'Staff' ); ?>>Staff</option>
                                    </select>
                                    <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-blue">Update Class</button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ALL EMPLOYEES JOB CLASS REGISTRY TABLE -->
        <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin:25px 0 12px 0;">
            📋 All Company Employees & Job Class Registry
        </h3>
        <div style="background:#111420; border:2px solid var(--wow-gold-border); border-radius:8px; overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                <thead>
                    <tr style="background:#090b12; border-bottom:2px solid var(--wow-gold); font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light);">
                        <th style="padding:10px 14px;">HERO</th>
                        <th style="padding:10px 14px;">LEVEL & XP</th>
                        <th style="padding:10px 14px;">CURRENT JOB CLASS</th>
                        <th style="padding:10px 14px;">LEVEL 30 STATUS</th>
                        <th style="padding:10px 14px; text-align:right;">ADMIN CHANGE CLASS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $users as $u ): 
                        $u_xp = sgd_get_user_xp_data( $u->ID );
                        $u_class = sgd_get_user_job_class( $u->ID );
                        $u_avatar = sgd_get_user_avatar( $u->ID );
                        $u_pstatus = get_user_meta( $u->ID, 'sgd_promotion_status', true ) ?: ( $u_xp['level'] >= 30 ? 'eligible' : 'none' );
                    ?>
                        <tr style="border-bottom:1px solid #1f2538;">
                            <td style="padding:10px 14px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:22px;"><?php echo $u_avatar; ?></span>
                                    <div>
                                        <div style="font-weight:bold; color:#fff;"><?php echo esc_html( $u->display_name ); ?></div>
                                        <div style="font-size:11px; color:#8fa0bd;"><?php echo esc_html( $u->user_login ); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:10px 14px;">
                                <strong style="color:#ffd700;">Level <?php echo $u_xp['level']; ?></strong>
                                <div style="font-size:11px; color:#00e5ff;"><?php echo number_format($u_xp['total_xp']); ?> XP</div>
                            </td>
                            <td style="padding:10px 14px;">
                                <span class="sgd-job-class-pill <?php echo strtolower($u_class) === 'manager' ? 'sgd-job-class-manager' : 'sgd-job-class-staff'; ?>">
                                    <?php echo ( strtolower($u_class) === 'manager' ? '⭐ ' : '🎖️ ' ) . esc_html( $u_class ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 14px;">
                                <?php if ( $u_xp['level'] >= 30 ): ?>
                                    <span style="color:#ffd700; font-weight:bold; font-size:12px;">★ Level 30 Master</span>
                                    <div style="font-size:10px; color:#8fa0bd;">Status: <?php echo esc_html($u_pstatus); ?></div>
                                <?php else: ?>
                                    <span style="color:#8fa0bd; font-size:11px;">Level <?php echo $u_xp['level']; ?> / 30</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 14px; text-align:right;">
                                <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                                    <input type="hidden" name="sgd_action" value="sgd_admin_change_job_class">
                                    <input type="hidden" name="target_user_id" value="<?php echo $u->ID; ?>">
                                    <select name="new_job_class_select" class="sgd-form-select" style="font-size:11px; padding:4px 6px; width:130px;">
                                        <option value="Staff" <?php selected( $u_class, 'Staff' ); ?>>Staff</option>
                                        <option value="Senior Staff" <?php selected( $u_class, 'Senior Staff' ); ?>>Senior Staff</option>
                                        <option value="Team Lead" <?php selected( $u_class, 'Team Lead' ); ?>>Team Lead</option>
                                        <option value="Assistant Manager" <?php selected( $u_class, 'Assistant Manager' ); ?>>Assistant Manager</option>
                                        <option value="Manager" <?php selected( $u_class, 'Manager' ); ?>>Manager</option>
                                        <option value="Director" <?php selected( $u_class, 'Director' ); ?>>Director</option>
                                    </select>
                                    <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-blue" style="font-size:8px; padding:4px 8px;">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 1. SOS HELP DISPATCH BOARD -->
    <div id="admin-sec-sos" class="sgd-admin-section" style="<?php echo ( ! $edit_task && ! $edit_ach && ! empty( $sos_requests ) && empty( $lvl30_candidates ) && $admin_tab !== 'promotion' ) ? 'display:block;' : 'display:none;'; ?>">
        <h3 style="font-family:var(--font-title); font-size:18px; color:#ff7675; margin-bottom:15px; display:flex; align-items:center; gap:8px;">
            <span>🚨</span> Emergency Reinforcements Dispatch Board
        </h3>

        <?php if ( empty( $sos_requests ) ): ?>
            <div style="background:#161c28; border:1px solid #2f3c55; padding:20px; text-align:center; border-radius:6px; color:#8fa0bd;">
                ✨ All employees are operating smoothly! No active SOS distress beacons at this time.
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:15px;">
                <?php foreach ( $sos_requests as $sos ): 
                    $emp = get_user_by( 'id', $sos->need_help_by );
                    $emp_name = $emp ? $emp->display_name : 'Unknown Employee';
                    $emp_email = $emp ? $emp->user_email : '';
                ?>
                    <div class="sgd-sos-card">
                        <div class="sgd-sos-info">
                            <h4>SOS BEACON: <?php echo esc_html( $sos->title ); ?></h4>
                            <p style="margin-top:4px;">
                                <strong>Employee Needing Help:</strong> <span style="color:#ffd700;"><?php echo esc_html( $emp_name ); ?></span> (<?php echo esc_html( $emp_email ); ?>)<br>
                                <strong>Alert Timestamp:</strong> <?php echo esc_html( $sos->need_help_time ); ?><br>
                                <strong>Task Type:</strong> <?php echo strtoupper( esc_html( $sos->type ) ); ?> Quest (Reward: +<?php echo (int) $sos->reward_diamonds; ?> 💎)<br>
                                <?php if ( ! empty( $sos->help_notes ) ): ?>
                                    <strong>Employee Blocker Notes:</strong> "<em><?php echo esc_html( $sos->help_notes ); ?></em>"
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- DISPATCH HELP ACTION -->
                        <form method="post" style="display:flex; gap:10px; align-items:center;">
                            <input type="hidden" name="sgd_action" value="sgd_admin_dispatch_help">
                            <input type="hidden" name="task_id" value="<?php echo $sos->id; ?>">
                            <input type="text" name="dispatch_notes" placeholder="e.g. Senior dev dispatched to desk" class="sgd-form-input" style="width:240px; font-size:12px;" required>
                            <button type="submit" class="sgd-btn sgd-btn-green" style="white-space:nowrap;">
                                🛡️ Dispatch Help
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $dispatched_requests ) ): ?>
            <h4 style="font-family:var(--font-title); font-size:14px; color:#2ecc71; margin:25px 0 10px 0;">Recently Dispatched Reinforcements:</h4>
            <div style="font-size:12px; color:#8fa0bd;">
                <?php foreach ( $dispatched_requests as $dis ): ?>
                    <div style="background:#141724; border-left:3px solid #2ecc71; padding:8px 12px; margin-bottom:6px; border-radius:0 4px 4px 0;">
                        <strong><?php echo esc_html( $dis->title ); ?></strong>: <?php echo esc_html( $dis->help_notes ); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. MANAGE TASKS (ADD / EDIT / DELETE DAILY & URGENT) -->
    <div id="admin-sec-tasks" class="sgd-admin-section" style="<?php echo ( $edit_task ) ? 'display:block;' : 'display:none;'; ?>">
        
        <!-- Task Form (Create or Edit) -->
        <div style="background:#161928; border:2px solid var(--wow-gold); border-radius:8px; padding:20px; margin-bottom:30px;">
            <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin-bottom:15px;">
                <?php echo $edit_task ? '✏️ Edit Task: #' . $edit_task->id : '➕ Post New Daily / Urgent Quest'; ?>
            </h3>

            <form method="post">
                <input type="hidden" name="sgd_action" value="sgd_admin_save_task">
                <?php if ( $edit_task ): ?>
                    <input type="hidden" name="task_id" value="<?php echo $edit_task->id; ?>">
                <?php endif; ?>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Quest Type</label>
                        <select name="task_type" class="sgd-form-select" id="sgd-admin-task-type">
                            <option value="daily" <?php echo ( $edit_task && $edit_task->type === 'daily' ) ? 'selected' : ''; ?>>📜 Daily Task (+200 💎)</option>
                            <option value="urgent" <?php echo ( $edit_task && $edit_task->type === 'urgent' ) ? 'selected' : ''; ?>>⚡ Urgent Emergency Bounty (+1,000 💎)</option>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Assign to Specific Employee (Optional)</label>
                        <select name="assigned_to" class="sgd-form-select">
                            <option value="0">🌐 All Employees (General Public Quest)</option>
                            <?php foreach ( $users as $u ): 
                                $is_selected_assignee = false;
                                if ( $edit_task && $edit_task->assigned_to == $u->ID ) {
                                    $is_selected_assignee = true;
                                } elseif ( ! $edit_task && isset($_GET['assign_to_emp']) && (int)$_GET['assign_to_emp'] == $u->ID ) {
                                    $is_selected_assignee = true;
                                }
                            ?>
                                <option value="<?php echo $u->ID; ?>" <?php echo $is_selected_assignee ? 'selected' : ''; ?>>
                                    🎯 Specific: <?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Category / Department</label>
                        <input type="text" name="category" class="sgd-form-input" value="<?php echo esc_attr( $edit_task ? $edit_task->category : 'Operations' ); ?>" required>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Diamond Reward</label>
                        <input type="number" name="reward_diamonds" class="sgd-form-input" value="<?php echo esc_attr( $edit_task ? $edit_task->reward_diamonds : 200 ); ?>" required>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Quest Title</label>
                    <input type="text" name="title" class="sgd-form-input" value="<?php echo esc_attr( $edit_task ? $edit_task->title : '' ); ?>" placeholder="e.g. Server Health Verification" required>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Quest Description & Acceptance Criteria</label>
                    <textarea name="description" rows="3" class="sgd-form-textarea" placeholder="Detail the exact steps and requirements for this task..." required><?php echo esc_textarea( $edit_task ? $edit_task->description : '' ); ?></textarea>
                </div>

                <div class="sgd-form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_recurring" id="admin_is_recurring" value="1" <?php echo ( ! $edit_task || ! empty( $edit_task->is_recurring ) ) ? 'checked' : ''; ?> style="width:18px; height:18px; cursor:pointer;">
                    <label for="admin_is_recurring" style="color:#ffd700; font-size:12px; cursor:pointer; font-weight:bold;">
                        🔄 Recurring Daily Quest (Auto-resets every morning at 00:00 via WP-Cron)
                    </label>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="sgd-btn sgd-btn-gold">
                        <?php echo $edit_task ? '💾 Save Changes' : '📜 Post to Quest Board'; ?>
                    </button>
                    <?php if ( $edit_task ): ?>
                        <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" class="sgd-btn sgd-btn-blue">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Task List Table -->
        <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin-bottom:15px;">
            All Active & Historical Quests
        </h3>

        <div style="background:#111420; border:2px solid var(--wow-gold-border); border-radius:8px; overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                <thead>
                    <tr style="background:#090b12; border-bottom:2px solid var(--wow-gold); font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light);">
                        <th style="padding:10px 14px;">TYPE</th>
                        <th style="padding:10px 14px;">TITLE</th>
                        <th style="padding:10px 14px;">ASSIGNED TO</th>
                        <th style="padding:10px 14px;">REWARD</th>
                        <th style="padding:10px 14px;">STATUS</th>
                        <th style="padding:10px 14px;">HELP BEACON</th>
                        <th style="padding:10px 14px; text-align:right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $all_tasks as $t ): 
                        $assigned_user = ( $t->assigned_to > 0 ) ? get_user_by( 'id', $t->assigned_to ) : null;
                    ?>
                        <tr style="border-bottom:1px solid #1f2538;">
                            <td style="padding:10px 14px;">
                                <span class="sgd-quest-type-tag <?php echo $t->type === 'urgent' ? 'sgd-tag-urgent' : 'sgd-tag-daily'; ?>" style="font-size:7px;">
                                    <?php echo strtoupper( esc_html( $t->type ) ); ?>
                                </span>
                                <?php if ( $t->type === 'daily' && ! empty( $t->is_recurring ) ): ?>
                                    <div style="margin-top:3px;">
                                        <span class="sgd-status-badge" style="background:#1b3a24; border:1px solid #2ecc71; color:#2ecc71; font-size:6px; padding:2px 5px;">🔄 Recurring</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 14px; color:#fff; font-weight:bold;">
                                <?php echo esc_html( $t->title ); ?>
                            </td>
                            <td style="padding:10px 14px;">
                                <?php if ( $assigned_user ): ?>
                                    <span class="sgd-status-badge" style="background:#8e44ad; color:#fff; font-size:7px;">
                                        🎯 <?php echo esc_html( $assigned_user->display_name ); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#8fa0bd; font-size:11px;">🌐 All Heroes</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 14px; color:#00e5ff; font-family:var(--font-pixel); font-size:9px;">
                                +<?php echo (int) $t->reward_diamonds; ?> 💎
                            </td>
                            <td style="padding:10px 14px;">
                                <span class="sgd-status-badge sgd-status-<?php echo esc_attr( $t->status ); ?>">
                                    <?php echo strtoupper( esc_html( $t->status ) ); ?>
                                </span>
                                <?php if ( $t->status === 'completed' && ! empty( $t->proof_image_url ) ): ?>
                                    <div style="margin-top:6px;">
                                        <a href="<?php echo esc_url( $t->proof_image_url ); ?>" target="_blank" class="sgd-btn sgd-btn-sm sgd-btn-green" style="font-size:7px; padding:3px 8px; text-decoration:none;" title="View uploaded photo proof">
                                            📷 View Proof
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 14px;">
                                <?php if ( $t->help_status === 'requested' ): ?>
                                    <span style="color:#ff7675; font-weight:bold;">🚨 SOS ACTIVE</span>
                                <?php elseif ( $t->help_status === 'dispatched' ): ?>
                                    <span style="color:#2ecc71;">🛡️ Dispatched</span>
                                <?php else: ?>
                                    <span style="color:#777;">None</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 14px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'edit_task' => $t->id ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue">
                                        Edit
                                    </a>
                                    <form method="post" onsubmit="return confirm('Permanently delete this quest?');" style="display:inline;">
                                        <input type="hidden" name="sgd_action" value="sgd_admin_delete_task">
                                        <input type="hidden" name="task_id" value="<?php echo $t->id; ?>">
                                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- 3. EMPLOYEE ACHIEVEMENTS MANAGER (SEE, ADD & EDIT ACHIEVEMENTS) -->
    <div id="admin-sec-achievements" class="sgd-admin-section" style="<?php echo ( $edit_ach || isset($_GET['view_employee']) || ( empty( $sos_requests ) && ! $edit_task ) ) ? 'display:block;' : 'display:none;'; ?>">
        
        <!-- TOP: SELECT EMPLOYEE TO INSPECT & EDIT -->
        <div style="background:#171c2c; border:2px solid var(--wow-gold); border-radius:8px; padding:20px; margin-bottom:25px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <div>
                    <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light);">
                        👁️ Inspect & Edit Employee Achievements
                    </h3>
                    <p style="color:#9cb3c9; font-size:12px; margin-top:2px;">
                        Select an employee to see which achievements they hold, grant new ones, or revoke existing ones.
                    </p>
                    <div style="margin-top:4px; font-size:11px; color:#ffd700; font-family:var(--font-pixel);">
                        ✨ MORALE BOOST: Granting an achievement triggers a spectacular Gilded WoW Celebration & Fanfare on their screen!
                    </div>
                </div>
                
                <!-- Employee Selector Form -->
                <form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:flex; gap:10px; align-items:center;">
                    <input type="hidden" name="sgd_page" value="admin">
                    <label style="font-family:var(--font-pixel); font-size:8px; color:#ffd700;">CHOOSE EMPLOYEE:</label>
                    <select name="view_employee" onchange="this.form.submit();" class="sgd-form-select" style="width:260px;">
                        <?php foreach ( $users as $u ): 
                            $u_diamonds = sgd_get_diamonds( $u->ID );
                        ?>
                            <option value="<?php echo $u->ID; ?>" <?php echo ( $u->ID == $selected_emp_id ) ? 'selected' : ''; ?>>
                                <?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>) - <?php echo number_format($u_diamonds); ?> 💎
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- CURRENT EMPLOYEE STATUS CARD -->
            <?php if ( $selected_emp ): 
                $sel_diamonds = sgd_get_diamonds( $selected_emp->ID );
                $sel_unlocked_count = count( $emp_unlocked_map );
                $total_achs = count( $achievements );
            ?>
                <div style="margin-top:15px; background:#0d101a; border:1px solid #333d59; border-radius:6px; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-size:32px;">🧙‍♂️</span>
                        <div>
                            <div style="font-family:var(--font-title); font-size:16px; color:#fff; font-weight:bold;">
                                <?php echo esc_html( $selected_emp->display_name ); ?> (<?php echo esc_html( $selected_emp->user_email ); ?>)
                            </div>
                            <div style="font-size:12px; color:#8fa0bd;">
                                Role: <strong><?php echo implode(', ', $selected_emp->roles); ?></strong> | Treasury: <strong style="color:#00e5ff;"><?php echo number_format($sel_diamonds); ?> 💎</strong>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'assign_to_emp' => $selected_emp_id ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue">
                            ➕ Assign Custom Task to <?php echo esc_html( $selected_emp->display_name ); ?>
                        </a>
                        <div style="background:#191d2c; border:1px solid var(--wow-gold-border); padding:6px 14px; border-radius:4px; font-family:var(--font-pixel); font-size:9px;">
                            <span>UNLOCKED: </span>
                            <strong style="color:#ffd700;"><?php echo $sel_unlocked_count; ?> / <?php echo $total_achs; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- EMPLOYEE ACHIEVEMENTS CHECKLIST / TOGGLE TABLE -->
                <div style="margin-top:18px; background:#111422; border:1px solid #28314a; border-radius:6px; overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                        <thead>
                            <tr style="background:#080a12; border-bottom:2px solid var(--wow-gold); font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light);">
                                <th style="padding:10px 14px;">ICON & TITLE</th>
                                <th style="padding:10px 14px;">DESCRIPTION</th>
                                <th style="padding:10px 14px;">REWARD</th>
                                <th style="padding:10px 14px;">STATUS FOR THIS EMPLOYEE</th>
                                <th style="padding:10px 14px; text-align:right;">ADMIN ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $achievements as $ach ): 
                                $is_unlocked = isset( $emp_unlocked_map[ $ach->id ] );
                                $unlock_time = $is_unlocked ? $emp_unlocked_map[ $ach->id ] : null;
                            ?>
                                <tr style="border-bottom:1px solid #1c2235; <?php echo $is_unlocked ? 'background:rgba(39, 174, 96, 0.08);' : ''; ?>">
                                    <td style="padding:10px 14px; font-weight:bold; color:#fff;">
                                        <span style="font-size:18px; vertical-align:middle; margin-right:6px;"><?php echo esc_html( $ach->icon ); ?></span>
                                        <?php echo esc_html( $ach->title ); ?>
                                    </td>
                                    <td style="padding:10px 14px; color:#8fa0bd; font-size:12px;">
                                        <?php echo esc_html( $ach->description ); ?>
                                    </td>
                                    <td style="padding:10px 14px; color:#00e5ff; font-family:var(--font-pixel); font-size:9px;">
                                        +<?php echo (int) $ach->reward_diamonds; ?> 💎
                                    </td>
                                    <td style="padding:10px 14px;">
                                        <?php if ( $is_unlocked ): ?>
                                            <span class="sgd-status-badge" style="background:#27ae60; color:#fff;">
                                                ✨ UNLOCKED (<?php echo date('M d, Y', strtotime($unlock_time)); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="sgd-status-badge" style="background:#444; color:#aaa;">
                                                🔒 LOCKED
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px; text-align:right;">
                                        <?php if ( $is_unlocked ): ?>
                                            <!-- REVOKE ACTION -->
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Revoke this achievement from employee?');">
                                                <input type="hidden" name="sgd_action" value="sgd_admin_toggle_user_achievement">
                                                <input type="hidden" name="target_user_id" value="<?php echo $selected_emp_id; ?>">
                                                <input type="hidden" name="achievement_id" value="<?php echo $ach->id; ?>">
                                                <input type="hidden" name="toggle_action" value="revoke">
                                                <input type="hidden" name="redirect_to" value="admin">
                                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red">
                                                    ❌ Revoke / Remove
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- GRANT ACTION -->
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="sgd_action" value="sgd_admin_toggle_user_achievement">
                                                <input type="hidden" name="target_user_id" value="<?php echo $selected_emp_id; ?>">
                                                <input type="hidden" name="achievement_id" value="<?php echo $ach->id; ?>">
                                                <input type="hidden" name="toggle_action" value="grant">
                                                <input type="hidden" name="award_bonus" value="yes">
                                                <input type="hidden" name="redirect_to" value="admin">
                                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-green">
                                                    ✅ Grant (+<?php echo (int) $ach->reward_diamonds; ?> 💎)
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- BOTTOM: ADD / EDIT ACHIEVEMENT DEFINITIONS -->
        <div style="background:#161928; border:2px solid var(--wow-gold); border-radius:8px; padding:20px; margin-bottom:25px;">
            <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin-bottom:15px;">
                <?php echo $edit_ach ? '✏️ Edit Achievement Definition: #' . $edit_ach->id : '➕ Create New Achievement Definition'; ?>
            </h3>

            <form method="post">
                <input type="hidden" name="sgd_action" value="sgd_admin_save_achievement">
                <input type="hidden" name="redirect_to" value="admin">
                <?php if ( $edit_ach ): ?>
                    <input type="hidden" name="achievement_id" value="<?php echo $edit_ach->id; ?>">
                <?php endif; ?>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Icon / Emoji</label>
                        <input type="text" name="icon" class="sgd-form-input" value="<?php echo esc_attr( $edit_ach ? $edit_ach->icon : '🏆' ); ?>" placeholder="e.g. 🏆, ⚔️, 🛡️, 🚀, 💻, 🎯" required>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Category</label>
                        <input type="text" name="category" class="sgd-form-input" value="<?php echo esc_attr( $edit_ach ? $edit_ach->category : 'Combat & Performance' ); ?>" required>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Reward Diamonds</label>
                        <input type="number" name="reward_diamonds" class="sgd-form-input" value="<?php echo esc_attr( $edit_ach ? $edit_ach->reward_diamonds : 150 ); ?>" min="0" required>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Achievement Title</label>
                    <input type="text" name="title" class="sgd-form-input" value="<?php echo esc_attr( $edit_ach ? $edit_ach->title : '' ); ?>" placeholder="e.g. Code Grandmaster, Bug Slayer" required>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Description / Criteria</label>
                    <textarea name="description" rows="2" class="sgd-form-textarea" placeholder="Detail how an employee earns this honor..." required><?php echo esc_textarea( $edit_ach ? $edit_ach->description : '' ); ?></textarea>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="sgd-btn sgd-btn-gold">
                        <?php echo $edit_ach ? '💾 Save Changes' : '✨ Add Achievement to Catalog'; ?>
                    </button>
                    <?php if ( $edit_ach ): ?>
                        <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" class="sgd-btn sgd-btn-blue">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ALL SYSTEM ACHIEVEMENTS CATALOG TABLE (EDIT / DELETE DEFINITION) -->
        <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin-bottom:12px;">
            System Achievement Catalog
        </h3>

        <div style="background:#111420; border:2px solid var(--wow-gold-border); border-radius:8px; overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                <thead>
                    <tr style="background:#090b12; border-bottom:2px solid var(--wow-gold); font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light);">
                        <th style="padding:10px 14px;">ICON</th>
                        <th style="padding:10px 14px;">TITLE</th>
                        <th style="padding:10px 14px;">CATEGORY</th>
                        <th style="padding:10px 14px;">BONUS</th>
                        <th style="padding:10px 14px;">UNLOCKED BY</th>
                        <th style="padding:10px 14px; text-align:right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $achievements as $a ): 
                        $unlocked_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_user_ach WHERE achievement_id = %d", $a->id ) );
                    ?>
                        <tr style="border-bottom:1px solid #1f2538;">
                            <td style="padding:10px 14px; font-size:22px;"><?php echo esc_html( $a->icon ); ?></td>
                            <td style="padding:10px 14px; color:#fff; font-weight:bold;">
                                <?php echo esc_html( $a->title ); ?>
                                <div style="font-size:11px; color:#8fa0bd; font-weight:normal;"><?php echo esc_html( $a->description ); ?></div>
                            </td>
                            <td style="padding:10px 14px; color:#c69b3a;"><?php echo esc_html( $a->category ); ?></td>
                            <td style="padding:10px 14px; color:#00e5ff; font-family:var(--font-pixel); font-size:9px;">+<?php echo (int) $a->reward_diamonds; ?> 💎</td>
                            <td style="padding:10px 14px; color:#ffd700; font-family:var(--font-pixel); font-size:9px;"><?php echo $unlocked_count; ?> Heroes</td>
                            <td style="padding:10px 14px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'edit_ach' => $a->id ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue">
                                        Edit
                                    </a>
                                    <form method="post" onsubmit="return confirm('Delete this achievement? It will also be removed from any employees who unlocked it.');" style="display:inline;">
                                        <input type="hidden" name="sgd_action" value="sgd_admin_delete_achievement">
                                        <input type="hidden" name="achievement_id" value="<?php echo $a->id; ?>">
                                        <input type="hidden" name="redirect_to" value="admin">
                                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- 4. EMPLOYEE ROSTER & QUEST RANK (S-F) COMPLETION STATISTICS -->
    <div id="admin-sec-users" class="sgd-admin-section" style="<?php echo ( $admin_tab === 'users' ) ? 'display:block;' : 'display:none;'; ?>">
        
        <?php 
        $all_roster_stats = sgd_get_all_users_quest_rank_stats();
        $admin_ranks_def = sgd_get_quest_ranks();

        // Calculate company-wide totals
        $guild_total_quests = 0;
        $guild_rank_totals = [ 'S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0 ];
        foreach ( $all_roster_stats as $ru ) {
            $guild_total_quests += (int) $ru['total_done'];
            foreach ( $ru['ranks'] as $rk => $cnt ) {
                $guild_rank_totals[ $rk ] = ( $guild_rank_totals[ $rk ] ?? 0 ) + (int) $cnt;
            }
        }
        ?>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:15px;">
            <div>
                <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin:0;">
                    👥 Hero Roster & Quest Rank (S-F) Completion Metrics
                </h3>
                <p style="color:#8fa0bd; font-size:12px; margin-top:3px;">
                    Pantau volume dan distribusi kontribusi tugas setiap hero di Sinar Graha Mandiri berdasarkan tier rank S, A, B, C, D, E, dan F.
                </p>
            </div>
            <div style="background:#111522; border:1px solid var(--wow-gold); padding:4px 12px; border-radius:4px; font-family:var(--font-pixel); font-size:9px; color:#ffd700;">
                TOTAL QUEST SELESAI (SERIKAT): <?php echo number_format( $guild_total_quests ); ?>
            </div>
        </div>

        <!-- GUILD SUMMARY MINI TILES (S TO F) -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; margin-bottom:20px;">
            <?php foreach ( $admin_ranks_def as $rk => $rdata ): ?>
                <div style="background:<?php echo esc_attr( $rdata['badge_bg'] ); ?>; border:1px solid <?php echo esc_attr( $rdata['badge_border'] ); ?>; border-radius:6px; padding:10px 12px; box-shadow:<?php echo esc_attr( $rdata['glow'] ); ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                        <span style="font-size:11px; font-weight:bold; color:<?php echo esc_attr( $rdata['color'] ); ?>;">
                            <?php echo $rdata['icon']; ?> <?php echo $rdata['name']; ?>
                        </span>
                        <span style="font-size:9px; color:#8fa0bd;">Lv.<?php echo $rdata['level_gate']; ?>+</span>
                    </div>
                    <div style="font-size:20px; font-weight:900; color:#fff;">
                        <?php echo number_format( $guild_rank_totals[ $rk ] ?? 0 ); ?>
                        <span style="font-size:10px; font-weight:normal; color:#cbd5e1;">Quest</span>
                    </div>
                    <div style="font-size:9px; color:#8fa0bd; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?php echo esc_html( $rdata['scope'] ); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- USER ROSTER TABLE -->
        <div style="background:#111420; border:2px solid var(--wow-gold-border); border-radius:8px; overflow-x:auto; margin-bottom:25px; box-shadow:0 8px 25px rgba(0,0,0,0.7);">
            <div style="padding:10px 14px; background:#090b12; border-bottom:1px solid #232a3b; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span style="font-family:var(--font-pixel); font-size:9px; color:#ffd700;">
                    ⚔️ DAFTAR KARYAWAN & STATISTIK TIER RANK
                </span>
                <input type="text" id="sgd-admin-user-filter" placeholder="Cari nama karyawan..." onkeyup="sgdFilterUserTable()" style="background:#161928; border:1px solid #3d4a66; border-radius:4px; padding:4px 10px; font-size:11px; color:#fff; width:200px;">
            </div>

            <table id="sgd-admin-users-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:12px;">
                <thead>
                    <tr style="background:#090b12; border-bottom:2px solid var(--wow-gold); font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light);">
                        <th style="padding:10px 12px;">HERO</th>
                        <th style="padding:10px 10px;">ROLE / KELAS</th>
                        <th style="padding:10px 10px; text-align:center;">LEVEL</th>
                        <th style="padding:10px 8px; text-align:center; color:#ff3838;" title="Rank S: Policy making & strategic planning (Gate Lv.20+)">👑 S</th>
                        <th style="padding:10px 8px; text-align:center; color:#ff9f43;" title="Rank A: Independent business & annual leadership (Gate Lv.15+)">⭐ A</th>
                        <th style="padding:10px 8px; text-align:center; color:#be2edd;" title="Rank B: Division quarterly management (Gate Lv.10+)">🔮 B</th>
                        <th style="padding:10px 8px; text-align:center; color:#00e5ff;" title="Rank C: Department monthly management (Gate Lv.8+)">💠 C</th>
                        <th style="padding:10px 8px; text-align:center; color:#2ed573;" title="Rank D: Basic managerial & weekly tasks (Gate Lv.6+)">🌿 D</th>
                        <th style="padding:10px 8px; text-align:center; color:#70a1ff;" title="Rank E: Technical work & task-based (Gate Lv.4+)">📜 E</th>
                        <th style="padding:10px 8px; text-align:center; color:#a4b0be;" title="Rank F: Consistency & routine tasks (Gate Lv.1+)">⚪ F</th>
                        <th style="padding:10px 10px; text-align:center; color:#ffd700;">TOTAL</th>
                        <th style="padding:10px 10px; text-align:center; color:#00e5ff;">SALDO 💎</th>
                        <th style="padding:10px 12px; text-align:right;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $all_roster_stats as $ru ): ?>
                        <tr class="sgd-admin-user-row" style="border-bottom:1px solid #1f2538; transition:background 0.15s ease;">
                            <td style="padding:10px 12px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:20px;"><?php echo $ru['avatar']; ?></span>
                                    <div>
                                        <div style="font-weight:bold; color:#fff; font-size:13px;" class="sgd-user-name">
                                            <?php echo esc_html( $ru['display_name'] ); ?>
                                        </div>
                                        <div style="font-size:10px; color:#8fa0bd;">@<?php echo esc_html( $ru['user_login'] ); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:10px 10px;">
                                <span class="sgd-job-class-pill <?php echo strtolower($ru['job_class']) === 'manager' ? 'sgd-job-class-manager' : 'sgd-job-class-staff'; ?>" style="font-size:9px; padding:2px 6px;">
                                    <?php echo ( strtolower($ru['job_class']) === 'manager' ? '⭐ ' : '🎖️ ' ) . esc_html( $ru['job_class'] ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 10px; text-align:center; font-weight:bold; color:var(--wow-gold-light);">
                                Lv.<?php echo $ru['level']; ?>
                            </td>
                            <td style="padding:10px 8px; text-align:center;">
                                <span style="display:inline-block; min-width:24px; padding:1px 5px; border-radius:3px; background:rgba(255, 56, 56, 0.2); border:1px solid #ff4d4d; color:#ff3838; font-weight:bold; font-size:11px;">
                                    <?php echo (int)( $ru['ranks']['S'] ?? 0 ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 8px; text-align:center;">
                                <span style="display:inline-block; min-width:24px; padding:1px 5px; border-radius:3px; background:rgba(255, 159, 67, 0.2); border:1px solid #ff9f43; color:#ff9f43; font-weight:bold; font-size:11px;">
                                    <?php echo (int)( $ru['ranks']['A'] ?? 0 ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 8px; text-align:center;">
                                <span style="display:inline-block; min-width:24px; padding:1px 5px; border-radius:3px; background:rgba(190, 46, 221, 0.2); border:1px solid #be2edd; color:#be2edd; font-weight:bold; font-size:11px;">
                                    <?php echo (int)( $ru['ranks']['B'] ?? 0 ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 8px; text-align:center;">
                                <span style="display:inline-block; min-width:24px; padding:1px 5px; border-radius:3px; background:rgba(0, 229, 255, 0.2); border:1px solid #00e5ff; color:#00e5ff; font-weight:bold; font-size:11px;">
                                    <?php echo (int)( $ru['ranks']['C'] ?? 0 ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 8px; text-align:center;">
                                <span style="display:inline-block; min-width:24px; padding:1px 5px; border-radius:3px; background:rgba(46, 213, 115, 0.2); border:1px solid #2ed573; color:#2ed573; font-weight:bold; font-size:11px;">
                                    <?php echo (int)( $ru['ranks']['D'] ?? 0 ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 8px; text-align:center;">
                                <span style="display:inline-block; min-width:24px; padding:1px 5px; border-radius:3px; background:rgba(112, 161, 255, 0.2); border:1px solid #70a1ff; color:#70a1ff; font-weight:bold; font-size:11px;">
                                    <?php echo (int)( $ru['ranks']['E'] ?? 0 ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 8px; text-align:center;">
                                <span style="display:inline-block; min-width:24px; padding:1px 5px; border-radius:3px; background:rgba(164, 176, 190, 0.2); border:1px solid #a4b0be; color:#a4b0be; font-weight:bold; font-size:11px;">
                                    <?php echo (int)( $ru['ranks']['F'] ?? 0 ); ?>
                                </span>
                            </td>
                            <td style="padding:10px 10px; text-align:center; font-weight:bold; color:#ffd700; font-size:12px;">
                                <?php echo number_format( $ru['total_done'] ); ?>
                            </td>
                            <td style="padding:10px 10px; text-align:center; font-weight:bold; color:#00e5ff;">
                                <?php echo number_format( $ru['diamonds'] ); ?> 💎
                            </td>
                            <td style="padding:10px 12px; text-align:right;">
                                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-gold" onclick="sgdSelectUserForAdjust('<?php echo $ru['user_id']; ?>');" title="Quick Adjust Balance & XP">
                                    ⚙️ Adjust
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
        function sgdFilterUserTable() {
            var input = document.getElementById('sgd-admin-user-filter');
            if (!input) return;
            var filter = input.value.toLowerCase();
            var rows = document.querySelectorAll('.sgd-admin-user-row');
            rows.forEach(function(row) {
                var nameEl = row.querySelector('.sgd-user-name');
                var text = nameEl ? nameEl.textContent.toLowerCase() : '';
                row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        }
        function sgdSelectUserForAdjust(userId) {
            var sel = document.getElementById('sgd-admin-target-user-select');
            if (sel) {
                sel.value = userId;
                var formTitle = document.getElementById('sgd-adjust-form-title');
                if (formTitle) formTitle.scrollIntoView({ behavior: 'smooth' });
            }
        }
        </script>

        <!-- FORM ADJUST EMPLOYEE DIAMONDS & XP -->
        <h3 id="sgd-adjust-form-title" style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin-bottom:15px;">
            💎 Adjust Employee Diamond Balances & Hero Stats
        </h3>

        <div style="background:#161928; border:2px solid var(--wow-gold); border-radius:8px; padding:20px; max-width:650px;">
            <form method="post">
                <input type="hidden" name="sgd_action" value="sgd_admin_update_user">

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Select Employee Hero</label>
                    <select name="target_user_id" id="sgd-admin-target-user-select" class="sgd-form-select">
                        <?php foreach ( $users as $u ): 
                            $u_diamonds = sgd_get_diamonds( $u->ID );
                        ?>
                            <option value="<?php echo $u->ID; ?>">
                                <?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>) - Current: <?php echo number_format($u_diamonds); ?> 💎
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Diamond Adjustment Action</label>
                        <select name="change_mode" class="sgd-form-select">
                            <option value="add">➕ Add Diamonds</option>
                            <option value="deduct">➖ Deduct Diamonds</option>
                            <option value="set">⚙️ Set Exact Balance</option>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Diamond Amount</label>
                        <input type="number" name="diamond_amount" class="sgd-form-input" value="0" min="0">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-top:10px; border-top:1px dashed #2f3b55; padding-top:10px;">
                    <div class="sgd-form-group">
                        <label class="sgd-form-label">⭐ XP Adjustment Action</label>
                        <select name="xp_mode" class="sgd-form-select">
                            <option value="add">➕ Add XP</option>
                            <option value="deduct">➖ Deduct XP</option>
                            <option value="set">⚙️ Set Exact XP</option>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">XP Amount (Level 30 floor = 10,092 XP)</label>
                        <input type="number" name="xp_amount" class="sgd-form-input" value="0" min="0">
                    </div>
                </div>

                <div class="sgd-form-group" style="margin-top:10px; border-top:1px dashed #2f3b55; padding-top:10px;">
                    <label class="sgd-form-label">🎖️ Change Employee Job Class</label>
                    <select name="job_class" class="sgd-form-select">
                        <option value="">-- Keep Current Job Class --</option>
                        <option value="Staff">Staff</option>
                        <option value="Senior Staff">Senior Staff</option>
                        <option value="Team Lead">Team Lead</option>
                        <option value="Assistant Manager">Assistant Manager</option>
                        <option value="Manager">⭐ Manager</option>
                        <option value="Senior Manager">Senior Manager</option>
                        <option value="Director">Director</option>
                    </select>
                </div>

                <button type="submit" class="sgd-btn sgd-btn-gold">
                    💾 Update Hero Balance
                </button>
            </form>
        </div>
    </div>

    <!-- 4. CASH SHOP REDEMPTION ORDERS BOARD -->
    <div id="admin-sec-orders" class="sgd-admin-section" style="<?php echo ( $admin_tab === 'orders' ) ? 'display:block;' : 'display:none;'; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
            <div>
                <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light);">
                    🎁 Goblin Cash Shop Redemption Requests & Approvals
                </h3>
                <p style="color:#8fa0bd; font-size:12px; margin-top:2px;">
                    Review employee reward redemptions (cash vouchers, paid leaves, holiday trips), fulfill requests, or reject with automatic diamond refund.
                </p>
            </div>
            <span style="background:#111522; border:1px solid var(--wow-gold); padding:4px 10px; border-radius:4px; font-family:var(--font-pixel); font-size:9px; color:#ffd700;">
                PENDING APPROVAL: <?php echo $pending_orders_count; ?>
            </span>
        </div>

        <?php if ( empty( $all_orders ) ): ?>
            <div style="background:#161c28; border:1px solid #2f3c55; padding:35px; text-align:center; border-radius:6px; color:#8fa0bd;">
                🎁 No redemption orders have been placed yet. When employees trade diamonds in the Cash Shop, their orders appear here for processing!
            </div>
        <?php else: ?>
            <div style="background:#111420; border:2px solid var(--wow-gold-border); border-radius:8px; overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                    <thead>
                        <tr style="background:#090b12; border-bottom:2px solid var(--wow-gold); font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light);">
                            <th style="padding:10px 14px;">ORDER #</th>
                            <th style="padding:10px 14px;">DATE</th>
                            <th style="padding:10px 14px;">EMPLOYEE</th>
                            <th style="padding:10px 14px;">REWARD ITEM</th>
                            <th style="padding:10px 14px;">DIAMONDS</th>
                            <th style="padding:10px 14px;">STATUS</th>
                            <th style="padding:10px 14px;">EMPLOYEE NOTES</th>
                            <th style="padding:10px 14px; text-align:right;">ADMIN ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_orders as $ord ): 
                            $order_emp = get_user_by( 'id', $ord->user_id );
                            $is_pending = ( $ord->status === 'pending' );
                            $is_approved = ( $ord->status === 'approved' );
                        ?>
                            <tr style="border-bottom:1px solid #1f2538; <?php echo $is_pending ? 'background:rgba(230, 126, 34, 0.08);' : ''; ?>">
                                <td style="padding:10px 14px; font-family:var(--font-pixel); font-size:9px; color:#8fa0bd;">
                                    #<?php echo $ord->id; ?>
                                </td>
                                <td style="padding:10px 14px; color:#ddd; font-size:12px; white-space:nowrap;">
                                    <?php echo esc_html( $ord->created_at ); ?>
                                </td>
                                <td style="padding:10px 14px; font-weight:bold; color:#fff;">
                                    <?php echo $order_emp ? esc_html( $order_emp->display_name ) : 'Employee #' . $ord->user_id; ?>
                                    <div style="font-size:11px; color:#8fa0bd; font-weight:normal;"><?php echo $order_emp ? esc_html( $order_emp->user_email ) : ''; ?></div>
                                </td>
                                <td style="padding:10px 14px; color:#ffd700; font-weight:bold;">
                                    <?php echo esc_html( $ord->item_name ); ?>
                                </td>
                                <td style="padding:10px 14px; color:#00e5ff; font-family:var(--font-pixel); font-size:9px; white-space:nowrap;">
                                    -<?php echo number_format( $ord->diamonds_spent ); ?> 💎
                                </td>
                                <td style="padding:10px 14px;">
                                    <?php if ( $is_pending ): ?>
                                        <span class="sgd-status-badge" style="background:#e67e22; color:#fff; animation:pulse-urgent 1.5s infinite;">
                                            ⏳ PENDING
                                        </span>
                                    <?php elseif ( $is_approved ): ?>
                                        <span class="sgd-status-badge" style="background:#27ae60; color:#fff;">
                                            ✅ FULFILLED
                                        </span>
                                    <?php else: ?>
                                        <span class="sgd-status-badge" style="background:#c0392b; color:#fff;">
                                            ❌ REJECTED
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 14px; font-size:12px; color:#ccc;">
                                    <?php echo esc_html( $ord->notes ? $ord->notes : '-' ); ?>
                                </td>
                                <td style="padding:10px 14px; text-align:right;">
                                    <?php if ( $is_pending ): ?>
                                        <div style="display:inline-flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                            <!-- APPROVE ACTION -->
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Approve and fulfill this Cash Shop order?');">
                                                <input type="hidden" name="sgd_action" value="sgd_admin_approve_redemption">
                                                <input type="hidden" name="order_id" value="<?php echo $ord->id; ?>">
                                                <input type="hidden" name="admin_notes" value="Approved by Admin">
                                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-green" style="font-size:7px; padding:4px 8px;">
                                                    ✅ Approve & Fulfill
                                                </button>
                                            </form>

                                            <!-- REJECT ACTION (REFUNDS DIAMONDS) -->
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Reject order and REFUND <?php echo number_format($ord->diamonds_spent); ?> diamonds back to employee?');">
                                                <input type="hidden" name="sgd_action" value="sgd_admin_reject_redemption">
                                                <input type="hidden" name="order_id" value="<?php echo $ord->id; ?>">
                                                <input type="hidden" name="admin_notes" value="Declined by Admin">
                                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red" style="font-size:7px; padding:4px 8px;">
                                                    ❌ Reject & Refund
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#777; font-size:11px;">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 5. SUBMITTED PHOTO PROOFS GALLERY -->
    <div id="admin-sec-proofs" class="sgd-admin-section" style="<?php echo ( $admin_tab === 'proofs' ) ? 'display:block;' : 'display:none;'; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
            <div>
                <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light);">
                    📸 Verified Photo Proof Gallery (Completed Jobs)
                </h3>
                <p style="color:#8fa0bd; font-size:12px; margin-top:2px;">
                    Review photos and screenshots submitted by employees as proof of finished daily and urgent tasks.
                </p>
            </div>
            <span style="background:#111522; border:1px solid var(--wow-gold); padding:4px 10px; border-radius:4px; font-family:var(--font-pixel); font-size:9px; color:#ffd700;">
                TOTAL PROOFS: <?php echo count( $completed_proofs ); ?>
            </span>
        </div>

        <?php if ( empty( $completed_proofs ) ): ?>
            <div style="background:#161c28; border:1px solid #2f3c55; padding:35px; text-align:center; border-radius:6px; color:#8fa0bd;">
                📷 No photo proofs uploaded yet. When an employee completes a job and uploads their photo proof, it will appear here for verification!
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
                <?php foreach ( $completed_proofs as $cp ): 
                    $cp_user = get_user_by( 'id', $cp->completed_by );
                ?>
                    <div style="background:#151824; border:2px solid var(--wow-gold-border); border-radius:8px; padding:15px; display:flex; flex-direction:column; justify-content:space-between; gap:10px; box-shadow:0 6px 16px rgba(0,0,0,0.7);">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <span class="sgd-quest-type-tag <?php echo $cp->type === 'urgent' ? 'sgd-tag-urgent' : 'sgd-tag-daily'; ?>" style="font-size:7px;">
                                    <?php echo strtoupper( esc_html( $cp->type ) ); ?> TASK
                                </span>
                                <span style="font-family:var(--font-pixel); font-size:9px; color:#00e5ff;">
                                    +<?php echo (int) $cp->reward_diamonds; ?> 💎
                                </span>
                            </div>

                            <a href="<?php echo esc_url( $cp->proof_image_url ); ?>" target="_blank" title="Click to view full image in new tab">
                                <img src="<?php echo esc_url( $cp->proof_image_url ); ?>" alt="Proof" style="width:100%; height:180px; object-fit:cover; border-radius:6px; border:2px solid var(--wow-gold-border); box-shadow:0 4px 10px rgba(0,0,0,0.8); transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            </a>

                            <div style="font-family:var(--font-title); font-weight:bold; color:#fff; font-size:15px; margin-top:8px;">
                                <?php echo esc_html( $cp->title ); ?>
                            </div>

                            <div style="font-size:12px; color:#8fa0bd; margin-top:4px;">
                                Completed by: <strong style="color:#ffd700;"><?php echo $cp_user ? esc_html( $cp_user->display_name ) : 'Employee'; ?></strong><br>
                                Submitted: <span style="color:#ddd;"><?php echo esc_html( $cp->completed_time ); ?></span>
                            </div>

                            <?php if ( ! empty( $cp->proof_notes ) ): ?>
                                <div style="font-style:italic; font-size:12px; color:#eee; background:#0c0e14; border:1px solid #222; padding:8px; border-radius:4px; margin-top:8px;">
                                    "<?php echo esc_html( $cp->proof_notes ); ?>"
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top:10px; border-top:1px solid #222a3d; padding-top:10px; display:flex; justify-content:space-between; align-items:center;">
                            <span class="sgd-status-badge sgd-status-completed" style="font-size:7px;">
                                ✅ VERIFIED COMPLETE
                            </span>
                            <a href="<?php echo esc_url( $cp->proof_image_url ); ?>" target="_blank" class="sgd-btn sgd-btn-sm sgd-btn-gold">
                                🔍 Open Full Size
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 7. DAILY RESET & WP-CRON AUTOMATION SECTION -->
    <div id="admin-sec-cron" class="sgd-admin-section" style="<?php echo ( $admin_tab === 'cron' ) ? 'display:block;' : 'display:none;'; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin:0;">
                    🔄 Daily Recurring Quest Automation & WP-Cron Scheduler
                </h3>
                <p style="color:#9cb3c9; font-size:13px; margin:4px 0 0 0;">
                    Automated maintenance engine that resets daily quests every midnight while keeping streak history and photo submissions intact.
                </p>
            </div>
            <form method="post" onsubmit="return confirm('Execute immediate Daily Quest Reset? This will reopen all recurring daily quests for today.');">
                <input type="hidden" name="sgd_action" value="sgd_admin_manual_daily_reset">
                <button type="submit" class="sgd-btn sgd-btn-gold">
                    🔄 Run Daily Reset Now (Reopen Tasks)
                </button>
            </form>
        </div>

        <?php 
        $next_cron = wp_next_scheduled( 'sgd_daily_quest_reset_cron' );
        $last_reset = get_option( 'sgd_last_daily_reset', 'Not yet executed today' );
        $recurring_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks WHERE type = 'daily' AND is_recurring = 1" );
        ?>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:15px; margin-bottom:25px;">
            <div style="background:#111522; border:2px solid var(--wow-gold-border); border-radius:8px; padding:16px;">
                <div style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">AUTOMATION ENGINE</div>
                <div style="font-family:var(--font-title); font-size:16px; color:#2ecc71; margin-top:4px; font-weight:bold;">
                    🟢 WP-Cron Active
                </div>
                <div style="font-size:12px; color:#8fa0bd; margin-top:4px;">
                    Hook: <code>sgd_daily_quest_reset_cron</code> (Daily)
                </div>
            </div>

            <div style="background:#111522; border:2px solid var(--wow-gold-border); border-radius:8px; padding:16px;">
                <div style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">NEXT SCHEDULED RUN</div>
                <div style="font-family:var(--font-title); font-size:16px; color:#00e5ff; margin-top:4px; font-weight:bold;">
                    <?php echo $next_cron ? date( 'Y-m-d H:i:s T', $next_cron ) : 'Scheduled on Next Request'; ?>
                </div>
                <div style="font-size:12px; color:#8fa0bd; margin-top:4px;">
                    Target: Midnight (00:00:00)
                </div>
            </div>

            <div style="background:#111522; border:2px solid var(--wow-gold-border); border-radius:8px; padding:16px;">
                <div style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">LAST DAILY RESET</div>
                <div style="font-family:var(--font-title); font-size:15px; color:#fff; margin-top:4px;">
                    <?php echo esc_html( $last_reset ); ?>
                </div>
                <div style="font-size:12px; color:#8fa0bd; margin-top:4px;">
                    Refreshes all completed recurring daily quests
                </div>
            </div>

            <div style="background:#111522; border:2px solid var(--wow-gold-border); border-radius:8px; padding:16px;">
                <div style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">RECURRING QUESTS</div>
                <div style="font-family:var(--font-title); font-size:16px; color:#ffd700; margin-top:4px; font-weight:bold;">
                    <?php echo $recurring_count; ?> Daily Tasks
                </div>
                <div style="font-size:12px; color:#8fa0bd; margin-top:4px;">
                    Marked with <code>is_recurring = 1</code>
                </div>
            </div>
        </div>

        <div style="background:#151824; border:1px solid #2e3b55; border-radius:8px; padding:18px; color:#c8d6e5; font-size:13px; line-height:1.6;">
            <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; margin-bottom:8px;">
                📜 HOW DAILY QUEST AUTOMATION & STREAKS WORK:
            </div>
            <ul style="margin:0; padding-left:20px;">
                <li><strong>Nightly Reset:</strong> At midnight, WP-Cron automatically resets all recurring daily tasks back to <code>open</code> status so heroes find fresh work every morning.</li>
                <li><strong>Photo Proof Preservation:</strong> All submitted employee photo proofs and completed timestamps are archived permanently in the <code>sgd_task_logs</code> audit ledger, so past approvals and photos are never deleted during resets.</li>
                <li><strong>Consistency Streaks:</strong> When an employee finishes a daily task on consecutive days, their streak advances (Day 1 ➔ Day 2 (+10%) ➔ Day 3 (+20%) ➔ Day 4 (+30%) ➔ Day 5+ (+50% 🔥 MAX BONUS)). Missing a calendar day safely resets the streak back to Day 1.</li>
            </ul>
        </div>
    </div>

    <!-- 8. AI & CICLE REST API & WEBHOOK AUTOMATION -->
    <?php
    $api_key = sgd_get_api_key();
    $rest_base_pretty = home_url( '/wp-json/sgd/v1' );
    $rest_base_plain  = home_url( '/?rest_route=/sgd/v1' );
    $cicle_webhook    = home_url( '/wp-json/sgd/v1/webhook/cicle?api_key=' . $api_key );
    ?>
    <div id="admin-sec-api" class="sgd-admin-section" style="<?php echo ( $admin_tab === 'api' ) ? 'display:block;' : 'display:none;'; ?>">
        <h3 style="font-family:var(--font-title); font-size:18px; color:#00e5ff; margin-bottom:15px; display:flex; align-items:center; gap:8px;">
            <span>🤖</span> AI Assistant & Cicle External Automation Engine
        </h3>
        <p style="color:#c8d6e5; font-size:13px; line-height:1.5; margin-bottom:20px;">
            Connect your manager's <strong>Cicle project management boards</strong> or an <strong>AI automation agent (OpenAI, Claude, DeepSeek, or Python script)</strong>. The AI can automatically read daily tasks from Cicle, break them down into gamified daily quests, and assign them directly to each employee in this portal with custom diamond rewards!
        </p>

        <!-- GMAIL AI QUEST BOT CONTROL PANEL -->
        <?php 
        $bot_cfg = function_exists('sgd_get_email_bot_config') ? sgd_get_email_bot_config() : ['email' => 'sgdquest@gmail.com'];
        $last_sync_time = get_option( 'sgd_email_bot_last_sync', 'Belum pernah dicek' );
        $last_sync_count = (int) get_option( 'sgd_email_bot_last_count', 0 );
        $all_teams_list = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}sgd_quest_teams ORDER BY id ASC" );
        ?>
        <div style="background:linear-gradient(135deg, #0d1b2a 0%, #111e38 100%); border:2px solid #00e5ff; border-radius:10px; padding:20px; margin-bottom:24px; box-shadow:0 0 20px rgba(0, 229, 255, 0.15);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:15px; border-bottom:1px solid rgba(0,229,255,0.25); padding-bottom:12px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:24px;">📬</span>
                    <div>
                        <h4 style="font-family:var(--font-title); font-size:16px; color:#00e5ff; margin:0;">
                            Gmail AI Quest Bot (<span style="color:#ffd700;"><?php echo esc_html( $bot_cfg['email'] ); ?></span>)
                        </h4>
                        <div style="font-size:11px; color:#8fa0bd; margin-top:2px;">
                            Kirim email instruksi/tugas ke <strong><?php echo esc_html( $bot_cfg['email'] ); ?></strong> ➔ AI Gemini otomatis mengubahnya jadi kartu tugas Kanban!
                        </div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="background:rgba(46, 204, 113, 0.2); border:1px solid #2ecc71; color:#2ecc71; padding:4px 10px; border-radius:12px; font-size:10px; font-weight:bold; font-family:var(--font-pixel);">
                        🟢 IMAP SSL READY
                    </span>
                    <span style="background:rgba(0, 229, 255, 0.2); border:1px solid #00e5ff; color:#00e5ff; padding:4px 10px; border-radius:12px; font-size:10px; font-weight:bold; font-family:var(--font-pixel);">
                        ✨ GEMINI AI ACTIVE
                    </span>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:15px; margin-bottom:18px;">
                <div style="background:rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.08); border-radius:6px; padding:12px;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#8fa0bd; margin-bottom:4px;">KOTAK MASUK TUGAS:</div>
                    <code style="color:#ffd700; font-size:13px; font-weight:bold;"><?php echo esc_html( $bot_cfg['email'] ); ?></code>
                    <div style="font-size:10px; color:#7bed9f; margin-top:4px;">✔ App Password 16-karakter Terkonfigurasi</div>
                </div>

                <div style="background:rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.08); border-radius:6px; padding:12px;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#8fa0bd; margin-bottom:4px;">AI PARSING ENGINE:</div>
                    <div style="color:#00e5ff; font-size:12px; font-weight:bold;">Google Gemini Flash ⚡</div>
                    <div style="font-size:10px; color:#c8d6e5; margin-top:4px;">Otomatis skala Rank Quest S/A/B/C/D/E/F & Hadiah XP/Gold</div>
                </div>

                <div style="background:rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.08); border-radius:6px; padding:12px;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#8fa0bd; margin-bottom:4px;">STATUS SINKRONISASI:</div>
                    <div style="color:#ffffff; font-size:11px;">Terakhir cek: <strong><?php echo esc_html( $last_sync_time ); ?></strong></div>
                    <div style="font-size:10px; color:#8fa0bd; margin-top:4px;">Background Auto-Check: Tiap 5 Menit via WP-Cron</div>
                </div>
            </div>

            <!-- SYNC NOW TRIGGER FORM -->
            <form method="post" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; background:rgba(0, 229, 255, 0.04); border:1px dashed rgba(0, 229, 255, 0.3); border-radius:8px; padding:12px 16px;">
                <input type="hidden" name="sgd_action" value="sgd_sync_email_tasks">
                <div style="font-size:12px; color:#c8d6e5;">
                    💡 <em>Kirim email dari HP atau laptop Anda ke <strong><?php echo esc_html( $bot_cfg['email'] ); ?></strong>, lalu klik tombol di samping untuk segera membuat kartu tugas:</em>
                </div>
                <button type="submit" class="sgd-btn sgd-btn-blue" style="padding:8px 20px; font-weight:bold; font-size:12px; display:inline-flex; align-items:center; gap:8px;">
                    <span>🔄</span> Cek & Proses Email Sekarang
                </button>
            </form>
        </div>

        <!-- API CREDENTIALS & ENDPOINTS -->
        <div style="background:#111522; border:2px solid var(--wow-gold-border); border-radius:10px; padding:20px; margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <span style="font-family:var(--font-pixel); font-size:9px; color:#c69b3a;">SECRET API AUTHENTICATION KEY:</span>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:6px;">
                        <code id="sgd-api-key-val" style="background:#090c14; border:1px solid #3c4866; border-radius:4px; padding:6px 14px; color:#00e5ff; font-family:var(--font-pixel); font-size:11px; letter-spacing:1px;">
                            <?php echo esc_html( $api_key ); ?>
                        </code>
                        <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" onclick="navigator.clipboard.writeText('<?php echo esc_js($api_key); ?>'); alert('📋 API Key copied to clipboard!');" style="font-size:8px;">
                            📋 Copy Key
                        </button>
                    </div>
                </div>

                <form method="post" onsubmit="return confirm('Regenerate API Secret Key? Any existing AI script or Cicle webhook will need to be updated with the new key!');">
                    <input type="hidden" name="sgd_action" value="sgd_regenerate_api_key">
                    <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red" style="font-size:8px;">
                        ⚡ Regenerate API Key
                    </button>
                </form>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:15px; margin-top:15px; border-top:1px dashed #28334a; padding-top:15px;">
                <div style="background:#090c14; border:1px solid #1f273b; border-radius:6px; padding:12px;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#2ecc71; margin-bottom:4px;">📥 1. CREATE QUEST ENDPOINT (POST):</div>
                    <code style="color:#fff; font-size:11px; word-break:break-all;"><?php echo esc_html( $rest_base_pretty . '/tasks' ); ?></code>
                    <div style="font-size:10px; color:#8fa0bd; margin-top:4px;">Fallback: <code><?php echo esc_html( $rest_base_plain . '/tasks' ); ?></code></div>
                </div>

                <div style="background:#090c14; border:1px solid #1f273b; border-radius:6px; padding:12px;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#be2edd; margin-bottom:4px;">👥 2. EMPLOYEE DISCOVERY ENDPOINT (GET):</div>
                    <code style="color:#fff; font-size:11px; word-break:break-all;"><?php echo esc_html( $rest_base_pretty . '/employees' ); ?></code>
                    <div style="font-size:10px; color:#8fa0bd; margin-top:4px;">AI calls this to get team members, user IDs, and levels</div>
                </div>

                <div style="background:#090c14; border:1px solid #1f273b; border-radius:6px; padding:12px; grid-column:span 1;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#ff9f43; margin-bottom:4px;">⚡ 3. CICLE DIRECT WEBHOOK URL (POST):</div>
                    <code style="color:#fff; font-size:11px; word-break:break-all;"><?php echo esc_html( $cicle_webhook ); ?></code>
                    <div style="font-size:10px; color:#8fa0bd; margin-top:4px;">Paste this URL in Cicle webhooks to trigger quest creation automatically on card events</div>
                </div>
            </div>
        </div>

        <!-- LIVE TEST DISPATCHER -->
        <div style="background:#141724; border:2px solid var(--wow-gold-border); border-radius:10px; padding:20px; margin-bottom:24px;">
            <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; margin-bottom:6px;">
                🚀 TEST DISPATCHER (SIMULATE AI / CICLE TASK CREATION)
            </div>
            <p style="color:#aaa; font-size:12px; margin-bottom:15px;">
                Test creating an assigned daily task right now to see it appear on the employee's quest board:
            </p>

            <form method="post">
                <input type="hidden" name="sgd_action" value="sgd_admin_test_dispatch_task">

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">
                    <div class="sgd-form-group">
                        <label class="sgd-form-label">🎯 Assign to Hero *</label>
                        <select name="assigned_to" class="sgd-form-select">
                            <option value="0">🌐 Open to All Employees</option>
                            <?php foreach ( $users as $u ): ?>
                                <option value="<?php echo $u->ID; ?>" <?php echo ($u->user_login === 'employee') ? 'selected' : ''; ?>>
                                    👤 <?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Category / Sprint</label>
                        <input type="text" name="task_category" class="sgd-form-input" value="Cicle Sprint Alpha" required>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Reward (Diamonds)</label>
                        <input type="number" name="reward_diamonds" class="sgd-form-input" value="200" required>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Quest Title *</label>
                    <input type="text" name="task_title" class="sgd-form-input" placeholder="e.g. Implement Cicle Card #412: Safari Mobile Checkout" required>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Quest Description & Deliverables</label>
                    <textarea name="task_desc" rows="2" class="sgd-form-textarea" placeholder="Instructions from Cicle card or AI sprint breakdown..."></textarea>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Cicle Card URL (Optional Link)</label>
                    <input type="url" name="external_url" class="sgd-form-input" placeholder="https://cicle.app/your-company/companies/1/teams/2/boards/cards/1234">
                </div>

                <button type="submit" class="sgd-btn sgd-btn-gold">
                    🤖 Dispatch Test Quest to Employee
                </button>
            </form>
        </div>

        <!-- AI AGENT TRAINING GUIDE & CODE SNIPPETS -->
        <div style="background:#090c14; border:1px solid #28334a; border-radius:10px; padding:20px;">
            <div style="font-family:var(--font-pixel); font-size:10px; color:#00e5ff; margin-bottom:10px;">
                🧠 HOW TO TRAIN YOUR AI AGENT WITH CICLE
            </div>
            <p style="color:#c8d6e5; font-size:13px; line-height:1.6; margin-bottom:15px;">
                Give the prompt below to your AI assistant (e.g., ChatGPT GPT-4o, Claude 3.5 Sonnet, DeepSeek, or a local Python LLM runner) along with your Cicle board export or Cicle API token:
            </p>

            <!-- PROMPT BOX -->
            <div style="background:#141724; border:1px solid #3c4866; border-radius:6px; padding:15px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-family:var(--font-pixel); font-size:8px; color:#ffd700;">📋 COPYABLE SYSTEM PROMPT FOR YOUR AI AGENT:</span>
                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-purple" onclick="navigator.clipboard.writeText(document.getElementById('sgd-ai-prompt-code').innerText); alert('📋 AI System Prompt copied!');" style="font-size:7px; padding:3px 8px;">
                        Copy Prompt
                    </button>
                </div>
                <pre id="sgd-ai-prompt-code" style="color:#7bed9f; font-family:monospace; font-size:11px; white-space:pre-wrap; background:#080a10; padding:12px; border-radius:4px; max-height:180px; overflow-y:auto;">You are an Autonomous Project Management AI Agent for our company.
Our team uses Cicle (cicle.app) for sprint planning and a 16-Bit Gamification portal for daily employee motivation.

YOUR WORKFLOW:
1. Every morning, check the active sprint cards and assignments in Cicle.
2. Query the employee roster at GET <?php echo esc_html( $rest_base_pretty ); ?>/employees with header "X-SGD-API-KEY: <?php echo esc_html( $api_key ); ?>" to match Cicle user names with local gamification IDs.
3. For each active task, formulate a clear daily quest and dispatch it via:
   POST <?php echo esc_html( $rest_base_pretty ); ?>/tasks
   Headers:
     Content-Type: application/json
     X-SGD-API-KEY: <?php echo esc_html( $api_key ); ?>
   JSON Body:
   {
     "title": "[Cicle Task Title]",
     "description": "[Summary of acceptance criteria]",
     "category": "Cicle Sprint",
     "type": "daily",
     "reward_diamonds": 200,
     "assign_to": "employee_username_or_id",
     "external_url": "[Link to Cicle card]"
   }
4. When employees complete the quest and submit screenshot proof in the gamification portal, query GET <?php echo esc_html( $rest_base_pretty ); ?>/tasks?status=completed to mark the task completed in Cicle.</pre>
            </div>

            <!-- PYTHON SCRIPT CODE SNIPPET -->
            <div style="background:#141724; border:1px solid #3c4866; border-radius:6px; padding:15px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-family:var(--font-pixel); font-size:8px; color:#ffd700;">🐍 PYTHON AI SCRIPT EXAMPLE (requests):</span>
                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-purple" onclick="navigator.clipboard.writeText(document.getElementById('sgd-python-code').innerText); alert('📋 Python code copied!');" style="font-size:7px; padding:3px 8px;">
                        Copy Python Code
                    </button>
                </div>
                <pre id="sgd-python-code" style="color:#70a1ff; font-family:monospace; font-size:11px; white-space:pre-wrap; background:#080a10; padding:12px; border-radius:4px; max-height:200px; overflow-y:auto;">import requests

API_URL = "<?php echo esc_html( $rest_base_pretty ); ?>/tasks"
API_KEY = "<?php echo esc_html( $api_key ); ?>"

headers = {
    "Content-Type": "application/json",
    "X-SGD-API-KEY": API_KEY
}

# Example: AI creating a daily task from Cicle
payload = {
    "title": "Fix Cicle Sprint Bug: Mobile Navigation Bar",
    "description": "Ensure the hamburger menu closes on link click on iOS Safari.",
    "category": "Frontend Team",
    "type": "daily",
    "reward_diamonds": 200,
    "assign_to": "employee",  # Can be username, email, or user_id
    "is_recurring": 1,
    "external_url": "https://cicle.app/teams/1/boards/1/cards/42"
}

response = requests.post(API_URL, json=payload, headers=headers)
print(response.json())</pre>
            </div>
        </div>
    </div>

</div>
