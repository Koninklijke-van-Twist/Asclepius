<div id="update-notify-banner" class="update-notify-banner" hidden>
    <div class="update-notify-strip" aria-hidden="true"></div>
    <div class="update-notify-content">
        <div class="update-notify-box" id="update-notify-text" role="status" aria-live="polite"></div>
    </div>
</div>
<script src="update-watch.js"></script>
<script>
    (function ()
    {
        var POLL_MS = 5000;
        var WARNING_MS = 5 * 60 * 1000;
        var banner = document.getElementById('update-notify-banner');
        var textEl = document.getElementById('update-notify-text');
        var deployDeadline = null;
        var countdownTimer = null;
        var activateDeployWatch = window.AsclepiusUpdateWatch
            ? window.AsclepiusUpdateWatch.startUpdateBannerWatch()
            : null;

        if (!banner || !textEl)
        {
            return;
        }

        function pad (value)
        {
            return value < 10 ? '0' + String(value) : String(value);
        }

        function formatRemaining (milliseconds)
        {
            var totalSeconds = Math.max(0, Math.ceil(milliseconds / 1000));
            var minutes = Math.floor(totalSeconds / 60);
            var seconds = totalSeconds % 60;
            return pad(minutes) + ':' + pad(seconds);
        }

        function renderCountdown ()
        {
            if (!deployDeadline)
            {
                return;
            }

            var remaining = deployDeadline - Date.now();
            if (remaining > 0)
            {
                textEl.textContent = 'Asclepius wordt over ' + formatRemaining(remaining)
                    + ' geupdate, en is dan mogelijk een paar minuten niet bereikbaar.';
                return;
            }

            textEl.textContent = 'Asclepius wordt nu bijgewerkt. Een moment geduld alstublieft.';
        }

        function showBanner (deadline)
        {
            if (deployDeadline)
            {
                return;
            }

            deployDeadline = deadline;
            banner.hidden = false;
            document.body.classList.add('has-update-notify');
            renderCountdown();
            countdownTimer = window.setInterval(renderCountdown, 1000);

            if (activateDeployWatch)
            {
                activateDeployWatch();
            }
        }

        function checkUpdateNotify ()
        {
            var poll = window.AsclepiusUpdateWatch && window.AsclepiusUpdateWatch.pollUpdateStatus;
            if (!poll)
            {
                return;
            }

            poll().then(function (status)
            {
                if (!status || !status.notify)
                {
                    return;
                }

                var notifyStartedAt = status.notify_started_at || Date.now();
                showBanner(notifyStartedAt + WARNING_MS);
            });
        }

        checkUpdateNotify();
        window.setInterval(checkUpdateNotify, POLL_MS);
    }());
</script>
