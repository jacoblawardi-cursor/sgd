<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
?>

<div style="max-width:550px; margin:30px auto;">
    <div style="background:radial-gradient(circle at 50% 20%, #1c2235 0%, #0c0e17 80%); border:3px solid var(--wow-gold); border-radius:8px; padding:30px; box-shadow:0 12px 30px rgba(0,0,0,0.8); text-align:center;">
        
        <div style="font-size:48px; margin-bottom:10px;">🛡️</div>
        <div style="font-family:var(--font-pixel); font-size:9px; color:#c69b3a; letter-spacing:2px;">HEARTHSTONE INN</div>
        <h2 style="font-family:var(--font-title); font-size:26px; color:var(--wow-gold-light); margin-bottom:15px;">
            Hero Authentication
        </h2>

        <?php if ( $is_logged_in ): ?>
            <div style="background:#131826; border:1px solid #2e3852; border-radius:6px; padding:18px; margin-bottom:20px; text-align:left;">
                <div style="font-size:14px; color:#fff;">
                    Logged in as: <strong style="color:var(--wow-gold-light);"><?php echo esc_html( $current_user->display_name ); ?></strong>
                </div>
                <div style="font-size:12px; color:#8fa0bd; margin-top:4px;">
                    Role: <strong><?php echo implode(', ', $current_user->roles); ?></strong> | Diamonds: <strong style="color:#00e5ff;"><?php echo number_format( sgd_get_diamonds( $current_user->ID ) ); ?> 💎</strong>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
                <?php if ( current_user_can( 'administrator' ) ): ?>
                    <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" class="sgd-btn sgd-btn-gold sgd-btn-lg" style="width:100%;">
                        🛡️ Open Admin Command Hall
                    </a>
                <?php endif; ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="sgd_action" value="sgd_logout">
                    <button type="submit" class="sgd-btn sgd-btn-red sgd-btn-lg" style="width:100%;">
                        🚪 Rest at Inn (Log Out)
                    </button>
                </form>
            </div>

        <?php else: ?>
            <p style="color:#9cb3c9; font-size:13px; margin-bottom:20px;">
                Identify yourself to accept daily tasks, earn diamonds, and redeem glorious company bounties.
            </p>

            <form method="post" style="text-align:left;">
                <input type="hidden" name="sgd_action" value="sgd_login">

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Hero Account Name / Email</label>
                    <input type="text" name="log" class="sgd-form-input" placeholder="e.g. employee or admin" required>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Secret Passphrase</label>
                    <input type="password" name="pwd" class="sgd-form-input" placeholder="••••••••" required>
                </div>

                <button type="submit" class="sgd-btn sgd-btn-gold sgd-btn-lg" style="width:100%; margin-top:10px;">
                    ⚔️ Enter the Realm
                </button>
            </form>
        <?php endif; ?>

        <!-- ONE-CLICK QUICK SWITCHER CARD FOR DEMO / EVALUATION -->
        <div style="margin-top:30px; border-top:2px dashed #3a445e; padding-top:20px; text-align:left;">
            <div style="font-family:var(--font-pixel); font-size:8px; color:var(--wow-gold-light); margin-bottom:10px;">
                ⚡ 1-CLICK DEMO CHARACTER SWITCHER (EVALUATION READY):
            </div>
            
            <div style="display:flex; flex-direction:column; gap:8px;">
                <!-- Switch to Admin -->
                <form method="post">
                    <input type="hidden" name="sgd_action" value="sgd_switch_user">
                    <input type="hidden" name="target_user" value="admin">
                    <button type="submit" class="sgd-btn sgd-btn-gold" style="width:100%; justify-content:space-between;">
                        <span>👑 Play as Grand Marshal (Admin)</span>
                        <span style="font-size:8px; opacity:0.8;">[admin / Admin123!]</span>
                    </button>
                </form>

                <!-- Switch to Employee -->
                <form method="post">
                    <input type="hidden" name="sgd_action" value="sgd_switch_user">
                    <input type="hidden" name="target_user" value="employee">
                    <button type="submit" class="sgd-btn sgd-btn-blue" style="width:100%; justify-content:space-between;">
                        <span>⚔️ Play as Champion (Employee)</span>
                        <span style="font-size:8px; opacity:0.8;">[employee / Emp123!]</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
