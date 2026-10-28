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

// Completed photo proofs
$completed_proofs = $wpdb->get_results( "SELECT * FROM $table_tasks WHERE status = 'completed' AND proof_image_url IS NOT NULL AND proof_image_url != '' ORDER BY completed_time DESC" );
$admin_tab = sanitize_text_field( $_GET['tab'] ?? '' );

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
        <button type="button" class="sgd-admin-subtab <?php echo ( ! $edit_task && ! $edit_ach && $admin_tab !== 'proofs' && ! empty( $sos_requests ) ) ? 'active' : ''; ?>" data-target="admin-sec-sos">
            🚨 SOS Help Dispatch (<?php echo count( $sos_requests ); ?>)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $edit_task ) ? 'active' : ''; ?>" data-target="admin-sec-tasks">
            📜 Manage Quests (Add / Edit / Delete)
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $edit_ach || isset($_GET['view_employee']) || ( empty( $sos_requests ) && ! $edit_task && $admin_tab !== 'proofs' ) ) ? 'active' : ''; ?>" data-target="admin-sec-achievements">
            🏆 Employee Achievements Manager
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'orders' ) ? 'active' : ''; ?>" data-target="admin-sec-orders">
            🎁 Cash Shop Orders (<?php echo $pending_orders_count; ?>)
        </button>
        <button type="button" class="sgd-admin-subtab" data-target="admin-sec-users">
            💎 Diamonds Treasury
        </button>
        <button type="button" class="sgd-admin-subtab <?php echo ( $admin_tab === 'proofs' ) ? 'active' : ''; ?>" data-target="admin-sec-proofs">
            📸 Submitted Photo Proofs (<?php echo count( $completed_proofs ); ?>)
        </button>
    </div>

    <!-- 1. SOS HELP DISPATCH BOARD -->
    <div id="admin-sec-sos" class="sgd-admin-section" style="<?php echo ( ! $edit_task && ! $edit_ach && ! empty( $sos_requests ) ) ? 'display:block;' : 'display:none;'; ?>">
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

    <!-- 4. EMPLOYEE DIAMONDS TREASURY -->
    <div id="admin-sec-users" class="sgd-admin-section" style="display:none;">
        <h3 style="font-family:var(--font-title); font-size:18px; color:var(--wow-gold-light); margin-bottom:15px;">
            💎 Adjust Employee Diamond Balances
        </h3>

        <div style="background:#161928; border:2px solid var(--wow-gold); border-radius:8px; padding:20px; max-width:650px;">
            <form method="post">
                <input type="hidden" name="sgd_action" value="sgd_admin_update_user">

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Select Employee Hero</label>
                    <select name="target_user_id" class="sgd-form-select">
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
                        <label class="sgd-form-label">Amount</label>
                        <input type="number" name="diamond_amount" class="sgd-form-input" value="500" min="0">
                    </div>
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

</div>
