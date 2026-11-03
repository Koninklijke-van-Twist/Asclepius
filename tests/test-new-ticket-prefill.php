<?php
// Test: 'Nieuw ticket' vooraf invullen via GET (getNewTicketPrefill) + escaping in de view.
$src = file_get_contents(__DIR__ . '/../web/content/helpers.php');
preg_match('/function getSelectableTicketCategories\(\).*?\r?\n}\r?\n/s', $src, $a);
preg_match('/function getNewTicketPrefill\(.*?\r?\n}\r?\n/s', $src, $b);
const TICKET_CATEGORIES = ['Business Central', 'AFAS', 'Sjabloon'];
function isTemplateTicketCategory(string $c): bool { return $c === 'Sjabloon'; }
eval($a[0] . $b[0]);
$fail = 0;
$check = function (bool $ok, string $msg) use (&$fail) { echo ($ok ? 'OK   ' : 'FAIL ') . $msg . PHP_EOL; if (!$ok) { $fail++; } };
$p = getNewTicketPrefill(['new_category' => 'business central', 'new_title' => "A\nB", 'new_description' => "| a |\r\n|---|"]);
$check($p['category'] === 'Business Central', 'categorie case-insensitive');
$check($p['title'] === 'A B', 'titel zonder regeleinde');
$check($p['description'] === "| a |\n|---|", 'body regeleinden genormaliseerd');
$check(getNewTicketPrefill(['new_category' => 'Onbekend'])['category'] === '', 'onbekende categorie leeg');
$check(getNewTicketPrefill(['new_category' => 'sjabloon'])['category'] === '', 'sjablooncategorie niet');
$check(getNewTicketPrefill(['new_title' => ['x']])['title'] === '', 'array genegeerd');
$check(mb_strlen(getNewTicketPrefill(['new_title' => str_repeat('é', 500)])['title']) === 150, 'titel begrensd');
$check(mb_strlen(getNewTicketPrefill(['new_description' => str_repeat('x', 30000)])['description']) === 20000, 'body begrensd');
$check(getNewTicketPrefill([]) === ['category' => '', 'title' => '', 'description' => ''], 'leeg zonder params');
$view = file_get_contents(__DIR__ . '/../web/content/views/view_new_ticket.php');
$check(str_contains($view, "<?= h(\$newTicketPrefill['title']) ?>") && str_contains($view, "<?= h(\$newTicketPrefill['description']) ?>"), 'view escapet met h()');
exit($fail > 0 ? 1 : 0);
