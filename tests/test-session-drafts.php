<?php

/**
 * Bewaarde concepten bij een verlopen sessie horen bij de gebruiker die ze
 * typte. De eigenaar-sleutel is een HMAC van het e-mailadres; het adres zelf
 * komt niet in de browser. Een andere login in hetzelfde tabblad gooit de
 * concepten weg. Het JS-deel draait met node als dat beschikbaar is.
 */

echo "=== TEST: concepten per gebruiker ===" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

/**
 * Telt en toont één controle.
 */
function draftAssert(string $label, bool $actual): void
{
    global $passed, $failed;
    if ($actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "  ✗ {$label}" . PHP_EOL;
    $failed++;
}

require_once __DIR__ . '/../web/content/session_keepalive.php';
require_once __DIR__ . '/../web/content/constants.php';

echo "Eigenaar-sleutel" . PHP_EOL;

$tmpDir = sys_get_temp_dir() . '/asclepius-drafts-' . bin2hex(random_bytes(4));
$secretFile = $tmpDir . '/data/session_draft_secret.php';
register_shutdown_function(static function () use ($tmpDir, $secretFile): void {
    @unlink($secretFile);
    @rmdir(dirname($secretFile));
    @rmdir($tmpDir);
});

$keyA = asclepiusSessionDraftOwnerKey('Ict@KVT.nl ', $secretFile);
$keyA2 = asclepiusSessionDraftOwnerKey('ict@kvt.nl', $secretFile);
$keyB = asclepiusSessionDraftOwnerKey('iemand.anders@kvt.nl', $secretFile);

draftAssert('sleutel is 64 hex-tekens', preg_match('/^[a-f0-9]{64}$/', $keyA) === 1);
draftAssert('zelfde adres (hoofdletters/spaties) geeft zelfde sleutel', $keyA === $keyA2);
draftAssert('andere gebruiker geeft andere sleutel', $keyA !== $keyB);
draftAssert('sleutel bevat het e-mailadres niet', !str_contains($keyA, 'ict') && !str_contains($keyA, 'kvt'));
draftAssert('sleutel is geen kale sha256 van het adres', $keyA !== hash('sha256', 'ict@kvt.nl'));
draftAssert('leeg adres geeft lege sleutel', asclepiusSessionDraftOwnerKey('', $secretFile) === '');
draftAssert('ongeldig adres geeft lege sleutel', asclepiusSessionDraftOwnerKey('geen-adres', $secretFile) === '');

draftAssert('geheim wordt aangemaakt', is_file($secretFile));
$secretRaw = is_file($secretFile) ? (string) file_get_contents($secretFile) : '';
draftAssert('geheimbestand stopt meteen als PHP', str_starts_with($secretRaw, '<?php') && str_contains($secretRaw, 'exit;'));
$includeOutput = '';
if (is_file($secretFile)) {
    $includeOutput = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($secretFile) . ' 2>&1');
}
draftAssert('uitvoeren van het geheimbestand toont niets', trim($includeOutput) === '');

$sameAfterReload = (string) shell_exec(
    escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg(
        'require ' . var_export(realpath(__DIR__ . '/../web/content/session_keepalive.php'), true) . ';'
        . 'echo asclepiusSessionDraftOwnerKey("ict@kvt.nl", ' . var_export($secretFile, true) . ');'
    )
);
draftAssert('sleutel blijft gelijk in een nieuw proces (na opnieuw inloggen)', trim($sameAfterReload) === $keyA);

$otherSecret = $tmpDir . '/other/session_draft_secret.php';
$keyOtherInstall = asclepiusSessionDraftOwnerKey('ict@kvt.nl', $otherSecret);
draftAssert('ander geheim geeft andere sleutel', $keyOtherInstall !== '' && $keyOtherInstall !== $keyA);
@unlink($otherSecret);
@rmdir(dirname($otherSecret));

draftAssert('geheim staat binnen web/data', str_contains(str_replace('\\', '/', SESSION_DRAFT_SECRET_FILE), '/web/content/../data/'));
$gitignore = (string) file_get_contents(__DIR__ . '/../.gitignore');
draftAssert('web/data staat in .gitignore', str_contains($gitignore, 'web/data/'));

$indexSource = (string) file_get_contents(__DIR__ . '/../web/index.php');
draftAssert('index.php zet data-session-draft-owner', str_contains($indexSource, 'data-session-draft-owner="<?= h(asclepiusSessionDraftOwnerKey('));

echo PHP_EOL . "Opslaan en terugzetten in de browser" . PHP_EOL;

$node = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($node === '') {
    echo "  - node ontbreekt; JS-controle overgeslagen" . PHP_EOL;
} else {
    $pageJs = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
    $start = strpos($pageJs, '        var cssAttributeValue = function (value)');
    $end = strpos($pageJs, '        var formatSessionExpiredNoticed = function (date)');
    draftAssert('conceptfuncties gevonden in page_js.php', $start !== false && $end !== false && $end > $start);
    if ($start !== false && $end !== false && $end > $start) {
        $harnessPre = <<<'JS'
function mkStorage(){var m={};return {getItem:k=>k in m?m[k]:null,setItem:(k,v)=>{m[k]=String(v)},removeItem:k=>{delete m[k]},_m:m};}
var sessionStorage=mkStorage();
var sessionDraftStorageKey='asclepius_unsaved_form_drafts';
var sessionDraftOwner='';
function Event(t,o){this.type=t;}
function field(name,id,value){return {name:name,id:id,value:value,disabled:false,dispatchEvent:function(){}};}
var fields=[]; 
var form={querySelector:function(sel){return sel.indexOf('csrf_token')>=0?{}:null;},querySelectorAll:function(){return fields;},closest:function(){return null;}};
var document={querySelectorAll:function(sel){return sel==='form'?[form]:[];},getElementById:function(id){return fields.find(f=>f.id===id)||null;},querySelector:function(sel){var m=sel.match(/name="([^"]+)"/);return m?fields.find(f=>f.name===m[1])||null:null;}};
JS;
        $harnessPost = <<<'JS'
function check(label,cond){console.log((cond?'OK  ':'FAIL')+' '+label); if(!cond) process.exitCode=1;}
// user A types and session expires
sessionDraftOwner='aaaa';
fields=[field('title','t','Printer kapot'),field('participant_emails','p','bob@kvt.nl')];
var saved=saveSessionDrafts();
check('A: 2 concepten bewaard',saved.length===2);
var raw=sessionStorage.getItem(sessionDraftStorageKey);
check('opslag bevat eigenaar-hash',JSON.parse(raw).owner==='aaaa');
check('geen e-mail van gebruiker A in opslag (alleen veldwaarde)',raw.indexOf('a@kvt.nl')<0);
// user B logs in same tab
sessionDraftOwner='bbbb';
fields=[field('title','t',''),field('participant_emails','p','')];
restoreSessionDrafts();
check('B krijgt niets terug',fields.every(f=>f.value===''));
check('A-concepten weggegooid',sessionStorage.getItem(sessionDraftStorageKey)===null);
// A again: save and restore same user
sessionDraftOwner='aaaa';
fields=[field('title','t','Printer kapot')]; saveSessionDrafts();
fields=[field('title','t','')]; restoreSessionDrafts();
check('A krijgt eigen concept terug',fields[0].value==='Printer kapot');
check('opslag leeg na terugzetten',sessionStorage.getItem(sessionDraftStorageKey)===null);
// remaining (field not on page) kept for same owner
fields=[field('title','t','x'),field('message','m','reply')]; saveSessionDrafts();
fields=[field('title','t','')]; restoreSessionDrafts();
check('niet-gevonden veld blijft bewaard met eigenaar',JSON.parse(sessionStorage.getItem(sessionDraftStorageKey)).owner==='aaaa' && JSON.parse(sessionStorage.getItem(sessionDraftStorageKey)).drafts.length===1);
// legacy array format discarded
sessionStorage.setItem(sessionDraftStorageKey,JSON.stringify([{name:'title',value:'oud'}]));
fields=[field('title','t','')]; restoreSessionDrafts();
check('oud formaat zonder eigenaar wordt niet teruggezet',fields[0].value==='' && sessionStorage.getItem(sessionDraftStorageKey)===null);
// unknown current user: nothing saved
sessionDraftOwner=''; fields=[field('title','t','geheim')];
check('zonder eigenaar niets bewaard',saveSessionDrafts().length===0 && sessionStorage.getItem(sessionDraftStorageKey)===null);
// unknown user on restore: keep but don't restore
sessionStorage.setItem(sessionDraftStorageKey,JSON.stringify({owner:'aaaa',drafts:[{name:'title',id:'t',value:'v'}]}));
fields=[field('title','t','')]; restoreSessionDrafts();
check('onbekende gebruiker: niet terugzetten',fields[0].value==='' && sessionStorage.getItem(sessionDraftStorageKey)!==null);
// storage throws
sessionDraftOwner='aaaa'; var orig=sessionStorage.setItem; sessionStorage.setItem=function(){throw new Error('quota')};
fields=[field('title','t','x')];
check('opslag vol: geen belofte',saveSessionDrafts().length===0);
JS;
        $script = $harnessPre . "\n" . substr($pageJs, $start, $end - $start) . "\n" . $harnessPost;
        $jsFile = $tmpDir . '/drafts-harness.js';
        file_put_contents($jsFile, $script);
        $out = [];
        $code = 0;
        exec(escapeshellarg($node) . ' ' . escapeshellarg($jsFile) . ' 2>&1', $out, $code);
        @unlink($jsFile);
        foreach ($out as $line) {
            if (str_starts_with($line, 'OK ')) {
                draftAssert(trim(substr($line, 3)), true);
            } elseif (str_starts_with($line, 'FAIL ')) {
                draftAssert(trim(substr($line, 5)), false);
            } else {
                echo "    {$line}" . PHP_EOL;
            }
        }
        draftAssert('JS-controle eindigt zonder fout', $code === 0);
    }
}

echo PHP_EOL;
if ($failed === 0) {
    echo "Alle concepttests geslaagd ({$passed})." . PHP_EOL;
    exit(0);
}

echo "{$failed} concepttest(s) gefaald, {$passed} geslaagd." . PHP_EOL;
exit(1);
