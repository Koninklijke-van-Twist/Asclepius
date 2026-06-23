(function ()

{

    'use strict';



    var CHECK_MS = 10000;

    var POLL_URL = 'update-poll.php';



    function pollUpdateStatus ()

    {

        return fetch(POLL_URL + '?_=' + String(Date.now()), {

            method: 'GET',

            cache: 'no-store'

        }).then(function (response)

        {

            if (!response.ok)

            {

                return { notify: false, complete: false };

            }



            return response.json();

        }).then(function (data)

        {

            if (!data || typeof data !== 'object')

            {

                return { notify: false, complete: false };

            }



            return {

                notify: !!data.notify,

                complete: !!data.complete,

                notify_started_at: data.notify_started_at || null

            };

        }).catch(function ()

        {

            return { notify: false, complete: false };

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

            pollUpdateStatus().then(function (status)

            {

                if (status.complete)

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



            pollUpdateStatus().then(function (status)

            {

                if (status.notify)

                {

                    notifyWasSeen = true;

                }



                if (status.complete)

                {

                    reloadPage();

                    return;

                }



                if (notifyWasSeen && !status.notify && !deployPhaseReloaded)

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

        pollUpdateStatus: pollUpdateStatus,

        startMaintenanceWatch: startMaintenanceWatch,

        startUpdateBannerWatch: startUpdateBannerWatch

    };

}());


