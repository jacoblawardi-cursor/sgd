/**
 * SGD 16-Bit & WoW Gamification JavaScript Engine
 * Audio synthesizer, modals, photo proof preview, and UI interactivity
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Audio Synthesizer (8-Bit Retro & Fanfare Sounds using Web Audio API)
    const AudioEngine = {
        ctx: null,
        muted: localStorage.getItem('sgd_sfx_muted') === 'true',
        volume: localStorage.getItem('sgd_sfx_volume') !== null ? parseFloat(localStorage.getItem('sgd_sfx_volume')) : 0.7,

        init: function() {
            if (!this.ctx && (window.AudioContext || window.webkitAudioContext)) {
                this.ctx = new (window.AudioContext || window.webkitAudioContext)();
            }
        },

        playTone: function(freq, type, duration, delay = 0) {
            if (this.muted || this.volume <= 0) return;
            this.init();
            if (!this.ctx) return;

            setTimeout(() => {
                try {
                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();
                    osc.type = type; // 'square', 'sine', 'triangle', 'sawtooth'
                    osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
                    const baseGain = 0.15 * this.volume;
                    gain.gain.setValueAtTime(baseGain, this.ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.0001, this.ctx.currentTime + duration);

                    osc.connect(gain);
                    gain.connect(this.ctx.destination);
                    osc.start();
                    osc.stop(this.ctx.currentTime + duration);
                } catch(e) {}
            }, delay * 1000);
        },

        playClick: function() {
            if (document.body && document.body.classList.contains('sgd-theme-genshin')) {
                // Genshin Impact crystalline UI chime
                this.playTone(880, 'sine', 0.04);
                this.playTone(1320, 'triangle', 0.04, 0.01);
            } else if (document.body && document.body.classList.contains('sgd-theme-hearthstone')) {
                // Hearthstone wooden card click / snap
                this.playHearthstoneCardSnap();
            } else {
                this.playTone(600, 'square', 0.05);
            }
        },

        playQuestAccept: function() {
            if (document.body && document.body.classList.contains('sgd-theme-genshin')) {
                this.playGenshinChime();
                return;
            } else if (document.body && document.body.classList.contains('sgd-theme-hearthstone')) {
                this.playHearthstoneTavernFanfare();
                return;
            }
            this.playTone(440, 'triangle', 0.1, 0);
            this.playTone(554.37, 'triangle', 0.1, 0.08);
            this.playTone(659.25, 'triangle', 0.2, 0.16);
        },

        playVictoryFanfare: function() {
            if (document.body && document.body.classList.contains('sgd-theme-genshin')) {
                this.playGenshinWish();
                return;
            } else if (document.body && document.body.classList.contains('sgd-theme-hearthstone')) {
                this.playHearthstoneLegendaryFanfare();
                return;
            }
            // Retro 8-bit level up / quest complete fanfare
            const notes = [
                { f: 523.25, d: 0.12, t: 0 },
                { f: 659.25, d: 0.12, t: 0.12 },
                { f: 783.99, d: 0.12, t: 0.24 },
                { f: 1046.50, d: 0.35, t: 0.36 }
            ];
            notes.forEach(n => this.playTone(n.f, 'square', n.d, n.t));
        },

        playGenshinChime: function() {
            // Ethereal Teyvat harp & crystal chime
            const notes = [
                { f: 587.33, type: 'sine', d: 0.2, t: 0 },
                { f: 739.99, type: 'sine', d: 0.25, t: 0.07 },
                { f: 880.00, type: 'sine', d: 0.3, t: 0.14 },
                { f: 1174.66, type: 'sine', d: 0.45, t: 0.21 },
                { f: 1479.98, type: 'triangle', d: 0.55, t: 0.28 }
            ];
            notes.forEach(n => this.playTone(n.f, n.type, n.d, n.t));
        },

        playGenshinWish: function() {
            // Genshin 5-star golden wish celestial fanfare
            const notes = [
                { f: 523.25, type: 'sine', d: 0.18, t: 0 },
                { f: 659.25, type: 'sine', d: 0.18, t: 0.08 },
                { f: 783.99, type: 'sine', d: 0.22, t: 0.16 },
                { f: 1046.50, type: 'sine', d: 0.32, t: 0.24 },
                { f: 1318.51, type: 'triangle', d: 0.45, t: 0.32 },
                { f: 1567.98, type: 'sine', d: 0.75, t: 0.40 }
            ];
            notes.forEach(n => this.playTone(n.f, n.type, n.d, n.t));
        },

        playHearthstoneCardSnap: function() {
            // Wooden card snap and mana crystal chime
            this.playTone(330, 'triangle', 0.04, 0);
            this.playTone(660, 'sine', 0.05, 0.01);
        },

        playHearthstoneTavernFanfare: function() {
            // Cozy tavern lute / accordion arpeggio
            const notes = [
                { f: 293.66, type: 'triangle', d: 0.12, t: 0 },
                { f: 369.99, type: 'triangle', d: 0.12, t: 0.08 },
                { f: 440.00, type: 'sine', d: 0.15, t: 0.16 },
                { f: 587.33, type: 'triangle', d: 0.25, t: 0.24 }
            ];
            notes.forEach(n => this.playTone(n.f, n.type, n.d, n.t));
        },

        playHearthstoneLegendaryFanfare: function() {
            // Grand Golden Legendary card trumpets & tavern fanfare
            const notes = [
                { f: 392.00, type: 'triangle', d: 0.14, t: 0 },
                { f: 523.25, type: 'triangle', d: 0.14, t: 0.09 },
                { f: 659.25, type: 'triangle', d: 0.16, t: 0.18 },
                { f: 783.99, type: 'square', d: 0.26, t: 0.27 },
                { f: 1046.50, type: 'sine', d: 0.55, t: 0.38 }
            ];
            notes.forEach(n => this.playTone(n.f, n.type, n.d, n.t));
        },

        playThemeSwitch: function(theme) {
            if (theme === 'genshin') {
                this.playGenshinChime();
            } else if (theme === 'hearthstone') {
                this.playHearthstoneTavernFanfare();
            } else {
                // WoW heroic guild fanfare
                const notes = [
                    { f: 440, type: 'square', d: 0.08, t: 0 },
                    { f: 554.37, type: 'triangle', d: 0.1, t: 0.07 },
                    { f: 659.25, type: 'triangle', d: 0.12, t: 0.14 },
                    { f: 880, type: 'square', d: 0.3, t: 0.21 }
                ];
                notes.forEach(n => this.playTone(n.f, n.type, n.d, n.t));
            }
        },

        playStreakFanfare: function() {
            // Bright fiery retro ascending chimes for streak combo
            const notes = [
                { f: 440.00, type: 'triangle', d: 0.10, t: 0 },
                { f: 554.37, type: 'triangle', d: 0.10, t: 0.08 },
                { f: 659.25, type: 'square', d: 0.12, t: 0.16 },
                { f: 880.00, type: 'square', d: 0.15, t: 0.24 },
                { f: 1108.73, type: 'sine', d: 0.38, t: 0.34 }
            ];
            notes.forEach(n => this.playTone(n.f, n.type, n.d, n.t));
        },

        playSosAlert: function() {
            this.playTone(880, 'sawtooth', 0.15, 0);
            this.playTone(440, 'sawtooth', 0.2, 0.15);
            this.playTone(880, 'sawtooth', 0.15, 0.35);
        },

        playAchievementFanfare: function() {
            if (document.body && document.body.classList.contains('sgd-theme-genshin')) {
                this.playGenshinWish();
                return;
            } else if (document.body && document.body.classList.contains('sgd-theme-hearthstone')) {
                this.playHearthstoneLegendaryFanfare();
                return;
            }
            // World of Warcraft grand golden achievement fanfare
            const notes = [
                { f: 523.25, type: 'triangle', d: 0.15, t: 0 },       // C5
                { f: 659.25, type: 'triangle', d: 0.15, t: 0.12 },    // E5
                { f: 783.99, type: 'triangle', d: 0.18, t: 0.24 },    // G5
                { f: 1046.50, type: 'square', d: 0.38, t: 0.38 },     // C6 (Triumph Peak)
                { f: 1318.51, type: 'triangle', d: 0.65, t: 0.48 },   // E6
                { f: 1567.98, type: 'sine', d: 0.85, t: 0.54 }        // G6 Shimmer
            ];
            notes.forEach(n => this.playTone(n.f, n.type || 'square', n.d, n.t));
        }
    };

    // Sound FX UI & Persistent Controller
    const sfxBtn = document.getElementById('sgd-sfx-toggle');
    const sfxIcon = document.getElementById('sgd-sfx-icon');
    const sfxText = document.getElementById('sgd-sfx-text');
    const volSlider = document.getElementById('sgd-volume-slider');
    const volDisplay = document.getElementById('sgd-vol-display');
    const soundPopover = document.getElementById('sgd-sound-popover');
    const soundWrap = document.querySelector('.sgd-sound-ctrl-wrap');

    function updateSoundUI() {
        const pct = Math.round(AudioEngine.volume * 100);
        if (volSlider) volSlider.value = pct;
        if (volDisplay) volDisplay.textContent = pct + '%';

        if (AudioEngine.muted || AudioEngine.volume <= 0) {
            if (sfxIcon) sfxIcon.textContent = '🔇';
            if (sfxText) sfxText.textContent = 'SFX: Muted';
        } else if (AudioEngine.volume < 0.35) {
            if (sfxIcon) sfxIcon.textContent = '🔈';
            if (sfxText) sfxText.textContent = `SFX: ${pct}%`;
        } else if (AudioEngine.volume < 0.7) {
            if (sfxIcon) sfxIcon.textContent = '🔉';
            if (sfxText) sfxText.textContent = `SFX: ${pct}%`;
        } else {
            if (sfxIcon) sfxIcon.textContent = '🔊';
            if (sfxText) sfxText.textContent = `SFX: ${pct}%`;
        }
    }

    updateSoundUI();

    if (sfxBtn) {
        sfxBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (soundPopover) {
                const isOpen = soundPopover.classList.contains('sgd-popover-open');
                soundPopover.classList.toggle('sgd-popover-open', !isOpen);
            }
        });
    }

    if (volSlider) {
        volSlider.addEventListener('input', function() {
            const val = parseFloat(this.value) / 100;
            AudioEngine.volume = val;
            AudioEngine.muted = (val <= 0);
            localStorage.setItem('sgd_sfx_volume', val.toString());
            localStorage.setItem('sgd_sfx_muted', AudioEngine.muted ? 'true' : 'false');
            updateSoundUI();
        });

        volSlider.addEventListener('change', function() {
            AudioEngine.playTone(587.33, 'sine', 0.08); // Test tone on release
        });
    }

    // Volume Preset Buttons
    document.querySelectorAll('.sgd-sound-preset-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const targetVol = parseFloat(this.getAttribute('data-vol')) / 100;
            AudioEngine.volume = targetVol;
            AudioEngine.muted = (targetVol <= 0);
            localStorage.setItem('sgd_sfx_volume', targetVol.toString());
            localStorage.setItem('sgd_sfx_muted', AudioEngine.muted ? 'true' : 'false');
            updateSoundUI();
            if (targetVol > 0) AudioEngine.playTone(659.25, 'triangle', 0.1);
        });
    });

    // Close popover when clicking outside
    document.addEventListener('click', function(e) {
        if (soundWrap && !soundWrap.contains(e.target)) {
            if (soundPopover) soundPopover.classList.remove('sgd-popover-open');
        }
    });

    // ==========================================
    // THEME SWITCHER CONTROLLER (WoW, Genshin & Hearthstone)
    // ==========================================
    const ThemeManager = {
        currentTheme: 'wow',

        init: function() {
            const cookieMatch = document.cookie.match(/(^|; )sgd_theme=([^;]+)/);
            const cookieTheme = cookieMatch ? decodeURIComponent(cookieMatch[2]) : null;
            let savedTheme = null;
            try {
                savedTheme = localStorage.getItem('sgd_active_theme');
            } catch(e) {}

            let domTheme = 'wow';
            if (document.body.classList.contains('sgd-theme-hearthstone')) domTheme = 'hearthstone';
            else if (document.body.classList.contains('sgd-theme-genshin')) domTheme = 'genshin';

            const initialTheme = savedTheme || cookieTheme || domTheme;
            this.setTheme(initialTheme, false);

            document.querySelectorAll('.sgd-theme-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetTheme = btn.getAttribute('data-theme');
                    if (targetTheme && targetTheme !== this.currentTheme) {
                        this.setTheme(targetTheme, true);
                    }
                });
            });
        },

        setTheme: function(theme, userTriggered = false) {
            if (!['wow', 'genshin', 'hearthstone'].includes(theme)) {
                theme = 'wow';
            }
            this.currentTheme = theme;

            // Apply classes & attributes
            document.documentElement.setAttribute('data-sgd-theme', theme);
            if (document.body) {
                document.body.classList.remove('sgd-theme-wow', 'sgd-theme-genshin', 'sgd-theme-hearthstone');
                document.body.classList.add('sgd-theme-' + theme);
            }

            // Update button states
            document.querySelectorAll('.sgd-theme-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-theme') === theme);
            });

            // Update document title dynamically
            const titleThemeMap = {
                'wow': '16-Bit Gamification Portal & Goblin Treasury',
                'genshin': 'Teyvat Adventurers\' Guild & Primogem Treasury',
                'hearthstone': 'Hearthstone Tavern & Mana Treasury'
            };
            for (const key in titleThemeMap) {
                if (document.title.includes(titleThemeMap[key])) {
                    document.title = document.title.replace(titleThemeMap[key], titleThemeMap[theme]);
                    break;
                }
            }

            // Persist preference in localStorage and Cookie
            try {
                localStorage.setItem('sgd_active_theme', theme);
            } catch(e) {}
            document.cookie = `sgd_theme=${encodeURIComponent(theme)}; path=/; max-age=31536000; SameSite=Lax`;

            // Sound feedback
            if (userTriggered && typeof AudioEngine !== 'undefined') {
                AudioEngine.playThemeSwitch(theme);
            }
        }
    };

    ThemeManager.init();

    // Attach click sound to buttons
    document.querySelectorAll('.sgd-btn, .sgd-tab-btn, .sgd-portal-card').forEach(btn => {
        btn.addEventListener('click', () => AudioEngine.playClick());
    });

    // Auto-play victory sound if celebration banner is present
    const celebrationBanner = document.querySelector('.sgd-flash-celebration');
    if (celebrationBanner) {
        if (celebrationBanner.textContent.includes('STREAK')) {
            AudioEngine.playStreakFanfare();
        } else {
            AudioEngine.playVictoryFanfare();
        }
    }

    // Auto-play Gilded Achievement Fanfare if Gilded Overlay is active
    const gildedCelebration = document.querySelector('.sgd-gilded-celebration-backdrop');
    if (gildedCelebration) {
        setTimeout(() => AudioEngine.playAchievementFanfare(), 300);

        // Fallback play if browser requires click
        document.addEventListener('click', function playOnce() {
            AudioEngine.playAchievementFanfare();
            document.removeEventListener('click', playOnce);
        }, { once: true });
    }

    // Gilded Claim button smooth transition
    document.querySelectorAll('.sgd-gilded-dismiss-form').forEach(form => {
        form.addEventListener('submit', function() {
            AudioEngine.playAchievementFanfare();
            const backdrop = this.closest('.sgd-gilded-celebration-backdrop');
            if (backdrop) {
                backdrop.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
                backdrop.style.opacity = '0';
                backdrop.style.transform = 'scale(1.05)';
            }
        });
    });

    // Auto-play SOS alert audio if Admin is viewing page and an active SOS exists
    if (document.querySelector('.sgd-sos-active-alert')) {
        setTimeout(() => AudioEngine.playSosAlert(), 500);
    }

    // 2. Photo Proof Upload Modal for "Finish Job"
    const finishModal = document.getElementById('sgd-finish-modal');
    const modalTaskId = document.getElementById('sgd-modal-task-id');
    const modalTaskTitle = document.getElementById('sgd-modal-task-title');
    const modalReward = document.getElementById('sgd-modal-reward-display');
    const proofInput = document.getElementById('sgd-proof-photo-input');
    const previewImg = document.getElementById('sgd-proof-preview-img');
    const dropzone = document.getElementById('sgd-upload-dropzone');

    document.querySelectorAll('.sgd-btn-finish-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.getAttribute('data-task-id');
            const title = this.getAttribute('data-task-title');
            const reward = this.getAttribute('data-task-reward');

            if (modalTaskId) modalTaskId.value = taskId;
            if (modalTaskTitle) modalTaskTitle.textContent = title;
            if (modalReward) modalReward.textContent = reward + ' 💎';
            if (previewImg) {
                previewImg.style.display = 'none';
                previewImg.src = '';
            }
            if (proofInput) proofInput.value = '';

            if (finishModal) {
                finishModal.classList.add('open');
            }
        });
    });

    // Close Modal
    document.querySelectorAll('.sgd-modal-close, .sgd-modal-cancel').forEach(btn => {
        btn.addEventListener('click', function() {
            if (finishModal) finishModal.classList.remove('open');
        });
    });

    // Close on clicking backdrop
    if (finishModal) {
        finishModal.addEventListener('click', function(e) {
            if (e.target === finishModal) {
                finishModal.classList.remove('open');
            }
        });
    }

    // File input preview
    if (dropzone && proofInput) {
        dropzone.addEventListener('click', () => proofInput.click());

        proofInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                        previewImg.style.display = 'block';
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // 3. Need Help Button Confirmation & Prompt
    document.querySelectorAll('.sgd-btn-help-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const confirmed = confirm('🚨 Send SOS Help Beacon?\n\nThis will instantly alert the Guild Master and Dispatch Board that you need reinforcements on this task!');
            if (!confirmed) {
                e.preventDefault();
            } else {
                AudioEngine.playSosAlert();
            }
        });
    });

    // 4. Take Job Button Confirmation
    document.querySelectorAll('.sgd-btn-take-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const confirmed = confirm('⚔️ Claim Emergency Bounty?\n\nYou are committing to complete this urgent mission and submit proof to claim the +1,000 Diamonds bounty!');
            if (!confirmed) {
                e.preventDefault();
            } else {
                AudioEngine.playQuestAccept();
            }
        });
    });

    // 5. Cash Shop Redeem Confirmation
    document.querySelectorAll('.sgd-btn-redeem-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const itemName = this.getAttribute('data-item-name');
            const cost = this.getAttribute('data-item-cost');
            const confirmed = confirm(`💎 Confirm Redemption:\n\nSpend ${cost} Diamonds for [${itemName}]?`);
            if (!confirmed) {
                e.preventDefault();
            }
        });
    });

    // 6. Admin Panel Subtabs
    document.querySelectorAll('.sgd-admin-subtab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.sgd-admin-subtab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const targetSection = this.getAttribute('data-target');
            document.querySelectorAll('.sgd-admin-section').forEach(sec => {
                sec.style.display = 'none';
            });
            const activeSec = document.getElementById(targetSection);
            if (activeSec) {
                activeSec.style.display = 'block';
            }
        });
    });

    // Auto-activate tab from query param e.g. ?tab=proofs
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab')) {
        const targetTab = document.querySelector(`.sgd-admin-subtab[data-target="admin-sec-${urlParams.get('tab')}"]`);
        if (targetTab) targetTab.click();
    }

    // 7. Hall of Legends: Quick Jump to Send Mana Form
    document.querySelectorAll('.sgd-send-mana-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target-id');
            const select = document.getElementById('sgd-kudos-recipient');
            if (select) select.value = targetId;
            const panel = document.getElementById('sgd-kudos-panel');
            if (panel) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                panel.classList.add('sgd-panel-pulse');
                setTimeout(() => panel.classList.remove('sgd-panel-pulse'), 1200);
            }
            AudioEngine.playClick();
        });
    });

    // Kudos Amount Radio Selection
    document.querySelectorAll('.sgd-kudos-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.sgd-kudos-pill').forEach(p => p.classList.remove('active'));
            const pill = this.closest('label').querySelector('.sgd-kudos-pill');
            if (pill) pill.classList.add('active');
            AudioEngine.playClick();
        });
    });

    // 8. Goblin Daily Fortune Wheel Canvas Engine
    const wheelCanvas = document.getElementById('sgd-wheel-canvas');
    if (wheelCanvas && wheelCanvas.getContext) {
        const ctx = wheelCanvas.getContext('2d');
        const sectors = [
            { label: '+50 💎', sub: 'POUCH', bg: '#192a56', text: '#ffffff' },
            { label: '+100 XP', sub: 'ARCANE', bg: '#6c5ce7', text: '#ffffff' },
            { label: '+100 💎', sub: 'SACK', bg: '#0984e3', text: '#ffffff' },
            { label: '🛡️ SHIELD', sub: 'STREAK', bg: '#00b894', text: '#ffffff' },
            { label: '+250 💎', sub: 'TREASURE', bg: '#d63031', text: '#ffffff' },
            { label: '☕ COFFEE', sub: 'COUPON', bg: '#e17055', text: '#ffffff' },
            { label: '🌟 500 💎', sub: 'JACKPOT', bg: '#d4af37', text: '#111111' },
            { label: '👑 TITLE', sub: 'FORTUNE', bg: '#8e44ad', text: '#ffffff' }
        ];

        const numSectors = sectors.length;
        const arc = (2 * Math.PI) / numSectors;
        const centerX = wheelCanvas.width / 2;
        const centerY = wheelCanvas.height / 2;
        const radius = centerX - 12;

        let currentAngle = 0;
        let isSpinning = false;
        let lastTickSector = -1;

        function drawWheel(angle) {
            ctx.clearRect(0, 0, wheelCanvas.width, wheelCanvas.height);

            // Outer Golden Bezel
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.beginPath();
            ctx.arc(0, 0, radius + 8, 0, 2 * Math.PI);
            ctx.fillStyle = '#6e4e13';
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#ffd700';
            ctx.stroke();

            // Outer Bezel Rivets
            for (let i = 0; i < 24; i++) {
                const rAngle = (i / 24) * 2 * Math.PI;
                const rx = (radius + 4) * Math.cos(rAngle);
                const ry = (radius + 4) * Math.sin(rAngle);
                ctx.beginPath();
                ctx.arc(rx, ry, 2.5, 0, 2 * Math.PI);
                ctx.fillStyle = i % 2 === 0 ? '#ffd700' : '#ffffff';
                ctx.fill();
            }

            // Sectors
            ctx.rotate(angle);
            for (let i = 0; i < numSectors; i++) {
                const sectorAngle = i * arc;
                ctx.beginPath();
                ctx.moveTo(0, 0);
                ctx.arc(0, 0, radius, sectorAngle, sectorAngle + arc);
                ctx.closePath();
                ctx.fillStyle = sectors[i].bg;
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#ffd700';
                ctx.stroke();

                // Draw Text & Icons
                ctx.save();
                ctx.rotate(sectorAngle + arc / 2);
                ctx.textAlign = 'right';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = sectors[i].text;

                // Main Prize Label
                ctx.font = 'bold 13px sans-serif';
                ctx.shadowColor = 'rgba(0,0,0,0.8)';
                ctx.shadowBlur = 4;
                ctx.fillText(sectors[i].label, radius - 20, -5);

                // Subtitle
                ctx.font = 'bold 8px monospace';
                ctx.fillStyle = sectors[i].text === '#111111' ? '#333' : '#ffd700';
                ctx.fillText(sectors[i].sub, radius - 22, 10);

                ctx.restore();
            }

            // Inner Core Golden Ring
            ctx.beginPath();
            ctx.arc(0, 0, 36, 0, 2 * Math.PI);
            ctx.fillStyle = '#141724';
            ctx.fill();
            ctx.lineWidth = 3;
            ctx.strokeStyle = '#ffd700';
            ctx.stroke();

            ctx.restore();
        }

        // Initial render
        drawWheel(0);

        // Spin Handler
        const spinBtn = document.getElementById('sgd-spin-action-btn');
        const spinForm = document.getElementById('sgd-spin-form');
        const sectorInput = document.getElementById('sgd-sector-index-input');

        if (spinBtn && spinForm) {
            spinBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (isSpinning) return;
                isSpinning = true;
                spinBtn.disabled = true;
                spinBtn.style.opacity = '0.6';
                spinBtn.style.cursor = 'not-allowed';

                // Determine target winning sector (0 to 7)
                // Weights: +50: 25%, +100 XP: 20%, +100: 20%, Shield: 12%, +250: 10%, Coffee: 8%, 500: 3%, Title: 2%
                const rand = Math.random() * 100;
                let targetIndex = 0;
                if (rand < 25) targetIndex = 0;
                else if (rand < 45) targetIndex = 1;
                else if (rand < 65) targetIndex = 2;
                else if (rand < 77) targetIndex = 3;
                else if (rand < 87) targetIndex = 4;
                else if (rand < 95) targetIndex = 5;
                else if (rand < 98) targetIndex = 6; // 🌟 JACKPOT
                else targetIndex = 7; // 👑 Title

                // Pointer is at the top (12 o'clock, 270 deg / 1.5 * PI)
                // When wheel rotates by totalAngle, the sector at the pointer is:
                // ((1.5 * PI - totalAngle) % 2PI) / arc
                // Therefore, to align targetIndex center at pointer:
                // targetAngle = 1.5 * PI - (targetIndex + 0.5) * arc + fullRotations * 2PI
                const minRevolutions = 5 + Math.floor(Math.random() * 3);
                // Slight random jitter inside sector bounds (+/- 35% of arc)
                const jitter = (Math.random() - 0.5) * (arc * 0.7);
                const targetCenterAngle = (targetIndex + 0.5) * arc + jitter;
                const totalTargetRotation = (minRevolutions * 2 * Math.PI) + (1.5 * Math.PI - targetCenterAngle);

                const startTime = performance.now();
                const duration = 4200; // 4.2 seconds dramatic spin
                const startAngle = currentAngle % (2 * Math.PI);
                const deltaAngle = totalTargetRotation;

                function easeOutCubic(t) {
                    return (--t) * t * t + 1;
                }

                function animateSpin(now) {
                    const elapsed = now - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easedProgress = easeOutCubic(progress);

                    currentAngle = startAngle + (deltaAngle * easedProgress);
                    drawWheel(currentAngle);

                    // Calculate current sector passing under pointer to trigger tick sound
                    const norm = ((1.5 * Math.PI - (currentAngle % (2 * Math.PI))) % (2 * Math.PI) + 2 * Math.PI) % (2 * Math.PI);
                    const curSector = Math.floor(norm / arc);
                    if (curSector !== lastTickSector) {
                        lastTickSector = curSector;
                        // Higher pitch near end, lower pitch during rapid spin
                        AudioEngine.playTone(800 + (curSector * 25), 'square', 0.025);
                    }

                    if (progress < 1) {
                        requestAnimationFrame(animateSpin);
                    } else {
                        // Spin complete!
                        isSpinning = false;
                        if (targetIndex === 6) {
                            AudioEngine.playVictoryFanfare();
                        } else {
                            AudioEngine.playStreakFanfare();
                        }

                        if (sectorInput) {
                            sectorInput.value = targetIndex;
                        }

                        setTimeout(() => {
                            spinForm.submit();
                        }, 900);
                    }
                }

                requestAnimationFrame(animateSpin);
            });
        }
    }

    // 9. Peer Bounty Board Modal & Proof Submission
    const bountyModal = document.getElementById('sgd-bounty-modal');
    const bountyModalId = document.getElementById('sgd-modal-bounty-id');
    const bountyModalTitle = document.getElementById('sgd-modal-bounty-title');
    const bountyModalReward = document.getElementById('sgd-modal-bounty-reward');
    const bountyCloseBtn = document.getElementById('sgd-bounty-modal-close');
    const bountyCancelBtn = document.getElementById('sgd-bounty-modal-cancel');

    document.querySelectorAll('.sgd-bounty-submit-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const bountyId = this.getAttribute('data-bounty-id');
            const title = this.getAttribute('data-bounty-title');
            const reward = this.getAttribute('data-bounty-reward');

            if (bountyModalId) bountyModalId.value = bountyId;
            if (bountyModalTitle) bountyModalTitle.textContent = title;
            if (bountyModalReward) bountyModalReward.textContent = '+' + reward + ' 💎';

            if (bountyModal) {
                bountyModal.classList.add('open');
                AudioEngine.playClick();
            }
        });
    });

    if (bountyCloseBtn && bountyModal) {
        bountyCloseBtn.addEventListener('click', function() {
            bountyModal.classList.remove('open');
        });
    }

    if (bountyCancelBtn && bountyModal) {
        bountyCancelBtn.addEventListener('click', function() {
            bountyModal.classList.remove('open');
        });
    }

    if (bountyModal) {
        bountyModal.addEventListener('click', function(e) {
            if (e.target === bountyModal) {
                bountyModal.classList.remove('open');
            }
        });
    }

    // 10. Hero Title Armory Modal Wire-up
    const titleModal = document.getElementById('sgd-title-modal');
    const titleCloseBtn = document.getElementById('sgd-title-modal-close');
    const titleCancelBtn = document.getElementById('sgd-title-modal-cancel');

    document.querySelectorAll('.sgd-title-modal-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (titleModal) {
                titleModal.classList.add('open');
                AudioEngine.playClick();
            }
        });
    });

    if (titleCloseBtn && titleModal) {
        titleCloseBtn.addEventListener('click', function() {
            titleModal.classList.remove('open');
        });
    }

    if (titleCancelBtn && titleModal) {
        titleCancelBtn.addEventListener('click', function() {
            titleModal.classList.remove('open');
        });
    }

    if (titleModal) {
        titleModal.addEventListener('click', function(e) {
            if (e.target === titleModal) {
                titleModal.classList.remove('open');
            }
        });
    }

    // 11. LAPORAN HARIAN (DAILY WORK REPORT) FEATURES
    // A. Template Chips Inserter
    const rawInputArea = document.getElementById('sgd-raw-input');
    const clearTextBtn = document.getElementById('sgd-clear-text-btn');

    document.querySelectorAll('.sgd-chip-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!rawInputArea) return;
            const textToInsert = this.getAttribute('data-text');
            if (!textToInsert) return;

            AudioEngine.playClick();
            let currentVal = rawInputArea.value.trim();
            if (currentVal.length > 0) {
                if (!/[.!?]$/.test(currentVal)) {
                    currentVal += '.';
                }
                rawInputArea.value = currentVal + ' ' + textToInsert;
            } else {
                rawInputArea.value = textToInsert;
            }
            rawInputArea.focus();
        });
    });

    if (clearTextBtn && rawInputArea) {
        clearTextBtn.addEventListener('click', function() {
            if (confirm('Bersihkan isi teks laporan?')) {
                rawInputArea.value = '';
                rawInputArea.focus();
            }
        });
    }

    // B. Web Speech API Voice Recognition (Bahasa Indonesia id-ID)
    const voiceBtn = document.getElementById('sgd-voice-btn');
    const voiceLabel = document.getElementById('sgd-voice-label');
    const voiceLiveStatus = document.getElementById('sgd-voice-live-status');
    const voiceStopBtn = document.getElementById('sgd-voice-stop-btn');
    let speechRecognitionInstance = null;
    let isRecording = false;

    if (voiceBtn) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (SpeechRecognition) {
            speechRecognitionInstance = new SpeechRecognition();
            speechRecognitionInstance.continuous = true;
            speechRecognitionInstance.interimResults = true;
            speechRecognitionInstance.lang = 'id-ID';

            let finalTranscript = '';

            speechRecognitionInstance.onstart = function() {
                isRecording = true;
                voiceBtn.classList.add('recording');
                if (voiceLabel) voiceLabel.textContent = 'Mendengarkan... (Klik Berhenti)';
                if (voiceLiveStatus) voiceLiveStatus.style.display = 'flex';
                AudioEngine.playQuestAccept();
            };

            speechRecognitionInstance.onresult = function(event) {
                let interimTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript + ' ';
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }

                if (rawInputArea) {
                    const existingPrefix = rawInputArea.getAttribute('data-base-text') || '';
                    rawInputArea.value = (existingPrefix + ' ' + finalTranscript + interimTranscript).trim();
                }
            };

            speechRecognitionInstance.onerror = function(event) {
                console.warn('Speech recognition error:', event.error);
                if (event.error === 'not-allowed') {
                    alert('Izin akses mikrofon ditolak. Silakan izinkan akses mikrofon di browser Anda.');
                }
                stopVoiceRecording();
            };

            speechRecognitionInstance.onend = function() {
                stopVoiceRecording();
            };

            function startVoiceRecording() {
                if (rawInputArea) {
                    rawInputArea.setAttribute('data-base-text', rawInputArea.value.trim());
                }
                finalTranscript = '';
                try {
                    speechRecognitionInstance.start();
                } catch(e) {
                    console.error('Speech recognition start failed:', e);
                }
            }

            function stopVoiceRecording() {
                if (!isRecording) return;
                isRecording = false;
                voiceBtn.classList.remove('recording');
                if (voiceLabel) voiceLabel.textContent = 'Rekam Suara (Voice Input)';
                if (voiceLiveStatus) voiceLiveStatus.style.display = 'none';
                try {
                    speechRecognitionInstance.stop();
                } catch(e) {}
                AudioEngine.playTone(520, 'square', 0.08);
            }

            voiceBtn.addEventListener('click', function() {
                if (!isRecording) {
                    startVoiceRecording();
                } else {
                    stopVoiceRecording();
                }
            });

            if (voiceStopBtn) {
                voiceStopBtn.addEventListener('click', function() {
                    stopVoiceRecording();
                });
            }
        } else {
            voiceBtn.addEventListener('click', function() {
                alert('Fitur Voice to Text (Web Speech Recognition) membutuhkan browser Google Chrome, Microsoft Edge, atau browser mobile modern. Silakan gunakan Google Chrome untuk fitur rekaman suara langsung, atau ketik laporan Anda di kotak teks.');
            });
        }
    }

    // C. 3 Progress Photos Upload Instant Preview
    for (let slot = 1; slot <= 3; slot++) {
        const fileInput = document.getElementById('file-photo-' + slot);
        const previewImg = document.getElementById('preview-img-' + slot);
        const placeholder = document.getElementById('placeholder-' + slot);
        const removeBtn = document.querySelector('.sgd-remove-photo-btn[data-slot="' + slot + '"]');

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (previewImg) {
                            previewImg.src = e.target.result;
                            previewImg.style.display = 'block';
                        }
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                        if (removeBtn) {
                            removeBtn.style.display = 'inline-flex';
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                    AudioEngine.playClick();
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (fileInput) fileInput.value = '';
                if (previewImg) {
                    previewImg.src = '';
                    previewImg.style.display = 'none';
                }
                if (placeholder) {
                    placeholder.style.display = 'flex';
                }
                this.style.display = 'none';
            });
        }
    }

    // D. View Mode Tabs (Standard Text / Interactive Editor / Photos Gallery)
    const modeTabs = document.querySelectorAll('.sgd-mode-tab-btn');
    modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            modeTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            document.querySelectorAll('.sgd-mode-section').forEach(sec => {
                sec.style.display = 'none';
            });
            const activeSec = document.getElementById(targetId);
            if (activeSec) {
                activeSec.style.display = 'block';
            }
            AudioEngine.playClick();
        });
    });

    // E. Copy Formatted Text to Clipboard & Toggle Raw ASCII
    const copyBtns = [
        document.getElementById('sgd-copy-formatted-text-btn'),
        document.getElementById('sgd-copy-term-btn'),
        document.getElementById('sgd-copy-raw-box-btn')
    ];
    copyBtns.forEach(btn => {
        if (!btn) return;
        btn.addEventListener('click', function() {
            const text = this.getAttribute('data-text');
            if (!text) return;

            navigator.clipboard.writeText(text).then(() => {
                const orig = this.innerHTML;
                this.innerHTML = '✅ Berhasil Disalin!';
                if (typeof AudioEngine !== 'undefined') {
                    AudioEngine.playStreakFanfare();
                }
                setTimeout(() => {
                    this.innerHTML = orig;
                }, 2000);
            }).catch(err => {
                console.error('Clipboard copy failed:', err);
            });
        });
    });

    const toggleRawBtn = document.getElementById('sgd-toggle-raw-ascii-btn');
    const rawBox = document.getElementById('sgd-raw-ascii-box');
    if (toggleRawBtn && rawBox) {
        toggleRawBtn.addEventListener('click', function() {
            if (rawBox.style.display === 'none' || rawBox.style.display === '') {
                rawBox.style.display = 'block';
                this.innerHTML = '💻 Sembunyikan Raw Monospace';
            } else {
                rawBox.style.display = 'none';
                this.innerHTML = '💻 Toggle Monospace Raw';
            }
            if (typeof AudioEngine !== 'undefined') {
                AudioEngine.playClick();
            }
        });
    }

    // E2. Weather Toggle Pills in Admin Edit Form
    const weatherPillBtns = document.querySelectorAll('.sgd-weather-edit-pill');
    const weatherStates = [
        { value: 'cerah', label: 'cerah', icon: '☀️', className: 'is-sunny' },
        { value: 'hujan', label: 'hujan', icon: '🌧️', className: 'is-rain' },
        { value: 'berawan', label: 'berawan', icon: '⛅', className: 'is-cloudy' }
    ];

    function applyWeatherToPill(btn, stateVal) {
        const targetId = btn.getAttribute('data-target-input');
        const hiddenInput = document.getElementById(targetId);
        if (!hiddenInput) return;

        const stateObj = weatherStates.find(s => s.value === stateVal) || weatherStates[0];
        hiddenInput.value = stateObj.value;

        btn.classList.remove('is-sunny', 'is-rain', 'is-cloudy');
        btn.classList.add(stateObj.className);

        const iconSpan = btn.querySelector('.sgd-pill-icon');
        if (iconSpan) iconSpan.textContent = stateObj.icon;

        const labelSpan = btn.querySelector('.sgd-pill-label');
        if (labelSpan) labelSpan.textContent = stateObj.label;
    }

    function setAllWeather(stateVal) {
        weatherPillBtns.forEach(btn => {
            applyWeatherToPill(btn, stateVal);
        });
        if (typeof AudioEngine !== 'undefined') {
            AudioEngine.playStreakFanfare();
        }
    }

    weatherPillBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target-input');
            const hiddenInput = document.getElementById(targetId);
            if (!hiddenInput) return;

            let currentVal = (hiddenInput.value || 'cerah').toLowerCase().trim();
            let currentIdx = weatherStates.findIndex(s => s.value === currentVal);
            if (currentIdx === -1) {
                currentIdx = 0;
            }
            let nextIdx = (currentIdx + 1) % weatherStates.length;
            let nextState = weatherStates[nextIdx];

            applyWeatherToPill(this, nextState.value);

            if (typeof AudioEngine !== 'undefined') {
                AudioEngine.playClick();
            }
            this.classList.add('sgd-pill-popping');
            setTimeout(() => {
                this.classList.remove('sgd-pill-popping');
            }, 180);
        });
    });

    const presetSunnyBtn = document.getElementById('sgd-preset-sunny-btn');
    const presetRainBtn = document.getElementById('sgd-preset-rain-btn');
    const presetNoonRainBtn = document.getElementById('sgd-preset-noon-rain-btn');

    if (presetSunnyBtn) {
        presetSunnyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            setAllWeather('cerah');
        });
    }
    if (presetRainBtn) {
        presetRainBtn.addEventListener('click', function(e) {
            e.preventDefault();
            setAllWeather('hujan');
        });
    }
    if (presetNoonRainBtn) {
        presetNoonRainBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const noonHours = ['10.00', '11.00', '12.00', '13.00', '14.00', '15.00', '16.00'];
            weatherPillBtns.forEach(btn => {
                const hour = btn.getAttribute('data-hour');
                const targetState = noonHours.includes(hour) ? 'hujan' : 'cerah';
                applyWeatherToPill(btn, targetState);
            });
            if (typeof AudioEngine !== 'undefined') {
                AudioEngine.playStreakFanfare();
            }
        });
    }

    // F. Dynamic Rows for Manpower
    const addManpowerBtn = document.getElementById('sgd-add-manpower-btn');
    const manpowerTbody = document.getElementById('sgd-manpower-tbody');
    if (addManpowerBtn && manpowerTbody) {
        addManpowerBtn.addEventListener('click', function() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="manpower_role[]" value="" class="sgd-form-input" placeholder="e.g. Tukang Kayu, Kenek"></td>
                <td><input type="number" name="manpower_count[]" value="1" class="sgd-form-input" min="1"></td>
                <td style="text-align:center;"><button type="button" class="sgd-row-delete-btn" onclick="this.closest('tr').remove();">✕</button></td>
            `;
            manpowerTbody.appendChild(tr);
            AudioEngine.playClick();
        });
    }

    // G. Dynamic Rows for Materials
    const addMaterialBtn = document.getElementById('sgd-add-material-btn');
    const materialsTbody = document.getElementById('sgd-materials-tbody');
    if (addMaterialBtn && materialsTbody) {
        addMaterialBtn.addEventListener('click', function() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="material_name[]" value="" class="sgd-form-input" placeholder="e.g. Semen, Batu Split"></td>
                <td><input type="text" name="material_quantity[]" value="1" class="sgd-form-input" placeholder="Jumlah"></td>
                <td><input type="text" name="material_unit[]" value="sak" class="sgd-form-input" placeholder="kg, sak, m3, btg"></td>
                <td style="text-align:center;"><button type="button" class="sgd-row-delete-btn" onclick="this.closest('tr').remove();">✕</button></td>
            `;
            materialsTbody.appendChild(tr);
            AudioEngine.playClick();
        });
    }

    // H. Dynamic Input for Equipment
    const addEquipmentBtn = document.getElementById('sgd-add-equipment-btn');
    const equipContainer = document.getElementById('sgd-equipment-list-container');
    if (addEquipmentBtn && equipContainer) {
        addEquipmentBtn.addEventListener('click', function() {
            const div = document.createElement('div');
            div.className = 'sgd-equipment-pill-input';
            div.innerHTML = `
                <span>🔧</span>
                <input type="text" name="equipment_name[]" value="" class="sgd-form-input" placeholder="e.g. Mesin Molen, Gerinda">
                <button type="button" class="sgd-row-delete-btn" onclick="this.parentElement.remove();">✕</button>
            `;
            equipContainer.appendChild(div);
            AudioEngine.playClick();
        });
    }

    // I. AI Settings Modal
    const aiModal = document.getElementById('sgd-ai-settings-modal');
    const openAiBtns = document.querySelectorAll('#sgd-open-ai-settings-btn, #sgd-trigger-ai-settings-btn, .sgd-open-ai-settings-btn');
    const closeAiBtn = document.getElementById('sgd-ai-settings-close');
    const cancelAiBtn = document.getElementById('sgd-ai-settings-cancel');

    if (openAiBtns && aiModal) {
        openAiBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                aiModal.classList.add('open');
                if (typeof AudioEngine !== 'undefined') {
                    AudioEngine.playClick();
                }
            });
        });
    }
    if (closeAiBtn && aiModal) {
        closeAiBtn.addEventListener('click', function() {
            aiModal.classList.remove('open');
        });
    }
    if (cancelAiBtn && aiModal) {
        cancelAiBtn.addEventListener('click', function() {
            aiModal.classList.remove('open');
        });
    }
    if (aiModal) {
        aiModal.addEventListener('click', function(e) {
            if (e.target === aiModal) {
                aiModal.classList.remove('open');
            }
        });
    }

    // J. Daily Report Submit Feedback
    const dailyForm = document.getElementById('sgd-daily-report-form');
    const submitBtn = document.getElementById('sgd-submit-report-btn');
    if (dailyForm && submitBtn) {
        dailyForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            submitBtn.innerHTML = '⏳ Sedang Memproses & Mengurai Laporan...';
            try { AudioEngine.playQuestAccept(); } catch(e) {}
        });
    }

    // ========================================================
    // K. CICLE KANBAN QUEST SYSTEM (HQ, TIM & PROYEK)
    // ========================================================
    (function initCicleKanban() {
        const board = document.getElementById('sgd-kanban-board');
        const modalCreateTeam = document.getElementById('sgd-modal-create-team');
        const modalEditTeam = document.getElementById('sgd-modal-edit-team');
        const modalTaskDetail = document.getElementById('sgd-modal-task-detail');

        // Helper to safely play sounds
        function sfxClick() {
            try { if (typeof AudioEngine !== 'undefined') AudioEngine.playClick(); } catch(e) {}
        }
        function sfxSuccess() {
            try { if (typeof AudioEngine !== 'undefined') AudioEngine.playSuccess(); } catch(e) {}
        }

        // Generic modal closer
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-close-modal');
                const m = document.getElementById(targetId);
                if (m) m.classList.remove('open');
                sfxClick();
            });
        });

        // Close on backdrop click
        [modalCreateTeam, modalEditTeam, modalTaskDetail].forEach(m => {
            if (m) {
                m.addEventListener('click', function(e) {
                    if (e.target === m) {
                        m.classList.remove('open');
                        sfxClick();
                    }
                });
            }
        });

        // 1. Create Team Modal
        const btnOpenCreate = document.getElementById('sgd-btn-open-create-team');
        const btnOpenCreateEmpty = document.getElementById('sgd-btn-open-create-team-empty');
        [btnOpenCreate, btnOpenCreateEmpty].forEach(btn => {
            if (btn && modalCreateTeam) {
                btn.addEventListener('click', function() {
                    modalCreateTeam.classList.add('open');
                    sfxClick();
                    const nameInput = document.getElementById('sgd-input-timname');
                    if (nameInput) setTimeout(() => nameInput.focus(), 100);
                });
            }
        });

        // Radio cards visual selection in Create Team
        document.querySelectorAll('.sgd-type-radio-selector input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const parentSelector = this.closest('.sgd-type-radio-selector');
                if (parentSelector) {
                    parentSelector.querySelectorAll('.sgd-type-radio-card').forEach(c => c.classList.remove('active'));
                    const card = this.closest('.sgd-type-radio-card');
                    if (card) card.classList.add('active');
                }
            });
        });

        // 2. Edit Team Modal
        function openEditTeam(data) {
            if (!modalEditTeam) return;
            document.getElementById('edit-team-id').value = data.id || '';
            document.getElementById('edit-team-name').value = data.name || '';
            document.getElementById('edit-team-desc').value = data.desc || '';
            document.getElementById('edit-team-manager').value = data.mgr || '';

            // Type radio
            const typeRadios = modalEditTeam.querySelectorAll('input[name="team_type"]');
            typeRadios.forEach(r => {
                r.checked = (r.value === data.type);
                const card = r.closest('.sgd-type-radio-card');
                if (card) {
                    if (r.checked) card.classList.add('active');
                    else card.classList.remove('active');
                }
            });

            // Member checkboxes
            const memberIds = (data.members || '').split(',').map(s => s.trim());
            modalEditTeam.querySelectorAll('.edit-member-check').forEach(chk => {
                chk.checked = memberIds.includes(chk.getAttribute('data-user-id'));
            });

            modalEditTeam.classList.add('open');
            sfxClick();
        }

        document.querySelectorAll('.sgd-team-edit-btn, #sgd-btn-edit-current-team').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                openEditTeam({
                    id: this.getAttribute('data-team-id'),
                    name: this.getAttribute('data-team-name'),
                    type: this.getAttribute('data-team-type'),
                    desc: this.getAttribute('data-team-desc'),
                    mgr: this.getAttribute('data-team-mgr'),
                    members: this.getAttribute('data-team-members'),
                });
            });
        });

        // 3. Inline Task Creator in Columns
        document.querySelectorAll('.sgd-btn-add-tugas-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const colKey = this.getAttribute('data-column');
                const formWrap = this.parentElement.querySelector('.sgd-inline-task-form');
                if (formWrap) {
                    const isVisible = formWrap.style.display !== 'none';
                    if (isVisible) {
                        formWrap.style.display = 'none';
                    } else {
                        // Close other inline forms first
                        document.querySelectorAll('.sgd-inline-task-form').forEach(f => f.style.display = 'none');
                        formWrap.style.display = 'block';
                        const input = formWrap.querySelector('.sgd-input-task-name');
                        if (input) input.focus();
                    }
                    sfxClick();
                }
            });
        });

        document.querySelectorAll('.sgd-btn-cancel-inline').forEach(btn => {
            btn.addEventListener('click', function() {
                const formWrap = this.closest('.sgd-inline-task-form');
                if (formWrap) formWrap.style.display = 'none';
                sfxClick();
            });
        });

        // 4. WYSIWYG Editor Controls
        const wysiwygEditor = document.getElementById('modal-task-editor');
        const wysiwygHidden = document.getElementById('modal-task-desc-hidden');

        if (wysiwygEditor && wysiwygHidden) {
            // Sync content from contenteditable to hidden textarea
            function syncWysiwyg() {
                wysiwygHidden.value = wysiwygEditor.innerHTML;
            }
            wysiwygEditor.addEventListener('input', syncWysiwyg);
            wysiwygEditor.addEventListener('blur', syncWysiwyg);

            // Toolbar actions
            document.querySelectorAll('#sgd-wysiwyg-toolbar .sgd-tool-btn[data-cmd]').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const cmd = this.getAttribute('data-cmd');
                    const val = this.getAttribute('data-val') || null;
                    document.execCommand(cmd, false, val);
                    wysiwygEditor.focus();
                    syncWysiwyg();
                    sfxClick();
                });
            });

            // Insert Link
            const linkBtn = document.getElementById('sgd-tool-insert-link');
            if (linkBtn) {
                linkBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = prompt('Masukkan URL tautan:', 'https://');
                    if (url && url !== 'https://') {
                        document.execCommand('createLink', false, url);
                        syncWysiwyg();
                    }
                });
            }

            // Sync on form submit
            const taskForm = document.getElementById('sgd-form-update-task');
            if (taskForm) {
                taskForm.addEventListener('submit', function() {
                    syncWysiwyg();
                    sfxSuccess();
                });
            }
        }

        // 5. Open Task Detail Modal when Task Card is clicked
        function openTaskDetail(taskId) {
            if (!modalTaskDetail || !taskId) return;

            // Fetch task detail via lightweight AJAX / fetch
            const formData = new FormData();
            formData.append('sgd_action', 'sgd_get_task_json');
            formData.append('task_id', taskId);

            // Optimistic UI fallback: read from card DOM
            const card = document.querySelector(`.sgd-kanban-card[data-task-id="${taskId}"]`);
            if (card) {
                const title = card.querySelector('.sgd-card-title') ? card.querySelector('.sgd-card-title').textContent.trim() : '';
                const col = card.getAttribute('data-column') || 'To do list';
                populateTaskModal({
                    id: taskId,
                    title: title,
                    column_name: col,
                    description: '',
                    deadline: '',
                    assigned_members: '',
                    comments: [],
                    attachments: [],
                });
            }

            // Query server for full rich details (WYSIWYG HTML, attachments, comments)
            const queryUrl = window.SGD_QUESTS_CONFIG && window.SGD_QUESTS_CONFIG.ajaxUrl 
                ? window.SGD_QUESTS_CONFIG.ajaxUrl + '?sgd_page=quests&get_task_detail=' + taskId 
                : window.location.href;

            fetch(queryUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.data && data.data.task) {
                    populateTaskModal(data.data.task);
                }
            })
            .catch(() => {});

            modalTaskDetail.classList.add('open');
            sfxClick();
        }

        function populateTaskModal(task) {
            document.getElementById('modal-task-id').value = task.id;
            document.getElementById('modal-task-id-display').textContent = '#' + task.id;
            document.getElementById('modal-task-title').value = task.title || '';
            document.getElementById('modal-task-column').value = task.column_name || 'To do list';
            
            const colBadge = document.getElementById('modal-task-column-badge');
            if (colBadge) {
                colBadge.textContent = task.column_name || 'To do list';
            }

            // WYSIWYG Editor
            if (wysiwygEditor && wysiwygHidden) {
                wysiwygEditor.innerHTML = task.description || '';
                wysiwygHidden.value = task.description || '';
            }

            // Deadline
            const dlInput = document.getElementById('modal-task-deadline');
            if (dlInput) {
                if (task.deadline && task.deadline !== '0000-00-00 00:00:00') {
                    // Convert 'YYYY-MM-DD HH:MM:SS' to 'YYYY-MM-DDTHH:MM'
                    dlInput.value = task.deadline.replace(' ', 'T').substring(0, 16);
                } else {
                    dlInput.value = '';
                }
            }

            // Assigned Members
            const assignedIds = (task.assigned_members || '').toString().split(',').map(s => s.trim());
            document.querySelectorAll('.modal-assigned-member-check').forEach(chk => {
                chk.checked = assignedIds.includes(chk.getAttribute('data-user-id'));
            });

            // Quest Level
            const qlInput = document.getElementById('modal-task-quest-level');
            if (qlInput) {
                qlInput.value = task.quest_level || 1;
            }

            // Reward Type & Amounts
            const rtSelect = document.getElementById('modal-task-reward-type');
            if (rtSelect) {
                rtSelect.value = task.reward_type || 'both';
            }
            const rxpInput = document.getElementById('modal-task-reward-xp');
            if (rxpInput) {
                rxpInput.value = (task.reward_xp !== undefined && task.reward_xp !== null) ? task.reward_xp : 200;
            }
            const rgoldInput = document.getElementById('modal-task-reward-gold');
            if (rgoldInput) {
                rgoldInput.value = (task.reward_gold !== undefined && task.reward_gold !== null) ? task.reward_gold : 50;
            }

            updateRewardFieldsVisibility(task.reward_type || 'both');
            updateQuestLevelPreview(task.quest_level || 1);

            // Cover Image Widget in Modal
            const coverBox = document.getElementById('modal-cover-preview-box');
            const coverImg = document.getElementById('modal-cover-preview-img');
            const btnRemoveCover = document.getElementById('modal-btn-remove-cover');
            if (task.cover_image && task.cover_image !== 'none') {
                if (coverImg) coverImg.src = task.cover_image;
                if (coverBox) coverBox.style.display = 'block';
                if (btnRemoveCover) btnRemoveCover.style.display = 'inline-block';
            } else {
                if (coverBox) coverBox.style.display = 'none';
                if (btnRemoveCover) btnRemoveCover.style.display = 'none';
            }

            // Labels / Tags
            renderLabels(task.tags || []);

            // Checklist / Subtasks
            renderModalChecklist(task.checklist || [], task.id);

            // Attachments
            renderAttachments(task.attachments || []);

            // Comments
            renderComments(task.comments || []);
        }

        function updateRewardFieldsVisibility(type) {
            const xpGroup = document.getElementById('modal-reward-xp-group');
            const goldGroup = document.getElementById('modal-reward-gold-group');
            if (!xpGroup || !goldGroup) return;

            if (type === 'both') {
                xpGroup.style.display = 'block';
                goldGroup.style.display = 'block';
            } else if (type === 'xp') {
                xpGroup.style.display = 'block';
                goldGroup.style.display = 'none';
            } else if (type === 'gold') {
                xpGroup.style.display = 'none';
                goldGroup.style.display = 'block';
            } else {
                xpGroup.style.display = 'none';
                goldGroup.style.display = 'none';
            }
        }

        function updateQuestLevelPreview(questLevel) {
            questLevel = Math.max(1, parseInt(questLevel, 10) || 1);
            const badge = document.getElementById('modal-quest-lvl-badge');
            if (badge) {
                badge.textContent = 'Lv. ' + questLevel;
            }

            const myLevel = (window.SGD_QUESTS_CONFIG && window.SGD_QUESTS_CONFIG.currentUserLevel) ? parseInt(window.SGD_QUESTS_CONFIG.currentUserLevel, 10) : 1;
            const diff = myLevel - questLevel;
            
            let percent = 100;
            let status = 'Tepat Level';
            let color = '#2ecc71';
            let desc = '';

            if (diff <= 2) {
                percent = 100;
                if (diff < 0) {
                    status = 'Tantangan Tinggi';
                    color = '#ff4444';
                    desc = `Level Anda (Lv.${myLevel}) di bawah quest ini -> Anda mendapatkan 100% hadiah penuh!`;
                } else {
                    status = 'Tepat Level';
                    color = '#2ecc71';
                    desc = `Level Anda (Lv.${myLevel}) seimbang dengan quest -> Anda mendapatkan 100% hadiah penuh!`;
                }
            } else if (diff <= 5) {
                percent = 70;
                status = 'Tugas Mudah (70% Porsi)';
                color = '#ff9f43';
                desc = `Level Anda (Lv.${myLevel}) lebih tinggi (+${diff} level) -> Hanya mendapat 70% porsi hadiah dasar.`;
            } else if (diff <= 9) {
                percent = 40;
                status = 'Tantangan Rendah (40% Porsi)';
                color = '#a4b0be';
                desc = `Level Anda (Lv.${myLevel}) jauh lebih tinggi (+${diff} level) -> Hanya mendapat 40% porsi hadiah dasar.`;
            } else {
                percent = 15;
                status = 'Trivial / Pemula (15% Porsi)';
                color = '#747d8c';
                desc = `Level Anda (Lv.${myLevel}) veteran (+${diff} level dari Lv.${questLevel}) -> Hanya mendapat 15% porsi hadiah minimum.`;
            }

            const tag = document.getElementById('modal-preview-portion-tag');
            if (tag) {
                tag.textContent = percent + '% Porsi (' + status + ')';
                tag.style.background = color;
                tag.style.color = (color === '#2ecc71' || color === '#ff9f43') ? '#000' : '#fff';
            }

            const text = document.getElementById('modal-scaling-user-text');
            if (text) {
                text.textContent = desc;
            }
        }

        // ========================================================
        // Label & Tag Manager (Admin Need Button & Color Options)
        // ========================================================
        let currentTaskLabels = [];
        let selectedLabelColor = '#ff4444';

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function renderLabels(labels) {
            currentTaskLabels = Array.isArray(labels) ? labels : [];
            const container = document.getElementById('modal-labels-container');
            const hiddenInput = document.getElementById('modal-task-labels-hidden');
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(currentTaskLabels);
            }
            if (!container) return;

            if (currentTaskLabels.length === 0) {
                container.innerHTML = '<span class="sgd-no-labels-text" style="font-size:11px; color:#718093;">Belum ada label.</span>';
                return;
            }

            let html = '';
            currentTaskLabels.forEach((lbl, idx) => {
                const color = lbl.color || '#00e5ff';
                html += `
                    <span class="sgd-modal-tag-chip" style="--chip-color:${color}; background:${color}22; border-color:${color}; color:${color};">
                        <span class="sgd-tag-dot" style="background:${color};"></span>
                        ${escapeHtml(lbl.name)}
                        <button type="button" class="sgd-remove-tag-btn" data-tag-index="${idx}" title="Hapus label">&times;</button>
                    </span>
                `;
            });
            container.innerHTML = html;
        }

        // Toggle add label panel
        const btnToggleAddLabel = document.getElementById('modal-btn-toggle-add-label');
        const btnHeaderAddLabel = document.getElementById('modal-btn-add-label-trigger');
        const addLabelPanel = document.getElementById('modal-add-label-panel');
        const btnCancelAddLabel = document.getElementById('modal-btn-cancel-add-label');
        const btnConfirmAddLabel = document.getElementById('modal-btn-confirm-add-label');
        const newTagNameInput = document.getElementById('modal-new-tag-name');

        function toggleAddLabelPanel(show) {
            if (!addLabelPanel) return;
            const willShow = (typeof show === 'boolean') ? show : (addLabelPanel.style.display === 'none');
            addLabelPanel.style.display = willShow ? 'block' : 'none';
            if (willShow && newTagNameInput) {
                setTimeout(() => newTagNameInput.focus(), 50);
            }
            sfxClick();
        }

        if (btnToggleAddLabel) btnToggleAddLabel.addEventListener('click', () => toggleAddLabelPanel());
        if (btnHeaderAddLabel) btnHeaderAddLabel.addEventListener('click', () => toggleAddLabelPanel(true));
        if (btnCancelAddLabel) btnCancelAddLabel.addEventListener('click', () => toggleAddLabelPanel(false));

        // Color swatches selection
        document.querySelectorAll('#modal-tag-color-swatches .sgd-color-swatch').forEach(sw => {
            sw.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('#modal-tag-color-swatches .sgd-color-swatch').forEach(s => s.classList.remove('active'));
                this.classList.add('active');
                selectedLabelColor = this.getAttribute('data-color') || '#ff4444';
                sfxClick();
            });
        });

        // Quick Tag Presets
        document.querySelectorAll('.sgd-tag-preset-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const name = this.getAttribute('data-name');
                const color = this.getAttribute('data-color') || '#00e5ff';
                if (!name) return;

                // Set input & swatches
                if (newTagNameInput) newTagNameInput.value = name;
                selectedLabelColor = color;
                document.querySelectorAll('#modal-tag-color-swatches .sgd-color-swatch').forEach(s => {
                    s.classList.toggle('active', s.getAttribute('data-color') === color);
                });

                // Directly attach tag if not already in list
                if (!currentTaskLabels.some(l => (l.name || '').toLowerCase() === name.toLowerCase())) {
                    currentTaskLabels.push({
                        name: name,
                        color: color
                    });
                    renderLabels(currentTaskLabels);
                    if (newTagNameInput) newTagNameInput.value = '';
                    toggleAddLabelPanel(false);
                    sfxSuccess();
                } else {
                    sfxClick();
                }
            });
        });

        // Confirm Add Label
        if (btnConfirmAddLabel && newTagNameInput) {
            btnConfirmAddLabel.addEventListener('click', function(e) {
                e.preventDefault();
                const name = newTagNameInput.value.trim();
                if (!name) {
                    newTagNameInput.focus();
                    return;
                }
                currentTaskLabels.push({
                    name: name,
                    color: selectedLabelColor || '#00e5ff'
                });
                renderLabels(currentTaskLabels);
                newTagNameInput.value = '';
                toggleAddLabelPanel(false);
                sfxSuccess();
            });
        }

        // Remove Label from container
        const labelsContainer = document.getElementById('modal-labels-container');
        if (labelsContainer) {
            labelsContainer.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.sgd-remove-tag-btn');
                if (removeBtn) {
                    e.preventDefault();
                    const idx = parseInt(removeBtn.getAttribute('data-tag-index'), 10);
                    if (!isNaN(idx) && idx >= 0 && idx < currentTaskLabels.length) {
                        currentTaskLabels.splice(idx, 1);
                        renderLabels(currentTaskLabels);
                        sfxClick();
                    }
                }
            });
        }

        // ========================================================
        // Task Checklist Manager (Card & Modal Interactive Subtasks)
        // ========================================================
        let currentModalChecklist = [];
        let currentModalTaskId = 0;

        function renderModalChecklist(items, taskId) {
            currentModalChecklist = Array.isArray(items) ? items : [];
            currentModalTaskId = taskId || (document.getElementById('modal-task-id') ? document.getElementById('modal-task-id').value : 0);
            const container = document.getElementById('modal-checklist-items');
            const hiddenInput = document.getElementById('modal-task-checklist-hidden');
            const counter = document.getElementById('modal-chk-counter');
            const progBar = document.getElementById('modal-chk-progress-bar');

            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(currentModalChecklist);
            }

            const total = currentModalChecklist.length;
            const done = currentModalChecklist.filter(it => it.completed).length;
            const pct = total > 0 ? Math.round((done / total) * 100) : 0;

            if (counter) counter.textContent = `${done}/${total} selesai (${pct}%)`;
            if (progBar) {
                progBar.style.width = pct + '%';
                progBar.classList.toggle('done', pct === 100 && total > 0);
            }

            if (!container) return;

            if (total === 0) {
                container.innerHTML = '<p class="sgd-comments-empty" style="padding:10px 0; color:#718093; font-size:12px; margin:0;">Belum ada item checklist verifikasi.</p>';
                return;
            }

            let html = '';
            currentModalChecklist.forEach(item => {
                const isC = !!item.completed;
                const itemId = escapeHtml(item.id || '');
                const text = escapeHtml(item.text || '');
                html += `
                    <div class="sgd-modal-chk-item ${isC ? 'completed' : ''}" id="modal-chk-row-${itemId}">
                        <input type="checkbox" class="sgd-card-chk-box" data-task-id="${currentModalTaskId}" data-item-id="${itemId}" ${isC ? 'checked' : ''} style="cursor:pointer; width:15px; height:15px;">
                        <span class="sgd-card-chk-text ${isC ? 'completed' : ''}">${text}</span>
                        <button type="button" class="sgd-card-chk-del-btn" data-task-id="${currentModalTaskId}" data-item-id="${itemId}" title="Hapus item">&times;</button>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        // Modal Add Checklist Item
        const btnModalAddChk = document.getElementById('modal-btn-add-chk');
        const inputModalNewChk = document.getElementById('modal-new-chk-input');
        if (btnModalAddChk && inputModalNewChk) {
            btnModalAddChk.addEventListener('click', function(e) {
                e.preventDefault();
                const text = inputModalNewChk.value.trim();
                const taskId = document.getElementById('modal-task-id').value;
                if (!text || !taskId) return;
                submitChecklistItem(taskId, text, () => {
                    inputModalNewChk.value = '';
                });
            });
            inputModalNewChk.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    btnModalAddChk.click();
                }
            });
        }

        function toggleChecklistItem(taskId, itemId, completed, checkboxEl) {
            const formData = new FormData();
            formData.append('sgd_action', 'sgd_toggle_quest_checklist_item');
            formData.append('task_id', taskId);
            formData.append('item_id', itemId);
            formData.append('completed', completed);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    sfxSuccess();
                    updateChecklistDOM(taskId, res.data);
                } else {
                    if (checkboxEl) checkboxEl.checked = !completed;
                    alert((res.data && res.data.message) ? res.data.message : 'Gagal memperbarui checklist.');
                }
            })
            .catch(() => {
                if (checkboxEl) checkboxEl.checked = !completed;
            });
        }

        function submitChecklistItem(taskId, text, callback) {
            const formData = new FormData();
            formData.append('sgd_action', 'sgd_add_quest_checklist_item');
            formData.append('task_id', taskId);
            formData.append('text', text);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    sfxSuccess();
                    if (typeof callback === 'function') callback();
                    // Append item into card items list
                    const cardList = document.getElementById(`chk-items-${taskId}`);
                    if (cardList && res.data.new_item) {
                        const it = res.data.new_item;
                        const row = document.createElement('label');
                        row.className = 'sgd-card-chk-item';
                        row.id = `chk-item-row-${it.id}`;
                        row.innerHTML = `
                            <input type="checkbox" class="sgd-card-chk-box" data-task-id="${taskId}" data-item-id="${it.id}">
                            <span class="sgd-card-chk-text">${escapeHtml(it.text)}</span>
                            <button type="button" class="sgd-card-chk-del-btn" data-task-id="${taskId}" data-item-id="${it.id}" title="Hapus item">&times;</button>
                        `;
                        cardList.appendChild(row);
                    }
                    updateChecklistDOM(taskId, res.data);
                    // Also refresh modal if open for this task
                    const modalTaskId = document.getElementById('modal-task-id');
                    if (modalTaskId && String(modalTaskId.value) === String(taskId)) {
                        renderModalChecklist(res.data.checklist, taskId);
                    }
                } else {
                    alert((res.data && res.data.message) ? res.data.message : 'Gagal menambahkan checklist.');
                }
            })
            .catch(() => {});
        }

        function deleteChecklistItem(taskId, itemId) {
            const formData = new FormData();
            formData.append('sgd_action', 'sgd_delete_quest_checklist_item');
            formData.append('task_id', taskId);
            formData.append('item_id', itemId);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    sfxClick();
                    const rowCard = document.getElementById(`chk-item-row-${itemId}`);
                    if (rowCard) rowCard.remove();
                    const rowModal = document.getElementById(`modal-chk-row-${itemId}`);
                    if (rowModal) rowModal.remove();
                    updateChecklistDOM(taskId, res.data);
                    const modalTaskId = document.getElementById('modal-task-id');
                    if (modalTaskId && String(modalTaskId.value) === String(taskId)) {
                        renderModalChecklist(res.data.checklist, taskId);
                    }
                }
            })
            .catch(() => {});
        }

        function updateChecklistDOM(taskId, data) {
            // Update counter on card
            const counter = document.getElementById(`chk-counter-${taskId}`);
            if (counter) counter.textContent = `${data.done_count}/${data.total_count}`;

            // Update progress wrap & bar on card
            const progWrap = document.getElementById(`chk-progress-wrap-${taskId}`);
            const progBar = document.getElementById(`chk-progress-bar-${taskId}`);
            if (progWrap) progWrap.style.display = data.total_count > 0 ? '' : 'none';
            if (progBar) {
                progBar.style.width = data.percent + '%';
                progBar.classList.toggle('done', !!data.all_completed);
            }

            // Sync item rows on card
            if (data.item_id) {
                const cardRow = document.getElementById(`chk-item-row-${data.item_id}`);
                if (cardRow) {
                    cardRow.classList.toggle('completed', !!data.completed);
                    const chkBox = cardRow.querySelector('.sgd-card-chk-box');
                    if (chkBox) chkBox.checked = !!data.completed;
                    const textSpan = cardRow.querySelector('.sgd-card-chk-text');
                    if (textSpan) textSpan.classList.toggle('completed', !!data.completed);
                }
                const modalRow = document.getElementById(`modal-chk-row-${data.item_id}`);
                if (modalRow) {
                    modalRow.classList.toggle('completed', !!data.completed);
                    const chkBox = modalRow.querySelector('.sgd-card-chk-box');
                    if (chkBox) chkBox.checked = !!data.completed;
                    const textSpan = modalRow.querySelector('.sgd-card-chk-text');
                    if (textSpan) textSpan.classList.toggle('completed', !!data.completed);
                }
            }

            // Also sync modal counter/bar if same task
            const modalTaskId = document.getElementById('modal-task-id');
            if (modalTaskId && String(modalTaskId.value) === String(taskId)) {
                const modalCounter = document.getElementById('modal-chk-counter');
                const modalProg = document.getElementById('modal-chk-progress-bar');
                if (modalCounter) modalCounter.textContent = `${data.done_count}/${data.total_count} selesai (${data.percent}%)`;
                if (modalProg) {
                    modalProg.style.width = data.percent + '%';
                    modalProg.classList.toggle('done', !!data.all_completed);
                }
            }
        }

        // Global Event delegation for card checklist interactions
        document.addEventListener('change', function(e) {
            if (e.target.matches('.sgd-card-chk-box')) {
                const chk = e.target;
                const taskId = chk.getAttribute('data-task-id');
                const itemId = chk.getAttribute('data-item-id');
                const completed = chk.checked ? 1 : 0;
                toggleChecklistItem(taskId, itemId, completed, chk);
            }
        });

        document.addEventListener('click', function(e) {
            // Toggle add input on card
            const toggleBtn = e.target.closest('.sgd-btn-card-add-chk-toggle');
            if (toggleBtn) {
                e.preventDefault();
                e.stopPropagation();
                const taskId = toggleBtn.getAttribute('data-task-id');
                const addRow = document.getElementById(`chk-add-row-${taskId}`);
                if (addRow) {
                    const isHidden = addRow.style.display === 'none';
                    addRow.style.display = isHidden ? 'flex' : 'none';
                    if (isHidden) {
                        const inp = document.getElementById(`chk-add-input-${taskId}`);
                        if (inp) setTimeout(() => inp.focus(), 50);
                    }
                }
                sfxClick();
                return;
            }

            // Submit add item on card
            const submitCardBtn = e.target.closest('.sgd-btn-card-add-chk-submit');
            if (submitCardBtn) {
                e.preventDefault();
                e.stopPropagation();
                const taskId = submitCardBtn.getAttribute('data-task-id');
                const inp = document.getElementById(`chk-add-input-${taskId}`);
                if (inp && inp.value.trim()) {
                    submitChecklistItem(taskId, inp.value.trim(), () => {
                        inp.value = '';
                    });
                }
                return;
            }

            // Delete item button on card or modal
            const delBtn = e.target.closest('.sgd-card-chk-del-btn');
            if (delBtn) {
                e.preventDefault();
                e.stopPropagation();
                const taskId = delBtn.getAttribute('data-task-id');
                const itemId = delBtn.getAttribute('data-item-id');
                if (confirm('Hapus item checklist ini?')) {
                    deleteChecklistItem(taskId, itemId);
                }
                return;
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.target.matches('.sgd-card-chk-add-input') && e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                const taskId = e.target.id.replace('chk-add-input-', '');
                if (e.target.value.trim()) {
                    submitChecklistItem(taskId, e.target.value.trim(), () => {
                        e.target.value = '';
                    });
                }
            }
        });

        function renderAttachments(attachments) {
            const list = document.getElementById('modal-attachments-list');
            const countElem = document.getElementById('modal-attachment-count');
            if (!list) return;

            if (countElem) countElem.textContent = attachments.length + ' file';

            if (!attachments || attachments.length === 0) {
                list.innerHTML = '<p class="sgd-attachments-empty" style="color:#718093; font-size:12px; margin:0;">Belum ada file terlampir.</p>';
                return;
            }

            let html = '';
            attachments.forEach(att => {
                const sizeKb = att.file_size ? Math.round(att.file_size / 1024) + ' KB' : '';
                const isImg = (att.file_type && att.file_type.startsWith('image/')) || /\.(png|jpe?g|webp|gif)$/i.test(att.file_url);
                const coverBtn = isImg ? `<button type="button" class="sgd-btn-make-cover" data-url="${att.file_url}" data-task-id="${att.task_id}" title="Jadikan gambar ini cover kartu">🖼️ Cover</button>` : '';

                html += `
                    <div class="sgd-attachment-item">
                        <div class="sgd-attachment-info">
                            <span style="font-size:18px;">${isImg ? '🖼️' : '📄'}</span>
                            <div>
                                <a href="${att.file_url}" target="_blank" class="sgd-attachment-name" title="${att.file_name}">${att.file_name}</a>
                                <div class="sgd-attachment-meta">Oleh: ${att.uploader_name || 'Staff'} • ${sizeKb}</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            ${coverBtn}
                            <form method="post" style="display:inline;" onsubmit="return confirm('Hapus file ini?');">
                                <input type="hidden" name="sgd_action" value="sgd_delete_quest_attachment">
                                <input type="hidden" name="attachment_id" value="${att.id}">
                                <input type="hidden" name="task_id" value="${att.task_id}">
                                <button type="submit" class="sgd-attachment-del" title="Hapus file">🗑️</button>
                            </form>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        function renderComments(comments) {
            const stream = document.getElementById('modal-comments-stream');
            const countElem = document.getElementById('modal-comment-count');
            if (!stream) return;

            if (countElem) countElem.textContent = comments.length + ' pesan';

            if (!comments || comments.length === 0) {
                stream.innerHTML = '<p class="sgd-comments-empty" style="color:#718093; font-size:12px; margin:0;">Belum ada obrolan. Jadilah yang pertama memberikan masukan!</p>';
                return;
            }

            let html = '';
            comments.forEach(c => {
                html += `
                    <div class="sgd-comment-card">
                        <div class="sgd-comment-avatar">${c.user_avatar || '👤'}</div>
                        <div class="sgd-comment-content">
                            <div class="sgd-comment-header">
                                <span class="sgd-comment-author">${c.user_name || 'Anggota'}</span>
                                <span class="sgd-comment-time">${c.created_at || ''}</span>
                            </div>
                            <div class="sgd-comment-text">${c.comment || ''}</div>
                        </div>
                    </div>
                `;
            });
            stream.innerHTML = html;
            stream.scrollTop = stream.scrollHeight;
        }

        // Attach card click handlers
        function initCardClicks() {
            document.querySelectorAll('.sgd-kanban-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicked on quick action or move buttons
                    if (e.target.closest('.sgd-card-quick-actions') || e.target.closest('.sgd-card-move-bar')) {
                        return;
                    }
                    const taskId = this.getAttribute('data-task-id');
                    openTaskDetail(taskId);
                });
            });
        }
        initCardClicks();

        // 6. Clone & Delete Task Action Handlers
        document.querySelectorAll('.sgd-btn-clone-task, #modal-btn-clone-task').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const taskId = this.getAttribute('data-task-id') || document.getElementById('modal-task-id').value;
                if (!taskId) return;
                const form = document.getElementById('sgd-hidden-clone-form');
                if (form) {
                    document.getElementById('sgd-hidden-clone-task-id').value = taskId;
                    sfxSuccess();
                    form.submit();
                }
            });
        });

        document.querySelectorAll('.sgd-btn-del-task, #modal-btn-delete-task').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const taskId = this.getAttribute('data-task-id') || document.getElementById('modal-task-id').value;
                const title = this.getAttribute('data-task-title') || document.getElementById('modal-task-title').value;
                if (!taskId) return;
                if (confirm(`Apakah Anda yakin ingin menghapus tugas '${title}'?`)) {
                    const form = document.getElementById('sgd-hidden-del-form');
                    if (form) {
                        document.getElementById('sgd-hidden-del-task-id').value = taskId;
                        sfxClick();
                        form.submit();
                    }
                }
            });
        });

        // 7. Quick Move Column Buttons
        document.querySelectorAll('.sgd-quick-move-pill').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const taskId = this.getAttribute('data-task-id');
                const targetCol = this.getAttribute('data-target-column');
                const form = document.getElementById('sgd-hidden-move-form');
                if (form && taskId && targetCol) {
                    document.getElementById('sgd-hidden-move-task-id').value = taskId;
                    document.getElementById('sgd-hidden-move-target-column').value = targetCol;
                    sfxClick();
                    form.submit();
                }
            });
        });

        // 8. Add Comment Button in Modal
        const btnSendComment = document.getElementById('modal-btn-send-comment');
        const commentInput = document.getElementById('modal-new-comment-input');
        if (btnSendComment && commentInput) {
            btnSendComment.addEventListener('click', function() {
                const text = commentInput.value.trim();
                const taskId = document.getElementById('modal-task-id').value;
                if (!text || !taskId) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const a1 = document.createElement('input');
                a1.name = 'sgd_action';
                a1.value = 'sgd_add_quest_comment';
                form.appendChild(a1);

                const a2 = document.createElement('input');
                a2.name = 'task_id';
                a2.value = taskId;
                form.appendChild(a2);

                const a3 = document.createElement('input');
                a3.name = 'comment_text';
                a3.value = text;
                form.appendChild(a3);

                document.body.appendChild(form);
                sfxSuccess();
                form.submit();
            });
        }

        // Helper: Upload Multiple Quest Files via AJAX
        function uploadQuestFiles(taskId, fileList, pillElem, onFinish) {
            if (!taskId || !fileList || fileList.length === 0) return;

            const formData = new FormData();
            formData.append('sgd_action', 'sgd_upload_quest_attachment');
            formData.append('is_ajax', '1');
            formData.append('task_id', taskId);

            for (let i = 0; i < fileList.length; i++) {
                formData.append('quest_files[]', fileList[i]);
            }

            let origPillHtml = '';
            if (pillElem) {
                pillElem.classList.add('uploading');
                origPillHtml = pillElem.innerHTML;
                pillElem.innerHTML = '⏳ Uploading ' + fileList.length + ' file...';
            }

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (pillElem) {
                    pillElem.classList.remove('uploading');
                    if (origPillHtml) pillElem.innerHTML = origPillHtml;
                }
                if (data.success) {
                    sfxSuccess();
                    // Update task card count
                    const countElem = document.getElementById('card-att-count-' + taskId);
                    if (countElem) {
                        countElem.textContent = data.data.total_attachment;
                    }

                    // Update modal if currently opened on this task
                    const currentModalTaskId = document.getElementById('modal-task-id')?.value;
                    if (currentModalTaskId == taskId && typeof renderAttachments === 'function') {
                        renderAttachments(data.data.attachments || []);
                    }

                    if (typeof onFinish === 'function') {
                        onFinish(true, data.data);
                    }
                } else {
                    alert(data.data?.message || 'Gagal mengunggah file.');
                    if (typeof onFinish === 'function') {
                        onFinish(false, data.data);
                    }
                }
            })
            .catch(err => {
                console.error('Upload quest files error:', err);
                if (pillElem) {
                    pillElem.classList.remove('uploading');
                    if (origPillHtml) pillElem.innerHTML = origPillHtml;
                }
                alert('Terjadi kesalahan koneksi saat mengunggah file.');
                if (typeof onFinish === 'function') {
                    onFinish(false, err);
                }
            });
        }

        // Event listener for multiple file upload directly from Kanban Task Cards
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('sgd-card-multiple-upload-input')) {
                const input = e.target;
                const taskId = input.getAttribute('data-task-id');
                if (!input.files || input.files.length === 0 || !taskId) return;

                const pill = document.getElementById('card-upload-pill-' + taskId);
                uploadQuestFiles(taskId, input.files, pill, function(success) {
                    input.value = '';
                });
            }
        });

        // 9. Upload File Button in Modal (Supports Multiple Files & AJAX)
        const btnUploadFile = document.getElementById('modal-btn-upload-file');
        const fileInput = document.getElementById('modal-file-input');
        if (btnUploadFile && fileInput) {
            btnUploadFile.addEventListener('click', function() {
                if (!fileInput.files || fileInput.files.length === 0) {
                    alert('Silakan pilih file terlebih dahulu.');
                    return;
                }
                const taskId = document.getElementById('modal-task-id')?.value;
                if (!taskId) return;

                btnUploadFile.disabled = true;
                const origBtnText = btnUploadFile.innerHTML;
                btnUploadFile.innerHTML = '⏳ Mengunggah ' + fileInput.files.length + ' file...';

                uploadQuestFiles(taskId, fileInput.files, null, function(success) {
                    btnUploadFile.disabled = false;
                    btnUploadFile.innerHTML = origBtnText;
                    if (success) {
                        fileInput.value = '';
                    }
                });
            });
        }

        // 10. HTML5 Drag and Drop for Kanban Cards
        let draggedCard = null;

        document.querySelectorAll('.sgd-kanban-card').forEach(card => {
            if (card.getAttribute('draggable') !== 'true') return;

            card.addEventListener('dragstart', function(e) {
                draggedCard = this;
                this.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', this.getAttribute('data-task-id'));
            });

            card.addEventListener('dragend', function() {
                this.classList.remove('is-dragging');
                draggedCard = null;
                document.querySelectorAll('.sgd-kanban-column').forEach(c => c.classList.remove('drag-over'));
            });
        });

        document.querySelectorAll('.sgd-kanban-column').forEach(col => {
            col.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });

            col.addEventListener('dragleave', function(e) {
                if (!this.contains(e.relatedTarget)) {
                    this.classList.remove('drag-over');
                }
            });

            col.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                const targetColumnName = this.getAttribute('data-column-name');
                const taskId = e.dataTransfer.getData('text/plain') || (draggedCard ? draggedCard.getAttribute('data-task-id') : null);

                if (draggedCard && taskId && targetColumnName) {
                    const originCol = draggedCard.getAttribute('data-column');
                    if (originCol !== targetColumnName) {
                        // MANAGER APPROVAL GATE: Non-manager cannot drag into "Selesai"
                        if (targetColumnName === 'Selesai' && !window.SGD_QUESTS_CONFIG?.canManageTeam) {
                            alert('🔒 Kolom "Selesai" membutuhkan verifikasi checklist dan persetujuan Manager Tim. Tugas tidak dapat langsung diselesaikan.');
                            return;
                        }

                        const form = document.getElementById('sgd-hidden-move-form');
                        if (form) {
                            document.getElementById('sgd-hidden-move-task-id').value = taskId;
                            document.getElementById('sgd-hidden-move-target-column').value = targetColumnName;
                            sfxSuccess();
                            form.submit();
                        }
                    }
                }
            });
        });

        // 11. Manager Approval Gate Button Handler in Modal
        const btnApproveDone = document.getElementById('modal-btn-approve-done');
        if (btnApproveDone) {
            btnApproveDone.addEventListener('click', function() {
                const taskId = document.getElementById('modal-task-id')?.value;
                if (!taskId) return;

                if (!confirm('👑 Setujui dan selesaikan tugas ini? Semua item checklist verifikasi akan otomatis dicentang selesai dan hadiah quest akan dibagikan ke anggota tim.')) {
                    return;
                }

                btnApproveDone.disabled = true;
                const origText = btnApproveDone.textContent;
                btnApproveDone.textContent = '⏳ Menyelesaikan...';

                const formData = new FormData();
                formData.append('sgd_action', 'sgd_approve_quest_task');
                formData.append('is_ajax', '1');
                formData.append('task_id', taskId);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        sfxSuccess();
                        alert(data.data?.message || '👑 Quest berhasil disetujui & diselesaikan!');
                        window.location.reload();
                    } else {
                        alert(data.data?.message || 'Gagal menyetujui tugas.');
                        btnApproveDone.disabled = false;
                        btnApproveDone.textContent = origText;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Terjadi kesalahan koneksi.');
                    btnApproveDone.disabled = false;
                    btnApproveDone.textContent = origText;
                });
            });
        }

        // 12. Set Image as Task Cover Handler
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('sgd-btn-make-cover')) {
                const btn = e.target;
                const coverUrl = btn.getAttribute('data-url');
                const taskId = btn.getAttribute('data-task-id');
                if (!coverUrl || !taskId) return;

                btn.disabled = true;
                btn.textContent = '⏳ Menyimpan...';

                const formData = new FormData();
                formData.append('sgd_action', 'sgd_set_quest_cover');
                formData.append('is_ajax', '1');
                formData.append('task_id', taskId);
                formData.append('cover_url', coverUrl);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = '🖼️ Cover';
                    if (data.success) {
                        sfxSuccess();
                        // Update card cover element in real-time
                        const wrap = document.getElementById('card-cover-wrap-' + taskId);
                        const img = document.getElementById('card-cover-img-' + taskId);
                        if (wrap && img) {
                            img.src = coverUrl;
                            wrap.style.display = '';
                        }
                        alert('Gambar berhasil dipasang sebagai cover kartu tugas!');
                    } else {
                        alert(data.data?.message || 'Gagal mengubah cover.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    btn.disabled = false;
                    btn.textContent = '🖼️ Cover';
                    alert('Terjadi kesalahan koneksi.');
                });
            }
        });

        // Modal Cover File Input Handler
        const modalCoverInput = document.getElementById('modal-cover-file-input');
        if (modalCoverInput) {
            modalCoverInput.addEventListener('change', function() {
                const file = this.files && this.files[0];
                const taskId = document.getElementById('modal-task-id')?.value;
                if (!file || !taskId) return;

                const lbl = document.getElementById('modal-lbl-upload-cover');
                const origText = lbl ? lbl.innerHTML : '';
                if (lbl) lbl.innerHTML = '⏳ Mengunggah...';

                const formData = new FormData();
                formData.append('sgd_action', 'sgd_upload_quest_cover');
                formData.append('is_ajax', '1');
                formData.append('task_id', taskId);
                formData.append('cover_file', file);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (lbl) lbl.innerHTML = origText;
                    modalCoverInput.value = '';
                    if (data.success) {
                        sfxSuccess();
                        const coverBox = document.getElementById('modal-cover-preview-box');
                        const coverImg = document.getElementById('modal-cover-preview-img');
                        const btnRemoveCover = document.getElementById('modal-btn-remove-cover');
                        if (coverImg) coverImg.src = data.data.cover_url;
                        if (coverBox) coverBox.style.display = 'block';
                        if (btnRemoveCover) btnRemoveCover.style.display = 'inline-block';

                        const cardWrap = document.getElementById('card-cover-wrap-' + taskId);
                        const cardImg = document.getElementById('card-cover-img-' + taskId);
                        if (cardWrap && cardImg) {
                            cardImg.src = data.data.cover_url;
                            cardWrap.style.display = '';
                        }

                        if (data.data.attachments && typeof renderAttachments === 'function') {
                            renderAttachments(data.data.attachments);
                        }
                        alert('Foto sampul berhasil diunggah!');
                    } else {
                        alert(data.data?.message || 'Gagal mengunggah foto sampul.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (lbl) lbl.innerHTML = origText;
                    modalCoverInput.value = '';
                    alert('Terjadi kesalahan koneksi.');
                });
            });
        }

        // Modal Remove Cover Button Handler
        const btnRemoveCover = document.getElementById('modal-btn-remove-cover');
        if (btnRemoveCover) {
            btnRemoveCover.addEventListener('click', function() {
                const taskId = document.getElementById('modal-task-id')?.value;
                if (!taskId) return;

                if (!confirm('Hapus foto sampul dari kartu ini?')) return;

                btnRemoveCover.disabled = true;

                const formData = new FormData();
                formData.append('sgd_action', 'sgd_set_quest_cover');
                formData.append('is_ajax', '1');
                formData.append('task_id', taskId);
                formData.append('cover_url', 'none');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    btnRemoveCover.disabled = false;
                    if (data.success) {
                        sfxSuccess();
                        const coverBox = document.getElementById('modal-cover-preview-box');
                        if (coverBox) coverBox.style.display = 'none';
                        btnRemoveCover.style.display = 'none';

                        const cardWrap = document.getElementById('card-cover-wrap-' + taskId);
                        if (cardWrap) cardWrap.style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error(err);
                    btnRemoveCover.disabled = false;
                });
            });
        }

        // Direct Card Quick Cover Upload Handler
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('sgd-card-cover-upload-input')) {
                const input = e.target;
                const file = input.files && input.files[0];
                const taskId = input.getAttribute('data-task-id');
                if (!file || !taskId) return;

                const parentBtn = input.closest('.sgd-btn-quick-cover');
                const origHtml = parentBtn ? parentBtn.innerHTML : '';
                if (parentBtn) parentBtn.innerHTML = '⏳';

                const formData = new FormData();
                formData.append('sgd_action', 'sgd_upload_quest_cover');
                formData.append('is_ajax', '1');
                formData.append('task_id', taskId);
                formData.append('cover_file', file);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (parentBtn) parentBtn.innerHTML = origHtml;
                    input.value = '';
                    if (data.success) {
                        sfxSuccess();
                        const cardWrap = document.getElementById('card-cover-wrap-' + taskId);
                        const cardImg = document.getElementById('card-cover-img-' + taskId);
                        if (cardWrap && cardImg) {
                            cardImg.src = data.data.cover_url;
                            cardWrap.style.display = '';
                        }
                    } else {
                        alert(data.data?.message || 'Gagal mengunggah cover.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (parentBtn) parentBtn.innerHTML = origHtml;
                    input.value = '';
                    alert('Terjadi kesalahan koneksi.');
                });
            }
        });

        // 13. Kanban Quick Filters & Live Search Engine
        function initKanbanFilters() {
            const searchInput = document.getElementById('sgd-kanban-search');
            const searchClear = document.getElementById('sgd-search-clear');
            const filterPills = document.querySelectorAll('.sgd-filter-pill');
            const tagSelect = document.getElementById('sgd-filter-tag');
            const memberSelect = document.getElementById('sgd-filter-member');
            const btnReset = document.getElementById('sgd-btn-reset-filters');
            const currentUserId = String(window.SGD_QUESTS_CONFIG?.currentUserId || '');

            let currentActivePill = 'all';

            // Calculate initial badge counts
            const cards = document.querySelectorAll('.sgd-kanban-card');
            let myCount = 0;
            let urgentCount = 0;
            let filesCount = 0;

            cards.forEach(card => {
                const assigned = (card.getAttribute('data-assigned') || '').split(',');
                const dlStatus = card.getAttribute('data-deadline-status') || '';
                const hasFiles = card.getAttribute('data-has-files') === 'true';

                if (currentUserId && assigned.includes(currentUserId)) myCount++;
                if (['urgent-3days', 'today', 'overdue'].includes(dlStatus)) urgentCount++;
                if (hasFiles) filesCount++;
            });

            const pillMy = document.getElementById('pill-count-my');
            const pillUrgent = document.getElementById('pill-count-urgent');
            const pillFiles = document.getElementById('pill-count-files');
            if (pillMy) pillMy.textContent = myCount;
            if (pillUrgent) pillUrgent.textContent = urgentCount;
            if (pillFiles) pillFiles.textContent = filesCount;

            function applyFilters() {
                const query = (searchInput?.value || '').trim().toLowerCase();
                const selectedTag = (tagSelect?.value || '').trim().toLowerCase();
                const selectedMember = (memberSelect?.value || '').trim();

                if (searchClear) {
                    searchClear.style.display = query ? 'flex' : 'none';
                }

                document.querySelectorAll('.sgd-kanban-column').forEach(col => {
                    const colCards = col.querySelectorAll('.sgd-kanban-card');
                    let visibleCount = 0;

                    colCards.forEach(card => {
                        const title = (card.getAttribute('data-title') || '').toLowerCase();
                        const tags = (card.getAttribute('data-tags') || '').toLowerCase();
                        const assigned = (card.getAttribute('data-assigned') || '').split(',');
                        const dlStatus = card.getAttribute('data-deadline-status') || '';
                        const hasFiles = card.getAttribute('data-has-files') === 'true';

                        let matches = true;

                        // 1. Text search
                        if (query && !title.includes(query) && !tags.includes(query)) {
                            matches = false;
                        }

                        // 2. Active quick pill filter
                        if (matches && currentActivePill === 'my-tasks') {
                            if (!currentUserId || !assigned.includes(currentUserId)) matches = false;
                        } else if (matches && currentActivePill === 'urgent') {
                            if (!['urgent-3days', 'today', 'overdue'].includes(dlStatus)) matches = false;
                        } else if (matches && currentActivePill === 'has-files') {
                            if (!hasFiles) matches = false;
                        }

                        // 3. Tag filter
                        if (matches && selectedTag) {
                            if (!tags.includes(selectedTag)) matches = false;
                        }

                        // 4. Member filter
                        if (matches && selectedMember) {
                            if (!assigned.includes(selectedMember)) matches = false;
                        }

                        if (matches) {
                            card.style.display = '';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    // Update column badge counter
                    const badge = col.querySelector('.sgd-column-count-badge');
                    if (badge) {
                        badge.textContent = visibleCount;
                    }

                    // Check if column needs empty placeholder
                    let emptyMsg = col.querySelector('.sgd-filter-empty-alert');
                    if (visibleCount === 0 && colCards.length > 0) {
                        if (!emptyMsg) {
                            emptyMsg = document.createElement('div');
                            emptyMsg.className = 'sgd-filter-empty-alert';
                            emptyMsg.textContent = 'Tidak ada tugas yang cocok dengan filter.';
                            col.querySelector('.sgd-column-cards-list')?.appendChild(emptyMsg);
                        }
                        emptyMsg.style.display = '';
                    } else if (emptyMsg) {
                        emptyMsg.style.display = 'none';
                    }
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            if (searchClear) {
                searchClear.addEventListener('click', function() {
                    searchInput.value = '';
                    applyFilters();
                    searchInput.focus();
                });
            }

            filterPills.forEach(pill => {
                pill.addEventListener('click', function() {
                    filterPills.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');
                    currentActivePill = this.getAttribute('data-filter') || 'all';
                    applyFilters();
                });
            });

            if (tagSelect) tagSelect.addEventListener('change', applyFilters);
            if (memberSelect) memberSelect.addEventListener('change', applyFilters);

            if (btnReset) {
                btnReset.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    if (tagSelect) tagSelect.value = '';
                    if (memberSelect) memberSelect.value = '';
                    filterPills.forEach(p => p.classList.remove('active'));
                    document.querySelector('.sgd-filter-pill[data-filter="all"]')?.classList.add('active');
                    currentActivePill = 'all';
                    applyFilters();
                });
            }

            // Auto-apply initial filter if passed from URL (e.g. ?filter=my-tasks)
            const initialFilter = window.SGD_QUESTS_CONFIG?.initialFilter;
            if (initialFilter) {
                const targetPill = document.querySelector(`.sgd-filter-pill[data-filter="${initialFilter}"]`);
                if (targetPill) {
                    filterPills.forEach(p => p.classList.remove('active'));
                    targetPill.classList.add('active');
                    currentActivePill = initialFilter;
                    applyFilters();
                }
            }
        }

        // Initialize Kanban Filters
        initKanbanFilters();

        // Listeners for Reward Choice and Quest Level in Modal
        const rewardTypeSelect = document.getElementById('modal-task-reward-type');
        if (rewardTypeSelect) {
            rewardTypeSelect.addEventListener('change', function() {
                updateRewardFieldsVisibility(this.value);
            });
        }

        const questLevelInput = document.getElementById('modal-task-quest-level');
        if (questLevelInput) {
            questLevelInput.addEventListener('input', function() {
                updateQuestLevelPreview(this.value);
            });
        }

        // Auto-open task if ?open_task=X is present in URL
        if (window.SGD_QUESTS_CONFIG && window.SGD_QUESTS_CONFIG.openTaskId) {
            const targetTaskId = window.SGD_QUESTS_CONFIG.openTaskId;
            openTaskDetail(targetTaskId);

            // Highlight and scroll to the card in Kanban
            setTimeout(() => {
                const cardEl = document.querySelector(`.sgd-kanban-card[data-task-id="${targetTaskId}"]`);
                if (cardEl) {
                    cardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    cardEl.classList.add('sgd-card-highlighted');
                    setTimeout(() => {
                        cardEl.classList.remove('sgd-card-highlighted');
                    }, 5000);
                }
            }, 300);
        }

    })();

    // Helper for deadline presets
    window.sgdSetDeadlinePreset = function(days) {
        const input = document.getElementById('modal-task-deadline');
        if (!input) return;
        const target = new Date(Date.now() + days * 86400000);
        const y = target.getFullYear();
        const m = String(target.getMonth() + 1).padStart(2, '0');
        const d = String(target.getDate()).padStart(2, '0');
        input.value = `${y}-${m}-${d}T17:00`;
    };

    // Helper for quest level presets
    window.sgdSetQuestLevel = function(lvl) {
        const input = document.getElementById('modal-task-quest-level');
        if (!input) return;
        input.value = lvl;
        input.dispatchEvent(new Event('input'));
    };
});
