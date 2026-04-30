<?php
/**
 * Preview
 * Serves file previews (text, code, data, documents).
 * Supports: TXT, MD, JSON, CSV, XLSX, PDF, DOCX, and all common code formats.
 */

require_once __DIR__ . '/content/bootstrap.php';
require_once __DIR__ . '/content/constants.php';
require_once __DIR__ . '/content/localization.php';
require_once __DIR__ . '/content/helpers.php';
require_once __DIR__ . '/TicketStore.php';

$attachmentId = max(0, (int) ($_GET['id'] ?? 0));
$format = trim((string) ($_GET['format'] ?? 'auto'));
$isThumbnail = isset($_GET['thumbnail']) && (string) $_GET['thumbnail'] === '1';
$isCheckOnly = isset($_GET['check']) && (string) $_GET['check'] === '1';

if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Invalid attachment ID');
}

// Fetch attachment from store
if (!isset($ictUserColors) || !is_array($ictUserColors)) {
    $ictUserColors = [];
}
if (!isset($ictUsers) || !is_array($ictUsers)) {
    $ictUsers = [];
}
normalizeIctUsersConfig($ictUsers, $ictUserColors);

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers, TICKET_CATEGORIES);
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Database connection failed');
}

$attachment = $store->getAttachment($attachmentId);
if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found');
}

$originalName = (string) ($attachment['original_name'] ?? '');
$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

// Security: verify user has access
$ticketId = max(0, (int) ($attachment['ticket_id'] ?? 0));
$viewerEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
$viewerIsAdmin = !empty($_SESSION['user']['admin']);
if ($ticketId <= 0 || $store->getTicket($ticketId, $viewerIsAdmin, $viewerEmail) === null) {
    http_response_code(403);
    exit('Access denied');
}

$storedPath = (string) ($attachment['stored_path'] ?? '');
if ($storedPath === '' || !file_exists($storedPath)) {
    http_response_code(404);
    exit('File not found');
}

// Auto-detect format if needed
if ($format === 'auto' || $format === '') {
    $format = getPreviewFormat($attachment) ?? 'text';
}

$downloadUrl = buildAttachmentDirectUrl($attachment);
if ($downloadUrl === '' && in_array($format, ['pdf', 'excel', 'word', 'video'], true)) {
    http_response_code(500);
    exit('Preview path not available');
}

// Validate format
$allowedFormats = ['text', 'markdown', 'json', 'javascript', 'python', 'ruby', 'go', 'rust', 'c', 'cpp', 'csharp', 'java', 'php', 'sql', 'html', 'css', 'xml', 'yaml', 'toml', 'ini', 'bash', 'csv', 'tsv', 'pdf', 'excel', 'word', 'video'];
if (!in_array($format, $allowedFormats, true)) {
    http_response_code(400);
    exit('Invalid format');
}

$fileSize = max(0, (int) ($attachment['file_size'] ?? 0));
$isBinaryPreview = in_array($format, ['pdf', 'excel', 'word', 'video'], true);
$maxPreviewSize = $isBinaryPreview ? MAX_ATTACHMENT_BYTES : 5242880;

if ($fileSize > $maxPreviewSize) {
    http_response_code(413);
    exit('File too large for preview');
}

if ($isCheckOnly) {
    if ($format === 'word' && $extension === 'doc') {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'reason' => 'unsupported_word_binary',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'format' => $format,
        'file_size' => $fileSize,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>' . PHP_EOL;
echo '<html lang="' . h(getCurrentLanguage()) . '">' . PHP_EOL;
echo '<head>' . PHP_EOL;
echo '<meta charset="UTF-8">' . PHP_EOL;
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . PHP_EOL;
echo '<title>' . h((string) ($attachment['original_name'] ?? 'Preview')) . '</title>' . PHP_EOL;

// Basic inline styles
?>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html,
    body {
        width: 100%;
        height: 100%;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: #f5f5f5;
        color: #333;
        line-height: 1.5;
        overflow-x: hidden;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: white;
        min-height: 100vh;
    }

    .header {
        background: #f9f9f9;
        border-bottom: 1px solid #e0e0e0;
        padding: 16px 20px;
    }

    .header h1 {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
    }

    .header p {
        font-size: 12px;
        color: #999;
    }

    .content {
        padding: 20px;
        overflow-x: auto;
    }

    body.thumbnail-mode {
        background: transparent;
        overflow: hidden;
    }

    body.thumbnail-mode .container {
        max-width: none;
        min-height: 0;
        border: 0;
    }

    body.thumbnail-mode .header {
        display: none;
    }

    body.thumbnail-mode .content {
        padding: 6px;
        overflow: hidden;
    }

    body.thumbnail-mode .text-preview,
    body.thumbnail-mode .code-preview {
        max-height: 112px;
        overflow: hidden;
        font-size: 11px;
        line-height: 1.35;
    }

    body.thumbnail-mode .csv-table {
        font-size: 10px;
    }

    body.thumbnail-mode .csv-table th,
    body.thumbnail-mode .csv-table td {
        padding: 4px 6px;
    }

    body.thumbnail-mode #pdf-viewer,
    body.thumbnail-mode #word-viewer,
    body.thumbnail-mode #excel-viewer,
    body.thumbnail-mode #video-viewer {
        min-height: 112px;
        max-height: 112px;
        overflow: hidden;
    }

    .text-preview {
        white-space: pre-wrap;
        word-wrap: break-word;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 13px;
        line-height: 1.6;
        background: #fafafa;
        padding: 16px;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
        overflow-x: auto;
    }

    .code-preview {
        background: #282c34;
        color: #abb2bf;
        padding: 16px;
        border-radius: 4px;
        overflow-x: auto;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 13px;
        line-height: 1.6;
    }

    .table-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
    }

    .csv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .csv-table th {
        background: #f5f5f5;
        color: #333;
        font-weight: 600;
        border: 1px solid #e0e0e0;
        padding: 8px 12px;
        text-align: left;
    }

    .csv-table td {
        border: 1px solid #e0e0e0;
        padding: 8px 12px;
    }

    .csv-table tr:nth-child(odd) {
        background: #fafafa;
    }

    .error-msg {
        color: #d32f2f;
        background: #ffebee;
        padding: 12px;
        border-radius: 4px;
        border-left: 4px solid #d32f2f;
    }

    .loading {
        text-align: center;
        padding: 40px;
        color: #999;
    }

    #pdf-viewer {
        width: 100%;
        height: 600px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
    }

    #excel-viewer {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
    }

    #excel-viewer table {
        width: 100%;
        border-collapse: collapse;
    }

    #excel-viewer th,
    #excel-viewer td {
        border: 1px solid #d0d7de;
        padding: 6px 8px;
    }

    #word-viewer {
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 16px;
    }

    #video-viewer {
        width: 100%;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        background: #000;
    }

    body.thumbnail-mode #video-viewer {
        border: 0;
        border-radius: 8px;
    }
</style>

<?php
// Syntax highlighting for code
if (in_array($format, ['javascript', 'python', 'ruby', 'go', 'rust', 'c', 'cpp', 'csharp', 'java', 'php', 'sql', 'html', 'css', 'xml', 'yaml', 'toml', 'ini', 'bash', 'json'], true)) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">' . PHP_EOL;
}

// PDF viewer
if ($format === 'pdf') {
    echo '<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/build/pdf.min.js"></script>' . PHP_EOL;
}

// Excel viewer
if ($format === 'excel') {
    echo '<script src="https://cdn.jsdelivr.net/npm/xlsx@latest/dist/xlsx.full.min.js"></script>' . PHP_EOL;
}

// DOCX viewer
if ($format === 'word') {
    echo '<script src="https://cdn.jsdelivr.net/npm/mammoth@latest/mammoth.browser.min.js"></script>' . PHP_EOL;
}

// Markdown renderer
if ($format === 'markdown') {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.2.0/github-markdown-light.min.css">' . PHP_EOL;
    echo '<script src="https://cdn.jsdelivr.net/npm/marked@latest/marked.min.js"></script>' . PHP_EOL;
}

echo '</head>' . PHP_EOL;
echo '<body class="' . ($isThumbnail ? 'thumbnail-mode' : '') . '">' . PHP_EOL;
echo '<div class="container">' . PHP_EOL;
echo '<div class="header">' . PHP_EOL;
echo '<h1>' . h((string) ($attachment['original_name'] ?? 'Preview')) . '</h1>' . PHP_EOL;
echo '<p>' . formatFileSize(max(0, (int) ($attachment['file_size'] ?? 0))) . '</p>' . PHP_EOL;
echo '</div>' . PHP_EOL;
echo '<div class="content">' . PHP_EOL;

$fileContent = null;
if (!in_array($format, ['pdf', 'excel', 'word', 'video'], true)) {
    $fileContent = file_get_contents($storedPath);
    if ($fileContent === false) {
        echo '<div class="error-msg">Error reading file</div>' . PHP_EOL;
    }
}

if (!in_array($format, ['pdf', 'excel', 'word', 'video'], true) && $fileContent === false) {
    // Error already rendered above.
} else {
    switch ($format) {
        case 'text':
            echo '<div class="text-preview">' . h($fileContent) . '</div>' . PHP_EOL;
            break;

        case 'markdown':
            echo '<div class="markdown-body">' . PHP_EOL;
            echo '<script>document.write(marked.parse(' . json_encode($fileContent) . '))</script>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            break;

        case 'json':
            $jsonPretty = json_encode(json_decode($fileContent), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonPretty === false) {
                echo '<div class="error-msg">Invalid JSON</div>' . PHP_EOL;
            } else {
                echo '<pre class="code-preview"><code class="language-json">' . h($jsonPretty) . '</code></pre>' . PHP_EOL;
            }
            break;

        case 'csv':
        case 'tsv':
            $delimiter = $format === 'tsv' ? "\t" : ',';
            $lines = explode("\n", trim($fileContent));
            if (!empty($lines)) {
                $firstLine = ltrim((string) ($lines[0] ?? ''), "\xEF\xBB\xBF");
                if ($format !== 'tsv') {
                    $semicolonCount = substr_count($firstLine, ';');
                    $commaCount = substr_count($firstLine, ',');
                    $tabCount = substr_count($firstLine, "\t");
                    if ($tabCount > $semicolonCount && $tabCount > $commaCount) {
                        $delimiter = "\t";
                    } elseif ($semicolonCount >= $commaCount && $semicolonCount > 0) {
                        $delimiter = ';';
                    } else {
                        $delimiter = ',';
                    }
                }

                echo '<table class="csv-table">' . PHP_EOL;
                echo '<div class="table-scroll">' . PHP_EOL;
                echo '<table class="csv-table">' . PHP_EOL;
                echo '<thead><tr>' . PHP_EOL;
                $headerFields = str_getcsv(trim($firstLine), $delimiter);
                foreach ($headerFields as $field) {
                    echo '<th>' . h($field) . '</th>' . PHP_EOL;
                }
                echo '</tr></thead>' . PHP_EOL;
                echo '<tbody>' . PHP_EOL;
                for ($i = 1; $i < count($lines); $i++) {
                    if (trim($lines[$i]) === '')
                        continue;
                    echo '<tr>' . PHP_EOL;
                    $fields = str_getcsv(trim($lines[$i]), $delimiter);
                    foreach ($fields as $field) {
                        echo '<td>' . h($field) . '</td>' . PHP_EOL;
                    }
                    echo '</tr>' . PHP_EOL;
                }
                echo '</tbody>' . PHP_EOL;
                echo '</table>' . PHP_EOL;
                echo '</div>' . PHP_EOL;
            }
            break;

        case 'pdf':
            echo '<div id="pdf-viewer"></div>' . PHP_EOL;
            echo '<script>' . PHP_EOL;
            echo 'pdfjsLib.getDocument(' . json_encode($downloadUrl) . ').promise.then(pdf => {' . PHP_EOL;
            echo '  pdf.getPage(1).then(page => {' . PHP_EOL;
            echo '    const canvas = document.createElement("canvas");' . PHP_EOL;
            echo '    const context = canvas.getContext("2d");' . PHP_EOL;
            echo '    const viewport = page.getViewport({ scale: ' . ($isThumbnail ? '0.32' : '1.5') . ' });' . PHP_EOL;
            echo '    canvas.width = viewport.width;' . PHP_EOL;
            echo '    canvas.height = viewport.height;' . PHP_EOL;
            echo '    const renderContext = { canvasContext: context, viewport };' . PHP_EOL;
            echo '    page.render(renderContext).promise.then(() => {' . PHP_EOL;
            echo '      document.getElementById("pdf-viewer").appendChild(canvas);' . PHP_EOL;
            echo '    });' . PHP_EOL;
            echo '  });' . PHP_EOL;
            echo '});' . PHP_EOL;
            echo '</script>' . PHP_EOL;
            break;

        case 'excel':
            echo '<div id="excel-viewer"></div>' . PHP_EOL;
            echo '<script>' . PHP_EOL;
            echo 'fetch(' . json_encode($downloadUrl) . ').then(r => r.arrayBuffer()).then(ab => {' . PHP_EOL;
            echo '  const workbook = XLSX.read(new Uint8Array(ab), { type: "array" });' . PHP_EOL;
            echo '  const sheet = workbook.Sheets[workbook.SheetNames[0]];' . PHP_EOL;
            echo '  const html = XLSX.utils.sheet_to_html(sheet);' . PHP_EOL;
            echo '  document.getElementById("excel-viewer").innerHTML = html;' . PHP_EOL;
            echo '});' . PHP_EOL;
            echo '</script>' . PHP_EOL;
            break;

        case 'word':
            if ($extension === 'doc') {
                echo '<div class="error-msg">DOC previews are not supported in-browser. Please use DOCX or download the file.</div>' . PHP_EOL;
                break;
            }

            echo '<div id="word-viewer"></div>' . PHP_EOL;
            echo '<script>' . PHP_EOL;
            echo 'fetch(' . json_encode($downloadUrl) . ').then(r => r.arrayBuffer()).then(ab => {' . PHP_EOL;
            echo '  mammoth.convertToHtml({ arrayBuffer: ab }).then(result => {' . PHP_EOL;
            echo '    const target = document.getElementById("word-viewer");' . PHP_EOL;
            echo '    if (!result || !result.value || result.value.trim() === "") {' . PHP_EOL;
            echo '      target.innerHTML = "<div class=\"error-msg\">Could not render this DOCX file.</div>";' . PHP_EOL;
            echo '      return;' . PHP_EOL;
            echo '    }' . PHP_EOL;
            echo '    target.innerHTML = result.value;' . PHP_EOL;
            echo '  }).catch(() => {' . PHP_EOL;
            echo '    document.getElementById("word-viewer").innerHTML = "<div class=\"error-msg\">Could not render this DOCX file.</div>";' . PHP_EOL;
            echo '  });' . PHP_EOL;
            echo '}).catch(() => {' . PHP_EOL;
            echo '  document.getElementById("word-viewer").innerHTML = "<div class=\"error-msg\">Could not load this DOCX file.</div>";' . PHP_EOL;
            echo '});' . PHP_EOL;
            echo '</script>' . PHP_EOL;
            break;

        case 'video':
            echo '<video id="video-viewer" ' . ($isThumbnail ? 'muted playsinline preload="metadata"' : 'controls preload="metadata"') . '>' . PHP_EOL;
            echo '<source src="' . h($downloadUrl) . '" type="' . h((string) ($attachment['mime_type'] ?? 'video/mp4')) . '">' . PHP_EOL;
            echo '</video>' . PHP_EOL;
            if ($isThumbnail) {
                echo '<script>' . PHP_EOL;
                echo '(() => {' . PHP_EOL;
                echo '  const video = document.getElementById("video-viewer");' . PHP_EOL;
                echo '  if (!video) return;' . PHP_EOL;
                echo '  video.addEventListener("loadeddata", function () {' . PHP_EOL;
                echo '    try { video.currentTime = Math.min(0.1, (video.duration || 0)); } catch (e) {}' . PHP_EOL;
                echo '    video.pause();' . PHP_EOL;
                echo '  }, { once: true });' . PHP_EOL;
                echo '})();' . PHP_EOL;
                echo '</script>' . PHP_EOL;
            }
            break;

        default:
            // All code formats get syntax highlighting
            $langMap = [
                'javascript' => 'javascript',
                'python' => 'python',
                'ruby' => 'ruby',
                'go' => 'go',
                'rust' => 'rust',
                'c' => 'c',
                'cpp' => 'cpp',
                'csharp' => 'csharp',
                'java' => 'java',
                'php' => 'php',
                'sql' => 'sql',
                'html' => 'html',
                'css' => 'css',
                'xml' => 'xml',
                'yaml' => 'yaml',
                'toml' => 'toml',
                'ini' => 'ini',
                'bash' => 'bash'
            ];
            $hlLang = $langMap[$format] ?? 'plaintext';
            echo '<pre class="code-preview"><code class="language-' . h($hlLang) . '">' . h($fileContent) . '</code></pre>' . PHP_EOL;
            echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>' . PHP_EOL;
            echo '<script>hljs.highlightAll();</script>' . PHP_EOL;
    }
}

echo '</div>' . PHP_EOL;
echo '</div>' . PHP_EOL;
echo '</body>' . PHP_EOL;
echo '</html>' . PHP_EOL;
