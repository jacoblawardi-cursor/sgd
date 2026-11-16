<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$user_id = get_current_user_id();
$diamonds = sgd_get_diamonds( $user_id );
$catalog = sgd_get_shop_catalog();

$table_redemptions = $wpdb->prefix . 'sgd_redemptions';
$my_redemptions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_redemptions WHERE user_id = %d ORDER BY id DESC", $user_id ) );
?>

<div class="sgd-shop-hero">
    <div style="font-family:var(--font-pixel); font-size:9px; color:var(--wow-gold); letter-spacing:2px; margin-bottom:6px;">
        <span class="sgd-theme-text-wow">💰 STEAMWHEEDLE CARTEL & GOBLIN TREASURY 💰</span>
        <span class="sgd-theme-text-genshin">✦ PAIMON'S BARGAINS & SOUVENIR SHOP ✦</span>
        <span class="sgd-theme-text-hearthstone">🎴 TAVERN VAULT & DUST CRAFTING EMPORIUM 🎴</span>
    </div>
    <h2>
        <span class="sgd-theme-text-wow">Goblin Diamond Exchange & Cash Shop</span>
        <span class="sgd-theme-text-genshin">Teyvat Souvenir & Primogem Exchange</span>
        <span class="sgd-theme-text-hearthstone">Tavern Mana Exchange & Card Pack Vault</span>
    </h2>
    <p style="color:#bfa87a; max-width:650px; margin:8px auto 0 auto; font-size:14px;">
        <span class="sgd-theme-text-wow">"Time is money, friend!" Trade your hard-earned Diamonds for real cold cash vouchers, paid leave tokens, and exotic vacation packages!</span>
        <span class="sgd-theme-text-genshin">"Ehe! Welcome to Paimon's Bargains!" Trade your earned Primogems for real cash vouchers, paid leave tokens, and vacation packages!</span>
        <span class="sgd-theme-text-hearthstone">"Come on in! Pull up a chair!" Trade your hard-earned Arcane Mana for real cash vouchers, paid leave tokens, and legendary tavern rewards!</span>
    </p>
</div>

<div class="sgd-section-header">
    <h2 class="sgd-section-title">
        <span class="sgd-theme-text-wow">💎</span>
        <span class="sgd-theme-text-genshin">✦</span>
        <span class="sgd-theme-text-hearthstone">💠</span> 
        Available Rewards
    </h2>
    <div class="sgd-diamond-wallet">
        <span class="sgd-diamond-icon">
            <span class="sgd-theme-text-wow">💎</span>
            <span class="sgd-theme-text-genshin">✦</span>
            <span class="sgd-theme-text-hearthstone">💠</span>
        </span>
        <div class="sgd-diamond-details">
            <span class="sgd-diamond-label">
                <span class="sgd-theme-text-wow">Your Wallet</span>
                <span class="sgd-theme-text-genshin">Primogems</span>
                <span class="sgd-theme-text-hearthstone">Arcane Mana</span>
            </span>
            <span class="sgd-diamond-count">
                <?php echo number_format($diamonds); ?> 
                <span class="sgd-theme-text-wow">💎</span>
                <span class="sgd-theme-text-genshin">✦</span>
                <span class="sgd-theme-text-hearthstone">💠</span>
            </span>
        </div>
    </div>
</div>

<div class="sgd-shop-grid">
    <?php foreach ( $catalog as $item ): 
        $can_afford = ( $diamonds >= $item['cost'] );
        $tier_class = 'tier-rare';
        if ( $item['cost'] >= 100000 ) $tier_class = 'tier-legendary';
        elseif ( $item['cost'] >= 20000 ) $tier_class = 'tier-epic';
    ?>
        <div class="sgd-shop-card <?php echo $tier_class; ?>">
            <div>
                <span class="sgd-shop-tier-badge"><?php echo esc_html( $item['tier'] ); ?></span>
                <div class="sgd-shop-icon"><?php echo esc_html( $item['icon'] ); ?></div>
                <h3 class="sgd-shop-title"><?php echo esc_html( $item['name'] ); ?></h3>
                <div class="sgd-shop-desc"><?php echo esc_html( $item['description'] ); ?></div>
            </div>

            <div>
                <div class="sgd-shop-cost-box">
                    <span class="sgd-shop-cost-label">REQUIRED DIAMONDS:</span>
                    <span class="sgd-shop-cost-val"><?php echo number_format($item['cost']); ?> 💎</span>
                </div>

                <?php if ( $can_afford ): ?>
                    <form method="post">
                        <input type="hidden" name="sgd_action" value="sgd_redeem">
                        <input type="hidden" name="item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
                        <button type="submit" 
                                class="sgd-btn sgd-btn-gold sgd-btn-redeem-trigger" 
                                style="width:100%;"
                                data-item-name="<?php echo esc_attr( $item['name'] ); ?>"
                                data-item-cost="<?php echo number_format($item['cost']); ?>">
                            🎁 Redeem Reward (<?php echo number_format($item['cost']); ?> 💎)
                        </button>
                    </form>
                <?php else: ?>
                    <button type="button" class="sgd-btn" style="width:100%; background:#2a2b33; color:#777; border:1px solid #444; cursor:not-allowed;" disabled>
                        🔒 Needs <?php echo number_format($item['cost'] - $diamonds); ?> More 💎
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- REDEMPTION HISTORY -->
<div class="sgd-section-header">
    <h2 class="sgd-section-title">
        <span>📜</span> Your Redemption History
    </h2>
</div>

<?php if ( empty( $my_redemptions ) ): ?>
    <div style="background:#141724; border:1px solid #2a3147; padding:20px; text-align:center; border-radius:6px; color:#888;">
        No rewards redeemed yet. Slay more quests and fill your treasure chest!
    </div>
<?php else: ?>
    <div style="background:#111420; border:2px solid var(--wow-gold-border); border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#090b12; border-bottom:2px solid var(--wow-gold); font-family:var(--font-pixel); font-size:9px; color:var(--wow-gold-light);">
                    <th style="padding:12px 16px;">DATE</th>
                    <th style="padding:12px 16px;">ITEM REWARD</th>
                    <th style="padding:12px 16px;">DIAMONDS</th>
                    <th style="padding:12px 16px;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $my_redemptions as $r ): ?>
                    <tr style="border-bottom:1px solid #1f2538;">
                        <td style="padding:12px 16px; color:#8fa0bd;"><?php echo esc_html( $r->created_at ); ?></td>
                        <td style="padding:12px 16px; font-weight:bold; color:#fff;"><?php echo esc_html( $r->item_name ); ?></td>
                        <td style="padding:12px 16px; color:#00e5ff; font-family:var(--font-pixel); font-size:10px;">-<?php echo number_format($r->diamonds_spent); ?> 💎</td>
                        <td style="padding:12px 16px;">
                            <span class="sgd-status-badge" style="background:#e67e22; color:#fff;">
                                ⏳ <?php echo strtoupper( esc_html( $r->status ) ); ?> (HR Processing)
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
