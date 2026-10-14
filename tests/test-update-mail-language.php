<?php
/**
 * Updatemails naar gebruikers: taal uit user_prefs, wachten op de vertaling,
 * meteen bij dezelfde brontaal, en na de time-out het origineel.
 */

echo "=== TEST: updatemail in de taal van de ontvanger ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';
$_SESSION = [];

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/TicketStore.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/helpers.php';
require __DIR__ . '/../web/content/TranslationProvider.php';
require __DIR__ . '/../web/content/LaraTranslationProvider.php';
require __DIR__ . '/../web/content/CoPilotTranslate.php';
require __DIR__ . '/../web/content/CopilotTranslationProvider.php';
require __DIR__ . '/../web/content/translation.php';
require __DIR__ . '/../web/content/mail.php';

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function assertMail(string $label, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "  ✗ {$label}" . PHP_EOL;
    echo "      Verwacht : " . var_export($expected, true) . PHP_EOL;
    echo "      Gekregen : " . var_export($actual, true) . PHP_EOL;
    $failed++;
}

function assertMailTrue(string $label, bool $condition): void
{
    assertMail($label, true, $condition);
}

$prefEmails = [
    'fr-update-mail@example.com',
    'en-update-mail@example.com',
    'de-update-mail@example.com',
];
foreach ($prefEmails as $prefEmail) {
    $path = getUserPrefsPath($prefEmail);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }
}
saveUserPref('fr-update-mail@example.com', 'lang', 'fr');
saveUserPref('en-update-mail@example.com', 'lang', 'en');
saveUserPref('de-update-mail@example.com', 'lang', 'de');

register_shutdown_function(static function () use ($prefEmails): void {
    foreach ($prefEmails as $prefEmail) {
        $path = getUserPrefsPath($prefEmail);
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }
});

$mailSettings = ['subject_prefix' => '', 'from_email' => 'kvtbot@kvt.nl', 'from_name' => 'KVT Bot'];
$GLOBALS['ictUsers'] = ['ict@kvt.nl'];
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$GLOBALS['asclepiusUpdateMailLog'] = [];

echo "Vaste teksten" . PHP_EOL;
assertMail('Onderwerp nl', 'Update op ticket #7', __mail('email.subject_update', 'nl', 7));
assertMail('Onderwerp en', 'Update on ticket #7', __mail('email.subject_update', 'en', 7));
assertMail('Onderwerp de', 'Aktualisierung zu Ticket #7', __mail('email.subject_update', 'de', 7));
assertMail('Onderwerp fr', 'Mise à jour du ticket #7', __mail('email.subject_update', 'fr', 7));
assertMail('Intro fr', 'Un collaborateur a mis à jour votre ticket.', __mail('email.intro_update', 'fr', __mail('email.intro_update_no_status', 'fr')));
assertMail('Status fr', 'soumis', translateStatus('ingediend', 'fr'));
assertMail('Categorie en', 'order hardware', translateCategory('hardware bestellen', 'en'));
assertMail('Lege tekst heeft geen bericht-id', 0, updateMailMessageId('', 4, 9));
assertMail('Ghost-mail gebruikt de zichtbare notitie', 9, updateMailMessageId('notitie', 4, 9));
assertMail('Gewoon antwoord gebruikt het bericht', 4, updateMailMessageId('antwoord', 4, 0));

$store = new TicketStore(':memory:', sys_get_temp_dir() . '/asclepius_test_uploads_update_mail', ['ict@kvt.nl'], TICKET_CATEGORIES);
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => null];

$created = $store->createTicket(
    'Printer stuk',
    'hardware bestellen',
    'fr-update-mail@example.com',
    'The keyboard is stuck',
    []
);
$ticketId = (int) ($created['ticket_id'] ?? 0);
$ticket = $store->getTicket($ticketId, true, 'ict@kvt.nl');
$messageId = (int) (($ticket['messages'][0]['id'] ?? 0));
$original = 'The keyboard is stuck';

echo PHP_EOL . "Zonder vertaler meteen, wel in de taal van de ontvanger" . PHP_EOL;
sendUserUpdateNotifications(
    $store,
    $GLOBALS['ictUsers'],
    $ticket,
    ['fr-update-mail@example.com', 'ict@kvt.nl'],
    $original,
    $messageId,
    false,
    'ict@kvt.nl',
    'ict@kvt.nl',
    false
);
assertMail('Eén mail zonder vertaler', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertMail('Geen wachtrij zonder vertaler', 0, count($store->listUserUpdateMailQueue()));
$immediateWithoutProvider = $GLOBALS['asclepiusCapturedTicketEmails'][0];
assertMail('Ontvanger is de Fransman', ['fr-update-mail@example.com'], $immediateWithoutProvider['recipients']);
assertMail('Onderwerp Frans', 'Mise à jour du ticket #' . $ticketId, $immediateWithoutProvider['subject']);
assertMailTrue('Bericht blijft het origineel', str_contains((string) $immediateWithoutProvider['plain'], $original));
assertMailTrue('Vaste status in het Frans', str_contains((string) $immediateWithoutProvider['plain'], 'soumis'));
assertMailTrue('Vaste categorie in het Frans', str_contains((string) $immediateWithoutProvider['plain'], 'commander du matériel'));
assertMailTrue('Franse intro', str_contains((string) $immediateWithoutProvider['plain'], 'Un collaborateur a mis à jour votre ticket.'));

$calls = [];
$scripted = [];
$transport = static function (string $url, string $jsonBody, float $timeout) use (&$calls, &$scripted): array {
    $calls[] = ['url' => $url, 'body' => $jsonBody, 'timeout' => $timeout];
    $next = array_shift($scripted);
    if (!is_array($next)) {
        return ['status' => 500, 'body' => 'geen script', 'error' => ''];
    }

    return $next;
};
$provider = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => $provider];
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$GLOBALS['asclepiusUpdateMailLog'] = [];

echo PHP_EOL . "Brontaal bekend: meteen. Andere taal: wachten" . PHP_EOL;
$sourceHash = hash('sha256', $original);
$store->upsertTextTranslation('ticket_message', $messageId, $ticketId, 'en', 'en', $sourceHash, $original);
sendUserUpdateNotifications(
    $store,
    $GLOBALS['ictUsers'],
    $ticket,
    ['en-update-mail@example.com', 'de-update-mail@example.com', 'ict@kvt.nl'],
    $original,
    $messageId,
    true,
    'ict@kvt.nl',
    'ict@kvt.nl',
    true
);
assertMail('Alleen de Engelse ontvanger is meteen verstuurd', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertMail('Engels onderwerp', 'Update on ticket #' . $ticketId, $GLOBALS['asclepiusCapturedTicketEmails'][0]['subject']);
assertMailTrue('Engelse status', str_contains((string) $GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'], 'submitted'));
assertMailTrue('Hoge prioriteit bij wachten op de gebruiker', $GLOBALS['asclepiusCapturedTicketEmails'][0]['high_importance'] === true);
assertMailTrue('Originele tekst, geen vertaling nodig', str_contains((string) $GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'], $original));
$waiting = $store->listUserUpdateMailQueue($messageId);
assertMail('Duitse ontvanger staat in de wachtrij', 1, count($waiting));
assertMail('Wachtrijtaal is de', 'de', (string) ($waiting[0]['recipient_lang'] ?? ''));
assertMail('Hoge prioriteit blijft in de wachtrij', 1, (int) ($waiting[0]['high_importance'] ?? 0));

$store->upsertTextTranslation('ticket_message', $messageId, $ticketId, 'de', 'en', $sourceHash, 'Die Tastatur klemmt');
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
dispatchQueuedUserUpdateMails($store, $messageId, time(), $GLOBALS['ictUsers']);
assertMail('Na de Duitse vertaling één mail', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertMail('Duits onderwerp', 'Aktualisierung zu Ticket #' . $ticketId, $GLOBALS['asclepiusCapturedTicketEmails'][0]['subject']);
assertMail('Duitse ontvanger', ['de-update-mail@example.com'], $GLOBALS['asclepiusCapturedTicketEmails'][0]['recipients']);
assertMailTrue('Duitse berichttekst', str_contains((string) $GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'], 'Die Tastatur klemmt'));
assertMailTrue('Geen Engelse brontekst meer in de Duitse mail', !str_contains((string) $GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'], $original));
assertMailTrue('Hoge prioriteit na het wachten', $GLOBALS['asclepiusCapturedTicketEmails'][0]['high_importance'] === true);
assertMail('Geen fallback-log bij een gelukte vertaling', 0, count($GLOBALS['asclepiusUpdateMailLog']));
assertMail('Wachtrij leeg', 0, count($store->listUserUpdateMailQueue($messageId)));

echo PHP_EOL . "Time-out: origineel, vaste teksten in de taal, en een logregel" . PHP_EOL;
$store->enqueueUserUpdateMail(
    $ticketId,
    $messageId,
    'fr-update-mail@example.com',
    'fr',
    hash('sha256', $original),
    $original,
    false,
    'ict@kvt.nl',
    'ict@kvt.nl',
    false,
    time() - userUpdateMailWaitSeconds() - 5
);
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$GLOBALS['asclepiusUpdateMailLog'] = [];
dispatchQueuedUserUpdateMails($store, null, time(), $GLOBALS['ictUsers']);
assertMail('Timeout verstuurt alsnog', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertMail('Timeout-onderwerp Frans', 'Mise à jour du ticket #' . $ticketId, $GLOBALS['asclepiusCapturedTicketEmails'][0]['subject']);
assertMailTrue('Timeout-bericht is het origineel', str_contains((string) $GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'], $original));
assertMailTrue('Timeout-intro Frans', str_contains((string) $GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'], 'Un collaborateur a mis à jour votre ticket.'));
assertMail('Eén fallback-logregel', 1, count($GLOBALS['asclepiusUpdateMailLog']));
assertMailTrue('Log noemt de taal en de wachttijd', str_contains((string) $GLOBALS['asclepiusUpdateMailLog'][0], 'vertaling fr niet binnen ' . userUpdateMailWaitSeconds() . 's'));
assertMail('Timeout haalt de rij uit de wachtrij', 0, count($store->listUserUpdateMailQueue()));

$notYet = $store->enqueueUserUpdateMail(
    $ticketId,
    $messageId,
    'fr-update-mail@example.com',
    'fr',
    hash('sha256', $original),
    $original,
    false,
    '',
    '',
    false,
    time()
);
assertMailTrue('Eerste wachtrijrij wordt geplaatst', $notYet);
assertMail('Dubbele rij voor dezelfde tekst wordt genegeerd', false, $store->enqueueUserUpdateMail(
    $ticketId,
    $messageId,
    'fr-update-mail@example.com',
    'fr',
    hash('sha256', $original),
    $original,
    false,
    '',
    '',
    false,
    time() - 9999
));
dispatchQueuedUserUpdateMails($store, null, time(), $GLOBALS['ictUsers']);
assertMail('Binnen de tijd blijft de mail liggen', 1, count($store->listUserUpdateMailQueue()));

echo PHP_EOL . "Vertaling via de achtergrondstuurt de wachtende mail" . PHP_EOL;
$replyId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Please restart the printer');
$fresh = $store->getTicket($ticketId, true, 'ict@kvt.nl');
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
sendUserUpdateNotifications(
    $store,
    $GLOBALS['ictUsers'],
    is_array($fresh) ? $fresh : $ticket,
    ['fr-update-mail@example.com'],
    'Please restart the printer',
    $replyId,
    false,
    'ict@kvt.nl',
    'ict@kvt.nl',
    false
);
assertMail('Mail wacht tot de flow antwoordt', 0, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertMail('Reply staat in de wachtrij', 1, count($store->listUserUpdateMailQueue($replyId)));
$scripted[] = [
    'status' => 200,
    'body' => json_encode([
        'nl' => 'Start de printer opnieuw',
        'en' => 'Please restart the printer',
        'fr' => "Redémarrez l'imprimante",
        'de' => 'Starten Sie den Drucker neu',
    ], JSON_UNESCAPED_UNICODE),
    'error' => '',
];
flushQueuedTicketTranslations();
$frenchMails = array_values(array_filter(
    $GLOBALS['asclepiusCapturedTicketEmails'],
    static fn(array $mail): bool => in_array('fr-update-mail@example.com', $mail['recipients'], true)
        && str_contains((string) $mail['plain'], "Redémarrez l'imprimante")
));
assertMail('Achtergrondvertaling stuurt de Franse mail', 1, count($frenchMails));
assertMail('Wachtrij van het antwoord is leeg', 0, count($store->listUserUpdateMailQueue($replyId)));
assertMailTrue('Flow is aangeroepen', count($calls) >= 1);

echo PHP_EOL . "Gewijzigd bericht stuurt de oude mail niet" . PHP_EOL;
foreach ($store->listUserUpdateMailQueue() as $leftover) {
    $store->claimUserUpdateMail((int) ($leftover['id'] ?? 0));
}
$staleText = '[ ] Controle';
$staleId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', $staleText);
discardQueuedTicketTranslations();
$store->enqueueUserUpdateMail(
    $ticketId,
    $staleId,
    'de-update-mail@example.com',
    'de',
    hash('sha256', $staleText),
    $staleText,
    false,
    '',
    '',
    false,
    time() - userUpdateMailWaitSeconds() - 5
);
$checked = $store->updateTicketMessageCheckboxState($ticketId, $staleId, 0, true, true, 'ict@kvt.nl');
discardQueuedTicketTranslations();
assertMail('Aangevinkte tekst', '[x] Controle', $checked);
assertMail('Wachtrij van het oude bericht is leeg', 0, count($store->listUserUpdateMailQueue($staleId)));
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$GLOBALS['asclepiusUpdateMailLog'] = [];
dispatchQueuedUserUpdateMails($store, $staleId, time(), $GLOBALS['ictUsers']);
assertMail('Geen mail met de oude tekst', 0, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertMail('Geen timeout-log voor een vervallen mail', 0, count($GLOBALS['asclepiusUpdateMailLog']));

$store->enqueueUserUpdateMail(
    $ticketId,
    $messageId,
    'de-update-mail@example.com',
    'de',
    hash('sha256', 'tekst die niet meer klopt'),
    'tekst die niet meer klopt',
    false,
    '',
    '',
    false,
    time() - userUpdateMailWaitSeconds() - 5
);
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$GLOBALS['asclepiusUpdateMailLog'] = [];
dispatchQueuedUserUpdateMails($store, $messageId, time(), $GLOBALS['ictUsers']);
assertMail('Verouderde hash wordt niet verstuurd', 0, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertMail('Verouderde hash verdwijnt uit de wachtrij', 0, count($store->listUserUpdateMailQueue($messageId)));
assertMail('Verouderde hash schrijft geen fallback-log', 0, count($GLOBALS['asclepiusUpdateMailLog']));

echo PHP_EOL . "Een mail wordt maar één keer geclaimd" . PHP_EOL;
$store->enqueueUserUpdateMail(
    $ticketId,
    $messageId,
    'fr-update-mail@example.com',
    'fr',
    hash('sha256', $original),
    $original,
    false,
    '',
    '',
    false,
    time()
);
$claimRows = $store->listUserUpdateMailQueue($messageId);
assertMail('Er staat één rij klaar om te claimen', 1, count($claimRows));
$claimId = (int) ($claimRows[0]['id'] ?? 0);
$firstClaim = $store->claimUserUpdateMail($claimId);
$secondClaim = $store->claimUserUpdateMail($claimId);
assertMailTrue('Eerste claim levert de rij', is_array($firstClaim));
assertMail('Tweede claim krijgt niets', null, $secondClaim);
assertMail('Geclaimde rij is weg', 0, count($store->listUserUpdateMailQueue($messageId)));

echo PHP_EOL;
echo "Geslaagd: {$passed}" . PHP_EOL;
echo "Gefaald: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
