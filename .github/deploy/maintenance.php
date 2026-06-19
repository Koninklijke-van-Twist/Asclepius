<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
http_response_code(503);
header('Retry-After: 120');

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asclepius — update</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #10233f;
        }

        .maintenance-box {
            max-width: 520px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #d8e0eb;
            border-radius: 16px;
            padding: 32px 28px;
            text-align: center;
            box-shadow: 0 16px 40px rgba(15, 35, 63, 0.08);
        }

        .maintenance-box h1 {
            margin: 0 0 12px;
            font-size: 22px;
        }

        .maintenance-box p {
            margin: 0;
            line-height: 1.6;
            color: #5b6b82;
            min-height: 3.2em;
        }
    </style>
</head>
<body data-asclepius-maintenance="1">
    <div class="maintenance-box">
        <h1>Asclepius</h1>
        <p id="maintenance-message">Asclepius wordt nu bijgewerkt naar een nieuwere versie. Een moment geduld alstublieft.</p>
    </div>
    <script>
        (function ()
        {
            var messages = [
                { lang: 'nl', text: 'Asclepius wordt nu bijgewerkt naar een nieuwere versie. Een moment geduld alstublieft.' },
                { lang: 'en', text: 'Asclepius is being updated to a newer version. Please wait a moment.' },
                { lang: 'fr', text: 'Asclepius est en cours de mise à jour vers une version plus récente. Veuillez patienter un instant.' },
                { lang: 'de', text: 'Asclepius wird gerade auf eine neuere Version aktualisiert. Bitte haben Sie einen Moment Geduld.' }
            ];
            var messageEl = document.getElementById('maintenance-message');
            var index = 0;

            if (!messageEl)
            {
                return;
            }

            window.setInterval(function ()
            {
                index = (index + 1) % messages.length;
                messageEl.textContent = messages[index].text;
                document.documentElement.lang = messages[index].lang;
            }, 3000);
        }());
    </script>
    <script src="update-watch.js"></script>
    <script>
        if (window.AsclepiusUpdateWatch)
        {
            window.AsclepiusUpdateWatch.startMaintenanceWatch();
        }
    </script>
</body>
</html>
