<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$is_logged_in = is_user_logged_in();
$is_admin = current_user_can( 'administrator' );
$my_diamonds = sgd_get_diamonds( $current_user_id );

$table_bounties = $wpdb->prefix . 'sgd_bounties';

// Filter
$filter = sanitize_text_field( $_GET['filter'] ?? 'all' );

if ( $filter === 'open' ) {
    $bounties = $wpdb->get_results( "SELECT * FROM $table_bounties WHERE status = 'open' ORDER BY id DESC" );
} elseif ( $filter === 'mine' ) {
    $bounties = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_bounties WHERE poster_id = %d ORDER BY id DESC", $current_user_id ) );
} elseif ( $filter === 'claimed' ) {
    $bounties = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_bounties WHERE claimed_by = %d ORDER BY id DESC", $current_user_id ) );
} else {
    $bounties = $wpdb->get_results( "SELECT * FROM $table_bounties ORDER BY FIELD(status, 'open', 'submitted', 'claimed', 'completed', 'cancelled'), id DESC" );
}
?>

<div class="sgd-bounty-hero">
    <div style="font-family:var(--font-pixel); font-size:9px; color:#ff9f43; letter-spacing:2px; margin-bottom:6px;">
        📜 GUILD CONTRACTS & PEER COMMISSIONS 📜
    </div>
    <h1 style="font-family:var(--font-title); font-size:26px; color:#fff; text-shadow:0 0 15px rgba(255,159,67,0.5); margin:0;">
        🤝 Hero-to-Hero Peer Bounty Board
    </h1>
    <p style="color:#dcdde1; max-width:700px; margin:8px auto 0 auto; font-size:14px;">
        Overloaded with sprint work or need a quick favor? Put your diamonds to work! Post a bounty from your own wallet to hire a teammate, or accept open contracts to earn bonus bounty gold.
    </p>
</div>

<!-- POST A BOUNTY FORM ACCORDION -->
<?php if ( $is_logged_in ): ?>
    <div class="sgd-post-bounty-card">
        <details <?php echo isset($_GET['post_new']) ? 'open' : ''; ?>>
            <summary style="font-family:var(--font-title); font-size:16px; color:#ffd700; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                <span>➕ Commission a Teammate (Post New Peer Bounty)</span>
                <span style="font-family:var(--font-pixel); font-size:9px; color:#ff9f43;">CLICK TO EXPAND / COLLAPSE ▾</span>
            </summary>

            <form method="post" style="margin-top:20px;">
                <input type="hidden" name="sgd_action" value="sgd_post_bounty">

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">
                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Quest Category</label>
                        <select name="bounty_category" class="sgd-form-select">
                            <option value="Testing & QA">Testing & QA Verification</option>
                            <option value="Code Review">Peer Code Review</option>
                            <option value="Documentation">Docs & Markdown Writing</option>
                            <option value="Design & Assets">Design & Asset Tweaks</option>
                            <option value="DevOps & Deploy">DevOps / Deployment Assist</option>
                            <option value="Quick Favor">Quick Sprint Favor</option>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label">Bounty Escrow (Your Diamonds) *</label>
                        <select name="bounty_reward" class="sgd-form-select" style="border-color:#00e5ff; color:#00e5ff; font-weight:bold;">
                            <option value="50">50 💎 (Quick 10-min favor)</option>
                            <option value="100" selected>100 💎 (Standard task)</option>
                            <option value="150">150 💎 (Detailed verification)</option>
                            <option value="200">200 💎 (High Priority)</option>
                            <option value="300">300 💎 (Major Assist)</option>
                            <option value="500">500 💎 (Urgent Emergency Bounty)</option>
                        </select>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Bounty Quest Title *</label>
                    <input type="text" name="bounty_title" class="sgd-form-input" placeholder="e.g. Verify Stripe Webhook Signature on iOS Mobile Safari" required>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Detailed Job Instructions & Acceptance Criteria *</label>
                    <textarea name="bounty_description" rows="3" class="sgd-form-textarea" placeholder="Detail the exact deliverable or proof needed from the colleague who accepts this contract..." required></textarea>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:10px;">
                    <span style="font-size:12px; color:#8fa0bd;">
                        Wallet Balance: <strong style="color:#00e5ff;"><?php echo number_format($my_diamonds); ?> 💎</strong> (Held in escrow until you approve the proof)
                    </span>
                    <button type="submit" class="sgd-btn sgd-btn-gold">
                        🎯 Post Bounty & Fund Escrow
                    </button>
                </div>
            </form>
        </details>
    </div>
<?php endif; ?>

<!-- FILTER & NAVIGATION BAR -->
<div class="sgd-section-header" style="margin-top:25px;">
    <div>
        <h2 class="sgd-section-title">
            <span>📜</span> Active Bulletin Contracts
        </h2>
    </div>

    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <a href="<?php echo esc_url( sgd_get_portal_url('bounties') ); ?>" class="sgd-btn sgd-btn-sm <?php echo ( $filter === 'all' ) ? 'sgd-btn-gold' : 'sgd-btn-blue'; ?>">
            All Bounties
        </a>
        <a href="<?php echo esc_url( add_query_arg(['sgd_page' => 'bounties', 'filter' => 'open'], home_url('/')) ); ?>" class="sgd-btn sgd-btn-sm <?php echo ( $filter === 'open' ) ? 'sgd-btn-gold' : 'sgd-btn-blue'; ?>">
            🟢 Open for Hire
        </a>
        <?php if ( $is_logged_in ): ?>
            <a href="<?php echo esc_url( add_query_arg(['sgd_page' => 'bounties', 'filter' => 'mine'], home_url('/')) ); ?>" class="sgd-btn sgd-btn-sm <?php echo ( $filter === 'mine' ) ? 'sgd-btn-gold' : 'sgd-btn-blue'; ?>">
                👑 Posted by Me
            </a>
            <a href="<?php echo esc_url( add_query_arg(['sgd_page' => 'bounties', 'filter' => 'claimed'], home_url('/')) ); ?>" class="sgd-btn sgd-btn-sm <?php echo ( $filter === 'claimed' ) ? 'sgd-btn-gold' : 'sgd-btn-blue'; ?>">
                ⚔️ Accepted by Me
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- BOUNTIES GRID -->
<div class="sgd-bounties-grid">
    <?php if ( empty( $bounties ) ): ?>
        <div style="background:#141724; border:2px dashed #444; border-radius:8px; padding:35px; text-align:center; color:#888; grid-column:1 / -1;">
            ✨ No contracts matching your filter at this time. Post a bounty above to commission your guild comrades!
        </div>
    <?php else: ?>
        <?php foreach ( $bounties as $b ): 
            $poster = get_user_by( 'id', $b->poster_id );
            $poster_name = $poster ? $poster->display_name : 'Guild Colleague';
            $claimer = $b->claimed_by ? get_user_by( 'id', $b->claimed_by ) : null;
            $claimer_name = $claimer ? $claimer->display_name : 'Unknown Hero';

            $is_my_bounty = ( $b->poster_id == $current_user_id );
            $is_claimed_by_me = ( $b->claimed_by == $current_user_id );
            $can_claim = ( $b->status === 'open' && ! $is_my_bounty && $is_logged_in );

            $status_colors = [
                'open'      => [ 'bg' => '#1b3a24', 'border' => '#2ecc71', 'text' => '#2ecc71', 'label' => '🟢 OPEN FOR HIRE' ],
                'claimed'   => [ 'bg' => '#3a2b12', 'border' => '#f39c12', 'text' => '#f39c12', 'label' => '⏳ IN PROGRESS' ],
                'submitted' => [ 'bg' => '#1b2c3a', 'border' => '#00e5ff', 'text' => '#00e5ff', 'label' => '📸 PROOF PENDING APPROVAL' ],
                'completed' => [ 'bg' => '#27193b', 'border' => '#be2edd', 'text' => '#e056fd', 'label' => '✅ ESCROW RELEASED' ],
                'cancelled' => [ 'bg' => '#222',    'border' => '#666',    'text' => '#888',    'label' => '❌ CANCELLED' ],
            ];
            $st = $status_colors[ $b->status ] ?? $status_colors['open'];
        ?>
            <div class="sgd-bounty-card status-<?php echo esc_attr( $b->status ); ?>">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span class="sgd-bounty-category-tag"><?php echo esc_html( $b->category ); ?></span>
                        <span class="sgd-status-badge" style="background:<?php echo $st['bg']; ?>; border:1px solid <?php echo $st['border']; ?>; color:<?php echo $st['text']; ?>; font-size:7px;">
                            <?php echo $st['label']; ?>
                        </span>
                    </div>

                    <h3 class="sgd-bounty-title"><?php echo esc_html( $b->title ); ?></h3>
                    <p class="sgd-bounty-desc"><?php echo nl2br( esc_html( $b->description ) ); ?></p>
                </div>

                <div>
                    <!-- POSTER & CLAIMER INFO -->
                    <div class="sgd-bounty-meta-box">
                        <div>
                            <span style="font-family:var(--font-pixel); font-size:7px; color:#8fa0bd;">POSTED BY:</span>
                            <div style="font-weight:bold; color:#fff; font-size:12px;">
                                👤 <?php echo esc_html( $poster_name ); ?> <?php echo $is_my_bounty ? '<span style="color:#2ecc71;">(You)</span>' : ''; ?>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <span style="font-family:var(--font-pixel); font-size:7px; color:#c69b3a;">BOUNTY ESCROW:</span>
                            <div style="font-family:var(--font-pixel); font-size:13px; color:#00e5ff; font-weight:bold;">
                                +<?php echo number_format( $b->reward_diamonds ); ?> 💎
                            </div>
                        </div>
                    </div>

                    <?php if ( $b->claimed_by > 0 ): ?>
                        <div style="background:#0c0f17; border:1px solid #1f273d; border-radius:4px; padding:6px 10px; margin-top:8px; font-size:11px; color:#b8c7e0;">
                            Contractor: <strong style="color:#ffd700;"><?php echo esc_html( $claimer_name ); ?></strong> <?php echo $is_claimed_by_me ? '<span style="color:#2ecc71;">(You)</span>' : ''; ?>
                        </div>
                    <?php endif; ?>

                    <!-- PROOF REVIEW SECTION -->
                    <?php if ( ! empty( $b->proof_notes ) || ! empty( $b->proof_image_url ) ): ?>
                        <div style="background:#131826; border:1px solid #2e3a55; border-radius:6px; padding:10px; margin-top:10px;">
                            <div style="font-family:var(--font-pixel); font-size:7px; color:#00e5ff; margin-bottom:4px;">
                                📸 SUBMITTED WORK PROOF:
                            </div>
                            <?php if ( ! empty( $b->proof_notes ) ): ?>
                                <div style="font-size:12px; color:#eee; font-style:italic; margin-bottom:6px;">
                                    "<?php echo esc_html( $b->proof_notes ); ?>"
                                </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $b->proof_image_url ) ): ?>
                                <a href="<?php echo esc_url( $b->proof_image_url ); ?>" target="_blank" class="sgd-btn sgd-btn-sm sgd-btn-blue" style="font-size:7px; padding:3px 8px;">
                                    🔍 View Attached Screenshot Proof
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- ACTIONS BAR -->
                    <div class="sgd-bounty-actions-bar">
                        <?php if ( $can_claim ): ?>
                            <form method="post" style="width:100%;">
                                <input type="hidden" name="sgd_action" value="sgd_claim_bounty">
                                <input type="hidden" name="bounty_id" value="<?php echo $b->id; ?>">
                                <button type="submit" class="sgd-btn sgd-btn-gold" style="width:100%; justify-content:center;">
                                    ⚔️ Accept Bounty (+<?php echo $b->reward_diamonds; ?> 💎)
                                </button>
                            </form>
                        <?php elseif ( $b->status === 'claimed' && $is_claimed_by_me ): ?>
                            <!-- Claimer Proof Modal Trigger -->
                            <button type="button" class="sgd-btn sgd-btn-green sgd-bounty-submit-trigger" 
                                data-bounty-id="<?php echo $b->id; ?>" 
                                data-bounty-title="<?php echo esc_attr( $b->title ); ?>" 
                                data-bounty-reward="<?php echo esc_attr( $b->reward_diamonds ); ?>" 
                                style="width:100%; justify-content:center;">
                                📸 Submit Completed Proof
                            </button>
                        <?php elseif ( $b->status === 'submitted' && ( $is_my_bounty || $is_admin ) ): ?>
                            <form method="post" style="width:100%;" onsubmit="return confirm('Release escrow and transfer <?php echo $b->reward_diamonds; ?> Diamonds to <?php echo esc_js($claimer_name); ?>?');">
                                <input type="hidden" name="sgd_action" value="sgd_approve_bounty">
                                <input type="hidden" name="bounty_id" value="<?php echo $b->id; ?>">
                                <button type="submit" class="sgd-btn sgd-btn-gold" style="width:100%; justify-content:center;">
                                    ✅ Verify Work & Release Escrow (+<?php echo $b->reward_diamonds; ?> 💎)
                                </button>
                            </form>
                        <?php elseif ( $b->status === 'open' && ( $is_my_bounty || $is_admin ) ): ?>
                            <form method="post" style="width:100%;" onsubmit="return confirm('Cancel this bounty and refund <?php echo $b->reward_diamonds; ?> Diamonds to your wallet?');">
                                <input type="hidden" name="sgd_action" value="sgd_cancel_bounty">
                                <input type="hidden" name="bounty_id" value="<?php echo $b->id; ?>">
                                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red" style="width:100%; justify-content:center;">
                                    ❌ Cancel Bounty & Refund Escrow
                                </button>
                            </form>
                        <?php elseif ( $b->status === 'completed' ): ?>
                            <div style="text-align:center; width:100%; font-size:11px; color:#2ecc71; font-weight:bold;">
                                🏆 Honor Upheld • Bounty Delivered
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- SUBMIT BOUNTY PROOF MODAL -->
<div class="sgd-modal-backdrop" id="sgd-bounty-modal">
    <div class="sgd-modal-box">
        <div class="sgd-modal-header">
            <h3 class="sgd-modal-title">📸 Submit Bounty Deliverable & Proof</h3>
            <button type="button" class="sgd-modal-close" id="sgd-bounty-modal-close">&times;</button>
        </div>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="sgd_action" value="sgd_submit_bounty_proof">
            <input type="hidden" name="bounty_id" id="sgd-modal-bounty-id" value="0">

            <div class="sgd-modal-body">
                <div style="background:#141724; border:1px solid var(--wow-gold-border); border-radius:6px; padding:12px; margin-bottom:16px;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">COMPLETING COMMISSION:</div>
                    <div id="sgd-modal-bounty-title" style="font-family:var(--font-title); font-size:15px; color:#fff; font-weight:bold; margin-top:2px;">
                        Bounty Title Here
                    </div>
                    <div style="font-size:12px; color:#00e5ff; font-family:var(--font-pixel); font-size:9px; margin-top:4px;">
                        Escrow Payout: <span id="sgd-modal-bounty-reward">+100 💎</span>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Deliverable Notes & Summary of Work Done *</label>
                    <textarea name="proof_notes" rows="3" class="sgd-form-textarea" placeholder="Describe the result or paste relevant links/notes..." required></textarea>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Attach Proof Photo / Screenshot (Optional)</label>
                    <input type="file" name="proof_photo" accept="image/*" class="sgd-form-input">
                </div>
            </div>

            <div class="sgd-modal-footer">
                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-bounty-modal-cancel">Cancel</button>
                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">📸 Submit Deliverable for Approval</button>
            </div>
        </form>
    </div>
</div>
