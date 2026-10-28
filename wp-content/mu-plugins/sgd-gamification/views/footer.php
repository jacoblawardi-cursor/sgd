<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

</div><!-- .sgd-container -->

<!-- PHOTO PROOF UPLOAD MODAL FOR "FINISH JOB" -->
<div class="sgd-modal-backdrop" id="sgd-finish-modal">
    <div class="sgd-modal-box">
        <div class="sgd-modal-header">
            <h3 class="sgd-modal-title">📸 Submit Proof of Honor & Finish Job</h3>
            <button type="button" class="sgd-modal-close">&times;</button>
        </div>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="sgd_action" value="sgd_finish_job">
            <input type="hidden" name="task_id" id="sgd-modal-task-id" value="0">

            <div class="sgd-modal-body">
                <div style="background:#141724; border:1px solid var(--wow-gold-border); border-radius:6px; padding:12px; margin-bottom:16px;">
                    <div style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">COMPLETING QUEST:</div>
                    <div id="sgd-modal-task-title" style="font-family:var(--font-title); font-size:15px; color:#fff; font-weight:bold; margin-top:2px;">
                        Quest Title Here
                    </div>
                    <div style="font-size:12px; color:#00e5ff; font-family:var(--font-pixel); font-size:9px; margin-top:4px;">
                        Bounty: <span id="sgd-modal-reward-display">+200 💎</span>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Attach Photo / Screenshot Proof of Completed Work *</label>
                    <div class="sgd-upload-dropzone" id="sgd-upload-dropzone">
                        <div style="font-size:32px;">📷</div>
                        <div style="font-family:var(--font-pixel); font-size:9px; color:var(--wow-gold-light); margin-top:6px;">
                            CLICK TO BROWSE OR DRAG & DROP PHOTO PROOF
                        </div>
                        <div style="font-size:11px; color:#888; margin-top:4px;">Supports PNG, JPG, JPEG, WebP, GIF</div>
                        <input type="file" name="proof_photo" id="sgd-proof-photo-input" accept="image/*" required>
                        <img id="sgd-proof-preview-img" class="sgd-preview-image" alt="Proof Preview">
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Completion Remarks / Notes (Optional)</label>
                    <textarea name="proof_notes" rows="2" class="sgd-form-textarea" placeholder="e.g. Completed PR review and merged staging commit #a3f12..."></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="sgd-btn sgd-btn-blue sgd-modal-cancel">Cancel</button>
                    <button type="submit" class="sgd-btn sgd-btn-green">
                        🏆 Submit Proof & Claim Diamonds
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<footer style="margin-top:60px; border-top:2px solid var(--wow-gold-dark); background:#08090e; padding:25px 0; text-align:center;">
    <div class="sgd-container">
        <div style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a; letter-spacing:1px; margin-bottom:6px;">
            ⚔️ SGD GUILD GAMIFICATION ENGINE ⚔️
        </div>
        <div style="font-size:12px; color:#6b7588;">
            Crafted with 16-Bit Nostalgia & World of Warcraft UI Style • Daily Quests (+200 💎) • Urgent Bounties (+1,000 💎) • Goblin Cash Shop
        </div>
    </div>
</footer>

<script src="<?php echo esc_url( SGD_PLUGIN_URL . '/assets/app.js' ); ?>?v=<?php echo time(); ?>"></script>
<?php wp_footer(); ?>
</body>
</html>
