<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$current_user_id = get_current_user_id();
$is_admin = current_user_can( 'administrator' );
$is_logged_in = is_user_logged_in();

// Date selection
$selected_date = sanitize_text_field( $_GET['report_date'] ?? current_time( 'Y-m-d' ) );
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
    $selected_date = current_time( 'Y-m-d' );
}

$prev_date = date( 'Y-m-d', strtotime( $selected_date . ' -1 day' ) );
$next_date = date( 'Y-m-d', strtotime( $selected_date . ' +1 day' ) );
$today_date = current_time( 'Y-m-d' );

// Fetch reports for selected date
$reports_for_date = sgd_get_daily_reports_by_date( $selected_date );
$total_reports = count( $reports_for_date );

// Active report selection
$req_report_id = (int) ( $_GET['report_id'] ?? 0 );
$active_report = null;
if ( $req_report_id ) {
    foreach ( $reports_for_date as $r ) {
        if ( (int) $r->id === $req_report_id ) {
            $active_report = $r;
            break;
        }
    }
    if ( ! $active_report ) {
        $active_report = sgd_get_daily_report( $req_report_id );
    }
}
if ( ! $active_report && ! empty( $reports_for_date ) ) {
    $active_report = $reports_for_date[0];
}

$show_new_form = isset( $_GET['action'] ) && $_GET['action'] === 'new';
if ( empty( $reports_for_date ) ) {
    $show_new_form = true;
}

// Indonesian Day Names & Months
$day_names = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$month_names = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$timestamp = strtotime( $selected_date );
$day_en = date( 'l', $timestamp );
$day_id = $day_names[ $day_en ] ?? $day_en;
$date_part = date( 'd', $timestamp );
$month_part = $month_names[ date( 'm', $timestamp ) ] ?? date( 'M', $timestamp );
$year_part = date( 'Y', $timestamp );
$formatted_date_id = "{$day_id}, {$date_part} {$month_part} {$year_part}";

// AI settings
$gemini_key = function_exists( 'sgd_get_gemini_api_key' ) ? sgd_get_gemini_api_key() : get_option( 'sgd_gemini_api_key', '' );
$openai_key = get_option( 'sgd_openai_api_key', '' );
$active_ai_provider = function_exists( 'sgd_get_active_ai_provider' ) ? sgd_get_active_ai_provider() : get_option( 'sgd_ai_provider', 'gemini' );
?>

<div class="sgd-laporan-wrapper">

    <!-- PAGE HEADER -->
    <div class="sgd-section-header">
        <div>
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                <span class="sgd-realm-badge" style="background:#0f3460; border-color:#00e5ff; color:#00e5ff;">
                    🏗️ SITE CONSTRUCTION & FIELD DAILY LOG
                </span>
                <span class="sgd-streak-bonus-pill" style="background:#00e5ff; color:#091428; font-weight:bold;">
                    +150 💎 QUEST REWARD
                </span>
            </div>
            <h1 class="sgd-section-title" style="color:#00e5ff; text-shadow:0 0 15px rgba(0,229,255,0.4);">
                <span>📋</span> Laporan Harian (Daily Work Report)
            </h1>
            <p style="color:#a4b0be; font-size:13px; margin-top:4px; max-width:850px; line-height:1.5;">
                Input laporan harian via <strong>Teks</strong> atau <strong>Rekaman Suara (Voice to Text)</strong>. 
                Sistem secara cerdas mengurai (decipher) laporan menjadi <strong>Cuaca Per Jam</strong>, <strong>Tenaga Kerja</strong>, <strong>Material Masuk</strong>, <strong>Peralatan</strong>, <strong>K3</strong>, <strong>Kendala</strong>, dan <strong>3 Foto Progress</strong>. 
                Admin dapat memilih tanggal dan mengedit seluruh rincian laporan secara fleksibel.
            </p>
        </div>
        <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
            <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue sgd-open-ai-settings-btn" id="sgd-open-ai-settings-btn" style="border-color:#00e5ff; display:inline-flex; align-items:center; gap:6px;">
                <span>🤖</span> Pengaturan AI (Gemini / OpenAI)
            </button>
            <div style="background:#111522; border:2px solid var(--wow-gold); padding:6px 14px; border-radius:6px; text-align:right;">
                <span style="font-family:var(--font-pixel); font-size:8px; color:#c69b3a;">ENGINE STATUS:</span>
                <strong style="color:#00e5ff; font-family:var(--font-pixel); font-size:10px; margin-left:4px; display:block;">
                    <?php 
                    if ( $gemini_key ) echo '✨ GEMINI AI READY';
                    elseif ( $openai_key ) echo '✨ OPENAI GPT READY';
                    else echo '⚡ SMART HEURISTIC NLP';
                    ?>
                </strong>
            </div>
        </div>
    </div>

    <!-- DATE PICKER NAVIGATION BAR -->
    <div class="sgd-date-nav-bar">
        <div class="sgd-date-nav-left">
            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $prev_date ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue" title="Pindah ke hari sebelumnya">
                ◀ Kemarin
            </a>
            
            <form method="get" class="sgd-date-picker-form" id="sgd-date-picker-form" style="display:inline-flex; align-items:center; gap:8px;">
                <input type="hidden" name="sgd_page" value="laporan-harian">
                <div class="sgd-date-input-wrap">
                    <span class="sgd-date-calendar-icon">📅</span>
                    <input type="date" name="report_date" id="sgd-report-date-input" value="<?php echo esc_attr( $selected_date ); ?>" class="sgd-date-picker-field" onchange="document.getElementById('sgd-date-picker-form').submit();">
                </div>
            </form>

            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $next_date ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue" title="Pindah ke hari berikutnya">
                Besok ▶
            </a>

            <?php if ( $selected_date !== $today_date ): ?>
                <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $today_date ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold">
                    ⭐ Hari Ini
                </a>
            <?php endif; ?>
        </div>

        <div class="sgd-date-nav-center">
            <div class="sgd-date-display-title">
                <?php echo esc_html( $formatted_date_id ); ?>
            </div>
            <div class="sgd-date-report-count">
                <?php if ( $total_reports > 0 ): ?>
                    <span class="sgd-pill-count" style="background:#00b894;"><?php echo $total_reports; ?> Laporan Tersedia</span>
                <?php else: ?>
                    <span class="sgd-pill-count" style="background:#e17055;">Belum Ada Laporan</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="sgd-date-nav-right">
            <?php if ( ! empty( $reports_for_date ) ): ?>
                <?php if ( $show_new_form ): ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $selected_date ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue">
                        👁️ Lihat Laporan Tersimpan
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $selected_date, 'action' => 'new' ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-sm sgd-btn-gold">
                        ➕ Buat Laporan Baru
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- AI CONFIGURATION MODAL (ACCESSIBLE TO ADMIN & MANAGERS) -->
    <div class="sgd-modal-backdrop" id="sgd-ai-settings-modal">
        <div class="sgd-modal-box" style="max-width:620px;">
            <div class="sgd-modal-header" style="border-bottom:2px solid #00e5ff;">
                <h3 class="sgd-modal-title" style="color:#00e5ff; display:flex; align-items:center; gap:8px;">
                    <span>🤖</span> Konfigurasi AI Decipher Engine
                </h3>
                <button type="button" class="sgd-modal-close" id="sgd-ai-settings-close">&times;</button>
            </div>
            <form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <input type="hidden" name="sgd_page" value="laporan-harian">
                <input type="hidden" name="sgd_action" value="sgd_save_ai_settings">
                <input type="hidden" name="redirect_to" value="laporan-harian">
                <div class="sgd-modal-body">
                    <p style="font-size:13px; color:#c8d1e0; line-height:1.6; margin-bottom:16px;">
                        Gunakan <strong>Google Gemini API Key</strong> (100% Gratis di Google AI Studio hingga 1.500 permintaan/hari) atau <strong>OpenAI API Key</strong> untuk dechipering laporan proyek otomatis dengan tingkat kecerdasan dan akurasi tertinggi!
                    </p>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label" style="color:#ffd700;">Pilih Mesin Decipher:</label>
                        <select name="ai_provider" class="sgd-form-select">
                            <option value="gemini" <?php selected( $active_ai_provider, 'gemini' ); ?>>✨ Google Gemini AI (Rekomendasi - Cerdas & Gratis 1.500 req/hari)</option>
                            <option value="openai" <?php selected( $active_ai_provider, 'openai' ); ?>>✨ OpenAI GPT-4o Mini (Berbayar via token OpenAI)</option>
                            <option value="smart_nlp" <?php selected( $active_ai_provider, 'smart_nlp' ); ?>>⚡ Smart Heuristic NLP (Bawaan - Cepat, Offline & Tanpa Kuota)</option>
                        </select>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label" style="color:#00e5ff;">Google Gemini API Key (Sangat Direkomendasikan):</label>
                        <input type="text" name="gemini_api_key" value="<?php echo esc_attr( $gemini_key ); ?>" class="sgd-form-input" placeholder="AIzaSy..." autocomplete="off">
                        <small style="color:#8fa0bd; font-size:11px; margin-top:4px; display:block;">
                            🔗 Dapatkan kunci API Gemini gratis hanya dalam 30 detik di: <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color:#00e5ff; text-decoration:underline;">https://aistudio.google.com/app/apikey</a>.
                        </small>
                    </div>

                    <div class="sgd-form-group">
                        <label class="sgd-form-label" style="color:#a29bfe;">OpenAI API Key (Alternatif):</label>
                        <input type="password" name="openai_api_key" value="<?php echo esc_attr( $openai_key ); ?>" class="sgd-form-input" placeholder="sk-proj-..." autocomplete="off">
                    </div>

                    <div style="background:rgba(0,229,255,0.08); border:1px solid rgba(0,229,255,0.3); border-radius:6px; padding:12px; margin-top:12px;">
                        <strong style="color:#00e5ff; font-size:12px;">💡 Keunggulan Decipher Melalui AI:</strong>
                        <p style="color:#b2bec3; font-size:11px; margin:4px 0 0 0; line-height:1.5;">
                            AI memahami bahasa sehari-hari dan istilah tukang di lapangan (misal: <em>"hujan jam 10-4 sore. butuh tukang batu 10, masuk pasir 100kg, pake hammer drill. kita bikin taman. pompa air rusak."</em>). AI otomatis memisahkan alat yang aktif digunakan dengan alat yang rusak sebagai kendala, membagi cuaca 07.00-17.00 secara presisi, serta memverifikasi status K3.
                        </p>
                    </div>
                </div>
                <div class="sgd-modal-footer">
                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-ai-settings-cancel">Batal</button>
                    <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-gold">💾 Simpan Konfigurasi AI</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ( $show_new_form ): ?>
        <!-- AI STATUS & QUICK CONFIGURATION BANNER -->
        <div style="margin-bottom:16px; background:linear-gradient(135deg, rgba(0, 229, 255, 0.12), rgba(16, 24, 39, 0.95)); border:2px solid #00e5ff; border-radius:8px; padding:12px 18px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; box-shadow:0 0 15px rgba(0, 229, 255, 0.15);">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size:26px;">🤖</span>
                <div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <strong style="font-family:var(--font-pixel); font-size:10px; color:#00e5ff; letter-spacing:1px;">
                            <?php if ( ! empty( $gemini_key ) ): ?>
                                ✨ AI ENGINE READY: GOOGLE GEMINI AKTIF
                            <?php elseif ( ! empty( $openai_key ) ): ?>
                                ✨ AI ENGINE READY: OPENAI GPT-4O MINI AKTIF
                            <?php else: ?>
                                ⚡ MODE DECIPHER: SMART CONSTRUCTION NLP (OFFLINE)
                            <?php endif; ?>
                        </strong>
                        <?php if ( ! empty( $gemini_key ) || ! empty( $openai_key ) ): ?>
                            <span style="background:#00b894; color:#fff; font-size:9px; font-weight:bold; padding:2px 8px; border-radius:10px;">AI ONLINE</span>
                        <?php else: ?>
                            <span style="background:#f39c12; color:#111; font-size:9px; font-weight:bold; padding:2px 8px; border-radius:10px;">STANDBY</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px; color:#c8d1e0; margin-top:3px; line-height:1.4;">
                        <?php if ( ! empty( $gemini_key ) ): ?>
                            Laporan suara/teks Anda akan diuraikan secara otomatis dengan <strong>Google Gemini AI</strong> untuk ekstraksi cuaca per jam, pekerja, material, dan K3 paling presisi.
                        <?php elseif ( ! empty( $openai_key ) ): ?>
                            Laporan suara/teks Anda akan diuraikan secara otomatis dengan <strong>OpenAI GPT</strong>.
                        <?php else: ?>
                            Sistem saat ini memakai <em>Smart NLP</em> bawaan. Untuk ekstraksi bahasa alami yang jauh lebih akurat & terstruktur, Anda dapat memasang <strong>Google Gemini API Key (100% Gratis di Google AI Studio)</strong>.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <button type="button" class="sgd-btn sgd-btn-sm <?php echo ( ! empty( $gemini_key ) || ! empty( $openai_key ) ) ? 'sgd-btn-blue' : 'sgd-btn-gold'; ?> sgd-open-ai-settings-btn" id="sgd-trigger-ai-settings-btn" style="white-space:nowrap;">
                    <?php echo ( ! empty( $gemini_key ) || ! empty( $openai_key ) ) ? '⚙️ Kelola Kunci AI' : '🚀 Masukkan Kunci AI Gemini'; ?>
                </button>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- INPUT FORM: SUBMIT DAILY REPORT (VOICE / TEXT + 3 PHOTOS)-->
        <!-- ======================================================== -->
        <div class="sgd-laporan-card-box sgd-form-card">
            <div class="sgd-card-title-bar" style="border-bottom:2px solid #00e5ff;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:24px;">📝</span>
                    <div>
                        <h2 style="font-family:var(--font-title); font-size:18px; color:#fff; margin:0;">
                            Input Laporan Harian Baru (<?php echo esc_html( $formatted_date_id ); ?>)
                        </h2>
                        <span style="font-size:11px; color:#8fa0bd;">
                            Gunakan suara atau teks bebas. Tambahkan hingga 3 foto progress lapangan.
                        </span>
                    </div>
                </div>
                <div class="sgd-badge-reward-pill">
                    🎁 +150 💎 Quest
                </div>
            </div>

            <form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>" enctype="multipart/form-data" id="sgd-daily-report-form">
                <input type="hidden" name="sgd_page" value="laporan-harian">
                <input type="hidden" name="sgd_action" value="sgd_submit_daily_report">
                <input type="hidden" name="report_date" value="<?php echo esc_attr( $selected_date ); ?>">

                <!-- QUICK TEMPLATE BUTTONS -->
                <div class="sgd-template-box">
                    <div class="sgd-template-header">
                        <span style="font-family:var(--font-pixel); font-size:9px; color:#ffd700;">⚡ TEMPLATE TEKS CEPAT (KLIK UNTUK MENAMBAH KE TEKS):</span>
                    </div>
                    <div class="sgd-template-chips-grid">
                        <button type="button" class="sgd-chip-btn" data-text="tidak ada kecelakaan kerja.">
                            🛡️ Tidak Ada Kecelakaan Kerja
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="hujan seharian.">
                            🌧️ Hujan Seharian
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="cuaca cerah sepanjang hari.">
                            ☀️ Cerah Sepanjang Hari
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="hujan jam 10-4 sore.">
                            ⛈️ Hujan Jam 10-4 Sore
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="butuh tukang batu 10.">
                            👷 Butuh Tukang Batu 10
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="masuk pasir 100kg.">
                            🧱 Masuk Pasir 100kg
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="masuk semen 50 sak.">
                            📦 Masuk Semen 50 Sak
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="pake hammer drill.">
                            🔨 Pake Hammer Drill
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="kita bikin taman.">
                            🌿 Kita Bikin Taman
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="pompa air rusak.">
                            ⚠️ Pompa Air Rusak
                        </button>
                        <button type="button" class="sgd-chip-btn" data-text="semua peralatan berfungsi baik.">
                            ✅ Peralatan Normal
                        </button>
                    </div>
                </div>

                <!-- MAIN TEXT / VOICE INPUT AREA -->
                <div class="sgd-form-group" style="margin-top:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                        <label class="sgd-form-label" style="font-size:12px; color:#00e5ff; margin-bottom:0;">
                            ✍️ Isi Laporan Kerja Harian (Teks atau Rekam Suara):
                        </label>
                        <!-- VOICE RECOGNITION BUTTON -->
                        <div class="sgd-voice-ctrl-wrap">
                            <button type="button" class="sgd-voice-record-btn" id="sgd-voice-btn">
                                <span class="sgd-voice-icon" id="sgd-voice-icon">🎙️</span>
                                <span class="sgd-voice-text" id="sgd-voice-label">Rekam Suara (Voice Input)</span>
                                <span class="sgd-voice-pulse" id="sgd-voice-pulse" style="display:none;"></span>
                            </button>
                        </div>
                    </div>

                    <!-- VOICE LIVE STATUS BANNER -->
                    <div id="sgd-voice-live-status" class="sgd-voice-live-alert" style="display:none;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="sgd-pulse-dot"></span>
                            <div>
                                <strong style="color:#ff7675; font-size:12px;">🎙️ SEDANG MENDENGARKAN SUARA...</strong>
                                <span style="font-size:11px; color:#dfe6e9; margin-left:8px;">Silakan berbicara dalam Bahasa Indonesia. Teks akan otomatis diketik di bawah.</span>
                            </div>
                        </div>
                        <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-red" id="sgd-voice-stop-btn" style="padding:3px 10px; font-size:10px;">
                            ⏹️ Selesai Bicara
                        </button>
                    </div>

                    <textarea name="raw_input" id="sgd-raw-input" class="sgd-form-textarea" rows="5" required placeholder="Contoh laporan:&#10;hujan jam 10-4 sore. butuh tukang batu 10, masuk pasir 100kg, pake hammer drill. kita bikin taman. pompa air rusak."></textarea>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                        <small style="color:#8fa0bd; font-size:11px;">
                            💡 <em>Tips: Tuliskan kondisi cuaca, jumlah tukang/pekerja, material yang datang, alat yang dipakai, pekerjaan hari ini, dan kendala (jika ada).</em>
                        </small>
                        <button type="button" class="sgd-btn-clear-text" id="sgd-clear-text-btn" title="Hapus seluruh teks">
                            Bersihkan Teks
                        </button>
                    </div>
                </div>

                <!-- 3 PROGRESS PHOTOS UPLOAD SECTION -->
                <div class="sgd-photos-upload-container">
                    <div class="sgd-photos-header">
                        <span style="font-size:18px;">📸</span>
                        <div>
                            <strong style="color:#fff; font-size:13px; font-family:var(--font-pixel);">FOTO PROGRESS DOKUMENTASI LAPANGAN (MAKSIMAL 3 FOTO)</strong>
                            <div style="font-size:11px; color:#8fa0bd;">Dapat upload file gambar atau langsung ambil foto dari kamera smartphone.</div>
                        </div>
                    </div>

                    <div class="sgd-photos-grid">
                        <!-- Photo 1 -->
                        <div class="sgd-photo-slot" id="slot-photo-1">
                            <div class="sgd-photo-slot-header">
                                <span class="sgd-photo-slot-num">1</span>
                                <span class="sgd-photo-slot-tag">Progress Awal / Pagi</span>
                            </div>
                            <div class="sgd-photo-preview-box" id="preview-box-1">
                                <div class="sgd-photo-placeholder" id="placeholder-1">
                                    <span style="font-size:32px;">📷</span>
                                    <span>Pilih / Ambil Foto 1</span>
                                </div>
                                <img src="" alt="Preview 1" class="sgd-photo-img-preview" id="preview-img-1" style="display:none;">
                            </div>
                            <input type="file" name="progress_photo_1" id="file-photo-1" class="sgd-photo-file-input" accept="image/*" capture="environment">
                            <div class="sgd-photo-actions">
                                <label for="file-photo-1" class="sgd-btn sgd-btn-sm sgd-btn-blue sgd-upload-trigger">
                                    📁 Unggah / Foto
                                </label>
                                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-red sgd-remove-photo-btn" data-slot="1" style="display:none;">
                                    ✕
                                </button>
                            </div>
                        </div>

                        <!-- Photo 2 -->
                        <div class="sgd-photo-slot" id="slot-photo-2">
                            <div class="sgd-photo-slot-header">
                                <span class="sgd-photo-slot-num">2</span>
                                <span class="sgd-photo-slot-tag">Progress Siang / Tengah</span>
                            </div>
                            <div class="sgd-photo-preview-box" id="preview-box-2">
                                <div class="sgd-photo-placeholder" id="placeholder-2">
                                    <span style="font-size:32px;">📷</span>
                                    <span>Pilih / Ambil Foto 2</span>
                                </div>
                                <img src="" alt="Preview 2" class="sgd-photo-img-preview" id="preview-img-2" style="display:none;">
                            </div>
                            <input type="file" name="progress_photo_2" id="file-photo-2" class="sgd-photo-file-input" accept="image/*" capture="environment">
                            <div class="sgd-photo-actions">
                                <label for="file-photo-2" class="sgd-btn sgd-btn-sm sgd-btn-blue sgd-upload-trigger">
                                    📁 Unggah / Foto
                                </label>
                                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-red sgd-remove-photo-btn" data-slot="2" style="display:none;">
                                    ✕
                                </button>
                            </div>
                        </div>

                        <!-- Photo 3 -->
                        <div class="sgd-photo-slot" id="slot-photo-3">
                            <div class="sgd-photo-slot-header">
                                <span class="sgd-photo-slot-num">3</span>
                                <span class="sgd-photo-slot-tag">Progress Sore / Akhir</span>
                            </div>
                            <div class="sgd-photo-preview-box" id="preview-box-3">
                                <div class="sgd-photo-placeholder" id="placeholder-3">
                                    <span style="font-size:32px;">📷</span>
                                    <span>Pilih / Ambil Foto 3</span>
                                </div>
                                <img src="" alt="Preview 3" class="sgd-photo-img-preview" id="preview-img-3" style="display:none;">
                            </div>
                            <input type="file" name="progress_photo_3" id="file-photo-3" class="sgd-photo-file-input" accept="image/*" capture="environment">
                            <div class="sgd-photo-actions">
                                <label for="file-photo-3" class="sgd-btn sgd-btn-sm sgd-btn-blue sgd-upload-trigger">
                                    📁 Unggah / Foto
                                </label>
                                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-red sgd-remove-photo-btn" data-slot="3" style="display:none;">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- OPTIONAL SUPERVISOR DIRECTIVES (FOR ADMIN/SUPERVISOR) -->
                <?php if ( $is_admin ): ?>
                    <div style="background:#131a29; border:1px solid #233454; border-radius:6px; padding:16px; margin-top:16px;">
                        <span style="font-family:var(--font-pixel); font-size:9px; color:#ffd700; display:block; margin-bottom:10px;">
                            👑 INPUT PENGAWAS & RENCANA (OPSIONAL UNTUK ADMIN / SUPERVISOR):
                        </span>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="sgd-form-group" style="margin-bottom:0;">
                                <label class="sgd-form-label" style="font-size:10px; color:#8fa0bd;">Instruksi Pengawas (Admin Input):</label>
                                <textarea name="supervisor_instruction" class="sgd-form-textarea" rows="2" placeholder="Catatan instruksi dari pengawas lapangan..."></textarea>
                            </div>
                            <div class="sgd-form-group" style="margin-bottom:0;">
                                <label class="sgd-form-label" style="font-size:10px; color:#8fa0bd;">Rencana Kerja Besok (Admin Input):</label>
                                <textarea name="next_day_plan" class="sgd-form-textarea" rows="2" placeholder="Target dan rencana pekerjaan besok..."></textarea>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SUBMIT BUTTON -->
                <div style="margin-top:20px; text-align:right; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="font-size:12px; color:#8fa0bd;">
                        ⚡ Sistem akan memproses dan mengurai laporan secara otomatis setelah tombol diklik.
                    </div>
                    <button type="submit" id="sgd-submit-report-btn" class="sgd-btn sgd-btn-gold" style="font-size:14px; padding:12px 28px;">
                        🚀 PROSES & KIRIM LAPORAN HARIAN (+150 💎)
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $reports_for_date ) && $active_report ): ?>
        <!-- ======================================================== -->
        <!-- REPORT VIEWER & ADMIN EDITOR: STRUCTURED BREAKDOWN       -->
        <!-- ======================================================== -->
        
        <!-- REPORT SELECTOR TABS (IF MULTIPLE REPORTS ON THIS DATE) -->
        <?php if ( count( $reports_for_date ) > 1 ): ?>
            <div class="sgd-reports-subtab-bar">
                <span style="font-family:var(--font-pixel); font-size:8px; color:#8fa0bd; align-self:center; margin-right:6px;">PILIH LAPORAN:</span>
                <?php foreach ( $reports_for_date as $idx => $rep_item ): 
                    $author = get_user_by( 'id', $rep_item->user_id );
                    $author_name = $author ? $author->display_name : 'Staff #' . $rep_item->user_id;
                    $is_curr_active = ( (int) $active_report->id === (int) $rep_item->id );
                ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $selected_date, 'report_id' => $rep_item->id ], home_url( '/' ) ) ); ?>" class="sgd-tab-btn <?php echo $is_curr_active ? 'active' : ''; ?>" style="font-size:10px; padding:6px 12px;">
                        📄 #<?php echo $rep_item->id; ?> • <?php echo esc_html( $author_name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        $report_author = get_user_by( 'id', $active_report->user_id );
        $author_display = $report_author ? $report_author->display_name : 'Hero Employee #' . $active_report->user_id;
        $weather_data = is_string( $active_report->weather_hourly ) ? json_decode( $active_report->weather_hourly, true ) : (array) $active_report->weather_hourly;
        $manpower_data = is_string( $active_report->manpower ) ? json_decode( $active_report->manpower, true ) : (array) $active_report->manpower;
        $materials_data = is_string( $active_report->materials ) ? json_decode( $active_report->materials, true ) : (array) $active_report->materials;
        $equipment_data = is_string( $active_report->equipment ) ? json_decode( $active_report->equipment, true ) : (array) $active_report->equipment;
        $work_data = is_string( $active_report->work_executed ) ? json_decode( $active_report->work_executed, true ) : (array) $active_report->work_executed;
        $issues_data = is_string( $active_report->issues ) ? json_decode( $active_report->issues, true ) : (array) $active_report->issues;

        $can_edit = $is_admin || ( (int) $active_report->user_id === $current_user_id );
        $formatted_text_output = sgd_format_daily_report_text( $active_report );
        ?>

        <div class="sgd-laporan-detail-container">

            <!-- REPORT SUMMARY HEADER -->
            <div class="sgd-report-summary-hud">
                <div class="sgd-hud-left">
                    <span class="sgd-report-id-badge">LAPORAN #<?php echo $active_report->id; ?></span>
                    <h2 class="sgd-report-header-title">
                        Laporan Tanggal <?php echo esc_html( $formatted_date_id ); ?>
                    </h2>
                    <div class="sgd-report-meta-line">
                        <span>👤 Pelapor: <strong><?php echo esc_html( $author_display ); ?></strong></span>
                        <span>⏰ Waktu Kirim: <strong><?php echo esc_html( $active_report->created_at ); ?></strong></span>
                        <span>
                            Status Engine: 
                            <?php if ( $active_report->parsed_with === 'gemini' ): ?>
                                <span class="sgd-badge-engine gemini">✨ Google Gemini AI</span>
                            <?php elseif ( $active_report->parsed_with === 'openai' ): ?>
                                <span class="sgd-badge-engine openai">✨ OpenAI GPT</span>
                            <?php else: ?>
                                <span class="sgd-badge-engine nlp">⚡ Smart Construction NLP</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="sgd-hud-actions-right">
                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-copy-formatted-text-btn" data-text="<?php echo esc_attr( $formatted_text_output ); ?>">
                        📋 Salin Format Teks
                    </button>
                    <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-gold" onclick="window.print();">
                        🖨️ Cetak / Print Laporan
                    </button>
                    <form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menganalisis ulang (re-parse) teks laporan ini dengan AI/NLP?');">
                        <input type="hidden" name="sgd_page" value="laporan-harian">
                        <input type="hidden" name="sgd_action" value="sgd_reparse_daily_report">
                        <input type="hidden" name="report_id" value="<?php echo $active_report->id; ?>">
                        <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-blue" title="Analisis ulang teks menggunakan AI / NLP">
                            🤖 Re-Parse AI
                        </button>
                    </form>
                    <?php if ( $is_admin ): ?>
                        <form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus laporan ini secara permanen?');">
                            <input type="hidden" name="sgd_page" value="laporan-harian">
                            <input type="hidden" name="sgd_action" value="sgd_delete_daily_report">
                            <input type="hidden" name="report_id" value="<?php echo $active_report->id; ?>">
                            <button type="submit" class="sgd-btn sgd-btn-sm sgd-btn-red" title="Hapus laporan">
                                🗑️ Hapus
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RAW INPUT RECAP CARD -->
            <div class="sgd-raw-input-card">
                <div class="sgd-raw-header">
                    <span>💬 TEKS LAPORAN ASLI (RAW INPUT DARI LAPANGAN):</span>
                </div>
                <div class="sgd-raw-text-body">
                    "<?php echo nl2br( esc_html( $active_report->raw_input ) ); ?>"
                </div>
            </div>

            <!-- VIEW MODE TABS: 1. FORMAT STANDAR TEKS | 2. RINCIAN FORM & ADMIN EDITOR | 3. FOTO PROGRESS -->
            <div class="sgd-view-mode-tabs">
                <button type="button" class="sgd-mode-tab-btn active" data-target="view-mode-standard-text">
                    📋 1. Format Teks Standar (User Spec)
                </button>
                <button type="button" class="sgd-mode-tab-btn" data-target="view-mode-editor">
                    ✏️ 2. Rincian & Editor Interaktif (Admin Edit)
                </button>
                <button type="button" class="sgd-mode-tab-btn" data-target="view-mode-photos">
                    📸 3. Galeri 3 Foto Progress (<?php echo ( !empty($active_report->photo_1) + !empty($active_report->photo_2) + !empty($active_report->photo_3) ); ?>)
                </button>
            </div>

            <!-- ============================================== -->
            <!-- TAB 1: FORMAT TEKS STANDAR (EXACT USER SPEC)    -->
            <!-- ============================================== -->
            <div id="view-mode-standard-text" class="sgd-mode-section" style="display:block;">
                <div class="sgd-spec-sheet-container">
                    
                    <!-- TOP HEADER: TITLE & QUICK ACTIONS -->
                    <div class="sgd-spec-sheet-header">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:26px;">📋</span>
                            <div>
                                <h3 style="font-family:var(--font-title); font-size:16px; color:#ffd700; margin:0; letter-spacing:0.5px;">
                                    Format Teks Standar Laporan Harian (User Specification)
                                </h3>
                                <span style="font-size:11px; color:#8fa0bd;">
                                    Hasil penguraian cerdas AI / NLP terstruktur rapi: cuaca per jam (pills), material masuk (distinct), tenaga kerja, dan K3.
                                </span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-gold" id="sgd-copy-term-btn" data-text="<?php echo esc_attr( $formatted_text_output ); ?>">
                                📋 Salin Teks Format
                            </button>
                            <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-toggle-raw-ascii-btn">
                                💻 Toggle Monospace Raw
                            </button>
                        </div>
                    </div>

                    <!-- VISUAL SPECIFICATION CARDS -->
                    <div class="sgd-spec-cards-wrapper">

                        <!-- 1. CUACA PER JAM (PILLS STYLE) -->
                        <div class="sgd-spec-card sgd-spec-weather-card">
                            <div class="sgd-spec-title-bar weather">
                                <div class="sgd-spec-title-left">
                                    <span class="sgd-spec-title-icon">🌤️</span>
                                    <span class="sgd-spec-title-text">cuaca per jam</span>
                                </div>
                                <span class="sgd-spec-divider">=============</span>
                            </div>
                            <div class="sgd-weather-pills-container">
                                <?php 
                                $hours = [ '07.00', '08.00', '09.00', '10.00', '11.00', '12.00', '13.00', '14.00', '15.00', '16.00', '17.00' ];
                                foreach ( $hours as $h ): 
                                    $status_w = strtolower( trim( $weather_data[ $h ] ?? 'cerah' ) );
                                    $is_rain = ( strpos( $status_w, 'hujan' ) !== false );
                                    $is_cloudy = ( strpos( $status_w, 'berawan' ) !== false || strpos( $status_w, 'mendung' ) !== false );
                                    
                                    $pill_class = 'is-sunny';
                                    $icon = '☀️';
                                    if ( $is_rain ) {
                                        $pill_class = 'is-rain';
                                        $icon = '🌧️';
                                    } elseif ( $is_cloudy ) {
                                        $pill_class = 'is-cloudy';
                                        $icon = '⛅';
                                    }
                                ?>
                                    <div class="sgd-weather-pill <?php echo $pill_class; ?>" title="Jam <?php echo esc_attr( $h ); ?>: <?php echo esc_attr( $status_w ); ?>">
                                        <span class="sgd-pill-hour">-<?php echo esc_html( $h ); ?></span>
                                        <span class="sgd-pill-icon"><?php echo $icon; ?></span>
                                        <span class="sgd-pill-weather"><?php echo esc_html( $status_w ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 2. TENAGA KERJA -->
                        <div class="sgd-spec-card sgd-spec-manpower-card">
                            <div class="sgd-spec-title-bar manpower">
                                <div class="sgd-spec-title-left">
                                    <span class="sgd-spec-title-icon">👷</span>
                                    <span class="sgd-spec-title-text">tenaga kerja</span>
                                </div>
                                <span class="sgd-spec-divider">============</span>
                            </div>
                            <div class="sgd-spec-items-grid">
                                <?php if ( ! empty( $manpower_data ) ): ?>
                                    <?php foreach ( $manpower_data as $mp ): 
                                        $role = is_array( $mp ) ? ( $mp['role'] ?? '' ) : (string) $mp;
                                        $count = is_array( $mp ) ? ( $mp['count'] ?? '' ) : '';
                                    ?>
                                        <div class="sgd-spec-item-badge manpower-badge">
                                            <span class="sgd-bullet">-</span>
                                            <span class="sgd-item-label"><?php echo esc_html( $role ); ?></span>
                                            <?php if ( $count !== '' ): ?>
                                                <span class="sgd-item-count"><?php echo esc_html( $count ); ?> orang</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="sgd-spec-empty">- (Tidak ada catatan tenaga kerja)</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 3. MATERIAL MASUK (HIGHLY DISTINCT SECTION) -->
                        <div class="sgd-spec-card sgd-spec-material-card">
                            <div class="sgd-spec-title-bar material">
                                <div class="sgd-spec-title-left">
                                    <span class="sgd-spec-title-icon">🧱</span>
                                    <span class="sgd-spec-title-text">material masuk</span>
                                    <span class="sgd-spec-highlight-tag">📦 LOGISTIK / MATERIAL ON-SITE</span>
                                </div>
                                <span class="sgd-spec-divider">==============</span>
                            </div>
                            <div class="sgd-spec-items-grid material-grid">
                                <?php if ( ! empty( $materials_data ) ): ?>
                                    <?php foreach ( $materials_data as $m ): 
                                        $name = is_array( $m ) ? ( $m['name'] ?? '' ) : (string) $m;
                                        $qty = is_array( $m ) ? ( $m['quantity'] ?? '' ) : '';
                                        $unit = is_array( $m ) ? ( $m['unit'] ?? '' ) : '';
                                    ?>
                                        <div class="sgd-spec-material-pill">
                                            <span class="sgd-bullet">-</span>
                                            <span class="sgd-material-name"><?php echo esc_html( $name ); ?></span>
                                            <?php if ( $qty !== '' || $unit !== '' ): ?>
                                                <span class="sgd-material-qty-tag"><?php echo esc_html( trim( "{$qty} {$unit}" ) ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="sgd-spec-empty">- (Tidak ada material masuk)</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 4. PERALATAN -->
                        <div class="sgd-spec-card sgd-spec-equipment-card">
                            <div class="sgd-spec-title-bar equipment">
                                <div class="sgd-spec-title-left">
                                    <span class="sgd-spec-title-icon">🔨</span>
                                    <span class="sgd-spec-title-text">peralatan</span>
                                </div>
                                <span class="sgd-spec-divider">=========</span>
                            </div>
                            <div class="sgd-spec-items-grid">
                                <?php if ( ! empty( $equipment_data ) ): ?>
                                    <?php foreach ( $equipment_data as $eq ): ?>
                                        <div class="sgd-spec-item-badge equipment-badge">
                                            <span class="sgd-bullet">-</span>
                                            <span class="sgd-item-label"><?php echo esc_html( is_array( $eq ) ? json_encode( $eq ) : $eq ); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="sgd-spec-empty">- (Tidak ada catatan peralatan)</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 5. PEKERJAAN YANG DILAKSANAKAN -->
                        <div class="sgd-spec-card sgd-spec-work-card">
                            <div class="sgd-spec-title-bar work">
                                <div class="sgd-spec-title-left">
                                    <span class="sgd-spec-title-icon">🏗️</span>
                                    <span class="sgd-spec-title-text">pekerjaan yang dilaksanakan</span>
                                </div>
                                <span class="sgd-spec-divider">==============================</span>
                            </div>
                            <div class="sgd-spec-list-container">
                                <?php if ( ! empty( $work_data ) ): ?>
                                    <?php 
                                    $work_items = is_array( $work_data ) ? $work_data : [ $work_data ];
                                    foreach ( $work_items as $w ): 
                                    ?>
                                        <div class="sgd-spec-list-item work-item">
                                            <span class="sgd-bullet">-</span>
                                            <span class="sgd-item-desc"><?php echo esc_html( is_array( $w ) ? json_encode( $w ) : $w ); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="sgd-spec-empty">- (Tidak ada catatan pekerjaan)</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 6. KENDALA -->
                        <div class="sgd-spec-card sgd-spec-issue-card">
                            <div class="sgd-spec-title-bar issue">
                                <div class="sgd-spec-title-left">
                                    <span class="sgd-spec-title-icon">⚠️</span>
                                    <span class="sgd-spec-title-text">kendala</span>
                                </div>
                                <span class="sgd-spec-divider">=======</span>
                            </div>
                            <div class="sgd-spec-list-container">
                                <?php if ( ! empty( $issues_data ) ): ?>
                                    <?php 
                                    $issue_items = is_array( $issues_data ) ? $issues_data : [ $issues_data ];
                                    foreach ( $issue_items as $is ): 
                                    ?>
                                        <div class="sgd-spec-list-item issue-item">
                                            <span class="sgd-bullet">-</span>
                                            <span class="sgd-item-desc"><?php echo esc_html( is_array( $is ) ? json_encode( $is ) : $is ); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="sgd-spec-list-item safe-item">
                                        <span class="sgd-bullet">-</span>
                                        <span class="sgd-item-desc">Tidak ada kendala</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 7. KESELAMATAN KERJA (K3) -->
                        <div class="sgd-spec-card sgd-spec-safety-card">
                            <div class="sgd-spec-title-bar safety">
                                <div class="sgd-spec-title-left">
                                    <span class="sgd-spec-title-icon">🛡️</span>
                                    <span class="sgd-spec-title-text">- keselamatan kerja(k3)</span>
                                </div>
                                <span class="sgd-spec-divider">=======================</span>
                            </div>
                            <div class="sgd-spec-safety-box">
                                <span class="sgd-safety-shield-icon">🔰</span>
                                <strong class="sgd-safety-val">
                                    <?php echo esc_html( ! empty( $active_report->safety_k3 ) ? $active_report->safety_k3 : 'Nihil kecelakaan kerja' ); ?>
                                </strong>
                            </div>
                        </div>

                        <!-- OPTIONAL SECTIONS IF POPULATED -->
                        <?php if ( ! empty( $active_report->supervisor_instruction ) ): ?>
                            <div class="sgd-spec-card sgd-spec-admin-card">
                                <div class="sgd-spec-title-bar admin">
                                    <div class="sgd-spec-title-left">
                                        <span class="sgd-spec-title-icon">👨‍💼</span>
                                        <span class="sgd-spec-title-text">instruksi pengawas</span>
                                    </div>
                                    <span class="sgd-spec-divider">==================</span>
                                </div>
                                <div class="sgd-spec-notes-box">
                                    <?php echo nl2br( esc_html( $active_report->supervisor_instruction ) ); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $active_report->next_day_plan ) ): ?>
                            <div class="sgd-spec-card sgd-spec-plan-card">
                                <div class="sgd-spec-title-bar plan">
                                    <div class="sgd-spec-title-left">
                                        <span class="sgd-spec-title-icon">📅</span>
                                        <span class="sgd-spec-title-text">rencana kerja besok</span>
                                    </div>
                                    <span class="sgd-spec-divider">===================</span>
                                </div>
                                <div class="sgd-spec-notes-box">
                                    <?php echo nl2br( esc_html( $active_report->next_day_plan ) ); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $active_report->additional_notes ) ): ?>
                            <div class="sgd-spec-card sgd-spec-notes-card">
                                <div class="sgd-spec-title-bar notes">
                                    <div class="sgd-spec-title-left">
                                        <span class="sgd-spec-title-icon">📝</span>
                                        <span class="sgd-spec-title-text">catatan lain</span>
                                    </div>
                                    <span class="sgd-spec-divider">============</span>
                                </div>
                                <div class="sgd-spec-notes-box">
                                    <?php echo nl2br( esc_html( $active_report->additional_notes ) ); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- COLLAPSIBLE MONOSPACE RAW ASCII TERMINAL VIEW -->
                    <div class="sgd-terminal-display-box" id="sgd-raw-ascii-box" style="margin-top:20px; display:none;">
                        <div class="sgd-terminal-top">
                            <div class="sgd-term-dots">
                                <span class="dot red"></span>
                                <span class="dot yellow"></span>
                                <span class="dot green"></span>
                            </div>
                            <span class="sgd-term-title">ASCII MONOSPACE RAW LOG (EXACT USER SPEC)</span>
                            <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-gold" id="sgd-copy-raw-box-btn" data-text="<?php echo esc_attr( $formatted_text_output ); ?>">
                                📋 Salin Teks Asli
                            </button>
                        </div>
                        <pre class="sgd-terminal-pre"><?php echo esc_html( $formatted_text_output ); ?></pre>
                    </div>

                </div>
            </div>

            <!-- ============================================== -->
            <!-- TAB 2: INTERACTIVE EDITOR & ADMIN FORM         -->
            <!-- ============================================== -->
            <div id="view-mode-editor" class="sgd-mode-section" style="display:none;">
                <?php if ( $can_edit ): ?>
                    <form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>" enctype="multipart/form-data" id="sgd-admin-edit-report-form">
                        <input type="hidden" name="sgd_page" value="laporan-harian">
                        <input type="hidden" name="sgd_action" value="sgd_update_daily_report">
                        <input type="hidden" name="report_id" value="<?php echo $active_report->id; ?>">
                        <input type="hidden" name="report_date" value="<?php echo esc_attr( $selected_date ); ?>">

                        <!-- CUACA PER JAM (07.00 - 17.00) -->
                        <div class="sgd-editor-block">
                            <div class="sgd-editor-block-header">
                                <span style="font-size:20px;">🌦️</span>
                                <div>
                                    <h3 style="font-family:var(--font-title); font-size:15px; color:#ffd700; margin:0;">
                                        CUACA PER JAM (07.00 - 17.00)
                                    </h3>
                                    <span style="font-size:11px; color:#8fa0bd;">Admin dapat mengubah status cuaca untuk masing-masing jam kerja:</span>
                                </div>
                            </div>
                            <div class="sgd-hourly-weather-grid">
                                <?php 
                                $hours = [ '07.00', '08.00', '09.00', '10.00', '11.00', '12.00', '13.00', '14.00', '15.00', '16.00', '17.00' ];
                                foreach ( $hours as $h ): 
                                    $curr_w = $weather_data[ $h ] ?? 'cerah';
                                ?>
                                    <div class="sgd-weather-hour-cell <?php echo ( $curr_w === 'hujan' ) ? 'is-rain' : ''; ?>">
                                        <div class="sgd-hour-label"><?php echo $h; ?></div>
                                        <select name="weather[<?php echo $h; ?>]" class="sgd-weather-select" onchange="this.parentElement.classList.toggle('is-rain', this.value === 'hujan');">
                                            <option value="cerah" <?php selected( $curr_w, 'cerah' ); ?>>☀️ Cerah</option>
                                            <option value="hujan" <?php selected( $curr_w, 'hujan' ); ?>>🌧️ Hujan</option>
                                            <option value="gerimis" <?php selected( $curr_w, 'gerimis' ); ?>>🌦️ Gerimis</option>
                                            <option value="mendung" <?php selected( $curr_w, 'mendung' ); ?>>☁️ Mendung</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- TENAGA KERJA -->
                        <div class="sgd-editor-block">
                            <div class="sgd-editor-block-header">
                                <span style="font-size:20px;">👷</span>
                                <div>
                                    <h3 style="font-family:var(--font-title); font-size:15px; color:#ffd700; margin:0;">
                                        TENAGA KERJA (MANPOWER)
                                    </h3>
                                    <span style="font-size:11px; color:#8fa0bd;">Daftar tukang, mandor, kenek, dan teknisi yang bekerja:</span>
                                </div>
                                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-add-manpower-btn" style="margin-left:auto;">
                                    ➕ Tambah Pekerja
                                </button>
                            </div>
                            <div class="sgd-dynamic-rows-table-wrap">
                                <table class="sgd-dynamic-table" id="sgd-manpower-table">
                                    <thead>
                                        <tr>
                                            <th>Jenis Pekerja / Keahlian</th>
                                            <th style="width:140px;">Jumlah (Orang)</th>
                                            <th style="width:70px; text-align:center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sgd-manpower-tbody">
                                        <?php if ( ! empty( $manpower_data ) ): ?>
                                            <?php foreach ( $manpower_data as $mp ): 
                                                $role = is_array($mp) ? ($mp['role'] ?? '') : (string)$mp;
                                                $count = is_array($mp) ? ($mp['count'] ?? 1) : 1;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <input type="text" name="manpower_role[]" value="<?php echo esc_attr( $role ); ?>" class="sgd-form-input" placeholder="e.g. Tukang Batu, Mandor">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="manpower_count[]" value="<?php echo esc_attr( $count ); ?>" class="sgd-form-input" min="1">
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <button type="button" class="sgd-row-delete-btn" onclick="this.closest('tr').remove();">✕</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <input type="text" name="manpower_role[]" value="Tukang Batu" class="sgd-form-input" placeholder="e.g. Tukang Batu">
                                                </td>
                                                <td>
                                                    <input type="number" name="manpower_count[]" value="10" class="sgd-form-input" min="1">
                                                </td>
                                                <td style="text-align:center;">
                                                    <button type="button" class="sgd-row-delete-btn" onclick="this.closest('tr').remove();">✕</button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- MATERIAL MASUK -->
                        <div class="sgd-editor-block">
                            <div class="sgd-editor-block-header">
                                <span style="font-size:20px;">🧱</span>
                                <div>
                                    <h3 style="font-family:var(--font-title); font-size:15px; color:#ffd700; margin:0;">
                                        MATERIAL MASUK (MATERIALS DELIVERED)
                                    </h3>
                                    <span style="font-size:11px; color:#8fa0bd;">Nama bahan, volume/kuantitas, dan satuan material yang diterima:</span>
                                </div>
                                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-add-material-btn" style="margin-left:auto;">
                                    ➕ Tambah Material
                                </button>
                            </div>
                            <div class="sgd-dynamic-rows-table-wrap">
                                <table class="sgd-dynamic-table" id="sgd-materials-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Material</th>
                                            <th style="width:140px;">Kuantitas</th>
                                            <th style="width:140px;">Satuan (Unit)</th>
                                            <th style="width:70px; text-align:center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sgd-materials-tbody">
                                        <?php if ( ! empty( $materials_data ) ): ?>
                                            <?php foreach ( $materials_data as $mat ): 
                                                $mname = is_array($mat) ? ($mat['name'] ?? '') : (string)$mat;
                                                $mqty = is_array($mat) ? ($mat['quantity'] ?? '') : '';
                                                $munit = is_array($mat) ? ($mat['unit'] ?? 'kg') : 'kg';
                                            ?>
                                                <tr>
                                                    <td>
                                                        <input type="text" name="material_name[]" value="<?php echo esc_attr( $mname ); ?>" class="sgd-form-input" placeholder="e.g. Pasir, Semen">
                                                    </td>
                                                    <td>
                                                        <input type="text" name="material_quantity[]" value="<?php echo esc_attr( $mqty ); ?>" class="sgd-form-input" placeholder="100">
                                                    </td>
                                                    <td>
                                                        <input type="text" name="material_unit[]" value="<?php echo esc_attr( $munit ); ?>" class="sgd-form-input" placeholder="kg, sak, m3, btg">
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <button type="button" class="sgd-row-delete-btn" onclick="this.closest('tr').remove();">✕</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <input type="text" name="material_name[]" value="Pasir" class="sgd-form-input" placeholder="e.g. Pasir">
                                                </td>
                                                <td>
                                                    <input type="text" name="material_quantity[]" value="100" class="sgd-form-input" placeholder="100">
                                                </td>
                                                <td>
                                                    <input type="text" name="material_unit[]" value="kg" class="sgd-form-input" placeholder="kg">
                                                </td>
                                                <td style="text-align:center;">
                                                    <button type="button" class="sgd-row-delete-btn" onclick="this.closest('tr').remove();">✕</button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- PERALATAN -->
                        <div class="sgd-editor-block">
                            <div class="sgd-editor-block-header">
                                <span style="font-size:20px;">🔨</span>
                                <div>
                                    <h3 style="font-family:var(--font-title); font-size:15px; color:#ffd700; margin:0;">
                                        PERALATAN (TOOLS & EQUIPMENT)
                                    </h3>
                                    <span style="font-size:11px; color:#8fa0bd;">Daftar alat berat, mesin, atau peralatan kerja yang digunakan:</span>
                                </div>
                                <button type="button" class="sgd-btn sgd-btn-sm sgd-btn-blue" id="sgd-add-equipment-btn" style="margin-left:auto;">
                                    ➕ Tambah Alat
                                </button>
                            </div>
                            <div id="sgd-equipment-list-container" class="sgd-equipment-inputs-grid">
                                <?php if ( ! empty( $equipment_data ) ): ?>
                                    <?php foreach ( $equipment_data as $eq_item ): ?>
                                        <div class="sgd-equipment-pill-input">
                                            <span>🔧</span>
                                            <input type="text" name="equipment_name[]" value="<?php echo esc_attr( $eq_item ); ?>" class="sgd-form-input">
                                            <button type="button" class="sgd-row-delete-btn" onclick="this.parentElement.remove();">✕</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="sgd-equipment-pill-input">
                                        <span>🔧</span>
                                        <input type="text" name="equipment_name[]" value="Hammer Drill" class="sgd-form-input">
                                        <button type="button" class="sgd-row-delete-btn" onclick="this.parentElement.remove();">✕</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- PEKERJAAN & KENDALA -->
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="sgd-editor-block" style="margin-bottom:0;">
                                <label class="sgd-form-label" style="color:#00e5ff;">🌿 Pekerjaan yang Dilaksanakan:</label>
                                <textarea name="work_executed" class="sgd-form-textarea" rows="4"><?php 
                                    if ( is_array( $work_data ) ) {
                                        echo esc_textarea( implode( "\n", $work_data ) );
                                    } else {
                                        echo esc_textarea( $work_data );
                                    }
                                ?></textarea>
                            </div>
                            <div class="sgd-editor-block" style="margin-bottom:0;">
                                <label class="sgd-form-label" style="color:#ff7675;">⚠️ Kendala / Hambatan:</label>
                                <textarea name="issues" class="sgd-form-textarea" rows="4"><?php 
                                    if ( is_array( $issues_data ) ) {
                                        echo esc_textarea( implode( "\n", $issues_data ) );
                                    } else {
                                        echo esc_textarea( $issues_data );
                                    }
                                ?></textarea>
                            </div>
                        </div>

                        <!-- ADMIN SUPERVISOR INPUTS & K3 -->
                        <div style="background:#131a29; border:2px solid var(--wow-gold); border-radius:8px; padding:18px; margin-top:16px;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                                <span style="font-size:20px;">👑</span>
                                <h4 style="font-family:var(--font-title); font-size:14px; color:#ffd700; margin:0;">
                                    ARAHAN PENGAWAS, RENCANA BESOK & KESELAMATAN KERJA (K3)
                                </h4>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                                <div class="sgd-form-group" style="margin-bottom:0;">
                                    <label class="sgd-form-label" style="color:#ffd700;">Instruksi Pengawas (Admin Input):</label>
                                    <textarea name="supervisor_instruction" class="sgd-form-textarea" rows="3" placeholder="Instruksi khusus pengawas/site manager..."><?php echo esc_textarea( $active_report->supervisor_instruction ); ?></textarea>
                                </div>
                                <div class="sgd-form-group" style="margin-bottom:0;">
                                    <label class="sgd-form-label" style="color:#00e5ff;">Rencana Kerja Besok (Admin Input):</label>
                                    <textarea name="next_day_plan" class="sgd-form-textarea" rows="3" placeholder="Target dan jadwal esok hari..."><?php echo esc_textarea( $active_report->next_day_plan ); ?></textarea>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="sgd-form-group" style="margin-bottom:0;">
                                    <label class="sgd-form-label" style="color:#00b894;">Keselamatan Kerja (K3):</label>
                                    <input type="text" name="safety_k3" value="<?php echo esc_attr( $active_report->safety_k3 ?: 'Nihil kecelakaan kerja' ); ?>" class="sgd-form-input">
                                </div>
                                <div class="sgd-form-group" style="margin-bottom:0;">
                                    <label class="sgd-form-label" style="color:#dfe6e9;">Catatan Lain:</label>
                                    <input type="text" name="additional_notes" value="<?php echo esc_attr( $active_report->additional_notes ); ?>" class="sgd-form-input" placeholder="Catatan tambahan bila ada...">
                                </div>
                            </div>
                        </div>

                        <!-- PHOTO REPLACEMENT -->
                        <div class="sgd-photos-upload-container" style="margin-top:16px;">
                            <div class="sgd-photos-header">
                                <span style="font-size:18px;">📸</span>
                                <div>
                                    <strong style="color:#fff; font-size:12px; font-family:var(--font-pixel);">FOTO DOKUMENTASI PROGRESS (GANTI / UNGGAH FOTO BARU)</strong>
                                </div>
                            </div>
                            <div class="sgd-photos-grid">
                                <?php for ( $pi = 1; $pi <= 3; $pi++ ): 
                                    $curr_photo = $active_report->{'photo_' . $pi};
                                ?>
                                    <div class="sgd-photo-slot">
                                        <div class="sgd-photo-slot-header">
                                            <span class="sgd-photo-slot-num"><?php echo $pi; ?></span>
                                            <span class="sgd-photo-slot-tag">Foto Progress #<?php echo $pi; ?></span>
                                        </div>
                                        <div class="sgd-photo-preview-box">
                                            <?php if ( ! empty( $curr_photo ) ): ?>
                                                <img src="<?php echo esc_url( $curr_photo ); ?>" alt="Foto Progress <?php echo $pi; ?>" class="sgd-photo-img-preview" style="display:block;">
                                            <?php else: ?>
                                                <div class="sgd-photo-placeholder">
                                                    <span style="font-size:32px;">📷</span>
                                                    <span>Kosong</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="progress_photo_<?php echo $pi; ?>" id="edit-file-photo-<?php echo $pi; ?>" class="sgd-photo-file-input" accept="image/*" capture="environment">
                                        <div class="sgd-photo-actions">
                                            <label for="edit-file-photo-<?php echo $pi; ?>" class="sgd-btn sgd-btn-sm sgd-btn-blue sgd-upload-trigger">
                                                Ganti Foto
                                            </label>
                                            <?php if ( ! empty( $curr_photo ) ): ?>
                                                <label style="font-size:10px; color:#ff7675; display:inline-flex; align-items:center; gap:4px; margin-left:6px; cursor:pointer;">
                                                    <input type="checkbox" name="remove_photo_<?php echo $pi; ?>" value="1"> Hapus
                                                </label>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- SAVE BUTTON -->
                        <div style="margin-top:20px; text-align:right;">
                            <button type="submit" class="sgd-btn sgd-btn-gold" style="font-size:14px; padding:12px 32px;">
                                💾 SIMPAN PERUBAHAN LAPORAN (SAVE CHANGES)
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="background:#161c28; border:1px solid #2f3c55; padding:24px; text-align:center; border-radius:8px; color:#8fa0bd;">
                        🔒 Mode baca saja. Hanya administrator atau pembuat laporan yang dapat mengubah data ini.
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============================================== -->
            <!-- TAB 3: 3 PROGRESS PHOTOS GALLERY               -->
            <!-- ============================================== -->
            <div id="view-mode-photos" class="sgd-mode-section" style="display:none;">
                <div class="sgd-photos-gallery-box">
                    <div class="sgd-gallery-grid">
                        <?php 
                        $has_any_photo = false;
                        for ( $pi = 1; $pi <= 3; $pi++ ): 
                            $curr_photo = $active_report->{'photo_' . $pi};
                            if ( ! empty( $curr_photo ) ):
                                $has_any_photo = true;
                        ?>
                            <div class="sgd-gallery-card">
                                <div class="sgd-gallery-card-img-wrap">
                                    <img src="<?php echo esc_url( $curr_photo ); ?>" alt="Progress Photo <?php echo $pi; ?>" onclick="window.open(this.src, '_blank');">
                                </div>
                                <div class="sgd-gallery-card-footer">
                                    <span style="font-family:var(--font-pixel); font-size:9px; color:#ffd700;">FOTO PROGRESS #<?php echo $pi; ?></span>
                                    <a href="<?php echo esc_url( $curr_photo ); ?>" target="_blank" class="sgd-btn sgd-btn-sm sgd-btn-blue" style="font-size:9px; padding:2px 8px;">
                                        🔍 Perbesar
                                    </a>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endfor; 
                        ?>
                        <?php if ( ! $has_any_photo ): ?>
                            <div style="grid-column:1/-1; background:#161c28; border:1px dashed #2f3c55; padding:40px; text-align:center; border-radius:8px; color:#8fa0bd;">
                                📷 Belum ada foto progress yang diunggah untuk laporan ini.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    <?php elseif ( ! $show_new_form ): ?>
        <!-- EMPTY STATE: NO REPORTS FOR THIS DATE -->
        <div class="sgd-empty-report-card">
            <span style="font-size:48px;">📅</span>
            <h3 style="font-family:var(--font-title); font-size:18px; color:#fff; margin:10px 0 6px 0;">
                Belum Ada Laporan pada Tanggal Ini
            </h3>
            <p style="color:#8fa0bd; font-size:13px; max-width:500px; margin:0 auto 18px auto;">
                Tidak ada laporan kerja yang dikirimkan pada <?php echo esc_html( $formatted_date_id ); ?>.
            </p>
            <a href="<?php echo esc_url( add_query_arg( [ 'sgd_page' => 'laporan-harian', 'report_date' => $selected_date, 'action' => 'new' ], home_url( '/' ) ) ); ?>" class="sgd-btn sgd-btn-gold">
                ➕ Buat Laporan Harian untuk Tanggal Ini
            </a>
        </div>
    <?php endif; ?>

</div>

<!-- PRINTABLE TEMPLATE (PRINT ONLY) -->
<div class="sgd-print-container" style="display:none;">
    <?php if ( $active_report ): ?>
        <div class="sgd-print-header">
            <div style="text-align:center; border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:15px;">
                <h2 style="margin:0; font-size:18pt; text-transform:uppercase;">LAPORAN HARIAN PROYEK KONSTRUKSI</h2>
                <div style="font-size:11pt; margin-top:4px;">Proyek: SGD Gamification & Construction Management Portal</div>
                <div style="font-size:10pt; color:#444;">Hari/Tanggal: <?php echo esc_html( $formatted_date_id ); ?> | Pelapor: <?php echo esc_html( $author_display ); ?></div>
            </div>
        </div>
        <div class="sgd-print-body">
            <pre style="font-family:monospace; font-size:11pt; white-space:pre-wrap; line-height:1.4; border:1px solid #ccc; padding:15px;"><?php echo esc_html( $formatted_text_output ); ?></pre>
            
            <?php if ( ! empty($active_report->photo_1) || ! empty($active_report->photo_2) || ! empty($active_report->photo_3) ): ?>
                <div style="margin-top:20px; page-break-inside:avoid;">
                    <h4 style="border-bottom:1px solid #000; padding-bottom:4px;">DOKUMENTASI FOTO PROGRESS:</h4>
                    <div style="display:flex; gap:15px; margin-top:10px;">
                        <?php for ( $pi = 1; $pi <= 3; $pi++ ): 
                            $curr_photo = $active_report->{'photo_' . $pi};
                            if ( ! empty( $curr_photo ) ):
                        ?>
                            <div style="flex:1; text-align:center;">
                                <img src="<?php echo esc_url( $curr_photo ); ?>" style="max-width:100%; max-height:180px; object-fit:cover; border:1px solid #999;">
                                <div style="font-size:9pt; margin-top:4px;">Foto Progress #<?php echo $pi; ?></div>
                            </div>
                        <?php 
                            endif;
                        endfor; 
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="display:flex; justify-content:space-between; margin-top:40px; page-break-inside:avoid;">
                <div style="text-align:center; width:200px;">
                    <div>Dibuat Oleh:</div>
                    <div style="height:60px;"></div>
                    <div style="border-top:1px solid #000; font-weight:bold;"><?php echo esc_html( $author_display ); ?></div>
                    <div style="font-size:9pt;">Pelaksana / Mandor</div>
                </div>
                <div style="text-align:center; width:200px;">
                    <div>Disetujui Oleh:</div>
                    <div style="height:60px;"></div>
                    <div style="border-top:1px solid #000; font-weight:bold;">Grand Marshal Admin</div>
                    <div style="font-size:9pt;">Site Manager / Pengawas</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
