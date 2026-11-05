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
            this.playTone(600, 'square', 0.05);
        },

        playQuestAccept: function() {
            this.playTone(440, 'triangle', 0.1, 0);
            this.playTone(554.37, 'triangle', 0.1, 0.08);
            this.playTone(659.25, 'triangle', 0.2, 0.16);
        },

        playVictoryFanfare: function() {
            // Retro 8-bit level up / quest complete fanfare
            const notes = [
                { f: 523.25, d: 0.12, t: 0 },
                { f: 659.25, d: 0.12, t: 0.12 },
                { f: 783.99, d: 0.12, t: 0.24 },
                { f: 1046.50, d: 0.35, t: 0.36 }
            ];
            notes.forEach(n => this.playTone(n.f, 'square', n.d, n.t));
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
});
