# Third-Party Libraries

Preview libraries are loaded from a CDN and run in the browser only.
Fenced code in ticket messages uses a local highlight.js subset, not a CDN.
No user data is ever sent to external servers.

## Ticket code blocks (local)

- **Highlight.js 11.11.1** — syntax highlighting after the message is rendered
  - Path: `web/thirdparty/highlightjs/highlight.min.js`
  - CSS: `web/thirdparty/highlightjs/asclepius-light.css` (GitHub light, scoped to message code)
  - Languages: javascript, php, sql, xml (also html), css, json, bash, powershell
  - License: BSD-3-Clause (`highlightjs/LICENSE`)
  - AL (Business Central) is not in this build and stays plain text

## Client-Side Preview Libraries (CDN)

- **Highlight.js** - Code syntax highlighting
  - Src: https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js
  - License: BSD-3-Clause
  - Local: Loaded only when needed for code/json preview

- **PDF.js** - PDF rendering in browser
  - Src: https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/build/pdf.min.js
  - License: Apache-2.0
  - Local: Loaded only when needed for PDF preview

- **SheetJS (xlsx)** - Excel file parsing
  - Src: https://cdn.jsdelivr.net/npm/xlsx@latest/dist/xlsx.full.min.js
  - License: Apache-2.0
  - Local: Loaded only when needed for XLSX preview

- **Mammoth.js** - DOCX to HTML converter
  - Src: https://cdn.jsdelivr.net/npm/mammoth@latest/mammoth.browser.min.js
  - License: BSD-2-Clause
  - Local: Loaded only when needed for DOCX preview

- **Marked.js** - Markdown parser
  - Src: https://cdn.jsdelivr.net/npm/marked@latest/marked.min.js
  - License: MIT
  - Local: Loaded only when needed for Markdown preview

## Server-Side Preview Processing

- **PHP built-ins only** - No external libraries required
- CSV → HTML table conversion
- Text file formatting
- JSON pretty-printing
- Syntax highlighting via Highlight.js (client-side)
