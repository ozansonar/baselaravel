/**
 * Campaign status screen — cron countdown and auto-refresh while sending.
 *
 * The counters move because a cron ran, not because of anything the browser
 * did, so the page reloads itself just after each scheduled run rather than
 * showing numbers that silently go stale.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var countdown = document.getElementById('cronCountdown');
        if (!countdown) return;

        var seconds = parseInt(countdown.dataset.seconds, 10);
        if (isNaN(seconds)) return;

        // Only a running campaign needs to refresh; a draft or a finished one
        // has nothing moving.
        var isSending = document.querySelector('.live-dot') !== null;

        var timer = setInterval(function () {
            seconds -= 1;

            if (seconds <= 0) {
                clearInterval(timer);
                countdown.textContent = '(çalışıyor…)';

                if (isSending) {
                    // A few seconds of slack so the run has finished writing
                    // before the page asks for the new numbers.
                    setTimeout(function () { window.location.reload(); }, 8000);
                }

                return;
            }

            countdown.textContent = '(' + format(seconds) + ')';
        }, 1000);
    });

    function format(seconds) {
        if (seconds < 60) return seconds + ' sn';
        var minutes = Math.floor(seconds / 60);
        return minutes + ' dk ' + (seconds % 60) + ' sn';
    }
})();
