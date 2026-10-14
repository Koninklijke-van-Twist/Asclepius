<?php
/**
 * Elke ontvanger, ook ICT en de behandelaar, krijgt de mail in de eigen taal.
 * Verschillende talen worden aparte mails. Dezelfde taal deelt één mail.
 */

echo "=== TEST: ICT-mail per taal van de ontvanger ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
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

function assertIctMail(string $label, mixed $expected, mixed $actual): void
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

function assertIctMailTrue(string $label, bool $condition): void
{
    assertIctMail($label, true, $condition);
}

$reporter = 'en-reporter@example.com';
$frenchIct = 'fr-ict@example.com';
$frenchIctTwo = 'fr-ict-2@example.com';
$germanIct = 'de-ict@example.com';
$frenchAssignee = 'fr-assignee@example.com';
$prefEmails = [$reporter, $frenchIct, $frenchIctTwo, $germanIct, $frenchAssignee];
foreach ($prefEmails as $prefEmail) {
    $path = getUserPrefsPath($prefEmail);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }
}
saveUserPref($reporter, 'lang', 'en');
saveUserPref($frenchIct, 'lang', 'fr');
saveUserPref($frenchIctTwo, 'lang', 'fr');
saveUserPref($germanIct, 'lang', 'de');
saveUserPref($frenchAssignee, 'lang', 'fr');
register_shutdown_function(static function () use ($prefEmails): void {
    foreach ($prefEmails as $prefEmail) {
        $path = getUserPrefsPath($prefEmail);
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }
});

$mailSettings = ['subject_prefix' => '', 'from_email' => 'kvtbot@kvt.nl', 'from_name' => 'KVT Bot'];
$ictUsers = [$frenchIct, $frenchIctTwo, $germanIct, $frenchAssignee];
$GLOBALS['ictUsers'] = $ictUsers;
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$GLOBALS['asclepiusUpdateMailLog'] = [];
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => null];

$store = new TicketStore(':memory:', sys_get_temp_dir() . '/asclepius_test_uploads_ict_mail', $ictUsers, TICKET_CATEGORIES);
$description = 'The screen stays black';
$created = $store->createTicket('Scherm zwart', 'hardware bestellen', $reporter, $description, [], 0, [], null, $frenchAssignee);
$ticketId = (int) ($created['ticket_id'] ?? 0);
$ticket = $store->getTicket($ticketId, true, $frenchAssignee);
$messageId = firstTicketMessageId(is_array($ticket) ? $ticket : []);
assertIctMailTrue('Ticket en eerste bericht bestaan', $ticketId > 0 && $messageId > 0 && is_array($ticket));

echo PHP_EOL . "Zonder vertaler: elke taal een eigen mail, niet de taal van de melder" . PHP_EOL;
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
notifyIctAboutTicket(
    $store,
    $ictUsers,
    $ticket,
    [$frenchIct, $germanIct],
    $description,
    $messageId,
    'email.subject_new_ticket',
    'email.intro_new_ict',
    $reporter,
    $reporter,
    'new_ticket'
);
assertIctMail('Twee talen, twee mails', 2, count($GLOBALS['asclepiusCapturedTicketEmails']));
$byRecipient = [];
foreach ($GLOBALS['asclepiusCapturedTicketEmails'] as $mail) {
    $recipients = $mail['recipients'] ?? [];
    if (is_array($recipients) && isset($recipients[0])) {
        $byRecipient[(string) $recipients[0]] = $mail;
    }
}
assertIctMailTrue('Franse ICT krijgt een eigen mail', isset($byRecipient[$frenchIct]));
assertIctMailTrue('Duitse ICT krijgt een eigen mail', isset($byRecipient[$germanIct]));
assertIctMail('Frans onderwerp', 'Nouveau ticket #' . $ticketId, (string) ($byRecipient[$frenchIct]['subject'] ?? ''));
assertIctMail('Duits onderwerp', 'Neues Ticket #' . $ticketId, (string) ($byRecipient[$germanIct]['subject'] ?? ''));
assertIctMailTrue(
    'Franse vaste tekst',
    str_contains((string) ($byRecipient[$frenchIct]['plain'] ?? ''), 'Un nouveau ticket a été soumis.')
);
assertIctMailTrue(
    'Duitse vaste tekst',
    str_contains((string) ($byRecipient[$germanIct]['plain'] ?? ''), 'Ein neues Ticket wurde eingereicht.')
);
assertIctMailTrue(
    'Niet het Engelse onderwerp van de melder',
    !str_contains((string) ($byRecipient[$frenchIct]['subject'] ?? ''), 'New ticket')
        && !str_contains((string) ($byRecipient[$germanIct]['subject'] ?? ''), 'New ticket')
);
assertIctMailTrue(
    'Origineel bericht tot de vertaling er is',
    str_contains((string) ($byRecipient[$frenchIct]['plain'] ?? ''), $description)
        && str_contains((string) ($byRecipient[$germanIct]['plain'] ?? ''), $description)
);

echo PHP_EOL . "Zelfde taal deelt één mail" . PHP_EOL;
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
notifyIctAboutTicket(
    $store,
    $ictUsers,
    $ticket,
    [$frenchIct, $frenchIctTwo],
    $description,
    $messageId,
    'email.subject_user_reply',
    'email.intro_user_reply',
    $reporter,
    $reporter,
    'user_reply'
);
assertIctMail('Eén mail voor twee Franse ontvangers', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
$sharedRecipients = $GLOBALS['asclepiusCapturedTicketEmails'][0]['recipients'] ?? [];
sort($sharedRecipients);
assertIctMail('Beide Fransen staan op die mail', [$frenchIctTwo, $frenchIct], $sharedRecipients);
assertIctMail(
    'Frans antwoord-onderwerp',
    'Réponse de l\'utilisateur sur le ticket #' . $ticketId,
    (string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['subject'] ?? '')
);

echo PHP_EOL . "Met vertaler: wachten per taal, daarna die vertaling" . PHP_EOL;
$waitingProvider = new class implements TranslationProvider {
    public function translateText(string $rawText, string $targetAppLanguage): array
    {
        return ['translated_text' => $rawText, 'source_language' => ''];
    }
};
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => $waitingProvider];
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
notifyIctAboutTicket(
    $store,
    $ictUsers,
    $ticket,
    [$frenchIct, $germanIct],
    $description,
    $messageId,
    'email.subject_new_ticket',
    'email.intro_new_ict',
    $reporter,
    $reporter,
    'new_ticket'
);
assertIctMail('Nog geen mail zolang de vertaling ontbreekt', 0, count($GLOBALS['asclepiusCapturedTicketEmails']));
$queuedLangs = [];
foreach ($store->listUserUpdateMailQueue($messageId) as $row) {
    $queuedLangs[] = (string) ($row['recipient_lang'] ?? '');
}
sort($queuedLangs);
assertIctMail('Wachtrij bevat Frans en Duits, niet de taal van de melder', ['de', 'fr'], $queuedLangs);

$sourceHash = hash('sha256', $description);
$store->upsertTextTranslation('ticket_message', $messageId, $ticketId, 'fr', 'en', $sourceHash, "L'écran reste noir");
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
dispatchQueuedUserUpdateMails($store, $messageId, time(), $ictUsers);
assertIctMail('Alleen de Franse mail gaat weg', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertIctMail('Franse ontvanger na het wachten', [$frenchIct], $GLOBALS['asclepiusCapturedTicketEmails'][0]['recipients'] ?? []);
assertIctMailTrue(
    'Franse berichttekst',
    str_contains((string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'] ?? ''), "L'écran reste noir")
);
assertIctMailTrue(
    'Duits blijft in de wachtrij',
    count($store->listUserUpdateMailQueue($messageId)) === 1
        && (string) ($store->listUserUpdateMailQueue($messageId)[0]['recipient_lang'] ?? '') === 'de'
);

$store->upsertTextTranslation('ticket_message', $messageId, $ticketId, 'de', 'en', $sourceHash, 'Der Bildschirm bleibt schwarz');
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
dispatchQueuedUserUpdateMails($store, $messageId, time(), $ictUsers);
assertIctMail('Daarna de Duitse mail', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertIctMail('Duitse ontvanger na het wachten', [$germanIct], $GLOBALS['asclepiusCapturedTicketEmails'][0]['recipients'] ?? []);
assertIctMail('Duits onderwerp na het wachten', 'Neues Ticket #' . $ticketId, (string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['subject'] ?? ''));
assertIctMailTrue(
    'Duitse berichttekst',
    str_contains((string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'] ?? ''), 'Der Bildschirm bleibt schwarz')
);
assertIctMail('Wachtrij leeg', 0, count($store->listUserUpdateMailQueue($messageId)));

echo PHP_EOL . "Behandelaar in het Frans, melder in het Engels" . PHP_EOL;
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
notifyAssigneeOfTicket($store, $ictUsers, $ticket, $frenchAssignee, $description, $reporter, $messageId);
assertIctMail('Behandelaar krijgt meteen de Franse mail', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertIctMail('Behandelaar is de ontvanger', [$frenchAssignee], $GLOBALS['asclepiusCapturedTicketEmails'][0]['recipients'] ?? []);
assertIctMail(
    'Frans toewijs-onderwerp',
    'Le ticket #' . $ticketId . ' vous a été assigné',
    (string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['subject'] ?? '')
);
assertIctMailTrue(
    'Franse toewijs-intro',
    str_contains((string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'] ?? ''), 'Un ticket vous a été réassigné.')
);
assertIctMailTrue(
    'Niet het Engelse toewijs-onderwerp',
    !str_contains((string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['subject'] ?? ''), 'assigned to you')
);
assertIctMailTrue(
    'Bericht van de behandelaar is de Franse vertaling',
    str_contains((string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'] ?? ''), "L'écran reste noir")
);

echo PHP_EOL;
echo "Geslaagd: {$passed}" . PHP_EOL;
echo "Gefaald: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
