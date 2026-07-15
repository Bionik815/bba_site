pdf.js (pdfjs-dist) 6.1.200 — legacy build, Apache-2.0, https://github.com/mozilla/pdf.js

Vendored, not CDN-loaded: the builder must keep working if a CDN is blocked,
and we pin the version deliberately (CVE-2024-4367 affected < 4.2.67; we also
load with isEvalSupported:false).

Only two files are needed:
  pdf.min.js          — the API, dynamically imported when a customer picks a PDF
  pdf.worker.min.js   — parsing worker

To update: npm i pdfjs-dist@<ver>, copy legacy/build/{pdf.min.mjs,pdf.worker.min.mjs} (rename .mjs -> .js:
here, bump BW_GSB_VERSION, and re-test a real PDF and a PDF-based .ai.

NOTE: the upstream files are .mjs; we rename to .js because LiteSpeed serves
.mjs as text/plain, and browsers refuse to execute a module with a non-JS MIME.
