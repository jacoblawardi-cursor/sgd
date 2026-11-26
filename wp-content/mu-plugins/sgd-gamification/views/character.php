<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id = get_current_user_id();
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$user_login = $current_user ? $current_user->user_login : 'hero';
$display_name = $is_logged_in ? $current_user->display_name : 'Highlord Adraal';

$xp_data = sgd_get_user_xp_data( $user_id );
$user_diamonds = sgd_get_diamonds( $user_id );
$job_class = sgd_get_user_job_class( $user_id );
$user_avatar = sgd_get_user_avatar( $user_id );
$streak_info = sgd_get_user_streak( $user_id );
$active_rpg_class = sgd_get_user_rpg_class( $user_id );

// Fetch equipment data & calculated ILVL
$equipment_data = sgd_get_character_equipment( $user_id, $active_rpg_class );
$items = $equipment_data['items'];
$ilvl = $equipment_data['avg_ilvl'];

// Dynamic Achievement Points calculation matching WoW Armory scale
$ach_points = 12000 + ( (int) $xp_data['ach_count'] * 750 ) + ( (int) $xp_data['daily_done'] * 150 ) + ( (int) $xp_data['urgent_done'] * 300 ) + ( (int) $streak_info['current'] * 50 );
if ( $ach_points < 14500 ) $ach_points = 18445; // Default classic high score if fresh

// Class-specific titles & styling
$class_titles = [
    'paladin' => [ 'title' => 'Highlord', 'race_spec' => 'Human Protection Paladin', 'crest' => '🦁', 'theme_color' => '#f5ac3a' ],
    'warrior' => [ 'title' => 'Warlord', 'race_spec' => 'Orc Arms Warrior', 'crest' => '⚔️', 'theme_color' => '#c79c6e' ],
    'mage'    => [ 'title' => 'Archmage', 'race_spec' => 'High Elf Frost & Arcane Mage', 'crest' => '🔮', 'theme_color' => '#3fc7eb' ],
    'hunter'  => [ 'title' => 'Ranger-General', 'race_spec' => 'Night Elf Marksmanship Hunter', 'crest' => '🏹', 'theme_color' => '#aad372' ],
    'shaman'  => [ 'title' => 'Farseer', 'race_spec' => 'Tauren Enhancement Shaman', 'crest' => '⚡', 'theme_color' => '#0070de' ],
];
$class_meta = $class_titles[ $active_rpg_class ] ?? $class_titles['paladin'];
?>

<div class="sgd-armory-page-wrapper">

    <!-- WOW ARMORY TOP HEADER BANNER -->
    <header class="sgd-armory-header">
        <div class="sgd-armory-header-inner">
            
            <!-- Guild / Faction Crest Emblem -->
            <div class="sgd-armory-crest-box" title="<?php echo esc_attr( $xp_data['faction'] ); ?>">
                <div class="sgd-armory-crest-shield">
                    <span class="sgd-armory-crest-symbol"><?php echo $class_meta['crest']; ?></span>
                </div>
            </div>

            <!-- Character Title & Name & Badges -->
            <div class="sgd-armory-identity">
                <div class="sgd-armory-title-row">
                    <span class="sgd-armory-pre-title"><?php echo esc_html( $class_meta['title'] ); ?></span>
                    <h1 class="sgd-armory-char-name"><?php echo esc_html( $display_name ); ?></h1>
                    
                    <!-- Achievement Points Badge -->
                    <div class="sgd-armory-ach-badge" title="Total Achievement Points">
                        <span class="sgd-armory-badge-icon">🛡️</span>
                        <span class="sgd-armory-badge-val"><?php echo number_format( $ach_points ); ?></span>
                    </div>

                    <!-- Item Level (ILVL) Badge -->
                    <div class="sgd-armory-ilvl-badge" title="Equipped Item Level (Average of 16 gear slots)">
                        <span class="sgd-armory-badge-icon">⚔️</span>
                        <span class="sgd-armory-ilvl-val"><?php echo $ilvl; ?> ILVL</span>
                    </div>
                </div>

                <!-- Subtitle: Level, Race, Spec, Class, Guild, Realm -->
                <div class="sgd-armory-sub-info">
                    <span class="sgd-armory-level-spec">
                        <strong>Level <?php echo $xp_data['level']; ?></strong> <?php echo esc_html( $class_meta['race_spec'] ); ?>
                    </span>
                    <span class="sgd-armory-guild-tag">
                        &lt;Sinar Graha Mandiri&gt;
                    </span>
                    <span class="sgd-armory-realm-tag">
                        <?php echo esc_html( $xp_data['faction'] ); ?>
                    </span>
                </div>
            </div>

            <!-- Quick Action: Switch RPG Class -->
            <div class="sgd-armory-class-picker-quick">
                <form method="post" id="sgd-class-switch-form" class="sgd-class-switch-form">
                    <input type="hidden" name="sgd_action" value="sgd_change_rpg_class">
                    <input type="hidden" name="redirect_to" value="character">
                    <label class="sgd-class-picker-label">Hero Class:</label>
                    <select name="rpg_class" onchange="this.form.submit()" class="sgd-class-select">
                        <option value="paladin" <?php selected( $active_rpg_class, 'paladin' ); ?>>🛡️ Paladin (Holy Crusader)</option>
                        <option value="warrior" <?php selected( $active_rpg_class, 'warrior' ); ?>>⚔️ Warrior (Colossus)</option>
                        <option value="mage" <?php selected( $active_rpg_class, 'mage' ); ?>>🧙‍♂️ Mage (Archmage)</option>
                        <option value="hunter" <?php selected( $active_rpg_class, 'hunter' ); ?>>🏹 Hunter (Ranger General)</option>
                        <option value="shaman" <?php selected( $active_rpg_class, 'shaman' ); ?>>⚡ Shaman (Stormcaller)</option>
                    </select>
                </form>
            </div>

        </div>
    </header>

    <!-- SECONDARY SUBNAV TABS (CHARACTER, ACHIEVEMENTS, COLLECTIONS, RAID PROGRESSION, PLAYER VS PLAYER, REPUTATION) -->
    <nav class="sgd-armory-subnav">
        <div class="sgd-armory-subnav-inner">
            <button type="button" class="sgd-armory-nav-tab active" data-tab="character">CHARACTER</button>
            <button type="button" class="sgd-armory-nav-tab" data-tab="achievements">ACHIEVEMENTS</button>
            <button type="button" class="sgd-armory-nav-tab" data-tab="collections">COLLECTIONS</button>
            <button type="button" class="sgd-armory-nav-tab" data-tab="raid">RAID PROGRESSION</button>
            <button type="button" class="sgd-armory-nav-tab" data-tab="pvp">PLAYER VS PLAYER</button>
            <button type="button" class="sgd-armory-nav-tab" data-tab="reputation">REPUTATION</button>
        </div>
    </nav>

    <!-- ======================================================== -->
    <!-- TAB 1: CHARACTER ARMORY (MAIN GEAR & 3D MODEL VIEW) -->
    <!-- ======================================================== -->
    <div class="sgd-armory-tab-pane active" id="pane-character">
        
        <div class="sgd-armory-stage-container">
            
            <!-- Cathedral Background Ambient Light & Beams -->
            <div class="sgd-cathedral-backdrop">
                <div class="sgd-light-shaft left-shaft"></div>
                <div class="sgd-light-shaft center-shaft"></div>
                <div class="sgd-light-shaft right-shaft"></div>
                <div class="sgd-lantern left-lantern"></div>
                <div class="sgd-lantern right-lantern"></div>
            </div>

            <!-- THREE COLUMN WOW EQUIPMENT & CHARACTER MODEL GRID -->
            <div class="sgd-armory-dual-columns">

                <!-- ================= LEFT EQUIPMENT COLUMN (8 SLOTS) ================= -->
                <div class="sgd-gear-col sgd-gear-col-left">
                    <?php 
                    $left_slots = [ 'head', 'neck', 'shoulders', 'back', 'chest', 'shirt', 'tabard', 'wrists' ];
                    foreach ( $left_slots as $slot_key ):
                        $item = $items[ $slot_key ];
                    ?>
                        <div class="sgd-gear-slot-item sgd-gear-left" 
                             data-slot="<?php echo esc_attr( $slot_key ); ?>"
                             data-quality="<?php echo esc_attr( $item['quality'] ); ?>"
                             data-name="<?php echo esc_attr( $item['name'] ); ?>"
                             data-ilvl="<?php echo esc_attr( $item['ilvl'] ); ?>"
                             data-type="<?php echo esc_attr( $item['type'] ); ?>"
                             data-stats="<?php echo esc_attr( $item['stats'] ); ?>"
                             data-flavor="<?php echo esc_attr( $item['flavor'] ); ?>">
                            
                            <div class="sgd-slot-icon-box sgd-rarity-<?php echo esc_attr( $item['quality'] ); ?>">
                                <span class="sgd-slot-glyph"><?php echo $item['icon']; ?></span>
                                <?php if ( ! empty( $item['socket'] ) ): ?>
                                    <span class="sgd-slot-gem-socket <?php echo esc_attr( $item['socket'] ); ?>"></span>
                                <?php endif; ?>
                            </div>

                            <div class="sgd-slot-text-meta">
                                <span class="sgd-slot-item-name sgd-text-<?php echo esc_attr( $item['quality'] ); ?>">
                                    <?php echo esc_html( $item['name'] ); ?>
                                </span>
                                <span class="sgd-slot-ilvl-tag">
                                    <?php echo $item['ilvl']; ?>
                                    <?php if ( ! empty( $item['socket'] ) && $item['socket'] === 'gemfinding' ): ?>
                                        <span class="sgd-gemfinding-label">Gemfinding</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ================= CENTER CHARACTER MODEL STAGE ================= -->
                <div class="sgd-character-model-stage" id="sgd-model-stage">
                    
                    <!-- Atmospheric Pedestal Shadow & Radial Aura -->
                    <div class="sgd-pedestal-light-cone"></div>

                    <!-- Stylized Vector Hero Character Model (SVG) -->
                    <div class="sgd-char-model-render-wrap" id="sgd-char-figure" title="<?php echo esc_attr( $display_name . ' (' . ucfirst($active_rpg_class) . ')' ); ?>">
                        
                        <svg class="sgd-char-model-svg" viewBox="0 0 380 500" width="100%" height="100%">
                            <defs>
                                <!-- Radiant Aura Gradient -->
                                <radialGradient id="auraGlow" cx="50%" cy="40%" r="50%">
                                    <stop offset="0%" stop-color="<?php echo $class_meta['theme_color']; ?>" stop-opacity="0.45" />
                                    <stop offset="60%" stop-color="<?php echo $class_meta['theme_color']; ?>" stop-opacity="0.12" />
                                    <stop offset="100%" stop-color="#000" stop-opacity="0" />
                                </radialGradient>

                                <!-- Ashbringer Holy Flame Gradient -->
                                <linearGradient id="ashbringerFlame" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#fffb99" />
                                    <stop offset="35%" stop-color="#ff9900" />
                                    <stop offset="85%" stop-color="#e63900" />
                                    <stop offset="100%" stop-color="#730d00" />
                                </linearGradient>

                                <!-- Armor Gold Sheen -->
                                <linearGradient id="goldArmor" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#ffe885" />
                                    <stop offset="40%" stop-color="#c9982a" />
                                    <stop offset="70%" stop-color="#8c6214" />
                                    <stop offset="100%" stop-color="#4d3205" />
                                </linearGradient>

                                <!-- Silver & Steel Plate -->
                                <linearGradient id="steelPlate" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#e6ecf5" />
                                    <stop offset="50%" stop-color="#8a99ad" />
                                    <stop offset="100%" stop-color="#3d4957" />
                                </linearGradient>

                                <!-- Royal Blue Cloth & Tabard -->
                                <linearGradient id="royalBlue" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" stop-color="#1e3c72" />
                                    <stop offset="50%" stop-color="#14244d" />
                                    <stop offset="100%" stop-color="#0b132b" />
                                </linearGradient>

                                <!-- Pedestal Runes Glow -->
                                <radialGradient id="runeRing" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#00e5ff" stop-opacity="0.6" />
                                    <stop offset="70%" stop-color="#0077b6" stop-opacity="0.25" />
                                    <stop offset="100%" stop-color="#000" stop-opacity="0" />
                                </radialGradient>

                                <filter id="glowEffect" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="4" result="blur" />
                                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                                </filter>
                            </defs>

                            <!-- PEDESTAL FLOOR & RUNIC CIRCLE -->
                            <g class="sgd-svg-pedestal" transform="translate(190, 440)">
                                <!-- Base Stone Dais -->
                                <ellipse cx="0" cy="15" rx="140" ry="35" fill="#0c1017" stroke="#364153" stroke-width="3" />
                                <ellipse cx="0" cy="10" rx="130" ry="30" fill="url(#runeRing)" />
                                
                                <!-- Glowing Rune Concentric Rings -->
                                <ellipse cx="0" cy="10" rx="115" ry="24" fill="none" stroke="#00e5ff" stroke-width="1.8" stroke-dasharray="6,8" opacity="0.85" class="sgd-rune-spin-ring" />
                                <ellipse cx="0" cy="10" rx="85" ry="18" fill="none" stroke="<?php echo $class_meta['theme_color']; ?>" stroke-width="1.5" stroke-dasharray="4,6" opacity="0.9" />
                                
                                <!-- Pedestal Rim Highlight -->
                                <ellipse cx="0" cy="10" rx="128" ry="28" fill="none" stroke="#ffd700" stroke-width="1" opacity="0.4" />
                            </g>

                            <!-- HERO BODY & ARMOR GROUP WITH IDLE BREATHING ANIMATION -->
                            <g class="sgd-hero-body-group">
                                
                                <!-- Back Cape / Cloak -->
                                <path d="M120 180 Q105 320 110 420 Q190 435 270 420 Q275 320 260 180 Z" 
                                      fill="url(#royalBlue)" stroke="#090d17" stroke-width="2" opacity="0.95" />
                                <path d="M110 420 Q190 440 270 420" stroke="#ffd700" stroke-width="3" fill="none" />

                                <!-- Legs & Armored Greaves -->
                                <g class="sgd-hero-legs">
                                    <!-- Left Leg -->
                                    <path d="M150 300 L140 415 L175 415 L178 300 Z" fill="url(#steelPlate)" stroke="#1a202c" stroke-width="2" />
                                    <!-- Right Leg -->
                                    <path d="M202 300 L205 415 L240 415 L230 300 Z" fill="url(#steelPlate)" stroke="#1a202c" stroke-width="2" />
                                    <!-- Knee Plates with Gold Trim -->
                                    <rect x="140" y="325" width="35" height="22" rx="4" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="1.5" />
                                    <rect x="205" y="325" width="35" height="22" rx="4" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="1.5" />
                                    <!-- Armored Sabatons / Boots -->
                                    <path d="M135 405 L178 405 L182 422 L130 422 Z" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2" />
                                    <path d="M202 405 L245 405 L250 422 L198 422 Z" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2" />
                                </g>

                                <!-- Torso & Cuirass -->
                                <g class="sgd-hero-torso">
                                    <path d="M135 170 L245 170 L235 305 L145 305 Z" fill="url(#steelPlate)" stroke="#111827" stroke-width="2" />
                                    
                                    <!-- Ornate Gold Breastplate Filigree & Lion Crest -->
                                    <path d="M145 175 Q190 205 235 175 L225 295 Q190 310 155 295 Z" fill="url(#goldArmor)" stroke="#4a3508" stroke-width="2" />
                                    
                                    <!-- Central Guild Emblem / Gem on Chest -->
                                    <circle cx="190" cy="225" r="16" fill="#1e3c72" stroke="#ffe885" stroke-width="2.5" />
                                    <polygon points="190,214 195,224 204,225 197,231 199,239 190,234 181,239 183,231 176,225 185,224" fill="#ffd700" />

                                    <!-- Hanging Guild Tabard -->
                                    <path d="M168 285 L212 285 L218 380 Q190 395 162 380 Z" fill="url(#royalBlue)" stroke="#c69b3a" stroke-width="2" />
                                    <line x1="190" y1="290" x2="190" y2="375" stroke="#ffd700" stroke-width="2" stroke-dasharray="3,3" />
                                    <circle cx="190" cy="335" r="6" fill="#ffd700" />
                                    
                                    <!-- Golden Belt & Clasp -->
                                    <rect x="140" y="278" width="100" height="18" rx="3" fill="url(#goldArmor)" stroke="#261702" stroke-width="1.8" />
                                    <rect x="178" y="273" width="24" height="28" rx="4" fill="#ffd700" stroke="#4a3508" stroke-width="2" />
                                    <circle cx="190" cy="287" r="5" fill="#00e5ff" filter="url(#glowEffect)" />
                                </g>

                                <!-- Massive Glowing Tier Shoulder Pauldrons -->
                                <g class="sgd-hero-shoulders">
                                    <!-- Left Pauldron (Viewer's Left) -->
                                    <g transform="translate(0, 0)">
                                        <path d="M95 130 L160 145 L155 195 L90 175 Z" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2.5" />
                                        <path d="M85 140 L125 105 L155 130 L115 160 Z" fill="url(#goldArmor)" stroke="#ffe885" stroke-width="2" />
                                        <!-- Pauldron Spikes / Winged Tier -->
                                        <polygon points="85,140 70,115 105,120" fill="#ffd700" stroke="#2d1c03" stroke-width="1.5" />
                                        <circle cx="120" cy="150" r="10" fill="#14244d" stroke="#ffd700" stroke-width="2" />
                                        <circle cx="120" cy="150" r="4" fill="#00e5ff" filter="url(#glowEffect)" />
                                    </g>

                                    <!-- Right Pauldron (Viewer's Right) -->
                                    <g transform="translate(0, 0)">
                                        <path d="M285 130 L220 145 L225 195 L290 175 Z" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2.5" />
                                        <path d="M295 140 L255 105 L225 130 L265 160 Z" fill="url(#goldArmor)" stroke="#ffe885" stroke-width="2" />
                                        <!-- Pauldron Spikes / Winged Tier -->
                                        <polygon points="295,140 310,115 275,120" fill="#ffd700" stroke="#2d1c03" stroke-width="1.5" />
                                        <circle cx="260" cy="150" r="10" fill="#14244d" stroke="#ffd700" stroke-width="2" />
                                        <circle cx="260" cy="150" r="4" fill="#00e5ff" filter="url(#glowEffect)" />
                                    </g>
                                </g>

                                <!-- Head & Grand Helm / Crown -->
                                <g class="sgd-hero-head">
                                    <!-- High Armored Gorget / Neck -->
                                    <path d="M165 160 L215 160 L210 185 L170 185 Z" fill="url(#steelPlate)" stroke="#261702" stroke-width="1.5" />
                                    
                                    <!-- Helmet Dome & Cheeks -->
                                    <path d="M162 105 Q190 70 218 105 L224 150 Q190 170 156 150 Z" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2.5" />
                                    
                                    <!-- Ornate Halo / Crown Horns on Helm -->
                                    <path d="M158 95 L145 65 L170 85 Z" fill="#ffd700" stroke="#2d1c03" stroke-width="1.5" />
                                    <path d="M222 95 L235 65 L210 85 Z" fill="#ffd700" stroke="#2d1c03" stroke-width="1.5" />
                                    <polygon points="190,60 184,80 196,80" fill="#ffd700" stroke="#2d1c03" stroke-width="1.5" />

                                    <!-- Faceplate T-Visor with Glowing Eyes -->
                                    <path d="M172 118 L208 118 L204 140 L190 148 L176 140 Z" fill="#0b0e14" stroke="#4a3508" stroke-width="2" />
                                    <line x1="175" y1="126" x2="205" y2="126" stroke="#00e5ff" stroke-width="3" filter="url(#glowEffect)" />
                                    <circle cx="182" cy="126" r="2.5" fill="#ffffff" filter="url(#glowEffect)" />
                                    <circle cx="198" cy="126" r="2.5" fill="#ffffff" filter="url(#glowEffect)" />
                                </g>

                                <!-- Off Hand: Ornate Alliance / Guild Shield (Viewer's Right) -->
                                <g class="sgd-hero-shield" transform="translate(265, 200)">
                                    <!-- Shield Body -->
                                    <path d="M0 20 L40 0 L55 90 L10 160 L-15 90 Z" fill="url(#royalBlue)" stroke="#ffd700" stroke-width="4" filter="url(#glowEffect)" />
                                    <path d="M5 25 L35 10 L48 85 L10 145 L-8 85 Z" fill="#14244d" stroke="#c69b3a" stroke-width="1.5" />
                                    <!-- Golden Lion Emblem on Shield -->
                                    <circle cx="18" cy="70" r="16" fill="url(#goldArmor)" stroke="#ffd700" stroke-width="2" />
                                    <polygon points="18,58 22,66 30,68 24,74 26,82 18,77 10,82 12,74 6,68 14,66" fill="#ffffff" />
                                    <!-- Armored Forearm Holding Shield -->
                                    <rect x="-20" y="55" width="25" height="35" rx="5" fill="url(#steelPlate)" stroke="#111827" stroke-width="2" transform="rotate(-15)" />
                                </g>

                                <!-- Main Hand: The Legendary Glowing Ashbringer / Weapon (Viewer's Left) -->
                                <g class="sgd-hero-weapon" transform="translate(75, 170)">
                                    <!-- Armored Forearm Holding Sword Hilt -->
                                    <rect x="25" y="85" width="25" height="38" rx="5" fill="url(#steelPlate)" stroke="#111827" stroke-width="2" transform="rotate(15)" />
                                    <ellipse cx="40" cy="95" rx="10" ry="8" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2" />

                                    <!-- ASHBRINGER SWORD GROUP WITH PULSATING FLAME -->
                                    <g class="sgd-ashbringer-blade">
                                        <!-- Glowing Holy Flame Aura -->
                                        <ellipse cx="20" cy="40" rx="36" ry="110" fill="url(#auraGlow)" filter="url(#glowEffect)" />

                                        <!-- Flame Particle Blade -->
                                        <path d="M12 -80 Q20 -110 28 -80 L35 70 L5 70 Z" fill="url(#ashbringerFlame)" filter="url(#glowEffect)" />
                                        
                                        <!-- Floating Holy Disc / Sun Core of the Ashbringer -->
                                        <circle cx="20" cy="-10" r="18" fill="url(#ashbringerFlame)" stroke="#ffe885" stroke-width="2.5" filter="url(#glowEffect)" />
                                        <circle cx="20" cy="-10" r="9" fill="#ffffff" filter="url(#glowEffect)" />
                                        
                                        <!-- Heavy Crossguard & Pommel -->
                                        <rect x="-8" y="68" width="56" height="12" rx="3" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2" />
                                        <rect x="15" y="80" width="10" height="35" fill="#3d2806" stroke="#c69b3a" stroke-width="1.5" />
                                        <circle cx="20" cy="120" r="9" fill="url(#goldArmor)" stroke="#2d1c03" stroke-width="2" />
                                    </g>
                                </g>

                            </g>
                        </svg>

                    </div>

                    <!-- Stage Model Controls Bar -->
                    <div class="sgd-stage-model-controls">
                        <div class="sgd-model-class-buttons">
                            <?php 
                            $classes_list = [
                                'paladin' => [ 'icon' => '🛡️', 'label' => 'Paladin' ],
                                'warrior' => [ 'icon' => '⚔️', 'label' => 'Warrior' ],
                                'mage'    => [ 'icon' => '🧙‍♂️', 'label' => 'Mage' ],
                                'hunter'  => [ 'icon' => '🏹', 'label' => 'Hunter' ],
                                'shaman'  => [ 'icon' => '⚡', 'label' => 'Shaman' ],
                            ];
                            foreach ( $classes_list as $c_key => $c_val ):
                            ?>
                                <button type="button" 
                                        class="sgd-class-btn <?php echo ( $active_rpg_class === $c_key ) ? 'active' : ''; ?>" 
                                        onclick="sgdSwitchClassModel('<?php echo esc_js( $c_key ); ?>')"
                                        title="Switch Model to <?php echo esc_attr( $c_val['label'] ); ?>">
                                    <span><?php echo $c_val['icon']; ?></span>
                                    <span><?php echo esc_html( $c_val['label'] ); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="sgd-model-toggles">
                            <button type="button" class="sgd-toggle-fx-btn active" id="sgd-fx-glow-toggle" onclick="sgdToggleCombatAura()" title="Toggle Weapon Holy Flame & Ambient Aura">
                                ✨ Combat Aura
                            </button>
                            <button type="button" class="sgd-toggle-fx-btn" id="sgd-tilt-toggle" onclick="sgdResetModelStance()" title="Reset Stance">
                                🔄 Center Pose
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Weapon Slots Bar (Main Hand & Off Hand) -->
                    <div class="sgd-bottom-weapon-slots">
                        
                        <!-- Main Hand -->
                        <?php $main_hand = $items['main_hand']; ?>
                        <div class="sgd-gear-slot-item sgd-gear-weapon"
                             data-slot="main_hand"
                             data-quality="<?php echo esc_attr( $main_hand['quality'] ); ?>"
                             data-name="<?php echo esc_attr( $main_hand['name'] ); ?>"
                             data-ilvl="<?php echo esc_attr( $main_hand['ilvl'] ); ?>"
                             data-type="<?php echo esc_attr( $main_hand['type'] ); ?>"
                             data-stats="<?php echo esc_attr( $main_hand['stats'] ); ?>"
                             data-flavor="<?php echo esc_attr( $main_hand['flavor'] ); ?>">
                            
                            <div class="sgd-slot-icon-box sgd-rarity-<?php echo esc_attr( $main_hand['quality'] ); ?>">
                                <span class="sgd-slot-glyph"><?php echo $main_hand['icon']; ?></span>
                                <?php if ( ! empty( $main_hand['socket'] ) ): ?>
                                    <span class="sgd-slot-gem-socket <?php echo esc_attr( $main_hand['socket'] ); ?>"></span>
                                <?php endif; ?>
                            </div>

                            <div class="sgd-slot-text-meta">
                                <span class="sgd-slot-item-name sgd-text-<?php echo esc_attr( $main_hand['quality'] ); ?>">
                                    <?php echo esc_html( $main_hand['name'] ); ?>
                                </span>
                                <span class="sgd-slot-ilvl-tag">
                                    <?php echo $main_hand['ilvl']; ?> Main Hand
                                </span>
                            </div>
                        </div>

                        <!-- Off Hand -->
                        <?php $off_hand = $items['off_hand']; ?>
                        <div class="sgd-gear-slot-item sgd-gear-weapon"
                             data-slot="off_hand"
                             data-quality="<?php echo esc_attr( $off_hand['quality'] ); ?>"
                             data-name="<?php echo esc_attr( $off_hand['name'] ); ?>"
                             data-ilvl="<?php echo esc_attr( $off_hand['ilvl'] ); ?>"
                             data-type="<?php echo esc_attr( $off_hand['type'] ); ?>"
                             data-stats="<?php echo esc_attr( $off_hand['stats'] ); ?>"
                             data-flavor="<?php echo esc_attr( $off_hand['flavor'] ); ?>">
                            
                            <div class="sgd-slot-icon-box sgd-rarity-<?php echo esc_attr( $off_hand['quality'] ); ?>">
                                <span class="sgd-slot-glyph"><?php echo $off_hand['icon']; ?></span>
                            </div>

                            <div class="sgd-slot-text-meta">
                                <span class="sgd-slot-item-name sgd-text-<?php echo esc_attr( $off_hand['quality'] ); ?>">
                                    <?php echo esc_html( $off_hand['name'] ); ?>
                                </span>
                                <span class="sgd-slot-ilvl-tag">
                                    <?php echo $off_hand['ilvl']; ?> Off Hand
                                </span>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- ================= RIGHT EQUIPMENT COLUMN (8 SLOTS) ================= -->
                <div class="sgd-gear-col sgd-gear-col-right">
                    <?php 
                    $right_slots = [ 'hands', 'waist', 'legs', 'feet', 'finger1', 'finger2', 'trinket1', 'trinket2' ];
                    foreach ( $right_slots as $slot_key ):
                        $item = $items[ $slot_key ];
                    ?>
                        <div class="sgd-gear-slot-item sgd-gear-right"
                             data-slot="<?php echo esc_attr( $slot_key ); ?>"
                             data-quality="<?php echo esc_attr( $item['quality'] ); ?>"
                             data-name="<?php echo esc_attr( $item['name'] ); ?>"
                             data-ilvl="<?php echo esc_attr( $item['ilvl'] ); ?>"
                             data-type="<?php echo esc_attr( $item['type'] ); ?>"
                             data-stats="<?php echo esc_attr( $item['stats'] ); ?>"
                             data-flavor="<?php echo esc_attr( $item['flavor'] ); ?>">
                            
                            <div class="sgd-slot-text-meta sgd-align-right">
                                <span class="sgd-slot-item-name sgd-text-<?php echo esc_attr( $item['quality'] ); ?>">
                                    <?php echo esc_html( $item['name'] ); ?>
                                </span>
                                <span class="sgd-slot-ilvl-tag">
                                    <?php echo $item['ilvl']; ?>
                                </span>
                            </div>

                            <div class="sgd-slot-icon-box sgd-rarity-<?php echo esc_attr( $item['quality'] ); ?>">
                                <span class="sgd-slot-glyph"><?php echo $item['icon']; ?></span>
                                <?php if ( ! empty( $item['socket'] ) ): ?>
                                    <span class="sgd-slot-gem-socket <?php echo esc_attr( $item['socket'] ); ?>"></span>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

        </div>

        <!-- WOW CHARACTER STATS ACCORDION PANEL -->
        <div class="sgd-armory-stats-panel">
            <div class="sgd-stats-panel-header">
                <h3>⚔️ ATTRIBUTES & GUILD PERFORMANCE METRICS</h3>
                <span class="sgd-stats-spec-pill"><?php echo esc_html( $class_meta['race_spec'] ); ?></span>
            </div>

            <div class="sgd-stats-grid">
                
                <!-- Primary Attributes Column -->
                <div class="sgd-stats-col">
                    <div class="sgd-stats-col-title">Primary Attributes</div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label"><?php echo esc_html( $equipment_data['primary_stat'] ); ?></span>
                        <span class="sgd-stat-val" style="color:#00e5ff;"><?php echo number_format( 8420 + ( $xp_data['level'] * 85 ) ); ?></span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Stamina (Health)</span>
                        <span class="sgd-stat-val" style="color:#2ecc71;"><?php echo number_format( 12850 + ( $xp_data['level'] * 140 ) ); ?></span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Armor</span>
                        <span class="sgd-stat-val"><?php echo number_format( 4620 + ( $xp_data['level'] * 45 ) ); ?></span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Total Diamonds / Gold</span>
                        <span class="sgd-stat-val" style="color:#ffd700;"><?php echo number_format( $user_diamonds ); ?> 💎</span>
                    </div>
                </div>

                <!-- Secondary Combat Enhancements -->
                <div class="sgd-stats-col">
                    <div class="sgd-stats-col-title">Combat & Focus Enhancements</div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Critical Strike</span>
                        <span class="sgd-stat-val">28.4% (+<?php echo number_format( 2840 ); ?>)</span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Haste (Task Speed)</span>
                        <span class="sgd-stat-val">19.2% (+<?php echo number_format( 1920 ); ?>)</span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Mastery (Specialty)</span>
                        <span class="sgd-stat-val" style="color:#a335ee;">42.8% (+<?php echo number_format( 4280 ); ?>)</span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Versatility</span>
                        <span class="sgd-stat-val">12.1% (+<?php echo number_format( 1210 ); ?>)</span>
                    </div>
                </div>

                <!-- Guild Quests & Diligence Record -->
                <div class="sgd-stats-col">
                    <div class="sgd-stats-col-title">Guild Productivity Log</div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Daily Tasks Cleared</span>
                        <span class="sgd-stat-val"><?php echo $xp_data['daily_done']; ?> Done</span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Urgent Tasks Handled</span>
                        <span class="sgd-stat-val" style="color:#ff4444;"><?php echo $xp_data['urgent_done']; ?> Done</span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Current Streak</span>
                        <span class="sgd-stat-val" style="color:#ff9f43;"><?php echo $streak_info['current']; ?> Days 🔥</span>
                    </div>
                    <div class="sgd-stat-row">
                        <span class="sgd-stat-label">Achievements Unlocked</span>
                        <span class="sgd-stat-val" style="color:#e056fd;"><?php echo $xp_data['ach_count']; ?> Feats</span>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- ======================================================== -->
    <!-- TAB 2: ACHIEVEMENTS OVERVIEW -->
    <!-- ======================================================== -->
    <div class="sgd-armory-tab-pane" id="pane-achievements">
        <div class="sgd-pane-content-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h2 style="font-family:var(--font-title); color:var(--wow-gold-light); font-size:22px; margin-bottom:4px;">
                        🏆 GUILD ACHIEVEMENTS & FEATS OF STRENGTH
                    </h2>
                    <p style="color:#94a3b8; font-size:13px;">
                        Total Points: <strong style="color:#ffd700;"><?php echo number_format( $ach_points ); ?> pts</strong> • Completed: <strong style="color:#fff;"><?php echo $xp_data['ach_count']; ?> Feats</strong>
                    </p>
                </div>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'achievements' ) ); ?>" class="sgd-btn sgd-btn-gold">
                    Buka Halaman Achievement Penuh ➔
                </a>
            </div>

            <div class="sgd-ach-highlight-grid">
                <div class="sgd-ach-card-mini">
                    <span class="sgd-ach-icon-mini">🔥</span>
                    <div>
                        <div class="sgd-ach-name-mini">Daily Streak Master</div>
                        <div class="sgd-ach-desc-mini">Menyelesaikan daily task berturut-turut tanpa terputus.</div>
                        <div class="sgd-ach-pts-mini">+500 pts • Completed</div>
                    </div>
                </div>
                <div class="sgd-ach-card-mini">
                    <span class="sgd-ach-icon-mini">⭐</span>
                    <div>
                        <div class="sgd-ach-name-mini">Mythic Paragon (Level 30+)</div>
                        <div class="sgd-ach-desc-mini">Mencapai Level 30 dan membuktikan dedikasi tanpa tanding.</div>
                        <div class="sgd-ach-pts-mini">+1,000 pts • <?php echo ($xp_data['level'] >= 30) ? 'Completed' : 'In Progress'; ?></div>
                    </div>
                </div>
                <div class="sgd-ach-card-mini">
                    <span class="sgd-ach-icon-mini">⚡</span>
                    <div>
                        <div class="sgd-ach-name-mini">First Responder</div>
                        <div class="sgd-ach-desc-mini">Menyelesaikan urgent task darurat sebelum batas waktu.</div>
                        <div class="sgd-ach-pts-mini">+750 pts • Completed</div>
                    </div>
                </div>
                <div class="sgd-ach-card-mini">
                    <span class="sgd-ach-icon-mini">🤝</span>
                    <div>
                        <div class="sgd-ach-name-mini">Pillar of the Alliance</div>
                        <div class="sgd-ach-desc-mini">Menerima dan memberikan Kudos apresiasi kepada rekan kerja.</div>
                        <div class="sgd-ach-pts-mini">+400 pts • Completed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 3: COLLECTIONS -->
    <!-- ======================================================== -->
    <div class="sgd-armory-tab-pane" id="pane-collections">
        <div class="sgd-pane-content-card">
            <h2 style="font-family:var(--font-title); color:var(--wow-gold-light); font-size:22px; margin-bottom:12px;">
                🎁 HERO COLLECTIONS & TITLES
            </h2>
            <p style="color:#94a3b8; font-size:13px; margin-bottom:20px;">
                Item kosmetik, gelar kehormatan, dan lencana yang telah Anda buka di Sinar Graha Mandiri.
            </p>

            <div class="sgd-collections-grid">
                <div class="sgd-col-card">
                    <div class="sgd-col-card-icon">👑</div>
                    <div class="sgd-col-card-title"><?php echo esc_html( $xp_data['title'] ); ?></div>
                    <div class="sgd-col-card-sub">Active Hero Title</div>
                </div>
                <div class="sgd-col-card">
                    <div class="sgd-col-card-icon"><?php echo $user_avatar; ?></div>
                    <div class="sgd-col-card-title">Equipped Avatar</div>
                    <div class="sgd-col-card-sub"><?php echo esc_html( $class_meta['race_spec'] ); ?></div>
                </div>
                <div class="sgd-col-card">
                    <div class="sgd-col-card-icon">🦁</div>
                    <div class="sgd-col-card-title">Guild Tabard of SGM</div>
                    <div class="sgd-col-card-sub">Cosmetic Uniform</div>
                </div>
                <div class="sgd-col-card">
                    <div class="sgd-col-card-icon">🗡️</div>
                    <div class="sgd-col-card-title"><?php echo esc_html( $items['main_hand']['name'] ); ?></div>
                    <div class="sgd-col-card-sub">Artifact Weapon Appearance</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 4: RAID PROGRESSION -->
    <!-- ======================================================== -->
    <div class="sgd-armory-tab-pane" id="pane-raid">
        <div class="sgd-pane-content-card">
            <h2 style="font-family:var(--font-title); color:var(--wow-gold-light); font-size:22px; margin-bottom:12px;">
                🏰 SPRINT RAID PROGRESSION
            </h2>
            <p style="color:#94a3b8; font-size:13px; margin-bottom:20px;">
                Catatan penyelesaian target raid sprint tim dan peluncuran proyek besar Sinar Graha Mandiri.
            </p>

            <div class="sgd-raid-list">
                <div class="sgd-raid-item cleared">
                    <div class="sgd-raid-banner-icon">⚔️</div>
                    <div class="sgd-raid-details">
                        <div class="sgd-raid-title">Sprint Q1: The Emerald Nightmare (Inisiasi Sistem)</div>
                        <div class="sgd-raid-status">7 / 7 Mythic Bosses Cleared (100%)</div>
                    </div>
                    <span class="sgd-raid-badge mythic">MYTHIC CLEARED</span>
                </div>

                <div class="sgd-raid-item cleared">
                    <div class="sgd-raid-banner-icon">🔮</div>
                    <div class="sgd-raid-details">
                        <div class="sgd-raid-title">Sprint Q2: The Nighthold (Automasi & Workflow)</div>
                        <div class="sgd-raid-status">10 / 10 Heroic Bosses Cleared (100%)</div>
                    </div>
                    <span class="sgd-raid-badge heroic">HEROIC CLEARED</span>
                </div>

                <div class="sgd-raid-item progress">
                    <div class="sgd-raid-banner-icon">🔥</div>
                    <div class="sgd-raid-details">
                        <div class="sgd-raid-title">Sprint Q3: Tomb of Sargeras (Ekspansi Lapangan)</div>
                        <div class="sgd-raid-status">6 / 9 Heroic Bosses Cleared (67%)</div>
                    </div>
                    <span class="sgd-raid-badge in-progress">IN PROGRESS</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 5: PLAYER VS PLAYER / HALL OF LEGENDS -->
    <!-- ======================================================== -->
    <div class="sgd-armory-tab-pane" id="pane-pvp">
        <div class="sgd-pane-content-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h2 style="font-family:var(--font-title); color:var(--wow-gold-light); font-size:22px; margin-bottom:4px;">
                        ⚔️ HALL OF LEGENDS & GUILD DUELS
                    </h2>
                    <p style="color:#94a3b8; font-size:13px;">
                        Peringkat hero teratas dan perolehan Kudos antar sesama pejuang divisi.
                    </p>
                </div>
                <a href="<?php echo esc_url( sgd_get_portal_url( 'leaderboard' ) ); ?>" class="sgd-btn sgd-btn-gold">
                    Buka Hall of Legends ➔
                </a>
            </div>

            <div class="sgd-pvp-stats-row">
                <div class="sgd-pvp-stat-box">
                    <div class="sgd-pvp-stat-num" style="color:#00e5ff;"><?php echo $xp_data['level']; ?></div>
                    <div class="sgd-pvp-stat-lbl">Hero Level</div>
                </div>
                <div class="sgd-pvp-stat-box">
                    <div class="sgd-pvp-stat-num" style="color:#ffd700;"><?php echo number_format( $xp_data['total_xp'] ); ?></div>
                    <div class="sgd-pvp-stat-lbl">Lifetime XP</div>
                </div>
                <div class="sgd-pvp-stat-box">
                    <div class="sgd-pvp-stat-num" style="color:#a335ee;"><?php echo $xp_data['kudos_received']; ?></div>
                    <div class="sgd-pvp-stat-lbl">Honor Kudos Received</div>
                </div>
                <div class="sgd-pvp-stat-box">
                    <div class="sgd-pvp-stat-num" style="color:#2ecc71;"><?php echo $streak_info['best']; ?> Days</div>
                    <div class="sgd-pvp-stat-lbl">Best Streak Record</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 6: REPUTATION -->
    <!-- ======================================================== -->
    <div class="sgd-armory-tab-pane" id="pane-reputation">
        <div class="sgd-pane-content-card">
            <h2 style="font-family:var(--font-title); color:var(--wow-gold-light); font-size:22px; margin-bottom:12px;">
                📜 GUILD FACTIONS & REPUTATION STANDINGS
            </h2>
            <p style="color:#94a3b8; font-size:13px; margin-bottom:20px;">
                Tingkat reputasi dan kepercayaan hero di setiap divisi internal Sinar Graha Mandiri.
            </p>

            <div class="sgd-rep-bars-list">
                
                <div class="sgd-rep-row">
                    <div class="sgd-rep-info">
                        <span class="sgd-rep-faction-name">Sinar Graha Mandiri (High Command)</span>
                        <span class="sgd-rep-standing exalted">Exalted (999 / 1,000)</span>
                    </div>
                    <div class="sgd-rep-track">
                        <div class="sgd-rep-fill exalted-fill" style="width: 100%;"></div>
                    </div>
                </div>

                <div class="sgd-rep-row">
                    <div class="sgd-rep-info">
                        <span class="sgd-rep-faction-name">Divisi IT & Digital Architecture</span>
                        <span class="sgd-rep-standing revered">Revered (18,450 / 21,000)</span>
                    </div>
                    <div class="sgd-rep-track">
                        <div class="sgd-rep-fill revered-fill" style="width: 88%;"></div>
                    </div>
                </div>

                <div class="sgd-rep-row">
                    <div class="sgd-rep-info">
                        <span class="sgd-rep-faction-name">Project Management & Kanban Vanguard</span>
                        <span class="sgd-rep-standing revered">Revered (14,200 / 21,000)</span>
                    </div>
                    <div class="sgd-rep-track">
                        <div class="sgd-rep-fill revered-fill" style="width: 67%;"></div>
                    </div>
                </div>

                <div class="sgd-rep-row">
                    <div class="sgd-rep-info">
                        <span class="sgd-rep-faction-name">Finance & Logistics Treasury</span>
                        <span class="sgd-rep-standing honored">Honored (8,900 / 12,000)</span>
                    </div>
                    <div class="sgd-rep-track">
                        <div class="sgd-rep-fill honored-fill" style="width: 74%;"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- WOW-ACCURATE FLOATING GEAR TOOLTIP -->
    <div id="sgd-armory-tooltip" class="sgd-wow-tooltip" style="display:none;">
        <div class="sgd-tooltip-header">
            <h4 id="sgd-tt-name" class="sgd-tt-title">Item Name</h4>
            <div id="sgd-tt-ilvl" class="sgd-tt-ilvl">Item Level 893</div>
            <div class="sgd-tt-sub">Upgrade Level 6/6 • Mythic</div>
        </div>
        <div class="sgd-tooltip-body">
            <div class="sgd-tt-binds">Binds when picked up</div>
            <div class="sgd-tt-type-row">
                <span id="sgd-tt-slot">Head</span>
                <span id="sgd-tt-armor">Plate</span>
            </div>
            <div id="sgd-tt-stats" class="sgd-tt-stats">
                +1,420 Strength<br>
                +2,580 Stamina<br>
                +610 Critical Strike
            </div>
            <div id="sgd-tt-flavor" class="sgd-tt-flavor">
                "Infused with the eternal dawn of the Silver Hand."
            </div>
        </div>
    </div>

</div>

<!-- JAVASCRIPT FOR SUBNAV SWITCHING, TOOLTIPS, AND 3D TILT EFFECT -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    
    // 1. Subnav Tab Switching
    var tabs = document.querySelectorAll('.sgd-armory-nav-tab');
    var panes = document.querySelectorAll('.sgd-armory-tab-pane');

    tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
            var target = this.getAttribute('data-tab');
            tabs.forEach(function(t){ t.classList.remove('active'); });
            panes.forEach(function(p){ p.classList.remove('active'); });

            this.classList.add('active');
            var activePane = document.getElementById('pane-' + target);
            if (activePane) {
                activePane.classList.add('active');
            }
        });
    });

    // 2. Interactive WoW Gear Tooltips
    var tooltip = document.getElementById('sgd-armory-tooltip');
    var ttName = document.getElementById('sgd-tt-name');
    var ttIlvl = document.getElementById('sgd-tt-ilvl');
    var ttSlot = document.getElementById('sgd-tt-slot');
    var ttArmor = document.getElementById('sgd-tt-armor');
    var ttStats = document.getElementById('sgd-tt-stats');
    var ttFlavor = document.getElementById('sgd-tt-flavor');

    var gearSlots = document.querySelectorAll('.sgd-gear-slot-item');

    gearSlots.forEach(function(slot){
        slot.addEventListener('mouseenter', function(e){
            var name = this.getAttribute('data-name');
            var ilvl = this.getAttribute('data-ilvl');
            var type = this.getAttribute('data-type');
            var quality = this.getAttribute('data-quality') || 'epic';
            var stats = this.getAttribute('data-stats') || '';
            var flavor = this.getAttribute('data-flavor') || '';
            var slotName = this.getAttribute('data-slot');

            ttName.textContent = name;
            ttName.className = 'sgd-tt-title sgd-text-' + quality;
            ttIlvl.textContent = 'Item Level ' + ilvl;
            ttSlot.textContent = slotName ? slotName.toUpperCase() : 'EQUIPMENT';
            ttArmor.textContent = type;
            
            // Format stats with linebreaks
            var statLines = stats.split(',').map(function(s){ return s.trim(); }).join('<br>');
            ttStats.innerHTML = statLines;
            ttFlavor.textContent = flavor ? ('"' + flavor + '"') : '';

            tooltip.style.display = 'block';
            positionTooltip(e);
        });

        slot.addEventListener('mousemove', function(e){
            positionTooltip(e);
        });

        slot.addEventListener('mouseleave', function(){
            tooltip.style.display = 'none';
        });

        // Click slot shows nice notification or stays active on mobile
        slot.addEventListener('click', function(e){
            e.stopPropagation();
            positionTooltip(e);
            tooltip.style.display = 'block';
        });
    });

    document.addEventListener('click', function(e){
        if (!e.target.closest('.sgd-gear-slot-item') && !e.target.closest('#sgd-armory-tooltip')) {
            tooltip.style.display = 'none';
        }
    });

    function positionTooltip(e) {
        var x = e.clientX + 15;
        var y = e.clientY + 15;
        var winW = window.innerWidth;
        var winH = window.innerHeight;

        var ttW = tooltip.offsetWidth || 280;
        var ttH = tooltip.offsetHeight || 180;

        if (x + ttW > winW - 20) {
            x = e.clientX - ttW - 15;
        }
        if (y + ttH > winH - 20) {
            y = winH - ttH - 20;
        }
        tooltip.style.left = Math.max(10, x) + 'px';
        tooltip.style.top = Math.max(10, y) + 'px';
    }

    // 3. 3D Tilt Effect on Character Model
    var stage = document.getElementById('sgd-model-stage');
    var figure = document.getElementById('sgd-char-figure');

    if (stage && figure) {
        stage.addEventListener('mousemove', function(e){
            var rect = stage.getBoundingClientRect();
            var x = e.clientX - rect.left - (rect.width / 2);
            var y = e.clientY - rect.top - (rect.height / 2);
            
            var rotY = (x / (rect.width / 2)) * 12; // -12 to 12 deg
            var rotX = -(y / (rect.height / 2)) * 8; // -8 to 8 deg

            figure.style.transform = 'perspective(900px) rotateY(' + rotY + 'deg) rotateX(' + rotX + 'deg)';
        });

        stage.addEventListener('mouseleave', function(){
            figure.style.transform = 'perspective(900px) rotateY(0deg) rotateX(0deg)';
        });
    }

});

// Global Switch Function for Class Buttons
window.sgdSwitchClassModel = function(classKey) {
    var form = document.getElementById('sgd-class-switch-form');
    if (form) {
        var sel = form.querySelector('select[name="rpg_class"]');
        if (sel) {
            sel.value = classKey;
            form.submit();
        }
    }
};

// Toggle Holy Flame / Combat FX
window.sgdToggleCombatAura = function() {
    var btn = document.getElementById('sgd-fx-glow-toggle');
    var figure = document.getElementById('sgd-char-figure');
    if (figure) {
        figure.classList.toggle('sgd-fx-disabled');
        if (btn) btn.classList.toggle('active');
    }
};

window.sgdResetModelStance = function() {
    var figure = document.getElementById('sgd-char-figure');
    if (figure) {
        figure.style.transform = 'perspective(900px) rotateY(0deg) rotateX(0deg)';
    }
};
</script>
