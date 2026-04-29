<?php
/**
 * Preview
 * Serves file previews (text, code, data, documents).
 * Supports: TXT, MD, JSON, CSV, XLSX, PDF, DOCX, and all common code formats.
 */

$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['SERVER_ADDR'] = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/preview.php';

require_once __DIR__ . '/content/bootstrap.php';
require_once __DIR__ . '/content/constants.php';
require_once __DIR__ . '/content/localization.php';
require_once __DIR__ . '/content/helpers.php';
require_once __DIR__ . '/TicketStore.php';

$attachmentId = max(0, (int) ($_GET['id'] ?? 0));
$format = trim((string) ($_GET['format'] ?? 'auto'));

if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Invalid attachment ID');
}

// Fetch attachment from store
if (!$store instanceof TicketStore) {
    http_response_code(500);
    exit('Database connection failed');
}

$attachment = $store->getAttachment($attachmentId);
if ($attachment === null) {
    http_response_code(404);
    exit('Attachment not found');
}

// Security: verify user has access
$ticketId = max(0, (int) ($attachment['ticket_id'] ?? 0));
if ($ticketId <= 0 || $store->getTicket($ticketId, false, $userEmail) === null) {
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

// Validate format
$allowedFormats = ['text', 'markdown', 'json', 'javascript', 'python', 'ruby', 'go', 'rust', 'c', 'cpp', 'csharp', 'java', 'php', 'sql', 'html', 'css', 'xml', 'yaml', 'toml', 'ini', 'bash', 'csv', 'tsv', 'pdf', 'excel', 'word'];
if (!in_array($format, $allowedFormats, true)) {
    http_response_code(400);
    exit('Invalid format');
}

$fileSize = max(0, (int) ($attachment['file_size'] ?? 0));
$maxPreviewSize = 5242880; // 5MB

if ($fileSize > $maxPreviewSize) {
    http_response_code(413);
    exit('File too large for preview (max 5 MB)');
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
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; color: #333; line-height: 1.5; }
.container { max-width: 1200px; margin: 0 auto; background: white; min-height: 100vh; }
.header { background: #f9f9f9; border-bottom: 1px solid #e0e0e0; padding: 16px 20px; }
.header h1 { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 4px; }
.header p { font-size: 12px; color: #999; }
.content { padding: 20px; }
.text-preview { white-space: pre-wrap; word-wrap: break-word; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 13px; line-height: 1.6; background: #fafafa; padding: 16px; border-radius: 4px; border: 1px solid #e0e0e0; overflow-x: auto; }
.code-preview { background: #282c34; color: #abb2bf; padding: 16px; border-radius: 4px; overflow-x: auto; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 13px; line-height: 1.6; }
.csv-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.csv-table th { background: #f5f5f5; color: #333; font-weight: 600; border: 1px solid #e0e0e0; padding: 8px 12px; text-align: left; }
.csv-table td { border: 1px solid #e0e0e0; padding: 8px 12px; }
.csv-table tr:nth-child(odd) { background: #fafafa; }
.error-msg { color: #d32f2f; background: #ffebee; padding: 12px; border-radius: 4px; border-left: 4px solid #d32f2f; }
.loading { text-align: center; padding: 40px; color: #999; }
#pdf-viewer { width: 100%; height: 600px; border: 1px solid #e0e0e0; border-radius: 4px; }
#excel-viewer { width: 100%; }
#word-viewer { border: 1px solid #e0e0e0; border-radius: 4px; padding: 16px; }
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
echo '<body>' . PHP_EOL;
echo '<div class="container">' . PHP_EOL;
echo '<div class="header">' . PHP_EOL;
echo '<h1>' . h((string) ($attachment['original_name'] ?? 'Preview')) . '</h1>' . PHP_EOL;
echo '<p>' . formatFileSize(max(0, (int) ($attachment['file_size'] ?? 0))) . '</p>' . PHP_EOL;
echo '</div>' . PHP_EOL;
echo '<div class="content">' . PHP_EOL;

$fileContent = file_get_contents($storedPath);
if ($fileContent === false) {
    echo '<div class="error-msg">Error reading file</div>' . PHP_EOL;
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
                echo '<table class="csv-table">' . PHP_EOL;
                echo '<thead><tr>' . PHP_EOL;
                $headerFields = str_getcsv(trim($lines[0]), $delimiter);
                foreach ($headerFields as $field) {
                    echo '<th>' . h($field) . '</th>' . PHP_EOL;
                }
                echo '</tr></thead>' . PHP_EOL;
                echo '<tbody>' . PHP_EOL;
                for ($i = 1; $i < count($lines); $i++) {
                    if (trim($lines[$i]) === '') continue;
                    echo '<tr>' . PHP_EOL;
                    $fields = str_getcsv(trim($lines[$i]), $delimiter);
                    foreach ($fields as $field) {
                        echo '<td>' . h($field) . '</td>' . PHP_EOL;
                    }
                    echo '</tr>' . PHP_EOL;
                }
                echo '</tbody>' . PHP_EOL;
                echo '</table>' . PHP_EOL;
            }
            break;

        case 'pdf':
            echo '<div id="pdf-viewer"></div>' . PHP_EOL;
            echo '<script>' . PHP_EOL;
            echo 'pdfjsLib.getDocument(' . json_encode($storedPath) . ').promise.then(pdf => {' . PHP_EOL;
            echo '  pdf.getPage(1).then(page => {' . PHP_EOL;
            echo '    const canvas = document.createElement("canvas");' . PHP_EOL;
            echo '    const context = canvas.getContext("2d");' . PHP_EOL;
            echo '    const viewport = page.getViewport({ scale: 1.5 });' . PHP_EOL;
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
            echo 'fetch(' . json_encode($storedPath) . ').then(r => r.arrayBuffer()).then(ab => {' . PHP_EOL;
            echo '  const workbook = XLSX.read(new Uint8Array(ab), { type: "array" });' . PHP_EOL;
            echo '  const sheet = workbook.Sheets[workbook.SheetNames[0]];' . PHP_EOL;
            echo '  const html = XLSX.utils.sheet_to_html(sheet);' . PHP_EOL;
            echo '  document.getElementById("excel-viewer").innerHTML = html;' . PHP_EOL;
            echo '});' . PHP_EOL;
            echo '</script>' . PHP_EOL;
            break;

        case 'word':
            echo '<div id="word-viewer"></div>' . PHP_EOL;
            echo '<script>' . PHP_EOL;
            echo 'fetch(' . json_encode($storedPath) . ').then(r => r.arrayBuffer()).then(ab => {' . PHP_EOL;
            echo '  mammoth.convertToHtml({ arrayBuffer: ab }).then(result => {' . PHP_EOL;
            echo '    document.getElementById("word-viewer").innerHTML = result.value;' . PHP_EOL;
            echo '  });' . PHP_EOL;
            echo '});' . PHP_EOL;
            echo '</script>' . PHP_EOL;
            break;

        default:
            // All code formats get syntax highlighting
            $langMap = [
                'javascript' => 'javascript', 'python' => 'python', 'ruby' => 'ruby',
                'go' => 'go', 'rust' => 'rust', 'c' => 'c', 'cpp' => 'cpp',
                'csharp' => 'csharp', 'java' => 'java', 'php' => 'php',
                'sql' => 'sql', 'html' => 'html', 'css' => 'css', 'xml' => 'xml',
                'yaml' => 'yaml', 'toml' => 'toml', 'ini' => 'ini', 'bash' => 'bash'
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
