<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_tasks = $wpdb->prefix . 'sgd_tasks';
$current_user_id = get_current_user_id();
$is_admin = current_user_can( 'administrator' );
$all_users = get_users();

// Filter: all vs personal
$filter = sanitize_text_field( $_GET['filter'] ?? 'all' );

if ( $filter === 'mine' && ! $is_admin ) {
    $daily_tasks = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table_tasks WHERE type = 'daily' AND (assigned_to = %d OR assigned_to = 0) ORDER BY status ASC, id DESC",
        $current_user_id
    ) );
} else {
    $daily_tasks = $wpdb->get_results( "SELECT * FROM $table_tasks WHERE type = 'daily' ORDER BY status ASC, id DESC" );
}
?>

<!-- ADMIN QUICK CREATOR FOR CUSTOM EMPLOYEE DAILY TASK -->
<?php if ( $is_admin ): ?>
    <div style="background:linear-gradient(135deg, #241c0b 0%, #110d05 100%); border:2px solid var(--wow-gold); border-radius:8px; padding:20px; margin-bottom:25px; box-shadow:0 8px 25px rgba(0,0,0,0.8);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
            <div>
                <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; letter-spacing:1px;">
                    👑 GRAND MARSHAL ADMIN DISPATCH
                </div>
                <h3 style="font-family:var(--font-title); font-size:18px; color:#fff; margin-top:2px;">
                    🎯 Assign Custom Daily Task to Specific Employee
                </h3>
            </div>
            <span style="font-size:12px; color:#c69b3a; font-family:var(--font-pixel);">
                ADMINISTRATOR MODE
            </span>
        </div>

        <details style="background:#0c0f18; border:1px solid #3d3115; border-radius:6px; padding:12px 16px;" open>
            <summary style="font-family:var(--font-pixel); font-size:9px; color:var(--wow-gold-light); cursor:pointer;">
                ➕ CLICK TO EXPAND / COLLAPSE CUSTOM TASK CREATOR
            </summary>

            <form method="post" style="margin-top:15px;">
                <input type="hidden" name="sgd_action" value="sgd_admin_save_task">
                <input type="hidden" name="task_type" value="daily">
                <input type="hidden" name="redirect_to" value="daily">

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">
                    
                    <!-- TARGET EMPLOYEE SELECTOR -->
                    <div class="sgd-form-group">
                        <label class="sgd-form-label">🎯 Target Employee (Assignee) *</label>
                        <select name="assigned_to" class="sgd-form-select" required style="border-color:#ffd700; background:#181d2e;">
                            <option value="0">🌐 Open to All Employees</option>
                            <?php foreach ( $all_users as $u ): ?>
                                <option value="<?php echo $u->ID; ?>" <?php echo ( $u->user_login === 'employee' ) ? 'selected' : ''; ?>>
                                    👤 Specific: <?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Division / Category</label>
                        <input type="text" name="category" class="sgd-form-input" value="Custom Assignment" required>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Diamond Reward</label>
                        <input type="number" name="reward_diamonds" class="sgd-form-input" value="200" required>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Quest Title *</label>
                    <input type="text" name="title" class="sgd-form-input" placeholder="e.g. Audit Q3 Security Logs & Close Stale Pull Requests" required>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Detailed Job Instructions & Proof Requirements *</label>
                    <textarea name="description" rows="2" class="sgd-form-textarea" placeholder="Detail the exact job this employee must execute. Remember: they will need to upload a photo proof upon finishing!" required></textarea>
                </div>

                <button type="submit" class="sgd-btn sgd-btn-gold">
                    🎯 Assign Custom Daily Task (+200 💎)
                </button>
            </form>
        </details>
    </div>
<?php endif; ?>

<div class="sgd-section-header">
    <div>
        <h1 class="sgd-section-title">
            <span>📜</span> Daily Assigned Tasks
        </h1>
        <p style="color:#9cb3c9; font-size:13px; margin-top:4px;">
            Complete your assigned daily duties to maintain the guild! Each completed daily task rewards <strong style="color:#00e5ff;">+200 Diamonds</strong>.
        </p>
    </div>
    
    <div style="display:flex; align-items:center; gap:12px;">
        <!-- FILTER PILLS -->
        <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-btn sgd-btn-sm <?php echo ( $filter === 'all' ) ? 'sgd-btn-gold' : 'sgd-btn-blue'; ?>">
            All Tasks
        </a>
        <?php if ( ! $is_admin && is_user_logged_in() ): ?>
            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'daily', 'filter' => 'mine' ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm <?php echo ( $filter === 'mine' ) ? 'sgd-btn-gold' : 'sgd-btn-blue'; ?>">
                ⭐ Assigned to Me
            </a>
        <?php endif; ?>

        <div style="background:#111522; border:2px solid var(--wow-gold); padding:6px 14px; border-radius:6px;">
            <span style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">REWARD RATE:</span>
            <strong style="color:#00e5ff; font-family:var(--font-pixel); font-size:12px; margin-left:4px;">+200 💎</strong>
        </div>
    </div>
</div>

<div class="sgd-task-grid">
    <?php if ( empty( $daily_tasks ) ): ?>
        <div style="background:#141724; border:2px dashed #444; padding:35px; text-align:center; border-radius:8px;">
            <p style="color:#888; font-size:16px;">All daily tasks have been vanquished! Rest at the inn or check back tomorrow.</p>
        </div>
    <?php else: ?>
        <?php foreach ( $daily_tasks as $task ): 
            $is_completed = ( $task->status === 'completed' );
            $reward = $task->reward_diamonds ? $task->reward_diamonds : 200;
            $is_assigned = ( $task->assigned_to > 0 );
            $assigned_user = $is_assigned ? get_user_by( 'id', $task->assigned_to ) : null;
            $is_assigned_to_me = ( $is_assigned && $task->assigned_to == $current_user_id );
            $can_work_on_it = ( ! $is_assigned || $is_assigned_to_me || $is_admin );
        ?>
            <div class="sgd-quest-card <?php echo $is_completed ? 'completed-border' : ''; ?>" style="<?php echo $is_assigned_to_me ? 'border:3px solid #8e44ad; box-shadow:0 0 15px rgba(142,68,173,0.3);' : ''; ?>">
                <div class="sgd-quest-top">
                    <div class="sgd-quest-header-left">
                        <span class="sgd-quest-exclamation"><?php echo $is_completed ? '✅' : '❗'; ?></span>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                                <span class="sgd-quest-type-tag sgd-tag-daily">📜 Daily Duty</span>
                                
                                <!-- ASSIGNMENT BADGE -->
                                <?php if ( $is_assigned ): ?>
                                    <?php if ( $is_assigned_to_me ): ?>
                                        <span class="sgd-status-badge" style="background:#8e44ad; color:#fff; animation:pulse-urgent 1.5s infinite;">
                                            ⭐ DIRECTED SPECIFICALLY TO YOU
                                        </span>
                                    <?php else: ?>
                                        <span class="sgd-status-badge" style="background:#5c3570; color:#eee;">
                                            🎯 ASSIGNED TO: <?php echo $assigned_user ? esc_html($assigned_user->display_name) : 'Employee #' . $task->assigned_to; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="sgd-status-badge" style="background:#2c3e50; color:#bdc3c7;">
                                        🌐 OPEN TO ALL HEROES
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 class="sgd-quest-title"><?php echo esc_html( $task->title ); ?></h3>
                            <div style="font-size:12px; color:#5c4827; margin-top:3px;">
                                Division: <strong><?php echo esc_html( $task->category ); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="sgd-quest-reward-box">
                        <span>REWARD</span>
                        <span class="sgd-quest-reward-num">+<?php echo $reward; ?> 💎</span>
                    </div>
                </div>

                <div class="sgd-quest-desc">
                    <?php echo nl2br( esc_html( $task->description ) ); ?>
                </div>

                <!-- SOS / Help Request Notification -->
                <?php if ( $task->help_status === 'requested' ): ?>
                    <?php 
                    $help_user = get_user_by( 'id', $task->need_help_by );
                    $help_name = $help_user ? $help_user->display_name : 'An employee';
                    ?>
                    <div class="sgd-help-alert-box" style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>🚨</span>
                            <span><strong>SOS HELP CALL ACTIVE:</strong> <span style="text-decoration:underline;"><?php echo esc_html( $help_name ); ?></span> requested help at <?php echo esc_html( $task->need_help_time ); ?>!</span>
                        </div>
                        <?php if ( ! empty( $task->help_notes ) ): ?>
                            <div style="font-size:12px; font-style:italic; color:#5a141a; background:rgba(0,0,0,0.05); padding:4px 8px; border-radius:4px;">
                                Blocker note: "<?php echo esc_html( $task->help_notes ); ?>"
                            </div>
                        <?php endif; ?>

                        <!-- ADMIN INSTANT DISPATCH ACTION -->
                        <?php if ( $is_admin ): ?>
                            <form method="post" style="display:flex; gap:8px; align-items:center; margin-top:4px; flex-wrap:wrap;">
                                <input type="hidden" name="sgd_action" value="sgd_admin_dispatch_help">
                                <input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
                                <input type="hidden" name="redirect_to" value="daily">
                                <input type="text" name="dispatch_notes" placeholder="e.g. Senior engineer dispatched to assist" class="sgd-form-input" style="padding:4px 8px; font-size:11px; width:260px; background:#fff; color:#000;" required>
                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-green">
                                    🛡️ Dispatch Help to <?php echo esc_html( $help_name ); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php elseif ( $task->help_status === 'dispatched' ): ?>
                    <div class="sgd-help-dispatched-box">
                        <span>🛡️</span>
                        <span><strong>HELP DISPATCHED:</strong> <?php echo esc_html( $task->help_notes ? $task->help_notes : 'Reinforcements are en route to assist you!' ); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Photo Proof Preview if Completed -->
                <?php if ( $is_completed && ! empty( $task->proof_image_url ) ): ?>
                    <div class="sgd-proof-preview">
                        <a href="<?php echo esc_url( $task->proof_image_url ); ?>" target="_blank" title="Click to view full photo proof">
                            <img src="<?php echo esc_url( $task->proof_image_url ); ?>" alt="Proof" class="sgd-proof-thumb">
                        </a>
                        <div>
                            <div style="font-family:var(--font-pixel); font-size:8px; color:#27ae60;">VERIFIED PROOF OF WORK</div>
                            <div style="font-size:12px; color:#443322; margin-top:2px;">
                                Submitted by <strong><?php 
                                    $cuser = get_user_by( 'id', $task->completed_by );
                                    echo $cuser ? esc_html( $cuser->display_name ) : 'Hero';
                                ?></strong> on <?php echo esc_html( $task->completed_time ); ?>
                            </div>
                            <?php if ( ! empty( $task->proof_notes ) ): ?>
                                <div style="font-style:italic; font-size:12px; margin-top:3px; color:#332211;">
                                    "<?php echo esc_html( $task->proof_notes ); ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons: Need Help & Finish Job -->
                <div class="sgd-quest-actions">
                    <?php if ( $is_completed ): ?>
                        <span class="sgd-status-badge sgd-status-completed">✅ COMPLETED (+<?php echo $reward; ?> 💎 AWARDED)</span>
                    <?php else: ?>
                        <?php if ( $can_work_on_it ): ?>
                            <!-- FINISH JOB (PHOTO PROOF UPLOAD) -->
                            <button type="button" 
                                    class="sgd-btn sgd-btn-green sgd-btn-finish-trigger"
                                    data-task-id="<?php echo $task->id; ?>"
                                    data-task-title="<?php echo esc_attr( $task->title ); ?>"
                                    data-task-reward="<?php echo $reward; ?>">
                                📸 Finish Job (Upload Photo Proof)
                            </button>

                            <!-- NEED HELP BUTTON -->
                            <?php if ( $task->help_status !== 'requested' ): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="sgd_action" value="sgd_need_help">
                                    <input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
                                    <button type="submit" class="sgd-btn sgd-btn-blue sgd-btn-help-trigger">
                                        🆘 Need Help
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="sgd-status-badge" style="background:#c0392b; color:#fff;">⚠️ SOS SENT</span>
                            <?php endif; ?>

                        <?php else: ?>
                            <span class="sgd-status-badge" style="background:#444; color:#aaa;">
                                🔒 Assigned to <?php echo $assigned_user ? esc_html($assigned_user->display_name) : 'another hero'; ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
