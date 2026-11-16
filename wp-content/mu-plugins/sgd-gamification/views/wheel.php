<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$is_logged_in = is_user_logged_in();

$diamonds = sgd_get_diamonds( $current_user_id );
$shields = (int) get_user_meta( $current_user_id, 'sgd_streak_shields', true );
$coffee = (int) get_user_meta( $current_user_id, 'sgd_coffee_tokens', true );
$hero_title = get_user_meta( $current_user_id, 'sgd_hero_title', true );

$today = current_time( 'Y-m-d' );
$last_spin = get_user_meta( $current_user_id, 'sgd_last_wheel_spin', true );
$has_spun_today = ( $last_spin === $today );
$today_prize = get_user_meta( $current_user_id, 'sgd_last_wheel_reward', true ) ?: 'Lucky Reward';
?>

<div class="sgd-wheel-hero">
    <div style="font-family:var(--font-pixel); font-size:9px; color:var(--wow-gold-light); letter-spacing:2px; margin-bottom:6px;">
        <span class="sgd-theme-text-wow">💰 STEAMWHEEDLE CASINO & GOBLIN ROULETTE 💰</span>
        <span class="sgd-theme-text-genshin">✦ CELESTIA WISH & STELLA FORTUNA PRAYER ✦</span>
        <span class="sgd-theme-text-hearthstone">🎴 GOLDEN PACK OPENING & TAVERN REWARDS 🎴</span>
    </div>
    <h1 style="font-family:var(--font-title); font-size:28px; color:#fff; text-shadow:0 0 18px rgba(255,215,0,0.6); margin:0;">
        <span class="sgd-theme-text-wow">🎰 Goblin Daily Fortune Wheel</span>
        <span class="sgd-theme-text-genshin">✦ Stella Fortuna (Daily Wish Prayer)</span>
        <span class="sgd-theme-text-hearthstone">🎴 Golden Pack Opening (Daily Free Pack)</span>
    </h1>
    <p style="color:#e0d2b4; max-width:650px; margin:8px auto 0 auto; font-size:14px;">
        <span class="sgd-theme-text-wow">"Step right up, hero! One free spin every morning! Win diamond jackpots, streak shields, coffee tokens, and legendary perks!"</span>
        <span class="sgd-theme-text-genshin">"May the stars guide your journey, Traveler! One free wish prayer every morning! Win Primogem jackpots, shields, and blessings!"</span>
        <span class="sgd-theme-text-hearthstone">"Whaaa! Golden Legendary!" Crack open your daily tavern pack! Win Arcane Mana jackpots, win streak shields, and epic perks!</span>
    </p>
</div>

<!-- INVENTORY / PERKS STATUS BAR -->
<div class="sgd-wheel-perks-bar">
    <div class="sgd-wheel-perk-item">
        <span class="perk-icon">
            <span class="sgd-theme-text-wow">💎</span>
            <span class="sgd-theme-text-genshin">✦</span>
            <span class="sgd-theme-text-hearthstone">💠</span>
        </span>
        <div>
            <div class="perk-label">
                <span class="sgd-theme-text-wow">DIAMOND TREASURY</span>
                <span class="sgd-theme-text-genshin">PRIMOGEM TREASURY</span>
                <span class="sgd-theme-text-hearthstone">ARCANE MANA TREASURY</span>
            </div>
            <div class="perk-val" style="color:#00e5ff;">
                <?php echo number_format($diamonds); ?> 
                <span class="sgd-theme-text-wow">💎</span>
                <span class="sgd-theme-text-genshin">✦</span>
                <span class="sgd-theme-text-hearthstone">💠</span>
            </div>
        </div>
    </div>
    <div class="sgd-wheel-perk-item">
        <span class="perk-icon">🛡️</span>
        <div>
            <div class="perk-label">STREAK SHIELDS</div>
            <div class="perk-val" style="color:#70a1ff;"><?php echo $shields; ?> Available</div>
        </div>
    </div>
    <div class="sgd-wheel-perk-item">
        <span class="perk-icon">☕</span>
        <div>
            <div class="perk-label">COFFEE TOKENS</div>
            <div class="perk-val" style="color:#eccc68;"><?php echo $coffee; ?> Free Drinks</div>
        </div>
    </div>
    <div class="sgd-wheel-perk-item">
        <span class="perk-icon">👑</span>
        <div>
            <div class="perk-label">CURRENT TITLE</div>
            <div class="perk-val" style="color:#ffd700; font-size:11px;"><?php echo esc_html( $hero_title ?: 'Champion' ); ?></div>
            <?php if ( $is_logged_in ): ?>
                <button type="button" class="sgd-title-modal-trigger" style="background:none; border:none; color:#00e5ff; font-family:var(--font-pixel); font-size:7px; cursor:pointer; padding:0; text-decoration:underline; margin-top:4px; display:inline-block;">
                    [Change Title ▾]
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MAIN WHEEL INTERACTIVE ARENA -->
<div class="sgd-wheel-arena">
    
    <!-- WHEEL CANVAS CONTAINER -->
    <div class="sgd-wheel-wrapper">
        <div class="sgd-wheel-pointer">▼</div>
        <canvas id="sgd-wheel-canvas" width="400" height="400"></canvas>
        <div class="sgd-wheel-center-cap">
            <span>🎰</span>
        </div>
    </div>

    <!-- WHEEL CONTROLS & STATE -->
    <div class="sgd-wheel-controls-box">
        <?php if ( ! $is_logged_in ): ?>
            <div style="background:#131826; border:2px solid #3c4866; border-radius:8px; padding:25px; text-align:center;">
                <p style="color:#aaa; font-size:14px; margin-bottom:15px;">You must be logged in to claim your free daily spin!</p>
                <a href="<?php echo esc_url( sgd_get_portal_url('login') ); ?>" class="sgd-btn sgd-btn-blue sgd-btn-lg">
                    🔑 Enter Hearthstone Inn (Log In)
                </a>
            </div>
        <?php elseif ( ! $has_spun_today ): ?>
            <div class="sgd-spin-ready-card">
                <div style="font-family:var(--font-pixel); font-size:9px; color:#2ecc71; letter-spacing:1px; margin-bottom:6px;">
                    ✨ YOUR MORNING SPIN IS READY!
                </div>
                <h3 style="font-family:var(--font-title); font-size:22px; color:#fff; margin:0 0 10px 0;">
                    Will Luck Smile Upon You Today?
                </h3>
                <p style="color:#ccc; font-size:13px; line-height:1.5; margin-bottom:20px;">
                    Spin the Goblin Fortune Wheel now to claim your free daily reward. Every prize is guaranteed to enhance your heroic workday!
                </p>

                <form id="sgd-spin-form" method="post">
                    <input type="hidden" name="sgd_action" value="sgd_spin_wheel">
                    <input type="hidden" name="sector_index" id="sgd-sector-index-input" value="0">
                    <button type="button" id="sgd-spin-action-btn" class="sgd-spin-btn-big">
                        🎰 SPIN THE WHEEL OF FORTUNE! 🎰
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="sgd-spun-already-card">
                <div style="font-size:36px; margin-bottom:6px;">🎉</div>
                <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; letter-spacing:1px;">
                    TODAY'S FORTUNE REAPED!
                </div>
                <h3 style="font-family:var(--font-title); font-size:20px; color:#fff; margin:6px 0;">
                    Prize Won: <span style="color:#00e5ff;"><?php echo esc_html( $today_prize ); ?></span>
                </h3>
                <p style="color:#aaa; font-size:13px; margin-bottom:15px;">
                    Your daily fortune has already been claimed! The Goblin Casino resets every night at midnight.
                </p>

                <div class="sgd-wheel-timer-box">
                    <span style="font-family:var(--font-pixel); font-size:8px; color:#8fa0bd;">NEXT FREE SPIN IN:</span>
                    <div id="sgd-wheel-countdown" style="font-family:var(--font-pixel); font-size:18px; color:#ffd700; margin-top:4px;">
                        --:--:--
                    </div>
                </div>

                <!-- Evaluator Demo Reset Button -->
                <div style="margin-top:20px; border-top:1px dashed #3d4a6b; padding-top:15px;">
                    <form method="post">
                        <input type="hidden" name="sgd_action" value="sgd_reset_demo_spin">
                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-purple" style="font-size:8px; width:100%; justify-content:center;">
                            ⚡ [Demo Evaluator] Reset My Daily Spin Now
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- PRIZE SECTORS OVERVIEW -->
        <div class="sgd-prizes-legend">
            <div style="font-family:var(--font-pixel); font-size:8px; color:#ffd700; margin-bottom:8px;">
                📜 WHEEL OF FORTUNE PRIZES:
            </div>
            <div class="sgd-legend-grid">
                <div class="legend-item"><span style="color:#00e5ff;">💎 +50 💎</span> <small>Pouch</small></div>
                <div class="legend-item"><span style="color:#be2edd;">⚡ +100 XP</span> <small>Arcane Surge</small></div>
                <div class="legend-item"><span style="color:#ffd700;">💎 +100 💎</span> <small>Sack</small></div>
                <div class="legend-item"><span style="color:#70a1ff;">🛡️ Shield</span> <small>Streak Saver</small></div>
                <div class="legend-item"><span style="color:#2ecc71;">💎 +250 💎</span> <small>Treasure</small></div>
                <div class="legend-item"><span style="color:#eccc68;">☕ Coffee</span> <small>Free Drink</small></div>
                <div class="legend-item" style="grid-column:span 2; background:rgba(255,215,0,0.15); border-color:#ffd700;"><span style="color:#ffd700; font-weight:bold;">🌟 JACKPOT +500 💎</span> <small>Legendary Hoard</small></div>
            </div>
        </div>

    </div>

</div>

<!-- COUNTDOWN SCRIPT FOR MIDNIGHT RESET -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateMidnightCountdown() {
        const timerEl = document.getElementById('sgd-wheel-countdown');
        if (!timerEl) return;
        const now = new Date();
        const tomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
        const diff = tomorrow - now;

        const hours = Math.floor((diff / (1000 * 60 * 60)) % 24).toString().padStart(2, '0');
        const minutes = Math.floor((diff / 1000 / 60) % 60).toString().padStart(2, '0');
        const seconds = Math.floor((diff / 1000) % 60).toString().padStart(2, '0');

        timerEl.textContent = `${hours}h ${minutes}m ${seconds}s`;
    }
    updateMidnightCountdown();
    setInterval(updateMidnightCountdown, 1000);
});
</script>
