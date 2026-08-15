jQuery(document).ready(function ($) {
    let lastOrderId = snaporder_sound_vars.latest_order_id;
    let audioContext = null;
    let soundEnabled = localStorage.getItem('snaporder_sound_enabled') === 'true';

    // Add UI Control
    const $header = $('.snaporder-logo-text');
    const $toggleBtn = $('<button type="button" class="button snaporder-sound-toggle"><span class="dashicons dashicons-volume-off"></span> Enable Sound</button>');

    // Insert after title or somewhere visible
    if ($('.snaporder-page-header').length) {
        $('.snaporder-page-header').append($toggleBtn);
        $toggleBtn.css({ 'margin-left': '15px', 'vertical-align': 'middle' });
    } else if ($('.snaporder-page-title').length) {
        $('.snaporder-page-title').append($toggleBtn);
        $toggleBtn.css({ 'margin-left': '15px', 'vertical-align': 'middle' });
    }

    // Update Initial Button State
    if (soundEnabled) {
        $toggleBtn.html('<span class="dashicons dashicons-volume-on"></span> Sound On');
        $toggleBtn.addClass('button-primary');
    } else {
        $toggleBtn.html('<span class="dashicons dashicons-volume-off"></span> Enable Sound');
    }

    let isRinging = false;
    let loopTimeout = null;

    // Enhance Audio Object

    function playSoundLoop() {
        if (!soundEnabled || isRinging) return;

        isRinging = true;

        const playWithDelay = () => {
            if (!isRinging || !soundEnabled) return;

            playTone();

            // Loop every 4 seconds
            loopTimeout = setTimeout(playWithDelay, 4000);
        };

        playWithDelay();

        // Visual indicator
        $toggleBtn.addClass('snaporder-ringing-pulse');
    }

    function stopSoundLoop() {
        isRinging = false;
        if (loopTimeout) {
            clearTimeout(loopTimeout);
            loopTimeout = null;
        }
        $toggleBtn.removeClass('snaporder-ringing-pulse');
    }

    function playTone() {
        audioContext = audioContext || new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.18, audioContext.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.45);
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.5);
    }

    // Stop Sound Triggers
    $(document).on('click', '.snaporder-view-order-btn, .snaporder-order-card, .snaporder-status-tab, #snaporder-order-modal', function () {
        if (isRinging) {
            stopSoundLoop();
        }
    });

    // Toggle Button Logic
    // ... (Keep existing toggle logic, maybe update class/icon)
    $toggleBtn.on('click', function () {
        soundEnabled = !soundEnabled;
        if (soundEnabled) {
            playTone();

            $(this).html('<span class="dashicons dashicons-volume-on"></span> Sound On');
            $(this).addClass('button-primary');
        } else {
            stopSoundLoop(); // Stop if user turns it off
            $(this).html('<span class="dashicons dashicons-volume-off"></span> Sound Off');
            $(this).removeClass('button-primary');
        }
        localStorage.setItem('snaporder_sound_enabled', soundEnabled ? 'true' : 'false');
    });

    // Check for new orders every 15 seconds
    setInterval(function () {
        $.post(snaporder_sound_vars.ajax_url, {
            action: 'snaporder_check_new_orders',
            nonce: snaporder_sound_vars.nonce,
            last_id: lastOrderId
        }, function (response) {
            if (response.success && response.data.new_orders) {
                // Update tracker
                lastOrderId = response.data.latest_id;

                // Trigger Loop
                playSoundLoop();

                // Optional: Show browser notification
                if (Notification.permission === "granted") {
                    new Notification("New Order Received!", {
                        body: "Order #" + lastOrderId + " has just been placed.",
                        icon: snaporder_sound_vars.icon_url // Optional if we had one
                    });
                }

                showToast("New Order #" + lastOrderId + " Received!");
            }
        });
    }, 15000); // 15 seconds

    function showToast(message) {
        // Simple toast notification
        let $toast = $('<div class="snaporder-toast">' + message + '</div>');
        $('body').append($toast);
        $toast.css({
            'position': 'fixed',
            'bottom': '20px',
            'right': '20px',
            'background': '#10b981',
            'color': '#fff',
            'padding': '15px 25px',
            'border-radius': '8px',
            'box-shadow': '0 4px 6px rgba(0,0,0,0.1)',
            'z-index': '9999',
            'font-weight': 'bold',
            'animation': 'slideIn 0.3s ease-out'
        });

        setTimeout(function () {
            $toast.fadeOut(function () { $(this).remove(); });
        }, 5000);
    }
});
