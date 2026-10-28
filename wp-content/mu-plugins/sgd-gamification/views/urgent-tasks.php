<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_tasks = $wpdb->prefix . 'sgd_tasks';
$current_user_id = get_current_user_id();

$urgent_tasks = $wpdb->get_results( "SELECT * FROM $table_tasks WHERE type = 'urgent' ORDER BY status ASC, id DESC" );
?>

<div class="sgd-section-header">
    <div>
        <h1 class="sgd-section-title" style="color:#ff8577;">
            <span>⚡</span> Urgent Emergency Tasks & Bounties
        </h1>
        <p style="color:#ffb8b0; font-size:13px; margin-top:4px;">
            Critical guild emergencies that anyone can take! Click <strong style="color:#ffd700;">"Take Job"</strong> to claim the bounty, then upload photo proof to finish and earn <strong style="color:#00e5ff;">+1,000 Diamonds</strong>!
        </p>
    </div>
    <div style="background:#2a0f0f; border:2px solid #e74c3c; padding:8px 16px; border-radius:6px; box-shadow:0 0 10px rgba(231,76,60,0.3);">
        <span style="font-family:var(--font-pixel); font-size:9px; color:#ff7675;">EMERGENCY BOUNTY:</span>
        <strong style="color:#00e5ff; font-family:var(--font-pixel); font-size:14px; margin-left:6px;">+1,000 💎</strong>
    </div>
</div>

<div class="sgd-task-grid">
    <?php if ( empty( $urgent_tasks ) ): ?>
        <div style="background:#1a1010; border:2px dashed #e74c3c; padding:35px; text-align:center; border-radius:8px;">
            <p style="color:#ff9999; font-size:16px;">Peace reigns in the realm! No emergency tasks currently active.</p>
        </div>
    <?php else: ?>
        <?php foreach ( $urgent_tasks as $task ): 
            $is_completed = ( $task->status === 'completed' );
            $is_claimed = ( $task->status === 'claimed' );
            $is_open = ( $task->status === 'open' );
            $claimed_by_me = ( $task->claimed_by == $current_user_id );
            $reward = $task->reward_diamonds ? $task->reward_diamonds : 1000;
        ?>
            <div class="sgd-quest-card urgent-border <?php echo $is_completed ? 'completed-border' : ''; ?>">
                <div class="sgd-quest-top">
                    <div class="sgd-quest-header-left">
                        <span class="sgd-quest-exclamation" style="animation:pulse-urgent 1.5s infinite;"><?php echo $is_completed ? '✅' : '⚡'; ?></span>
                        <div>
                            <span class="sgd-quest-type-tag sgd-tag-urgent">🔥 Critical Emergency Bounty</span>
                            <h3 class="sgd-quest-title"><?php echo esc_html( $task->title ); ?></h3>
                            <div style="font-size:12px; color:#682c2c; margin-top:3px;">
                                Threat Level: <strong>CRITICAL EMERGENCY</strong> | Sector: <strong><?php echo esc_html( $task->category ); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="sgd-quest-reward-box" style="border-color:#ff4444; background:#360c0c;">
                        <span>EMERGENCY BOUNTY</span>
                        <span class="sgd-quest-reward-num" style="color:#ffd700;">+<?php echo $reward; ?> 💎</span>
                    </div>
                </div>

                <div class="sgd-quest-desc">
                    <?php echo nl2br( esc_html( $task->description ) ); ?>
                </div>

                <!-- Claim Status Banner -->
                <?php if ( $is_claimed && ! $is_completed ): ?>
                    <?php 
                    $claimant = get_user_by( 'id', $task->claimed_by );
                    $claimant_name = $claimant ? $claimant->display_name : 'Unknown Hero';
                    ?>
                    <div style="background:#301e0a; border:2px solid #e67e22; color:#f39c12; padding:8px 12px; border-radius:6px; margin-bottom:12px; font-size:13px; font-weight:bold; display:flex; align-items:center; gap:8px;">
                        <span>⚔️</span>
                        <span>TASK CLAIMED BY: <?php echo esc_html( $claimant_name ); ?> <?php echo $claimed_by_me ? '(YOU)' : ''; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Help requested indicator if active -->
                <?php if ( $task->help_status === 'requested' ): ?>
                    <?php 
                    $help_user = get_user_by( 'id', $task->need_help_by );
                    $help_name = $help_user ? $help_user->display_name : 'An employee';
                    ?>
                    <div class="sgd-help-alert-box" style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>🚨</span>
                            <span><strong>SOS HELP NEEDED:</strong> <span style="text-decoration:underline;"><?php echo esc_html( $help_name ); ?></span> requested urgent help at <?php echo esc_html( $task->need_help_time ); ?>!</span>
                        </div>
                        <?php if ( ! empty( $task->help_notes ) ): ?>
                            <div style="font-size:12px; font-style:italic; color:#5a141a; background:rgba(0,0,0,0.05); padding:4px 8px; border-radius:4px;">
                                Blocker note: "<?php echo esc_html( $task->help_notes ); ?>"
                            </div>
                        <?php endif; ?>

                        <!-- ADMIN INSTANT DISPATCH ACTION -->
                        <?php if ( current_user_can( 'administrator' ) ): ?>
                            <form method="post" style="display:flex; gap:8px; align-items:center; margin-top:4px; flex-wrap:wrap;">
                                <input type="hidden" name="sgd_action" value="sgd_admin_dispatch_help">
                                <input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
                                <input type="hidden" name="redirect_to" value="urgent">
                                <input type="text" name="dispatch_notes" placeholder="Notes: e.g. Emergency response team dispatched" class="sgd-form-input" style="padding:4px 8px; font-size:11px; width:260px; background:#fff; color:#000;" required>
                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-green">
                                    🛡️ Dispatch Help to <?php echo esc_html( $help_name ); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php elseif ( $task->help_status === 'dispatched' ): ?>
                    <div class="sgd-help-dispatched-box">
                        <span>🛡️</span>
                        <span><strong>HELP DISPATCHED:</strong> <?php echo esc_html( $task->help_notes ? $task->help_notes : 'Emergency backup dispatched!' ); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Proof of work if completed -->
                <?php if ( $is_completed && ! empty( $task->proof_image_url ) ): ?>
                    <div class="sgd-proof-preview">
                        <a href="<?php echo esc_url( $task->proof_image_url ); ?>" target="_blank" title="Click to view full photo proof">
                            <img src="<?php echo esc_url( $task->proof_image_url ); ?>" alt="Proof" class="sgd-proof-thumb">
                        </a>
                        <div>
                            <div style="font-family:var(--font-pixel); font-size:8px; color:#27ae60;">VERIFIED EMERGENCY PROOF</div>
                            <div style="font-size:12px; color:#443322; margin-top:2px;">
                                Resolved by <strong><?php 
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

                <!-- Action Buttons: Take Job & Finish Job -->
                <div class="sgd-quest-actions">
                    <?php if ( $is_completed ): ?>
                        <span class="sgd-status-badge sgd-status-completed">✅ RESOLVED (+<?php echo $reward; ?> 💎 BOUNTY AWARDED)</span>
                    <?php else: ?>
                        <!-- 1. TAKE JOB BUTTON (If open to anyone) -->
                        <?php if ( $is_open ): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="sgd_action" value="sgd_take_job">
                                <input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
                                <button type="submit" class="sgd-btn sgd-btn-red sgd-btn-take-trigger">
                                    ⚔️ Take Job (Claim Bounty)
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- 2. FINISH JOB (PHOTO PROOF UPLOAD) -->
                        <?php if ( $is_claimed && ( $claimed_by_me || current_user_can('administrator') ) || $is_open ): ?>
                            <button type="button" 
                                    class="sgd-btn sgd-btn-green sgd-btn-finish-trigger"
                                    data-task-id="<?php echo $task->id; ?>"
                                    data-task-title="<?php echo esc_attr( $task->title ); ?>"
                                    data-task-reward="<?php echo $reward; ?>">
                                📸 Finish Job (Upload Photo Proof)
                            </button>
                        <?php endif; ?>

                        <!-- 3. NEED HELP BUTTON -->
                        <?php if ( $task->help_status !== 'requested' ): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="sgd_action" value="sgd_need_help">
                                <input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
                                <button type="submit" class="sgd-btn sgd-btn-blue sgd-btn-help-trigger">
                                    🆘 Need Help
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
