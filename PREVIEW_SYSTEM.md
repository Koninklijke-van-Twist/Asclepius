# File Preview System - Implementation Summary

## Geïmplementeerd

### Server-side (`web/preview.php`)
✅ **Pure PHP file serving** (no external PHP libraries)
- TXT, Markdown, JSON, CSV/TSV
- All common code formats (JS, Python, Ruby, Go, Rust, C, C++, C#, Java, PHP, SQL, HTML, CSS, XML, YAML, TOML, INI, Bash)
- PDF, Excel, DOCX (rendered client-side)

### Helper Functions (`web/content/helpers.php`)
✅ `canPreviewFile()` - Determines if file is previewable
✅ `getPreviewFormat()` - Detects and categorizes file type
✅ `formatFileSize()` - Human-readable file sizes (B, KB, MB, GB)

### Client-side Libraries (CDN - no local code)
✅ **Highlight.js** - Code syntax highlighting (10KB)
✅ **PDF.js** - PDF rendering in browser
✅ **SheetJS (XLSX.js)** - Excel file parsing to HTML table
✅ **Mammoth.js** - DOCX to HTML conversion
✅ **Marked.js** - Markdown parsing and rendering
✅ **GitHub Markdown CSS** - Pretty markdown styling

### UI Integration
✅ Preview button added to attachments
✅ Preview modal (iframe overlay)
✅ CSS styling for preview button
✅ JavaScript modal handling (open, close, Escape key)
✅ Keyboard support (ESC to close)

### Localization
✅ `ticket.preview_file` - "Bestand voorvertonen" (NL)
✅ All 4 languages complete (NL, EN, DE, FR)

## File Types Supported

### Text & Markup
- `.txt` → Plain text
- `.md`, `.markdown`, `.mdown`, `.mkd`, `.rst` → Markdown (with Marked.js)

### Code (with Highlight.js)
- `.js`, `.jsx`, `.mjs`, `.ts`, `.tsx` → JavaScript/TypeScript
- `.json`, `.jsonld` → JSON (pretty-printed)
- `.py`, `.pyw`, `.pyi` → Python
- `.rb`, `.erb`, `.gemspec` → Ruby
- `.go` → Go
- `.rs` → Rust
- `.c`, `.h` → C
- `.cpp`, `.cc`, `.cxx`, `.hpp` → C++
- `.cs`, `.csx` → C#
- `.java` → Java
- `.php` → PHP
- `.sql` → SQL
- `.html`, `.htm` → HTML
- `.css`, `.scss`, `.sass`, `.less` → CSS/SCSS
- `.xml` → XML
- `.yaml`, `.yml` → YAML
- `.toml` → TOML
- `.ini`, `.cfg`, `.conf`, `.config` → INI
- `.sh`, `.bash`, `.zsh` → Bash

### Data
- `.csv` → HTML table with borders
- `.tsv` → HTML table (tab-separated)

### Documents (Client-side Rendering)
- `.pdf` → PDF.js viewer (first page visible)
- `.xlsx`, `.xls` → SheetJS HTML table (first sheet)
- `.docx`, `.doc` → Mammoth.js HTML rendering
- `.odt`, `.rtf` → As text/raw

## Usage

### Preview a File
```html
<button data-file-preview-trigger data-preview-id="123">
    Preview File
</button>
```

### Direct URL
```
https://your-domain/asclepius/preview.php?id=123&format=auto
```

Query Parameters:
- `id` - Attachment ID (required)
- `format` - File format (optional, auto-detected if omitted)

## Security
✅ Attachment access checks (user must have ticket access)
✅ No data sent to external servers (everything is local/CDN-only)
✅ File size limit (5 MB for preview, >20 MB downloads directly)
✅ All user input escaped with `h()`
✅ Sandbox iframe for document previews

## Performance
✅ Syntax highlighting only loaded when needed (lazy-loaded CDN)
✅ 5 MB preview size limit (larger files download instead)
✅ Client-side rendering (no server CPU strain)
✅ CDN-cached JavaScript libraries

## Third-party Libraries (CDN Only)
All libraries loaded from CDN via `<script src="">` tags:
- No local files stored
- No data sent to external servers
- Libraries execute in browser only
- See `web/thirdparty/README.md` for details
