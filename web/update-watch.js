(function ()
{
    'use strict';

    var CHECK_MS = 10000;
    var NOTIFY_URL = 'update.notify';
    var COMPLETE_URL = 'update.complete';

    function fileExists (url)
    {
        return fetch(url + '?_=' + String(Date.now()), {
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
            fileExists(COMPLETE_URL).then(function (complete)
            {
                if (complete)
                {
                    reloadPage();
                }
            });
        }, CHECK_MS);
    }

    function startUpdateBannerWatch ()
    {
        var watching = false;
        var notifyWasSeen = false;
        var deployPhaseReloaded = false;

        window.setInterval(function ()
        {
            if (!watching)
            {
                return;
            }

            Promise.all([
                fileExists(NOTIFY_URL),
                fileExists(COMPLETE_URL)
            ]).then(function (results)
            {
                var notifyExists = results[0];
                var completeExists = results[1];

                if (notifyExists)
                {
                    notifyWasSeen = true;
                }

                if (completeExists)
                {
                    reloadPage();
                    return;
                }

                if (notifyWasSeen && !notifyExists && !deployPhaseReloaded)
                {
                    deployPhaseReloaded = true;
                    reloadPage();
                }
            });
        }, CHECK_MS);

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
