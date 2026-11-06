<?php
/**
 * De trendgrafiek op ICT-stats tekent elke reeks als SVG-pad.
 * De kromme moet door dezelfde meetpunten lopen, gaten door null laten
 * vallen, en niet voorbij een piek, dal of de nullijn (yMax) schieten.
 */

echo "=== TEST: vloeiende trendlijnen ===" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }
    echo "  ✗ {$label}" . PHP_EOL;
    if ($detail !== '') {
        echo "      {$detail}" . PHP_EOL;
    }
    $failed++;
}

$pageJs = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
$startMarker = '// ASCLEPIUS-TREND-SMOOTH-START';
$endMarker = '// ASCLEPIUS-TREND-SMOOTH-END';
$start = strpos($pageJs, $startMarker);
$end = strpos($pageJs, $endMarker);
check('kromme-functie gevonden in page_js.php', $start !== false && $end !== false && $end > $start);
$nullPos = strpos($pageJs, 'point === null || point === undefined');
$callPos = strpos($pageJs, 'trendSmoothPath(chunk, padding.top, padding.top + plotHeight)');
check('null blijft een gat vóór de kromme', $nullPos !== false && $callPos !== false && $nullPos < $callPos);
check('rechte L-segmenten tussen meetpunten zijn weg', !str_contains($pageJs, "(index === 0 ? 'M' : 'L')"));

$nodeBin = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($nodeBin === '' || $start === false || $end === false || $end <= $start) {
    echo "  (node of functie ontbreekt; kromme-cases niet gedraaid)" . PHP_EOL;
    echo PHP_EOL . "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
    exit($failed > 0 ? 1 : 0);
}

$block = substr($pageJs, $start, $end - $start);
check('kromme-blok bevat geen PHP', !str_contains($block, '<?'));

$script = $block . <<<'JS'

function parsePath(d) {
    var tokens = String(d || '').match(/[MLC]|-?\d+(?:\.\d+)?/g) || [];
    var i = 0;
    var x = 0;
    var y = 0;
    var segs = [];
    while (i < tokens.length) {
        var cmd = tokens[i++];
        if (cmd === 'M' || cmd === 'L') {
            x = Number(tokens[i++]);
            y = Number(tokens[i++]);
            segs.push({ cmd: cmd, x: x, y: y });
        } else if (cmd === 'C') {
            var c1x = Number(tokens[i++]);
            var c1y = Number(tokens[i++]);
            var c2x = Number(tokens[i++]);
            var c2y = Number(tokens[i++]);
            x = Number(tokens[i++]);
            y = Number(tokens[i++]);
            segs.push({ cmd: 'C', c1x: c1x, c1y: c1y, c2x: c2x, c2y: c2y, x: x, y: y });
        } else {
            return null;
        }
    }
    return segs;
}

function bezier(a, b, c, d, t) {
    var u = 1 - t;
    return (u * u * u * a) + (3 * u * u * t * b) + (3 * u * t * t * c) + (t * t * t * d);
}

function samplesOf(d, steps) {
    var segs = parsePath(d);
    if (!segs) {
        return null;
    }
    var pts = [];
    var x = 0;
    var y = 0;
    segs.forEach(function (seg) {
        if (seg.cmd === 'M') {
            x = seg.x;
            y = seg.y;
            pts.push({ x: x, y: y, y0: y, y1: y });
            return;
        }
        var y0 = y;
        var x0 = x;
        if (seg.cmd === 'L') {
            for (var s = 1; s <= steps; s++) {
                var t = s / steps;
                pts.push({
                    x: x0 + (seg.x - x0) * t,
                    y: y0 + (seg.y - y0) * t,
                    y0: Math.min(y0, seg.y),
                    y1: Math.max(y0, seg.y)
                });
            }
        } else {
            for (var s = 1; s <= steps; s++) {
                var t = s / steps;
                pts.push({
                    x: bezier(x0, seg.c1x, seg.c2x, seg.x, t),
                    y: bezier(y0, seg.c1y, seg.c2y, seg.y, t),
                    y0: Math.min(y0, seg.y),
                    y1: Math.max(y0, seg.y)
                });
            }
        }
        x = seg.x;
        y = seg.y;
    });
    return pts;
}

function endsAtPoints(d, points) {
    var segs = parsePath(d);
    if (!segs || segs.length === 0 || segs[0].cmd !== 'M') {
        return false;
    }
    if (Math.abs(segs[0].x - Number(points[0].x.toFixed(2))) > 0.001) {
        return false;
    }
    if (Math.abs(segs[0].y - Number(points[0].y.toFixed(2))) > 0.001) {
        return false;
    }
    var drawn = segs.filter(function (seg) { return seg.cmd !== 'M'; });
    if (drawn.length !== points.length - 1) {
        return false;
    }
    for (var i = 0; i < drawn.length; i++) {
        if (Math.abs(drawn[i].x - Number(points[i + 1].x.toFixed(2))) > 0.001) {
            return false;
        }
        if (Math.abs(drawn[i].y - Number(points[i + 1].y.toFixed(2))) > 0.001) {
            return false;
        }
    }
    return true;
}

function withinSegment(d, eps) {
    var pts = samplesOf(d, 24);
    if (!pts) {
        return false;
    }
    return pts.every(function (p) {
        return p.y >= p.y0 - eps && p.y <= p.y1 + eps;
    });
}

function withinPlot(d, yMin, yMax, eps) {
    var pts = samplesOf(d, 24);
    if (!pts) {
        return false;
    }
    return pts.every(function (p) {
        return p.y >= yMin - eps && p.y <= yMax + eps;
    });
}

function finitePath(d) {
    return d !== '' && !/NaN|Infinity/.test(d);
}

var yTop = 18;
var yBottom = 360;
var cases = [];

cases.push({
    name: 'leeg',
    ok: trendSmoothPath([], yTop, yBottom) === ''
});

var one = [{ x: 12.5, y: 40 }];
cases.push({
    name: 'één punt blijft een punt',
    ok: trendSmoothPath(one, yTop, yBottom) === 'M12.50 40.00'
});

var two = [{ x: 0, y: 100 }, { x: 40, y: 20 }];
var twoPath = trendSmoothPath(two, yTop, yBottom);
cases.push({
    name: 'twee punten blijven een rechte lijn',
    ok: twoPath === 'M0.00 100.00 L40.00 20.00' && endsAtPoints(twoPath, two)
});

var line = [];
for (var i = 0; i < 5; i++) {
    line.push({ x: i * 20, y: 80 + i * 15 });
}
var linePath = trendSmoothPath(line, yTop, yBottom);
var lineSamples = samplesOf(linePath, 16);
var lineFlat = lineSamples && lineSamples.every(function (p) {
    return Math.abs(p.y - (80 + (p.x / 20) * 15)) <= 0.05;
});
cases.push({
    name: 'rechte reeks blijft op de lijn',
    ok: !!lineFlat && endsAtPoints(linePath, line) && (linePath.match(/C/g) || []).length === 4
});

function spike(name, ys, yMin, yMax) {
    var points = ys.map(function (y, index) {
        return { x: index * 30, y: y };
    });
    var d = trendSmoothPath(points, yMin, yMax);
    return {
        name: name,
        ok: finitePath(d) && endsAtPoints(d, points) && withinSegment(d, 0.02) && withinPlot(d, yMin, yMax, 0.02),
        detail: d
    };
}

cases.push(spike('piek schiet niet voorbij', [300, 300, 40, 300, 300], yTop, yBottom));
cases.push(spike('dal op nul zakt niet onder de as', [80, 80, yBottom, 80, 80], yTop, yBottom));
cases.push(spike('sprong 0-0-0-10-10-10 blijft in bereik', [200, 200, 200, 40, 40, 40], yTop, yBottom));
cases.push(spike('zaagtand per segment binnen de buren', [100, 20, 100, 20, 100, 20, 100], yTop, yBottom));

var same = [{ x: 0, y: 50 }, { x: 0, y: 50 }, { x: 25, y: 90 }];
var samePath = trendSmoothPath(same, yTop, yBottom);
cases.push({
    name: 'samenvallend punt geeft geen NaN',
    ok: finitePath(samePath) && withinSegment(samePath, 0.02) && withinPlot(samePath, yTop, yBottom, 0.02)
});

var hourly = [];
for (var h = 0; h < 48; h++) {
    var count = (h % 12 < 6) ? 4 : 15;
    hourly.push({ x: h * 8, y: yBottom - (count / 15) * (yBottom - yTop) });
}
var hourlyPath = trendSmoothPath(hourly, yTop, yBottom);
cases.push({
    name: '48 uur blijft binnen as en buren',
    ok: finitePath(hourlyPath) && endsAtPoints(hourlyPath, hourly) && withinSegment(hourlyPath, 0.02) && withinPlot(hourlyPath, yTop, yBottom, 0.02)
});

var corner = [{ x: 0, y: 300 }, { x: 30, y: 40 }, { x: 60, y: 300 }];
var cornerPath = trendSmoothPath(corner, yTop, yBottom);
var cornerMid = (samplesOf(cornerPath, 2) || []).filter(function (p) {
    return Math.abs(p.x - 15) < 1;
})[0];
cases.push({
    name: 'hoek wordt een kromme en blijft door de piek',
    ok: !!cornerMid && Math.abs(cornerMid.y - 170) > 20 && withinSegment(cornerPath, 0.02) && endsAtPoints(cornerPath, corner)
});

var seriesA = [40, 40, 90, 30, 30, 120].map(function (y, index) {
    return { x: 46 + index * 80, y: y };
});
var seriesB = [140, 70, 70, 160, 90].map(function (y, index) {
    return { x: 46 + index * 80, y: y };
});
var pathA = trendSmoothPath(seriesA, yTop, yBottom);
var pathB = trendSmoothPath(seriesB, yTop, yBottom);
cases.push({
    name: 'twee reeksen elk binnen hun eigen punten',
    ok: pathA !== pathB
        && endsAtPoints(pathA, seriesA) && withinSegment(pathA, 0.02)
        && endsAtPoints(pathB, seriesB) && withinSegment(pathB, 0.02)
});

process.stdout.write(JSON.stringify(cases.map(function (row) {
    return { name: row.name, ok: !!row.ok };
})));
JS;

$tmp = tempnam(sys_get_temp_dir(), 'asc-trend-') . '.js';
file_put_contents($tmp, $script);
$output = shell_exec(escapeshellarg($nodeBin) . ' ' . escapeshellarg($tmp) . ' 2>&1');
@unlink($tmp);
$result = json_decode((string) $output, true);
check('node draait de kromme', is_array($result), (string) $output);
if (is_array($result)) {
    foreach ($result as $row) {
        $name = (string) ($row['name'] ?? '?');
        check($name, !empty($row['ok']));
    }
}

echo PHP_EOL . "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
