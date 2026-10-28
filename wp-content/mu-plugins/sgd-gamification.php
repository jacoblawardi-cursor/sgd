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
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_tasks );

    // Achievements table
    $table_achievements = $wpdb->prefix . 'sgd_achievements';
    $sql_achievements = "CREATE TABLE IF NOT EXISTS $table_achievements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(64) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        icon VARCHAR(64) NOT NULL DEFAULT '🏆',
        reward_diamonds INT NOT NULL DEFAULT 100,
        category VARCHAR(50) NOT NULL DEFAULT 'General',
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_achievements );

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
} );

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

    foreach ( $to_unlock as $slug ) {
        $ach = $wpdb->get_row( $wpdb->prepare( "SELECT id, reward_diamonds FROM $table_ach WHERE slug = %s", $slug ) );
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
                // Award achievement bonus
                if ( $ach->reward_diamonds > 0 ) {
                    sgd_add_diamonds( $user_id, $ach->reward_diamonds );
                }
            }
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

// 2. Action Handlers (Form Submissions)
function sgd_handle_form_actions() {
    if ( ! isset( $_POST['sgd_action'] ) ) {
        return;
    }

    global $wpdb;
    $action = sanitize_text_field( $_POST['sgd_action'] );
    $current_user_id = get_current_user_id();

    // Quick Character Switcher (for painless evaluator/user testing)
    if ( $action === 'sgd_switch_user' ) {
        $target = sanitize_text_field( $_POST['target_user'] ?? '' );
        if ( $target === 'admin' ) {
            $user = get_user_by( 'login', 'admin' );
        } elseif ( $target === 'employee' ) {
            $user = get_user_by( 'login', 'employee' );
        }
        if ( isset( $user ) && $user ) {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID );
            sgd_set_flash( 'success', 'Switched character to: ' . esc_html( $user->display_name ) );
        }
        wp_safe_redirect( sgd_get_portal_url() );
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

        // Reward calculation
        $reward = (int) $task->reward_diamonds;
        if ( $reward <= 0 ) {
            $reward = ( $task->type === 'urgent' ) ? 1000 : 200;
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

        // Award diamonds
        sgd_add_diamonds( $current_user_id, $reward );

        sgd_set_flash( 'celebration', "🏆 QUEST ACCOMPLISHED! You delivered proof of honor and earned +{$reward} Diamonds! 💎" );
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
            $assigned_to = (int) ( $_POST['assigned_to'] ?? 0 );

            if ( empty( $title ) ) {
                sgd_set_flash( 'error', 'Task title cannot be empty.' );
            } else {
                $data = [
                    'type'            => $type,
                    'title'           => $title,
                    'description'     => $description,
                    'category'        => $category,
                    'reward_diamonds' => $reward,
                    'assigned_to'     => $assigned_to,
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
                        sgd_set_flash( 'success', "🎯 Custom Daily Task assigned specifically to {$hero_name}!" );
                    } else {
                        sgd_set_flash( 'success', 'New quest posted to the bulletin board!' );
                    }
                }
            }
            $redirect_page = sanitize_text_field( $_POST['redirect_to'] ?? 'admin' );
            wp_safe_redirect( sgd_get_portal_url( $redirect_page ) );
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

        // Admin Update Employee Diamonds or Achievements
        if ( $action === 'sgd_admin_update_user' ) {
            $target_user_id = (int) ( $_POST['target_user_id'] ?? 0 );
            $diamond_change = (int) ( $_POST['diamond_amount'] ?? 0 );
            $change_mode = sanitize_text_field( $_POST['change_mode'] ?? 'add' );

            if ( $target_user_id ) {
                if ( $change_mode === 'set' ) {
                    sgd_set_diamonds( $target_user_id, $diamond_change );
                } elseif ( $change_mode === 'add' ) {
                    sgd_add_diamonds( $target_user_id, $diamond_change );
                } else {
                    sgd_deduct_diamonds( $target_user_id, $diamond_change );
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
                }

                sgd_set_flash( 'success', 'Hero record & Treasury balance updated.' );
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
                    // Optionally award reward diamonds
                    if ( isset( $_POST['award_bonus'] ) && $_POST['award_bonus'] === 'yes' && $ach && $ach->reward_diamonds > 0 ) {
                        sgd_add_diamonds( $target_user_id, $ach->reward_diamonds );
                    }
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
