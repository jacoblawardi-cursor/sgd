<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Security Guard: Quests is accessible by Administrator and Team Managers
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
if ( ! current_user_can( 'administrator' ) && ! ( function_exists('sgd_user_can_access_quests') && sgd_user_can_access_quests( $current_user_id ) ) ) {
    echo '<div class="sgd-flash-banner sgd-flash-error">⛔ Akses Ditolak: Halaman Quests (Kanban) hanya dapat diakses oleh Administrator dan Manager Tim.</div>';
    return;
}

global $wpdb;
$active_theme = function_exists( 'sgd_get_current_theme' ) ? sgd_get_current_theme() : 'wow';

// Get parameters
$team_id = (int) ( $_GET['team_id'] ?? 0 );
$filter_type = sanitize_text_field( $_GET['filter_type'] ?? '' );
$open_task_id = (int) ( $_GET['open_task'] ?? 0 );
$users = get_users();

// If team_id is specified, load that team's Kanban board
$current_team = null;
$can_manage_team = false;
if ( $team_id > 0 ) {
    $current_team = sgd_get_quest_team( $team_id );
    if ( $current_team ) {
        $can_manage_team = sgd_user_can_manage_quest( $current_team, $current_user_id );
    }
} else {
    $can_manage_team = current_user_can( 'administrator' );
}

// Columns definition
$kanban_columns = [
    'To do list' => [
        'title' => 'To do list',
        'icon'  => '📝',
        'color' => '#00e5ff',
        'bg'    => 'rgba(0, 229, 255, 0.08)',
        'border'=> 'rgba(0, 229, 255, 0.35)',
    ],
    'Dikerjakan' => [
        'title' => 'Dikerjakan',
        'icon'  => '⏳',
        'color' => '#ff9f43',
        'bg'    => 'rgba(255, 159, 67, 0.08)',
        'border'=> 'rgba(255, 159, 67, 0.35)',
    ],
    'Selesai' => [
        'title' => 'Selesai',
        'icon'  => '✅',
        'color' => '#2ecc71',
        'bg'    => 'rgba(46, 204, 113, 0.08)',
        'border'=> 'rgba(46, 204, 113, 0.35)',
    ],
    'Batal' => [
        'title' => 'Batal',
        'icon'  => '🚫',
        'color' => '#ff5252',
        'bg'    => 'rgba(255, 82, 82, 0.08)',
        'border'=> 'rgba(255, 82, 82, 0.35)',
    ],
];
?>

<div class="sgd-quests-wrapper">

    <!-- TOP HEADER BAR -->
    <div class="sgd-quests-top-bar">
        <div class="sgd-quests-header-left">
            <span class="sgd-quests-icon">🎯</span>
            <div>
                <h1 class="sgd-quests-title">
                    QUESTS — CICLE KANBAN BOARD
                </h1>
                <p class="sgd-quests-subtitle">
                    Sistem manajemen tugas terintegrasi: Task Group (HQ, Tim, Proyek) & Papan Kanban 4 Kolom ala Cicle
                </p>
            </div>
        </div>

        <div class="sgd-quests-header-actions">
            <?php if ( $current_team ): ?>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue">
                    ◀ Semua Task Group
                </a>
            <?php endif; ?>
            <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-gold" id="sgd-btn-open-create-team">
                + Buat Tim Baru
            </button>
        </div>
    </div>

    <?php if ( ! $current_team ): ?>
        <!-- ======================================================== -->
        <!-- VIEW 1: OVERVIEW DAFTAR TASK GROUP (HQ, TIM, PROYEK) -->
        <!-- ======================================================== -->
        
        <!-- Filter Tabs for Task Group Types -->
        <div class="sgd-quests-filters-bar">
            <div class="sgd-quests-filter-pills">
                <a href="<?php echo esc_url( sgd_get_portal_url( 'quests' ) ); ?>" class="sgd-filter-pill <?php echo ( $filter_type === '' ) ? 'active' : ''; ?>">
                    ⭐ Semua Task Group
                </a>
                <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'filter_type' => 'hq' ], home_url( '/' ) ) ); ?>" class="sgd-filter-pill <?php echo ( $filter_type === 'hq' ) ? 'active' : ''; ?>">
                    🏢 HQ (Markas Besar)
                </a>
                <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'filter_type' => 'tim' ], home_url( '/' ) ) ); ?>" class="sgd-filter-pill <?php echo ( $filter_type === 'tim' ) ? 'active' : ''; ?>">
                    👥 Tim (Regu Kerja)
                </a>
                <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'filter_type' => 'proyek' ], home_url( '/' ) ) ); ?>" class="sgd-filter-pill <?php echo ( $filter_type === 'proyek' ) ? 'active' : ''; ?>">
                    🚀 Proyek (Inisiatif)
                </a>
            </div>
        </div>

        <?php 
        $teams_list = sgd_get_quest_teams( $filter_type );
        ?>

        <?php if ( empty( $teams_list ) ): ?>
            <div class="sgd-quests-empty-box">
                <span style="font-size:42px; display:block; margin-bottom:12px;">📋</span>
                <h3 style="color:#ffd700; font-family:var(--font-title); font-size:18px;">Belum Ada Task Group</h3>
                <p style="color:#8fa0bd; font-size:13px; max-width:500px; margin:8px auto 18px auto;">
                    Mulai dengan membuat task group baru. Anda dapat memilih tipe HQ, Tim, atau Proyek serta menentukan Manager dan anggota!
                </p>
                <button type="button" class="sgd-btn sgd-btn-gold" id="sgd-btn-open-create-team-empty">
                    + Buat Task Group Baru Sekarang
                </button>
            </div>
        <?php else: ?>
            <div class="sgd-teams-grid">
                <?php foreach ( $teams_list as $t ): 
                    $badge_class = 'type-' . esc_attr( $t->type );
                    $type_icons = [ 'hq' => '🏢 HQ', 'tim' => '👥 Tim', 'proyek' => '🚀 Proyek' ];
                    $type_label = $type_icons[ $t->type ] ?? '👥 Tim';
                    $total = $t->counts['total'];
                    $done = $t->counts['Selesai'];
                    $progress_percent = $total > 0 ? round( ( $done / $total ) * 100 ) : 0;
                ?>
                    <div class="sgd-team-card">
                        <div class="sgd-team-card-header">
                            <span class="sgd-team-type-badge <?php echo esc_attr( $badge_class ); ?>">
                                <?php echo esc_html( $type_label ); ?>
                            </span>
                            <div class="sgd-team-card-actions">
                                <button type="button" class="sgd-team-edit-btn" data-team-id="<?php echo esc_attr( $t->id ); ?>" data-team-name="<?php echo esc_attr( $t->name ); ?>" data-team-type="<?php echo esc_attr( $t->type ); ?>" data-team-desc="<?php echo esc_attr( $t->description ); ?>" data-team-mgr="<?php echo esc_attr( $t->manager_id ); ?>" data-team-members="<?php echo esc_attr( $t->member_ids ); ?>" title="Edit Pengaturan Tim">
                                    ⚙️
                                </button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Task Group \'<?php echo esc_js( $t->name ); ?>\' beserta seluruh tugas di dalamnya?');">
                                    <input type="hidden" name="sgd_action" value="sgd_delete_quest_team">
                                    <input type="hidden" name="team_id" value="<?php echo esc_attr( $t->id ); ?>">
                                    <button type="submit" class="sgd-team-del-btn" title="Hapus Task Group">🗑️</button>
                                </form>
                            </div>
                        </div>

                        <h3 class="sgd-team-title">
                            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $t->id ], home_url( '/' ) ) ); ?>">
                                <?php echo esc_html( $t->name ); ?>
                            </a>
                        </h3>

                        <?php if ( ! empty( $t->description ) ): ?>
                            <p class="sgd-team-desc">
                                <?php echo esc_html( wp_trim_words( $t->description, 18 ) ); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Manager & Members Showcase -->
                        <div class="sgd-team-members-block">
                            <div class="sgd-team-mgr-pill" title="Manager Task Group: <?php echo esc_attr( $t->manager_name ); ?>">
                                <span class="sgd-mgr-icon"><?php echo $t->manager_avatar; ?></span>
                                <span class="sgd-mgr-name">Manager: <strong><?php echo esc_html( $t->manager_name ); ?></strong></span>
                            </div>

                            <div class="sgd-team-member-avatars">
                                <?php 
                                $shown_count = 0;
                                foreach ( $t->members as $mb ): 
                                    if ( $shown_count < 5 ):
                                ?>
                                    <span class="sgd-member-chip" title="<?php echo esc_attr( $mb['display_name'] . ' (' . $mb['job_class'] . ')' ); ?>">
                                        <?php echo $mb['avatar']; ?>
                                    </span>
                                <?php 
                                    $shown_count++;
                                    endif;
                                endforeach; 
                                if ( count( $t->members ) > 5 ):
                                ?>
                                    <span class="sgd-member-more">+<?php echo ( count( $t->members ) - 5 ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Mini Kanban Stats Summary -->
                        <div class="sgd-team-stats-row">
                            <span class="sgd-stat-chip todo" title="To do list: <?php echo $t->counts['To do list']; ?>">
                                📝 <?php echo $t->counts['To do list']; ?>
                            </span>
                            <span class="sgd-stat-chip inprog" title="Dikerjakan: <?php echo $t->counts['Dikerjakan']; ?>">
                                ⏳ <?php echo $t->counts['Dikerjakan']; ?>
                            </span>
                            <span class="sgd-stat-chip done" title="Selesai: <?php echo $t->counts['Selesai']; ?>">
                                ✅ <?php echo $t->counts['Selesai']; ?>
                            </span>
                            <span class="sgd-stat-chip cancel" title="Batal: <?php echo $t->counts['Batal']; ?>">
                                🚫 <?php echo $t->counts['Batal']; ?>
                            </span>
                            <span class="sgd-stat-total">
                                Total: <strong><?php echo $total; ?></strong>
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="sgd-team-progress-bar-wrap" title="<?php echo $progress_percent; ?>% tugas selesai">
                            <div class="sgd-team-progress-fill" style="width:<?php echo $progress_percent; ?>%;"></div>
                        </div>

                        <!-- Open Board CTA -->
                        <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $t->id ], home_url( '/' ) ) ); ?>" class="sgd-team-open-btn">
                            Buka Kanban Board ➔
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ======================================================== -->
        <!-- VIEW 2: KANBAN BOARD 4 KOLOM UNTUK TIM TERPILIH -->
        <!-- ======================================================== -->
        <?php
        $type_icons = [ 'hq' => '🏢 HQ', 'tim' => '👥 Tim', 'proyek' => '🚀 Proyek' ];
        $type_label = $type_icons[ $current_team->type ] ?? '👥 Tim';
        $badge_class = 'type-' . esc_attr( $current_team->type );
        $all_teams = sgd_get_quest_teams();
        ?>

        <!-- Active Board Header Banner -->
        <div class="sgd-board-header-banner">
            <div class="sgd-board-header-info">
                <div class="sgd-board-title-line">
                    <span class="sgd-team-type-badge <?php echo esc_attr( $badge_class ); ?>">
                        <?php echo esc_html( $type_label ); ?>
                    </span>
                    <h2 class="sgd-board-title"><?php echo esc_html( $current_team->name ); ?></h2>
                </div>
                <?php if ( ! empty( $current_team->description ) ): ?>
                    <p class="sgd-board-desc"><?php echo esc_html( $current_team->description ); ?></p>
                <?php endif; ?>

                <div class="sgd-board-meta-row">
                    <span class="sgd-board-meta-item">
                        ⭐ Manager: <strong><?php echo esc_html( $current_team->manager_name ); ?></strong>
                    </span>
                    <span class="sgd-board-meta-item">
                        👥 Anggota (<?php echo count( $current_team->members ); ?>): 
                        <span class="sgd-board-member-avatars">
                            <?php foreach ( $current_team->members as $m ): ?>
                                <span class="sgd-board-avatar" title="<?php echo esc_attr( $m['display_name'] . ' (' . $m['job_class'] . ')' ); ?>"><?php echo $m['avatar']; ?></span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="sgd-board-header-ctrls">
                <!-- Quick Switcher Dropdown -->
                <div class="sgd-board-switcher">
                    <label for="sgd-team-switch-select">Ganti Tim:</label>
                    <select id="sgd-team-switch-select" class="sgd-form-select" onchange="if(this.value) window.location.href=this.value;">
                        <?php foreach ( $all_teams as $alt ): ?>
                            <option value="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $alt->id ], home_url( '/' ) ) ); ?>" <?php selected( $alt->id, $current_team->id ); ?>>
                                [<?php echo strtoupper( $alt->type ); ?>] <?php echo esc_html( $alt->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-btn-edit-current-team" data-team-id="<?php echo esc_attr( $current_team->id ); ?>" data-team-name="<?php echo esc_attr( $current_team->name ); ?>" data-team-type="<?php echo esc_attr( $current_team->type ); ?>" data-team-desc="<?php echo esc_attr( $current_team->description ); ?>" data-team-mgr="<?php echo esc_attr( $current_team->manager_id ); ?>" data-team-members="<?php echo esc_attr( $current_team->member_ids ); ?>">
                    ⚙️ Pengaturan Tim
                </button>
            </div>
        </div>

        <!-- THE 4 KANBAN COLUMNS CONTAINER -->
        <?php
        $team_members_pool = $current_team ? ( $current_team->members ?? [] ) : [];
        if ( empty( $team_members_pool ) ) {
            $all_users = get_users();
            foreach ( $all_users as $u ) {
                $team_members_pool[] = [
                    'id'           => $u->ID,
                    'display_name' => $u->display_name,
                    'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤',
                    'job_class'    => function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff',
                ];
            }
        }
        ?>
        <div class="sgd-kanban-board-container" id="sgd-kanban-board" data-team-id="<?php echo esc_attr( $current_team->id ); ?>">
            <?php 
            foreach ( $kanban_columns as $col_key => $col_meta ): 
                $tasks_in_col = sgd_get_quest_tasks( $current_team->id, $col_key );
                $task_count = count( $tasks_in_col );
            ?>
                <div class="sgd-kanban-column" data-column-name="<?php echo esc_attr( $col_key ); ?>" style="--col-accent:<?php echo esc_attr( $col_meta['color'] ); ?>; --col-bg:<?php echo esc_attr( $col_meta['bg'] ); ?>; --col-border:<?php echo esc_attr( $col_meta['border'] ); ?>;">
                    
                    <!-- Column Header -->
                    <div class="sgd-column-header">
                        <div class="sgd-column-header-title">
                            <span class="sgd-column-icon"><?php echo $col_meta['icon']; ?></span>
                            <span class="sgd-column-title-text"><?php echo esc_html( $col_meta['title'] ); ?></span>
                            <span class="sgd-column-count-badge" id="count-<?php echo sanitize_title( $col_key ); ?>">
                                <?php echo $task_count; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Add New Tugas Trigger Button -->
                    <div class="sgd-add-tugas-wrapper">
                        <button type="button" class="sgd-btn-add-tugas-toggle" data-column="<?php echo esc_attr( $col_key ); ?>" title="Tambah tugas baru ke kolom <?php echo esc_attr( $col_key ); ?>">
                            <span class="sgd-add-plus">+</span> Tambah tugas
                        </button>

                        <!-- Inline Form to ask name and submit "Buat tugas" -->
                        <div class="sgd-inline-task-form" id="inline-form-<?php echo sanitize_title( $col_key ); ?>" style="display:none;">
                            <form method="post" class="sgd-form-create-task" data-column="<?php echo esc_attr( $col_key ); ?>">
                                <input type="hidden" name="sgd_action" value="sgd_create_quest_task">
                                <input type="hidden" name="team_id" value="<?php echo esc_attr( $current_team->id ); ?>">
                                <input type="hidden" name="column_name" value="<?php echo esc_attr( $col_key ); ?>">
                                <div class="sgd-form-group" style="margin-bottom:8px;">
                                    <input type="text" name="task_title" class="sgd-form-input sgd-input-task-name" placeholder="Ketik nama tugas..." required maxlength="255" autocomplete="off">
                                </div>
                                <div class="sgd-inline-meta-row" style="display:flex; gap:8px; margin-bottom:8px;">
                                    <div style="flex:1;">
                                        <label style="font-size:10px; color:#ffd700; display:block; margin-bottom:2px; font-weight:bold;">⚔️ Lvl Quest:</label>
                                        <input type="number" name="quest_level" min="1" max="50" value="1" class="sgd-form-input" style="font-size:11px; padding:4px 6px;">
                                    </div>
                                    <div style="flex:1.4;">
                                        <label style="font-size:10px; color:#00e5ff; display:block; margin-bottom:2px; font-weight:bold;">🎁 Pilihan Hadiah:</label>
                                        <select name="reward_type" class="sgd-form-select" style="font-size:11px; padding:4px 6px;">
                                            <option value="both">👑 XP & Gold</option>
                                            <option value="xp">⭐ Hanya XP</option>
                                            <option value="gold">💎 Hanya Gold</option>
                                            <option value="none">⚪ Tanpa Hadiah</option>
                                        </select>
                                    </div>
                                    <div style="flex:1.3;">
                                        <label style="font-size:10px; color:#2ecc71; display:block; margin-bottom:2px; font-weight:bold;">🏷️ Label / Tag:</label>
                                        <select name="task_tag" class="sgd-form-select" style="font-size:11px; padding:4px 6px;">
                                            <option value="">⚪ Tanpa Tag</option>
                                            <option value="Fitur">✨ Fitur</option>
                                            <option value="Bug">🐛 Bug</option>
                                            <option value="UI/UX">🎨 UI/UX</option>
                                            <option value="Review">📋 Review</option>
                                            <option value="Urgent">🔴 Urgent</option>
                                            <option value="Optimasi">⚡ Optimasi</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sgd-inline-meta-row" style="display:flex; gap:8px; margin-bottom:10px;">
                                    <div style="flex:1;">
                                        <label style="font-size:10px; color:#a55eea; display:block; margin-bottom:2px; font-weight:bold;">👤 Tugaskan Anggota Tim:</label>
                                        <select name="assigned_user_id" class="sgd-form-select" style="font-size:11px; padding:4px 6px;">
                                            <option value="<?php echo esc_attr( $current_user_id ?: 1 ); ?>">👤 Saya Sendiri (<?php echo esc_html( wp_get_current_user()->display_name ?: 'Admin' ); ?>)</option>
                                            <?php foreach ( $team_members_pool as $tmb ): 
                                                if ( (int) $tmb['id'] !== (int) $current_user_id ):
                                            ?>
                                                <option value="<?php echo esc_attr( $tmb['id'] ); ?>">
                                                    <?php echo $tmb['avatar'] . ' ' . esc_html( $tmb['display_name'] ); ?>
                                                </option>
                                            <?php endif; endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="sgd-inline-form-actions">
                                    <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-green sgd-btn-buat-tugas">
                                        Buat tugas
                                    </button>
                                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue sgd-btn-cancel-inline" data-column="<?php echo esc_attr( $col_key ); ?>">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Column Cards Dropzone List -->
                    <div class="sgd-column-cards-list" id="cards-list-<?php echo sanitize_title( $col_key ); ?>" data-column="<?php echo esc_attr( $col_key ); ?>">
                        <?php if ( empty( $tasks_in_col ) ): ?>
                            <div class="sgd-column-empty-placeholder">
                                Belum ada tugas di kolom ini.
                            </div>
                        <?php else: ?>
                            <?php foreach ( $tasks_in_col as $task ): ?>
                                <?php 
                                $has_deadline = ( ! empty( $task->deadline ) && $task->deadline !== '0000-00-00 00:00:00' );
                                $dl_status = 'safe';
                                $dl_icon = '📅';
                                $dl_display = '';
                                $card_deadline_class = '';
                                $deadline_badge = '';

                                if ( $has_deadline ) {
                                    $d_ts = strtotime( $task->deadline );
                                    $now_ts = current_time( 'timestamp' );

                                    $today_start = strtotime( 'today', $now_ts );
                                    $deadline_day_start = strtotime( 'today', $d_ts );
                                    $calendar_diff_days = (int) round( ( $deadline_day_start - $today_start ) / 86400 );

                                    $is_completed = ( ( $task->column_name ?? '' ) === 'Selesai' );
                                    $is_cancelled = ( ( $task->column_name ?? '' ) === 'Batal' );

                                    if ( $is_completed ) {
                                        $dl_status = 'completed';
                                        $dl_icon = '✅';
                                        $dl_display = 'Selesai (' . date( 'd M Y', $d_ts ) . ')';
                                        $deadline_badge = '<span class="sgd-task-deadline-pill completed">✅ Selesai</span>';
                                    } elseif ( $is_cancelled ) {
                                        $dl_status = 'cancelled';
                                        $dl_icon = '🚫';
                                        $dl_display = 'Batal (' . date( 'd M Y', $d_ts ) . ')';
                                        $deadline_badge = '<span class="sgd-task-deadline-pill cancelled">🚫 Batal</span>';
                                    } elseif ( $d_ts < $now_ts && $calendar_diff_days < 0 ) {
                                        $dl_status = 'overdue';
                                        $dl_icon = '⚠️';
                                        $dl_display = 'Lewat: ' . date( 'd M Y', $d_ts );
                                        $card_deadline_class = 'sgd-card-deadline-urgent';
                                        $deadline_badge = '<span class="sgd-task-deadline-pill overdue" title="Lewat tenggat: ' . esc_attr( date( 'd M Y', $d_ts ) ) . '">⚠️ Lewat</span>';
                                    } elseif ( $calendar_diff_days <= 0 ) {
                                        $dl_status = 'today';
                                        $dl_icon = '🚨';
                                        $dl_display = 'Hari Ini (' . date( 'H:i', $d_ts ) . ')';
                                        $card_deadline_class = 'sgd-card-deadline-urgent';
                                        $deadline_badge = '<span class="sgd-task-deadline-pill today" title="Tenggat hari ini: ' . esc_attr( date( 'd M Y', $d_ts ) ) . '">🚨 Hari Ini</span>';
                                    } elseif ( $calendar_diff_days <= 3 ) {
                                        $dl_status = 'urgent-3days';
                                        $dl_icon = '⏰';
                                        if ( $calendar_diff_days === 1 ) {
                                            $dl_display = 'Besok (' . date( 'd M', $d_ts ) . ')';
                                        } else {
                                            $dl_display = $calendar_diff_days . ' Hari Lagi (' . date( 'd M', $d_ts ) . ')';
                                        }
                                        $card_deadline_class = 'sgd-card-deadline-urgent';
                                        $deadline_badge = '<span class="sgd-task-deadline-pill urgent-3days" title="Tenggat: ' . esc_attr( date( 'd M Y', $d_ts ) ) . '">⏰ ' . $calendar_diff_days . ' Hari</span>';
                                    } elseif ( $calendar_diff_days <= 7 ) {
                                        $dl_status = 'upcoming';
                                        $dl_icon = '⏳';
                                        $dl_display = $calendar_diff_days . ' Hari Lagi (' . date( 'd M', $d_ts ) . ')';
                                        $deadline_badge = '<span class="sgd-task-deadline-pill upcoming" title="Tenggat: ' . esc_attr( date( 'd M Y', $d_ts ) ) . '">⏳ ' . $calendar_diff_days . ' Hari</span>';
                                    } else {
                                        $dl_status = 'safe';
                                        $dl_icon = '📅';
                                        $dl_display = date( 'd M Y', $d_ts );
                                        $deadline_badge = '<span class="sgd-task-deadline-pill" title="Tenggat: ' . esc_attr( date( 'd M Y', $d_ts ) ) . '">📅 ' . esc_html( date( 'd M', $d_ts ) ) . '</span>';
                                    }
                                }
                                ?>
                                <div class="sgd-kanban-card <?php echo esc_attr( $card_deadline_class ); ?> <?php echo $can_manage_team ? 'sgd-card-manager-draggable' : ''; ?>" draggable="<?php echo $can_manage_team ? 'true' : 'false'; ?>" data-task-id="<?php echo esc_attr( $task->id ); ?>" data-column="<?php echo esc_attr( $task->column_name ); ?>">
                                    
                                    <!-- TAGS / LABELS ROW ON CARD -->
                                    <div class="sgd-card-tags-list">
                                        <?php if ( ! empty( $task->tags ) ): ?>
                                            <?php foreach ( $task->tags as $tg ): 
                                                $tg_color = esc_attr( $tg['color'] ?? '#00e5ff' );
                                                $tg_name = esc_html( $tg['name'] ?? '' );
                                            ?>
                                                <span class="sgd-card-tag-pill" style="--tag-color:<?php echo $tg_color; ?>; background:<?php echo $tg_color; ?>26; border-color:<?php echo $tg_color; ?>; color:<?php echo $tg_color; ?>;" title="Label: <?php echo $tg_name; ?>">
                                                    <span class="sgd-tag-dot" style="background:<?php echo $tg_color; ?>; box-shadow:0 0 6px <?php echo $tg_color; ?>;"></span>
                                                    <?php echo $tg_name; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php elseif ( $can_manage_team ): ?>
                                            <span class="sgd-card-tag-pill sgd-card-tag-pill-add" title="Klik kartu untuk menambahkan label/tag">
                                                <span class="sgd-tag-dot" style="background:#8fa0bd;"></span>
                                                + Tag
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="sgd-card-top">
                                        <div style="display:flex; align-items:center; gap:6px; flex:1;">
                                            <?php if ( $can_manage_team ): ?>
                                                <span class="sgd-card-drag-handle" title="⭐ Drag kartu untuk memindahkan status tugas">⠿</span>
                                            <?php endif; ?>
                                            <h4 class="sgd-card-title"><?php echo esc_html( $task->title ); ?></h4>
                                        </div>
                                        <div class="sgd-card-quick-actions">
                                            <button type="button" class="sgd-card-quick-btn sgd-btn-clone-task" data-task-id="<?php echo esc_attr( $task->id ); ?>" title="Duplikat Tugas (Clone Task)">
                                                📋
                                            </button>
                                            <button type="button" class="sgd-card-quick-btn sgd-btn-del-task" data-task-id="<?php echo esc_attr( $task->id ); ?>" data-task-title="<?php echo esc_attr( $task->title ); ?>" title="Hapus Tugas">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>

                                    <!-- QUEST LEVEL & REWARD PILLS -->
                                    <?php 
                                    $ql = max( 1, (int) ( $task->quest_level ?? 1 ) );
                                    $rt = $task->reward_type ?? 'both';
                                    $rxp = (int) ( $task->reward_xp ?? 200 );
                                    $rgold = (int) ( $task->reward_gold ?? 50 );
                                    $is_awarded = ! empty( $task->reward_awarded );

                                    // Scaling info for logged-in user
                                    $user_scaling = null;
                                    if ( $current_user_id && function_exists('sgd_calculate_quest_reward_ratio') && function_exists('sgd_get_user_xp_data') ) {
                                        $my_xp_data = sgd_get_user_xp_data( $current_user_id );
                                        $my_lvl = max( 1, (int) $my_xp_data['level'] );
                                        $user_scaling = sgd_calculate_quest_reward_ratio( $ql, $my_lvl );
                                    }
                                    ?>
                                    <div class="sgd-card-quest-meta-row">
                                        <span class="sgd-quest-lvl-badge <?php echo $user_scaling ? 'diff-' . esc_attr($user_scaling['difficulty']) : ''; ?>" title="Level Quest: <?php echo $ql; ?><?php echo $user_scaling ? ' (Porsi Hadiah untukmu: ' . $user_scaling['percent'] . '%)' : ''; ?>">
                                            ⚔️ Lv.<?php echo $ql; ?>
                                        </span>

                                        <?php if ( $rt === 'xp' || $rt === 'both' ): ?>
                                            <span class="sgd-card-reward-pill reward-xp" title="Hadiah XP: <?php echo number_format($rxp); ?> XP<?php echo $user_scaling && $user_scaling['percent'] < 100 ? ' (Porsi kamu: ' . round($rxp * $user_scaling['ratio']) . ' XP)' : ''; ?>">
                                                ⭐ +<?php echo number_format( $rxp ); ?> XP
                                            </span>
                                        <?php endif; ?>

                                        <?php if ( $rt === 'gold' || $rt === 'both' ): ?>
                                            <span class="sgd-card-reward-pill reward-gold" title="Hadiah Gold/Diamonds: <?php echo number_format($rgold); ?> 💎<?php echo $user_scaling && $user_scaling['percent'] < 100 ? ' (Porsi kamu: ' . round($rgold * $user_scaling['ratio']) . ' 💎)' : ''; ?>">
                                                💎 +<?php echo number_format( $rgold ); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ( $user_scaling && $user_scaling['percent'] < 100 && ! $is_awarded ): ?>
                                            <span class="sgd-card-scaling-pill" title="Level Anda (Lv.<?php echo $my_lvl; ?>) lebih tinggi dari Quest (Lv.<?php echo $ql; ?>): Anda mendapat <?php echo $user_scaling['percent']; ?>% porsi hadiah.">
                                                ⚡ <?php echo $user_scaling['percent']; ?>% Porsi
                                            </span>
                                        <?php endif; ?>

                                        <?php if ( $is_awarded ): ?>
                                            <span class="sgd-card-reward-pill reward-claimed" title="Hadiah tugas ini sudah dibagikan kepada anggota tim!">
                                                🏆 Diklaim
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ( ! empty( $task->description ) ): ?>
                                        <div class="sgd-card-desc-snippet">
                                            <?php echo wp_trim_words( wp_strip_all_tags( $task->description ), 14 ); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- PROMINENT DEADLINE IN THE CARD -->
                                    <?php if ( $has_deadline ): ?>
                                        <div class="sgd-card-deadline-bar <?php echo esc_attr( $dl_status ); ?>" title="Tenggat Waktu: <?php echo esc_attr( date( 'd M Y, H:i', $d_ts ) ); ?>">
                                            <span class="sgd-deadline-icon"><?php echo $dl_icon; ?></span>
                                            <span class="sgd-deadline-text"><?php echo esc_html( $dl_display ); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="sgd-card-footer">
                                        <div class="sgd-card-footer-left">
                                            <?php if ( $deadline_badge ) echo $deadline_badge; ?>
                                            <?php if ( $task->comment_count > 0 ): ?>
                                                <span class="sgd-card-indicator" title="<?php echo $task->comment_count; ?> Komentar">
                                                    💬 <?php echo $task->comment_count; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $task->attachment_count > 0 ): ?>
                                                <span class="sgd-card-indicator" title="<?php echo $task->attachment_count; ?> Lampiran File">
                                                    📎 <?php echo $task->attachment_count; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( empty( $deadline_badge ) && $task->comment_count == 0 && $task->attachment_count == 0 ): ?>
                                                <span class="sgd-card-team-label">👥 Tim</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="sgd-card-footer-right">
                                            <!-- Assigned Member Avatars -->
                                            <div class="sgd-card-member-chips" title="<?php echo ! empty( $task->members ) ? esc_attr( 'Anggota: ' . implode( ', ', array_column( $task->members, 'display_name' ) ) ) : 'Belum ada anggota ditugaskan'; ?>">
                                                <?php if ( ! empty( $task->members ) ): ?>
                                                    <?php foreach ( $task->members as $idx => $tm ): 
                                                        $tm_job = function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $tm['id'] ) : 'Staff';
                                                    ?>
                                                        <span class="sgd-card-avatar" title="<?php echo esc_attr( $tm['display_name'] . ' (' . $tm_job . ')' ); ?>" style="z-index:<?php echo 10 - $idx; ?>;">
                                                            <span class="sgd-avatar-icon"><?php echo $tm['avatar']; ?></span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php elseif ( $can_manage_team ): ?>
                                                    <span class="sgd-card-avatar sgd-card-avatar-unassigned" title="Belum ada anggota yang ditugaskan. Klik kartu untuk menugaskan anggota.">
                                                        👤+
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quick Move Column Selector -->
                                    <div class="sgd-card-move-bar" onclick="event.stopPropagation();">
                                        <span style="font-size:9px; color:#8fa0bd;">Pindah:</span>
                                        <?php foreach ( [ 'To do list', 'Dikerjakan', 'Selesai', 'Batal' ] as $target_c ): 
                                            if ( $target_c !== $task->column_name ):
                                        ?>
                                            <button type="button" class="sgd-quick-move-pill" data-task-id="<?php echo esc_attr( $task->id ); ?>" data-target-column="<?php echo esc_attr( $target_c ); ?>" title="Pindahkan ke <?php echo esc_attr( $target_c ); ?>">
                                                ➔ <?php echo esc_html( $target_c ); ?>
                                            </button>
                                        <?php 
                                            endif;
                                        endforeach; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div><!-- .sgd-quests-wrapper -->


<!-- ======================================================== -->
<!-- MODAL 1: BUAT TASK GROUP BARU (HQ, TIM, PROYEK) -->
<!-- ======================================================== -->
<div class="sgd-modal-backdrop" id="sgd-modal-create-team">
    <div class="sgd-modal-box" style="max-width:620px;">
        <div class="sgd-modal-header">
            <h3 class="sgd-modal-title">👥 Buat Task Group Baru (Tim)</h3>
            <button type="button" class="sgd-modal-close" data-close-modal="sgd-modal-create-team">&times;</button>
        </div>
        <form method="post" action="">
            <input type="hidden" name="sgd_action" value="sgd_create_quest_team">
            <div class="sgd-modal-body">
                
                <!-- 3 Option Types: HQ, Tim, Proyek -->
                <div class="sgd-form-group">
                    <label class="sgd-form-label">Pilih Tipe Task Group (3 Pilihan):</label>
                    <div class="sgd-type-radio-selector">
                        <label class="sgd-type-radio-card">
                            <input type="radio" name="team_type" value="hq">
                            <div class="sgd-radio-card-content">
                                <span class="sgd-radio-card-icon">🏢</span>
                                <strong class="sgd-radio-card-title">HQ</strong>
                                <span class="sgd-radio-card-desc">Markas Pusat & Koordinasi Operasional Utama</span>
                            </div>
                        </label>
                        <label class="sgd-type-radio-card active">
                            <input type="radio" name="team_type" value="tim" checked>
                            <div class="sgd-radio-card-content">
                                <span class="sgd-radio-card-icon">👥</span>
                                <strong class="sgd-radio-card-title">Tim</strong>
                                <span class="sgd-radio-card-desc">Departemen & Regu Kerja Harian</span>
                            </div>
                        </label>
                        <label class="sgd-type-radio-card">
                            <input type="radio" name="team_type" value="proyek">
                            <div class="sgd-radio-card-content">
                                <span class="sgd-radio-card-icon">🚀</span>
                                <strong class="sgd-radio-card-title">Proyek</strong>
                                <span class="sgd-radio-card-desc">Proyek Khusus & Inisiatif Lapangan</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- TimName field -->
                <div class="sgd-form-group">
                    <label class="sgd-form-label" for="sgd-input-timname">Nama Tim (TimName) <span style="color:#ff5252;">*</span>:</label>
                    <input type="text" name="team_name" id="sgd-input-timname" class="sgd-form-input" placeholder="Contoh: Tim Frontend Dev, HQ Operasional, Proyek Gedung A" required maxlength="255">
                </div>

                <!-- Description field -->
                <div class="sgd-form-group">
                    <label class="sgd-form-label" for="sgd-input-timdesc">Deskripsi Task Group (Opsional):</label>
                    <textarea name="team_description" id="sgd-input-timdesc" class="sgd-form-textarea" rows="2" placeholder="Tuliskan tujuan atau fokus task group ini..."></textarea>
                </div>

                <!-- Manager Assignment -->
                <div class="sgd-form-group">
                    <label class="sgd-form-label" for="sgd-select-manager">Tugaskan Manager Tim (Manager):</label>
                    <select name="manager_id" id="sgd-select-manager" class="sgd-form-select">
                        <?php foreach ( $users as $u ): 
                            $u_class = function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff';
                            $u_avatar = function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤';
                        ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $u->ID, $current_user_id ); ?>>
                                <?php echo $u_avatar . ' ' . esc_html( $u->display_name ) . ' (' . esc_html( $u_class ) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Members Assignment -->
                <div class="sgd-form-group">
                    <label class="sgd-form-label">Tugaskan Anggota Tim (Members):</label>
                    <div class="sgd-members-checklist-grid">
                        <?php foreach ( $users as $u ): 
                            $u_class = function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff';
                            $u_avatar = function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤';
                        ?>
                            <label class="sgd-member-check-item">
                                <input type="checkbox" name="member_ids[]" value="<?php echo esc_attr( $u->ID ); ?>" checked>
                                <span class="sgd-check-avatar"><?php echo $u_avatar; ?></span>
                                <span class="sgd-check-info">
                                    <strong><?php echo esc_html( $u->display_name ); ?></strong>
                                    <small><?php echo esc_html( $u_class ); ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            <div class="sgd-modal-footer">
                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" data-close-modal="sgd-modal-create-team">Batal</button>
                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">👥 Buat Tim</button>
            </div>
        </form>
    </div>
</div>


<!-- ======================================================== -->
<!-- MODAL 2: EDIT TASK GROUP (PENGATURAN TIM) -->
<!-- ======================================================== -->
<div class="sgd-modal-backdrop" id="sgd-modal-edit-team">
    <div class="sgd-modal-box" style="max-width:620px;">
        <div class="sgd-modal-header">
            <h3 class="sgd-modal-title">⚙️ Edit Pengaturan Task Group</h3>
            <button type="button" class="sgd-modal-close" data-close-modal="sgd-modal-edit-team">&times;</button>
        </div>
        <form method="post" action="" id="sgd-form-edit-team">
            <input type="hidden" name="sgd_action" value="sgd_update_quest_team">
            <input type="hidden" name="team_id" id="edit-team-id" value="">
            <div class="sgd-modal-body">
                
                <div class="sgd-form-group">
                    <label class="sgd-form-label">Tipe Task Group:</label>
                    <div class="sgd-type-radio-selector">
                        <label class="sgd-type-radio-card" id="edit-type-hq-card">
                            <input type="radio" name="team_type" value="hq" id="edit-type-hq">
                            <div class="sgd-radio-card-content">
                                <span class="sgd-radio-card-icon">🏢</span>
                                <strong class="sgd-radio-card-title">HQ</strong>
                            </div>
                        </label>
                        <label class="sgd-type-radio-card" id="edit-type-tim-card">
                            <input type="radio" name="team_type" value="tim" id="edit-type-tim">
                            <div class="sgd-radio-card-content">
                                <span class="sgd-radio-card-icon">👥</span>
                                <strong class="sgd-radio-card-title">Tim</strong>
                            </div>
                        </label>
                        <label class="sgd-type-radio-card" id="edit-type-proyek-card">
                            <input type="radio" name="team_type" value="proyek" id="edit-type-proyek">
                            <div class="sgd-radio-card-content">
                                <span class="sgd-radio-card-icon">🚀</span>
                                <strong class="sgd-radio-card-title">Proyek</strong>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label" for="edit-team-name">Nama Tim (TimName):</label>
                    <input type="text" name="team_name" id="edit-team-name" class="sgd-form-input" required maxlength="255">
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label" for="edit-team-desc">Deskripsi Task Group:</label>
                    <textarea name="team_description" id="edit-team-desc" class="sgd-form-textarea" rows="2"></textarea>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label" for="edit-team-manager">Manager Tim:</label>
                    <select name="manager_id" id="edit-team-manager" class="sgd-form-select">
                        <?php foreach ( $users as $u ): 
                            $u_class = function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff';
                            $u_avatar = function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤';
                        ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>">
                                <?php echo $u_avatar . ' ' . esc_html( $u->display_name ) . ' (' . esc_html( $u_class ) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sgd-form-group">
                    <label class="sgd-form-label">Anggota Tim (Members):</label>
                    <div class="sgd-members-checklist-grid" id="edit-members-grid">
                        <?php foreach ( $users as $u ): 
                            $u_class = function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff';
                            $u_avatar = function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤';
                        ?>
                            <label class="sgd-member-check-item">
                                <input type="checkbox" name="member_ids[]" value="<?php echo esc_attr( $u->ID ); ?>" class="edit-member-check" data-user-id="<?php echo esc_attr( $u->ID ); ?>">
                                <span class="sgd-check-avatar"><?php echo $u_avatar; ?></span>
                                <span class="sgd-check-info">
                                    <strong><?php echo esc_html( $u->display_name ); ?></strong>
                                    <small><?php echo esc_html( $u_class ); ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            <div class="sgd-modal-footer">
                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" data-close-modal="sgd-modal-edit-team">Batal</button>
                <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>


<!-- ======================================================== -->
<!-- MODAL 3: DETAIL TUGAS, WYSIWYG, ANGGOTA, CLONE & CHAT -->
<!-- ======================================================== -->
<div class="sgd-modal-backdrop" id="sgd-modal-task-detail">
    <div class="sgd-modal-box sgd-modal-task-box" style="max-width:980px; width:95%;">
        
        <div class="sgd-modal-header sgd-task-modal-header">
            <div class="sgd-task-modal-header-left">
                <span class="sgd-task-col-badge" id="modal-task-column-badge">📝 To do list</span>
                <span class="sgd-task-id-badge" id="modal-task-id-display">#0</span>
            </div>
            
            <div class="sgd-task-modal-header-actions">
                <!-- Add Label button (Admin Need) -->
                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="modal-btn-add-label-trigger" title="Tambah Label / Tag">
                    🏷️ Tambah Label
                </button>
                <!-- Clone Task button -->
                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-purple" id="modal-btn-clone-task" title="Duplikat tugas ini (Clone task)">
                    📋 Duplikat Tugas
                </button>
                <!-- Delete Task button -->
                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-red" id="modal-btn-delete-task" title="Hapus tugas ini">
                    🗑️ Hapus
                </button>
                <button type="button" class="sgd-modal-close" data-close-modal="sgd-modal-task-detail">&times;</button>
            </div>
        </div>

        <form method="post" id="sgd-form-update-task" action="">
            <input type="hidden" name="sgd_action" value="sgd_update_quest_task">
            <input type="hidden" name="task_id" id="modal-task-id" value="">
            <input type="hidden" name="team_id" value="<?php echo esc_attr( $team_id ); ?>">

            <div class="sgd-modal-body sgd-task-modal-body">
                <div class="sgd-task-modal-grid">
                    
                    <!-- LEFT COLUMN: Title, WYSIWYG Editor, Attachments, Comments -->
                    <div class="sgd-task-modal-main">
                        
                        <!-- Task Title Input -->
                        <div class="sgd-form-group">
                            <label class="sgd-form-label" for="modal-task-title">Judul Tugas (Task Title):</label>
                            <input type="text" name="task_title" id="modal-task-title" class="sgd-form-input sgd-title-input-large" required maxlength="255">
                        </div>

                        <!-- BIG WYSIWYG EDITOR FOR DESCRIPTION -->
                        <div class="sgd-form-group">
                            <label class="sgd-form-label">
                                📝 Deskripsi Lengkap Tugas (Big WYSIWYG Editor):
                            </label>
                            
                            <div class="sgd-wysiwyg-container">
                                <!-- WYSIWYG Formatting Toolbar -->
                                <div class="sgd-wysiwyg-toolbar" id="sgd-wysiwyg-toolbar">
                                    <div class="sgd-toolbar-group">
                                        <button type="button" class="sgd-tool-btn" data-cmd="bold" title="Tebal (Ctrl+B)"><strong>B</strong></button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="italic" title="Miring (Ctrl+I)"><em>I</em></button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="underline" title="Garis Bawah (Ctrl+U)"><u>U</u></button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="strikeThrough" title="Coret"><s>S</s></button>
                                    </div>
                                    <div class="sgd-toolbar-group">
                                        <button type="button" class="sgd-tool-btn" data-cmd="formatBlock" data-val="h2" title="Judul Besar">H2</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="formatBlock" data-val="h3" title="Sub Judul">H3</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="formatBlock" data-val="p" title="Paragraf Biasa">¶</button>
                                    </div>
                                    <div class="sgd-toolbar-group">
                                        <button type="button" class="sgd-tool-btn" data-cmd="insertUnorderedList" title="Daftar Bullet">• List</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="insertOrderedList" title="Daftar Bernomor">1. List</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="formatBlock" data-val="blockquote" title="Kutipan">❝ Quote</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="formatBlock" data-val="pre" title="Blok Kode">&lt;/&gt;</button>
                                    </div>
                                    <div class="sgd-toolbar-group">
                                        <button type="button" class="sgd-tool-btn" id="sgd-tool-insert-link" title="Sisipkan Tautan">🔗 Link</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="hiliteColor" data-val="#ffeb3b" style="background:#ffeb3b; color:#000;" title="Sorot Kuning">🖍️</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="hiliteColor" data-val="#00e5ff" style="background:#00e5ff; color:#000;" title="Sorot Cyan">🖍️</button>
                                        <button type="button" class="sgd-tool-btn" data-cmd="removeFormat" title="Hapus Pemformatan">🧹 Clear</button>
                                    </div>
                                </div>

                                <!-- Editable Rich-Text Area -->
                                <div class="sgd-wysiwyg-editor" id="modal-task-editor" contenteditable="true" placeholder="Tuliskan spesifikasi tugas, petunjuk pengerjaan, atau catatan detail di sini..."></div>
                                
                                <!-- Hidden textarea that syncs on submit -->
                                <textarea name="description" id="modal-task-desc-hidden" style="display:none;"></textarea>
                            </div>
                        </div>

                        <!-- FILE ATTACHMENTS & UPLOAD SECTION -->
                        <div class="sgd-task-section sgd-task-attachments-section">
                            <div class="sgd-section-title-line">
                                <h4 class="sgd-section-title">📎 Lampiran Dokumen & File</h4>
                                <span class="sgd-attachment-count" id="modal-attachment-count">0 file</span>
                            </div>

                            <!-- List of Uploaded Attachments -->
                            <div class="sgd-attachments-list" id="modal-attachments-list">
                                <p class="sgd-attachments-empty">Belum ada file terlampir.</p>
                            </div>

                            <!-- File Upload Action Form -->
                            <div class="sgd-upload-box">
                                <div class="sgd-upload-input-row">
                                    <input type="file" id="modal-file-input" class="sgd-form-file-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt">
                                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="modal-btn-upload-file">
                                        ⬆️ Unggah File
                                    </button>
                                </div>
                                <small style="color:#8fa0bd; font-size:11px;">Mendukung format gambar (JPG/PNG), PDF, Word, Excel, ZIP & Dokumen teknis.</small>
                            </div>
                        </div>

                        <!-- CHAT & COMMENTS DISCUSSION SECTION -->
                        <div class="sgd-task-section sgd-task-comments-section">
                            <div class="sgd-section-title-line">
                                <h4 class="sgd-section-title">💬 Diskusi & Obrolan Anggota (Team Chat)</h4>
                                <span class="sgd-comment-count" id="modal-comment-count">0 pesan</span>
                            </div>

                            <!-- Comments Stream -->
                            <div class="sgd-comments-stream" id="modal-comments-stream">
                                <p class="sgd-comments-empty">Belum ada obrolan. Jadilah yang pertama memberikan masukan!</p>
                            </div>

                            <!-- Add Comment Box -->
                            <div class="sgd-add-comment-box">
                                <textarea id="modal-new-comment-input" class="sgd-form-textarea" rows="2" placeholder="Tulis pesan atau komentar untuk anggota tugas ini..."></textarea>
                                <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-gold" id="modal-btn-send-comment">
                                        💬 Kirim Komentar
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div><!-- .sgd-task-modal-main -->


                    <!-- RIGHT SIDEBAR: Status, Deadline, Assigned Members, Save Button -->
                    <div class="sgd-task-modal-sidebar">
                        
                        <!-- Primary Save Button -->
                        <div class="sgd-sidebar-widget" style="background:rgba(255, 215, 0, 0.08); border-color:#ffd700;">
                            <button type="submit" class="sgd-btn sgd-btn-gold sgd-btn-block" id="modal-btn-save-task">
                                💾 Simpan Perubahan Tugas
                            </button>
                        </div>

                        <!-- Column / Status Selector -->
                        <div class="sgd-sidebar-widget">
                            <label class="sgd-form-label" for="modal-task-column">Status / Kolom Tugas:</label>
                            <select name="column_name" id="modal-task-column" class="sgd-form-select">
                                <option value="To do list">📝 To do list</option>
                                <option value="Dikerjakan">⏳ Dikerjakan</option>
                                <option value="Selesai">✅ Selesai</option>
                                <option value="Batal">🚫 Batal</option>
                            </select>
                        </div>

                        <!-- QUEST LEVEL & SCALING WIDGET -->
                        <div class="sgd-sidebar-widget" id="modal-quest-level-widget">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <label class="sgd-form-label" for="modal-task-quest-level" style="margin:0; font-size:12px; color:#ffd700;">
                                    ⚔️ Level Quest (Tingkat):
                                </label>
                                <span class="sgd-modal-lvl-badge" id="modal-quest-lvl-badge" style="font-family:var(--font-pixel); font-size:9px; background:#ffd700; color:#0b1933; padding:2px 6px; border-radius:3px; font-weight:bold;">Lv. 1</span>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                                <input type="number" name="quest_level" id="modal-task-quest-level" min="1" max="50" value="1" class="sgd-form-input" style="font-size:13px; font-weight:bold; width:75px; text-align:center;">
                                <div class="sgd-lvl-presets-row" style="display:flex; gap:3px; flex-wrap:wrap; flex:1;">
                                    <button type="button" class="sgd-preset-pill" onclick="sgdSetQuestLevel(1);">Lv.1</button>
                                    <button type="button" class="sgd-preset-pill" onclick="sgdSetQuestLevel(5);">Lv.5</button>
                                    <button type="button" class="sgd-preset-pill" onclick="sgdSetQuestLevel(10);">Lv.10</button>
                                    <button type="button" class="sgd-preset-pill" onclick="sgdSetQuestLevel(20);">Lv.20</button>
                                    <button type="button" class="sgd-preset-pill" onclick="sgdSetQuestLevel(30);">Lv.30</button>
                                </div>
                            </div>
                            <div class="sgd-scaling-preview-box" id="modal-scaling-preview-box" style="background:rgba(0,0,0,0.4); border:1px solid rgba(255,215,0,0.3); border-radius:6px; padding:8px 10px; font-size:11px; line-height:1.4;">
                                <div style="font-weight:bold; color:#ffd700; margin-bottom:3px; display:flex; align-items:center; gap:5px;">
                                    <span>⚖️ Mekanisme Skala Level:</span>
                                    <span id="modal-preview-portion-tag" style="background:#2ecc71; color:#000; font-size:9px; padding:1px 5px; border-radius:3px; font-family:var(--font-pixel);">100% Porsi</span>
                                </div>
                                <div id="modal-scaling-user-text" style="color:#c8d1e0; font-size:10px;">
                                    Karyawan level tinggi yang menyelesaikan quest level rendah hanya mendapat sebagian porsi hadiah (15% - 70%) untuk menjaga keadilan serikat.
                                </div>
                            </div>
                        </div>

                        <!-- REWARD CHOICE WIDGET (XP, GOLD, OR BOTH) -->
                        <div class="sgd-sidebar-widget" id="modal-quest-rewards-widget">
                            <label class="sgd-form-label" for="modal-task-reward-type" style="font-size:12px; color:#00e5ff;">🎁 Pilihan Hadiah Tugas:</label>
                            <select name="reward_type" id="modal-task-reward-type" class="sgd-form-select" style="margin-bottom:10px;">
                                <option value="both">👑 Keduanya (XP & Gold/Diamonds)</option>
                                <option value="xp">⭐ Hanya XP</option>
                                <option value="gold">💎 Hanya Gold / Diamonds</option>
                                <option value="none">⚪ Tanpa Hadiah</option>
                            </select>

                            <div class="sgd-reward-fields-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                <div id="modal-reward-xp-group">
                                    <label class="sgd-form-label" for="modal-task-reward-xp" style="font-size:10px; color:#00e5ff;">⭐ Jumlah XP:</label>
                                    <input type="number" name="reward_xp" id="modal-task-reward-xp" min="0" step="10" value="200" class="sgd-form-input" style="font-size:12px;">
                                </div>
                                <div id="modal-reward-gold-group">
                                    <label class="sgd-form-label" for="modal-task-reward-gold" style="font-size:10px; color:#ffd700;">💎 Jumlah Gold:</label>
                                    <input type="number" name="reward_gold" id="modal-task-reward-gold" min="0" step="5" value="50" class="sgd-form-input" style="font-size:12px;">
                                </div>
                            </div>
                        </div>

                        <!-- LABELS & TAGS WIDGET (ADMIN NEED) -->
                        <div class="sgd-sidebar-widget" id="modal-labels-widget">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <label class="sgd-form-label" style="margin:0;">🏷️ Label / Tag Tugas:</label>
                                <button type="button" class="sgd-btn-add-label-toggle" id="modal-btn-toggle-add-label" title="Tambah Label">+ Tambah</button>
                            </div>

                            <!-- Active Attached Labels Container -->
                            <div class="sgd-modal-labels-container" id="modal-labels-container">
                                <span class="sgd-no-labels-text" style="font-size:11px; color:#718093;">Belum ada label.</span>
                            </div>
                            <input type="hidden" name="labels" id="modal-task-labels-hidden" value="[]">

                            <!-- Add Label Interactive Popover -->
                            <div class="sgd-add-label-popover" id="modal-add-label-panel" style="display:none; background:#070b14; border:1px solid #ffd700; border-radius:6px; padding:10px; margin-top:10px;">
                                <div style="font-size:11px; color:#ffd700; margin-bottom:6px; font-weight:bold;">Nama & Warna Label:</div>
                                
                                <input type="text" id="modal-new-tag-name" class="sgd-form-input" placeholder="Ketik nama label..." style="margin-bottom:8px; font-size:12px; padding:6px 8px;">

                                <div style="font-size:10px; color:#8fa0bd; margin-bottom:4px;">Pilih Warna Label (8 Pilihan):</div>
                                <div class="sgd-color-options-swatches" id="modal-tag-color-swatches">
                                    <button type="button" class="sgd-color-swatch active" data-color="#ff4444" style="background:#ff4444;" title="Merah"></button>
                                    <button type="button" class="sgd-color-swatch" data-color="#ff9f43" style="background:#ff9f43;" title="Oranye"></button>
                                    <button type="button" class="sgd-color-swatch" data-color="#ffd700" style="background:#ffd700;" title="Kuning"></button>
                                    <button type="button" class="sgd-color-swatch" data-color="#2ecc71" style="background:#2ecc71;" title="Hijau"></button>
                                    <button type="button" class="sgd-color-swatch" data-color="#00e5ff" style="background:#00e5ff;" title="Cyan"></button>
                                    <button type="button" class="sgd-color-swatch" data-color="#3b82f6" style="background:#3b82f6;" title="Biru"></button>
                                    <button type="button" class="sgd-color-swatch" data-color="#a55eea" style="background:#a55eea;" title="Ungu"></button>
                                    <button type="button" class="sgd-color-swatch" data-color="#fd79a8" style="background:#fd79a8;" title="Pink"></button>
                                </div>

                                <div style="font-size:10px; color:#8fa0bd; margin:8px 0 4px 0;">Preset Cepat:</div>
                                <div class="sgd-tag-presets-row">
                                    <button type="button" class="sgd-tag-preset-btn" data-name="Urgent" data-color="#ff4444">🔴 Urgent</button>
                                    <button type="button" class="sgd-tag-preset-btn" data-name="Bug" data-color="#ff5252">🐛 Bug</button>
                                    <button type="button" class="sgd-tag-preset-btn" data-name="Fitur Baru" data-color="#2ecc71">✨ Fitur</button>
                                    <button type="button" class="sgd-tag-preset-btn" data-name="UI/UX" data-color="#a55eea">🎨 UI/UX</button>
                                    <button type="button" class="sgd-tag-preset-btn" data-name="Review" data-color="#ffd700">📋 Review</button>
                                </div>

                                <div style="display:flex; justify-content:flex-end; gap:6px; margin-top:10px;">
                                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="modal-btn-cancel-add-label" style="font-size:10px; padding:4px 8px;">Batal</button>
                                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-green" id="modal-btn-confirm-add-label" style="font-size:10px; padding:4px 10px;">+ Tambah ke Tugas</button>
                                </div>
                            </div>
                        </div>

                        <!-- Deadline Picker -->
                        <div class="sgd-sidebar-widget">
                            <label class="sgd-form-label" for="modal-task-deadline">Tenggat Waktu (Deadline):</label>
                            <input type="datetime-local" name="deadline" id="modal-task-deadline" class="sgd-form-input">
                            <div class="sgd-deadline-presets">
                                <button type="button" class="sgd-preset-pill" onclick="sgdSetDeadlinePreset(1);">Besok</button>
                                <button type="button" class="sgd-preset-pill" onclick="sgdSetDeadlinePreset(3);">+3 Hari</button>
                                <button type="button" class="sgd-preset-pill" onclick="sgdSetDeadlinePreset(7);">+1 Minggu</button>
                                <button type="button" class="sgd-preset-pill" onclick="document.getElementById('modal-task-deadline').value='';">Hapus</button>
                            </div>
                        </div>

                        <!-- Assigned Members Checklist -->
                        <div class="sgd-sidebar-widget">
                            <label class="sgd-form-label">Tugaskan Anggota Tugas Ini:</label>
                            <div class="sgd-task-members-selector" id="modal-task-members-list">
                                <?php 
                                $team_members_pool = $current_team ? $current_team->members : [];
                                if ( empty( $team_members_pool ) ) {
                                    foreach ( $users as $u ) {
                                        $team_members_pool[] = [
                                            'id'           => $u->ID,
                                            'display_name' => $u->display_name,
                                            'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤',
                                            'job_class'    => function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff',
                                        ];
                                    }
                                }
                                foreach ( $team_members_pool as $tmb ):
                                ?>
                                    <label class="sgd-task-member-option">
                                        <input type="checkbox" name="assigned_members[]" value="<?php echo esc_attr( $tmb['id'] ); ?>" class="modal-assigned-member-check" data-user-id="<?php echo esc_attr( $tmb['id'] ); ?>">
                                        <span class="sgd-tmb-avatar"><?php echo $tmb['avatar']; ?></span>
                                        <span class="sgd-tmb-name"><?php echo esc_html( $tmb['display_name'] ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div><!-- .sgd-task-modal-sidebar -->

                </div><!-- .sgd-task-modal-grid -->
            </div><!-- .sgd-modal-body -->
        </form>

    </div>
</div>


<!-- Hidden helper forms for clone and delete -->
<form id="sgd-hidden-clone-form" method="post" style="display:none;">
    <input type="hidden" name="sgd_action" value="sgd_clone_quest_task">
    <input type="hidden" name="task_id" id="sgd-hidden-clone-task-id" value="">
</form>

<form id="sgd-hidden-del-form" method="post" style="display:none;">
    <input type="hidden" name="sgd_action" value="sgd_delete_quest_task">
    <input type="hidden" name="task_id" id="sgd-hidden-del-task-id" value="">
</form>

<form id="sgd-hidden-move-form" method="post" style="display:none;">
    <input type="hidden" name="sgd_action" value="sgd_move_quest_task">
    <input type="hidden" name="task_id" id="sgd-hidden-move-task-id" value="">
    <input type="hidden" name="target_column" id="sgd-hidden-move-target-column" value="">
</form>

<script>
window.SGD_QUESTS_CONFIG = {
    ajaxUrl: "<?php echo esc_url( home_url( '/' ) ); ?>",
    currentUserId: <?php echo (int) $current_user_id; ?>,
    currentUserLevel: <?php echo (int) ( ( $current_user_id && function_exists('sgd_get_user_xp_data') ) ? sgd_get_user_xp_data($current_user_id)['level'] : 1 ); ?>,
    currentTeamId: <?php echo (int) $team_id; ?>,
    openTaskId: <?php echo (int) $open_task_id; ?>,
    canManageTeam: <?php echo $can_manage_team ? 'true' : 'false'; ?>
};
</script>
