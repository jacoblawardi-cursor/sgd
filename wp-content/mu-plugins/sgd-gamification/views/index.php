<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/header.php';

$page = sanitize_text_field( $_GET['sgd_page'] ?? 'home' );

switch ( $page ) {
    case 'daily':
        require_once __DIR__ . '/daily-tasks.php';
        break;
    case 'laporan-harian':
        require_once __DIR__ . '/laporan-harian.php';
        break;
    case 'urgent':
        require_once __DIR__ . '/urgent-tasks.php';
        break;
    case 'achievements':
        require_once __DIR__ . '/achievements.php';
        break;
    case 'leaderboard':
        require_once __DIR__ . '/leaderboard.php';
        break;
    case 'wheel':
        require_once __DIR__ . '/wheel.php';
        break;
    case 'bounties':
        require_once __DIR__ . '/bounties.php';
        break;
    case 'shop':
        require_once __DIR__ . '/cash-shop.php';
        break;
    case 'admin':
        require_once __DIR__ . '/admin.php';
        break;
    case 'quests':
        require_once __DIR__ . '/quests.php';
        break;
    case 'login':
        require_once __DIR__ . '/login.php';
        break;
    case 'home':
    default:
        require_once __DIR__ . '/home.php';
        break;
}

require_once __DIR__ . '/footer.php';
