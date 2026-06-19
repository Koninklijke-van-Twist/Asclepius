(function ()
{
    'use strict';

    var INDEX_CHECK_MS = 10000;
    var MAINTENANCE_MARKER = 'data-asclepius-maintenance="1"';
    var LIVE_MARKER = 'data-api-url="';

    function isMaintenanceHtml (html)
    {
        return String(html || '').indexOf(MAINTENANCE_MARKER) !== -1;
    }

    function isLiveAppHtml (html)
    {
        return String(html || '').indexOf(LIVE_MARKER) !== -1;
    }

    function fetchIndexHtml ()
    {
        return fetch('index.php?_=' + String(Date.now()), {
            cache: 'no-store',
            credentials: 'same-origin'
        }).then(function (response)
        {
            return response.ok ? response.text() : '';
        }).catch(function ()
        {
            return '';
        });
    }

    function fetchUpdateNotifyExists ()
    {
        return fetch('update.notify?_=' + String(Date.now()), {
            method: 'HEAD',
            cache: 'no-store'
        }).then(function (response)
        {
            return response.ok;
        }).catch(function ()
        {
            return false;
        });
    }

    function reloadPage ()
    {
        window.location.reload();
    }

    function startMaintenanceWatch ()
    {
        window.setInterval(function ()
        {
            fetchIndexHtml().then(function (html)
            {
                if (html && isLiveAppHtml(html) && !isMaintenanceHtml(html))
                {
                    reloadPage();
                }
            });
        }, INDEX_CHECK_MS);
    }

    function startUpdateBannerWatch ()
    {
        var watching = false;

        window.setInterval(function ()
        {
            if (!watching)
            {
                return;
            }

            fetchIndexHtml().then(function (html)
            {
                if (!html)
                {
                    return;
                }

                if (isMaintenanceHtml(html))
                {
                    reloadPage();
                    return;
                }

                if (!isLiveAppHtml(html))
                {
                    return;
                }

                fetchUpdateNotifyExists().then(function (notifyExists)
                {
                    if (!notifyExists)
                    {
                        reloadPage();
                    }
                });
            });
        }, INDEX_CHECK_MS);

        return function activateWatch ()
        {
            watching = true;
        };
    }

    window.AsclepiusUpdateWatch = {
        startMaintenanceWatch: startMaintenanceWatch,
        startUpdateBannerWatch: startUpdateBannerWatch
    };
}());
