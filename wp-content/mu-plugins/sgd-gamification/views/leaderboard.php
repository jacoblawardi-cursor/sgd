<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$is_logged_in = is_user_logged_in();
$my_diamonds = sgd_get_diamonds( $current_user_id );

$leaderboard = sgd_get_leaderboard_data();
$factions = sgd_get_faction_summary();

$table_kudos = $wpdb->prefix . 'sgd_kudos';
$recent_kudos = $wpdb->get_results( "SELECT * FROM $table_kudos ORDER BY id DESC LIMIT 10" );

$other_heroes = [];
foreach ( $leaderboard as $hero ) {
    if ( $hero['user_id'] != $current_user_id ) {
        $other_heroes[] = $hero;
    }
}
?>

<!-- REALM HEADER -->
<div class="sgd-leaderboard-hero">
    <div style="font-family:var(--font-pixel); font-size:9px; color:#be2edd; letter-spacing:2px; margin-bottom:6px;">
        ⚔️ PANTHEON OF VALOR & GUILD SQUADS ⚔️
    </div>
    <h1 style="font-family:var(--font-title); font-size:26px; color:#fff; text-shadow:0 0 15px rgba(190,46,221,0.6); margin:0;">
        👑 Hall of Legends & Leaderboard
    </h1>
    <p style="color:#d1d8e0; max-width:700px; margin:8px auto 0 auto; font-size:14px;">
        Stand tall among the legendary heroes of the guild! Earn XP from daily tasks and emergency bounties, lead your squad faction to glory, and empower your comrades with peer Mana.
    </p>
</div>

<!-- TOP 3 PODIUM -->
<?php if ( ! empty( $leaderboard ) ): ?>
    <div class="sgd-podium-container">
        <!-- 2nd Place -->
        <?php if ( isset( $leaderboard[1] ) ): 
            $second = $leaderboard[1];
        ?>
            <div class="sgd-podium-card podium-silver">
                <div class="sgd-podium-rank-badge">🥈 2ND PLACE</div>
                <div class="sgd-podium-avatar">
                    <?php echo $second['user_login'] === 'admin' ? '👑' : ( $second['user_login'] === 'employee2' ? '🧙‍♀️' : '⚔️' ); ?>
                </div>
                <div class="sgd-podium-name"><?php echo esc_html( $second['display_name'] ); ?></div>
                <div class="sgd-podium-faction"><?php echo esc_html( $second['faction'] ); ?></div>
                <div class="sgd-podium-level">Level <?php echo $second['level']; ?> Champion</div>
                <div class="sgd-podium-stats">
                    <span style="color:#00e5ff; font-weight:bold;">💎 <?php echo number_format( $second['diamonds'] ); ?></span>
                    <span style="color:#ff9f43; font-weight:bold;">🔥 <?php echo $second['streak']; ?>d Streak</span>
                </div>
                <div class="sgd-podium-xp"><?php echo number_format( $second['total_xp'] ); ?> Total XP</div>
            </div>
        <?php endif; ?>

        <!-- 1st Place (Champion) -->
        <?php if ( isset( $leaderboard[0] ) ): 
            $first = $leaderboard[0];
        ?>
            <div class="sgd-podium-card podium-gold">
                <div class="sgd-podium-crown">👑</div>
                <div class="sgd-podium-rank-badge gold-badge">🥇 GRAND CHAMPION</div>
                <div class="sgd-podium-avatar gold-avatar">
                    <?php echo $first['user_login'] === 'admin' ? '👑' : ( $first['user_login'] === 'employee2' ? '🧙‍♀️' : '⚔️' ); ?>
                </div>
                <div class="sgd-podium-name" style="font-size:18px; color:#ffd700;"><?php echo esc_html( $first['display_name'] ); ?></div>
                <div class="sgd-podium-faction" style="color:#ffeaa7;"><?php echo esc_html( $first['faction'] ); ?></div>
                <div class="sgd-podium-level" style="color:#fff;">★ Level <?php echo $first['level']; ?> Paragon ★</div>
                <div class="sgd-podium-stats">
                    <span style="color:#00e5ff; font-weight:bold; font-size:14px;">💎 <?php echo number_format( $first['diamonds'] ); ?></span>
                    <span style="color:#ff9f43; font-weight:bold; font-size:14px;">🔥 <?php echo $first['streak']; ?>d Streak</span>
                </div>
                <div class="sgd-podium-xp" style="color:#ffd700; font-size:13px; font-weight:bold;"><?php echo number_format( $first['total_xp'] ); ?> Total XP</div>
            </div>
        <?php endif; ?>

        <!-- 3rd Place -->
        <?php if ( isset( $leaderboard[2] ) ): 
            $third = $leaderboard[2];
        ?>
            <div class="sgd-podium-card podium-bronze">
                <div class="sgd-podium-rank-badge">🥉 3RD PLACE</div>
                <div class="sgd-podium-avatar">
                    <?php echo $third['user_login'] === 'admin' ? '👑' : ( $third['user_login'] === 'employee2' ? '🧙‍♀️' : '⚔️' ); ?>
                </div>
                <div class="sgd-podium-name"><?php echo esc_html( $third['display_name'] ); ?></div>
                <div class="sgd-podium-faction"><?php echo esc_html( $third['faction'] ); ?></div>
                <div class="sgd-podium-level">Level <?php echo $third['level']; ?> Hero</div>
                <div class="sgd-podium-stats">
                    <span style="color:#00e5ff; font-weight:bold;">💎 <?php echo number_format( $third['diamonds'] ); ?></span>
                    <span style="color:#ff9f43; font-weight:bold;">🔥 <?php echo $third['streak']; ?>d Streak</span>
                </div>
                <div class="sgd-podium-xp"><?php echo number_format( $third['total_xp'] ); ?> Total XP</div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- FULL LEADERBOARD TABLE -->
<div class="sgd-section-header" style="margin-top:20px;">
    <h2 class="sgd-section-title">
        <span>📜</span> Guild Standings & Hero Registry
    </h2>
    <div style="font-family:var(--font-pixel); font-size:9px; color:#8fa0bd;">
        UPDATES LIVE • SORTED BY TOTAL XP
    </div>
</div>

<div class="sgd-leaderboard-table-wrap">
    <table class="sgd-leaderboard-table">
        <thead>
            <tr>
                <th style="width:60px; text-align:center;">RANK</th>
                <th>HERO & SQUAD FACTION</th>
                <th style="text-align:center;">LEVEL & XP</th>
                <th style="text-align:center;">DIAMONDS</th>
                <th style="text-align:center;">QUESTS DONE</th>
                <th style="text-align:center;">ACHIEVEMENTS</th>
                <th style="text-align:center;">STREAK</th>
                <th style="text-align:right;">ACTION</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rank = 1;
            foreach ( $leaderboard as $hero ): 
                $is_me = ( $hero['user_id'] == $current_user_id );
                $avatar_icon = $hero['user_login'] === 'admin' ? '👑' : ( $hero['user_login'] === 'employee2' ? '🧙‍♀️' : '⚔️' );
            ?>
                <tr class="<?php echo $is_me ? 'row-highlight-me' : ''; ?>">
                    <td style="text-align:center;">
                        <?php if ( $rank === 1 ): ?>
                            <span class="sgd-rank-medal gold">🥇 1</span>
                        <?php elseif ( $rank === 2 ): ?>
                            <span class="sgd-rank-medal silver">🥈 2</span>
                        <?php elseif ( $rank === 3 ): ?>
                            <span class="sgd-rank-medal bronze">🥉 3</span>
                        <?php else: ?>
                            <span style="font-family:var(--font-pixel); color:#aaa; font-size:10px;">#<?php echo $rank; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:24px;"><?php echo $avatar_icon; ?></span>
                            <div>
                                <div style="font-weight:bold; color:#fff; font-size:14px; display:flex; align-items:center; gap:6px;">
                                    <?php echo esc_html( $hero['display_name'] ); ?>
                                    <?php if ( $is_me ): ?>
                                        <span class="sgd-status-badge" style="background:#2ecc71; color:#000; font-size:6px; padding:1px 4px;">YOU</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11px; color:#c69b3a; margin-top:2px;">
                                    <?php echo esc_html( $hero['faction'] ); ?> • <em><?php echo esc_html( $hero['title'] ); ?></em>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <span class="sgd-status-badge" style="background:#4a148c; border:1px solid #9b59b6; color:#e1bee7; font-size:8px;">
                            ★ Lv <?php echo $hero['level']; ?>
                        </span>
                        <div style="font-size:10px; color:#aaa; margin-top:3px;">
                            <?php echo number_format( $hero['total_xp'] ); ?> XP
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <span style="color:#00e5ff; font-family:var(--font-pixel); font-size:10px; font-weight:bold;">
                            <?php echo number_format( $hero['diamonds'] ); ?> 💎
                        </span>
                    </td>
                    <td style="text-align:center; font-size:12px; color:#ddd;">
                        <span title="Daily Quests"><?php echo $hero['daily_done']; ?> 📜</span> / 
                        <span title="Urgent Emergency Bounties" style="color:#ff7675;"><?php echo $hero['urgent_done']; ?> ⚡</span>
                    </td>
                    <td style="text-align:center;">
                        <span style="color:#ffd700; font-family:var(--font-pixel); font-size:9px;">
                            🏆 <?php echo $hero['ach_count']; ?> Unlocked
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span style="color:#ff9f43; font-family:var(--font-pixel); font-size:9px;">
                            🔥 <?php echo $hero['streak']; ?>d
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <?php if ( ! $is_me && $is_logged_in ): ?>
                            <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-purple sgd-send-mana-btn" 
                                data-target-id="<?php echo $hero['user_id']; ?>" 
                                data-target-name="<?php echo esc_attr( $hero['display_name'] ); ?>">
                                ⚡ Send Mana
                            </button>
                        <?php else: ?>
                            <span style="color:#555; font-size:11px;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php 
            $rank++;
            endforeach; 
            ?>
        </tbody>
    </table>
</div>

<!-- SQUAD FACTIONS COMPARISON -->
<div class="sgd-section-header" style="margin-top:35px;">
    <h2 class="sgd-section-title">
        <span>🛡️</span> Guild Department Squad Vaults
    </h2>
    <div style="font-family:var(--font-pixel); font-size:9px; color:#ffd700;">
        COLLECTIVE SQUAD POWER
    </div>
</div>

<div class="sgd-faction-grid">
    <?php foreach ( $factions as $fac ): ?>
        <div class="sgd-faction-card">
            <div class="sgd-faction-crest"><?php echo $fac['crest']; ?></div>
            <h3 class="sgd-faction-title"><?php echo esc_html( $fac['name'] ); ?></h3>
            <p class="sgd-faction-desc"><?php echo esc_html( $fac['desc'] ); ?></p>
            <div class="sgd-faction-stats">
                <div class="sgd-fac-stat">
                    <span class="sgd-fac-stat-label">HEROES</span>
                    <span class="sgd-fac-stat-val"><?php echo $fac['members']; ?></span>
                </div>
                <div class="sgd-fac-stat">
                    <span class="sgd-fac-stat-label">TOTAL 💎 VAULT</span>
                    <span class="sgd-fac-stat-val" style="color:#00e5ff;"><?php echo number_format( $fac['diamonds'] ); ?></span>
                </div>
                <div class="sgd-fac-stat">
                    <span class="sgd-fac-stat-label">QUESTS WON</span>
                    <span class="sgd-fac-stat-val" style="color:#2ecc71;"><?php echo $fac['quests']; ?></span>
                </div>
                <div class="sgd-fac-stat">
                    <span class="sgd-fac-stat-label">SQUAD XP</span>
                    <span class="sgd-fac-stat-val" style="color:#ffd700;"><?php echo number_format( $fac['total_xp'] ); ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- PEER KUDOS & HERO MANA RECOGNITION SECTION -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:20px; margin-top:35px;">
    
    <!-- SEND KUDOS FORM CARD -->
    <div class="sgd-kudos-form-card" id="sgd-kudos-panel">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
            <span style="font-size:28px;">⚡</span>
            <div>
                <div style="font-family:var(--font-pixel); font-size:8px; color:#be2edd;">PEER RECOGNITION</div>
                <h3 style="font-family:var(--font-title); font-size:18px; color:#fff; margin:0;">
                    Send Mana & Kudos to a Teammate
                </h3>
            </div>
        </div>
        <p style="color:#b8c7e0; font-size:13px; margin-bottom:15px;">
            Notice a colleague answering SOS alerts, reviewing PRs, or saving production? Send them bonus Diamonds from your wallet with a personal tribute!
        </p>

        <?php if ( $is_logged_in ): ?>
            <form method="post">
                <input type="hidden" name="sgd_action" value="sgd_send_kudos">

                <div class="sgd-form-group">
                    <label class="sgd-form-label">🎯 Choose Recipient Hero *</label>
                    <select name="to_user_id" id="sgd-kudos-recipient" class="sgd-form-select" required>
                        <option value="">-- Select a Teammate --</option>
                        <?php foreach ( $other_heroes as $oh ): ?>
                            <option value="<?php echo $oh['user_id']; ?>">
                                👤 <?php echo esc_html( $oh['display_name'] ); ?> (<?php echo esc_html( $oh['faction'] ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">💎 Mana Bounty to Gift *</label>
                    <div style="display:flex; gap:10px;">
                        <label style="flex:1; cursor:pointer;">
                            <input type="radio" name="kudos_amount" value="25" style="display:none;" class="sgd-kudos-radio">
                            <div class="sgd-kudos-pill">+25 💎</div>
                        </label>
                        <label style="flex:1; cursor:pointer;">
                            <input type="radio" name="kudos_amount" value="50" checked style="display:none;" class="sgd-kudos-radio">
                            <div class="sgd-kudos-pill active">+50 💎</div>
                        </label>
                        <label style="flex:1; cursor:pointer;">
                            <input type="radio" name="kudos_amount" value="100" style="display:none;" class="sgd-kudos-radio">
                            <div class="sgd-kudos-pill">+100 💎</div>
                        </label>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">📜 Recognition Message / Tribute *</label>
                    <textarea name="kudos_message" rows="3" class="sgd-form-textarea" placeholder="e.g. Huge shoutout for jumping into the SOS alert and fixing the server lag!" required></textarea>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                    <span style="font-size:12px; color:#8fa0bd;">
                        Wallet Balance: <strong style="color:#00e5ff;"><?php echo number_format( $my_diamonds ); ?> 💎</strong>
                    </span>
                    <button type="submit" class="sgd-btn sgd-btn-gold">
                        ⚡ Deliver Mana & Diamonds
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div style="background:#131826; border:1px solid #2e3b55; padding:20px; text-align:center; border-radius:6px;">
                <p style="color:#aaa; font-size:13px; margin-bottom:10px;">Please authenticate to send Kudos to colleagues.</p>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'login' ) ); ?>" class="sgd-btn sgd-btn-blue">Log In</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- LIVE GUILD KUDOS FEED -->
    <div class="sgd-kudos-feed-card">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
            <span style="font-size:24px;">💖</span>
            <div>
                <div style="font-family:var(--font-pixel); font-size:8px; color:#ff9ff3;">GUILD CHRONICLES</div>
                <h3 style="font-family:var(--font-title); font-size:18px; color:#fff; margin:0;">
                    Live Peer Recognition Feed
                </h3>
            </div>
        </div>

        <?php if ( empty( $recent_kudos ) ): ?>
            <div style="text-align:center; padding:30px; color:#777; font-size:13px;">
                No mana sent yet! Be the first hero to recognize a teammate today.
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:12px; max-height:420px; overflow-y:auto; padding-right:6px;">
                <?php foreach ( $recent_kudos as $kd ): 
                    $from_u = get_user_by( 'id', $kd->from_user_id );
                    $to_u   = get_user_by( 'id', $kd->to_user_id );
                ?>
                    <div class="sgd-kudos-item">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <div style="font-size:13px; color:#fff;">
                                <strong style="color:#e056fd;"><?php echo $from_u ? esc_html($from_u->display_name) : 'A Hero'; ?></strong>
                                <span style="color:#aaa; font-size:11px;">sent</span>
                                <span class="sgd-kudos-mini-badge">+<?php echo (int) $kd->amount_diamonds; ?> 💎</span>
                                <span style="color:#aaa; font-size:11px;">to</span>
                                <strong style="color:#ffd700;"><?php echo $to_u ? esc_html($to_u->display_name) : 'A Teammate'; ?></strong>
                            </div>
                            <span style="font-family:var(--font-pixel); font-size:7px; color:#666;">
                                <?php echo date( 'M d, H:i', strtotime( $kd->created_at ) ); ?>
                            </span>
                        </div>
                        <div style="font-size:12px; color:#dcdde1; font-style:italic; background:#121624; border-left:3px solid #be2edd; padding:6px 10px; border-radius:3px;">
                            "<?php echo esc_html( $kd->message ); ?>"
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
