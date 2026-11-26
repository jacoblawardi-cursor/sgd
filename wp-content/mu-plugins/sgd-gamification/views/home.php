<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id = get_current_user_id();
$diamonds = sgd_get_diamonds( $user_id );
$is_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();
$is_admin = current_user_can( 'administrator' );

$home_xp = sgd_get_user_xp_data( $user_id );
$home_streak = sgd_get_user_streak( $user_id );
$home_job_class = sgd_get_user_job_class( $user_id );
$home_avatar = sgd_get_user_avatar( $user_id );
$lvl30_candidates = $is_admin ? sgd_get_level_30_candidates() : [];

global $wpdb;
$table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
$table_quest_teams = $wpdb->prefix . 'sgd_quest_teams';
$table_tasks = $wpdb->prefix . 'sgd_tasks';
$table_daily_reports = $wpdb->prefix . 'sgd_daily_reports';

// 1. My Active Assigned Kanban Tasks
$my_active_tasks = $wpdb->get_results( $wpdb->prepare(
    "SELECT t.*, tm.name as team_name, tm.type as team_type 
     FROM $table_quest_tasks t 
     LEFT JOIN $table_quest_teams tm ON t.team_id = tm.id 
     WHERE t.column_name != 'Selesai' AND t.column_name != 'Batal' 
     AND (FIND_IN_SET(%d, t.assigned_members) OR t.assigned_members LIKE %s OR t.created_by = %d)
     ORDER BY (t.deadline IS NULL OR t.deadline = '0000-00-00 00:00:00') ASC, t.deadline ASC, t.id DESC 
     LIMIT 5",
    $user_id, '%' . $wpdb->esc_like( (string) $user_id ) . '%', $user_id
) );

$my_active_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM $table_quest_tasks WHERE column_name != 'Selesai' AND column_name != 'Batal' AND (FIND_IN_SET(%d, assigned_members) OR assigned_members LIKE %s OR created_by = %d)",
    $user_id, '%' . $wpdb->esc_like( (string) $user_id ) . '%', $user_id
) );

$first_my_task = ! empty( $my_active_tasks ) ? $my_active_tasks[0] : null;
$my_tasks_link = sgd_get_portal_url( 'quests' );

// 2. Urgent / Approaching Deadline Tasks (<= 3 Days, Today, or Overdue across all teams)
$urgent_deadline_tasks = $wpdb->get_results(
    "SELECT t.*, tm.name as team_name, tm.type as team_type 
     FROM $table_quest_tasks t 
     LEFT JOIN $table_quest_teams tm ON t.team_id = tm.id 
     WHERE t.column_name != 'Selesai' AND t.column_name != 'Batal' 
     AND t.deadline IS NOT NULL AND t.deadline != '0000-00-00 00:00:00' 
     AND t.deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY) 
     ORDER BY t.deadline ASC LIMIT 4"
);

// 3. Leaderboard Top 3 & User Rank
$leaderboard_all = function_exists('sgd_get_leaderboard_data') ? sgd_get_leaderboard_data() : [];
$leaderboard_top = array_slice( $leaderboard_all, 0, 3 );
$my_rank = 1;
foreach ( $leaderboard_all as $idx => $lb_user ) {
    if ( (int) ( $lb_user['user_id'] ?? 0 ) === (int) $user_id ) {
        $my_rank = $idx + 1;
        break;
    }
}

// 4. Daily Check-in & Tasks
$today = current_time( 'Y-m-d' );
$has_daily_report_today = (bool) $wpdb->get_var( $wpdb->prepare(
    "SELECT id FROM $table_daily_reports WHERE user_id = %d AND report_date = %s LIMIT 1",
    $user_id, $today
) );
$has_spun_wheel_today = ( get_user_meta( $user_id, 'sgd_last_wheel_spin', true ) === $today );

// 5. Classic Daily / Urgent Tasks Count
$daily_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM $table_tasks WHERE type = 'daily' AND status != 'completed' AND (assigned_to = 0 OR assigned_to = %d)",
    $user_id
) );
$urgent_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks WHERE type = 'urgent' AND status != 'completed'" );

// 6. Admin / Manager Alerts
$sos_requests = $is_admin ? $wpdb->get_results( "SELECT * FROM $table_tasks WHERE help_status = 'requested' ORDER BY need_help_time DESC" ) : [];
$pending_orders_count = $is_admin ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_redemptions WHERE status = 'pending'" ) : 0;
$all_teams = function_exists('sgd_get_all_quest_teams') ? sgd_get_all_quest_teams() : [];
?>

<div class="sgd-dashboard-wrap">

    <!-- FLASHING EMERGENCY SOS HELP ALERT BANNER (FOR ADMIN) -->
    <?php if ( $is_admin && ! empty( $sos_requests ) ): ?>
        <div class="sgd-sos-active-alert" style="background:radial-gradient(circle at 50% 20%, #4a1010 0%, #200505 100%); border:3px solid #ff4444; border-radius:10px; padding:16px 20px; margin-bottom:20px; box-shadow:0 0 25px rgba(255,68,68,0.5); animation:pulse-urgent 1.2s infinite; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <span style="font-size:32px;">🚨</span>
                <div>
                    <div style="font-family:var(--font-pixel); font-size:10px; color:#ff9999; letter-spacing:1px; text-transform:uppercase;">
                        EMERGENCY SOS: <?php echo count($sos_requests); ?> KARYAWAN MEMBUTUHKAN BANTUAN!
                    </div>
                    <div style="font-size:13px; color:#fff; margin-top:3px;">
                        <?php 
                        $first_sos = $sos_requests[0];
                        $sos_emp = get_user_by('id', $first_sos->need_help_by);
                        echo '<strong>' . ($sos_emp ? esc_html($sos_emp->display_name) : 'Karyawan') . '</strong> meminta bantuan pada: <em>' . esc_html($first_sos->title) . '</em>';
                        ?>
                    </div>
                </div>
            </div>
            <a href="<?php echo esc_url( sgd_get_portal_url('admin') ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold" style="white-space:nowrap;">
                🛡️ Buka SOS Dispatch Board ➔
            </a>
        </div>
    <?php endif; ?>

    <!-- LEVEL 30 PROMOTION ALERT BANNER (FOR ADMIN) -->
    <?php if ( $is_admin && ! empty( $lvl30_candidates ) ): ?>
        <div class="sgd-lvl30-home-banner" style="background:radial-gradient(circle at 50% 20%, #2e240a 0%, #151105 100%); border:3px solid #ffd700; border-radius:10px; padding:16px 20px; margin-bottom:20px; box-shadow:0 0 25px rgba(255,215,0,0.5); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <span style="font-size:34px;">👑</span>
                <div>
                    <div style="font-family:var(--font-pixel); font-size:10px; color:#ffd700; letter-spacing:1px; text-transform:uppercase;">
                        ⭐ PEMBERITAHUAN PROMOSI LEVEL 30 (CALON MANAGER):
                    </div>
                    <div style="font-size:13px; color:#fff; margin-top:3px;">
                        <?php 
                        $names_list = [];
                        foreach ( $lvl30_candidates as $c ) {
                            $names_list[] = '<strong>' . esc_html($c['display_name']) . '</strong> (' . esc_html($c['job_class']) . ')';
                        }
                        echo implode(', ', $names_list);
                        ?> telah mencapai Level 30! Silakan evaluasi dan promosikan menjadi Manager.
                    </div>
                </div>
            </div>
            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'admin', 'admin_tab' => 'promotion' ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold" style="white-space:nowrap;">
                👑 Buka Promotion Hall ➔
            </a>
        </div>
    <?php endif; ?>

    <!-- 1. HERO WELCOME & LEVEL PROGRESSION -->
    <div class="sgd-dashboard-hero">
        <div class="sgd-hero-top">
            <div class="sgd-hero-profile">
                <div class="sgd-hero-avatar-ring">
                    <span><?php echo $home_avatar; ?></span>
                    <span class="sgd-hero-lvl-tag">Lv.<?php echo $home_xp['level']; ?></span>
                </div>
                <div>
                    <h2 class="sgd-hero-greeting">
                        Selamat Datang kembali, <?php echo $is_logged_in ? esc_html($current_user->display_name) : 'Guest Hero'; ?>!
                        <span class="sgd-job-class-pill <?php echo strtolower($home_job_class) === 'manager' ? 'sgd-job-class-manager' : 'sgd-job-class-staff'; ?>">
                            <?php echo ( strtolower($home_job_class) === 'manager' ? '⭐ MANAGER' : '🎖️ STAFF' ); ?>
                        </span>
                    </h2>
                    <p class="sgd-hero-subtitle">
                        <?php echo esc_html( $home_xp['title'] ); ?> • Divisi: <strong style="color:#00e5ff;"><?php echo esc_html( $home_xp['faction'] ); ?></strong>
                    </p>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-btn sgd-btn-gold" style="font-weight:bold; box-shadow:0 0 15px rgba(255,215,0,0.35);">
                    🎯 Buka Papan Kanban ➔
                </a>
            </div>
        </div>

        <!-- Experience Bar -->
        <div class="sgd-hero-xp-section">
            <div class="sgd-hero-xp-info">
                <span style="color:#ffd700; font-weight:bold;">👑 Progres Level Hero:</span>
                <span style="color:#fff;">
                    <strong><?php echo number_format($home_xp['xp_in_level']); ?></strong> / <?php echo number_format($home_xp['xp_needed']); ?> XP
                    <span style="color:#ffd700; font-weight:bold;">(<?php echo $home_xp['percent']; ?>%)</span>
                </span>
            </div>
            <div class="sgd-hero-xp-track">
                <div class="sgd-hero-xp-fill" style="width:<?php echo $home_xp['percent']; ?>%;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:10px; color:#8fa0bd;">
                <span>Level <?php echo $home_xp['level']; ?></span>
                <span>✨ Total: <?php echo number_format($home_xp['total_xp']); ?> XP</span>
                <span>Target: Level <?php echo min(60, $home_xp['level'] + 1); ?></span>
            </div>
        </div>
    </div>

    <!-- 2. HIGH-IMPACT 4 KPI STATS METRIC CARDS -->
    <div class="sgd-kpi-grid">
        <a href="<?php echo esc_url( sgd_get_portal_url( 'shop' ) ); ?>" class="sgd-kpi-card kpi-diamonds" title="Klik untuk belanja di Cash Shop">
            <div class="sgd-kpi-icon" style="color:#00e5ff;">💎</div>
            <div class="sgd-kpi-content">
                <div class="sgd-kpi-label">Saldo Diamonds / Gold</div>
                <div class="sgd-kpi-value" style="color:#00e5ff;"><?php echo number_format($diamonds); ?> 💎</div>
                <div class="sgd-kpi-sub">Tukar Voucher & Cuti ➔</div>
            </div>
        </a>

        <a href="<?php echo esc_url( sgd_get_portal_url( 'achievements' ) ); ?>" class="sgd-kpi-card kpi-xp" title="Klik untuk melihat Pencapaian & Feats">
            <div class="sgd-kpi-icon" style="color:#ffd700;">⭐</div>
            <div class="sgd-kpi-content">
                <div class="sgd-kpi-label">Level & Total XP</div>
                <div class="sgd-kpi-value" style="color:#ffd700;">Lv. <?php echo $home_xp['level']; ?></div>
                <div class="sgd-kpi-sub"><?php echo number_format($home_xp['total_xp']); ?> Total XP • <?php echo $home_xp['ach_count']; ?> Badges</div>
            </div>
        </a>

        <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-kpi-card kpi-streak" title="Login harian untuk mempertahankan streak">
            <div class="sgd-kpi-icon" style="color:#ff9f43;">🔥</div>
            <div class="sgd-kpi-content">
                <div class="sgd-kpi-label">Daily Login Streak</div>
                <div class="sgd-kpi-value" style="color:#ff9f43;"><?php echo (int) $home_streak['current']; ?> Hari</div>
                <div class="sgd-kpi-sub">
                    <?php echo $home_streak['bonus_percent'] > 0 ? "+{$home_streak['bonus_percent']}% XP Bonus Aktif!" : "Pertahankan streak tiap hari"; ?>
                </div>
            </div>
        </a>

        <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-kpi-card kpi-tasks" title="Buka Papan Kanban">
            <div class="sgd-kpi-icon" style="color:#2ecc71;">🎯</div>
            <div class="sgd-kpi-content">
                <div class="sgd-kpi-label">Tugas Aktif Saya</div>
                <div class="sgd-kpi-value" style="color:#2ecc71;"><?php echo $my_active_count; ?> Tugas</div>
                <div class="sgd-kpi-sub">
                    Buka Papan Kanban ➔
                </div>
            </div>
        </a>
    </div>

    <!-- 3. QUICK LAUNCHPAD TOOLBAR (ALL MODULES IN 1 CLICK) -->
    <div class="sgd-quick-launchpad">
        <div class="sgd-launchpad-title">
            <span>⚡ Akses Cepat Modul Utama (Quick Launchpad)</span>
        </div>
        <div class="sgd-launchpad-tiles">
            <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-launch-tile highlight" title="Papan Kanban Tugas Terintegrasi">
                <span class="sgd-tile-icon">🎯</span>
                <span>Papan Kanban</span>
            </a>

            <a href="<?php echo esc_url( sgd_get_portal_url( 'laporan-harian' ) ); ?>" class="sgd-launch-tile" title="Laporan Harian Lapangan Suara & AI Decipher">
                <span class="sgd-tile-icon">📋</span>
                <span>Laporan Harian</span>
                <?php if ( ! $has_daily_report_today ): ?>
                    <span class="sgd-tile-badge">+200XP</span>
                <?php endif; ?>
            </a>

            <a href="<?php echo esc_url( sgd_get_portal_url( 'wheel' ) ); ?>" class="sgd-launch-tile" title="Putar Roda Keberuntungan Harian">
                <span class="sgd-tile-icon">🎰</span>
                <span>Fortune Wheel</span>
                <?php if ( ! $has_spun_wheel_today ): ?>
                    <span class="sgd-tile-badge">Free</span>
                <?php endif; ?>
            </a>

            <a href="<?php echo esc_url( sgd_get_portal_url( 'shop' ) ); ?>" class="sgd-launch-tile" title="Tukar Diamonds dengan Voucher Uang & Cuti">
                <span class="sgd-tile-icon">💎</span>
                <span>Cash Shop</span>
            </a>

            <a href="<?php echo esc_url( sgd_get_portal_url( 'leaderboard' ) ); ?>" class="sgd-launch-tile" title="Peringkat Karyawan & Skuad Tim">
                <span class="sgd-tile-icon">👑</span>
                <span>Hall of Fame</span>
            </a>

            <a href="<?php echo esc_url( sgd_get_portal_url( 'daily' ) ); ?>" class="sgd-launch-tile" title="Tugas Harian / Daily Commissions">
                <span class="sgd-tile-icon">📜</span>
                <span>Daily Tasks</span>
                <?php if ( $daily_count > 0 ): ?>
                    <span class="sgd-tile-badge"><?php echo $daily_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo esc_url( sgd_get_portal_url( 'urgent' ) ); ?>" class="sgd-launch-tile" title="Tugas Mendesak Kritis (+1,000 Diamonds)">
                <span class="sgd-tile-icon">⚡</span>
                <span>Urgent Tasks</span>
                <?php if ( $urgent_count > 0 ): ?>
                    <span class="sgd-tile-badge"><?php echo $urgent_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo esc_url( sgd_get_portal_url( 'bounties' ) ); ?>" class="sgd-launch-tile" title="Papan Permintaan Bantuan Rekan Kerja">
                <span class="sgd-tile-icon">🤝</span>
                <span>Bounty Board</span>
            </a>

            <?php if ( $is_admin ): ?>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'admin' ) ); ?>" class="sgd-launch-tile admin-tile" title="Pusat Kendali Administrator">
                    <span class="sgd-tile-icon">🛡️</span>
                    <span>Admin Center</span>
                    <?php if ( $pending_orders_count > 0 || ! empty( $sos_requests ) ): ?>
                        <span class="sgd-tile-badge">!</span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. MAIN DASHBOARD 2-COLUMN GRID -->
    <div class="sgd-dashboard-grid">

        <!-- LEFT COLUMN: FOCUS & ACTIONABLES -->
        <div class="sgd-dash-col-left">

            <!-- CARD 1: TUGAS AKTIF SAYA -->
            <div class="sgd-dash-card">
                <div class="sgd-dash-card-header">
                    <h3 class="sgd-dash-card-title">
                        <span>🎯 Tugas & Misi Saya Hari Ini</span>
                        <span style="font-size:11px; background:rgba(0,229,255,0.15); color:#00e5ff; padding:2px 8px; border-radius:10px; font-weight:normal;">
                            <?php echo count($my_active_tasks); ?> Berjalan
                        </span>
                    </h3>
                    <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-dash-card-link">
                        Buka Semua di Kanban ➔
                    </a>
                </div>
                <div class="sgd-dash-card-body">
                    <?php if ( empty( $my_active_tasks ) ): ?>
                        <div style="text-align:center; padding:26px 12px; color:#8fa0bd;">
                            <span style="font-size:36px; display:block; margin-bottom:8px;">🎉</span>
                            <div style="font-weight:bold; color:#fff; margin-bottom:4px;">Semua tugas Anda saat ini telah selesai!</div>
                            <div style="font-size:12px; margin-bottom:14px;">Belum ada tugas aktif yang ditugaskan kepada Anda.</div>
                            <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue">
                                + Ambil Tugas Baru di Kanban
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="sgd-dash-task-list">
                            <?php foreach ( $my_active_tasks as $mt ): 
                                $mt_d_ts = ! empty( $mt->deadline ) && $mt->deadline !== '0000-00-00 00:00:00' ? strtotime( $mt->deadline ) : null;
                                $mt_dl_badge = '';
                                if ( $mt_d_ts ) {
                                    $diff_days = (int) round( ( strtotime( 'today', $mt_d_ts ) - strtotime( 'today', current_time('timestamp') ) ) / 86400 );
                                    if ( $diff_days < 0 ) {
                                        $mt_dl_badge = '<span class="sgd-task-deadline-pill overdue">⚠️ Lewat: ' . date('d M', $mt_d_ts) . '</span>';
                                    } elseif ( $diff_days === 0 ) {
                                        $mt_dl_badge = '<span class="sgd-task-deadline-pill today">🚨 Hari Ini (' . date('H:i', $mt_d_ts) . ')</span>';
                                    } elseif ( $diff_days === 1 ) {
                                        $mt_dl_badge = '<span class="sgd-task-deadline-pill urgent-3days">⏰ Besok</span>';
                                    } else {
                                        $mt_dl_badge = '<span class="sgd-task-deadline-pill safe">📅 ' . date('d M', $mt_d_ts) . '</span>';
                                    }
                                }

                                // Checklist completion ratio
                                $chk_raw = json_decode( $mt->checklist ?? '[]', true );
                                $chk_total = is_array($chk_raw) ? count($chk_raw) : 0;
                                $chk_done = is_array($chk_raw) ? count(array_filter($chk_raw, fn($c) => !empty($c['completed']))) : 0;
                            ?>
                                <div class="sgd-dash-task-item">
                                    <div class="sgd-dash-task-top">
                                        <div style="flex:1;">
                                            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $mt->team_id, 'open_task' => $mt->id, 'filter' => 'my-tasks' ], home_url( '/' ) ) ); ?>" class="sgd-dash-task-title">
                                                <?php echo esc_html( $mt->title ); ?>
                                            </a>
                                            <div style="font-size:11px; color:#8fa0bd; margin-top:2px;">
                                                Grup: <strong><?php echo esc_html( $mt->team_name ?: 'Tim' ); ?></strong>
                                            </div>
                                        </div>
                                        <div style="text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                                            <span style="font-size:10px; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.08); color:#ffd700; font-family:var(--font-pixel, monospace); font-weight:bold;">
                                                Lv.<?php echo max(1, (int)$mt->quest_level); ?>
                                            </span>
                                            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $mt->team_id, 'open_task' => $mt->id, 'filter' => 'my-tasks' ], home_url( '/' ) ) ); ?>" class="sgd-dash-task-open-btn" title="Buka Kartu Tugas">
                                                Buka Kartu ➔
                                            </a>
                                        </div>
                                    </div>
                                    <div class="sgd-dash-task-meta">
                                        <span class="sgd-card-status-pill" style="font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(0,229,255,0.1); border:1px solid rgba(0,229,255,0.3); color:#00e5ff;">
                                            <?php echo esc_html( $mt->column_name ); ?>
                                        </span>
                                        <?php if ( $mt_dl_badge ) echo $mt_dl_badge; ?>
                                        <?php if ( $chk_total > 0 ): ?>
                                            <span style="font-size:10px; color:#8fa0bd;">
                                                ☑️ Checklist: <strong><?php echo $chk_done; ?>/<?php echo $chk_total; ?></strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ( ! empty($mt->cover_image) && $mt->cover_image !== 'none' ): ?>
                                            <span style="font-size:10px; color:#ffd700;">🖼️ Cover</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CARD 2: PERINGATAN DEADLINE MENDESAK -->
            <div class="sgd-dash-card" style="border-color:rgba(255,71,87,0.35);">
                <div class="sgd-dash-card-header" style="background:rgba(255,71,87,0.06);">
                    <h3 class="sgd-dash-card-title" style="color:#ff6b81;">
                        <span>🚨 Peringatan Deadline Mendesak (&le; 3 Hari)</span>
                    </h3>
                    <span style="font-size:11px; color:#8fa0bd;">Semua Tim</span>
                </div>
                <div class="sgd-dash-card-body">
                    <?php if ( empty( $urgent_deadline_tasks ) ): ?>
                        <div style="text-align:center; padding:16px 12px; color:#2ecc71; font-size:12px;">
                            ✅ Tidak ada tugas yang mendekati deadline mendesak saat ini. Semua jadwal aman!
                        </div>
                    <?php else: ?>
                        <div class="sgd-dash-task-list">
                            <?php foreach ( $urgent_deadline_tasks as $ut ): 
                                $ut_d_ts = strtotime( $ut->deadline );
                                $diff_days = (int) round( ( strtotime( 'today', $ut_d_ts ) - strtotime( 'today', current_time('timestamp') ) ) / 86400 );
                                $urgency_tag = '';
                                if ( $diff_days < 0 ) {
                                    $urgency_tag = '<span class="sgd-task-deadline-pill overdue">⚠️ Lewat: ' . date('d M', $ut_d_ts) . '</span>';
                                } elseif ( $diff_days === 0 ) {
                                    $urgency_tag = '<span class="sgd-task-deadline-pill today">🚨 Hari Ini (' . date('H:i', $ut_d_ts) . ')</span>';
                                } elseif ( $diff_days === 1 ) {
                                    $urgency_tag = '<span class="sgd-task-deadline-pill urgent-3days">⏰ Besok</span>';
                                } else {
                                    $urgency_tag = '<span class="sgd-task-deadline-pill urgent-3days">⏰ ' . $diff_days . ' hari lagi</span>';
                                }
                            ?>
                                <div class="sgd-dash-task-item" style="border-left:3px solid #ff4757;">
                                    <div class="sgd-dash-task-top">
                                        <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $ut->team_id, 'open_task' => $ut->id ], home_url( '/' ) ) ); ?>" class="sgd-dash-task-title">
                                            <?php echo esc_html( $ut->title ); ?>
                                        </a>
                                        <?php echo $urgency_tag; ?>
                                    </div>
                                    <div class="sgd-dash-task-meta">
                                        <span style="color:#8fa0bd;">Grup: <strong><?php echo esc_html( $ut->team_name ?: 'Tim' ); ?></strong></span>
                                        <span style="color:#718093;">• Kolom: <?php echo esc_html( $ut->column_name ); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: OVERVIEW & PROGRESSION -->
        <div class="sgd-dash-col-right">

            <!-- CARD 3: TOP 3 LEADERBOARD (HALL OF FAME) -->
            <div class="sgd-dash-card">
                <div class="sgd-dash-card-header">
                    <h3 class="sgd-dash-card-title">
                        <span>👑 Peringkat Karyawan (Top 3)</span>
                    </h3>
                    <a href="<?php echo esc_url( sgd_get_portal_url( 'leaderboard' ) ); ?>" class="sgd-dash-card-link">
                        Peringkat Lengkap ➔
                    </a>
                </div>
                <div class="sgd-dash-card-body">
                    <div class="sgd-podium-list">
                        <?php 
                        $medals = [ '🥇', '🥈', '🥉' ];
                        foreach ( $leaderboard_top as $idx => $lead_user ): 
                        ?>
                            <div class="sgd-podium-item rank-<?php echo $idx + 1; ?>">
                                <div class="sgd-podium-left">
                                    <span class="sgd-podium-rank"><?php echo $medals[$idx] ?? '#' . ($idx + 1); ?></span>
                                    <span style="font-size:22px;"><?php echo $lead_user['avatar'] ?? '👤'; ?></span>
                                    <div>
                                        <div class="sgd-podium-name"><?php echo esc_html( $lead_user['display_name'] ); ?></div>
                                        <div class="sgd-podium-sub">
                                            Lv.<?php echo $lead_user['level']; ?> • <?php echo esc_html($lead_user['job_class']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="sgd-podium-score">
                                    <?php echo number_format($lead_user['total_xp']); ?> XP
                                    <div style="font-size:10px; color:#00e5ff; font-weight:normal;"><?php echo number_format($lead_user['diamonds']); ?> 💎</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top:14px; padding:10px 12px; background:rgba(0,229,255,0.06); border:1px solid rgba(0,229,255,0.2); border-radius:6px; font-size:11px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#8fa0bd;">Posisi Peringkat Anda:</span>
                        <strong style="color:#00e5ff; font-size:13px;">#<?php echo $my_rank; ?> dari <?php echo count($leaderboard_all); ?> Hero</strong>
                    </div>
                </div>
            </div>

            <!-- CARD 4: DAILY CHECK-IN & REWARDS -->
            <div class="sgd-dash-card">
                <div class="sgd-dash-card-header">
                    <h3 class="sgd-dash-card-title">
                        <span>🎁 Check-in Harian & Reward</span>
                    </h3>
                </div>
                <div class="sgd-dash-card-body">
                    <div class="sgd-checkin-list">
                        <div class="sgd-checkin-item done">
                            <span>🔥 Daily Streak Login</span>
                            <span class="sgd-checkin-status done">✅ <?php echo (int) $home_streak['current']; ?> Hari Aktif</span>
                        </div>

                        <div class="sgd-checkin-item <?php echo $has_spun_wheel_today ? 'done' : ''; ?>">
                            <span>🎰 Fortune Wheel (Spin Harian)</span>
                            <?php if ( $has_spun_wheel_today ): ?>
                                <span class="sgd-checkin-status done">✅ Selesai</span>
                            <?php else: ?>
                                <a href="<?php echo esc_url( sgd_get_portal_url( 'wheel' ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold" style="font-size:10px; padding:2px 8px;">
                                    Putar Sekarang ➔
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="sgd-checkin-item <?php echo $has_daily_report_today ? 'done' : ''; ?>">
                            <span>📋 Laporan Harian Proyek (+200 XP)</span>
                            <?php if ( $has_daily_report_today ): ?>
                                <span class="sgd-checkin-status done">✅ Terkirim</span>
                            <?php else: ?>
                                <a href="<?php echo esc_url( sgd_get_portal_url( 'laporan-harian' ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue" style="font-size:10px; padding:2px 8px;">
                                    Isi Laporan ➔
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 5: TIM & PROYEK SAYA (WORKSPACES) -->
            <div class="sgd-dash-card">
                <div class="sgd-dash-card-header">
                    <h3 class="sgd-dash-card-title">
                        <span>🏢 Grup & Tim Workspace</span>
                    </h3>
                    <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-dash-card-link">
                        Lihat Semua ➔
                    </a>
                </div>
                <div class="sgd-dash-card-body">
                    <div class="sgd-team-chips-list">
                        <?php foreach ( $all_teams as $atm ): 
                            $type_icon = $atm->type === 'hq' ? '🏢' : ( $atm->type === 'project' ? '🚀' : '👥' );
                            $member_count = count( $atm->members ?? [] );
                        ?>
                            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $atm->id ], home_url( '/' ) ) ); ?>" class="sgd-team-chip-item">
                                <div class="sgd-team-chip-left">
                                    <span style="font-size:18px;"><?php echo $type_icon; ?></span>
                                    <div>
                                        <div class="sgd-team-chip-title"><?php echo esc_html( $atm->name ); ?></div>
                                        <div style="font-size:10px; color:#8fa0bd;"><?php echo strtoupper( $atm->type ); ?> • <?php echo $member_count; ?> Anggota</div>
                                    </div>
                                </div>
                                <span class="sgd-team-chip-count">Buka ➔</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- QUICK DEMO ROLE SWITCHER (FOOTNOTE) -->
    <div style="margin-top:25px; padding:14px 18px; background:#090e1a; border:1px solid rgba(255,215,0,0.2); border-radius:10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <span style="font-family:var(--font-pixel); font-size:9px; color:#8fa0bd;">QUICK DEMO ROLE SWITCHER:</span>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <form method="post" style="display:inline;">
                <input type="hidden" name="sgd_action" value="sgd_switch_user">
                <input type="hidden" name="target_user" value="employee">
                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-blue">⚔️ Arthas (Staff)</button>
            </form>
            <form method="post" style="display:inline;">
                <input type="hidden" name="sgd_action" value="sgd_switch_user">
                <input type="hidden" name="target_user" value="employee2">
                <button type="submit" class="sgd-btn sgd-btn-sm" style="background:#8e44ad; border-color:#be2edd; color:#fff;">🧙‍♀️ Jaina (Staff 2)</button>
            </form>
            <form method="post" style="display:inline;">
                <input type="hidden" name="sgd_action" value="sgd_switch_user">
                <input type="hidden" name="target_user" value="admin">
                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">👑 Admin / Manager</button>
            </form>
        </div>
    </div>

</div>
