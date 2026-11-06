<?php
/**
 * Antwoord per e-mail op een ticketmail: uitgaande headers, citaat-/handtekeningverwijdering,
 * matching op headers en onderwerp, en het ingest-endpoint (feature-flag, autorisatie,
 * auto-replies, lussen, dubbele Message-ID's).
 */

echo "=== TEST: antwoord per e-mail op ticketmail ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/api.php';
$_SERVER['HTTP_HOST'] = 'localhost';
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
        . "\$ictUsers = ['ict@kvt.nl', 'colleague@kvt.nl'];\n"
        . "\$ictUserColors = [];\n"
        . "\$mailSettings = ['subject_prefix' => 'ICT Tickets', 'from_email' => 'kvtbot@kvt.nl', 'from_name' => 'KVT Bot'];\n"
        . "\$grokBot = ['enabled' => false];\n"
    );
    if ($written === false) {
        echo "FAIL: kon stub auth.php niet schrijven." . PHP_EOL;
        exit(1);
    }
    $createdStubAuth = true;
}

register_shutdown_function(static function () use ($authPath, $createdStubAuth): void {
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
});

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}

require __DIR__ . '/../web/api.php';

$passed = 0;
$failed = 0;

function assertSame(string $label, mixed $expected, mixed $actual): void
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

function assertTrue(string $label, bool $actual): void
{
    assertSame($label, true, $actual);
}

$GLOBALS['ictUsers'] = ['ict@kvt.nl', 'colleague@kvt.nl'];
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => null];

echo '--- Citaten en handtekening: Outlook (NL, HTML) ---' . PHP_EOL;
$outlookNl = '<html><head><style>p.MsoNormal{margin:0}</style></head><body><div class="WordSection1">'
    . '<p class="MsoNormal">Hoi,</p><p class="MsoNormal">Het is gelukt, bedankt!</p><p class="MsoNormal">&nbsp;</p>'
    . '<p class="MsoNormal">Met vriendelijke groet,</p><p class="MsoNormal">Piet Jansen<br>Werkplaats</p>'
    . '<div><div style="border:none;border-top:solid #E1E1E1 1.0pt;padding:3.0pt 0cm 0cm 0cm"><p class="MsoNormal"><b>Van:</b> KVT Bot &lt;kvtbot@kvt.nl&gt;<br>'
    . '<b>Verzonden:</b> vrijdag 24 juli 2026 10:12<br><b>Aan:</b> Piet Jansen<br><b>Onderwerp:</b> ICT Tickets - Update op ticket #42</p></div></div>'
    . '<p>Ticket #42 is bijgewerkt: probeer het opnieuw.</p></div></body></html>';
assertSame(
    'Outlook desktop: antwoord zonder groet en citaat',
    "Hoi,\n\nHet is gelukt, bedankt!",
    extractMailReplyText('', $outlookNl)
);

echo PHP_EOL . '--- Citaten en handtekening: Outlook web (HTML) ---' . PHP_EOL;
$outlookWeb = '<html><body><div dir="ltr">Kan iemand vanmiddag langskomen?</div>'
    . '<div id="Signature"><div>Groeten, Sanne</div></div><div id="appendonsend"></div><hr style="display:inline-block;width:98%">'
    . '<div id="divRplyFwdMsg" dir="ltr"><b>From:</b> KVT Bot<br><b>Sent:</b> Friday</div><div>Oude tekst</div></body></html>';
assertSame('Outlook web: alleen nieuwe tekst', 'Kan iemand vanmiddag langskomen?', extractMailReplyText('', $outlookWeb));

echo PHP_EOL . '--- Citaten: Gmail en Apple Mail (HTML) ---' . PHP_EOL;
$gmail = '<div dir="ltr">Dank je, werkt weer.<br>Zie <a href="https://kvt.nl/hulp">de handleiding</a></div><br>'
    . '<div class="gmail_quote gmail_quote_container"><div dir="ltr" class="gmail_attr">Op vr 24 jul 2026 om 10:12 schreef KVT Bot &lt;<a href="mailto:kvtbot@kvt.nl">kvtbot@kvt.nl</a>&gt;:<br></div>'
    . '<blockquote class="gmail_quote" style="margin:0px 0px 0px 0.8ex"><div>Oude tekst</div></blockquote></div>';
assertSame(
    'Gmail: tekst met markdownlink, zonder citaat',
    "Dank je, werkt weer.\nZie [de handleiding](https://kvt.nl/hulp)",
    extractMailReplyText('', $gmail)
);
$apple = '<html><body><div>Prima, graag.</div><div><br><blockquote type="cite"><div>On 24 Jul 2026, at 10:12, KVT Bot wrote:</div><div>Oude tekst</div></blockquote></div></body></html>';
assertSame('Apple Mail: blockquote weg', 'Prima, graag.', extractMailReplyText('', $apple));

echo PHP_EOL . '--- Citaten en handtekening: platte tekst ---' . PHP_EOL;
$cases = [
    'Outlook NL Van/Verzonden' => ["Het werkt weer.\n\nVan: KVT Bot <kvtbot@kvt.nl>\nVerzonden: vrijdag 24 juli 2026 10:12\nAan: Piet\nOnderwerp: ICT Tickets - Update op ticket #42\n\nOude tekst", 'Het werkt weer.'],
    'Outlook EN Original Message' => ["Thanks, fixed.\n\n-----Original Message-----\nFrom: KVT Bot\nSent: Friday\nSubject: x\n\nOld text", 'Thanks, fixed.'],
    'Outlook NL Oorspronkelijk bericht' => ["Klaar.\n\n-----Oorspronkelijk bericht-----\nVan: KVT Bot\n\nOud", 'Klaar.'],
    'Outlook underscore-scheiding' => ["Graag meer info.\n\n________________________________\nFrom: KVT Bot <kvtbot@kvt.nl>\nSent: Friday\nTo: Piet\nSubject: x\n\nOld", 'Graag meer info.'],
    'Gmail EN wrote (omgebroken)' => ["Works now.\n\nOn Fri, Jul 24, 2026 at 10:12 AM KVT Bot\n<kvtbot@kvt.nl> wrote:\n> Old text", 'Works now.'],
    'Gmail NL schreef' => ["Werkt weer.\n\nOp vr 24 jul 2026 om 10:12 schreef KVT Bot <kvtbot@kvt.nl>:\n> Oud", 'Werkt weer.'],
    'Apple Mail DE schrieb' => ["Funktioniert wieder.\n\nAm 24.07.2026 um 10:12 schrieb KVT Bot <kvtbot@kvt.nl>:\n\nAlt", 'Funktioniert wieder.'],
    'Handtekening met -- ' => ["Zie bijlage.\n-- \nPiet Jansen\nKVT Werkplaats\n+31 6 12345678", 'Zie bijlage.'],
    'Groet en handtekening' => ["Zie hierboven.\n\nMet vriendelijke groet,\n\nPiet Jansen\nTeamleider\nKoninklijke van Twist", 'Zie hierboven.'],
    'Mobiele footer' => ["Prima!\n\nVerzonden vanaf mijn iPhone", 'Prima!'],
    'Outlook mobiel footer' => ["Ok, bedankt\n\nGet Outlook for iOS<https://aka.ms/o0ukef>", 'Ok, bedankt'],
    'Quote-prefix >' => ["Ja\n> Oude tekst\n> meer", 'Ja'],
    'Groet midden in bericht blijft' => ["Groet aan jullie team.\nIk kan morgen niet.\nTot gauw!", "Groet aan jullie team.\nIk kan morgen niet.\nTot gauw!"],
    'From in lopende tekst blijft' => ["Het bericht from: Piet kwam niet aan.\nWil je kijken?", "Het bericht from: Piet kwam niet aan.\nWil je kijken?"],
    'Alleen citaat geeft leeg' => ["-----Original Message-----\nFrom: KVT Bot\nSent: x\n\nOld", ''],
];
foreach ($cases as $label => [$input, $expected]) {
    assertSame($label, $expected, extractMailReplyText($input));
}

echo PHP_EOL . '--- Matching: Message-ID en onderwerp ---' . PHP_EOL;
$lookup = static fn(array $ids): int => in_array('abc123@kvt.nl', $ids, true) ? 7 : 0;
assertSame('Opgeslagen Message-ID wint van onderwerp', ['ticket_id' => 7, 'via' => 'header'], matchMailToTicketId(['abc123@kvt.nl'], 'RE: Update op ticket #99', $lookup));
assertSame('Ingebakken ticketnummer in Message-ID', ['ticket_id' => 55, 'via' => 'header'], matchMailToTicketId(['asclepius-t55-0123abcd@kvt.nl'], 'RE: iets anders', $lookup));
assertSame('Onderwerp als terugval (NL)', ['ticket_id' => 42, 'via' => 'subject'], matchMailToTicketId([], 'RE: ICT Tickets - Update op ticket #42', $lookup));
assertSame('Onderwerp als terugval (alleen #)', ['ticket_id' => 12, 'via' => 'subject'], matchMailToTicketId(['onbekend@x.nl'], 'AW: Nutzerantwort auf Ticket #12', $lookup));
assertSame('Geen match', ['ticket_id' => 0, 'via' => 'none'], matchMailToTicketId([], 'Hallo', $lookup));
assertSame('References tekst wordt geparsed', ['a@x', 'b@y'], parseMailMessageIdList('<A@x>  <b@y>'));

echo PHP_EOL . '--- Uitgaande mail: Message-ID, In-Reply-To, References, Reply-To ---' . PHP_EOL;
$uploadRoot = sys_get_temp_dir() . '/asclepius_test_uploads_mail_reply_' . getmypid();
$store = new TicketStore(':memory:', $uploadRoot, ['ict@kvt.nl', 'colleague@kvt.nl'], TICKET_CATEGORIES);
$requester = 'piet@kvt.nl';
$participant = 'sanne@kvt.nl';
$stranger = 'iemand@extern.nl';
$ticketId = (int) $store->createTicket('Printer stuk', 'Anders', $requester, 'De printer doet het niet', [], 0, [], null, 'ict@kvt.nl')['ticket_id'];
$otherTicketId = (int) $store->createTicket('Ander ticket', 'Anders', 'jan@kvt.nl', 'Iets anders', [], 0, [], null, 'ict@kvt.nl')['ticket_id'];
$store->addTicketParticipants($ticketId, [$participant], 'ict@kvt.nl');

$mailSettings = ['subject_prefix' => 'ICT Tickets', 'from_email' => 'kvtbot@kvt.nl', 'from_name' => 'KVT Bot'];
$mailReplyIngest = ['enabled' => true, 'mailbox' => 'ict@kvt.nl', 'ticket_ids' => [$ticketId]];
$GLOBALS['asclepiusCapturedTicketEmails'] = [];

sendTicketNotification($store, $GLOBALS['ictUsers'], [$requester], 'Update op ticket #' . $ticketId, 'Eerste update', null, null, $ticketId);
sendTicketNotification($store, $GLOBALS['ictUsers'], [$requester], 'Update op ticket #' . $ticketId, 'Tweede update', null, null, $ticketId);
sendTicketNotification($store, $GLOBALS['ictUsers'], ['jan@kvt.nl'], 'Update op ticket #' . $otherTicketId, 'Ander ticket', null, null, $otherTicketId);
[$first, $second, $other] = $GLOBALS['asclepiusCapturedTicketEmails'];
assertTrue('Message-ID bevat ticketnummer', preg_match('/^asclepius-t' . $ticketId . '-[0-9a-f]+@kvt\.nl$/', (string) $first['thread']['message_id']) === 1);
assertSame('Eerste mail heeft geen In-Reply-To', false, isset($first['thread']['in_reply_to']));
assertSame('Tweede mail verwijst naar eerste', $first['thread']['message_id'], $second['thread']['in_reply_to'] ?? null);
assertSame('Tweede mail heeft References', [$first['thread']['message_id']], $second['thread']['references'] ?? null);
assertSame('Reply-To naar mailbox bij ingeschakeld ticket', 'ict@kvt.nl', $second['thread']['reply_to'] ?? null);
assertSame('Geen Reply-To bij ticket buiten de testlijst', false, isset($other['thread']['reply_to']));
assertTrue('Message-ID staat in de database', $store->findTicketIdByMailMessageIds([normalizeMailMessageId($first['thread']['message_id'])]) === $ticketId);
[$mimeHeaders] = buildMimeParts('kvtbot@kvt.nl', 'KVT Bot', [$requester], 'x', 'plain', null, false, $second['thread']);
assertTrue('MIME-headers bevatten Message-ID, In-Reply-To en Reply-To', str_contains($mimeHeaders, 'Message-ID: <' . $second['thread']['message_id'] . '>')
    && str_contains($mimeHeaders, 'In-Reply-To: <' . $first['thread']['message_id'] . '>')
    && str_contains($mimeHeaders, 'References: <' . $first['thread']['message_id'] . '>')
    && str_contains($mimeHeaders, 'Reply-To: ict@kvt.nl'));
[$legacyHeaders] = buildMimeParts('kvtbot@kvt.nl', 'KVT Bot', [$requester], 'x', 'plain', null);
assertTrue('Zonder thread blijft de oude Message-ID', preg_match('/Message-ID: <ticket-[^>]+@kvt\.nl>/', $legacyHeaders) === 1 && !str_contains($legacyHeaders, 'Reply-To'));

echo PHP_EOL . '--- Ingest: antwoord wordt gebruikersbericht ---' . PHP_EOL;
function ingestMail(TicketStore $store, array $overrides = [], bool $service = true): array
{
    static $counter = 0;
    $counter++;

    return handleIngestTicketReplyMailApiAction($store, $overrides + [
        'message_id' => '<reply-' . $counter . '@kvt.nl>',
        'in_reply_to' => '',
        'subject' => 'RE: ICT Tickets - Update op ticket #' . $GLOBALS['ticketId'],
        'from_email' => 'piet@kvt.nl',
        'body_text' => "Het werkt weer, bedankt!\n\nVan: KVT Bot <kvtbot@kvt.nl>\nVerzonden: vrijdag\nAan: Piet\nOnderwerp: x\n\nOude tekst",
    ], $service);
}

function ticketMessages(TicketStore $store, int $ticketId): array
{
    return $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', true)['messages'] ?? [];
}

$GLOBALS['ticketId'] = $ticketId;
$store->updateTicket($ticketId, 'afwachtende op gebruiker', null, 0, null);
$messagesBefore = count(ticketMessages($store, $ticketId));
$GLOBALS['asclepiusCapturedTicketEmails'] = [];

$viaHeader = ingestMail($store, ['subject' => 'Geen nummer', 'in_reply_to' => '<' . $first['thread']['message_id'] . '>']);
assertTrue('Header-match slaagt', !empty($viaHeader['ingested']));
assertSame('Gematcht via header', 'header', $viaHeader['matched_via'] ?? null);
$messages = ticketMessages($store, $ticketId);
$posted = end($messages);
assertSame('Eén bericht erbij', $messagesBefore + 1, count($messages));
assertSame('Tekst zonder citaat', 'Het werkt weer, bedankt!', $posted['message_text'] ?? null);
assertSame('sender_role=user', 'user', $posted['sender_role'] ?? null);
assertSame('Afzender is de mailafzender', $requester, strtolower((string) ($posted['sender_email'] ?? '')));
assertSame('Status van wachten op gebruiker naar in behandeling', 'in behandeling', $store->getTicket($ticketId, true, 'ict@kvt.nl')['status'] ?? null);
$recipients = array_merge(...array_column($GLOBALS['asclepiusCapturedTicketEmails'], 'recipients') ?: [[]]);
assertTrue('Behandelaar krijgt melding, gedeelde mailbox niet', !in_array('ict@kvt.nl', $recipients, true) && in_array('colleague@kvt.nl', $recipients, true));

$viaSubject = ingestMail($store, ['from_email' => strtoupper($participant)]);
assertTrue('Onderwerp-terugval en deelnemer slagen', !empty($viaSubject['ingested']) && ($viaSubject['matched_via'] ?? '') === 'subject');
$messages = ticketMessages($store, $ticketId);
assertSame('Deelnemer als gebruiker', ['user', $participant], [end($messages)['sender_role'] ?? null, strtolower((string) (end($messages)['sender_email'] ?? ''))]);

$htmlMail = ingestMail($store, ['body_text' => '', 'body_html' => $outlookNl]);
assertTrue('HTML-body wordt opgeslagen', !empty($htmlMail['ingested']));
$messages = ticketMessages($store, $ticketId);
assertSame('HTML-tekst opgeslagen', "Hoi,\n\nHet is gelukt, bedankt!", end($messages)['message_text'] ?? null);

echo PHP_EOL . '--- Ingest: afwijzen en negeren ---' . PHP_EOL;
$count = count(ticketMessages($store, $ticketId));
$notService = ingestMail($store, [], false);
assertSame('Zonder service-key geweigerd', 'forbidden', $notService['error_code'] ?? null);
$unauthorized = ingestMail($store, ['from_email' => $stranger]);
assertSame('Vreemde afzender genegeerd', 'sender_not_on_ticket', $unauthorized['reason'] ?? null);
$spoofLookalike = ingestMail($store, ['from_email' => 'jan@kvt.nl']);
assertSame('Aanvrager van ander ticket genegeerd', 'sender_not_on_ticket', $spoofLookalike['reason'] ?? null);
assertSame('Auto-reply (header)', 'auto_reply', ingestMail($store, ['headers' => ['Auto-Submitted' => 'auto-replied']])['reason'] ?? null);
assertSame('Auto-reply (Graph-headerlijst)', 'auto_reply', ingestMail($store, ['headers' => [['name' => 'Precedence', 'value' => 'bulk']]])['reason'] ?? null);
assertSame('Auto-reply (onderwerp)', 'auto_reply', ingestMail($store, ['subject' => 'Automatisch antwoord: ticket #' . $ticketId])['reason'] ?? null);
assertSame('NDR (mailer-daemon)', 'automatic_sender', ingestMail($store, ['from_email' => 'MAILER-DAEMON@kvt.nl'])['reason'] ?? null);
assertSame('Lus: mail van de gedeelde mailbox', 'loop', ingestMail($store, ['from_email' => 'ict@kvt.nl'])['reason'] ?? null);
assertSame('Lus: mail van kvtbot', 'loop', ingestMail($store, ['from_email' => 'kvtbot@kvt.nl'])['reason'] ?? null);
assertSame('Lus: eigen Message-ID', 'loop', ingestMail($store, ['message_id' => '<' . $first['thread']['message_id'] . '>'])['reason'] ?? null);
assertSame('Ticket buiten de testlijst', 'ticket_not_enabled', ingestMail($store, ['subject' => 'RE: ticket #' . $otherTicketId, 'from_email' => 'jan@kvt.nl'])['reason'] ?? null);
assertSame('Geen ticket gevonden', 'no_ticket_match', ingestMail($store, ['subject' => 'Hallo'])['reason'] ?? null);
assertSame('Alleen citaat is een lege body', 'empty_body', ingestMail($store, ['body_text' => "-----Original Message-----\nFrom: x\nSent: y\n\nOud"])['reason'] ?? null);
assertSame('Ontbrekende Message-ID is ongeldig', 'invalid_payload', ingestMail($store, ['message_id' => ''])['error_code'] ?? null);
assertSame('Ongeldig afzenderadres is ongeldig', 'invalid_payload', ingestMail($store, ['from_email' => 'geen-adres'])['error_code'] ?? null);
assertSame('Geen bericht erbij bij al het bovenstaande', $count, count(ticketMessages($store, $ticketId)));

assertSame('Geweigerde mail', 'sender_not_on_ticket', ingestMail($store, ['from_email' => $stranger, 'message_id' => '<retry-1@x>'])['reason'] ?? null);
assertTrue('Geweigerde Message-ID is niet als verwerkt onthouden', !empty(ingestMail($store, ['message_id' => '<retry-1@x>'])['ingested']));
$count++;

echo PHP_EOL . '--- Ingest: dubbele Message-ID is idempotent ---' . PHP_EOL;
$dupe = ['message_id' => '<Dupe-1@Kvt.NL>'];
$firstTry = ingestMail($store, $dupe);
$secondTry = ingestMail($store, ['message_id' => 'dupe-1@kvt.nl']);
assertTrue('Eerste keer opgeslagen', !empty($firstTry['ingested']));
assertSame('Tweede keer genegeerd als duplicate', 'duplicate', $secondTry['reason'] ?? null);
assertSame('Slechts één extra bericht', $count + 1, count(ticketMessages($store, $ticketId)));

echo PHP_EOL . '--- Feature-flag ---' . PHP_EOL;
$mailReplyIngest = ['enabled' => true, 'mailbox' => 'ict@kvt.nl'];
assertTrue('Zonder ticket_ids geldt de vlag voor elk ticket', mailReplyIngestEnabledForTicket($otherTicketId));
$mailReplyIngest = ['enabled' => 'true', 'mailbox' => 'ict@kvt.nl'];
assertSame('Alleen echte true schakelt in', 'ingest_disabled', ingestMail($store)['reason'] ?? null);
$mailReplyIngest = [];
assertSame('Standaard uit', 'ingest_disabled', ingestMail($store)['reason'] ?? null);
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
sendTicketNotification($store, $GLOBALS['ictUsers'], [$requester], 'Update', 'Uit', null, null, $ticketId);
assertSame('Uitgeschakeld: geen Reply-To', false, isset($GLOBALS['asclepiusCapturedTicketEmails'][0]['thread']['reply_to']));

echo PHP_EOL . '--- Router en documentatie ---' . PHP_EOL;
$apiSource = (string) file_get_contents(__DIR__ . '/../web/api.php');
$docs = (string) file_get_contents(__DIR__ . '/../web/docs/api.md');
assertTrue('Router kent ingest_ticket_reply_mail', str_contains($apiSource, "\$action === 'ingest_ticket_reply_mail'"));
assertTrue('docs/api.md beschrijft de actie', str_contains($docs, '`ingest_ticket_reply_mail`'));
assertTrue('docs/api.md beschrijft de feature-flag', str_contains($docs, '$mailReplyIngest'));
assertTrue('auth_TEMPLATE.php beschrijft de feature-flag', str_contains((string) file_get_contents(__DIR__ . '/../web/auth_TEMPLATE.php'), '$mailReplyIngest'));

echo PHP_EOL . "Geslaagd: {$passed}" . PHP_EOL . "Gefaald : {$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
