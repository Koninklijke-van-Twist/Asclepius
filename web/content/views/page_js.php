    <script>
        document.addEventListener('DOMContentLoaded', function ()
        {
            var blockedCheckbox = document.getElementById('priority_blocked');
            var fullyBlockedCheckbox = document.getElementById('priority_fully_blocked');
            var fullyBlockedWrap = document.getElementById('priority_fully_blocked_wrap');

            var syncPriorityVisibility = function ()
            {
                if (!blockedCheckbox || !fullyBlockedWrap || !fullyBlockedCheckbox)
                {
                    return;
                }

                var wasHidden = fullyBlockedWrap.hidden;
                var showFullyBlocked = blockedCheckbox.checked;
                fullyBlockedWrap.hidden = !showFullyBlocked;

                if (showFullyBlocked && wasHidden)
                {
                    fullyBlockedWrap.classList.remove('flash-blue');
                    void fullyBlockedWrap.offsetWidth;
                    fullyBlockedWrap.classList.add('flash-blue');
                }

                if (!showFullyBlocked)
                {
                    fullyBlockedCheckbox.checked = false;
                    fullyBlockedWrap.classList.remove('flash-blue');
                }
            };

            if (blockedCheckbox)
            {
                blockedCheckbox.addEventListener('change', syncPriorityVisibility);
                syncPriorityVisibility();
            }

            document.querySelectorAll('[data-settings-row]').forEach(function (row)
            {
                var availabilityCheckbox = row.querySelector('.availability-checkbox');
                var vacationIndicator = row.querySelector('.vacation-indicator');
                var vacationBadge = row.querySelector('.vacation-badge');

                if (!availabilityCheckbox)
                {
                    return;
                }

                var syncAvailabilityState = function ()
                {
                    var isAvailable = availabilityCheckbox.checked;
                    row.classList.toggle('is-away', !isAvailable);
                    if (vacationIndicator)
                    {
                        vacationIndicator.hidden = isAvailable;
                    }
                    if (vacationBadge)
                    {
                        vacationBadge.classList.toggle('is-away', !isAvailable);
                    }
                };

                availabilityCheckbox.addEventListener('change', syncAvailabilityState);
                syncAvailabilityState();
            });
        });
    </script>
