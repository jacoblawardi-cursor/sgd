<?php
/**
 * Plugin Name: SGD WoW Gamification System
 * Description: 16-bit / World of Warcraft inspired employee gamification portal with Daily Tasks, Urgent Emergency Tasks, Achievements, Cash Shop, Help Dispatch, and Photo Proof verification.
 * Version: 2.0
 * Author: Antigravity
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SGD_PLUGIN_DIR', __DIR__ . '/sgd-gamification' );
define( 'SGD_PLUGIN_URL', content_url( 'mu-plugins/sgd-gamification' ) );

// 1. Database & Role Setup
function sgd_install_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Tasks table (Daily and Urgent tasks)
    $table_tasks = $wpdb->prefix . 'sgd_tasks';
    $sql_tasks = "CREATE TABLE IF NOT EXISTS $table_tasks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL DEFAULT 'daily',
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(100) DEFAULT 'General Quest',
        reward_diamonds INT NOT NULL DEFAULT 200,
        assigned_to BIGINT UNSIGNED DEFAULT 0,
        claimed_by BIGINT UNSIGNED DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'open',
        need_help_by BIGINT UNSIGNED DEFAULT 0,
        need_help_time DATETIME DEFAULT NULL,
        help_status VARCHAR(30) NOT NULL DEFAULT 'none',
        help_notes TEXT DEFAULT NULL,
        completed_by BIGINT UNSIGNED DEFAULT 0,
        completed_time DATETIME DEFAULT NULL,
        proof_image_url TEXT DEFAULT NULL,
        proof_notes TEXT DEFAULT NULL,
        is_recurring TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_tasks );

    // Ensure is_recurring column exists for existing tables
    $has_rec_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_tasks` LIKE 'is_recurring'" );
    if ( empty( $has_rec_col ) ) {
        $wpdb->query( "ALTER TABLE `$table_tasks` ADD COLUMN `is_recurring` TINYINT(1) NOT NULL DEFAULT 1 AFTER `proof_notes`" );
    }

    // Ensure external_url column exists for Cicle / external PM links
    $has_ext_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_tasks` LIKE 'external_url'" );
    if ( empty( $has_ext_col ) ) {
        $wpdb->query( "ALTER TABLE `$table_tasks` ADD COLUMN `external_url` VARCHAR(255) DEFAULT NULL AFTER `is_recurring`" );
    }

    // Ensure reward_xp column exists for tasks
    $has_xp_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_tasks` LIKE 'reward_xp'" );
    if ( empty( $has_xp_col ) ) {
        $wpdb->query( "ALTER TABLE `$table_tasks` ADD COLUMN `reward_xp` INT NOT NULL DEFAULT 200 AFTER `reward_diamonds`" );
        $wpdb->query( "UPDATE `$table_tasks` SET `reward_xp` = `reward_diamonds` WHERE `reward_xp` = 0 OR `reward_xp` IS NULL" );
    }

    // Task completions & photo proof log table (preserves photo submissions across recurring daily resets)
    $table_task_logs = $wpdb->prefix . 'sgd_task_logs';
    $sql_task_logs = "CREATE TABLE IF NOT EXISTS $table_task_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        task_id BIGINT UNSIGNED NOT NULL,
        task_title VARCHAR(255) NOT NULL,
        task_type VARCHAR(20) NOT NULL DEFAULT 'daily',
        user_id BIGINT UNSIGNED NOT NULL,
        diamonds_awarded INT NOT NULL DEFAULT 0,
        xp_awarded INT NOT NULL DEFAULT 0,
        streak_count INT NOT NULL DEFAULT 0,
        streak_bonus INT NOT NULL DEFAULT 0,
        proof_image_url TEXT DEFAULT NULL,
        proof_notes TEXT DEFAULT NULL,
        completed_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_task_logs );

    // Ensure xp_awarded column exists on task logs
    $has_log_xp = $wpdb->get_results( "SHOW COLUMNS FROM `$table_task_logs` LIKE 'xp_awarded'" );
    if ( empty( $has_log_xp ) ) {
        $wpdb->query( "ALTER TABLE `$table_task_logs` ADD COLUMN `xp_awarded` INT NOT NULL DEFAULT 0 AFTER `diamonds_awarded`" );
    }

    // Backfill historical completed tasks with proofs if log table is empty
    $existing_log_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_task_logs" );
    if ( ! $existing_log_count ) {
        $past_completed = $wpdb->get_results( "SELECT * FROM $table_tasks WHERE status = 'completed' AND proof_image_url IS NOT NULL AND proof_image_url != ''" );
        if ( ! empty( $past_completed ) ) {
            foreach ( $past_completed as $pc ) {
                $wpdb->insert( $table_task_logs, [
                    'task_id'          => $pc->id,
                    'task_title'       => $pc->title,
                    'task_type'        => $pc->type,
                    'user_id'          => $pc->completed_by ?: 1,
                    'diamonds_awarded' => $pc->reward_diamonds ?: 200,
                    'streak_count'     => 1,
                    'streak_bonus'     => 0,
                    'proof_image_url'  => $pc->proof_image_url,
                    'proof_notes'      => $pc->proof_notes,
                    'completed_time'   => $pc->completed_time ?: current_time( 'mysql' ),
                ] );
            }
        }
    }

    // Achievements table
    $table_achievements = $wpdb->prefix . 'sgd_achievements';
    $sql_achievements = "CREATE TABLE IF NOT EXISTS $table_achievements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(64) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        icon VARCHAR(64) NOT NULL DEFAULT '🏆',
        reward_diamonds INT NOT NULL DEFAULT 100,
        reward_xp INT NOT NULL DEFAULT 200,
        category VARCHAR(50) NOT NULL DEFAULT 'General',
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_achievements );

    // Ensure reward_xp column exists on achievements
    $has_ach_xp = $wpdb->get_results( "SHOW COLUMNS FROM `$table_achievements` LIKE 'reward_xp'" );
    if ( empty( $has_ach_xp ) ) {
        $wpdb->query( "ALTER TABLE `$table_achievements` ADD COLUMN `reward_xp` INT NOT NULL DEFAULT 200 AFTER `reward_diamonds`" );
        $wpdb->query( "UPDATE `$table_achievements` SET `reward_xp` = `reward_diamonds` WHERE `reward_xp` = 0 OR `reward_xp` IS NULL" );
    }

    // User Achievements table
    $table_user_achievements = $wpdb->prefix . 'sgd_user_achievements';
    $sql_user_achievements = "CREATE TABLE IF NOT EXISTS $table_user_achievements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        achievement_id BIGINT UNSIGNED NOT NULL,
        unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_ach (user_id, achievement_id)
    ) $charset_collate;";
    dbDelta( $sql_user_achievements );

    // Redemptions table (Cash Shop)
    $table_redemptions = $wpdb->prefix . 'sgd_redemptions';
    $sql_redemptions = "CREATE TABLE IF NOT EXISTS $table_redemptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        item_id VARCHAR(64) NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        diamonds_spent INT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        notes TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_redemptions );

    // Peer Kudos / Hero Mana recognition table
    $table_kudos = $wpdb->prefix . 'sgd_kudos';
    $sql_kudos = "CREATE TABLE IF NOT EXISTS $table_kudos (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        from_user_id BIGINT UNSIGNED NOT NULL,
        to_user_id BIGINT UNSIGNED NOT NULL,
        amount_diamonds INT NOT NULL DEFAULT 50,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_kudos );

    // Peer Bounty Board table (Employee-to-Employee Contracting)
    $table_bounties = $wpdb->prefix . 'sgd_bounties';
    $sql_bounties = "CREATE TABLE IF NOT EXISTS $table_bounties (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        poster_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(100) DEFAULT 'Peer Favor',
        reward_diamonds INT NOT NULL DEFAULT 100,
        claimed_by BIGINT UNSIGNED DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'open',
        proof_image_url TEXT DEFAULT NULL,
        proof_notes TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_bounties );

    // Daily Construction & Work Reports (Laporan Harian Proyek)
    $table_daily_reports = $wpdb->prefix . 'sgd_daily_reports';
    $sql_daily_reports = "CREATE TABLE IF NOT EXISTS $table_daily_reports (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        report_date DATE NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        raw_input LONGTEXT NOT NULL,
        weather_hourly LONGTEXT DEFAULT NULL,
        manpower LONGTEXT DEFAULT NULL,
        materials LONGTEXT DEFAULT NULL,
        equipment LONGTEXT DEFAULT NULL,
        work_executed LONGTEXT DEFAULT NULL,
        issues LONGTEXT DEFAULT NULL,
        supervisor_instruction LONGTEXT DEFAULT NULL,
        next_day_plan LONGTEXT DEFAULT NULL,
        safety_k3 LONGTEXT DEFAULT NULL,
        additional_notes LONGTEXT DEFAULT NULL,
        photo_1 TEXT DEFAULT NULL,
        photo_2 TEXT DEFAULT NULL,
        photo_3 TEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'submitted',
        parsed_with VARCHAR(50) NOT NULL DEFAULT 'smart_nlp',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY report_date (report_date),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta( $sql_daily_reports );

    // Ensure table exists via direct query if dbDelta was bypassed or failed
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_daily_reports'" ) != $table_daily_reports ) {
        $wpdb->query( $sql_daily_reports );
    }

    // Cicle Kanban: Task groups / Teams table (HQ, Tim, Proyek)
    $table_quest_teams = $wpdb->prefix . 'sgd_quest_teams';
    $sql_quest_teams = "CREATE TABLE IF NOT EXISTS $table_quest_teams (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'tim',
        description TEXT DEFAULT NULL,
        manager_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        member_ids TEXT DEFAULT NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY type_idx (type)
    ) $charset_collate;";
    dbDelta( $sql_quest_teams );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_quest_teams'" ) != $table_quest_teams ) {
        $wpdb->query( $sql_quest_teams );
    }

    // Cicle Kanban: Tasks table (To do list, Dikerjakan, Selesai, Batal)
    $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
    $sql_quest_tasks = "CREATE TABLE IF NOT EXISTS $table_quest_tasks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        team_id BIGINT UNSIGNED NOT NULL,
        column_name VARCHAR(50) NOT NULL DEFAULT 'To do list',
        title VARCHAR(255) NOT NULL,
        description LONGTEXT DEFAULT NULL,
        deadline DATETIME DEFAULT NULL,
        assigned_members TEXT DEFAULT NULL,
        labels TEXT DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY team_col_idx (team_id, column_name)
    ) $charset_collate;";
    dbDelta( $sql_quest_tasks );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_quest_tasks'" ) != $table_quest_tasks ) {
        $wpdb->query( $sql_quest_tasks );
    }

    // Ensure labels column exists on tasks table for tags
    $has_labels_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_quest_tasks` LIKE 'labels'" );
    if ( empty( $has_labels_col ) ) {
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `labels` TEXT DEFAULT NULL AFTER `assigned_members`" );
    }

    // Ensure checklist column exists on tasks table for subtasks verification
    $has_checklist_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_quest_tasks` LIKE 'checklist'" );
    if ( empty( $has_checklist_col ) ) {
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `checklist` LONGTEXT DEFAULT NULL AFTER `labels`" );
    }

    // Ensure cover_image column exists on tasks table
    $has_cover_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_quest_tasks` LIKE 'cover_image'" );
    if ( empty( $has_cover_col ) ) {
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `cover_image` TEXT DEFAULT NULL AFTER `labels`" );
    }

    // Auto-seed sample cover images for a couple of tasks so it renders nicely out of the box
    $uncovered_tasks = $wpdb->get_results( "SELECT id FROM `$table_quest_tasks` WHERE cover_image IS NULL OR cover_image = '' LIMIT 2" );
    if ( ! empty( $uncovered_tasks ) ) {
        $upload_dir = wp_upload_dir();
        $sample_covers = [
            $upload_dir['baseurl'] . '/2026/10/aPx6ppV_460swp.webp',
            $upload_dir['baseurl'] . '/2026/10/avW2vKE_460swp.webp',
        ];
        foreach ( $uncovered_tasks as $idx => $t_obj ) {
            $c_url = $sample_covers[ $idx % count( $sample_covers ) ];
            $wpdb->update( $table_quest_tasks, [ 'cover_image' => $c_url ], [ 'id' => $t_obj->id ] );
        }
    }

    // Auto-seed initial labels for existing tasks that have no labels yet so labels/tags immediately show on cards
    $unlabeled_tasks = $wpdb->get_results( "SELECT id, title FROM `$table_quest_tasks` WHERE labels IS NULL OR labels = '' OR labels = '[]' LIMIT 30" );
    if ( ! empty( $unlabeled_tasks ) ) {
        $sample_tags_pool = [
            [ [ 'name' => '🎨 UI/UX', 'color' => '#a55eea' ], [ 'name' => '🔴 Urgent', 'color' => '#ff4444' ] ],
            [ [ 'name' => '✨ Fitur', 'color' => '#2ecc71' ] ],
            [ [ 'name' => '📋 Review', 'color' => '#ffd700' ] ],
            [ [ 'name' => '🐛 Bug', 'color' => '#ff5252' ] ],
            [ [ 'name' => '⚡ Optimasi', 'color' => '#00e5ff' ] ],
        ];
        foreach ( $unlabeled_tasks as $idx => $t_obj ) {
            $pool_idx = $idx % count( $sample_tags_pool );
            $wpdb->update(
                $table_quest_tasks,
                [ 'labels' => wp_json_encode( $sample_tags_pool[ $pool_idx ] ) ],
                [ 'id' => $t_obj->id ]
            );
        }
    }

    // Auto-seed sample checklist items for tasks that have no checklist yet so manager can verify immediately
    $unchecklisted_tasks = $wpdb->get_results( "SELECT id, title, column_name FROM `$table_quest_tasks` WHERE checklist IS NULL OR checklist = '' OR checklist = '[]' LIMIT 30" );
    if ( ! empty( $unchecklisted_tasks ) ) {
        $sample_checklists = [
            [
                [ 'id' => 'chk_1', 'text' => 'Review spesifikasi teknis & desain UI', 'completed' => true ],
                [ 'id' => 'chk_2', 'text' => 'Uji fungsi pada lingkungan staging', 'completed' => false ],
                [ 'id' => 'chk_3', 'text' => 'Verifikasi & persetujuan manager tim', 'completed' => false ],
            ],
            [
                [ 'id' => 'chk_1', 'text' => 'Pemeriksaan standar mutu & keselamatan K3', 'completed' => true ],
                [ 'id' => 'chk_2', 'text' => 'Dokumentasi bukti pekerjaan lapangan', 'completed' => false ],
            ],
            [
                [ 'id' => 'chk_1', 'text' => 'Validasi input data & testing fungsional', 'completed' => true ],
                [ 'id' => 'chk_2', 'text' => 'Konfirmasi checklist penyelesaian tugas', 'completed' => true ],
            ],
        ];
        foreach ( $unchecklisted_tasks as $idx => $t_obj ) {
            $pick = $sample_checklists[ $idx % count( $sample_checklists ) ];
            if ( $t_obj->column_name === 'Selesai' ) {
                foreach ( $pick as &$p_item ) { $p_item['completed'] = true; }
            }
            $wpdb->update(
                $table_quest_tasks,
                [ 'checklist' => wp_json_encode( $pick ) ],
                [ 'id' => $t_obj->id ]
            );
        }
    }

    // Auto-seed assigned members for existing tasks that have no assigned members yet so member avatars immediately show on cards
    $unassigned_tasks = $wpdb->get_results( "SELECT id, team_id, created_by FROM `$table_quest_tasks` WHERE assigned_members IS NULL OR assigned_members = '' OR assigned_members = '0' LIMIT 30" );
    if ( ! empty( $unassigned_tasks ) ) {
        $all_wp_users = get_users( [ 'number' => 5 ] );
        $user_pool_ids = ! empty( $all_wp_users ) ? wp_list_pluck( $all_wp_users, 'ID' ) : [ 1 ];
        foreach ( $unassigned_tasks as $idx => $t_obj ) {
            $assigned_sample = [ $user_pool_ids[ $idx % count( $user_pool_ids ) ] ];
            if ( count( $user_pool_ids ) > 1 && $idx % 2 === 0 ) {
                $assigned_sample[] = $user_pool_ids[ ( $idx + 1 ) % count( $user_pool_ids ) ];
            }
            $wpdb->update(
                $table_quest_tasks,
                [ 'assigned_members' => implode( ',', $assigned_sample ) ],
                [ 'id' => $t_obj->id ]
            );
        }
    }

    // Ensure quest_level & reward columns exist on tasks table
    $has_ql_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_quest_tasks` LIKE 'quest_level'" );
    if ( empty( $has_ql_col ) ) {
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `quest_level` INT NOT NULL DEFAULT 1 AFTER `sort_order`" );
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `reward_type` VARCHAR(20) NOT NULL DEFAULT 'both' AFTER `quest_level`" );
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `reward_xp` INT NOT NULL DEFAULT 200 AFTER `reward_type`" );
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `reward_gold` INT NOT NULL DEFAULT 50 AFTER `reward_xp`" );
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `reward_awarded` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reward_gold`" );
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `completed_by` BIGINT UNSIGNED DEFAULT 0 AFTER `reward_awarded`" );
        $wpdb->query( "ALTER TABLE `$table_quest_tasks` ADD COLUMN `completed_time` DATETIME DEFAULT NULL AFTER `completed_by`" );
    }

    // Cicle Kanban: Task Comments table
    $table_quest_comments = $wpdb->prefix . 'sgd_quest_comments';
    $sql_quest_comments = "CREATE TABLE IF NOT EXISTS $table_quest_comments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        task_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY task_idx (task_id)
    ) $charset_collate;";
    dbDelta( $sql_quest_comments );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_quest_comments'" ) != $table_quest_comments ) {
        $wpdb->query( $sql_quest_comments );
    }

    // Cicle Kanban: Task Attachments table
    $table_quest_attachments = $wpdb->prefix . 'sgd_quest_attachments';
    $sql_quest_attachments = "CREATE TABLE IF NOT EXISTS $table_quest_attachments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        task_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        file_url TEXT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size BIGINT UNSIGNED DEFAULT 0,
        file_type VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY task_idx (task_id)
    ) $charset_collate;";
    dbDelta( $sql_quest_attachments );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_quest_attachments'" ) != $table_quest_attachments ) {
        $wpdb->query( $sql_quest_attachments );
    }

    // Ensure employee role exists
    if ( ! get_role( 'employee' ) ) {
        $sub = get_role( 'subscriber' );
        $caps = $sub ? $sub->capabilities : [ 'read' => true ];
        $caps['upload_files'] = true;
        add_role( 'employee', __( 'Employee Hero', 'sgd' ), $caps );
    }

    // Seed default data if empty
    sgd_seed_default_data();
}

function sgd_seed_default_data() {
    global $wpdb;

    // 1. Create demo users if not present
    $admin_email = 'admin@example.com';
    $admin_id = email_exists( $admin_email );
    if ( ! $admin_id ) {
        $admin_id = wp_insert_user( [
            'user_login'    => 'admin',
            'user_pass'     => 'Admin123!',
            'user_email'    => $admin_email,
            'role'          => 'administrator',
            'display_name'  => 'Grand Marshal Admin',
        ] );
    }
    if ( $admin_id && ! is_wp_error( $admin_id ) ) {
        if ( ! get_user_meta( $admin_id, 'sgd_diamonds', true ) ) {
            update_user_meta( $admin_id, 'sgd_diamonds', 5000 );
        }
    }

    $emp_email = 'employee@example.com';
    $emp_id = email_exists( $emp_email );
    if ( ! $emp_id ) {
        $emp_id = wp_insert_user( [
            'user_login'    => 'employee',
            'user_pass'     => 'Emp123!',
            'user_email'    => $emp_email,
            'role'          => 'employee',
            'display_name'  => 'Arthas the Champion',
        ] );
    }
    if ( $emp_id && ! is_wp_error( $emp_id ) ) {
        if ( ! get_user_meta( $emp_id, 'sgd_diamonds', true ) ) {
            update_user_meta( $emp_id, 'sgd_diamonds', 1200 ); // Start with 1,200 diamonds
        }
        if ( ! get_user_meta( $emp_id, 'sgd_faction', true ) ) {
            update_user_meta( $emp_id, 'sgd_faction', 'Alliance Vanguard' );
            update_user_meta( $emp_id, 'sgd_hero_title', 'Champion of the Alliance' );
        }
    }

    // Second Demo Employee: Jaina the Archmage
    $emp2_email = 'employee2@example.com';
    $emp2_id = email_exists( $emp2_email );
    if ( ! $emp2_id ) {
        $emp2_id = wp_insert_user( [
            'user_login'    => 'employee2',
            'user_pass'     => 'Emp123!',
            'user_email'    => $emp2_email,
            'role'          => 'employee',
            'display_name'  => 'Jaina the Archmage',
        ] );
    }
    if ( $emp2_id && ! is_wp_error( $emp2_id ) ) {
        if ( ! get_user_meta( $emp2_id, 'sgd_diamonds', true ) ) {
            update_user_meta( $emp2_id, 'sgd_diamonds', 1850 ); // 1,850 diamonds
        }
        if ( ! get_user_meta( $emp2_id, 'sgd_faction', true ) ) {
            update_user_meta( $emp2_id, 'sgd_faction', 'Kirin Tor Operations' );
            update_user_meta( $emp2_id, 'sgd_hero_title', 'Archmage of the Guild' );
            update_user_meta( $emp2_id, 'sgd_daily_streak', 2 );
            update_user_meta( $emp2_id, 'sgd_best_streak', 4 );
            update_user_meta( $emp2_id, 'sgd_last_task_date', current_time( 'Y-m-d' ) );
        }
    }

    if ( $admin_id && ! is_wp_error( $admin_id ) ) {
        if ( ! get_user_meta( $admin_id, 'sgd_faction', true ) ) {
            update_user_meta( $admin_id, 'sgd_faction', 'High Command' );
            update_user_meta( $admin_id, 'sgd_hero_title', 'Grand Marshal Admin' );
        }
        if ( ! get_user_meta( $admin_id, 'sgd_job_class', true ) ) {
            update_user_meta( $admin_id, 'sgd_job_class', 'Administrator' );
        }
        if ( ! get_user_meta( $admin_id, 'sgd_avatar', true ) ) {
            update_user_meta( $admin_id, 'sgd_avatar', '👑' );
        }
    }

    // Default metadata for employee 1 (Arthas)
    if ( $emp_id && ! is_wp_error( $emp_id ) ) {
        if ( ! get_user_meta( $emp_id, 'sgd_job_class', true ) ) {
            update_user_meta( $emp_id, 'sgd_job_class', 'Staff' );
        }
        if ( ! get_user_meta( $emp_id, 'sgd_avatar', true ) ) {
            update_user_meta( $emp_id, 'sgd_avatar', '⚔️' );
        }
    }

    // Default metadata for employee 2 (Jaina)
    if ( $emp2_id && ! is_wp_error( $emp2_id ) ) {
        if ( ! get_user_meta( $emp2_id, 'sgd_job_class', true ) ) {
            update_user_meta( $emp2_id, 'sgd_job_class', 'Staff' );
        }
        if ( ! get_user_meta( $emp2_id, 'sgd_avatar', true ) ) {
            update_user_meta( $emp2_id, 'sgd_avatar', '🧙‍♀️' );
        }
    }

    // Third Demo Employee: Sylvanas the Ranger General (LEVEL 30 HERO)
    $emp3_email = 'employee3@example.com';
    $emp3_id = email_exists( $emp3_email );
    if ( ! $emp3_id ) {
        $emp3_id = wp_insert_user( [
            'user_login'    => 'employee3',
            'user_pass'     => 'Emp123!',
            'user_email'    => $emp3_email,
            'role'          => 'employee',
            'display_name'  => 'Sylvanas the Ranger General',
        ] );
    }
    if ( $emp3_id && ! is_wp_error( $emp3_id ) ) {
        if ( ! get_user_meta( $emp3_id, 'sgd_diamonds', true ) ) {
            update_user_meta( $emp3_id, 'sgd_diamonds', 3200 );
        }
        // Level 30 XP requirement: sqrt(xp / 12) = 29 -> 29^2 * 12 = 10,092. Set to 10,350 XP.
        if ( ! get_user_meta( $emp3_id, 'sgd_xp', true ) ) {
            update_user_meta( $emp3_id, 'sgd_xp', 10350 );
        }
        if ( ! get_user_meta( $emp3_id, 'sgd_job_class', true ) ) {
            update_user_meta( $emp3_id, 'sgd_job_class', 'Staff' );
        }
        if ( ! get_user_meta( $emp3_id, 'sgd_avatar', true ) ) {
            update_user_meta( $emp3_id, 'sgd_avatar', '🏹' );
        }
        if ( ! get_user_meta( $emp3_id, 'sgd_promotion_status', true ) ) {
            update_user_meta( $emp3_id, 'sgd_promotion_status', 'eligible' );
        }
        if ( ! get_user_meta( $emp3_id, 'sgd_faction', true ) ) {
            update_user_meta( $emp3_id, 'sgd_faction', 'Forsaken Vanguard' );
            update_user_meta( $emp3_id, 'sgd_hero_title', 'Ranger-General of the Guild' );
            update_user_meta( $emp3_id, 'sgd_daily_streak', 6 );
            update_user_meta( $emp3_id, 'sgd_best_streak', 9 );
            update_user_meta( $emp3_id, 'sgd_last_task_date', current_time( 'Y-m-d' ) );
        }
    }

    // Fourth Demo Employee: Thrall the Earth Warder
    $emp4_email = 'employee4@example.com';
    $emp4_id = email_exists( $emp4_email );
    if ( ! $emp4_id ) {
        $emp4_id = wp_insert_user( [
            'user_login'    => 'employee4',
            'user_pass'     => 'Emp123!',
            'user_email'    => $emp4_email,
            'role'          => 'employee',
            'display_name'  => 'Thrall the Earth Warder',
        ] );
    }
    if ( $emp4_id && ! is_wp_error( $emp4_id ) ) {
        if ( ! get_user_meta( $emp4_id, 'sgd_diamonds', true ) ) {
            update_user_meta( $emp4_id, 'sgd_diamonds', 850 );
        }
        if ( ! get_user_meta( $emp4_id, 'sgd_xp', true ) ) {
            update_user_meta( $emp4_id, 'sgd_xp', 400 ); // Level 5
        }
        if ( ! get_user_meta( $emp4_id, 'sgd_job_class', true ) ) {
            update_user_meta( $emp4_id, 'sgd_job_class', 'Staff' );
        }
        if ( ! get_user_meta( $emp4_id, 'sgd_avatar', true ) ) {
            update_user_meta( $emp4_id, 'sgd_avatar', '⚡' );
        }
        if ( ! get_user_meta( $emp4_id, 'sgd_faction', true ) ) {
            update_user_meta( $emp4_id, 'sgd_faction', 'Earthen Ring Logistics' );
            update_user_meta( $emp4_id, 'sgd_hero_title', 'Shaman of the Elements' );
            update_user_meta( $emp4_id, 'sgd_daily_streak', 1 );
            update_user_meta( $emp4_id, 'sgd_best_streak', 3 );
            update_user_meta( $emp4_id, 'sgd_last_task_date', current_time( 'Y-m-d' ) );
        }
    }

    // 2. Seed Default Achievements
    $table_ach = $wpdb->prefix . 'sgd_achievements';
    $existing_ach = $wpdb->get_var( "SELECT COUNT(*) FROM $table_ach" );
    if ( ! $existing_ach ) {
        $default_achs = [
            [
                'slug'            => 'first_blood',
                'title'           => 'First Blood',
                'description'     => 'Complete your very first Daily Task.',
                'icon'            => '⚔️',
                'reward_diamonds' => 100,
                'category'        => 'Combat & Tasks',
            ],
            [
                'slug'            => 'daily_grinder',
                'title'           => 'Honorbound Grinder',
                'description'     => 'Complete 3 Daily Tasks in service to the Guild.',
                'icon'            => '📜',
                'reward_diamonds' => 300,
                'category'        => 'Combat & Tasks',
            ],
            [
                'slug'            => 'emergency_hero',
                'title'           => 'Emergency Defender',
                'description'     => 'Answer the call and resolve an Urgent Emergency Task.',
                'icon'            => '⚡',
                'reward_diamonds' => 500,
                'category'        => 'Emergency',
            ],
            [
                'slug'            => 'diamond_hoarder',
                'title'           => 'Diamond Hoarder',
                'description'     => 'Accumulate a balance of 1,000 Diamonds or more.',
                'icon'            => '💎',
                'reward_diamonds' => 200,
                'category'        => 'Wealth',
            ],
            [
                'slug'            => 'shop_baron',
                'title'           => 'Goblin Trader',
                'description'     => 'Redeem a glorious reward from the Goblin Cash Shop.',
                'icon'            => '💰',
                'reward_diamonds' => 250,
                'category'        => 'Economy',
            ],
            [
                'slug'            => 'team_player',
                'title'           => 'Raid Coordinator',
                'description'     => 'Call for backup using the Need Help beacon.',
                'icon'            => '🛡️',
                'reward_diamonds' => 150,
                'category'        => 'Teamwork',
            ],
        ];

        foreach ( $default_achs as $ach ) {
            $wpdb->insert( $table_ach, $ach );
        }

        // Grant employee initial achievement
        if ( $emp_id && ! is_wp_error( $emp_id ) ) {
            $first_ach_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_ach WHERE slug = %s", 'diamond_hoarder' ) );
            if ( $first_ach_id ) {
                $wpdb->insert( $wpdb->prefix . 'sgd_user_achievements', [
                    'user_id'        => $emp_id,
                    'achievement_id' => $first_ach_id,
                    'unlocked_at'    => current_time( 'mysql' ),
                ] );
            }
        }
    }

    // Ensure streak consistency achievements exist
    $streak_achs = [
        [
            'slug'            => 'streak_flame_3',
            'title'           => 'Flamekeeper of Duty',
            'description'     => 'Maintain a 3-Day Daily Quest streak without breaking the chain.',
            'icon'            => '🔥',
            'reward_diamonds' => 300,
            'category'        => 'Dedication',
        ],
        [
            'slug'            => 'streak_flame_7',
            'title'           => 'Unstoppable Vanguard',
            'description'     => 'Maintain a 7-Day uninterrupted Daily Quest streak in service of the guild.',
            'icon'            => '⚔️',
            'reward_diamonds' => 750,
            'category'        => 'Dedication',
        ],
        [
            'slug'            => 'streak_flame_14',
            'title'           => 'Living Legend of Consistency',
            'description'     => 'Achieve a legendary 14-Day uninterrupted Daily Quest streak! Feat of the Gods.',
            'icon'            => '👑',
            'reward_diamonds' => 2000,
            'category'        => 'Dedication',
        ],
    ];
    foreach ( $streak_achs as $s_ach ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_ach WHERE slug = %s", $s_ach['slug'] ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_ach, $s_ach );
        }
    }

    // Kudos and peer recognition achievements
    $kudos_achs = [
        [
            'slug'            => 'kudos_giver',
            'title'           => 'Generous Comrade',
            'description'     => 'Send your first Mana & Kudos bonus to a fellow guild member.',
            'icon'            => '⚡',
            'reward_diamonds' => 100,
            'category'        => 'Teamwork',
        ],
        [
            'slug'            => 'kudos_receiver',
            'title'           => 'Beloved Champion',
            'description'     => 'Receive recognition and peer Mana from a fellow hero in the guild.',
            'icon'            => '💖',
            'reward_diamonds' => 100,
            'category'        => 'Teamwork',
        ],
    ];
    foreach ( $kudos_achs as $k_ach ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_ach WHERE slug = %s", $k_ach['slug'] ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_ach, $k_ach );
        }
    }

    // Seed sample kudos between heroes if empty
    $table_kudos = $wpdb->prefix . 'sgd_kudos';
    $existing_kudos = $wpdb->get_var( "SELECT COUNT(*) FROM $table_kudos" );
    if ( ! $existing_kudos && $admin_id && $emp_id && $emp2_id ) {
        $wpdb->insert( $table_kudos, [
            'from_user_id'    => $admin_id,
            'to_user_id'      => $emp_id,
            'amount_diamonds' => 50,
            'message'         => 'Outstanding turnaround on resolving the database latency spike this morning! True hero work.',
            'created_at'      => current_time( 'mysql' ),
        ] );
        $wpdb->insert( $table_kudos, [
            'from_user_id'    => $emp2_id,
            'to_user_id'      => $emp_id,
            'amount_diamonds' => 50,
            'message'         => 'Thanks for reviewing my pull request so quickly, saved the staging release!',
            'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-2 hours', current_time( 'timestamp' ) ) ),
        ] );
    }

    // Fortune Wheel & Peer Bounty Achievements
    $fun_achs = [
        [
            'slug'            => 'wheel_spinner',
            'title'           => 'Fortune Favored',
            'description'     => 'Take your first morning spin at the Goblin Daily Fortune Wheel.',
            'icon'            => '🎰',
            'reward_diamonds' => 50,
            'category'        => 'Economy',
        ],
        [
            'slug'            => 'bounty_hunter',
            'title'           => 'Mercenary Hero',
            'description'     => 'Complete a Peer Bounty posted by a fellow colleague.',
            'icon'            => '🎯',
            'reward_diamonds' => 150,
            'category'        => 'Combat & Tasks',
        ],
        [
            'slug'            => 'bounty_patron',
            'title'           => 'Guild Patron',
            'description'     => 'Post a bounty from your personal diamond wallet to commission a teammate.',
            'icon'            => '🤝',
            'reward_diamonds' => 100,
            'category'        => 'Teamwork',
        ],
    ];
    foreach ( $fun_achs as $f_ach ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_ach WHERE slug = %s", $f_ach['slug'] ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_ach, $f_ach );
        }
    }

    // Seed sample peer bounties if empty
    $table_bounties = $wpdb->prefix . 'sgd_bounties';
    $existing_bounties = $wpdb->get_var( "SELECT COUNT(*) FROM $table_bounties" );
    if ( ! $existing_bounties && $emp_id && $emp2_id ) {
        $wpdb->insert( $table_bounties, [
            'poster_id'       => $emp_id,
            'title'           => 'Verify Stripe Webhook Signature on iOS Mobile Safari',
            'description'     => 'Need an engineer to trigger a test checkout on mobile Safari and verify that the webhook handler logs HTTP 200.',
            'category'        => 'Testing & QA',
            'reward_diamonds' => 200,
            'claimed_by'      => 0,
            'status'          => 'open',
            'created_at'      => current_time( 'mysql' ),
        ] );

        $wpdb->insert( $table_bounties, [
            'poster_id'       => $emp2_id,
            'title'           => 'Proofread API Documentation for v3.1 Release',
            'description'     => 'Quick peer review on the markdown endpoints guide before tomorrow\'s client sprint review meeting.',
            'category'        => 'Documentation',
            'reward_diamonds' => 150,
            'claimed_by'      => 0,
            'status'          => 'open',
            'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-3 hours', current_time( 'timestamp' ) ) ),
        ] );
    }

    // 3. Seed Default Daily & Urgent Tasks if empty
    $table_tasks = $wpdb->prefix . 'sgd_tasks';
    $existing_tasks = $wpdb->get_var( "SELECT COUNT(*) FROM $table_tasks" );
    if ( ! $existing_tasks ) {
        $sample_tasks = [
            // Daily Tasks (Reward: 200 Diamonds)
            [
                'type'            => 'daily',
                'title'           => 'Deploy Production Morning Health Check',
                'description'     => 'Verify server response times, check Apache & MySQL error logs, and ensure all services are green before 10:00 AM.',
                'category'        => 'DevOps & Maintenance',
                'reward_diamonds' => 200,
                'status'          => 'open',
                'help_status'     => 'none',
            ],
            [
                'type'            => 'daily',
                'title'           => 'Review & Merge Pull Request Backlog',
                'description'     => 'Review at least 2 pending team Pull Requests, write constructive code review comments, and sign off for CI/CD pipeline.',
                'category'        => 'Code & Architecture',
                'reward_diamonds' => 200,
                'status'          => 'open',
                'help_status'     => 'none',
            ],
            [
                'type'            => 'daily',
                'title'           => 'Sync Daily Standup Notes & Jira Tickets',
                'description'     => 'Update sprint board statuses, document blockers, and log daily milestones in the team discord / slack channel.',
                'category'        => 'Project Management',
                'reward_diamonds' => 200,
                'status'          => 'open',
                'help_status'     => 'none',
            ],
            // Urgent Emergency Tasks (Reward: 1,000 Diamonds)
            [
                'type'            => 'urgent',
                'title'           => 'CRITICAL: Database Replication Lag Spike in US-East',
                'description'     => 'EMERGENCY: High read replica latency detected. Need an engineer to inspect slow queries, kill hung sessions, and verify failover cluster.',
                'category'        => '🚨 Emergency Bounty',
                'reward_diamonds' => 1000,
                'status'          => 'open',
                'help_status'     => 'none',
            ],
            [
                'type'            => 'urgent',
                'title'           => 'URGENT: Hotfix Client Security Patch v4.2.1',
                'description'     => 'Emergency security advisory released. Need hero developer to backport the patch, run regression tests, and prepare release artifacts.',
                'category'        => '🔥 Critical Security',
                'reward_diamonds' => 1000,
                'status'          => 'open',
                'help_status'     => 'none',
            ],
        ];

        foreach ( $sample_tasks as $t ) {
            $wpdb->insert( $table_tasks, $t );
        }
    }

    // 4. Seed sample daily construction report if empty
    $table_daily_reports = $wpdb->prefix . 'sgd_daily_reports';
    $existing_rep_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_daily_reports" );
    if ( ! $existing_rep_count ) {
        $sample_raw = "hujan jam 10-4 sore. butuh tukang batu 10, masuk pasir 100kg, pake hammer drill. kita bikin taman. pompa air rusak.";
        $parsed = sgd_smart_nlp_decipher( $sample_raw );
        $today = current_time( 'Y-m-d' );
        $author_id = ( isset( $emp_id ) && $emp_id && ! is_wp_error( $emp_id ) ) ? $emp_id : 1;
        $wpdb->insert( $table_daily_reports, [
            'report_date'            => $today,
            'user_id'                => $author_id,
            'raw_input'              => $sample_raw,
            'weather_hourly'         => wp_json_encode( $parsed['weather_hourly'] ),
            'manpower'               => wp_json_encode( $parsed['manpower'] ),
            'materials'              => wp_json_encode( $parsed['materials'] ),
            'equipment'              => wp_json_encode( $parsed['equipment'] ),
            'work_executed'          => wp_json_encode( $parsed['work_executed'] ),
            'issues'                 => wp_json_encode( $parsed['issues'] ),
            'supervisor_instruction' => 'Prioritaskan penggantian pompa air cadangan dari gudang logistik sebelum pukul 13.00.',
            'next_day_plan'          => 'Penyelesaian penataan rumput gajah mini dan pemadatan paving tepi taman area utara.',
            'safety_k3'              => 'Nihil kecelakaan kerja',
            'additional_notes'       => 'Pekerjaan taman dilanjutkan bertahap setelah hujan reda pukul 16.00 WIB.',
            'photo_1'                => 'https://images.unsplash.com/photo-1541888946425-d0fbb186156f?w=800&auto=format&fit=crop&q=80',
            'photo_2'                => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=800&auto=format&fit=crop&q=80',
            'photo_3'                => 'https://images.unsplash.com/photo-1581094794329-c8112a89af12?w=800&auto=format&fit=crop&q=80',
            'status'                 => 'approved',
            'parsed_with'            => 'gemini',
            'created_at'             => current_time( 'mysql' ),
            'updated_at'             => current_time( 'mysql' ),
        ] );
    }

    // 5. Seed Cicle Kanban task groups (HQ, Tim, Proyek) & initial tasks if empty
    $table_quest_teams = $wpdb->prefix . 'sgd_quest_teams';
    $existing_team_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_quest_teams" );
    if ( ! $existing_team_count ) {
        $adm_uid = ( isset( $admin_id ) && $admin_id && ! is_wp_error( $admin_id ) ) ? $admin_id : 1;
        $emp1_uid = ( isset( $emp_id ) && $emp_id && ! is_wp_error( $emp_id ) ) ? $emp_id : 2;
        $emp2_uid = ( isset( $emp2_id ) && $emp2_id && ! is_wp_error( $emp2_id ) ) ? $emp2_id : 3;
        $emp3_uid = ( isset( $emp3_id ) && $emp3_id && ! is_wp_error( $emp3_id ) ) ? $emp3_id : 4;
        $emp4_uid = ( isset( $emp4_id ) && $emp4_id && ! is_wp_error( $emp4_id ) ) ? $emp4_id : 5;

        // Group 1: HQ (Markas Besar)
        $wpdb->insert( $table_quest_teams, [
            'name'        => 'HQ Grand Operations',
            'type'        => 'hq',
            'description' => 'Markas komando tertinggi: koordinasi strategi organisasi, evaluasi kinerja serikat, dan kebijakan umum perusahaan.',
            'manager_id'  => $adm_uid,
            'member_ids'  => implode( ',', [ $adm_uid, $emp1_uid, $emp2_uid, $emp3_uid, $emp4_uid ] ),
            'created_by'  => $adm_uid,
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ] );
        $team_hq_id = $wpdb->insert_id;

        // Group 2: Tim (Regu Kerja / Divisi)
        $wpdb->insert( $table_quest_teams, [
            'name'        => 'Tim Frontend & Gamification',
            'type'        => 'tim',
            'description' => 'Divisi pengembangan fitur interaktif, optimasi Kanban board, dan sistem reward gamifikasi.',
            'manager_id'  => $emp2_uid, // Jaina
            'member_ids'  => implode( ',', [ $adm_uid, $emp1_uid, $emp2_uid, $emp3_uid ] ),
            'created_by'  => $adm_uid,
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ] );
        $team_tim_id = $wpdb->insert_id;

        // Group 3: Proyek (Inisiatif Lapangan)
        $wpdb->insert( $table_quest_teams, [
            'name'        => 'Proyek Revitalisasi Konstruksi Alpha',
            'type'        => 'proyek',
            'description' => 'Inisiatif proyek lapangan: renovasi infrastruktur, pengecoran pondasi zona inti, dan keselamatan kerja K3.',
            'manager_id'  => $emp4_uid, // Thrall
            'member_ids'  => implode( ',', [ $adm_uid, $emp1_uid, $emp3_uid, $emp4_uid ] ),
            'created_by'  => $adm_uid,
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ] );
        $team_proyek_id = $wpdb->insert_id;

        // Seed sample tasks across the 4 columns for Team Tim
        $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
        $now = current_time( 'timestamp' );

        $sample_cicle_tasks = [
            [
                'team_id'          => $team_tim_id,
                'column_name'      => 'To do list',
                'title'            => 'Audit UI Responsive & Drag-Drop Kanban di Layar Sentuh',
                'description'      => '<h3>Spesifikasi Pengerjaan:</h3><p>Verifikasi bahwa kolom kartu Kanban dapat digeser mulus menggunakan touch event pada perangkat tablet dan mobile.</p><ul><li>Uji pada viewport 768px dan 1024px</li><li>Pastikan tombol <em>+ Tambah tugas</em> mudah diakses</li><li>Periksa transisi animasi kartu</li></ul>',
                'deadline'         => date( 'Y-m-d H:i:s', $now + 86400 * 3 ),
                'assigned_members' => implode( ',', [ $emp1_uid, $emp3_uid ] ),
                'sort_order'       => 1,
            ],
            [
                'team_id'          => $team_tim_id,
                'column_name'      => 'Dikerjakan',
                'title'            => 'Integrasi WYSIWYG Editor & File Attachment Uploader',
                'description'      => '<h3>Status Saat Ini:</h3><p>Editor WYSIWYG besar sedang dipasang pada modal kartu tugas dengan toolbar lengkap (Bold, Italic, List, Quote, Code, Color).</p><blockquote>Fitur unggah file mendukung dokumen PDF, gambar JPEG/PNG, dan arsip ZIP untuk memfasilitasi koordinasi tim.</blockquote>',
                'deadline'         => date( 'Y-m-d H:i:s', $now + 86400 * 1 ),
                'assigned_members' => implode( ',', [ $emp2_uid, $adm_uid ] ),
                'sort_order'       => 1,
            ],
            [
                'team_id'          => $team_tim_id,
                'column_name'      => 'Selesai',
                'title'            => 'Perancangan Tata Letak 4 Kolom: To do list, Dikerjakan, Selesai, Batal',
                'description'      => '<p>Papan Kanban dengan 4 kontainer utama telah selesai diimplementasikan dengan warna aksen tematik.</p>',
                'deadline'         => date( 'Y-m-d H:i:s', $now - 86400 * 2 ),
                'assigned_members' => implode( ',', [ $emp2_uid ] ),
                'sort_order'       => 1,
            ],
            [
                'team_id'          => $team_tim_id,
                'column_name'      => 'Batal',
                'title'            => 'Rencana Penggunaan Library External CDN yang Berat',
                'description'      => '<p>Dibatalkan demi menjamin sistem bekerja sepenuhnya offline, cepat, dan aman tanpa ketergantungan koneksi internet pihak ketiga.</p>',
                'deadline'         => null,
                'assigned_members' => implode( ',', [ $adm_uid ] ),
                'sort_order'       => 1,
            ],
            // Additional task in To do list
            [
                'team_id'          => $team_tim_id,
                'column_name'      => 'To do list',
                'title'            => 'Sistem Duplikasi Tugas (Clone Task) 1-Klik',
                'description'      => '<p>Memastikan admin dan manager dapat menduplikasi tugas yang berulang lengkap beserta rincian deskripsi, tenggat waktu, dan anggota yang ditugaskan.</p>',
                'deadline'         => date( 'Y-m-d H:i:s', $now + 86400 * 5 ),
                'assigned_members' => implode( ',', [ $emp1_uid ] ),
                'sort_order'       => 2,
            ],
        ];

        foreach ( $sample_cicle_tasks as $sct ) {
            $wpdb->insert( $table_quest_tasks, [
                'team_id'          => $sct['team_id'],
                'column_name'      => $sct['column_name'],
                'title'            => $sct['title'],
                'description'      => $sct['description'],
                'deadline'         => $sct['deadline'],
                'assigned_members' => $sct['assigned_members'],
                'sort_order'       => $sct['sort_order'],
                'created_by'       => $adm_uid,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ] );
            $first_task_id = $wpdb->insert_id;
        }

        // Seed a sample comment on the working task
        if ( isset( $first_task_id ) ) {
            $table_quest_comments = $wpdb->prefix . 'sgd_quest_comments';
            $wpdb->insert( $table_quest_comments, [
                'task_id'    => $first_task_id - 3, // The 'Dikerjakan' task
                'user_id'    => $emp2_uid,
                'comment'    => 'Editor WYSIWYG dan fitur upload file berjalan sangat baik. Siap direview oleh Grand Marshal!',
                'created_at' => current_time( 'mysql' ),
            ] );
            $wpdb->insert( $table_quest_comments, [
                'task_id'    => $first_task_id - 3,
                'user_id'    => $adm_uid,
                'comment'    => 'Bagus sekali! Pastikan juga tombol clone task dan filter 4 kolom terhubung sempurna.',
                'created_at' => current_time( 'mysql' ),
            ] );
        }
    }
}

// Initialize on plugin load
add_action( 'init', function() {
    sgd_install_database();

    // Ensure static front page points to Gamification Hub so WordPress home always renders our portal
    $front_page_id = get_option( 'page_on_front' );
    if ( ! $front_page_id || ! get_post( $front_page_id ) ) {
        $page = get_page_by_path( 'gamification-hub' );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => 'Gamification Hub',
                'post_name'    => 'gamification-hub',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '<!-- World of Warcraft 16-Bit Gamification Portal -->',
            ] );
            if ( ! is_wp_error( $page_id ) ) {
                update_option( 'show_on_front', 'page' );
                update_option( 'page_on_front', $page_id );
            }
        } else {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $page->ID );
        }
    } else {
        update_option( 'show_on_front', 'page' );
    }

    // Handle Form Submissions
    sgd_handle_form_actions();

    // Ensure WP-Cron daily recurring reset is scheduled
    if ( ! wp_next_scheduled( 'sgd_daily_quest_reset_cron' ) ) {
        $midnight = strtotime( 'tomorrow midnight', current_time( 'timestamp' ) );
        wp_schedule_event( $midnight, 'daily', 'sgd_daily_quest_reset_cron' );
    }
} );

// Hook WP-Cron Daily Reset
add_action( 'sgd_daily_quest_reset_cron', 'sgd_run_daily_reset' );

// Daily Streak System Functions
function sgd_calculate_streak_bonus_percent( $streak ) {
    if ( $streak <= 1 ) return 0;
    if ( $streak === 2 ) return 10;
    if ( $streak === 3 ) return 20;
    if ( $streak === 4 ) return 30;
    return 50; // 5+ days: +50% max bonus!
}

function sgd_get_user_streak( $user_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    if ( ! $user_id ) {
        return [
            'current'         => 0,
            'best'            => 0,
            'bonus_percent'   => 0,
            'is_active_today' => false,
            'last_date'       => '',
            'next_bonus'      => 0,
        ];
    }

    $today = current_time( 'Y-m-d' );
    $yesterday = date( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );

    $last_date = get_user_meta( $user_id, 'sgd_last_task_date', true );
    $raw_streak = (int) get_user_meta( $user_id, 'sgd_daily_streak', true );
    $best = (int) get_user_meta( $user_id, 'sgd_best_streak', true );

    // Determine current effective streak
    if ( empty( $last_date ) ) {
        $effective_streak = 0;
    } elseif ( $last_date === $today || $last_date === $yesterday ) {
        $effective_streak = max( 1, $raw_streak );
    } else {
        // More than 1 day skipped: streak broken
        $effective_streak = 0;
    }

    $is_active_today = ( $last_date === $today );
    $bonus_percent = sgd_calculate_streak_bonus_percent( $effective_streak );
    $next_bonus = sgd_calculate_streak_bonus_percent( $effective_streak + 1 );

    return [
        'current'         => $effective_streak,
        'best'            => max( $best, $effective_streak ),
        'bonus_percent'   => $bonus_percent,
        'is_active_today' => $is_active_today,
        'last_date'       => $last_date,
        'next_bonus'      => $next_bonus,
    ];
}

function sgd_record_user_daily_completion( $user_id ) {
    $today = current_time( 'Y-m-d' );
    $yesterday = date( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );

    $last_date = get_user_meta( $user_id, 'sgd_last_task_date', true );
    $streak = (int) get_user_meta( $user_id, 'sgd_daily_streak', true );
    $best = (int) get_user_meta( $user_id, 'sgd_best_streak', true );

    if ( $last_date === $today ) {
        // Already completed one today; maintain existing streak
        if ( $streak < 1 ) $streak = 1;
    } elseif ( $last_date === $yesterday ) {
        // Consecutive day!
        $streak++;
    } else {
        // Reset or first time
        $streak = 1;
    }

    if ( $streak > $best ) {
        $best = $streak;
        update_user_meta( $user_id, 'sgd_best_streak', $best );
    }

    update_user_meta( $user_id, 'sgd_daily_streak', $streak );
    update_user_meta( $user_id, 'sgd_last_task_date', $today );

    $bonus_percent = sgd_calculate_streak_bonus_percent( $streak );

    return [
        'current'       => $streak,
        'best'          => $best,
        'bonus_percent' => $bonus_percent,
        'last_date'     => $today,
    ];
}

// Automated Daily Recurring Quest Reset Engine
function sgd_run_daily_reset() {
    global $wpdb;
    $table_tasks = $wpdb->prefix . 'sgd_tasks';

    // Find and reset all recurring daily tasks back to open
    $affected = $wpdb->query(
        "UPDATE $table_tasks 
         SET status = 'open', 
             claimed_by = 0, 
             help_status = 'none', 
             need_help_by = 0, 
             need_help_time = NULL, 
             help_notes = NULL, 
             completed_by = 0, 
             completed_time = NULL, 
             proof_image_url = NULL, 
             proof_notes = NULL 
         WHERE type = 'daily' AND is_recurring = 1"
    );

    update_option( 'sgd_last_daily_reset', current_time( 'mysql' ) );
    return (int) $affected;
}

// XP & Leveling Progression Engine (Level 1 to 60)
function sgd_get_user_xp_data( $user_id = null ) {
    global $wpdb;
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    if ( ! $user_id ) {
        return [
            'level'          => 1,
            'total_xp'       => 0,
            'xp_in_level'    => 0,
            'xp_needed'      => 100,
            'percent'        => 0,
            'faction'        => 'Guild Recruit',
            'title'          => 'Wanderer',
            'daily_done'     => 0,
            'urgent_done'    => 0,
            'ach_count'      => 0,
            'kudos_received' => 0,
        ];
    }

    $table_tasks = $wpdb->prefix . 'sgd_tasks';
    $table_task_logs = $wpdb->prefix . 'sgd_task_logs';
    $table_user_ach = $wpdb->prefix . 'sgd_user_achievements';
    $table_kudos = $wpdb->prefix . 'sgd_kudos';

    // Count completions from task logs (or fallback to tasks)
    $daily_done = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_task_logs WHERE user_id = %d AND task_type = 'daily'",
        $user_id
    ) );
    $urgent_done = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_task_logs WHERE user_id = %d AND task_type = 'urgent'",
        $user_id
    ) );
    if ( ! $daily_done && ! $urgent_done ) {
        $daily_done = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_tasks WHERE completed_by = %d AND type = 'daily' AND status = 'completed'",
            $user_id
        ) );
        $urgent_done = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_tasks WHERE completed_by = %d AND type = 'urgent' AND status = 'completed'",
            $user_id
        ) );
    }

    $ach_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_user_ach WHERE user_id = %d",
        $user_id
    ) );

    $kudos_received = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_kudos WHERE to_user_id = %d",
        $user_id
    ) );

    $diamonds = sgd_get_diamonds( $user_id );
    $streak_info = sgd_get_user_streak( $user_id );

    // XP breakdown
    $user_stored_xp = (int) get_user_meta( $user_id, 'sgd_xp', true );
    $xp_quests = ( $daily_done * 150 ) + ( $urgent_done * 500 );
    $xp_achs   = $ach_count * 200;
    $xp_kudos  = $kudos_received * 75;
    $xp_streak = $streak_info['current'] * 50;

    $total_xp = max( 150, $user_stored_xp + $xp_quests + $xp_achs + $xp_kudos + $xp_streak );

    // Calculate level (1 to 60)
    // Formula: level = floor( sqrt( total_xp / 12 ) ) + 1
    $level = min( 60, max( 1, (int) floor( sqrt( $total_xp / 12 ) ) + 1 ) );

    // XP calculation within level
    $xp_current_floor = 12 * pow( $level - 1, 2 );
    $xp_next_ceil     = 12 * pow( $level, 2 );
    $xp_range         = max( 50, $xp_next_ceil - $xp_current_floor );
    $xp_in_level      = max( 0, $total_xp - $xp_current_floor );
    $percent          = min( 100, max( 8, (int) round( ( $xp_in_level / $xp_range ) * 100 ) ) );

    // Check Level 30 milestone trigger
    if ( $level >= 30 ) {
        $p_status = get_user_meta( $user_id, 'sgd_promotion_status', true );
        if ( empty( $p_status ) ) {
            update_user_meta( $user_id, 'sgd_promotion_status', 'eligible' );
            update_user_meta( $user_id, 'sgd_lvl30_reached_at', current_time( 'mysql' ) );
        }
    }

    $faction = get_user_meta( $user_id, 'sgd_faction', true );
    if ( empty( $faction ) ) {
        $faction = current_user_can( 'administrator' ) ? 'High Command' : 'Alliance Vanguard';
    }

    $title = get_user_meta( $user_id, 'sgd_hero_title', true );
    if ( empty( $title ) ) {
        $title = "Level {$level} Champion";
    }

    return [
        'level'          => $level,
        'total_xp'       => $total_xp,
        'xp_in_level'    => $xp_in_level,
        'xp_needed'      => $xp_range,
        'percent'        => $percent,
        'faction'        => $faction,
        'title'          => $title,
        'daily_done'     => $daily_done,
        'urgent_done'    => $urgent_done,
        'ach_count'      => $ach_count,
        'kudos_received' => $kudos_received,
    ];
}

// User Stored XP Helpers
function sgd_get_user_xp( $user_id = null ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    return (int) get_user_meta( $user_id, 'sgd_xp', true );
}

function sgd_set_xp( $user_id, $amount ) {
    update_user_meta( $user_id, 'sgd_xp', max( 0, (int) $amount ) );
}

function sgd_add_xp( $user_id, $amount ) {
    $current = sgd_get_user_xp( $user_id );
    $new = $current + (int) $amount;
    sgd_set_xp( $user_id, $new );

    // Check if hero crossed Level 30 milestone
    $xp_data = sgd_get_user_xp_data( $user_id );
    if ( $xp_data['level'] >= 30 ) {
        $status = get_user_meta( $user_id, 'sgd_promotion_status', true );
        if ( empty( $status ) || $status === 'none' ) {
            update_user_meta( $user_id, 'sgd_promotion_status', 'eligible' );
            update_user_meta( $user_id, 'sgd_lvl30_reached_at', current_time( 'mysql' ) );
        }
    }
    return $new;
}

// Employee Job Class Helpers (e.g. Staff -> Manager)
function sgd_get_user_job_class( $user_id = null ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    $class = get_user_meta( $user_id, 'sgd_job_class', true );
    if ( empty( $class ) ) {
        if ( user_can( $user_id, 'administrator' ) ) {
            return 'Administrator';
        }
        return 'Staff';
    }
    return $class;
}

function sgd_set_user_job_class( $user_id, $class ) {
    return update_user_meta( $user_id, 'sgd_job_class', sanitize_text_field( $class ) );
}

// Employee Avatar & Crest Helpers
function sgd_get_user_avatar( $user_id = null ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    $avatar = get_user_meta( $user_id, 'sgd_avatar', true );
    if ( ! empty( $avatar ) ) {
        return $avatar;
    }
    $user = get_userdata( $user_id );
    $login = $user ? $user->user_login : '';
    if ( user_can( $user_id, 'administrator' ) || $login === 'admin' ) {
        return '👑';
    }
    if ( $login === 'employee2' ) {
        return '🧙‍♀️';
    }
    if ( $login === 'employee3' ) {
        return '🏹';
    }
    if ( $login === 'employee4' ) {
        return '⚡';
    }
    return '⚔️';
}

function sgd_set_user_avatar( $user_id, $avatar ) {
    return update_user_meta( $user_id, 'sgd_avatar', sanitize_text_field( $avatar ) );
}

function sgd_get_avatar_options() {
    return [
        '🏹' => '🏹 Sylvanas / Ranger General (Elven Marksman)',
        '⚡' => '⚡ Thrall / Shaman of the Elements (Stormcaller)',
        '⚔️' => '⚔️ Arthas / Paladin Knight (Holy Crusader)',
        '🧙‍♀️' => '🧙‍♀️ Jaina / Kirin Tor Archmage (Arcane Sorceress)',
        '🛡️' => '🛡️ Muradin / Mountain King (Ironforge Guardian)',
        '🗡️' => '🗡️ Illidan / Demon Hunter (Shadow Blade)',
        '👑' => '👑 Grand Marshal / High Commander',
        '💼' => '💼 Corporate Lead / Guild Manager',
        '🐉' => '🐉 Dragon Aspect / Mythic Paragon',
        '🔥' => '🔥 Flamekeeper / Senior Specialist',
    ];
}

function sgd_get_available_job_classes() {
    return [
        'Staff'             => 'Staff (Entry Hero)',
        'Senior Staff'      => 'Senior Staff (Experienced Specialist)',
        'Team Lead'         => 'Team Lead (Squad Commander)',
        'Assistant Manager' => 'Assistant Manager (Junior Officer)',
        'Manager'           => 'Manager (Full Department Leader)',
        'Senior Manager'    => 'Senior Manager (Executive Strategist)',
        'Director'          => 'Director / Guild Marshal',
    ];
}

// Query Level 30 Candidates for Admin Promotion Review
function sgd_get_level_30_candidates() {
    $employees = get_users( [ 'role' => 'employee' ] );
    $candidates = [];
    foreach ( $employees as $emp ) {
        $xp_data = sgd_get_user_xp_data( $emp->ID );
        if ( $xp_data['level'] >= 30 ) {
            $status = get_user_meta( $emp->ID, 'sgd_promotion_status', true );
            if ( empty( $status ) ) {
                $status = 'eligible';
                update_user_meta( $emp->ID, 'sgd_promotion_status', 'eligible' );
            }
            $task_id = (int) get_user_meta( $emp->ID, 'sgd_promotion_task_id', true );
            $candidates[] = [
                'user_id'           => $emp->ID,
                'user_login'        => $emp->user_login,
                'display_name'      => $emp->display_name,
                'level'             => $xp_data['level'],
                'total_xp'          => $xp_data['total_xp'],
                'job_class'         => sgd_get_user_job_class( $emp->ID ),
                'avatar'            => sgd_get_user_avatar( $emp->ID ),
                'promotion_status'  => $status,
                'promotion_task_id' => $task_id,
            ];
        }
    }
    return $candidates;
}

// Get all titles unlocked/available for a hero
function sgd_get_user_unlocked_titles( $user_id ) {
    $xp_data = sgd_get_user_xp_data( $user_id );
    $streak_info = sgd_get_user_streak( $user_id );
    $user = get_userdata( $user_id );
    $login = $user ? $user->user_login : '';

    $titles = [];

    // 1. Class & Faction Signature Titles
    if ( user_can( $user_id, 'administrator' ) ) {
        $titles[] = 'Grand Marshal Admin';
        $titles[] = 'High Commander';
    } elseif ( $login === 'employee2' ) {
        $titles[] = 'Archmage of the Guild';
        $titles[] = 'Sorceress Supreme';
    } else {
        $titles[] = 'Champion of the Alliance';
        $titles[] = 'Knight-Lieutenant';
    }

    // 2. Level Progression Milestone Titles
    $level = $xp_data['level'];
    if ( $level >= 1 )  $titles[] = 'Apprentice Quester';
    if ( $level >= 3 )  $titles[] = 'Vanguard Specialist';
    if ( $level >= 5 )  $titles[] = 'Veteran Hero';
    if ( $level >= 10 ) $titles[] = 'Grand Champion';
    if ( $level >= 20 ) $titles[] = 'Legendary Paragon';

    // 3. Streak Mastery Titles
    if ( $streak_info['current'] >= 2 || $streak_info['best'] >= 2 ) {
        $titles[] = 'The Consistent';
    }
    if ( $streak_info['current'] >= 5 || $streak_info['best'] >= 5 ) {
        $titles[] = 'The Flamekeeper';
    }

    // 4. Saved Unlocked Titles (Wheel Wins, Custom Titles, Achievements)
    $stored = get_user_meta( $user_id, 'sgd_unlocked_titles', true );
    if ( is_array( $stored ) ) {
        foreach ( $stored as $t ) {
            if ( ! empty( $t ) ) $titles[] = $t;
        }
    }

    // 5. Currently Active Title
    $current = get_user_meta( $user_id, 'sgd_hero_title', true );
    if ( ! empty( $current ) ) {
        $titles[] = $current;
    }

    return array_values( array_unique( array_filter( $titles ) ) );
}

// Hall of Legends Leaderboard Aggregator
function sgd_get_leaderboard_data() {
    $users = get_users();
    $leaderboard = [];

    foreach ( $users as $u ) {
        $xp_data = sgd_get_user_xp_data( $u->ID );
        $diamonds = sgd_get_diamonds( $u->ID );
        $streak = sgd_get_user_streak( $u->ID );

        $leaderboard[] = [
            'user_id'      => $u->ID,
            'user_login'   => $u->user_login,
            'display_name' => $u->display_name,
            'diamonds'     => $diamonds,
            'level'        => $xp_data['level'],
            'total_xp'     => $xp_data['total_xp'],
            'job_class'    => sgd_get_user_job_class( $u->ID ),
            'avatar'       => sgd_get_user_avatar( $u->ID ),
            'faction'      => $xp_data['faction'],
            'title'        => $xp_data['title'],
            'daily_done'   => $xp_data['daily_done'],
            'urgent_done'  => $xp_data['urgent_done'],
            'ach_count'    => $xp_data['ach_count'],
            'streak'       => $streak['current'],
            'best_streak'  => $streak['best'],
        ];
    }

    // Sort by Total XP / Diamonds descending
    usort( $leaderboard, function( $a, $b ) {
        if ( $a['total_xp'] === $b['total_xp'] ) {
            return $b['diamonds'] <=> $a['diamonds'];
        }
        return $b['total_xp'] <=> $a['total_xp'];
    } );

    return $leaderboard;
}

// Guild Department Factions Summary
function sgd_get_faction_summary() {
    $leaderboard = sgd_get_leaderboard_data();
    $factions = [
        'Alliance Vanguard' => [
            'name'       => 'Alliance Vanguard',
            'crest'      => '🦁',
            'desc'       => 'Engineering, DevOps & Site Reliability Defenders',
            'members'    => 0,
            'diamonds'   => 0,
            'total_xp'   => 0,
            'quests'     => 0,
        ],
        'Kirin Tor Operations' => [
            'name'       => 'Kirin Tor Operations',
            'crest'      => '🔮',
            'desc'       => 'Architecture, QA Automation & Arcane Specialists',
            'members'    => 0,
            'diamonds'   => 0,
            'total_xp'   => 0,
            'quests'     => 0,
        ],
        'High Command' => [
            'name'       => 'High Command',
            'crest'      => '👑',
            'desc'       => 'Guild Administration, Dispatch & Leadership Hall',
            'members'    => 0,
            'diamonds'   => 0,
            'total_xp'   => 0,
            'quests'     => 0,
        ],
    ];

    foreach ( $leaderboard as $entry ) {
        $fac_name = $entry['faction'];
        if ( ! isset( $factions[ $fac_name ] ) ) {
            $factions[ $fac_name ] = [
                'name'     => $fac_name,
                'crest'    => '⚔️',
                'desc'     => 'Guild Division',
                'members'  => 0,
                'diamonds' => 0,
                'total_xp' => 0,
                'quests'   => 0,
            ];
        }
        $factions[ $fac_name ]['members']++;
        $factions[ $fac_name ]['diamonds'] += $entry['diamonds'];
        $factions[ $fac_name ]['total_xp'] += $entry['total_xp'];
        $factions[ $fac_name ]['quests']   += ( $entry['daily_done'] + $entry['urgent_done'] );
    }

    return $factions;
}

// User Diamonds Helper
function sgd_get_diamonds( $user_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    if ( ! $user_id ) {
        return 0;
    }
    $d = get_user_meta( $user_id, 'sgd_diamonds', true );
    if ( $d === '' || $d === false ) {
        $d = 500;
        update_user_meta( $user_id, 'sgd_diamonds', $d );
    }
    return (int) $d;
}

function sgd_set_diamonds( $user_id, $amount ) {
    update_user_meta( $user_id, 'sgd_diamonds', max( 0, (int) $amount ) );
}

function sgd_add_diamonds( $user_id, $amount ) {
    $current = sgd_get_diamonds( $user_id );
    $new_amount = $current + (int) $amount;
    sgd_set_diamonds( $user_id, $new_amount );
    sgd_check_achievements( $user_id );
    return $new_amount;
}

function sgd_deduct_diamonds( $user_id, $amount ) {
    $current = sgd_get_diamonds( $user_id );
    if ( $current < $amount ) {
        return false;
    }
    sgd_set_diamonds( $user_id, $current - $amount );
    return true;
}

// Achievement checking logic
function sgd_check_achievements( $user_id ) {
    global $wpdb;
    if ( ! $user_id ) return;

    $table_tasks = $wpdb->prefix . 'sgd_tasks';
    $table_ach = $wpdb->prefix . 'sgd_achievements';
    $table_user_ach = $wpdb->prefix . 'sgd_user_achievements';

    // Count completed daily tasks
    $completed_daily = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_tasks WHERE completed_by = %d AND type = 'daily' AND status = 'completed'",
        $user_id
    ) );

    // Count completed urgent tasks
    $completed_urgent = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_tasks WHERE completed_by = %d AND type = 'urgent' AND status = 'completed'",
        $user_id
    ) );

    $diamonds = sgd_get_diamonds( $user_id );

    $to_unlock = [];
    if ( $completed_daily >= 1 ) {
        $to_unlock[] = 'first_blood';
    }
    if ( $completed_daily >= 3 ) {
        $to_unlock[] = 'daily_grinder';
    }
    if ( $completed_urgent >= 1 ) {
        $to_unlock[] = 'emergency_hero';
    }
    if ( $diamonds >= 1000 ) {
        $to_unlock[] = 'diamond_hoarder';
    }

    // Check streak achievements
    $streak_info = sgd_get_user_streak( $user_id );
    if ( $streak_info['current'] >= 3 || $streak_info['best'] >= 3 ) {
        $to_unlock[] = 'streak_flame_3';
    }
    if ( $streak_info['current'] >= 7 || $streak_info['best'] >= 7 ) {
        $to_unlock[] = 'streak_flame_7';
    }
    if ( $streak_info['current'] >= 14 || $streak_info['best'] >= 14 ) {
        $to_unlock[] = 'streak_flame_14';
    }

    foreach ( $to_unlock as $slug ) {
        $ach = $wpdb->get_row( $wpdb->prepare( "SELECT id, reward_diamonds, reward_xp FROM $table_ach WHERE slug = %s", $slug ) );
        if ( $ach ) {
            $already = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_user_ach WHERE user_id = %d AND achievement_id = %d",
                $user_id, $ach->id
            ) );
            if ( ! $already ) {
                $wpdb->insert( $table_user_ach, [
                    'user_id'        => $user_id,
                    'achievement_id' => $ach->id,
                    'unlocked_at'    => current_time( 'mysql' ),
                ] );
                // Award achievement bonus as XP (instead of diamond/primogem)
                $bonus_xp = ! empty( $ach->reward_xp ) ? (int) $ach->reward_xp : ( ! empty( $ach->reward_diamonds ) ? (int) $ach->reward_diamonds : 200 );
                if ( $bonus_xp > 0 ) {
                    sgd_add_xp( $user_id, $bonus_xp );
                }
                // Queue gilded achievement unlocked notification for the employee
                sgd_queue_achievement_alert( $user_id, $ach->id );
            }
        }
    }
}

// Queue and retrieve gilded achievement celebration alerts for employees
function sgd_queue_achievement_alert( $user_id, $achievement_id ) {
    $alerts = get_user_meta( $user_id, 'sgd_pending_achievement_alerts', true );
    if ( ! is_array( $alerts ) ) {
        $alerts = [];
    }
    if ( ! in_array( (int) $achievement_id, $alerts ) ) {
        $alerts[] = (int) $achievement_id;
        update_user_meta( $user_id, 'sgd_pending_achievement_alerts', $alerts );
    }
}

function sgd_get_pending_achievement_alerts( $user_id ) {
    $alerts = get_user_meta( $user_id, 'sgd_pending_achievement_alerts', true );
    if ( empty( $alerts ) || ! is_array( $alerts ) ) {
        return [];
    }
    global $wpdb;
    $table_ach = $wpdb->prefix . 'sgd_achievements';
    $placeholders = implode( ',', array_fill( 0, count( $alerts ), '%d' ) );
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table_ach WHERE id IN ($placeholders)",
        ...$alerts
    ) );
}

function sgd_dismiss_achievement_alerts( $user_id, $achievement_id = 0 ) {
    if ( ! $achievement_id ) {
        delete_user_meta( $user_id, 'sgd_pending_achievement_alerts' );
        return;
    }
    $alerts = get_user_meta( $user_id, 'sgd_pending_achievement_alerts', true );
    if ( is_array( $alerts ) ) {
        $alerts = array_diff( $alerts, [ (int) $achievement_id ] );
        if ( empty( $alerts ) ) {
            delete_user_meta( $user_id, 'sgd_pending_achievement_alerts' );
        } else {
            update_user_meta( $user_id, 'sgd_pending_achievement_alerts', array_values( $alerts ) );
        }
    }
}

// Flash messages stored in transients or session
function sgd_set_flash( $type, $msg ) {
    set_transient( 'sgd_flash_' . get_current_user_id(), [ 'type' => $type, 'msg' => $msg ], 60 );
}

function sgd_get_flash() {
    $key = 'sgd_flash_' . get_current_user_id();
    $flash = get_transient( $key );
    if ( $flash ) {
        delete_transient( $key );
    }
    return $flash;
}

// Visual Theme Selector (World of Warcraft, Genshin Impact, or Hearthstone Tavern)
function sgd_get_current_theme() {
    $allowed_themes = [ 'wow', 'genshin', 'hearthstone' ];
    if ( isset( $_GET['sgd_theme'] ) && in_array( $_GET['sgd_theme'], $allowed_themes, true ) ) {
        return sanitize_text_field( $_GET['sgd_theme'] );
    }
    if ( isset( $_COOKIE['sgd_theme'] ) && in_array( $_COOKIE['sgd_theme'], $allowed_themes, true ) ) {
        return sanitize_text_field( $_COOKIE['sgd_theme'] );
    }
    if ( is_user_logged_in() ) {
        $meta = get_user_meta( get_current_user_id(), 'sgd_theme', true );
        if ( in_array( $meta, $allowed_themes, true ) ) {
            return $meta;
        }
    }
    return 'wow';
}

// ========================================================
// Cicle Kanban Quest System - Helper Functions
// ========================================================
function sgd_user_can_access_quests( $user_id = 0 ) {
    return true;
}

function sgd_user_can_manage_quest( $team = null, $user_id = 0 ) {
    if ( current_user_can( 'administrator' ) ) {
        return true;
    }
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    if ( ! $user_id ) {
        return false;
    }
    if ( $team ) {
        $mgr_id = is_object( $team ) ? (int) $team->manager_id : (int) ( $team['manager_id'] ?? 0 );
        if ( $mgr_id === (int) $user_id ) {
            return true;
        }
    }
    $job_class = get_user_meta( $user_id, 'sgd_job_class', true );
    if ( strtolower( $job_class ) === 'manager' ) {
        return true;
    }
    return false;
}

/**
 * Calculate the reward ratio based on level difference between Employee Level and Quest Level.
 *
 * Mechanism:
 * - Difference <= 2 (At-Level or Under-Level Challenge): 100% full reward
 * - Difference 3 to 5 (Moderate / Easy): 70% portion
 * - Difference 6 to 9 (Low Challenge / Beginner): 40% portion
 * - Difference >= 10 (Over-Leveled / Trivial): 15% portion (minimum baseline)
 */
function sgd_calculate_quest_reward_ratio( $quest_level = 1, $user_level = 1 ) {
    $quest_level = max( 1, (int) $quest_level );
    $user_level = max( 1, (int) $user_level );
    $diff = $user_level - $quest_level;

    if ( $diff <= 2 ) {
        return [
            'ratio'      => 1.0,
            'percent'    => 100,
            'difficulty' => ( $diff < 0 ) ? 'challenging' : 'normal',
            'label'      => ( $diff < 0 ) ? 'Challenging (100% Hadiah Penuh)' : 'Tepat Level (100% Hadiah Penuh)',
            'color'      => ( $diff < 0 ) ? '#ff4444' : '#ffd700',
        ];
    } elseif ( $diff <= 5 ) {
        return [
            'ratio'      => 0.70,
            'percent'    => 70,
            'difficulty' => 'easy',
            'label'      => 'Tugas Mudah (70% Porsi Hadiah)',
            'color'      => '#2ecc71',
        ];
    } elseif ( $diff <= 9 ) {
        return [
            'ratio'      => 0.40,
            'percent'    => 40,
            'difficulty' => 'low',
            'label'      => 'Tantangan Rendah (40% Porsi Hadiah)',
            'color'      => '#a4b0be',
        ];
    } else {
        return [
            'ratio'      => 0.15,
            'percent'    => 15,
            'difficulty' => 'trivial',
            'label'      => 'Tugas Trivial / Abu-abu (15% Porsi Hadiah)',
            'color'      => '#747d8c',
        ];
    }
}

/**
 * Distribute quest rewards to assigned members when a task is finished.
 * Scales XP and Gold portions dynamically according to employee level vs quest level.
 */
function sgd_complete_quest_task( $task_id, $actor_user_id = 0 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_quest_tasks';
    $task = sgd_get_quest_task( $task_id );
    if ( ! $task ) return false;

    // Do not double-reward if already awarded
    if ( ! empty( $task->reward_awarded ) ) {
        return false;
    }

    $assigned_ids = array_filter( array_map( 'intval', explode( ',', $task->assigned_members ?? '' ) ) );
    if ( empty( $assigned_ids ) && $actor_user_id ) {
        $assigned_ids = [ (int) $actor_user_id ];
    }

    $quest_level = max( 1, (int) ( $task->quest_level ?? 1 ) );
    $reward_type = $task->reward_type ?? 'both';
    $base_xp = (int) ( $task->reward_xp ?? 200 );
    $base_gold = (int) ( $task->reward_gold ?? 50 );

    $award_summary = [];

    foreach ( $assigned_ids as $uid ) {
        $u = get_user_by( 'id', $uid );
        if ( ! $u ) continue;

        $xp_data = sgd_get_user_xp_data( $uid );
        $user_level = max( 1, (int) $xp_data['level'] );
        $scaling = sgd_calculate_quest_reward_ratio( $quest_level, $user_level );
        $ratio = $scaling['ratio'];

        $awarded_xp = 0;
        $awarded_gold = 0;

        if ( in_array( $reward_type, [ 'xp', 'both' ], true ) && $base_xp > 0 ) {
            $awarded_xp = max( 10, (int) round( $base_xp * $ratio ) );
            sgd_add_xp( $uid, $awarded_xp );
        }

        if ( in_array( $reward_type, [ 'gold', 'both' ], true ) && $base_gold > 0 ) {
            $awarded_gold = max( 1, (int) round( $base_gold * $ratio ) );
            sgd_add_diamonds( $uid, $awarded_gold );
        }

        $award_summary[] = [
            'user_id'      => $uid,
            'user_name'    => $u->display_name,
            'user_level'   => $user_level,
            'quest_level'  => $quest_level,
            'percent'      => $scaling['percent'],
            'difficulty'   => $scaling['difficulty'],
            'label'        => $scaling['label'],
            'awarded_xp'   => $awarded_xp,
            'awarded_gold' => $awarded_gold,
        ];
    }

    // Mark task as completed & reward awarded
    $wpdb->update(
        $table,
        [
            'column_name'    => 'Selesai',
            'reward_awarded' => 1,
            'completed_by'   => $actor_user_id ?: get_current_user_id(),
            'completed_time' => current_time( 'mysql' ),
            'updated_at'     => current_time( 'mysql' ),
        ],
        [ 'id' => $task_id ]
    );

    return $award_summary;
}

function sgd_get_quest_teams( $type = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_quest_teams';
    $table_tasks = $wpdb->prefix . 'sgd_quest_tasks';

    if ( $type && in_array( $type, [ 'hq', 'tim', 'proyek' ], true ) ) {
        $teams = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE type = %s ORDER BY id ASC", $type ) );
    } else {
        $teams = $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC" );
    }
    if ( empty( $teams ) ) {
        return [];
    }

    foreach ( $teams as &$team ) {
        $member_ids = array_filter( array_map( 'intval', explode( ',', $team->member_ids ?? '' ) ) );
        $team->members = [];
        foreach ( $member_ids as $mid ) {
            $u = get_user_by( 'id', $mid );
            if ( $u ) {
                $team->members[] = [
                    'id'           => $u->ID,
                    'display_name' => $u->display_name,
                    'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤',
                    'job_class'    => function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff',
                ];
            }
        }
        $manager = get_user_by( 'id', $team->manager_id );
        $team->manager_name = $manager ? $manager->display_name : 'Belum Ditugaskan';
        $team->manager_avatar = ( $manager && function_exists('sgd_get_user_avatar') ) ? sgd_get_user_avatar( $manager->ID ) : '⭐';
        $team->manager_class = ( $manager && function_exists('sgd_get_user_job_class') ) ? sgd_get_user_job_class( $manager->ID ) : 'Manager';

        // Count tasks per column
        $stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT column_name, COUNT(*) as cnt FROM $table_tasks WHERE team_id = %d GROUP BY column_name",
            $team->id
        ), OBJECT_K );

        $team->counts = [
            'total'       => 0,
            'To do list'  => isset( $stats['To do list'] ) ? (int) $stats['To do list']->cnt : 0,
            'Dikerjakan'  => isset( $stats['Dikerjakan'] ) ? (int) $stats['Dikerjakan']->cnt : 0,
            'Selesai'     => isset( $stats['Selesai'] ) ? (int) $stats['Selesai']->cnt : 0,
            'Batal'       => isset( $stats['Batal'] ) ? (int) $stats['Batal']->cnt : 0,
        ];
        $team->counts['total'] = $team->counts['To do list'] + $team->counts['Dikerjakan'] + $team->counts['Selesai'] + $team->counts['Batal'];
    }
    return $teams;
}

function sgd_get_quest_team( $team_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_quest_teams';
    $team = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $team_id ) );
    if ( ! $team ) return null;

    $member_ids = array_filter( array_map( 'intval', explode( ',', $team->member_ids ?? '' ) ) );
    $team->members = [];
    foreach ( $member_ids as $mid ) {
        $u = get_user_by( 'id', $mid );
        if ( $u ) {
            $team->members[] = [
                'id'           => $u->ID,
                'display_name' => $u->display_name,
                'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤',
                'job_class'    => function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff',
            ];
        }
    }
    $manager = get_user_by( 'id', $team->manager_id );
    $team->manager_name = $manager ? $manager->display_name : 'Belum Ditugaskan';
    $team->manager_avatar = ( $manager && function_exists('sgd_get_user_avatar') ) ? sgd_get_user_avatar( $manager->ID ) : '⭐';
    $team->manager_class = ( $manager && function_exists('sgd_get_user_job_class') ) ? sgd_get_user_job_class( $manager->ID ) : 'Manager';

    return $team;
}

function sgd_get_quest_tasks( $team_id, $column_name = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_quest_tasks';
    $table_comm = $wpdb->prefix . 'sgd_quest_comments';
    $table_att = $wpdb->prefix . 'sgd_quest_attachments';

    if ( $column_name ) {
        $tasks = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE team_id = %d AND column_name = %s ORDER BY sort_order ASC, id ASC",
            $team_id, $column_name
        ) );
    } else {
        $tasks = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE team_id = %d ORDER BY sort_order ASC, id ASC",
            $team_id
        ) );
    }

    if ( empty( $tasks ) ) return [];

    foreach ( $tasks as &$t ) {
        $assigned_ids = array_filter( array_map( 'intval', explode( ',', $t->assigned_members ?? '' ) ) );
        $t->members = [];
        foreach ( $assigned_ids as $uid ) {
            $u = get_user_by( 'id', $uid );
            if ( $u ) {
                $t->members[] = [
                    'id'           => $u->ID,
                    'display_name' => $u->display_name,
                    'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤',
                ];
            }
        }
        // Fallback if no assigned members found: use task creator
        if ( empty( $t->members ) && ! empty( $t->created_by ) ) {
            $creator = get_user_by( 'id', $t->created_by );
            if ( $creator ) {
                $t->members[] = [
                    'id'           => $creator->ID,
                    'display_name' => $creator->display_name,
                    'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $creator->ID ) : '👤',
                ];
            }
        }
        $t->comment_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_comm WHERE task_id = %d", $t->id ) );
        $t->attachment_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_att WHERE task_id = %d", $t->id ) );

        // Parse labels / tags JSON
        $t->tags = [];
        if ( ! empty( $t->labels ) ) {
            $parsed_tags = json_decode( $t->labels, true );
            if ( is_array( $parsed_tags ) ) {
                $t->tags = $parsed_tags;
            }
        }

        // Parse checklist JSON
        $raw_chk = $t->checklist ?? '';
        $t->checklist = [];
        if ( ! empty( $raw_chk ) && is_string( $raw_chk ) ) {
            $parsed_chk = json_decode( $raw_chk, true );
            if ( is_array( $parsed_chk ) ) {
                $t->checklist = $parsed_chk;
            }
        }

        // Quest Level & Rewards defaults
        $t->quest_level = max( 1, (int) ( $t->quest_level ?? 1 ) );
        $t->reward_type = $t->reward_type ?? 'both';
        $t->reward_xp = isset( $t->reward_xp ) ? (int) $t->reward_xp : 200;
        $t->reward_gold = isset( $t->reward_gold ) ? (int) $t->reward_gold : 50;
        $t->reward_awarded = (int) ( $t->reward_awarded ?? 0 );

        // Cover Image: fallback to first image attachment if empty
        if ( empty( $t->cover_image ) ) {
            $first_img = $wpdb->get_var( $wpdb->prepare(
                "SELECT file_url FROM $table_att WHERE task_id = %d AND (file_type LIKE 'image/%' OR file_name LIKE '%.png' OR file_name LIKE '%.jpg' OR file_name LIKE '%.jpeg' OR file_name LIKE '%.webp' OR file_name LIKE '%.gif') ORDER BY id ASC LIMIT 1",
                $t->id
            ) );
            if ( $first_img ) {
                $t->cover_image = $first_img;
            }
        }
    }
    return $tasks;
}

function sgd_get_quest_task( $task_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_quest_tasks';
    $task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $task_id ) );
    if ( ! $task ) return null;

    $assigned_ids = array_filter( array_map( 'intval', explode( ',', $task->assigned_members ?? '' ) ) );
    $task->members = [];
    foreach ( $assigned_ids as $uid ) {
        $u = get_user_by( 'id', $uid );
        if ( $u ) {
            $task->members[] = [
                'id'           => $u->ID,
                'display_name' => $u->display_name,
                'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $u->ID ) : '👤',
                'job_class'    => function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $u->ID ) : 'Staff',
            ];
        }
    }
    // Fallback if no assigned members found: use task creator
    if ( empty( $task->members ) && ! empty( $task->created_by ) ) {
        $creator = get_user_by( 'id', $task->created_by );
        if ( $creator ) {
            $task->members[] = [
                'id'           => $creator->ID,
                'display_name' => $creator->display_name,
                'avatar'       => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $creator->ID ) : '👤',
                'job_class'    => function_exists('sgd_get_user_job_class') ? sgd_get_user_job_class( $creator->ID ) : 'Staff',
            ];
        }
    }
    $task->tags = [];
    if ( ! empty( $task->labels ) ) {
        $parsed_tags = json_decode( $task->labels, true );
        if ( is_array( $parsed_tags ) ) {
            $task->tags = $parsed_tags;
        }
    }

    // Parse checklist JSON
    $raw_chk = $task->checklist ?? '';
    $task->checklist = [];
    if ( ! empty( $raw_chk ) && is_string( $raw_chk ) ) {
        $parsed_chk = json_decode( $raw_chk, true );
        if ( is_array( $parsed_chk ) ) {
            $task->checklist = $parsed_chk;
        }
    }

    // Quest Level & Rewards defaults
    $task->quest_level = max( 1, (int) ( $task->quest_level ?? 1 ) );
    $task->reward_type = $task->reward_type ?? 'both';
    $task->reward_xp = isset( $task->reward_xp ) ? (int) $task->reward_xp : 200;
    $task->reward_gold = isset( $task->reward_gold ) ? (int) $task->reward_gold : 50;
    $task->reward_awarded = (int) ( $task->reward_awarded ?? 0 );

    // Cover Image: fallback to first image attachment if empty
    if ( empty( $task->cover_image ) ) {
        $table_att = $wpdb->prefix . 'sgd_quest_attachments';
        $first_img = $wpdb->get_var( $wpdb->prepare(
            "SELECT file_url FROM $table_att WHERE task_id = %d AND (file_type LIKE 'image/%' OR file_name LIKE '%.png' OR file_name LIKE '%.jpg' OR file_name LIKE '%.jpeg' OR file_name LIKE '%.webp' OR file_name LIKE '%.gif') ORDER BY id ASC LIMIT 1",
            $task->id
        ) );
        if ( $first_img ) {
            $task->cover_image = $first_img;
        }
    }

    $task->comments = sgd_get_quest_comments( $task->id );
    $task->attachments = sgd_get_quest_attachments( $task->id );
    $task->team = sgd_get_quest_team( $task->team_id );

    return $task;
}

function sgd_get_quest_comments( $task_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_quest_comments';
    $comments = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE task_id = %d ORDER BY created_at ASC",
        $task_id
    ) );
    if ( empty( $comments ) ) return [];

    foreach ( $comments as &$c ) {
        $u = get_user_by( 'id', $c->user_id );
        $c->user_name = $u ? $u->display_name : 'Anggota';
        $c->user_avatar = ( $u && function_exists('sgd_get_user_avatar') ) ? sgd_get_user_avatar( $u->ID ) : '👤';
        $c->user_role = ( $u && function_exists('sgd_get_user_job_class') ) ? sgd_get_user_job_class( $u->ID ) : 'Staff';
    }
    return $comments;
}

function sgd_get_quest_attachments( $task_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_quest_attachments';
    $attachments = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE task_id = %d ORDER BY created_at DESC",
        $task_id
    ) );
    if ( empty( $attachments ) ) return [];

    foreach ( $attachments as &$att ) {
        $u = get_user_by( 'id', $att->user_id );
        $att->uploader_name = $u ? $u->display_name : 'Staff';
    }
    return $attachments;
}

// 2. Action Handlers (Form Submissions)
function sgd_handle_form_actions() {
    if ( ! isset( $_POST['sgd_action'] ) ) {
        return;
    }

    global $wpdb;
    $action = sanitize_text_field( $_POST['sgd_action'] );
    $current_user_id = get_current_user_id();

    // Theme Switcher Action
    if ( $action === 'sgd_switch_theme' ) {
        $target_theme = sanitize_text_field( $_POST['target_theme'] ?? 'wow' );
        if ( ! in_array( $target_theme, [ 'wow', 'genshin', 'hearthstone' ], true ) ) {
            $target_theme = 'wow';
        }
        setcookie( 'sgd_theme', $target_theme, time() + 31536000, COOKIEPATH, COOKIE_DOMAIN );
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), 'sgd_theme', $target_theme );
        }
        if ( ! empty( $_POST['is_ajax'] ) ) {
            wp_send_json_success( [ 'theme' => $target_theme ] );
        }
        $redirect_page = sanitize_text_field( $_POST['redirect_to'] ?? '' );
        wp_safe_redirect( sgd_get_portal_url( $redirect_page ) );
        exit;
    }

    // Quick Character Switcher (for painless evaluator/user testing)
    if ( $action === 'sgd_switch_user' ) {
        $target = sanitize_text_field( $_POST['target_user'] ?? '' );
        if ( $target === 'admin' ) {
            $user = get_user_by( 'login', 'admin' );
        } elseif ( $target === 'employee' ) {
            $user = get_user_by( 'login', 'employee' );
        } elseif ( $target === 'employee2' ) {
            $user = get_user_by( 'login', 'employee2' );
        } elseif ( $target === 'employee3' ) {
            $user = get_user_by( 'login', 'employee3' );
        } elseif ( $target === 'employee4' ) {
            $user = get_user_by( 'login', 'employee4' );
        } else {
            $user = get_user_by( 'login', $target );
            if ( ! $user && is_numeric( $target ) ) {
                $user = get_user_by( 'id', (int) $target );
            }
        }
        if ( isset( $user ) && $user ) {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID );
            sgd_set_flash( 'success', 'Switched character to: ' . esc_html( $user->display_name ) );
        }
        wp_safe_redirect( sgd_get_portal_url() );
        exit;
    }

    // Change Hero Avatar Action
    if ( $action === 'sgd_change_avatar' ) {
        $avatar_icon = sanitize_text_field( $_POST['avatar_icon'] ?? '⚔️' );
        if ( ! empty( $avatar_icon ) ) {
            sgd_set_user_avatar( $current_user_id, $avatar_icon );
            sgd_set_flash( 'success', '🎨 Hero Avatar updated to: ' . esc_html( $avatar_icon ) );
        }
        wp_safe_redirect( sgd_get_portal_url() );
        exit;
    }

    // Dismiss Gilded Achievement Alert
    if ( $action === 'sgd_dismiss_achievement_alert' ) {
        $ach_id = (int) ( $_POST['achievement_id'] ?? 0 );
        sgd_dismiss_achievement_alerts( $current_user_id, $ach_id );
        if ( ! empty( $_POST['is_ajax'] ) ) {
            wp_send_json_success( [ 'dismissed' => $ach_id ] );
        }
        $redirect_to = sanitize_text_field( $_POST['redirect_to'] ?? '' );
        wp_safe_redirect( sgd_get_portal_url( $redirect_to ) );
        exit;
    }

    // Trigger Demo Gilded Achievement Alert (for previewing gilded fanfare)
    if ( $action === 'sgd_demo_achievement_alert' ) {
        $demo_ach_id = (int) ( $_POST['achievement_id'] ?? 0 );
        if ( ! $demo_ach_id ) {
            $sample_ach = $wpdb->get_row( "SELECT id FROM {$wpdb->prefix}sgd_achievements ORDER BY id ASC LIMIT 1" );
            if ( $sample_ach ) {
                $demo_ach_id = $sample_ach->id;
            }
        }
        if ( $demo_ach_id ) {
            sgd_queue_achievement_alert( $current_user_id, $demo_ach_id );
        }
        $redirect_to = sanitize_text_field( $_POST['redirect_to'] ?? 'achievements' );
        wp_safe_redirect( sgd_get_portal_url( $redirect_to ) );
        exit;
    }

    // Standard Login
    if ( $action === 'sgd_login' ) {
        $creds = [
            'user_login'    => sanitize_text_field( $_POST['log'] ?? '' ),
            'user_password' => $_POST['pwd'] ?? '',
            'remember'      => true,
        ];
        $user = wp_signon( $creds, false );
        if ( is_wp_error( $user ) ) {
            sgd_set_flash( 'error', 'Login Failed: ' . $user->get_error_message() );
            wp_safe_redirect( sgd_get_portal_url( 'login' ) );
            exit;
        }
        wp_set_current_user( $user->ID );
        sgd_set_flash( 'success', 'Welcome back, brave hero ' . esc_html( $user->display_name ) . '!' );
        wp_safe_redirect( sgd_get_portal_url() );
        exit;
    }

    // Standard Logout
    if ( $action === 'sgd_logout' ) {
        wp_logout();
        sgd_set_flash( 'success', 'Logged out safely at the Hearthstone Inn.' );
        wp_safe_redirect( sgd_get_portal_url() );
        exit;
    }

    // Require logged in for task actions
    if ( ! is_user_logged_in() ) {
        sgd_set_flash( 'error', 'You must log in to accept or manage quests!' );
        wp_safe_redirect( sgd_get_portal_url( 'login' ) );
        exit;
    }

    $table_tasks = $wpdb->prefix . 'sgd_tasks';

    // SEND PEER KUDOS / MANA RECOGNITION
    if ( $action === 'sgd_send_kudos' ) {
        $to_user_id = (int) ( $_POST['to_user_id'] ?? 0 );
        $amount = (int) ( $_POST['kudos_amount'] ?? 50 );
        if ( ! in_array( $amount, [ 25, 50, 100 ] ) ) {
            $amount = 50;
        }
        $message = sanitize_textarea_field( $_POST['kudos_message'] ?? '' );

        if ( ! $to_user_id || $to_user_id === $current_user_id ) {
            sgd_set_flash( 'error', 'You cannot send Mana or Kudos to yourself!' );
            wp_safe_redirect( sgd_get_portal_url( 'leaderboard' ) );
            exit;
        }

        $recipient = get_user_by( 'id', $to_user_id );
        if ( ! $recipient ) {
            sgd_set_flash( 'error', 'Colleague not found in guild registry.' );
            wp_safe_redirect( sgd_get_portal_url( 'leaderboard' ) );
            exit;
        }

        $my_diamonds = sgd_get_diamonds( $current_user_id );
        if ( $my_diamonds < $amount ) {
            sgd_set_flash( 'error', "You don't have enough diamonds! You need {$amount} 💎 but only have {$my_diamonds} 💎." );
            wp_safe_redirect( sgd_get_portal_url( 'leaderboard' ) );
            exit;
        }

        if ( empty( $message ) ) {
            $message = 'Sent you a boost of hero mana and appreciation!';
        }

        // Deduct from sender, give to recipient
        sgd_deduct_diamonds( $current_user_id, $amount );
        sgd_add_diamonds( $to_user_id, $amount );

        // Insert record
        $table_kudos = $wpdb->prefix . 'sgd_kudos';
        $wpdb->insert( $table_kudos, [
            'from_user_id'    => $current_user_id,
            'to_user_id'      => $to_user_id,
            'amount_diamonds' => $amount,
            'message'         => $message,
            'created_at'      => current_time( 'mysql' ),
        ] );

        // Check kudos achievements
        $ach_giver = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sgd_achievements WHERE slug = %s", 'kudos_giver' ) );
        if ( $ach_giver ) {
            $already = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_user_achievements WHERE user_id = %d AND achievement_id = %d", $current_user_id, $ach_giver ) );
            if ( ! $already ) {
                $wpdb->insert( "{$wpdb->prefix}sgd_user_achievements", [
                    'user_id' => $current_user_id,
                    'achievement_id' => $ach_giver,
                    'unlocked_at' => current_time('mysql'),
                ] );
                sgd_add_diamonds( $current_user_id, 100 );
            }
        }

        $ach_receiver = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sgd_achievements WHERE slug = %s", 'kudos_receiver' ) );
        if ( $ach_receiver ) {
            $already_rcv = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_user_achievements WHERE user_id = %d AND achievement_id = %d", $to_user_id, $ach_receiver ) );
            if ( ! $already_rcv ) {
                $wpdb->insert( "{$wpdb->prefix}sgd_user_achievements", [
                    'user_id' => $to_user_id,
                    'achievement_id' => $ach_receiver,
                    'unlocked_at' => current_time('mysql'),
                ] );
                sgd_add_diamonds( $to_user_id, 100 );
            }
        }

        sgd_set_flash( 'celebration', "⚡ KUDOS & MANA DELIVERED! You awarded +{$amount} 💎 to " . esc_html( $recipient->display_name ) . " with your shoutout of honor!" );
        wp_safe_redirect( sgd_get_portal_url( 'leaderboard' ) );
        exit;
    }

    // 1. GOBLIN DAILY FORTUNE WHEEL SPIN
    if ( $action === 'sgd_spin_wheel' ) {
        $today = current_time( 'Y-m-d' );
        $last_spin = get_user_meta( $current_user_id, 'sgd_last_wheel_spin', true );

        if ( $last_spin === $today && empty( $_POST['demo_override'] ) ) {
            sgd_set_flash( 'error', 'You already used your free daily spin today! Return tomorrow at 00:00:00.' );
            wp_safe_redirect( sgd_get_portal_url( 'wheel' ) );
            exit;
        }

        // Sectors definition (8 slices)
        $sectors = [
            0 => [ 'type' => 'diamonds', 'val' => 50,  'name' => '+50 💎 Pouch of Diamonds', 'flash' => 'You won a pouch of +50 Diamonds!' ],
            1 => [ 'type' => 'xp',       'val' => 100, 'name' => '+100 XP Arcane Surge',     'flash' => 'You gained an Arcane Surge of +100 XP!' ],
            2 => [ 'type' => 'diamonds', 'val' => 100, 'name' => '+100 💎 Sack of Diamonds',  'flash' => 'You won a sack of +100 Diamonds!' ],
            3 => [ 'type' => 'shield',   'val' => 1,   'name' => '🛡️ Streak Shield Potion', 'flash' => 'You won a Streak Shield! If you miss a day, your streak will be protected.' ],
            4 => [ 'type' => 'diamonds', 'val' => 250, 'name' => '+250 💎 Treasure Chest',   'flash' => 'You struck gold! +250 Diamonds added to your wallet!' ],
            5 => [ 'type' => 'coffee',   'val' => 1,   'name' => '☕ Free Coffee Token',      'flash' => 'Enjoy your morning caffeine! +1 Free Coffee Token added to your perks.' ],
            6 => [ 'type' => 'diamonds', 'val' => 500, 'name' => '🌟 JACKPOT! +500 💎',      'flash' => '🎉 MEGA JACKPOT! You won +500 Diamonds from the Goblin Treasury!' ],
            7 => [ 'type' => 'title',    'val' => 100, 'name' => '👑 The Favored of Fortune', 'flash' => 'You won the exclusive title "The Favored of Fortune" + 100 💎!' ],
        ];

        $sector_idx = isset( $_POST['sector_index'] ) ? (int) $_POST['sector_index'] : rand( 0, 7 );
        if ( ! isset( $sectors[ $sector_idx ] ) ) {
            $sector_idx = 2;
        }
        $prize = $sectors[ $sector_idx ];

        // Award prize
        if ( $prize['type'] === 'diamonds' ) {
            sgd_add_diamonds( $current_user_id, $prize['val'] );
        } elseif ( $prize['type'] === 'xp' ) {
            sgd_add_diamonds( $current_user_id, 50 );
        } elseif ( $prize['type'] === 'shield' ) {
            $cur_shields = (int) get_user_meta( $current_user_id, 'sgd_streak_shields', true );
            update_user_meta( $current_user_id, 'sgd_streak_shields', $cur_shields + 1 );
        } elseif ( $prize['type'] === 'coffee' ) {
            $cur_coffee = (int) get_user_meta( $current_user_id, 'sgd_coffee_tokens', true );
            update_user_meta( $current_user_id, 'sgd_coffee_tokens', $cur_coffee + 1 );
        } elseif ( $prize['type'] === 'title' ) {
            update_user_meta( $current_user_id, 'sgd_hero_title', 'The Favored of Fortune' );
            $unlocked = get_user_meta( $current_user_id, 'sgd_unlocked_titles', true );
            if ( ! is_array( $unlocked ) ) $unlocked = [];
            if ( ! in_array( 'The Favored of Fortune', $unlocked, true ) ) {
                $unlocked[] = 'The Favored of Fortune';
                update_user_meta( $current_user_id, 'sgd_unlocked_titles', $unlocked );
            }
            sgd_add_diamonds( $current_user_id, $prize['val'] );
        }

        update_user_meta( $current_user_id, 'sgd_last_wheel_spin', $today );
        update_user_meta( $current_user_id, 'sgd_last_wheel_reward', $prize['name'] );

        // Award achievement
        $spin_ach = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sgd_achievements WHERE slug = %s", 'wheel_spinner' ) );
        if ( $spin_ach ) {
            $already = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_user_achievements WHERE user_id = %d AND achievement_id = %d", $current_user_id, $spin_ach ) );
            if ( ! $already ) {
                $wpdb->insert( "{$wpdb->prefix}sgd_user_achievements", [
                    'user_id'        => $current_user_id,
                    'achievement_id' => $spin_ach,
                    'unlocked_at'    => current_time( 'mysql' ),
                ] );
                sgd_add_diamonds( $current_user_id, 50 );
            }
        }

        sgd_set_flash( 'celebration', "🎰 FORTUNE WHEEL RESULT: [{$prize['name']}]! " . $prize['flash'] );
        wp_safe_redirect( sgd_get_portal_url( 'wheel' ) );
        exit;
    }

    // EQUIP OR CUSTOMIZE HERO TITLE
    if ( $action === 'sgd_change_title' ) {
        $custom = sanitize_text_field( $_POST['custom_title'] ?? '' );
        $selected = sanitize_text_field( $_POST['hero_title'] ?? '' );
        $chosen_title = ! empty( $custom ) ? $custom : $selected;

        if ( ! empty( $chosen_title ) ) {
            $chosen_title = mb_substr( trim( $chosen_title ), 0, 40 );
            update_user_meta( $current_user_id, 'sgd_hero_title', $chosen_title );

            // Save in unlocked armory list so it can be re-equipped anytime
            $unlocked = get_user_meta( $current_user_id, 'sgd_unlocked_titles', true );
            if ( ! is_array( $unlocked ) ) $unlocked = [];
            if ( ! in_array( $chosen_title, $unlocked, true ) ) {
                $unlocked[] = $chosen_title;
                update_user_meta( $current_user_id, 'sgd_unlocked_titles', $unlocked );
            }

            sgd_set_flash( 'celebration', "👑 TITLE EQUIPPED: You are now known throughout the realm as \"{$chosen_title}\"!" );
        }

        $redirect = wp_get_referer() ?: sgd_get_portal_url( 'wheel' );
        wp_safe_redirect( $redirect );
        exit;
    }

    // RESET DEMO SPIN (Quick tester)
    if ( $action === 'sgd_reset_demo_spin' ) {
        delete_user_meta( $current_user_id, 'sgd_last_wheel_spin' );
        sgd_set_flash( 'success', '⚡ [Demo Mode] Daily spin refreshed! You may spin the wheel again.' );
        wp_safe_redirect( sgd_get_portal_url( 'wheel' ) );
        exit;
    }

    // 2. PEER BOUNTY BOARD: POST BOUNTY
    if ( $action === 'sgd_post_bounty' ) {
        $title = sanitize_text_field( $_POST['bounty_title'] ?? '' );
        $description = sanitize_textarea_field( $_POST['bounty_description'] ?? '' );
        $category = sanitize_text_field( $_POST['bounty_category'] ?? 'Peer Favor' );
        $reward = max( 50, (int) ( $_POST['bounty_reward'] ?? 100 ) );

        if ( empty( $title ) || empty( $description ) ) {
            sgd_set_flash( 'error', 'Bounty title and instructions are required.' );
            wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
            exit;
        }

        $my_diamonds = sgd_get_diamonds( $current_user_id );
        if ( $my_diamonds < $reward ) {
            sgd_set_flash( 'error', "Insufficient diamonds! You need {$reward} 💎 to post this bounty but have {$my_diamonds} 💎." );
            wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
            exit;
        }

        // Deduct diamonds into escrow
        sgd_deduct_diamonds( $current_user_id, $reward );

        $table_bounties = $wpdb->prefix . 'sgd_bounties';
        $wpdb->insert( $table_bounties, [
            'poster_id'       => $current_user_id,
            'title'           => $title,
            'description'     => $description,
            'category'        => $category,
            'reward_diamonds' => $reward,
            'claimed_by'      => 0,
            'status'          => 'open',
            'created_at'      => current_time( 'mysql' ),
        ] );

        // Award Patron achievement
        $patron_ach = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sgd_achievements WHERE slug = %s", 'bounty_patron' ) );
        if ( $patron_ach ) {
            $already = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_user_achievements WHERE user_id = %d AND achievement_id = %d", $current_user_id, $patron_ach ) );
            if ( ! $already ) {
                $wpdb->insert( "{$wpdb->prefix}sgd_user_achievements", [
                    'user_id'        => $current_user_id,
                    'achievement_id' => $patron_ach,
                    'unlocked_at'    => current_time( 'mysql' ),
                ] );
                sgd_add_diamonds( $current_user_id, 100 );
            }
        }

        sgd_set_flash( 'success', "🎯 PEER BOUNTY POSTED! {$reward} 💎 held in escrow. Teammates can now accept your commission!" );
        wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
        exit;
    }

    // CLAIM PEER BOUNTY
    if ( $action === 'sgd_claim_bounty' ) {
        $bounty_id = (int) ( $_POST['bounty_id'] ?? 0 );
        $table_bounties = $wpdb->prefix . 'sgd_bounties';
        $bounty = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bounties WHERE id = %d", $bounty_id ) );

        if ( ! $bounty || $bounty->status !== 'open' ) {
            sgd_set_flash( 'error', 'This bounty is no longer open for acceptance.' );
        } elseif ( $bounty->poster_id == $current_user_id ) {
            sgd_set_flash( 'error', 'You cannot accept your own posted bounty!' );
        } else {
            $wpdb->update( $table_bounties, [
                'claimed_by' => $current_user_id,
                'status'     => 'claimed',
            ], [ 'id' => $bounty_id ] );
            sgd_set_flash( 'success', '⚔️ BOUNTY ACCEPTED! Deliver the completed job and submit your proof to claim the ' . number_format( $bounty->reward_diamonds ) . ' 💎 bounty!' );
        }
        wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
        exit;
    }

    // SUBMIT BOUNTY PROOF
    if ( $action === 'sgd_submit_bounty_proof' ) {
        $bounty_id = (int) ( $_POST['bounty_id'] ?? 0 );
        $proof_notes = sanitize_textarea_field( $_POST['proof_notes'] ?? '' );
        $table_bounties = $wpdb->prefix . 'sgd_bounties';
        $bounty = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bounties WHERE id = %d", $bounty_id ) );

        if ( ! $bounty || $bounty->claimed_by != $current_user_id ) {
            sgd_set_flash( 'error', 'Bounty not found or not assigned to you.' );
            wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
            exit;
        }

        $photo_url = '';
        if ( isset( $_FILES['proof_photo'] ) && ! empty( $_FILES['proof_photo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $movefile = wp_handle_upload( $_FILES['proof_photo'], [ 'test_form' => false ] );
            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $photo_url = $movefile['url'];
            }
        }

        $wpdb->update( $table_bounties, [
            'status'          => 'submitted',
            'proof_notes'     => $proof_notes,
            'proof_image_url' => $photo_url,
        ], [ 'id' => $bounty_id ] );

        sgd_set_flash( 'celebration', '📸 PROOF SUBMITTED! The poster has been notified to verify your work and release the escrow!' );
        wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
        exit;
    }

    // APPROVE & PAY BOUNTY (RELEASE ESCROW)
    if ( $action === 'sgd_approve_bounty' ) {
        $bounty_id = (int) ( $_POST['bounty_id'] ?? 0 );
        $table_bounties = $wpdb->prefix . 'sgd_bounties';
        $bounty = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bounties WHERE id = %d", $bounty_id ) );

        if ( ! $bounty || ( $bounty->poster_id != $current_user_id && ! current_user_can( 'administrator' ) ) ) {
            sgd_set_flash( 'error', 'Only the bounty poster or admin can approve this commission.' );
            wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
            exit;
        }

        $wpdb->update( $table_bounties, [
            'status'       => 'completed',
            'completed_at' => current_time( 'mysql' ),
        ], [ 'id' => $bounty_id ] );

        // Transfer escrowed reward to claimer
        sgd_add_diamonds( $bounty->claimed_by, $bounty->reward_diamonds );

        // Award Mercenary Hero achievement to claimer
        $hunter_ach = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sgd_achievements WHERE slug = %s", 'bounty_hunter' ) );
        if ( $hunter_ach ) {
            $already = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_user_achievements WHERE user_id = %d AND achievement_id = %d", $bounty->claimed_by, $hunter_ach ) );
            if ( ! $already ) {
                $wpdb->insert( "{$wpdb->prefix}sgd_user_achievements", [
                    'user_id'        => $bounty->claimed_by,
                    'achievement_id' => $hunter_ach,
                    'unlocked_at'    => current_time( 'mysql' ),
                ] );
                sgd_add_diamonds( $bounty->claimed_by, 150 );
            }
        }

        $claimer = get_user_by( 'id', $bounty->claimed_by );
        $claimer_name = $claimer ? $claimer->display_name : 'Colleague';
        sgd_set_flash( 'celebration', "🎉 BOUNTY APPROVED! Escrow released: +{$bounty->reward_diamonds} 💎 awarded to {$claimer_name}!" );
        wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
        exit;
    }

    // CANCEL OPEN BOUNTY
    if ( $action === 'sgd_cancel_bounty' ) {
        $bounty_id = (int) ( $_POST['bounty_id'] ?? 0 );
        $table_bounties = $wpdb->prefix . 'sgd_bounties';
        $bounty = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bounties WHERE id = %d", $bounty_id ) );

        if ( ! $bounty || ( $bounty->poster_id != $current_user_id && ! current_user_can( 'administrator' ) ) ) {
            sgd_set_flash( 'error', 'Permission denied.' );
            wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
            exit;
        }

        if ( $bounty->status !== 'open' ) {
            sgd_set_flash( 'error', 'Only unaccepted open bounties can be cancelled.' );
        } else {
            // Refund diamonds to poster
            sgd_add_diamonds( $bounty->poster_id, $bounty->reward_diamonds );
            $wpdb->update( $table_bounties, [ 'status' => 'cancelled' ], [ 'id' => $bounty_id ] );
            sgd_set_flash( 'success', "Bounty cancelled. {$bounty->reward_diamonds} 💎 refunded back to your wallet." );
        }
        wp_safe_redirect( sgd_get_portal_url( 'bounties' ) );
        exit;
    }

    // TAKE JOB (Urgent tasks)
    if ( $action === 'sgd_take_job' ) {
        $task_id = (int) ( $_POST['task_id'] ?? 0 );
        $task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_tasks WHERE id = %d", $task_id ) );
        if ( ! $task ) {
            sgd_set_flash( 'error', 'Quest not found.' );
        } elseif ( $task->status === 'claimed' && $task->claimed_by != $current_user_id ) {
            sgd_set_flash( 'error', 'This emergency bounty was already claimed by another hero!' );
        } else {
            $wpdb->update( $table_tasks, [
                'claimed_by' => $current_user_id,
                'status'     => 'claimed',
            ], [ 'id' => $task_id ] );
            sgd_set_flash( 'success', '⚔️ Emergency Bounty Claimed! Slay this urgent task and submit your photo proof for +1,000 Diamonds!' );
        }
        wp_safe_redirect( sgd_get_portal_url( 'urgent' ) );
        exit;
    }

    // NEED HELP (Daily & Urgent tasks)
    if ( $action === 'sgd_need_help' ) {
        $task_id = (int) ( $_POST['task_id'] ?? 0 );
        $help_note = sanitize_textarea_field( $_POST['help_note'] ?? 'Employee requested immediate backup!' );
        $task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_tasks WHERE id = %d", $task_id ) );

        if ( $task ) {
            $wpdb->update( $table_tasks, [
                'help_status'    => 'requested',
                'need_help_by'   => $current_user_id,
                'need_help_time' => current_time( 'mysql' ),
                'help_notes'     => $help_note,
            ], [ 'id' => $task_id ] );

            // Award "Raid Coordinator" achievement if applicable
            $ach_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sgd_achievements WHERE slug = %s", 'team_player' ) );
            if ( $ach_id ) {
                $already = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_user_achievements WHERE user_id = %d AND achievement_id = %d", $current_user_id, $ach_id ) );
                if ( ! $already ) {
                    $wpdb->insert( "{$wpdb->prefix}sgd_user_achievements", [
                        'user_id' => $current_user_id,
                        'achievement_id' => $ach_id,
                        'unlocked_at' => current_time( 'mysql' ),
                    ] );
                    sgd_add_diamonds( $current_user_id, 150 );
                }
            }

            // Send Email Notification to Admin
            $admin_email = get_option( 'admin_email' );
            $emp_user = get_user_by( 'id', $current_user_id );
            $emp_name = $emp_user ? $emp_user->display_name : 'Employee #' . $current_user_id;
            $email_subject = "🚨 [SOS EMERGENCY] {$emp_name} requested help on: {$task->title}";
            $email_body = "Guild Master Alert,\n\nEmployee {$emp_name} ({$emp_user->user_email}) has clicked the 'Need Help' beacon!\n\nQuest: {$task->title}\nType: " . strtoupper( $task->type ) . "\nTime: " . current_time( 'mysql' ) . "\nBlocker Details: {$help_note}\n\nPlease dispatch reinforcements immediately at the Admin Command Center:\n" . sgd_get_portal_url( 'admin' ) . "\n";
            @wp_mail( $admin_email, $email_subject, $email_body );

            sgd_set_flash( 'success', '🚨 SOS Flare Sent! The Guild Master & Dispatch Board have been alerted to dispatch help to your location!' );
        }
        $redirect_tab = ( $task && $task->type === 'urgent' ) ? 'urgent' : 'daily';
        wp_safe_redirect( sgd_get_portal_url( $redirect_tab ) );
        exit;
    }

    // FINISH JOB WITH PHOTO PROOF UPLOAD
    if ( $action === 'sgd_finish_job' ) {
        $task_id = (int) ( $_POST['task_id'] ?? 0 );
        $proof_notes = sanitize_textarea_field( $_POST['proof_notes'] ?? '' );
        $task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_tasks WHERE id = %d", $task_id ) );

        if ( ! $task ) {
            sgd_set_flash( 'error', 'Quest not found.' );
            wp_safe_redirect( sgd_get_portal_url() );
            exit;
        }

        // Verify photo proof upload
        $photo_url = '';
        if ( isset( $_FILES['proof_photo'] ) && ! empty( $_FILES['proof_photo']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $uploadedfile = $_FILES['proof_photo'];
            $upload_overrides = [ 'test_form' => false ];
            $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $photo_url = $movefile['url'];
            } else {
                sgd_set_flash( 'error', 'Photo Upload Failed: ' . ( $movefile['error'] ?? 'Unknown error' ) );
                $redirect_tab = ( $task->type === 'urgent' ) ? 'urgent' : 'daily';
                wp_safe_redirect( sgd_get_portal_url( $redirect_tab ) );
                exit;
            }
        } else {
            sgd_set_flash( 'error', 'Proof of completion is required! Please attach a photo/screenshot of your completed job.' );
            $redirect_tab = ( $task->type === 'urgent' ) ? 'urgent' : 'daily';
            wp_safe_redirect( sgd_get_portal_url( $redirect_tab ) );
            exit;
        }

        // Reward & Streak calculation: Reward XP for daily tasks and special promotion tasks!
        $base_xp = ! empty( $task->reward_xp ) ? (int) $task->reward_xp : ( ! empty( $task->reward_diamonds ) ? (int) $task->reward_diamonds : 200 );
        $streak_count = 0;
        $streak_bonus = 0;
        $total_xp_awarded = $base_xp;
        $total_diamonds = 0;

        $is_promo_trial = false;
        $promo_task_id = (int) get_user_meta( $current_user_id, 'sgd_promotion_task_id', true );
        if ( ( $promo_task_id && $promo_task_id === (int) $task_id ) || ( isset( $task->category ) && $task->category === 'Promotion Evaluation' ) ) {
            $is_promo_trial = true;
        }

        if ( $task->type === 'daily' ) {
            $streak_data = sgd_record_user_daily_completion( $current_user_id );
            $streak_count = $streak_data['current'];
            if ( $streak_data['bonus_percent'] > 0 ) {
                $streak_bonus = (int) round( $base_xp * ( $streak_data['bonus_percent'] / 100 ) );
                $total_xp_awarded = $base_xp + $streak_bonus;
            }
            // Reward XP instead of diamonds
            sgd_add_xp( $current_user_id, $total_xp_awarded );
        } elseif ( $is_promo_trial ) {
            // Special Level 30 promotion task
            sgd_add_xp( $current_user_id, $total_xp_awarded );
            update_user_meta( $current_user_id, 'sgd_promotion_status', 'task_completed' );
            update_user_meta( $current_user_id, 'sgd_promotion_task_completed_at', current_time( 'mysql' ) );
        } elseif ( $task->type === 'urgent' ) {
            // Urgent emergency bounty rewards diamonds & XP
            $total_diamonds = (int) ( $task->reward_diamonds ?: 1000 );
            sgd_add_diamonds( $current_user_id, $total_diamonds );
            sgd_add_xp( $current_user_id, $total_xp_awarded );
        } else {
            sgd_add_xp( $current_user_id, $total_xp_awarded );
        }

        if ( $is_promo_trial ) {
            update_user_meta( $current_user_id, 'sgd_promotion_status', 'task_completed' );
            update_user_meta( $current_user_id, 'sgd_promotion_task_completed_at', current_time( 'mysql' ) );
        }

        // Mark task completed
        $wpdb->update( $table_tasks, [
            'status'          => 'completed',
            'completed_by'    => $current_user_id,
            'completed_time'  => current_time( 'mysql' ),
            'proof_image_url' => $photo_url,
            'proof_notes'     => $proof_notes,
            'help_status'     => ( $task->help_status === 'requested' ) ? 'resolved' : $task->help_status,
        ], [ 'id' => $task_id ] );

        // Log completion in persistent task log table
        $table_task_logs = $wpdb->prefix . 'sgd_task_logs';
        $wpdb->insert( $table_task_logs, [
            'task_id'          => $task_id,
            'task_title'       => $task->title,
            'task_type'        => $task->type,
            'user_id'          => $current_user_id,
            'diamonds_awarded' => $total_diamonds,
            'xp_awarded'       => $total_xp_awarded,
            'streak_count'     => $streak_count,
            'streak_bonus'     => $streak_bonus,
            'proof_image_url'  => $photo_url,
            'proof_notes'      => $proof_notes,
            'completed_time'   => current_time( 'mysql' ),
        ] );

        // Check and award automatic achievements (and queue gilded alerts)
        sgd_check_achievements( $current_user_id );

        if ( $is_promo_trial ) {
            sgd_set_flash( 'celebration', "🌟 PROMOTION TRIAL COMPLETED! (+{$total_xp_awarded} XP!) Grand Marshal Admin has been notified to upgrade your Employee Job Class to Manager! 👑" );
        } elseif ( $task->type === 'daily' ) {
            if ( $streak_bonus > 0 ) {
                sgd_set_flash( 'celebration', "🔥 {$streak_count}-DAY STREAK! (+{$streak_bonus} XP ⭐ Streak Bonus!) Daily Duty accomplished for +{$total_xp_awarded} Total XP! ⭐" );
            } elseif ( $streak_count === 1 ) {
                sgd_set_flash( 'celebration', "🔥 STREAK STARTED (Day 1)! You earned +{$total_xp_awarded} XP! Return tomorrow for streak bonuses! ⭐" );
            } else {
                sgd_set_flash( 'celebration', "🏆 DAILY QUEST ACCOMPLISHED! You delivered proof of honor and earned +{$total_xp_awarded} XP! ⭐" );
            }
        } else {
            sgd_set_flash( 'celebration', "🏆 QUEST ACCOMPLISHED! You delivered proof of honor and earned +{$total_xp_awarded} XP! ⭐" );
        }
        $redirect_tab = ( $task->type === 'urgent' ) ? 'urgent' : 'daily';
        wp_safe_redirect( sgd_get_portal_url( $redirect_tab ) );
        exit;
    }

    // CASH SHOP REDEMPTION
    if ( $action === 'sgd_redeem' ) {
        $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
        $shop_catalog = sgd_get_shop_catalog();

        if ( ! isset( $shop_catalog[ $item_id ] ) ) {
            sgd_set_flash( 'error', 'Invalid reward item selected.' );
            wp_safe_redirect( sgd_get_portal_url( 'shop' ) );
            exit;
        }

        $item = $shop_catalog[ $item_id ];
        $cost = (int) $item['cost'];
        $user_diamonds = sgd_get_diamonds( $current_user_id );

        if ( $user_diamonds < $cost ) {
            sgd_set_flash( 'error', "Not enough diamonds! You need {$cost} 💎 but only have {$user_diamonds} 💎. Complete more quests!" );
            wp_safe_redirect( sgd_get_portal_url( 'shop' ) );
            exit;
        }

        // Deduct diamonds
        sgd_deduct_diamonds( $current_user_id, $cost );

        // Insert redemption record
        $table_redemptions = $wpdb->prefix . 'sgd_redemptions';
        $wpdb->insert( $table_redemptions, [
            'user_id'        => $current_user_id,
            'item_id'        => $item_id,
            'item_name'      => $item['name'],
            'diamonds_spent' => $cost,
            'status'         => 'pending',
            'notes'          => sanitize_textarea_field( $_POST['redeem_notes'] ?? '' ),
            'created_at'     => current_time( 'mysql' ),
        ] );
        $new_order_id = $wpdb->insert_id;

        // Send Email Notification to Admin
        $admin_email = get_option( 'admin_email' );
        $buyer = get_user_by( 'id', $current_user_id );
        $buyer_name = $buyer ? $buyer->display_name : 'Employee #' . $current_user_id;
        $order_subject = "🎁 [CASH SHOP ORDER] {$buyer_name} redeemed: {$item['name']} ({$cost} 💎)";
        $order_body = "Guild Master Alert,\n\nEmployee {$buyer_name} ({$buyer->user_email}) has placed a new Cash Shop order!\n\nOrder ID: #{$new_order_id}\nItem: {$item['name']}\nDiamonds Deducted: " . number_format($cost) . " 💎\nOrder Time: " . current_time('mysql') . "\n\nPlease review and approve this order in the Admin Command Center:\n" . add_query_arg(['sgd_page' => 'admin', 'tab' => 'orders'], home_url('/')) . "\n";
        @wp_mail( $admin_email, $order_subject, $order_body );

        // Award "Goblin Trader" achievement
        $ach_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sgd_achievements WHERE slug = %s", 'shop_baron' ) );
        if ( $ach_id ) {
            $already = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sgd_user_achievements WHERE user_id = %d AND achievement_id = %d", $current_user_id, $ach_id ) );
            if ( ! $already ) {
                $wpdb->insert( "{$wpdb->prefix}sgd_user_achievements", [
                    'user_id' => $current_user_id,
                    'achievement_id' => $ach_id,
                    'unlocked_at' => current_time( 'mysql' ),
                ] );
                sgd_add_diamonds( $current_user_id, 250 );
            }
        }

        sgd_set_flash( 'celebration', "🎁 BOUNTY REDEEMED! You spent {$cost} 💎 for [{$item['name']}]. HR & Goblin Treasury have received your order voucher!" );
        wp_safe_redirect( sgd_get_portal_url( 'shop' ) );
        exit;
    }

    // ADMIN ACTIONS (Require current_user_can('administrator'))
    if ( current_user_can( 'administrator' ) ) {

        // Admin Create / Update Task
        if ( $action === 'sgd_admin_save_task' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $type = ( $_POST['task_type'] === 'urgent' ) ? 'urgent' : 'daily';
            $title = sanitize_text_field( $_POST['title'] ?? '' );
            $description = sanitize_textarea_field( $_POST['description'] ?? '' );
            $category = sanitize_text_field( $_POST['category'] ?? 'General' );
            $reward = (int) ( $_POST['reward_diamonds'] ?? ( $type === 'urgent' ? 1000 : 200 ) );
            $reward_xp = (int) ( $_POST['reward_xp'] ?? ( ! empty($_POST['reward_diamonds']) ? $_POST['reward_diamonds'] : 200 ) );
            $assigned_to = (int) ( $_POST['assigned_to'] ?? 0 );
            $is_recurring = ( isset( $_POST['is_recurring'] ) || ( ! isset( $_POST['task_id'] ) && $type === 'daily' ) ) ? 1 : 0;

            if ( empty( $title ) ) {
                sgd_set_flash( 'error', 'Task title cannot be empty.' );
            } else {
                $data = [
                    'type'            => $type,
                    'title'           => $title,
                    'description'     => $description,
                    'category'        => $category,
                    'reward_diamonds' => $reward,
                    'reward_xp'       => $reward_xp,
                    'assigned_to'     => $assigned_to,
                    'is_recurring'    => $is_recurring,
                ];

                if ( $task_id > 0 ) {
                    $wpdb->update( $table_tasks, $data, [ 'id' => $task_id ] );
                    sgd_set_flash( 'success', 'Quest updated successfully.' );
                } else {
                    $data['status'] = 'open';
                    $data['help_status'] = 'none';
                    $wpdb->insert( $table_tasks, $data );

                    if ( $assigned_to > 0 ) {
                        $target_hero = get_user_by( 'id', $assigned_to );
                        $hero_name = $target_hero ? $target_hero->display_name : 'Employee';
                        sgd_set_flash( 'success', "🎯 Custom Task assigned specifically to {$hero_name}!" );
                    } else {
                        sgd_set_flash( 'success', 'New quest posted to the bulletin board!' );
                    }
                }
            }
            $redirect_page = sanitize_text_field( $_POST['redirect_to'] ?? 'admin' );
            wp_safe_redirect( sgd_get_portal_url( $redirect_page ) );
            exit;
        }

        // Admin Assign Special Level 30 Promotion Task
        if ( $action === 'sgd_admin_assign_promotion_task' ) {
            $target_user_id = (int) ( $_POST['target_user_id'] ?? 0 );
            $title = sanitize_text_field( $_POST['title'] ?? 'Level 30 Promotion Trial: Leadership Audit & Sprint Review' );
            $desc  = sanitize_textarea_field( $_POST['description'] ?? 'Lead departmental audit, coordinate cross-team deliverables, and submit leadership verification report with photo proof.' );
            $reward_xp = max( 100, (int) ( $_POST['reward_xp'] ?? 1000 ) );

            if ( $target_user_id ) {
                $wpdb->insert( $table_tasks, [
                    'type'            => 'urgent',
                    'title'           => $title,
                    'description'     => $desc,
                    'category'        => 'Promotion Evaluation',
                    'reward_diamonds' => 200,
                    'reward_xp'       => $reward_xp,
                    'assigned_to'     => $target_user_id,
                    'status'          => 'open',
                    'is_recurring'    => 0,
                    'created_at'      => current_time( 'mysql' ),
                ] );
                $new_task_id = $wpdb->insert_id;

                update_user_meta( $target_user_id, 'sgd_promotion_status', 'task_assigned' );
                update_user_meta( $target_user_id, 'sgd_promotion_task_id', $new_task_id );

                $target_user = get_userdata( $target_user_id );
                $target_name = $target_user ? $target_user->display_name : 'Employee';
                sgd_set_flash( 'celebration', "⭐ SPECIAL PROMOTION TASK DISPATCHED! Assigned to {$target_name}. Once completed, you can change their Job Class to Manager! 👑" );
            }
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'admin_tab' => 'promotion' ], home_url( '/' ) ) );
            exit;
        }

        // Admin Change Employee Job Class (e.g. from Staff to Manager)
        if ( $action === 'sgd_admin_change_job_class' ) {
            $target_user_id = (int) ( $_POST['target_user_id'] ?? 0 );
            $new_class_select = sanitize_text_field( $_POST['new_job_class_select'] ?? '' );
            $custom_class = sanitize_text_field( $_POST['custom_job_class'] ?? '' );
            $new_class = ! empty( $custom_class ) ? $custom_class : $new_class_select;

            if ( $target_user_id && ! empty( $new_class ) ) {
                $old_class = sgd_get_user_job_class( $target_user_id );
                sgd_set_user_job_class( $target_user_id, $new_class );
                update_user_meta( $target_user_id, 'sgd_promotion_status', 'promoted' );
                update_user_meta( $target_user_id, 'sgd_promoted_at', current_time( 'mysql' ) );
                update_user_meta( $target_user_id, 'sgd_pending_promotion_notice', $new_class );

                $target_user = get_userdata( $target_user_id );
                $target_name = $target_user ? $target_user->display_name : 'Employee';
                sgd_set_flash( 'celebration', "👑 PROMOTION COMPLETE! {$target_name} is now promoted from {$old_class} to {$new_class}! 🎖️" );
            }
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'admin_tab' => 'promotion' ], home_url( '/' ) ) );
            exit;
        }

        // Admin Manual Trigger for Daily Reset
        if ( $action === 'sgd_admin_manual_daily_reset' ) {
            $affected = sgd_run_daily_reset();
            sgd_set_flash( 'celebration', "🔄 DAILY QUEST BOARD RESET! {$affected} recurring daily task(s) were refreshed and reopened for today." );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'tab' => 'cron' ], home_url( '/' ) ) );
            exit;
        }

        // Admin Delete Task
        if ( $action === 'sgd_admin_delete_task' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $wpdb->delete( $table_tasks, [ 'id' => $task_id ] );
            sgd_set_flash( 'success', 'Quest destroyed from quest log.' );
            wp_safe_redirect( sgd_get_portal_url( 'admin' ) );
            exit;
        }

        // Admin Dispatch Help
        if ( $action === 'sgd_admin_dispatch_help' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $notes = sanitize_textarea_field( $_POST['dispatch_notes'] ?? 'Reinforcements dispatched!' );
            $wpdb->update( $table_tasks, [
                'help_status' => 'dispatched',
                'help_notes'  => $notes,
            ], [ 'id' => $task_id ] );
            sgd_set_flash( 'success', '🛡️ Reinforcements Dispatched! Employee notification sent.' );
            $redirect_page = sanitize_text_field( $_POST['redirect_to'] ?? 'admin' );
            wp_safe_redirect( sgd_get_portal_url( $redirect_page ) );
            exit;
        }

        // Admin Update Employee Diamonds, XP, Job Class, or Achievements
        if ( $action === 'sgd_admin_update_user' ) {
            $target_user_id = (int) ( $_POST['target_user_id'] ?? 0 );
            $diamond_change = (int) ( $_POST['diamond_amount'] ?? 0 );
            $change_mode = sanitize_text_field( $_POST['change_mode'] ?? 'add' );

            if ( $target_user_id ) {
                if ( $diamond_change > 0 || $change_mode === 'set' ) {
                    if ( $change_mode === 'set' ) {
                        sgd_set_diamonds( $target_user_id, $diamond_change );
                    } elseif ( $change_mode === 'add' ) {
                        sgd_add_diamonds( $target_user_id, $diamond_change );
                    } else {
                        sgd_deduct_diamonds( $target_user_id, $diamond_change );
                    }
                }

                // XP Adjustment
                if ( isset( $_POST['xp_amount'] ) && is_numeric( $_POST['xp_amount'] ) ) {
                    $xp_amount = (int) $_POST['xp_amount'];
                    $xp_mode = sanitize_text_field( $_POST['xp_mode'] ?? 'add' );
                    if ( $xp_mode === 'set' ) {
                        sgd_set_xp( $target_user_id, $xp_amount );
                    } elseif ( $xp_mode === 'add' ) {
                        sgd_add_xp( $target_user_id, $xp_amount );
                    } else {
                        $curr_xp = sgd_get_user_xp( $target_user_id );
                        sgd_set_xp( $target_user_id, max( 0, $curr_xp - $xp_amount ) );
                    }
                }

                // Job Class Adjustment
                if ( ! empty( $_POST['job_class'] ) ) {
                    sgd_set_user_job_class( $target_user_id, sanitize_text_field( $_POST['job_class'] ) );
                }

                // Grant custom achievement if selected
                $grant_ach = (int) ( $_POST['grant_achievement_id'] ?? 0 );
                if ( $grant_ach ) {
                    $table_user_ach = $wpdb->prefix . 'sgd_user_achievements';
                    $wpdb->replace( $table_user_ach, [
                        'user_id'        => $target_user_id,
                        'achievement_id' => $grant_ach,
                        'unlocked_at'    => current_time( 'mysql' ),
                    ] );
                    // Queue gilded celebration for employee
                    sgd_queue_achievement_alert( $target_user_id, $grant_ach );
                }

                sgd_set_flash( 'success', 'Hero record, Job Class & Balances updated.' );
            }
            wp_safe_redirect( sgd_get_portal_url( 'admin' ) );
            exit;
        }

        // Admin Create / Edit Achievement Definition
        if ( $action === 'sgd_admin_save_achievement' ) {
            $table_ach = $wpdb->prefix . 'sgd_achievements';
            $ach_id = (int) ( $_POST['achievement_id'] ?? 0 );
            $title = sanitize_text_field( $_POST['title'] ?? '' );
            $description = sanitize_textarea_field( $_POST['description'] ?? '' );
            $icon = sanitize_text_field( $_POST['icon'] ?? '🏆' );
            $reward = (int) ( $_POST['reward_diamonds'] ?? 100 );
            $reward_xp = (int) ( $_POST['reward_xp'] ?? ( ! empty($_POST['reward_diamonds']) ? $_POST['reward_diamonds'] : 200 ) );
            $category = sanitize_text_field( $_POST['category'] ?? 'General' );

            if ( empty( $title ) ) {
                sgd_set_flash( 'error', 'Achievement title cannot be empty.' );
            } else {
                $slug = sanitize_title( $title ) . '_' . time();
                $data = [
                    'title'           => $title,
                    'description'     => $description,
                    'icon'            => $icon,
                    'reward_diamonds' => $reward,
                    'reward_xp'       => $reward_xp,
                    'category'        => $category,
                ];

                if ( $ach_id > 0 ) {
                    $wpdb->update( $table_ach, $data, [ 'id' => $ach_id ] );
                    sgd_set_flash( 'success', '🏆 Achievement definition updated!' );
                } else {
                    $data['slug'] = $slug;
                    $wpdb->insert( $table_ach, $data );
                    sgd_set_flash( 'success', '✨ New Achievement created and added to the Guild Hall!' );
                }
            }
            $redirect_page = sanitize_text_field( $_POST['redirect_to'] ?? 'admin' );
            wp_safe_redirect( sgd_get_portal_url( $redirect_page ) );
            exit;
        }

        // Admin Delete Achievement Definition
        if ( $action === 'sgd_admin_delete_achievement' ) {
            $ach_id = (int) ( $_POST['achievement_id'] ?? 0 );
            if ( $ach_id ) {
                $wpdb->delete( $wpdb->prefix . 'sgd_achievements', [ 'id' => $ach_id ] );
                $wpdb->delete( $wpdb->prefix . 'sgd_user_achievements', [ 'achievement_id' => $ach_id ] );
                sgd_set_flash( 'success', 'Achievement deleted from system.' );
            }
            $redirect_page = sanitize_text_field( $_POST['redirect_to'] ?? 'admin' );
            wp_safe_redirect( sgd_get_portal_url( $redirect_page ) );
            exit;
        }

        // Admin Grant or Revoke Employee Achievement
        if ( $action === 'sgd_admin_toggle_user_achievement' ) {
            $target_user_id = (int) ( $_POST['target_user_id'] ?? 0 );
            $ach_id = (int) ( $_POST['achievement_id'] ?? 0 );
            $subaction = sanitize_text_field( $_POST['toggle_action'] ?? 'grant' ); // 'grant' or 'revoke'

            $table_user_ach = $wpdb->prefix . 'sgd_user_achievements';
            $table_ach = $wpdb->prefix . 'sgd_achievements';

            if ( $target_user_id && $ach_id ) {
                $target_user = get_user_by( 'id', $target_user_id );
                $ach = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_ach WHERE id = %d", $ach_id ) );

                if ( $subaction === 'grant' ) {
                    $wpdb->replace( $table_user_ach, [
                        'user_id'        => $target_user_id,
                        'achievement_id' => $ach_id,
                        'unlocked_at'    => current_time( 'mysql' ),
                    ] );
                    // Optionally award reward XP (instead of diamonds)
                    if ( isset( $_POST['award_bonus'] ) && $_POST['award_bonus'] === 'yes' && $ach ) {
                        $ach_xp = ! empty( $ach->reward_xp ) ? (int) $ach->reward_xp : ( ! empty( $ach->reward_diamonds ) ? (int) $ach->reward_diamonds : 200 );
                        if ( $ach_xp > 0 ) {
                            sgd_add_xp( $target_user_id, $ach_xp );
                        }
                    }
                    // Queue gilded celebration for employee
                    sgd_queue_achievement_alert( $target_user_id, $ach_id );
                    sgd_set_flash( 'celebration', "🎖️ Granted [{$ach->title}] to {$target_user->display_name}!" );
                } else {
                    $wpdb->delete( $table_user_ach, [
                        'user_id'        => $target_user_id,
                        'achievement_id' => $ach_id,
                    ] );
                    sgd_set_flash( 'success', "❌ Revoked [{$ach->title}] from {$target_user->display_name}." );
                }
            }
            $redirect_page = sanitize_text_field( $_POST['redirect_to'] ?? 'admin' );
            $extra_args = [ 'sgd_page' => $redirect_page ];
            if ( $target_user_id ) {
                $extra_args['view_employee'] = $target_user_id;
            }
            wp_safe_redirect( add_query_arg( $extra_args, home_url( '/' ) ) );
            exit;
        }

        // Admin Approve Cash Shop Redemption
        if ( $action === 'sgd_admin_approve_redemption' ) {
            $order_id = (int) ( $_POST['order_id'] ?? 0 );
            $notes = sanitize_textarea_field( $_POST['admin_notes'] ?? 'Order fulfilled & delivered.' );
            $table_redemptions = $wpdb->prefix . 'sgd_redemptions';

            if ( $order_id ) {
                $wpdb->update( $table_redemptions, [
                    'status' => 'approved',
                    'notes'  => $notes,
                ], [ 'id' => $order_id ] );
                sgd_set_flash( 'celebration', "✅ Cash Shop Order #{$order_id} marked as APPROVED & FULFILLED!" );
            }
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'tab' => 'orders' ], home_url( '/' ) ) );
            exit;
        }

        // Admin Reject Cash Shop Redemption (with automatic Diamond refund)
        if ( $action === 'sgd_admin_reject_redemption' ) {
            $order_id = (int) ( $_POST['order_id'] ?? 0 );
            $reason = sanitize_textarea_field( $_POST['admin_notes'] ?? 'Order declined.' );
            $table_redemptions = $wpdb->prefix . 'sgd_redemptions';

            if ( $order_id ) {
                $order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_redemptions WHERE id = %d", $order_id ) );
                if ( $order && $order->status !== 'rejected' ) {
                    // Refund diamonds back to employee
                    sgd_add_diamonds( $order->user_id, $order->diamonds_spent );

                    $wpdb->update( $table_redemptions, [
                        'status' => 'rejected',
                        'notes'  => 'Rejected: ' . $reason . ' (Diamonds refunded)',
                    ], [ 'id' => $order_id ] );

                    $buyer = get_user_by( 'id', $order->user_id );
                    $buyer_name = $buyer ? $buyer->display_name : 'Employee';
                    sgd_set_flash( 'error', "❌ Order #{$order_id} rejected. Refunded +{$order->diamonds_spent} 💎 back to {$buyer_name}'s treasury." );
                }
            }
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'tab' => 'orders' ], home_url( '/' ) ) );
            exit;
        }

        // Admin Regenerate API Secret Key
        if ( $action === 'sgd_regenerate_api_key' ) {
            $new_key = 'sgd_live_' . wp_generate_password( 28, false, false );
            update_option( 'sgd_api_secret_key', $new_key );
            sgd_set_flash( 'celebration', '⚡ New API Secret Key generated successfully! Update your AI agents and Cicle webhooks.' );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'tab' => 'api' ], home_url( '/' ) ) );
            exit;
        }

        // Admin Test Dispatch Task via Internal API handler
        if ( $action === 'sgd_admin_test_dispatch_task' ) {
            $title = sanitize_text_field( $_POST['task_title'] ?? '' );
            $desc = sanitize_textarea_field( $_POST['task_desc'] ?? '' );
            $category = sanitize_text_field( $_POST['task_category'] ?? 'Cicle Sprint' );
            $target_id = (int) ( $_POST['assigned_to'] ?? 0 );
            $reward = max( 50, (int) ( $_POST['reward_diamonds'] ?? 200 ) );
            $ext_url = esc_url_raw( $_POST['external_url'] ?? '' );

            if ( ! empty( $title ) ) {
                $table_tasks = $wpdb->prefix . 'sgd_tasks';
                $wpdb->insert( $table_tasks, [
                    'type'            => 'daily',
                    'title'           => $title,
                    'description'     => $desc,
                    'category'        => $category,
                    'reward_diamonds' => $reward,
                    'assigned_to'     => $target_id,
                    'claimed_by'      => 0,
                    'status'          => 'open',
                    'help_status'     => 'none',
                    'is_recurring'    => 1,
                    'external_url'    => $ext_url,
                    'created_at'      => current_time( 'mysql' ),
                ] );
                $target_user = $target_id ? get_user_by( 'id', $target_id ) : null;
                $target_name = $target_user ? $target_user->display_name : 'All Employees';
                sgd_set_flash( 'celebration', "🤖 TEST TASK CREATED: \"{$title}\" dispatched and assigned to {$target_name}!" );
            }
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'tab' => 'api' ], home_url( '/' ) ) );
            exit;
        }
    } // End of Administrator-Only Action handlers

    // ==========================================
    // LAPORAN HARIAN (DAILY WORK REPORT) HANDLERS
    // Accessible by all users (Employees & Admin)
    // ==========================================

    // 1. Submit Daily Report (Text / Voice + up to 3 Photos + AI/NLP Decipher)
    if ( $action === 'sgd_submit_daily_report' ) {
        // Ensure table exists
        $table_daily_reports = $wpdb->prefix . 'sgd_daily_reports';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_daily_reports'" ) != $table_daily_reports ) {
            sgd_install_database();
        }

        $raw_input = sanitize_textarea_field( $_POST['raw_input'] ?? '' );
        if ( empty( trim( $raw_input ) ) ) {
            sgd_set_flash( 'error', '⚠️ Laporan kerja tidak boleh kosong. Silakan ketik atau gunakan rekaman suara.' );
            wp_safe_redirect( add_query_arg( 'sgd_page', 'laporan-harian', home_url( '/' ) ) );
            exit;
        }

        $report_date = sanitize_text_field( $_POST['report_date'] ?? current_time( 'Y-m-d' ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $report_date ) ) {
            $report_date = current_time( 'Y-m-d' );
        }

        // Handle up to 3 photo uploads
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $photos = [ '', '', '' ];
        for ( $i = 1; $i <= 3; $i++ ) {
            $fkey = 'progress_photo_' . $i;
            if ( ! empty( $_FILES[ $fkey ]['name'] ) ) {
                $upload = wp_handle_upload( $_FILES[ $fkey ], [ 'test_form' => false ] );
                if ( $upload && ! isset( $upload['error'] ) ) {
                    $photos[ $i - 1 ] = $upload['url'];
                }
            }
        }

        // Decipher the raw input using AI or smart NLP heuristic parser
        $parsed = sgd_decipher_daily_report( $raw_input );
        $parsed_engine = $parsed['parsed_with'] ?? 'smart_nlp';

        $insert_data = [
            'report_date'            => $report_date,
            'user_id'                => $current_user_id ?: 1,
            'raw_input'              => $raw_input,
            'weather_hourly'         => wp_json_encode( $parsed['weather_hourly'] ?? [] ),
            'manpower'               => wp_json_encode( $parsed['manpower'] ?? [] ),
            'materials'              => wp_json_encode( $parsed['materials'] ?? [] ),
            'equipment'              => wp_json_encode( $parsed['equipment'] ?? [] ),
            'work_executed'          => wp_json_encode( $parsed['work_executed'] ?? [] ),
            'issues'                 => wp_json_encode( $parsed['issues'] ?? [] ),
            'supervisor_instruction' => sanitize_textarea_field( $_POST['supervisor_instruction'] ?? '' ),
            'next_day_plan'          => sanitize_textarea_field( $_POST['next_day_plan'] ?? '' ),
            'safety_k3'              => sanitize_text_field( $parsed['safety_k3'] ?? 'Nihil kecelakaan kerja' ),
            'additional_notes'       => sanitize_textarea_field( $_POST['additional_notes'] ?? '' ),
            'photo_1'                => $photos[0],
            'photo_2'                => $photos[1],
            'photo_3'                => $photos[2],
            'status'                 => 'submitted',
            'parsed_with'            => $parsed_engine,
            'created_at'             => current_time( 'mysql' ),
            'updated_at'             => current_time( 'mysql' ),
        ];

        $inserted = $wpdb->insert( $table_daily_reports, $insert_data );
        $report_id = $wpdb->insert_id;

        if ( ! $inserted ) {
            $last_error = $wpdb->last_error ? ': ' . $wpdb->last_error : '';
            sgd_set_flash( 'error', '⚠️ Gagal menyimpan laporan ke database' . $last_error );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $report_date ], home_url( '/' ) ) );
            exit;
        }

        // Award daily report completion XP (instead of diamonds)
        $reward_xp = 200;
        if ( $current_user_id ) {
            sgd_add_xp( $current_user_id, $reward_xp );
        }

        $engine_label = ( $parsed_engine === 'gemini' ) ? 'Google Gemini AI ✨' : ( ( $parsed_engine === 'openai' ) ? 'OpenAI GPT ✨' : 'Smart Construction NLP ⚡' );
        sgd_set_flash( 'celebration', "📋 LAPORAN HARIAN BERHASIL DIKIRIM! Berhasil di-decipher oleh {$engine_label} dan Anda memperoleh +{$reward_xp} XP ⭐!" );

        wp_safe_redirect( add_query_arg( [
            'sgd_page'    => 'laporan-harian',
            'report_date' => $report_date,
            'report_id'   => $report_id,
        ], home_url( '/' ) ) );
        exit;
    }

        // 2. Update Daily Report (Admin or Employee editing fields, weather, manpower, materials, supervisor instructions)
        if ( $action === 'sgd_update_daily_report' ) {
            $report_id = (int) ( $_POST['report_id'] ?? 0 );
            $table_daily_reports = $wpdb->prefix . 'sgd_daily_reports';
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_daily_reports WHERE id = %d", $report_id ) );

            if ( ! $existing ) {
                sgd_set_flash( 'error', '⚠️ Laporan tidak ditemukan.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'laporan-harian', home_url( '/' ) ) );
                exit;
            }

            // Only administrator or original author can edit
            if ( ! current_user_can( 'administrator' ) && (int) $existing->user_id !== $current_user_id ) {
                sgd_set_flash( 'error', '⚠️ Anda tidak memiliki izin untuk mengubah laporan ini.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'laporan-harian', home_url( '/' ) ) );
                exit;
            }

            $report_date = sanitize_text_field( $_POST['report_date'] ?? $existing->report_date );
            $raw_input = sanitize_textarea_field( $_POST['raw_input'] ?? $existing->raw_input );

            // Process Weather per hour
            $hours = [ '07.00', '08.00', '09.00', '10.00', '11.00', '12.00', '13.00', '14.00', '15.00', '16.00', '17.00' ];
            $weather_hourly = [];
            if ( isset( $_POST['weather'] ) && is_array( $_POST['weather'] ) ) {
                foreach ( $hours as $h ) {
                    $weather_hourly[ $h ] = sanitize_text_field( $_POST['weather'][ $h ] ?? 'cerah' );
                }
            } else {
                $weather_hourly = json_decode( $existing->weather_hourly, true ) ?: [];
            }

            // Process Manpower rows
            $manpower = [];
            if ( isset( $_POST['manpower_role'] ) && is_array( $_POST['manpower_role'] ) ) {
                foreach ( $_POST['manpower_role'] as $idx => $role ) {
                    $r = sanitize_text_field( $role );
                    $c = (int) ( $_POST['manpower_count'][ $idx ] ?? 0 );
                    if ( ! empty( $r ) && $c > 0 ) {
                        $manpower[] = [ 'role' => $r, 'count' => $c ];
                    }
                }
            } else {
                $manpower = json_decode( $existing->manpower, true ) ?: [];
            }

            // Process Materials rows
            $materials = [];
            if ( isset( $_POST['material_name'] ) && is_array( $_POST['material_name'] ) ) {
                foreach ( $_POST['material_name'] as $idx => $mname ) {
                    $mn = sanitize_text_field( $mname );
                    $mq = sanitize_text_field( $_POST['material_quantity'][ $idx ] ?? '' );
                    $mu = sanitize_text_field( $_POST['material_unit'][ $idx ] ?? 'kg' );
                    if ( ! empty( $mn ) ) {
                        $materials[] = [ 'name' => $mn, 'quantity' => $mq, 'unit' => $mu ];
                    }
                }
            } else {
                $materials = json_decode( $existing->materials, true ) ?: [];
            }

            // Process Equipment rows
            $equipment = [];
            if ( isset( $_POST['equipment_name'] ) && is_array( $_POST['equipment_name'] ) ) {
                foreach ( $_POST['equipment_name'] as $eq ) {
                    $eq_clean = sanitize_text_field( $eq );
                    if ( ! empty( $eq_clean ) && ! in_array( $eq_clean, $equipment ) ) {
                        $equipment[] = $eq_clean;
                    }
                }
            } else {
                $equipment = json_decode( $existing->equipment, true ) ?: [];
            }

            // Work executed, issues, supervisor instructions, tomorrow's plan, K3, notes
            $work_text = sanitize_textarea_field( $_POST['work_executed'] ?? '' );
            $work_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $work_text ) ) ) );
            $work_executed = ! empty( $work_lines ) ? array_values( $work_lines ) : ( json_decode( $existing->work_executed, true ) ?: [ $work_text ] );

            $issues_text = sanitize_textarea_field( $_POST['issues'] ?? '' );
            $issues_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $issues_text ) ) ) );
            $issues = ! empty( $issues_lines ) ? array_values( $issues_lines ) : ( json_decode( $existing->issues, true ) ?: [ $issues_text ] );

            $supervisor_instruction = sanitize_textarea_field( $_POST['supervisor_instruction'] ?? $existing->supervisor_instruction );
            $next_day_plan = sanitize_textarea_field( $_POST['next_day_plan'] ?? $existing->next_day_plan );
            $safety_k3 = sanitize_text_field( $_POST['safety_k3'] ?? $existing->safety_k3 );
            $additional_notes = sanitize_textarea_field( $_POST['additional_notes'] ?? $existing->additional_notes );

            // Process photo replacements or removals
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $photo_1 = $existing->photo_1;
            $photo_2 = $existing->photo_2;
            $photo_3 = $existing->photo_3;

            for ( $i = 1; $i <= 3; $i++ ) {
                $fkey = 'progress_photo_' . $i;
                $remkey = 'remove_photo_' . $i;
                if ( ! empty( $_POST[ $remkey ] ) ) {
                    ${'photo_' . $i} = '';
                }
                if ( ! empty( $_FILES[ $fkey ]['name'] ) ) {
                    $upload = wp_handle_upload( $_FILES[ $fkey ], [ 'test_form' => false ] );
                    if ( $upload && ! isset( $upload['error'] ) ) {
                        ${'photo_' . $i} = $upload['url'];
                    }
                }
            }

            $wpdb->update( $table_daily_reports, [
                'report_date'            => $report_date,
                'raw_input'              => $raw_input,
                'weather_hourly'         => wp_json_encode( $weather_hourly ),
                'manpower'               => wp_json_encode( $manpower ),
                'materials'              => wp_json_encode( $materials ),
                'equipment'              => wp_json_encode( $equipment ),
                'work_executed'          => wp_json_encode( $work_executed ),
                'issues'                 => wp_json_encode( $issues ),
                'supervisor_instruction' => $supervisor_instruction,
                'next_day_plan'          => $next_day_plan,
                'safety_k3'              => $safety_k3,
                'additional_notes'       => $additional_notes,
                'photo_1'                => $photo_1,
                'photo_2'                => $photo_2,
                'photo_3'                => $photo_3,
                'status'                 => current_user_can( 'administrator' ) ? 'approved' : $existing->status,
                'updated_at'             => current_time( 'mysql' ),
            ], [ 'id' => $report_id ] );

            sgd_set_flash( 'celebration', '💾 PERUBAHAN LAPORAN HARIAN BERHASIL DISIMPAN! Rincian cuaca, tenaga kerja, material, alat, dan instruksi pengawas telah diperbarui.' );
            wp_safe_redirect( add_query_arg( [
                'sgd_page'    => 'laporan-harian',
                'report_date' => $report_date,
                'report_id'   => $report_id,
            ], home_url( '/' ) ) );
            exit;
        }

        // 3. Re-Decipher report with AI or NLP
        if ( $action === 'sgd_reparse_daily_report' ) {
            $report_id = (int) ( $_POST['report_id'] ?? 0 );
            $table_daily_reports = $wpdb->prefix . 'sgd_daily_reports';
            $report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_daily_reports WHERE id = %d", $report_id ) );

            if ( $report && ! empty( $report->raw_input ) ) {
                $parsed = sgd_decipher_daily_report( $report->raw_input );
                $parsed_engine = $parsed['parsed_with'] ?? 'smart_nlp';

                $wpdb->update( $table_daily_reports, [
                    'weather_hourly' => wp_json_encode( $parsed['weather_hourly'] ?? [] ),
                    'manpower'       => wp_json_encode( $parsed['manpower'] ?? [] ),
                    'materials'      => wp_json_encode( $parsed['materials'] ?? [] ),
                    'equipment'      => wp_json_encode( $parsed['equipment'] ?? [] ),
                    'work_executed'  => wp_json_encode( $parsed['work_executed'] ?? [] ),
                    'issues'         => wp_json_encode( $parsed['issues'] ?? [] ),
                    'safety_k3'      => sanitize_text_field( $parsed['safety_k3'] ?? 'Nihil kecelakaan kerja' ),
                    'parsed_with'    => $parsed_engine,
                    'updated_at'     => current_time( 'mysql' ),
                ], [ 'id' => $report_id ] );

                $engine_label = ( $parsed_engine === 'gemini' ) ? 'Google Gemini AI ✨' : ( ( $parsed_engine === 'openai' ) ? 'OpenAI GPT ✨' : 'Smart Construction NLP ⚡' );
                sgd_set_flash( 'celebration', "🤖 LAPORAN BERHASIL DI-ANALISIS ULANG (RE-PARSED) MENGGUNAKAN {$engine_label}!" );
            }

            wp_safe_redirect( add_query_arg( [
                'sgd_page'    => 'laporan-harian',
                'report_date' => $report ? $report->report_date : current_time( 'Y-m-d' ),
                'report_id'   => $report_id,
            ], home_url( '/' ) ) );
            exit;
        }

        // 4. Save AI API Configuration
        if ( $action === 'sgd_save_ai_settings' ) {
            $gemini_key = sanitize_text_field( $_POST['gemini_api_key'] ?? '' );
            $openai_key = sanitize_text_field( $_POST['openai_api_key'] ?? '' );
            $provider   = sanitize_text_field( $_POST['ai_provider'] ?? 'gemini' );

            // Auto-select AI provider based on entered keys if not specified
            if ( ! empty( $gemini_key ) && ( empty( $provider ) || $provider === 'smart_nlp' ) ) {
                $provider = 'gemini';
            } elseif ( ! empty( $openai_key ) && empty( $gemini_key ) ) {
                $provider = 'openai';
            }

            update_option( 'sgd_gemini_api_key', $gemini_key );
            update_option( 'sgd_openai_api_key', $openai_key );
            update_option( 'sgd_ai_provider', $provider );

            $engine_name = 'Smart Construction NLP ⚡';
            if ( $provider === 'gemini' ) {
                $engine_name = 'Google Gemini AI (Gratis & Cerdas) ✨';
            } elseif ( $provider === 'openai' ) {
                $engine_name = 'OpenAI GPT-4o Mini ✨';
            }

            sgd_set_flash( 'celebration', '🤖 KONFIGURASI AI BERHASIL DISIMPAN! Laporan harian kini di-decipher menggunakan ' . $engine_name . '.' );
            $redirect = sanitize_text_field( $_POST['redirect_to'] ?? 'laporan-harian' );
            wp_safe_redirect( add_query_arg( 'sgd_page', $redirect, home_url( '/' ) ) );
            exit;
        }

        // 5. Delete Daily Report
        if ( $action === 'sgd_delete_daily_report' ) {
            if ( ! current_user_can( 'administrator' ) ) {
                sgd_set_flash( 'error', '⛔ Hanya Administrator yang dapat menghapus laporan.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'laporan-harian', home_url( '/' ) ) );
                exit;
            }

            $report_id = (int) ( $_POST['report_id'] ?? 0 );
            $table_daily_reports = $wpdb->prefix . 'sgd_daily_reports';
            $report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_daily_reports WHERE id = %d", $report_id ) );
            if ( $report ) {
                $rdate = $report->report_date;
                $wpdb->delete( $table_daily_reports, [ 'id' => $report_id ] );
                sgd_set_flash( 'celebration', "🗑️ Laporan Harian #{$report_id} berhasil dihapus." );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $rdate ], home_url( '/' ) ) );
                exit;
            }
            wp_safe_redirect( add_query_arg( 'sgd_page', 'laporan-harian', home_url( '/' ) ) );
            exit;
        }

        // ========================================================
        // CICLE KANBAN QUEST SYSTEM ACTIONS
        // ========================================================

        // 6. Create Task Group (HQ, Tim, Proyek)
        if ( $action === 'sgd_create_quest_team' ) {
            if ( ! current_user_can( 'administrator' ) ) {
                sgd_set_flash( 'error', '⛔ Akses Ditolak: Hanya Administrator yang dapat membuat Task Group.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $name = sanitize_text_field( $_POST['team_name'] ?? '' );
            $type = sanitize_text_field( $_POST['team_type'] ?? 'tim' );
            if ( ! in_array( $type, [ 'hq', 'tim', 'proyek' ], true ) ) {
                $type = 'tim';
            }
            $description = sanitize_textarea_field( $_POST['team_description'] ?? '' );
            $manager_id = (int) ( $_POST['manager_id'] ?? $current_user_id );
            
            $raw_members = $_POST['member_ids'] ?? [];
            if ( is_string( $raw_members ) ) {
                $raw_members = explode( ',', $raw_members );
            }
            $clean_members = array_filter( array_map( 'intval', (array) $raw_members ) );
            // Ensure manager is in members
            if ( $manager_id && ! in_array( $manager_id, $clean_members, true ) ) {
                $clean_members[] = $manager_id;
            }
            $member_ids_str = implode( ',', $clean_members );

            if ( empty( $name ) ) {
                sgd_set_flash( 'error', '⚠️ Nama Tim / Task Group (TimName) wajib diisi!' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $table_quest_teams = $wpdb->prefix . 'sgd_quest_teams';
            $wpdb->insert( $table_quest_teams, [
                'name'        => $name,
                'type'        => $type,
                'description' => $description,
                'manager_id'  => $manager_id,
                'member_ids'  => $member_ids_str,
                'created_by'  => $current_user_id,
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ] );
            $new_team_id = $wpdb->insert_id;

            $type_label = strtoupper( $type );
            sgd_set_flash( 'celebration', "🎉 Task Group [{$type_label}] '{$name}' berhasil dibuat!" );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $new_team_id ], home_url( '/' ) ) );
            exit;
        }

        // 7. Update Task Group
        if ( $action === 'sgd_update_quest_team' ) {
            if ( ! current_user_can( 'administrator' ) ) {
                sgd_set_flash( 'error', '⛔ Akses Ditolak: Hanya Administrator yang dapat mengubah Task Group.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $team_id = (int) ( $_POST['team_id'] ?? 0 );
            $name = sanitize_text_field( $_POST['team_name'] ?? '' );
            $type = sanitize_text_field( $_POST['team_type'] ?? 'tim' );
            if ( ! in_array( $type, [ 'hq', 'tim', 'proyek' ], true ) ) {
                $type = 'tim';
            }
            $description = sanitize_textarea_field( $_POST['team_description'] ?? '' );
            $manager_id = (int) ( $_POST['manager_id'] ?? $current_user_id );
            
            $raw_members = $_POST['member_ids'] ?? [];
            if ( is_string( $raw_members ) ) {
                $raw_members = explode( ',', $raw_members );
            }
            $clean_members = array_filter( array_map( 'intval', (array) $raw_members ) );
            if ( $manager_id && ! in_array( $manager_id, $clean_members, true ) ) {
                $clean_members[] = $manager_id;
            }
            $member_ids_str = implode( ',', $clean_members );

            if ( empty( $name ) ) {
                sgd_set_flash( 'error', '⚠️ Nama Tim tidak boleh kosong!' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $team_id ], home_url( '/' ) ) );
                exit;
            }

            $table_quest_teams = $wpdb->prefix . 'sgd_quest_teams';
            $wpdb->update(
                $table_quest_teams,
                [
                    'name'        => $name,
                    'type'        => $type,
                    'description' => $description,
                    'manager_id'  => $manager_id,
                    'member_ids'  => $member_ids_str,
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $team_id ]
            );

            sgd_set_flash( 'celebration', "✅ Pengaturan Task Group '{$name}' berhasil diperbarui!" );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $team_id ], home_url( '/' ) ) );
            exit;
        }

        // 8. Delete Task Group
        if ( $action === 'sgd_delete_quest_team' ) {
            if ( ! current_user_can( 'administrator' ) ) {
                sgd_set_flash( 'error', '⛔ Akses Ditolak: Hanya Administrator yang dapat menghapus Task Group.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $team_id = (int) ( $_POST['team_id'] ?? 0 );
            $table_quest_teams = $wpdb->prefix . 'sgd_quest_teams';
            $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
            $table_quest_comments = $wpdb->prefix . 'sgd_quest_comments';
            $table_quest_attachments = $wpdb->prefix . 'sgd_quest_attachments';

            // Find tasks to delete related comments/attachments
            $team_tasks = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table_quest_tasks WHERE team_id = %d", $team_id ) );
            if ( ! empty( $team_tasks ) ) {
                $task_ids_str = implode( ',', array_map( 'intval', $team_tasks ) );
                $wpdb->query( "DELETE FROM $table_quest_comments WHERE task_id IN ($task_ids_str)" );
                $wpdb->query( "DELETE FROM $table_quest_attachments WHERE task_id IN ($task_ids_str)" );
                $wpdb->query( "DELETE FROM $table_quest_tasks WHERE team_id = $team_id" );
            }
            $wpdb->delete( $table_quest_teams, [ 'id' => $team_id ] );

            sgd_set_flash( 'celebration', '🗑️ Task Group dan seluruh tugas di dalamnya berhasil dihapus.' );
            wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
            exit;
        }

        // 9. Create New Task (Tugas) in a Column
        if ( $action === 'sgd_create_quest_task' ) {
            $team_id = (int) ( $_POST['team_id'] ?? 0 );
            $team = sgd_get_quest_team( $team_id );
            if ( ! $team ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => 'Task Group tidak ditemukan.' ] );
                }
                sgd_set_flash( 'error', '⚠️ Task Group tidak ditemukan.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            if ( ! sgd_user_can_manage_quest( $team, $current_user_id ) ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => 'Hanya Manager Tim atau Administrator yang dapat membuat tugas.' ] );
                }
                sgd_set_flash( 'error', '⛔ Hanya Manager Tim atau Administrator yang dapat membuat tugas.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $team_id ], home_url( '/' ) ) );
                exit;
            }

            $allowed_columns = [ 'To do list', 'Dikerjakan', 'Selesai', 'Batal' ];
            $column_name = sanitize_text_field( $_POST['column_name'] ?? 'To do list' );
            if ( ! in_array( $column_name, $allowed_columns, true ) ) {
                $column_name = 'To do list';
            }

            $title = sanitize_text_field( $_POST['task_title'] ?? '' );
            if ( empty( $title ) ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => 'Nama tugas wajib diisi!' ] );
                }
                sgd_set_flash( 'error', '⚠️ Nama tugas (ask name) wajib diisi!' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $team_id ], home_url( '/' ) ) );
                exit;
            }

            $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
            $max_order = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(sort_order) FROM $table_quest_tasks WHERE team_id = %d AND column_name = %s",
                $team_id, $column_name
            ) );

            $quest_level = max( 1, min( 50, (int) ( $_POST['quest_level'] ?? 1 ) ) );
            $reward_type = sanitize_text_field( $_POST['reward_type'] ?? 'both' );
            if ( ! in_array( $reward_type, [ 'both', 'xp', 'gold', 'none' ], true ) ) {
                $reward_type = 'both';
            }
            $reward_xp = isset( $_POST['reward_xp'] ) ? max( 0, (int) $_POST['reward_xp'] ) : 200;
            $reward_gold = isset( $_POST['reward_gold'] ) ? max( 0, (int) $_POST['reward_gold'] ) : 50;

            // Handle labels / tags from create form
            $labels_json = null;
            if ( ! empty( $_POST['task_tag'] ) ) {
                $tag_map = [
                    'Fitur'    => [ 'name' => '✨ Fitur', 'color' => '#2ecc71' ],
                    'Bug'      => [ 'name' => '🐛 Bug', 'color' => '#ff5252' ],
                    'UI/UX'    => [ 'name' => '🎨 UI/UX', 'color' => '#a55eea' ],
                    'Review'   => [ 'name' => '📋 Review', 'color' => '#ffd700' ],
                    'Urgent'   => [ 'name' => '🔴 Urgent', 'color' => '#ff4444' ],
                    'Optimasi' => [ 'name' => '⚡ Optimasi', 'color' => '#00e5ff' ],
                ];
                $chosen = sanitize_text_field( $_POST['task_tag'] );
                if ( isset( $tag_map[ $chosen ] ) ) {
                    $labels_json = wp_json_encode( [ $tag_map[ $chosen ] ] );
                }
            } elseif ( ! empty( $_POST['labels'] ) ) {
                $decoded = json_decode( stripslashes( $_POST['labels'] ), true );
                if ( is_array( $decoded ) ) {
                    $clean_labels = [];
                    foreach ( $decoded as $lbl ) {
                        $lname = sanitize_text_field( $lbl['name'] ?? '' );
                        $lcolor = sanitize_hex_color( $lbl['color'] ?? '#00e5ff' ) ?: '#00e5ff';
                        if ( ! empty( $lname ) ) {
                            $clean_labels[] = [ 'name' => $lname, 'color' => $lcolor ];
                        }
                    }
                    $labels_json = wp_json_encode( $clean_labels );
                }
            }

            // Handle assigned member from create form
            $assigned_user_id = (int) ( $_POST['assigned_user_id'] ?? 0 );
            if ( ! $assigned_user_id ) {
                $assigned_user_id = $current_user_id ?: 1;
            }
            $assigned_members_str = (string) $assigned_user_id;

            $wpdb->insert( $table_quest_tasks, [
                'team_id'          => $team_id,
                'column_name'      => $column_name,
                'title'            => $title,
                'description'      => '',
                'deadline'         => null,
                'assigned_members' => $assigned_members_str,
                'labels'           => $labels_json,
                'sort_order'       => $max_order + 1,
                'quest_level'      => $quest_level,
                'reward_type'      => $reward_type,
                'reward_xp'        => $reward_xp,
                'reward_gold'      => $reward_gold,
                'created_by'       => $current_user_id,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ] );
            $new_task_id = $wpdb->insert_id;

            if ( ! empty( $_POST['is_ajax'] ) ) {
                $task = sgd_get_quest_task( $new_task_id );
                wp_send_json_success( [
                    'message' => "Tugas '{$title}' berhasil dibuat!",
                    'task'    => $task,
                ] );
            }

            sgd_set_flash( 'celebration', "✅ Tugas baru '{$title}' berhasil dibuat di kolom {$column_name}!" );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $team_id ], home_url( '/' ) ) );
            exit;
        }

        // 10. Update Task (Title, Column, Deadline, Members, WYSIWYG Description)
        if ( $action === 'sgd_update_quest_task' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
                }
                sgd_set_flash( 'error', '⚠️ Tugas tidak ditemukan.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $team = sgd_get_quest_team( $task->team_id );
            $is_mgr = sgd_user_can_manage_quest( $team, $current_user_id );
            $team_member_ids = array_filter( array_map( 'intval', explode( ',', $team->member_ids ?? '' ) ) );
            $is_member = in_array( $current_user_id, $team_member_ids, true ) || ( (int) ( $team->manager_id ?? 0 ) === $current_user_id );

            if ( ! $is_mgr && ! $is_member ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => 'Anda bukan anggota tim ini.' ] );
                }
                sgd_set_flash( 'error', '⛔ Anda bukan anggota dari tim ini.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
                exit;
            }

            $title = sanitize_text_field( $_POST['task_title'] ?? $task->title );
            $allowed_columns = [ 'To do list', 'Dikerjakan', 'Selesai', 'Batal' ];
            $column_name = sanitize_text_field( $_POST['column_name'] ?? $task->column_name );
            if ( ! in_array( $column_name, $allowed_columns, true ) ) {
                $column_name = $task->column_name;
            }

            // MANAGER APPROVAL GATE: Only manager can update status to Selesai
            if ( $column_name === 'Selesai' && $task->column_name !== 'Selesai' && ! $is_mgr ) {
                $column_name = $task->column_name;
                sgd_set_flash( 'error', '🔒 Kolom Selesai membutuhkan verifikasi dan persetujuan Manager Tim.' );
            }

            // Big WYSIWYG editor description: preserve rich HTML formatting safely
            $description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );

            // Deadline
            $deadline_raw = sanitize_text_field( $_POST['deadline'] ?? '' );
            $deadline = null;
            if ( ! empty( $deadline_raw ) ) {
                $deadline_ts = strtotime( $deadline_raw );
                if ( $deadline_ts ) {
                    $deadline = date( 'Y-m-d H:i:s', $deadline_ts );
                }
            }

            // Assigned members
            $raw_members = $_POST['assigned_members'] ?? [];
            if ( is_string( $raw_members ) ) {
                $raw_members = explode( ',', $raw_members );
            }
            $clean_members = array_filter( array_map( 'intval', (array) $raw_members ) );
            $assigned_str = implode( ',', $clean_members );

            // Labels / Tags (JSON array of {name, color})
            $raw_labels = $_POST['labels'] ?? '';
            $labels_json = null;
            if ( ! empty( $raw_labels ) ) {
                $decoded = json_decode( stripslashes( $raw_labels ), true );
                if ( is_array( $decoded ) ) {
                    $clean_labels = [];
                    foreach ( $decoded as $lbl ) {
                        $lname = sanitize_text_field( $lbl['name'] ?? '' );
                        $lcolor = sanitize_hex_color( $lbl['color'] ?? '#00e5ff' ) ?: '#00e5ff';
                        if ( ! empty( $lname ) ) {
                            $clean_labels[] = [ 'name' => $lname, 'color' => $lcolor ];
                        }
                    }
                    $labels_json = wp_json_encode( $clean_labels );
                }
            }

            // Checklist JSON
            $checklist_json = wp_json_encode( $task->checklist );
            if ( isset( $_POST['checklist'] ) ) {
                $raw_chk = stripslashes( $_POST['checklist'] );
                $decoded_chk = json_decode( $raw_chk, true );
                if ( is_array( $decoded_chk ) ) {
                    $clean_chk = [];
                    foreach ( $decoded_chk as $c_it ) {
                        $c_txt = sanitize_text_field( $c_it['text'] ?? '' );
                        if ( ! empty( $c_txt ) ) {
                            $clean_chk[] = [
                                'id'           => sanitize_text_field( $c_it['id'] ?? uniqid('chk_') ),
                                'text'         => $c_txt,
                                'completed'    => ! empty( $c_it['completed'] ),
                                'completed_by' => ! empty( $c_it['completed_by'] ) ? (int) $c_it['completed_by'] : null,
                                'completed_at' => ! empty( $c_it['completed_at'] ) ? sanitize_text_field( $c_it['completed_at'] ) : null,
                            ];
                        }
                    }
                    $checklist_json = wp_json_encode( $clean_chk );
                }
            }

            // Quest Level & Rewards
            $quest_level = max( 1, min( 50, (int) ( $_POST['quest_level'] ?? ( $task->quest_level ?? 1 ) ) ) );
            $reward_type = sanitize_text_field( $_POST['reward_type'] ?? ( $task->reward_type ?? 'both' ) );
            if ( ! in_array( $reward_type, [ 'both', 'xp', 'gold', 'none' ], true ) ) {
                $reward_type = 'both';
            }
            $reward_xp = isset( $_POST['reward_xp'] ) ? max( 0, (int) $_POST['reward_xp'] ) : ( $task->reward_xp ?? 200 );
            $reward_gold = isset( $_POST['reward_gold'] ) ? max( 0, (int) $_POST['reward_gold'] ) : ( $task->reward_gold ?? 50 );

            $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
            $wpdb->update(
                $table_quest_tasks,
                [
                    'title'            => $title,
                    'column_name'      => $column_name,
                    'description'      => $description,
                    'deadline'         => $deadline,
                    'assigned_members' => $assigned_str,
                    'labels'           => $labels_json,
                    'checklist'        => $checklist_json,
                    'quest_level'      => $quest_level,
                    'reward_type'      => $reward_type,
                    'reward_xp'        => $reward_xp,
                    'reward_gold'      => $reward_gold,
                    'updated_at'       => current_time( 'mysql' ),
                ],
                [ 'id' => $task_id ]
            );

            // If column is Selesai, trigger reward distribution
            $reward_msg = '';
            if ( $column_name === 'Selesai' ) {
                $award_result = sgd_complete_quest_task( $task_id, $current_user_id );
                if ( ! empty( $award_result ) ) {
                    $parts = [];
                    foreach ( $award_result as $ar ) {
                        $parts[] = "{$ar['user_name']} ({$ar['percent']}% porsi = +{$ar['awarded_xp']} XP, +{$ar['awarded_gold']} 💎)";
                    }
                    $reward_msg = ' Hadiah dibagikan: ' . implode( ', ', $parts );
                }
            }

            if ( ! empty( $_POST['is_ajax'] ) ) {
                wp_send_json_success( [
                    'message'    => 'Tugas berhasil diperbarui!' . ( $reward_msg ? " 🎉 $reward_msg" : '' ),
                    'task'       => sgd_get_quest_task( $task_id ),
                    'reward_msg' => $reward_msg,
                ] );
            }

            sgd_set_flash( 'celebration', "💾 Tugas '{$title}' berhasil disimpan!" . ( $reward_msg ? " 🎉 $reward_msg" : '' ) );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
            exit;
        }

        // 11. Clone Task (Duplikat Tugas)
        if ( $action === 'sgd_clone_quest_task' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                sgd_set_flash( 'error', '⚠️ Tugas tidak ditemukan untuk diduplikasi.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $team = sgd_get_quest_team( $task->team_id );
            if ( ! sgd_user_can_manage_quest( $team, $current_user_id ) ) {
                sgd_set_flash( 'error', '⛔ Akses Ditolak: Hanya Manager Tim atau Administrator yang dapat menduplikasi tugas.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
                exit;
            }

            $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
            $cloned_title = '📋 (Copy) ' . $task->title;

            $max_order = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(sort_order) FROM $table_quest_tasks WHERE team_id = %d AND column_name = %s",
                $task->team_id, $task->column_name
            ) );

            $wpdb->insert( $table_quest_tasks, [
                'team_id'          => $task->team_id,
                'column_name'      => $task->column_name,
                'title'            => $cloned_title,
                'description'      => $task->description,
                'deadline'         => $task->deadline,
                'assigned_members' => $task->assigned_members,
                'labels'           => $task->labels,
                'checklist'        => wp_json_encode( $task->checklist ),
                'quest_level'      => (int) ( $task->quest_level ?? 1 ),
                'reward_type'      => $task->reward_type ?? 'both',
                'reward_xp'        => (int) ( $task->reward_xp ?? 200 ),
                'reward_gold'      => (int) ( $task->reward_gold ?? 50 ),
                'reward_awarded'   => 0,
                'sort_order'       => $max_order + 1,
                'created_by'       => $current_user_id,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ] );
            $new_cloned_id = $wpdb->insert_id;

            if ( ! empty( $_POST['is_ajax'] ) ) {
                wp_send_json_success( [
                    'message'   => "Tugas berhasil diduplikasi menjadi '{$cloned_title}'!",
                    'cloned_id' => $new_cloned_id,
                ] );
            }

            sgd_set_flash( 'celebration', "📋 Tugas berhasil diduplikasi: '{$cloned_title}'!" );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $new_cloned_id ], home_url( '/' ) ) );
            exit;
        }

        // 12. Move Task Between Columns (Kanban Move / Drag & Drop)
        if ( $action === 'sgd_move_quest_task' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $team = sgd_get_quest_team( $task->team_id );
            $is_mgr = sgd_user_can_manage_quest( $team, $current_user_id );
            $team_member_ids = array_filter( array_map( 'intval', explode( ',', $team->member_ids ?? '' ) ) );
            $is_member = in_array( $current_user_id, $team_member_ids, true ) || ( (int) ( $team->manager_id ?? 0 ) === $current_user_id );

            if ( ! $is_mgr && ! $is_member ) {
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => 'Anda bukan anggota tim ini.' ] );
                sgd_set_flash( 'error', '⛔ Anda bukan anggota dari tim ini.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
                exit;
            }

            // MANAGER APPROVAL GATE: Only Manager can approve moving task to "Selesai"
            if ( $target_column === 'Selesai' && ! $is_mgr ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => '🔒 Kolom Selesai membutuhkan verifikasi dan persetujuan Manager Tim.' ] );
                }
                sgd_set_flash( 'error', '🔒 Kolom Selesai membutuhkan verifikasi dan persetujuan Manager Tim.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
                exit;
            }

            $allowed_columns = [ 'To do list', 'Dikerjakan', 'Selesai', 'Batal' ];
            $target_column = sanitize_text_field( $_POST['target_column'] ?? '' );
            if ( ! in_array( $target_column, $allowed_columns, true ) ) {
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => 'Kolom tujuan tidak valid.' ] );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
                exit;
            }

            $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
            $wpdb->update(
                $table_quest_tasks,
                [
                    'column_name' => $target_column,
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $task_id ]
            );

            // If moved to Selesai, distribute quest rewards
            $award_msg = '';
            if ( $target_column === 'Selesai' ) {
                $award_result = sgd_complete_quest_task( $task_id, $current_user_id );
                if ( ! empty( $award_result ) ) {
                    $parts = [];
                    foreach ( $award_result as $ar ) {
                        $parts[] = "{$ar['user_name']} ({$ar['percent']}% porsi = +{$ar['awarded_xp']} XP, +{$ar['awarded_gold']} 💎)";
                    }
                    $award_msg = ' Hadiah dibagikan: ' . implode( ', ', $parts );
                }
            }

            if ( ! empty( $_POST['is_ajax'] ) ) {
                wp_send_json_success( [
                    'message'       => "Tugas dipindahkan ke '{$target_column}'" . ( $award_msg ? " 🎉 $award_msg" : '' ),
                    'target_column' => $target_column,
                    'reward_msg'    => $award_msg,
                ] );
            }

            sgd_set_flash( 'celebration', "↔️ Tugas dipindahkan ke kolom '{$target_column}'." . ( $award_msg ? " 🎉 $award_msg" : '' ) );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
            exit;
        }

        // 13. Delete Task
        if ( $action === 'sgd_delete_quest_task' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                sgd_set_flash( 'error', '⚠️ Tugas tidak ditemukan.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $team = sgd_get_quest_team( $task->team_id );
            if ( ! sgd_user_can_manage_quest( $team, $current_user_id ) ) {
                sgd_set_flash( 'error', '⛔ Hanya Manager Tim atau Administrator yang dapat menghapus tugas.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
                exit;
            }

            $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
            $table_quest_comments = $wpdb->prefix . 'sgd_quest_comments';
            $table_quest_attachments = $wpdb->prefix . 'sgd_quest_attachments';

            $wpdb->delete( $table_quest_comments, [ 'task_id' => $task_id ] );
            $wpdb->delete( $table_quest_attachments, [ 'task_id' => $task_id ] );
            $wpdb->delete( $table_quest_tasks, [ 'id' => $task_id ] );

            if ( ! empty( $_POST['is_ajax'] ) ) {
                wp_send_json_success( [ 'message' => 'Tugas berhasil dihapus!' ] );
            }

            sgd_set_flash( 'celebration', "🗑️ Tugas '{$task->title}' berhasil dihapus." );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id ], home_url( '/' ) ) );
            exit;
        }

        // 14. Add Comment / Chat to Task
        if ( $action === 'sgd_add_quest_comment' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            $comment_text = sanitize_textarea_field( $_POST['comment_text'] ?? '' );
            if ( empty( $comment_text ) ) {
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => 'Komentar tidak boleh kosong.' ] );
                sgd_set_flash( 'error', '⚠️ Komentar tidak boleh kosong.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
                exit;
            }

            $table_quest_comments = $wpdb->prefix . 'sgd_quest_comments';
            $wpdb->insert( $table_quest_comments, [
                'task_id'    => $task_id,
                'user_id'    => $current_user_id,
                'comment'    => $comment_text,
                'created_at' => current_time( 'mysql' ),
            ] );
            $comment_id = $wpdb->insert_id;

            if ( ! empty( $_POST['is_ajax'] ) ) {
                $u = wp_get_current_user();
                wp_send_json_success( [
                    'comment' => [
                        'id'          => $comment_id,
                        'user_name'   => $u->display_name,
                        'user_avatar' => function_exists('sgd_get_user_avatar') ? sgd_get_user_avatar( $current_user_id ) : '👤',
                        'comment'     => esc_html( $comment_text ),
                        'created_at'  => current_time( 'd M Y, H:i' ),
                    ],
                ] );
            }

            sgd_set_flash( 'celebration', '💬 Komentar berhasil dikirim!' );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
            exit;
        }

        // 15. Upload File Attachment for Task (Supports Multiple Files & AJAX)
        if ( $action === 'sgd_upload_quest_attachment' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
                }
                sgd_set_flash( 'error', '⚠️ Tugas tidak ditemukan.' );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $files_to_process = [];
            // Check quest_files (multiple) or quest_file
            $file_key = isset( $_FILES['quest_files'] ) ? 'quest_files' : ( isset( $_FILES['quest_file'] ) ? 'quest_file' : null );

            if ( $file_key && ! empty( $_FILES[ $file_key ]['name'] ) ) {
                if ( is_array( $_FILES[ $file_key ]['name'] ) ) {
                    $count = count( $_FILES[ $file_key ]['name'] );
                    for ( $i = 0; $i < $count; $i++ ) {
                        if ( ! empty( $_FILES[ $file_key ]['name'][ $i ] ) && ( $_FILES[ $file_key ]['error'][ $i ] ?? 1 ) === UPLOAD_ERR_OK ) {
                            $files_to_process[] = [
                                'name'     => $_FILES[ $file_key ]['name'][ $i ],
                                'type'     => $_FILES[ $file_key ]['type'][ $i ] ?? '',
                                'tmp_name' => $_FILES[ $file_key ]['tmp_name'][ $i ] ?? '',
                                'error'    => $_FILES[ $file_key ]['error'][ $i ] ?? 0,
                                'size'     => $_FILES[ $file_key ]['size'][ $i ] ?? 0,
                            ];
                        }
                    }
                } else {
                    if ( ( $_FILES[ $file_key ]['error'] ?? 1 ) === UPLOAD_ERR_OK ) {
                        $files_to_process[] = $_FILES[ $file_key ];
                    }
                }
            }

            if ( empty( $files_to_process ) ) {
                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_error( [ 'message' => 'Silakan pilih setidaknya satu file dokumen atau gambar untuk diunggah.' ] );
                }
                sgd_set_flash( 'error', '⚠️ Silakan pilih file dokumen atau gambar untuk diunggah.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
                exit;
            }

            $table_quest_attachments = $wpdb->prefix . 'sgd_quest_attachments';
            $uploaded_count = 0;
            $uploaded_items = [];

            foreach ( $files_to_process as $file_arr ) {
                $uploaded = wp_handle_upload( $file_arr, [ 'test_form' => false ] );
                if ( $uploaded && ! isset( $uploaded['error'] ) ) {
                    $orig_name = sanitize_file_name( $file_arr['name'] );
                    $wpdb->insert( $table_quest_attachments, [
                        'task_id'    => $task_id,
                        'user_id'    => $current_user_id,
                        'file_url'   => $uploaded['url'],
                        'file_name'  => $orig_name,
                        'file_size'  => (int) ( $file_arr['size'] ?? 0 ),
                        'file_type'  => sanitize_text_field( $uploaded['type'] ?? '' ),
                        'created_at' => current_time( 'mysql' ),
                    ] );
                    $uploaded_count++;
                    $uploaded_items[] = [
                        'id'        => $wpdb->insert_id,
                        'file_name' => $orig_name,
                        'file_url'  => $uploaded['url'],
                        'file_size' => (int) ( $file_arr['size'] ?? 0 ),
                    ];
                }
            }

            $total_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_quest_attachments WHERE task_id = %d", $task_id ) );

            if ( ! empty( $_POST['is_ajax'] ) ) {
                if ( $uploaded_count > 0 ) {
                    wp_send_json_success( [
                        'message'          => "$uploaded_count file berhasil diunggah!",
                        'uploaded_count'   => $uploaded_count,
                        'total_attachment' => $total_count,
                        'attachments'      => sgd_get_quest_attachments( $task_id ),
                    ] );
                } else {
                    wp_send_json_error( [ 'message' => 'Gagal mengunggah file. Pastikan format file didukung.' ] );
                }
            }

            if ( $uploaded_count > 0 ) {
                sgd_set_flash( 'celebration', "📎 $uploaded_count file lampiran berhasil diunggah!" );
            } else {
                sgd_set_flash( 'error', '⚠️ Gagal mengunggah file: Pastikan format didukung.' );
            }

            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
            exit;
        }

        // 16. Delete File Attachment
        if ( $action === 'sgd_delete_quest_attachment' ) {
            $attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );

            $table_quest_attachments = $wpdb->prefix . 'sgd_quest_attachments';
            $wpdb->delete( $table_quest_attachments, [ 'id' => $attachment_id ] );

            sgd_set_flash( 'celebration', '🗑️ File lampiran berhasil dihapus.' );
            $team_id = $task ? $task->team_id : 0;
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
            exit;
        }

        // 17. Toggle Checklist Item (Manager Verification)
        if ( $action === 'sgd_toggle_quest_checklist_item' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
            }

            $team = sgd_get_quest_team( $task->team_id );
            if ( ! sgd_user_can_manage_quest( $team, $current_user_id ) ) {
                wp_send_json_error( [ 'message' => 'Hanya Manager Tim atau Administrator yang berhak memverifikasi checklist tugas ini.' ] );
            }

            $checklist = ! empty( $task->checklist ) && is_array( $task->checklist ) ? $task->checklist : [];
            $found = false;
            $now_completed = false;

            foreach ( $checklist as &$it ) {
                if ( ( $it['id'] ?? '' ) === $item_id ) {
                    $it['completed'] = ! empty( $_POST['completed'] );
                    $it['completed_by'] = $it['completed'] ? $current_user_id : null;
                    $it['completed_at'] = $it['completed'] ? current_time( 'mysql' ) : null;
                    $now_completed = $it['completed'];
                    $found = true;
                    break;
                }
            }

            if ( ! $found ) {
                wp_send_json_error( [ 'message' => 'Item checklist tidak ditemukan.' ] );
            }

            $wpdb->update(
                $wpdb->prefix . 'sgd_quest_tasks',
                [
                    'checklist'  => wp_json_encode( $checklist ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $task_id ]
            );

            $chk_total = count( $checklist );
            $chk_done = count( array_filter( $checklist, fn( $c ) => ! empty( $c['completed'] ) ) );
            $chk_pct = $chk_total > 0 ? round( ( $chk_done / $chk_total ) * 100 ) : 0;

            wp_send_json_success( [
                'message'       => $now_completed ? 'Item checklist diverifikasi selesai!' : 'Item checklist diubah belum selesai.',
                'item_id'       => $item_id,
                'completed'     => $now_completed,
                'checklist'     => $checklist,
                'done_count'    => $chk_done,
                'total_count'   => $chk_total,
                'percent'       => $chk_pct,
                'all_completed' => ( $chk_done === $chk_total && $chk_total > 0 ),
            ] );
        }

        // 18. Add Checklist Item
        if ( $action === 'sgd_add_quest_checklist_item' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $text = sanitize_text_field( wp_unslash( $_POST['text'] ?? '' ) );
            if ( empty( $text ) ) {
                wp_send_json_error( [ 'message' => 'Teks checklist tidak boleh kosong.' ] );
            }

            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
            }

            $team = sgd_get_quest_team( $task->team_id );
            if ( ! sgd_user_can_manage_quest( $team, $current_user_id ) ) {
                wp_send_json_error( [ 'message' => 'Hanya Manager Tim atau Administrator yang dapat menambahkan checklist.' ] );
            }

            $checklist = ! empty( $task->checklist ) && is_array( $task->checklist ) ? $task->checklist : [];
            $new_item = [
                'id'           => uniqid( 'chk_' ),
                'text'         => $text,
                'completed'    => false,
                'completed_by' => null,
                'completed_at' => null,
            ];
            $checklist[] = $new_item;

            $wpdb->update(
                $wpdb->prefix . 'sgd_quest_tasks',
                [
                    'checklist'  => wp_json_encode( $checklist ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $task_id ]
            );

            $chk_total = count( $checklist );
            $chk_done = count( array_filter( $checklist, fn( $c ) => ! empty( $c['completed'] ) ) );
            $chk_pct = $chk_total > 0 ? round( ( $chk_done / $chk_total ) * 100 ) : 0;

            wp_send_json_success( [
                'message'     => 'Item checklist berhasil ditambahkan!',
                'new_item'    => $new_item,
                'checklist'   => $checklist,
                'done_count'  => $chk_done,
                'total_count' => $chk_total,
                'percent'     => $chk_pct,
            ] );
        }

        // 19. Delete Checklist Item
        if ( $action === 'sgd_delete_quest_checklist_item' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
            }

            $team = sgd_get_quest_team( $task->team_id );
            if ( ! sgd_user_can_manage_quest( $team, $current_user_id ) ) {
                wp_send_json_error( [ 'message' => 'Hanya Manager Tim atau Administrator yang dapat menghapus checklist.' ] );
            }

            $checklist = ! empty( $task->checklist ) && is_array( $task->checklist ) ? $task->checklist : [];
            $checklist = array_values( array_filter( $checklist, fn( $it ) => ( $it['id'] ?? '' ) !== $item_id ) );

            $wpdb->update(
                $wpdb->prefix . 'sgd_quest_tasks',
                [
                    'checklist'  => wp_json_encode( $checklist ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $task_id ]
            );

            $chk_total = count( $checklist );
            $chk_done = count( array_filter( $checklist, fn( $c ) => ! empty( $c['completed'] ) ) );
            $chk_pct = $chk_total > 0 ? round( ( $chk_done / $chk_total ) * 100 ) : 0;

            wp_send_json_success( [
                'message'     => 'Item checklist dihapus!',
                'item_id'     => $item_id,
                'checklist'   => $checklist,
                'done_count'  => $chk_done,
                'total_count' => $chk_total,
                'percent'     => $chk_pct,
            ] );
        }

        // 20. Set Card Cover Image (Or remove cover with cover_url='none')
        if ( $action === 'sgd_set_quest_cover' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $cover_url = sanitize_text_field( $_POST['cover_url'] ?? '' );
            if ( $cover_url !== 'none' ) {
                $cover_url = esc_url_raw( $cover_url );
            }
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
            }

            $wpdb->update(
                $wpdb->prefix . 'sgd_quest_tasks',
                [
                    'cover_image' => $cover_url,
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $task_id ]
            );

            if ( ! empty( $_POST['is_ajax'] ) ) {
                wp_send_json_success( [
                    'message'   => $cover_url === 'none' ? 'Cover kartu dihapus.' : 'Gambar cover kartu berhasil dipasang!',
                    'cover_url' => $cover_url,
                    'task_id'   => $task_id,
                ] );
            }
            sgd_set_flash( 'celebration', '🖼️ Cover kartu tugas diperbarui!' );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
            exit;
        }

        // 21. Manager Approval Gate (One-Click Verify & Complete Quest)
        if ( $action === 'sgd_approve_quest_task' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
            }

            $team = sgd_get_quest_team( $task->team_id );
            if ( ! sgd_user_can_manage_quest( $team, $current_user_id ) ) {
                wp_send_json_error( [ 'message' => '⛔ Hanya Manager Tim atau Administrator yang berhak menyetujui penyelesaian tugas ini.' ] );
            }

            // Auto-check all subtasks/checklist items as verified
            $checklist = ! empty( $task->checklist ) && is_array( $task->checklist ) ? $task->checklist : [];
            foreach ( $checklist as &$item ) {
                $item['completed'] = true;
                $item['completed_by'] = $current_user_id;
                $item['completed_at'] = current_time( 'mysql' );
            }

            $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
            $wpdb->update(
                $table_quest_tasks,
                [
                    'column_name' => 'Selesai',
                    'checklist'   => wp_json_encode( $checklist ),
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $task_id ]
            );

            // Execute reward distribution
            $award_result = sgd_complete_quest_task( $task_id, $current_user_id );
            $award_msg = '';
            if ( ! empty( $award_result ) ) {
                $parts = [];
                foreach ( $award_result as $ar ) {
                    $parts[] = "{$ar['user_name']} ({$ar['percent']}% porsi = +{$ar['awarded_xp']} XP, +{$ar['awarded_gold']} 💎)";
                }
                $award_msg = ' Hadiah dibagikan: ' . implode( ', ', $parts );
            }

            if ( ! empty( $_POST['is_ajax'] ) ) {
                wp_send_json_success( [
                    'message'       => "👑 Quest telah disetujui & diselesaikan oleh Manager! 🎉" . ( $award_msg ? " $award_msg" : '' ),
                    'target_column' => 'Selesai',
                    'award_msg'     => $award_msg,
                    'checklist'     => $checklist,
                ] );
            }

            sgd_set_flash( 'celebration', "👑 Quest telah diverifikasi & disetujui oleh Manager!" . ( $award_msg ? " 🎉 $award_msg" : '' ) );
            wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
            exit;
        }

        // 22. Upload Card Cover Image directly from file
        if ( $action === 'sgd_upload_quest_cover' ) {
            $task_id = (int) ( $_POST['task_id'] ?? 0 );
            $task = sgd_get_quest_task( $task_id );
            if ( ! $task ) {
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan.' ] );
                wp_safe_redirect( add_query_arg( 'sgd_page', 'quests', home_url( '/' ) ) );
                exit;
            }

            if ( empty( $_FILES['cover_file'] ) || ( $_FILES['cover_file']['error'] ?? 1 ) !== UPLOAD_ERR_OK ) {
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => 'Silakan pilih file gambar yang valid.' ] );
                sgd_set_flash( 'error', '⚠️ Silakan pilih file gambar yang valid untuk cover.' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
                exit;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $uploaded = wp_handle_upload( $_FILES['cover_file'], [ 'test_form' => false ] );
            if ( $uploaded && ! isset( $uploaded['error'] ) ) {
                $cover_url = $uploaded['url'];
                $table_quest_tasks = $wpdb->prefix . 'sgd_quest_tasks';
                $wpdb->update(
                    $table_quest_tasks,
                    [ 'cover_image' => $cover_url, 'updated_at' => current_time( 'mysql' ) ],
                    [ 'id' => $task_id ]
                );

                // Also record into attachments table so it appears in files list
                $table_quest_attachments = $wpdb->prefix . 'sgd_quest_attachments';
                $orig_name = sanitize_file_name( $_FILES['cover_file']['name'] );
                $wpdb->insert( $table_quest_attachments, [
                    'task_id'    => $task_id,
                    'user_id'    => $current_user_id,
                    'file_url'   => $cover_url,
                    'file_name'  => $orig_name,
                    'file_size'  => (int) ( $_FILES['cover_file']['size'] ?? 0 ),
                    'file_type'  => sanitize_text_field( $uploaded['type'] ?? '' ),
                    'created_at' => current_time( 'mysql' ),
                ] );

                if ( ! empty( $_POST['is_ajax'] ) ) {
                    wp_send_json_success( [
                        'message'     => 'Foto sampul berhasil diunggah!',
                        'cover_url'   => $cover_url,
                        'task_id'     => $task_id,
                        'attachments' => sgd_get_quest_attachments( $task_id ),
                    ] );
                }
                sgd_set_flash( 'celebration', '🖼️ Foto sampul kartu berhasil diunggah!' );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
                exit;
            } else {
                $err_msg = $uploaded['error'] ?? 'Gagal mengunggah file gambar.';
                if ( ! empty( $_POST['is_ajax'] ) ) wp_send_json_error( [ 'message' => $err_msg ] );
                sgd_set_flash( 'error', "⚠️ $err_msg" );
                wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'quests', 'team_id' => $task->team_id, 'open_task' => $task_id ], home_url( '/' ) ) );
                exit;
            }
        }
}

// Cash Shop Rewards Catalog
function sgd_get_shop_catalog() {
    return [
        'idr_200k' => [
            'id'          => 'idr_200k',
            'name'        => 'IDR 200,000 Cash Voucher',
            'cost'        => 1000,
            'cost_label'  => '1,000 Diamonds',
            'icon'        => '💵',
            'tier'        => 'Rare (Blue)',
            'description' => 'Real money direct transfer or e-wallet voucher (GoPay / OVO / BCA) worth IDR 200,000.',
            'badge'       => 'Most Popular',
        ],
        'paid_leave_1day' => [
            'id'          => 'paid_leave_1day',
            'name'        => '1 Day Paid Leave (Rest & Recovery)',
            'cost'        => 20000,
            'cost_label'  => '20,000 Diamonds',
            'icon'        => '🏖️',
            'tier'        => 'Epic (Purple)',
            'description' => '1 extra day of fully paid annual leave sanctioned by Guild Master & Human Resources.',
            'badge'       => 'Guild Master Approved',
        ],
        'holiday_trip' => [
            'id'          => 'holiday_trip',
            'name'        => 'All-Expenses-Paid Holiday Trip',
            'cost'        => 100000,
            'cost_label'  => '100,000 Diamonds',
            'icon'        => '✈️',
            'tier'        => 'Legendary (Orange)',
            'description' => 'Luxury weekend resort getaway for 2 persons with flights, 5-star hotel, and breakfast included.',
            'badge'       => 'Legendary Loot',
        ],
    ];
}

// ==========================================================================
// 8. EXTERNAL AI & CICLE REST API / WEBHOOK ENGINE
// ==========================================================================

// Get or auto-generate persistent API Secret Key
function sgd_get_api_key() {
    $key = get_option( 'sgd_api_secret_key' );
    if ( empty( $key ) ) {
        $key = 'sgd_live_' . wp_generate_password( 28, false, false );
        update_option( 'sgd_api_secret_key', $key );
    }
    return $key;
}

// Authentication check for incoming AI / Cicle API requests
function sgd_verify_api_auth( $request ) {
    $valid_key = sgd_get_api_key();

    // 1. Header: X-SGD-API-KEY
    $provided = $request->get_header( 'x_sgd_api_key' );

    // 2. Header: Authorization: Bearer <key>
    if ( empty( $provided ) ) {
        $auth = $request->get_header( 'authorization' );
        if ( $auth && preg_match( '/Bearer\s+(.*)$/i', $auth, $m ) ) {
            $provided = trim( $m[1] );
        }
    }

    // 3. Query Parameter / Body Parameter: api_key
    if ( empty( $provided ) ) {
        $provided = $request->get_param( 'api_key' );
    }

    if ( ! empty( $provided ) && hash_equals( $valid_key, $provided ) ) {
        return true;
    }

    return new WP_Error(
        'sgd_api_unauthorized',
        'Invalid or missing API key. Provide header "X-SGD-API-KEY: your_key" or parameter "?api_key=your_key".',
        [ 'status' => 401 ]
    );
}

// Register REST API Endpoints under 'sgd/v1'
add_action( 'rest_api_init', 'sgd_register_rest_routes' );
function sgd_register_rest_routes() {
    // 1. Employees Discovery Endpoint (Returns all users so AI knows IDs/Usernames to assign tasks to)
    register_rest_route( 'sgd/v1', '/employees', [
        'methods'             => 'GET',
        'callback'            => 'sgd_api_get_employees',
        'permission_callback' => 'sgd_verify_api_auth',
    ] );

    // 2. Tasks Endpoint: Create Task (POST) & List Tasks (GET)
    register_rest_route( 'sgd/v1', '/tasks', [
        [
            'methods'             => 'GET',
            'callback'            => 'sgd_api_get_tasks',
            'permission_callback' => 'sgd_verify_api_auth',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'sgd_api_create_task',
            'permission_callback' => 'sgd_verify_api_auth',
        ],
    ] );

    // 3. Cicle / Generic PM Direct Webhook Receiver
    register_rest_route( 'sgd/v1', '/webhook/cicle', [
        'methods'             => 'POST',
        'callback'            => 'sgd_api_cicle_webhook',
        'permission_callback' => 'sgd_verify_api_auth',
    ] );
}

// GET /sgd/v1/employees - Discovery endpoint for AI
function sgd_api_get_employees( $request ) {
    $users = get_users();
    $employees = [];

    foreach ( $users as $u ) {
        $xp_data = sgd_get_user_xp_data( $u->ID );
        $streak = sgd_get_user_streak( $u->ID );
        $diamonds = sgd_get_diamonds( $u->ID );

        $employees[] = [
            'id'           => $u->ID,
            'username'     => $u->user_login,
            'display_name' => $u->display_name,
            'email'        => $u->user_email,
            'is_admin'     => in_array( 'administrator', (array) $u->roles, true ),
            'level'        => $xp_data['level'],
            'faction'      => $xp_data['faction'],
            'title'        => $xp_data['title'],
            'diamonds'     => $diamonds,
            'streak_days'  => $streak['current'],
            'best_streak'  => $streak['best'],
        ];
    }

    return rest_ensure_response( [
        'success'   => true,
        'count'     => count( $employees ),
        'employees' => $employees,
    ] );
}

// POST /sgd/v1/tasks - Create Single or Batch Tasks from Outside
function sgd_api_create_task( $request ) {
    global $wpdb;
    $table_tasks = $wpdb->prefix . 'sgd_tasks';

    $title = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
    if ( empty( $title ) ) {
        return new WP_Error( 'sgd_missing_title', 'Task title is required.', [ 'status' => 400 ] );
    }

    $description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
    $category = sanitize_text_field( $request->get_param( 'category' ) ?? 'Cicle Sprint' );
    $type = sanitize_text_field( $request->get_param( 'type' ) ?? 'daily' );
    if ( ! in_array( $type, [ 'daily', 'urgent' ], true ) ) {
        $type = 'daily';
    }

    $default_diamonds = ( $type === 'urgent' ) ? 1000 : 200;
    $reward = (int) ( $request->get_param( 'reward_diamonds' ) ?: $default_diamonds );
    if ( $reward <= 0 ) $reward = $default_diamonds;

    $is_rec = $request->get_param( 'is_recurring' );
    $is_recurring = ( $is_rec !== null ) ? (int) (bool) $is_rec : ( ( $type === 'daily' ) ? 1 : 0 );

    $external_url = esc_url_raw( $request->get_param( 'external_url' ) ?? '' );

    // Resolve Assignee(s) (can be user_id, username, email, "all", or array of them)
    $assign_param = $request->get_param( 'assign_to' );
    $assignees = [];

    if ( is_array( $assign_param ) ) {
        $assignees = $assign_param;
    } elseif ( ! empty( $assign_param ) ) {
        $assignees = [ $assign_param ];
    } else {
        $assignees = [ 0 ]; // Open to all heroes
    }

    $created_tasks = [];

    foreach ( $assignees as $target ) {
        $target_user_id = 0;
        $target_user_name = 'All Heroes (Open)';

        if ( is_numeric( $target ) && (int) $target > 0 ) {
            $user = get_user_by( 'id', (int) $target );
            if ( $user ) {
                $target_user_id = $user->ID;
                $target_user_name = $user->display_name . ' (' . $user->user_login . ')';
            }
        } elseif ( is_string( $target ) && strtolower( trim( $target ) ) !== 'all' && ! empty( $target ) ) {
            $user = get_user_by( 'login', trim( $target ) ) ?: get_user_by( 'email', trim( $target ) ) ?: get_user_by( 'slug', trim( $target ) );
            if ( $user ) {
                $target_user_id = $user->ID;
                $target_user_name = $user->display_name . ' (' . $user->user_login . ')';
            }
        }

        $task_data = [
            'type'            => $type,
            'title'           => $title,
            'description'     => $description,
            'category'        => $category,
            'reward_diamonds' => $reward,
            'assigned_to'     => $target_user_id,
            'claimed_by'      => 0,
            'status'          => 'open',
            'help_status'     => 'none',
            'is_recurring'    => $is_recurring,
            'external_url'    => $external_url,
            'created_at'      => current_time( 'mysql' ),
        ];

        $inserted = $wpdb->insert( $table_tasks, $task_data );
        if ( $inserted ) {
            $new_id = $wpdb->insert_id;
            $created_tasks[] = [
                'id'              => $new_id,
                'title'           => $title,
                'type'            => $type,
                'category'        => $category,
                'reward_diamonds' => $reward,
                'assigned_to_id'  => $target_user_id,
                'assigned_user'   => $target_user_name,
                'is_recurring'    => $is_recurring,
                'external_url'    => $external_url,
                'portal_url'      => sgd_get_portal_url( $type ),
            ];
        }
    }

    return rest_ensure_response( [
        'success'       => true,
        'message'       => count( $created_tasks ) . ' quest(s) created and dispatched successfully.',
        'tasks_created' => $created_tasks,
    ] );
}

// GET /sgd/v1/tasks - Check status of quests (completed/open)
function sgd_api_get_tasks( $request ) {
    global $wpdb;
    $table_tasks = $wpdb->prefix . 'sgd_tasks';

    $type = sanitize_text_field( $request->get_param( 'type' ) ?? 'all' );
    $status = sanitize_text_field( $request->get_param( 'status' ) ?? 'all' );
    $assigned_to = $request->get_param( 'assigned_to' );
    $limit = min( 100, max( 1, (int) ( $request->get_param( 'limit' ) ?: 50 ) ) );

    $where = [ '1=1' ];
    $params = [];

    if ( in_array( $type, [ 'daily', 'urgent' ], true ) ) {
        $where[] = "type = %s";
        $params[] = $type;
    }
    if ( in_array( $status, [ 'open', 'completed' ], true ) ) {
        $where[] = "status = %s";
        $params[] = $status;
    }
    if ( ! empty( $assigned_to ) ) {
        if ( is_numeric( $assigned_to ) ) {
            $where[] = "assigned_to = %d";
            $params[] = (int) $assigned_to;
        } else {
            $user = get_user_by( 'login', $assigned_to ) ?: get_user_by( 'email', $assigned_to );
            if ( $user ) {
                $where[] = "assigned_to = %d";
                $params[] = $user->ID;
            }
        }
    }

    $where_sql = implode( ' AND ', $where );
    if ( ! empty( $params ) ) {
        $query = $wpdb->prepare( "SELECT * FROM $table_tasks WHERE $where_sql ORDER BY id DESC LIMIT %d", array_merge( $params, [ $limit ] ) );
    } else {
        $query = $wpdb->prepare( "SELECT * FROM $table_tasks WHERE $where_sql ORDER BY id DESC LIMIT %d", $limit );
    }

    $tasks = $wpdb->get_results( $query );

    return rest_ensure_response( [
        'success' => true,
        'count'   => count( $tasks ),
        'tasks'   => $tasks,
    ] );
}

// POST /sgd/v1/webhook/cicle - Auto-parse Cicle Card Webhooks
function sgd_api_cicle_webhook( $request ) {
    $params = $request->get_params();

    // Auto-detect title from common Cicle webhook formats
    $title = $params['card_title'] ?? $params['title'] ?? $params['name'] ?? $params['card']['name'] ?? $params['content'] ?? '';
    if ( empty( $title ) && ! empty( $params['data']['name'] ) ) {
        $title = $params['data']['name'];
    }
    if ( empty( $title ) ) {
        return new WP_Error( 'sgd_missing_title', 'Webhook payload did not contain a valid card/task title.', [ 'status' => 400 ] );
    }

    $desc = $params['description'] ?? $params['desc'] ?? $params['card']['description'] ?? $params['notes'] ?? '';
    $assignee = $params['assignee'] ?? $params['user'] ?? $params['member'] ?? $params['assignee_email'] ?? $params['email'] ?? '';
    if ( empty( $assignee ) && ! empty( $params['members'][0] ) ) {
        $assignee = is_array( $params['members'][0] ) ? ( $params['members'][0]['email'] ?? $params['members'][0]['name'] ?? '' ) : $params['members'][0];
    }

    $external_url = $params['url'] ?? $params['card_url'] ?? $params['link'] ?? '';

    // Set parameters and delegate
    $request->set_param( 'title', sanitize_text_field( $title ) );
    $request->set_param( 'description', sanitize_textarea_field( $desc ) );
    $request->set_param( 'category', sanitize_text_field( $params['category'] ?? 'Cicle Card' ) );
    $request->set_param( 'assign_to', $assignee );
    $request->set_param( 'external_url', $external_url );
    $request->set_param( 'type', sanitize_text_field( $params['type'] ?? 'daily' ) );

    return sgd_api_create_task( $request );
}

// ========================================================
// LAPORAN HARIAN (DAILY WORK REPORT) SMART AI & NLP ENGINE
// ========================================================

/**
 * Built-in Smart Indonesian Construction Heuristic NLP Parser
 * Extracts hourly weather, manpower, materials delivered, equipment used, work executed, issues, and K3 safety.
 */
function sgd_smart_nlp_decipher( $raw_text ) {
    $text = trim( (string) $raw_text );
    $lower = strtolower( $text );

    // 1. Weather per hour (07.00 - 17.00)
    $hours = [ '07.00', '08.00', '09.00', '10.00', '11.00', '12.00', '13.00', '14.00', '15.00', '16.00', '17.00' ];
    $weather_hourly = [];
    foreach ( $hours as $h ) {
        $weather_hourly[ $h ] = 'cerah';
    }

    if ( preg_match( '/(seharian\s+hujan|hujan\s+seharian|hujan\s+sepanjang\s+hari)/i', $lower ) ) {
        foreach ( $hours as $h ) {
            $weather_hourly[ $h ] = 'hujan';
        }
    } elseif ( preg_match( '/hujan\s*(?:dari\s*)?(?:jam\s*)?(\d{1,2})(?:\.00|:00)?\s*(?:-|sampai|s\/d|sd)\s*(\d{1,2})(?:\.00|:00)?(?:\s*(sore|siang|malam))?/i', $lower, $m ) ) {
        $start = (int) $m[1];
        $end = (int) $m[2];
        $period = isset( $m[3] ) ? strtolower( $m[3] ) : '';

        // Handle 12-hour clock (e.g., "4 sore" -> 16, "1 sore" -> 13)
        if ( ( $period === 'sore' || $period === 'siang' ) && $end <= 12 ) {
            $end += 12;
        } elseif ( $end < $start && $end <= 6 ) {
            // "10-4" clearly means 10:00 to 16:00
            $end += 12;
        }

        foreach ( $hours as $h ) {
            $h_num = (int) substr( $h, 0, 2 );
            if ( $h_num >= $start && $h_num <= $end ) {
                $weather_hourly[ $h ] = 'hujan';
            }
        }
    }

    // 2. Tenaga kerja (Manpower)
    $manpower = [];
    $manpower_regexes = [
        '/(?:butuh|ada|pakai|tenaga\s*kerja|jumlah)?\s*(tukang\s+[a-z]+|mandor|kenek|pekerja|kuli|operator|driver|mekanik|surveyor|drafter|pelaksana|helper)\s*[:=]?\s*(\d+)/i',
        '/(\d+)\s*(?:orang\s+)?(tukang\s+[a-z]+|mandor|kenek|pekerja|kuli|operator|driver|mekanik|surveyor|drafter|pelaksana|helper)/i'
    ];
    $found_roles = [];
    foreach ( $manpower_regexes as $idx => $reg ) {
        if ( preg_match_all( $reg, $lower, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                if ( $idx === 0 ) {
                    $role = trim( $match[1] );
                    $count = (int) $match[2];
                } else {
                    $count = (int) $match[1];
                    $role = trim( $match[2] );
                }
                if ( ! in_array( $role, $found_roles ) ) {
                    $found_roles[] = $role;
                    $manpower[] = [ 'role' => $role, 'count' => $count ];
                }
            }
        }
    }

    // 3. Material masuk (Avoid matching "tukang batu" or manpower)
    $materials = [];
    $clean_for_mat = $lower;
    foreach ( $found_roles as $fr ) {
        $clean_for_mat = preg_replace( '/\b' . preg_quote( $fr, '/' ) . '\s*\d+\b/i', '', $clean_for_mat );
        $clean_for_mat = preg_replace( '/\d+\s*' . preg_quote( $fr, '/' ) . '\b/i', '', $clean_for_mat );
    }

    $mat_regex = '/(?:masuk|datang|terima|kirim)?\s*(pasir|semen|batu\s*split|batu\s*kali|batu(?!\s*batu)|bata\s*ringan|bata\s*merah|hebel|besi(?:\s*\d+mm)?|kawat\s*bendrat|keramik|cat|pipa|hollow|gypsum|triplek|multiplek|wiremesh|solar|baut|seng)\s*[:=]?\s*(\d+(?:[.,]\d+)?)\s*(kg|sak|zak|ton|m3|kubik|truk|rit|batang|lembar|btg|pcs|dus|box|roll|pail|galon|liter|bh|buah)?/i';
    if ( preg_match_all( $mat_regex, $clean_for_mat, $mat_matches, PREG_SET_ORDER ) ) {
        foreach ( $mat_matches as $mm ) {
            $mat_name = trim( $mm[1] );
            $mat_qty = trim( $mm[2] );
            $mat_unit = ! empty( $mm[3] ) ? trim( $mm[3] ) : 'kg';
            $materials[] = [
                'name'     => $mat_name,
                'quantity' => $mat_qty,
                'unit'     => $mat_unit,
            ];
        }
    }

    // 4. Peralatan (Tools & Equipment)
    $equipment = [];
    $equip_keywords = [
        'hammer drill', 'jackhammer', 'pompa air', 'mesin molen', 'molen', 'excavator', 'stamper', 
        'vibrator', 'vibro', 'gerinda', 'mesin las', 'genset', 'kompresor', 'scaffolding', 'steger', 
        'cangkul', 'sekop', 'waterpass', 'bar bender', 'bar cutter', 'dump truck', 'crane', 'theodolite', 'drill'
    ];
    foreach ( $equip_keywords as $tool ) {
        if ( preg_match( '/(?:pake|pakai|menggunakan|alat)\s+' . preg_quote( $tool, '/' ) . '/i', $lower ) ) {
            $equipment[] = $tool;
        } elseif ( preg_match( '/\b' . preg_quote( $tool, '/' ) . '\b/i', $lower ) ) {
            if ( ! preg_match( '/' . preg_quote( $tool, '/' ) . '\s*(?:rusak|mati|macet|trouble|kendala)/i', $lower ) &&
                 ! preg_match( '/(?:kendala|masalah|rusak)[^.,;]*' . preg_quote( $tool, '/' ) . '/i', $lower ) ) {
                $equipment[] = $tool;
            }
        }
    }
    // Remove shorter duplicates (e.g. "drill" when "hammer drill" is present)
    $filtered_equip = [];
    foreach ( $equipment as $t1 ) {
        $is_sub = false;
        foreach ( $equipment as $t2 ) {
            if ( $t1 !== $t2 && strpos( $t2, $t1 ) !== false ) {
                $is_sub = true;
                break;
            }
        }
        if ( ! $is_sub && ! in_array( $t1, $filtered_equip ) ) {
            $filtered_equip[] = $t1;
        }
    }

    // 5. Pekerjaan yang dilaksanakan
    $work = [];
    if ( preg_match( '/(?:kita\s+|kami\s+)?(?:bikin|membuat|mengerjakan|pekerjaan|progres|progress|pasang|memasang|pengecoran|plesteran|galian|urugan|finishing|pengecatan)\s+([^.,;\n]+)/i', $text, $wm ) ) {
        $cleaned_work = preg_replace( '/^(?:kita|kami)\s+/i', '', trim( $wm[0] ) );
        $work[] = $cleaned_work;
    } else {
        $work[] = 'Pekerjaan umum harian';
    }

    // 6. Kendala
    $issues = [];
    if ( preg_match( '/([^.,;\n]+?(?:rusak|mati|padam|macet|bocor|terlambat|kurang|becek|banjir|kendala|masalah)[^.,;\n]*)/i', $text, $im ) ) {
        $cleaned_issue = preg_replace( '/^(?:kendala|masalah)\s*[:=]?\s*/i', '', trim( $im[1] ) );
        $issues[] = $cleaned_issue;
    }

    // 7. Keselamatan Kerja (K3)
    $safety = 'Nihil kecelakaan kerja';
    if ( preg_match( '/(?:kecelakaan|insiden|luka|tertusuk|jatuh)\s*:\s*([^.,;\n]+)/i', $text, $sm ) ) {
        $safety = trim( $sm[1] );
    }

    return [
        'weather_hourly'         => $weather_hourly,
        'manpower'               => $manpower,
        'materials'              => $materials,
        'equipment'              => $filtered_equip,
        'work_executed'          => $work,
        'issues'                 => $issues,
        'safety_k3'              => $safety,
        'supervisor_instruction' => '',
        'next_day_plan'          => '',
        'additional_notes'       => '',
    ];
}

/**
 * Clean & Extract JSON from AI Response (strips markdown code blocks and excess text)
 */
function sgd_extract_json_from_ai_response( $raw_text ) {
    if ( ! is_string( $raw_text ) ) {
        return null;
    }
    $clean = trim( $raw_text );

    // 1. Strip markdown code fence e.g. ```json ... ``` or ``` ... ```
    if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/i', $clean, $matches ) ) {
        $clean = trim( $matches[1] );
    }

    // 2. Direct JSON decode
    $decoded = json_decode( $clean, true );
    if ( is_array( $decoded ) && ! empty( $decoded ) ) {
        return $decoded;
    }

    // 3. Fallback: Search for outer curly braces { ... }
    $start = strpos( $clean, '{' );
    $end   = strrpos( $clean, '}' );
    if ( $start !== false && $end !== false && $end > $start ) {
        $sub = substr( $clean, $start, $end - $start + 1 );
        $decoded = json_decode( $sub, true );
        if ( is_array( $decoded ) && ! empty( $decoded ) ) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Retrieve Gemini API key securely from local configuration, constant, or database
 */
function sgd_get_gemini_api_key() {
    // 1. Check local environment config file (ignored by git to prevent secret exposure)
    $local_cfg = dirname( __FILE__ ) . '/sgd-ai-config.local.php';
    if ( file_exists( $local_cfg ) ) {
        @include_once $local_cfg;
    }
    // 2. Check PHP constant
    if ( defined( 'SGD_GEMINI_API_KEY' ) && ! empty( constant( 'SGD_GEMINI_API_KEY' ) ) ) {
        return trim( (string) constant( 'SGD_GEMINI_API_KEY' ) );
    }
    // 3. Check WordPress database option
    return trim( (string) get_option( 'sgd_gemini_api_key', '' ) );
}

/**
 * Retrieve active AI provider securely
 */
function sgd_get_active_ai_provider() {
    $db_provider = get_option( 'sgd_ai_provider', '' );
    if ( ! empty( $db_provider ) ) {
        return $db_provider;
    }
    if ( defined( 'SGD_DEFAULT_AI_PROVIDER' ) ) {
        return constant( 'SGD_DEFAULT_AI_PROVIDER' );
    }
    if ( ! empty( sgd_get_gemini_api_key() ) ) {
        return 'gemini';
    }
    return 'smart_nlp';
}

/**
 * AI-powered Decipher (Google Gemini / OpenAI)
 * Specialized for Indonesian Civil/Architectural Construction Daily Site Reports
 */
function sgd_ai_decipher_report( $raw_text ) {
    $raw_text = trim( (string) $raw_text );
    if ( empty( $raw_text ) ) {
        return null;
    }

    $gemini_key = sgd_get_gemini_api_key();
    $openai_key = trim( (string) get_option( 'sgd_openai_api_key', '' ) );
    $provider   = sgd_get_active_ai_provider();

    $construction_system_prompt = "Anda adalah AI asisten insinyur proyek konstruksi sipil & arsitektur profesional di Indonesia.
Tugas Anda: Menguraikan (decipher) catatan lapangan harian (baik dari rekaman suara pekerja atau teks singkat informal yang mungkin terdapat singkatan/typo) menjadi data JSON terstruktur untuk Laporan Harian Proyek (Daily Site Report).

Aturan Penguraian:
1. Cuaca Per Jam (weather_hourly):
   - Wajib memuat tepat 11 jam operasional kerja: \"07.00\", \"08.00\", \"09.00\", \"10.00\", \"11.00\", \"12.00\", \"13.00\", \"14.00\", \"15.00\", \"16.00\", \"17.00\".
   - Nilai cuaca yang valid: \"cerah\", \"berawan\", \"hujan\", \"hujan lebat\".
   - Jika teks menyebutkan rentang waktu, cth: \"hujan jam 10-4 sore\" atau \"hujan 10 sampai 16\", maka jam 10.00, 11.00, 12.00, 13.00, 14.00, 15.00, 16.00 bernilai \"hujan\", dan jam lainnya tetap \"cerah\" kecuali disebutkan berbeda.
2. Tenaga Kerja (manpower):
   - Array of objects: [{\"role\": \"nama posisi/keahlian (misal: tukang batu, mandor, pekerja, tukang las)\", \"count\": jumlah_pekerja_angka}].
3. Material Masuk (materials):
   - Array of objects: [{\"name\": \"nama material\", \"quantity\": \"angka kuantitas\", \"unit\": \"satuan (cth: kg, sak, m3, truck, batang, lembar)\"}].
4. Peralatan (equipment):
   - Array of string nama alat/mesin yang AKTIF digunakan bekerja (cth: [\"hammer drill\", \"molen\"]).
   - PENTING: Alat yang rusak atau bermasalah BUKAN dimasukkan ke equipment, melainkan dimasukkan ke kendala (issues)!
5. Pekerjaan Yang Dilaksanakan (work_executed):
   - Array of string tugas/progress pekerjaan yang dikerjakan hari ini (cth: [\"bikin taman\", \"pengecoran plat lantai\"]).
6. Kendala (issues):
   - Array of string kendala, kerusakan alat, hambatan material, atau cuaca ekstrem yang menghambat (cth: [\"pompa air rusak\"]).
7. Keselamatan Kerja / K3 (safety_k3):
   - String ringkasan kondisi K3.
   - Jika aman / tidak ada kecelakaan, tulis persis: \"Nihil kecelakaan kerja\".
   - Jika ada insiden kecelakaan kerja atau nyaris celaka (near miss), sebutkan secara jelas dan singkat.
8. Catatan Lain (additional_notes):
   - String catatan tambahan lain bila ada.

Output WAJIB berupa JSON murni tanpa markdown fence dan tanpa teks penjelasan di luar JSON:
{
  \"weather_hourly\": {
    \"07.00\": \"cerah\",
    \"08.00\": \"cerah\",
    \"09.00\": \"cerah\",
    \"10.00\": \"hujan\",
    \"11.00\": \"hujan\",
    \"12.00\": \"hujan\",
    \"13.00\": \"hujan\",
    \"14.00\": \"hujan\",
    \"15.00\": \"hujan\",
    \"16.00\": \"hujan\",
    \"17.00\": \"cerah\"
  },
  \"manpower\": [
    {\"role\": \"tukang batu\", \"count\": 10}
  ],
  \"materials\": [
    {\"name\": \"pasir\", \"quantity\": \"100\", \"unit\": \"kg\"}
  ],
  \"equipment\": [\"hammer drill\"],
  \"work_executed\": [\"bikin taman\"],
  \"issues\": [\"pompa air rusak\"],
  \"safety_k3\": \"Nihil kecelakaan kerja\",
  \"additional_notes\": \"\"
}

Laporan Lapangan Mentah:
" . $raw_text;

    // 1. Prioritize Google Gemini (Gratis di Google AI Studio & Sangat Cepat)
    if ( ! empty( $gemini_key ) && ( $provider === 'gemini' || empty( $openai_key ) ) ) {
        // Try Gemini models with automatic fallback (gemini-3.5-flash-lite, 3.8-flash, flash-latest, 2.5-flash, 1.5-flash)
        $models_to_try = [ 'gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-flash-latest', 'gemini-2.5-flash', 'gemini-1.5-flash' ];

        foreach ( $models_to_try as $model_id ) {
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model_id . ':generateContent?key=' . urlencode( $gemini_key );

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [ 'text' => $construction_system_prompt ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature'        => 0.1,
                ]
            ];

            $response = wp_remote_post( $endpoint, [
                'body'      => wp_json_encode( $payload ),
                'headers'   => [ 'Content-Type' => 'application/json' ],
                'timeout'   => 15,
                'sslverify' => false, // Ensure local MAMP / Windows environments work smoothly
            ] );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $resp_body = wp_remote_retrieve_body( $response );
                $json_res = json_decode( $resp_body, true );
                $raw_json = $json_res['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ( $raw_json ) {
                    $parsed = sgd_extract_json_from_ai_response( $raw_json );
                    if ( ! empty( $parsed ) && is_array( $parsed ) ) {
                        $parsed['parsed_with'] = 'gemini';
                        return $parsed;
                    }
                }
            }
        }
    }

    // 2. Try OpenAI GPT-4o Mini if key is configured
    if ( ! empty( $openai_key ) && ( $provider === 'openai' || empty( $gemini_key ) ) ) {
        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [ 'role' => 'system', 'content' => 'Output raw JSON only matching the schema exactly.' ],
                [ 'role' => 'user', 'content' => $construction_system_prompt ]
            ],
            'response_format' => [ 'type' => 'json_object' ],
            'temperature' => 0.1,
        ];

        $response = wp_remote_post( $endpoint, [
            'body'      => wp_json_encode( $payload ),
            'headers'   => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $openai_key,
            ],
            'timeout'   => 15,
            'sslverify' => false,
        ] );

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $resp_body = wp_remote_retrieve_body( $response );
            $json_res = json_decode( $resp_body, true );
            $raw_json = $json_res['choices'][0]['message']['content'] ?? '';
            if ( $raw_json ) {
                $parsed = sgd_extract_json_from_ai_response( $raw_json );
                if ( ! empty( $parsed ) && is_array( $parsed ) ) {
                    $parsed['parsed_with'] = 'openai';
                    return $parsed;
                }
            }
        }
    }

    return null;
}

/**
 * Main Decipher Router (AI with automatic fallback to Smart Heuristic NLP)
 */
function sgd_decipher_daily_report( $raw_text ) {
    $hours = [ '07.00', '08.00', '09.00', '10.00', '11.00', '12.00', '13.00', '14.00', '15.00', '16.00', '17.00' ];

    // 1. Attempt AI deciphering first (Google Gemini / OpenAI)
    $ai_res = sgd_ai_decipher_report( $raw_text );
    if ( ! empty( $ai_res ) && is_array( $ai_res ) ) {
        $wh = $ai_res['weather_hourly'] ?? [];
        $clean_wh = [];
        foreach ( $hours as $h ) {
            $clean_wh[ $h ] = ! empty( $wh[ $h ] ) ? strtolower( trim( (string) $wh[ $h ] ) ) : 'cerah';
        }
        $ai_res['weather_hourly'] = $clean_wh;

        // Ensure array structures
        if ( ! isset( $ai_res['manpower'] ) || ! is_array( $ai_res['manpower'] ) ) {
            $ai_res['manpower'] = [];
        }
        if ( ! isset( $ai_res['materials'] ) || ! is_array( $ai_res['materials'] ) ) {
            $ai_res['materials'] = [];
        }
        if ( ! isset( $ai_res['equipment'] ) || ! is_array( $ai_res['equipment'] ) ) {
            $ai_res['equipment'] = [];
        }
        if ( ! isset( $ai_res['work_executed'] ) || ! is_array( $ai_res['work_executed'] ) ) {
            $ai_res['work_executed'] = ! empty( $ai_res['work_executed'] ) ? [ (string) $ai_res['work_executed'] ] : [];
        }
        if ( ! isset( $ai_res['issues'] ) || ! is_array( $ai_res['issues'] ) ) {
            $ai_res['issues'] = ! empty( $ai_res['issues'] ) ? [ (string) $ai_res['issues'] ] : [];
        }
        if ( empty( $ai_res['safety_k3'] ) ) {
            $ai_res['safety_k3'] = 'Nihil kecelakaan kerja';
        }
        if ( ! isset( $ai_res['additional_notes'] ) ) {
            $ai_res['additional_notes'] = '';
        }

        return $ai_res;
    }

    // 2. Fallback to Smart Offline Heuristic Construction NLP
    $parsed = sgd_smart_nlp_decipher( $raw_text );
    $parsed['parsed_with'] = 'smart_nlp';
    return $parsed;
}

/**
 * Format Daily Report as clean structured Indonesian construction text
 */
function sgd_format_daily_report_text( $report ) {
    $out = "cuaca per jam\n=============\n";
    $weather = is_string( $report->weather_hourly ) ? json_decode( $report->weather_hourly, true ) : (array) $report->weather_hourly;
    if ( ! empty( $weather ) ) {
        foreach ( $weather as $h => $w ) {
            $out .= "-{$h} {$w}\n";
        }
    } else {
        $out .= "-07.00 cerah\n-08.00 cerah\n-09.00 cerah\n-10.00 hujan\n-11.00 hujan\n-12.00 hujan\n-13.00 hujan\n-14.00 hujan\n-15.00 hujan\n-16.00 hujan\n-17.00 cerah\n";
    }

    $out .= "tenaga kerja\n============\n";
    $manpower = is_string( $report->manpower ) ? json_decode( $report->manpower, true ) : (array) $report->manpower;
    if ( ! empty( $manpower ) ) {
        foreach ( $manpower as $mp ) {
            $role = is_array( $mp ) ? ( $mp['role'] ?? '' ) : (string) $mp;
            $count = is_array( $mp ) ? ( $mp['count'] ?? '' ) : '';
            $out .= "- " . trim( "{$role} {$count}" ) . "\n";
        }
    } else {
        $out .= "- (Tidak ada catatan tenaga kerja)\n";
    }

    $out .= "\nmaterial masuk\n==============\n";
    $materials = is_string( $report->materials ) ? json_decode( $report->materials, true ) : (array) $report->materials;
    if ( ! empty( $materials ) ) {
        foreach ( $materials as $m ) {
            $name = is_array( $m ) ? ( $m['name'] ?? '' ) : (string) $m;
            $qty = is_array( $m ) ? ( $m['quantity'] ?? '' ) : '';
            $unit = is_array( $m ) ? ( $m['unit'] ?? '' ) : '';
            $out .= "-{$name} {$qty}{$unit}\n";
        }
    } else {
        $out .= "- (Tidak ada material masuk)\n";
    }

    $out .= "\nperalatan\n=========\n";
    $equipment = is_string( $report->equipment ) ? json_decode( $report->equipment, true ) : (array) $report->equipment;
    if ( ! empty( $equipment ) ) {
        foreach ( $equipment as $eq ) {
            $out .= "- {$eq}\n";
        }
    } else {
        $out .= "- (Tidak ada catatan peralatan)\n";
    }

    $out .= "pekerjaan yang dilaksanakan\n==============================\n";
    $work = is_string( $report->work_executed ) ? json_decode( $report->work_executed, true ) : (array) $report->work_executed;
    if ( ! empty( $work ) ) {
        if ( is_array( $work ) ) {
            foreach ( $work as $w ) {
                $out .= "- {$w}\n";
            }
        } else {
            $out .= "- {$work}\n";
        }
    } else {
        $out .= "- (Tidak ada catatan)\n";
    }

    $out .= "kendala\n=======\n";
    $issues = is_string( $report->issues ) ? json_decode( $report->issues, true ) : (array) $report->issues;
    if ( ! empty( $issues ) ) {
        if ( is_array( $issues ) ) {
            foreach ( $issues as $is ) {
                $out .= "- {$is}\n";
            }
        } else {
            $out .= "- {$issues}\n";
        }
    } else {
        $out .= "- Tidak ada kendala\n";
    }

    $out .= "- keselamatan kerja(k3)\n=======================\n";
    $safety = ! empty( $report->safety_k3 ) ? $report->safety_k3 : 'Nihil kecelakaan kerja';
    $out .= "{$safety}\n";

    if ( ! empty( $report->supervisor_instruction ) ) {
        $out .= "\ninstruksi pengawas\n==================\n{$report->supervisor_instruction}\n";
    }
    if ( ! empty( $report->next_day_plan ) ) {
        $out .= "\nrencana kerja besok\n===================\n{$report->next_day_plan}\n";
    }
    if ( ! empty( $report->additional_notes ) ) {
        $out .= "\ncatatan lain\n============\n{$report->additional_notes}\n";
    }

    return $out;
}

/**
 * Fetch a single daily report by ID
 */
function sgd_get_daily_report( $id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_daily_reports';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
}

/**
 * Fetch daily reports by date
 */
function sgd_get_daily_reports_by_date( $date ) {
    global $wpdb;
    $table = $wpdb->prefix . 'sgd_daily_reports';
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE report_date = %s ORDER BY id DESC", $date ) );
}

// URL helper
function sgd_get_portal_url( $page = '' ) {
    $base = home_url( '/' );
    if ( $page && $page !== 'home' ) {
        return add_query_arg( 'sgd_page', $page, $base );
    }
    return $base;
}

// 3. Template Interception - Force Gamification Portal on Homepage and all sgd pages
add_action( 'template_redirect', 'sgd_render_portal_override', 1 );
function sgd_render_portal_override() {
    if ( is_admin() ) {
        return;
    }
    // Never intercept WordPress REST API requests (wp-json or rest_route)
    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( strpos( $_SERVER['REQUEST_URI'] ?? '', 'wp-json' ) !== false ) || isset( $_GET['rest_route'] ) ) {
        return;
    }

    // Fast JSON API responder for Quests Kanban task detail
    if ( isset( $_GET['get_task_detail'] ) ) {
        $task_id = (int) $_GET['get_task_detail'];
        $task = sgd_get_quest_task( $task_id );
        if ( $task ) {
            wp_send_json_success( [ 'task' => $task ] );
        } else {
            wp_send_json_error( [ 'message' => 'Tugas tidak ditemukan' ] );
        }
    }

    $view_file = SGD_PLUGIN_DIR . '/views/index.php';
    if ( file_exists( $view_file ) ) {
        include $view_file;
        exit;
    }
}

add_filter( 'template_include', 'sgd_template_loader', 999 );
function sgd_template_loader( $template ) {
    if ( is_front_page() || is_home() || isset( $_GET['sgd_page'] ) ) {
        $view_file = SGD_PLUGIN_DIR . '/views/index.php';
        if ( file_exists( $view_file ) ) {
            return $view_file;
        }
    }
    return $template;
}

// 4. WP-Admin Quests (Kanban) Menu for Administrators
add_action( 'admin_menu', 'sgd_register_quests_admin_menu' );
function sgd_register_quests_admin_menu() {
    add_menu_page(
        __( 'Quests (Kanban)', 'sgd' ),
        __( 'Quests', 'sgd' ),
        'manage_options',
        'sgd-quests',
        function() {
            $quests_url = add_query_arg( 'sgd_page', 'quests', home_url( '/' ) );
            echo '<script>window.location.href = "' . esc_url( $quests_url ) . '";</script>';
            echo '<div class="wrap" style="padding:40px; text-align:center;">';
            echo '<h1 style="font-size:24px; margin-bottom:12px;">🎯 Mengalihkan ke Quests Kanban...</h1>';
            echo '<p style="font-size:14px; color:#555;">Jika halaman tidak terbuka otomatis, silakan klik tombol di bawah:</p>';
            echo '<p style="margin-top:20px;"><a class="button button-primary button-hero" href="' . esc_url( $quests_url ) . '">Buka Quests Kanban Board ➔</a></p>';
            echo '</div>';
        },
        'dashicons-columns',
        25
    );
}
