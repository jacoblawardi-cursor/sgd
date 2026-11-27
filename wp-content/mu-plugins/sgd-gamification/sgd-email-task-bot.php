<?php
/**
 * SGD Gmail AI Quest Bot
 * Automatically reads emails from sgdquest@gmail.com via IMAP SSL,
 * deciphers them with Google Gemini AI into structured task attributes (Rank S-F, rewards, checklists, deadlines),
 * and creates Kanban task cards in wp_sgd_quest_tasks.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get email bot configuration
 */
function sgd_get_email_bot_config() {
    $email = defined( 'SGD_GMAIL_ADDRESS' ) ? SGD_GMAIL_ADDRESS : get_option( 'sgd_gmail_address', 'sgdquest@gmail.com' );
    $pass  = defined( 'SGD_GMAIL_APP_PASSWORD' ) ? SGD_GMAIL_APP_PASSWORD : get_option( 'sgd_gmail_app_password', '' );
    
    // Clean password from spaces
    $pass = str_replace( ' ', '', $pass );

    return [
        'email'            => trim( (string) $email ),
        'password'         => trim( (string) $pass ),
        'mailbox'          => '{imap.gmail.com:993/imap/ssl}INBOX',
        'default_team_id'  => (int) get_option( 'sgd_email_bot_team_id', 1 ),
        'default_column'   => 'To do list',
    ];
}

/**
 * Helper to call Gemini AI for deciphering email content
 */
function sgd_ai_decipher_email( $subject, $body, $from ) {
    $gemini_key = function_exists( 'sgd_get_gemini_api_key' ) ? sgd_get_gemini_api_key() : '';
    if ( empty( $gemini_key ) && defined( 'SGD_GEMINI_API_KEY' ) ) {
        $gemini_key = SGD_GEMINI_API_KEY;
    }

    // Get team members for AI prompt context
    $all_users = get_users();
    $roster_hints = [];
    foreach ( $all_users as $u ) {
        $first_name = explode( ' ', $u->display_name )[0];
        $roster_hints[] = "{$u->display_name} (tag: @{$first_name} or @{$u->user_login})";
    }
    $roster_str = implode( ', ', $roster_hints );

    $system_prompt = "You are an intelligent project management AI assistant for a gamified workplace (SGD Quests).
Your role is to decipher an incoming email and convert it into a well-structured task card.

Here is the email:
From: {$from}
Subject: {$subject}
Body:
{$body}

Available team members in the system:
{$roster_str}
If the email mentions or tags any member (e.g. '@jaina' or '@jainabeli' or 'Jaina'), extract their exact first name or username into 'assigned_names' (e.g. ['jaina']).

Guidelines for Quest Rank (S/A/B/C/D/E/F):
- S: High-level policy, company-wide, long-term strategic initiatives (Gate Lv 20).
- A: Independent business division, annual project / core system (Gate Lv 15).
- B: Division management, monthly or quarterly milestone (Gate Lv 10).
- C: Department management, weekly-monthly task (Gate Lv 8).
- D: Basic managerial, weekly task (Gate Lv 6).
- E: Specific task-based execution (Gate Lv 4).
- F: Consistency, routine daily task, physical check (Gate Lv 1).
If the email mentions a specific rank like 'Rank B' or 'Level C', use that explicit rank.

Task Attributes to extract:
1. title: Concise, professional task title (max 90 chars).
2. description: Well-formatted HTML (<p>, <strong>, <ul>, <li>) summarizing the goals, instructions, and context.
3. quest_level: One uppercase letter from 'S', 'A', 'B', 'C', 'D', 'E', 'F'. Default to 'D' if standard.
4. deadline: Exact date/time in 'YYYY-MM-DD HH:MM:SS' format calculated relative to current date (" . current_time( 'Y-m-d H:i:s' ) . "), or null if not mentioned.
5. assigned_names: Array of strings of mentioned team member names or handles (e.g. ['Jacob', 'Sarah', 'jaina']).
6. checklist: Array of actionable subtask strings extracted from the email.
7. team_hint: Name of team or project mentioned in the email if any (e.g. 'Proyek', 'Frontend', 'HQ'), or null.
8. is_secret: Boolean true if the email has markers like [RAHASIA], [SECRET], 'rahasia', or confidential matters.
9. reward_xp: Integer (e.g. S=1000, A=700, B=500, C=350, D=200, E=120, F=60).
10. reward_gold: Integer (e.g. S=250, A=150, B=100, C=70, D=40, E=20, F=10).

Return ONLY a valid JSON object matching the keys above. No markdown code blocks, no other text.";

    // Fallback default in case AI fails
    $fallback = [
        'title'          => sanitize_text_field( $subject ?: 'Tugas Baru dari Email' ),
        'description'    => wpautop( esc_html( $body ) ),
        'quest_level'    => 'D',
        'deadline'       => null,
        'assigned_names' => [],
        'checklist'      => [],
        'team_hint'      => null,
        'is_secret'      => false,
        'reward_xp'      => 200,
        'reward_gold'    => 50,
    ];

    if ( empty( $gemini_key ) ) {
        return $fallback;
    }

    $models_to_try = [ 'gemini-flash-latest', 'gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-flash-lite-latest', 'gemini-1.5-flash' ];

    foreach ( $models_to_try as $model_id ) {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model_id . ':generateContent?key=' . urlencode( $gemini_key );

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $system_prompt ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ]
        ];

        $response = wp_remote_post( $endpoint, [
            'body'      => wp_json_encode( $payload ),
            'headers'   => [ 'Content-Type' => 'application/json' ],
            'timeout'   => 15,
            'sslverify' => false,
        ] );

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $resp_body = wp_remote_retrieve_body( $response );
            $data = json_decode( $resp_body, true );
            $raw_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            // Extract json substring
            if ( preg_match( '/\{[\s\S]*\}/', $raw_text, $matches ) ) {
                $parsed = json_decode( $matches[0], true );
                if ( ! empty( $parsed ) && is_array( $parsed ) && ! empty( $parsed['title'] ) ) {
                    return array_merge( $fallback, $parsed );
                }
            }
        }
    }

    return $fallback;
}

/**
 * Helper to extract clean plain text from IMAP email message
 */
function sgd_extract_clean_email_body( $inbox, $msg_id ) {
    $structure = @imap_fetchstructure( $inbox, $msg_id );
    $body = '';

    if ( ! empty( $structure->parts ) ) {
        // Multi-part message: try part 1 (usually text/plain)
        $body = imap_fetchbody( $inbox, $msg_id, "1" );
        if ( empty( $body ) ) {
            $body = imap_fetchbody( $inbox, $msg_id, "1.1" );
        }
        if ( empty( $body ) ) {
            $body = imap_body( $inbox, $msg_id );
        }
    } else {
        $body = imap_body( $inbox, $msg_id );
    }

    // Decode quoted-printable or base64 if needed
    if ( preg_match( '/=[0-9A-F]{2}/', $body ) ) {
        $body = quoted_printable_decode( $body );
    }

    // Strip HTML tags and extra boundary markers
    $body = strip_tags( $body );
    $body = preg_replace( '/------=_Part_[a-zA-Z0-9_\-\.\=]+/', '', $body );
    $body = preg_replace( '/Content-Type:[\s\S]*?7bit/i', '', $body );
    return trim( $body );
}

/**
 * Main function: Connect via IMAP, fetch unimported emails, and generate tasks
 */
function sgd_process_email_tasks() {
    $cfg = sgd_get_email_bot_config();

    if ( empty( $cfg['email'] ) || empty( $cfg['password'] ) ) {
        return [
            'success'   => false,
            'processed' => 0,
            'message'   => 'Alamat email atau App Password belum disetel di konfigurasi.'
        ];
    }

    if ( ! function_exists( 'imap_open' ) ) {
        return [
            'success'   => false,
            'processed' => 0,
            'message'   => 'Modul PHP IMAP (php_imap.dll) belum aktif di server.'
        ];
    }

    // Connect to Gmail IMAP
    $inbox = @imap_open( $cfg['mailbox'], $cfg['email'], $cfg['password'], 0, 1, [ 'DISABLE_AUTHENTICATOR' => 'GSSAPI' ] );

    if ( ! $inbox ) {
        $err = imap_errors();
        $err_msg = ! empty( $err ) ? implode( ', ', $err ) : 'Gagal membuka koneksi IMAP Gmail';
        return [
            'success'   => false,
            'processed' => 0,
            'message'   => $err_msg
        ];
    }

    // Get list of already imported Message-IDs to prevent duplicates
    $imported_message_ids = get_option( 'sgd_imported_email_ids', [] );
    if ( ! is_array( $imported_message_ids ) ) {
        $imported_message_ids = [];
    }

    // Search for unseen or recent messages
    $unseen_ids = imap_search( $inbox, 'UNSEEN' );
    $recent_all = imap_search( $inbox, 'ALL' );

    $candidate_ids = [];
    if ( ! empty( $unseen_ids ) ) {
        $candidate_ids = array_merge( $candidate_ids, $unseen_ids );
    }
    // Also include the latest 10 messages from ALL
    if ( ! empty( $recent_all ) ) {
        $latest_all = array_slice( $recent_all, -10 );
        $candidate_ids = array_merge( $candidate_ids, $latest_all );
    }
    $candidate_ids = array_unique( $candidate_ids );

    if ( empty( $candidate_ids ) ) {
        imap_close( $inbox );
        return [
            'success'   => true,
            'processed' => 0,
            'message'   => 'Kotak masuk sudah dicek. Tidak ada email baru di ' . $cfg['email']
        ];
    }

    global $wpdb;
    $all_users = get_users();
    $all_teams = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}sgd_quest_teams ORDER BY id ASC" );
    $created_tasks = [];
    $processed_count = 0;

    foreach ( $candidate_ids as $msg_id ) {
        $header = @imap_headerinfo( $inbox, $msg_id );
        if ( ! $header ) continue;

        $message_id_key = trim( $header->message_id ?? ( $header->Date . '_' . $header->subject ) );
        if ( in_array( $message_id_key, $imported_message_ids, true ) ) {
            continue; // Already processed
        }

        $from = isset( $header->fromaddress ) ? ( function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader( $header->fromaddress ) : $header->fromaddress ) : '';
        $subject = isset( $header->subject ) ? ( function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader( $header->subject ) : $header->subject ) : 'Tugas Baru';

        // Ignore automated security / alert emails from Google
        if ( preg_match( '/(no-reply|accounts\.google\.com|mailer-daemon|notifications@google|google\.com)/i', $from ) ) {
            $imported_message_ids[] = $message_id_key;
            imap_setflag_full( $inbox, (string)$msg_id, "\\Seen" );
            continue;
        }

        // Fetch and clean body
        $body_clean = sgd_extract_clean_email_body( $inbox, $msg_id );

        // Decipher email using Gemini AI
        $parsed = sgd_ai_decipher_email( $subject, $body_clean, $from );

        // Resolve Target Team
        $target_team_id = $cfg['default_team_id'];
        if ( ! empty( $parsed['team_hint'] ) && ! empty( $all_teams ) ) {
            $hint = strtolower( trim( $parsed['team_hint'] ) );
            foreach ( $all_teams as $tm ) {
                if ( stripos( strtolower( $tm->name ), $hint ) !== false ) {
                    $target_team_id = (int) $tm->id;
                    break;
                }
            }
        }

        // Match assigned members (e.g. @jaina -> Jaina the Archmage, user ID 3)
        $assigned_ids = [];
        if ( ! empty( $parsed['assigned_names'] ) && is_array( $parsed['assigned_names'] ) ) {
            foreach ( $parsed['assigned_names'] as $name_mention ) {
                $name_clean = strtolower( trim( ltrim( $name_mention, '@' ) ) );
                if ( empty( $name_clean ) ) continue;

                foreach ( $all_users as $u ) {
                    $u_login = strtolower( $u->user_login );
                    $u_disp  = strtolower( $u->display_name );
                    $u_first = strtolower( explode( ' ', $u->display_name )[0] );
                    $u_email = strtolower( $u->user_email );

                    if ( 
                        strpos( $u_disp, $name_clean ) !== false || 
                        strpos( $u_login, $name_clean ) !== false || 
                        strpos( $u_email, $name_clean ) !== false ||
                        ( strlen( $u_first ) >= 3 && strpos( $name_clean, $u_first ) !== false ) ||
                        ( strlen( $u_login ) >= 3 && strpos( $name_clean, $u_login ) !== false )
                    ) {
                        $assigned_ids[] = (int) $u->ID;
                    }
                }
            }
        }
        $assigned_ids = array_unique( $assigned_ids );

        // Match sender to WP User if possible
        $sender_user_id = 1;
        if ( preg_match( '/[\w\.\-]+@[\w\.\-]+/', $from, $email_match ) ) {
            $sender_email = strtolower( $email_match[0] );
            $matched_user = get_user_by( 'email', $sender_email );
            if ( $matched_user ) {
                $sender_user_id = (int) $matched_user->ID;
            }
        }

        // Format checklist items
        $formatted_checklist = [];
        if ( ! empty( $parsed['checklist'] ) && is_array( $parsed['checklist'] ) ) {
            foreach ( $parsed['checklist'] as $idx => $step_text ) {
                $formatted_checklist[] = [
                    'id'   => 'chk_ai_' . time() . '_' . $idx,
                    'text' => sanitize_text_field( $step_text ),
                    'done' => false,
                ];
            }
        }

        // Format deadline
        $final_deadline = null;
        if ( ! empty( $parsed['deadline'] ) ) {
            $d_time = strtotime( $parsed['deadline'] );
            if ( $d_time && $d_time > 0 ) {
                $final_deadline = date( 'Y-m-d H:i:s', $d_time );
            }
        }

        // Normalize quest level
        $q_level = strtoupper( trim( $parsed['quest_level'] ?? 'D' ) );
        if ( ! in_array( $q_level, [ 'S', 'A', 'B', 'C', 'D', 'E', 'F' ], true ) ) {
            $q_level = 'D';
        }

        // Calculate sort order
        $max_sort = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$wpdb->prefix}sgd_quest_tasks WHERE team_id = %d AND column_name = 'To do list'",
            $target_team_id
        ) );

        // Append email source note in description
        $desc_html = $parsed['description'];
        $desc_html .= '<div style="margin-top:16px; padding:10px 14px; background:rgba(0,229,255,0.06); border-left:3px solid #00e5ff; border-radius:4px; font-size:11px; color:#8fa0bd;">';
        $desc_html .= '🤖 <strong>Dibuat Otomatis oleh SGD AI Quest Bot:</strong><br>';
        $desc_html .= '📧 Dari: <em>' . esc_html( $from ) . '</em><br>';
        $desc_html .= '📅 Subjek Asli: <em>' . esc_html( $subject ) . '</em>';
        $desc_html .= '</div>';

        // Insert into database
        $insert_data = [
            'team_id'          => $target_team_id,
            'column_name'      => 'To do list',
            'title'            => sanitize_text_field( $parsed['title'] ),
            'description'      => $desc_html,
            'deadline'         => $final_deadline,
            'assigned_members' => implode( ',', $assigned_ids ),
            'quest_level'      => $q_level,
            'reward_type'      => 'both',
            'reward_xp'        => max( 10, (int) ( $parsed['reward_xp'] ?? 200 ) ),
            'reward_gold'      => max( 5, (int) ( $parsed['reward_gold'] ?? 50 ) ),
            'is_secret'        => ! empty( $parsed['is_secret'] ) ? 1 : 0,
            'checklist'        => ! empty( $formatted_checklist ) ? wp_json_encode( $formatted_checklist ) : null,
            'sort_order'       => $max_sort + 1,
            'created_by'       => $sender_user_id,
            'created_at'       => current_time( 'mysql' ),
            'updated_at'       => current_time( 'mysql' ),
        ];

        $res = $wpdb->insert( "{$wpdb->prefix}sgd_quest_tasks", $insert_data );

        if ( $res ) {
            $new_task_id = $wpdb->insert_id;
            $created_tasks[] = [
                'id'          => $new_task_id,
                'title'       => $insert_data['title'],
                'quest_level' => $q_level,
                'team_id'     => $target_team_id,
                'from'        => $from,
            ];
            $processed_count++;
            $imported_message_ids[] = $message_id_key;
        }

        // Mark message as seen in Gmail
        imap_setflag_full( $inbox, (string)$msg_id, "\\Seen" );
    }

    // Expunge and close connection
    imap_close( $inbox, CL_EXPUNGE );

    // Keep up to 500 recent imported message IDs
    if ( count( $imported_message_ids ) > 500 ) {
        $imported_message_ids = array_slice( $imported_message_ids, -500 );
    }
    update_option( 'sgd_imported_email_ids', $imported_message_ids );
    update_option( 'sgd_email_bot_last_sync', current_time( 'mysql' ) );
    update_option( 'sgd_email_bot_last_count', $processed_count );

    return [
        'success'   => true,
        'processed' => $processed_count,
        'tasks'     => $created_tasks,
        'message'   => "Berhasil memproses $processed_count email menjadi kartu tugas baru!"
    ];
}

/**
 * Handle manual sync trigger from admin
 */
add_action( 'init', function() {
    if ( isset( $_POST['sgd_action'] ) && $_POST['sgd_action'] === 'sgd_sync_email_tasks' ) {
        if ( ! current_user_can( 'administrator' ) ) {
            wp_die( 'Akses ditolak.' );
        }

        // Save default team if selected
        if ( isset( $_POST['sgd_email_bot_team_id'] ) ) {
            update_option( 'sgd_email_bot_team_id', (int) $_POST['sgd_email_bot_team_id'] );
        }

        $result = sgd_process_email_tasks();
        if ( ! empty( $result['tasks'] ) ) {
            $first_task = $result['tasks'][0];
            sgd_set_flash( 'celebration', "🤖 Berhasil membuat " . count( $result['tasks'] ) . " tugas baru dari email! Tugas: \"" . esc_html( $first_task['title'] ) . "\"" );
        } else {
            sgd_set_flash( $result['success'] ? 'info' : 'error', "📬 " . $result['message'] );
        }

        wp_safe_redirect( add_query_arg( [ 'sgd_page' => 'admin', 'tab' => 'api' ], home_url( '/' ) ) );
        exit;
    }
} );

/**
 * Register 5-Minute Cron Schedule Interval
 */
add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['every_five_minutes'] ) ) {
        $schedules['every_five_minutes'] = [
            'interval' => 300, // 5 minutes in seconds
            'display'  => __( 'Every 5 Minutes (SGD Email Bot)', 'sgd' ),
        ];
    }
    return $schedules;
} );

/**
 * Register & Manage WP-Cron hook for background syncing (Every 5 Minutes)
 */
if ( wp_get_schedule( 'sgd_check_email_tasks_cron' ) !== 'every_five_minutes' ) {
    wp_clear_scheduled_hook( 'sgd_check_email_tasks_cron' );
    wp_schedule_event( time(), 'every_five_minutes', 'sgd_check_email_tasks_cron' );
}
add_action( 'sgd_check_email_tasks_cron', 'sgd_process_email_tasks' );
