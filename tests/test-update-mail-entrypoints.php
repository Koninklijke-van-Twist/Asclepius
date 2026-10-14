<?php
/**
 * Vertaling bij posten en taalafhankelijke mail: dezelfde functies voor de UI en de API.
 */

echo "=== TEST: UI en API delen vertaling en updatemail ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/api.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SESSION = [];
@ini_set('sendmail_path', '/bin/true');

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    exit(1);
}

$authPath = __DIR__ . '/../web/auth.php';
$createdStubAuth = false;
if (!is_file($authPath)) {
    $written = file_put_contents(
        $authPath,
        "<?php\n"
        . "\$apiKeys = ['test-service-key'];\n"
        . "\$ictUsers = ['ict@kvt.nl'];\n"
        . "\$ictUserColors = [];\n"
        . "\$mailSettings = ['subject_prefix' => ''];\n"
        . "\$grokBot = ['enabled' => false];\n"
    );
    if ($written === false) {
        echo "FAIL: kon stub auth.php niet schrijven." . PHP_EOL;
        exit(1);
    }
    $createdStubAuth = true;
}

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}

require __DIR__ . '/../web/api.php';

$passed = 0;
$failed = 0;

function assertEntry(string $label, mixed $expected, mixed $actual): void
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

function assertEntryTrue(string $label, bool $condition): void
{
    assertEntry($label, true, $condition);
}

$requester = 'fr-api-mail@example.com';
$prefPath = getUserPrefsPath($requester);
if ($prefPath !== null && is_file($prefPath)) {
    unlink($prefPath);
}
saveUserPref($requester, 'lang', 'fr');
register_shutdown_function(static function () use ($authPath, $createdStubAuth, $requester): void {
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
    $path = getUserPrefsPath($requester);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }
});

$actionsSource = (string) file_get_contents(__DIR__ . '/../web/content/actions.php');
$apiSource = (string) file_get_contents(__DIR__ . '/../web/api.php');
assertEntryTrue('UI-antwoord gebruikt de gedeelde updatemail', str_contains($actionsSource, 'notifyTicketUpdateToParticipants('));
assertEntryTrue('UI-ticket gebruikt de gedeelde bevestiging', str_contains($actionsSource, 'notifyRequesterOfCreatedTicket('));
assertEntryTrue('API-bericht gebruikt de gedeelde updatemail', str_contains($apiSource, 'notifyTicketUpdateToParticipants('));
assertEntryTrue('API-ticket gebruikt de gedeelde bevestiging', str_contains($apiSource, 'notifyRequesterOfCreatedTicket('));
assertEntryTrue('Publiceren gebruikt dezelfde updatemail', str_contains($apiSource, 'notifyTicketUpdateToParticipants('));

$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_uploads_mail_entry',
    ['ict@kvt.nl'],
    TICKET_CATEGORIES
);
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => null];
$GLOBALS['ictUsers'] = ['ict@kvt.nl'];
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$mailSettings = ['subject_prefix' => '', 'from_email' => 'kvtbot@kvt.nl', 'from_name' => 'KVT Bot'];

$created = $store->createTicket('Scherm zwart', 'hardware bestellen', $requester, 'The screen stays black', [], 0, [], null, 'ict@kvt.nl');
$ticketId = (int) ($created['ticket_id'] ?? 0);
$ticket = $store->getTicket($ticketId, true, 'ict@kvt.nl');
$firstMessageId = firstTicketMessageId(is_array($ticket) ? $ticket : []);
assertEntryTrue('Eerste bericht bestaat', $firstMessageId > 0);
assertEntry('Aanmaken zonder vertaler zet niets in de vertaalwachtrij', [], queuedTicketTranslationKeys());

$calls = [];
$scripted = [];
$transport = static function (string $url, string $jsonBody, float $timeout) use (&$calls, &$scripted): array {
    $calls[] = $jsonBody;
    $next = array_shift($scripted);

    return is_array($next) ? $next : ['status' => 500, 'body' => '', 'error' => 'geen script'];
};
$provider = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => $provider];

echo PHP_EOL . "UI-pad" . PHP_EOL;
$uiMessageId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Please restart the screen');
assertEntryTrue(
    'UI-bericht staat in de vertaalwachtrij',
    in_array('ticket_message:' . $uiMessageId, queuedTicketTranslationKeys(), true)
);
$uiTicket = $store->getTicket($ticketId, true, 'ict@kvt.nl');
notifyTicketUpdateToParticipants(
    $store,
    ['ict@kvt.nl'],
    is_array($uiTicket) ? $uiTicket : [],
    'Please restart the screen',
    $uiMessageId,
    false,
    false,
    false,
    'ingediend',
    'ict@kvt.nl'
);
assertEntry('UI-mail wacht op de vertaling', 0, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertEntry('UI-mail staat in de wachtrij', 1, count($store->listUserUpdateMailQueue($uiMessageId)));
discardQueuedTicketTranslations();

echo PHP_EOL . "API-pad, ook een bot" . PHP_EOL;
$adminClient = ['email' => 'ict@kvt.nl', 'is_admin' => true, 'oid' => 'ict', 'api_key' => 'abc'];
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$apiResponse = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Please restart the printer',
    'sender_email' => 'grok-bot@kvt.nl',
    'sender_name' => 'Metis',
    'sender_title' => 'Assistent',
], $adminClient, true);
assertEntryTrue('API-bericht slaagt', !empty($apiResponse['success']));
$apiMessageId = (int) ($apiResponse['message_id'] ?? 0);
assertEntryTrue(
    'API-bericht staat in de vertaalwachtrij',
    in_array('ticket_message:' . $apiMessageId, queuedTicketTranslationKeys(), true)
);
assertEntry('API-mail wacht op de vertaling', 0, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertEntry('API-mail staat in de wachtrij', 1, count($store->listUserUpdateMailQueue($apiMessageId)));

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
$apiMails = array_values(array_filter(
    $GLOBALS['asclepiusCapturedTicketEmails'],
    static fn(array $mail): bool => in_array($requester, $mail['recipients'] ?? [], true)
        && str_contains((string) ($mail['plain'] ?? ''), "Redémarrez l'imprimante")
));
assertEntry('API-vertaling stuurt de Franse updatemail', 1, count($apiMails));
assertEntry('Frans onderwerp via de API', 'Mise à jour du ticket #' . $ticketId, (string) ($apiMails[0]['subject'] ?? ''));

echo PHP_EOL . "Ghost via de API" . PHP_EOL;
$beforeGhostKeys = queuedTicketTranslationKeys();
$beforeGhostMail = count($store->listUserUpdateMailQueue());
$ghostResponse = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Internal draft for ICT only',
    'ghost' => true,
    'sender_email' => 'grok-bot@kvt.nl',
    'sender_name' => 'Metis',
    'sender_title' => 'Assistent',
], $adminClient, true);
assertEntryTrue('Ghost-bericht slaagt', !empty($ghostResponse['success']));
assertEntryTrue('Ghost blijft verborgen', !empty($ghostResponse['is_ghost']));
$ghostId = (int) ($ghostResponse['message_id'] ?? 0);
assertEntryTrue('Ghost-id aanwezig', $ghostId > 0);
assertEntry(
    'Ghost komt niet in de vertaalwachtrij',
    $beforeGhostKeys,
    queuedTicketTranslationKeys()
);
assertEntry('Ghost mailt niet', $beforeGhostMail, count($store->listUserUpdateMailQueue()));

$published = handlePublishGhostMessageApiAction($store, [
    'message_id' => $ghostId,
    'message_text' => 'The draft is now visible',
], $adminClient, true);
assertEntryTrue('Publiceren slaagt', !empty($published['success']));
assertEntryTrue(
    'Gepubliceerd bericht komt in de vertaalwachtrij',
    in_array('ticket_message:' . $ghostId, queuedTicketTranslationKeys(), true)
);
assertEntry('Mail na publiceren wacht nog', 0, count(array_filter(
    $GLOBALS['asclepiusCapturedTicketEmails'],
    static fn(array $mail): bool => str_contains((string) ($mail['plain'] ?? ''), 'The draft is now visible')
        || str_contains((string) ($mail['plain'] ?? ''), 'Le brouillon')
)));
assertEntry('Gepubliceerde mail staat in de wachtrij', 1, count($store->listUserUpdateMailQueue($ghostId)));

echo PHP_EOL . "Nieuw ticket" . PHP_EOL;
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
notifyRequesterOfCreatedTicket(
    $store,
    ['ict@kvt.nl'],
    is_array($ticket) ? $ticket : [],
    'The screen stays black',
    $firstMessageId,
    'email.intro_created_self',
    'ict@kvt.nl'
);
$createdQueue = $store->listUserUpdateMailQueue($firstMessageId);
assertEntry('Bevestiging wacht op de beschrijving', 1, count($createdQueue));
assertEntry('Bevestiging gebruikt het aanmaak-onderwerp', 'email.subject_created', (string) ($createdQueue[0]['subject_key'] ?? ''));
$store->upsertTextTranslation(
    'ticket_message',
    $firstMessageId,
    $ticketId,
    'fr',
    'en',
    hash('sha256', 'The screen stays black'),
    "L'écran reste noir"
);
dispatchQueuedUserUpdateMails($store, $firstMessageId, time(), ['ict@kvt.nl']);
assertEntry('Bevestiging in het Frans', 1, count($GLOBALS['asclepiusCapturedTicketEmails']));
assertEntry(
    'Frans aanmaak-onderwerp',
    'Le ticket #' . $ticketId . ' a été créé',
    (string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['subject'] ?? '')
);
assertEntryTrue(
    'Franse beschrijving',
    str_contains((string) ($GLOBALS['asclepiusCapturedTicketEmails'][0]['plain'] ?? ''), "L'écran reste noir")
);

echo PHP_EOL;
echo "Geslaagd: {$passed}" . PHP_EOL;
echo "Gefaald: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
