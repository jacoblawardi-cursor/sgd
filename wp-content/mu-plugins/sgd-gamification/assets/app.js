/**
 * SGD 16-Bit & WoW Gamification JavaScript Engine
 * Audio synthesizer, modals, photo proof preview, and UI interactivity
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Audio Synthesizer (8-Bit Retro & Fanfare Sounds using Web Audio API)
    const AudioEngine = {
        ctx: null,
        enabled: true,

        init: function() {
            if (!this.ctx && (window.AudioContext || window.webkitAudioContext)) {
                this.ctx = new (window.AudioContext || window.webkitAudioContext)();
            }
        },

        playTone: function(freq, type, duration, delay = 0) {
            if (!this.enabled) return;
            this.init();
            if (!this.ctx) return;

            setTimeout(() => {
                try {
                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();
                    osc.type = type; // 'square', 'sine', 'triangle', 'sawtooth'
                    osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
                    gain.gain.setValueAtTime(0.12, this.ctx.currentTime);
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

        playSosAlert: function() {
            this.playTone(880, 'sawtooth', 0.15, 0);
            this.playTone(440, 'sawtooth', 0.2, 0.15);
            this.playTone(880, 'sawtooth', 0.15, 0.35);
        }
    };

    // Sound FX button toggle
    const sfxBtn = document.getElementById('sgd-sfx-toggle');
    if (sfxBtn) {
        sfxBtn.addEventListener('click', function() {
            AudioEngine.enabled = !AudioEngine.enabled;
            sfxBtn.textContent = AudioEngine.enabled ? '🔊 SFX: ON' : '🔇 SFX: OFF';
            if (AudioEngine.enabled) AudioEngine.playClick();
        });
    }

    // Attach click sound to buttons
    document.querySelectorAll('.sgd-btn, .sgd-tab-btn, .sgd-portal-card').forEach(btn => {
        btn.addEventListener('click', () => AudioEngine.playClick());
    });

    // Auto-play victory sound if celebration banner is present
    if (document.querySelector('.sgd-flash-celebration')) {
        AudioEngine.playVictoryFanfare();
    }

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
});
