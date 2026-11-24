# AvenPay documentation and branding fixes

Applied to the user-supplied ZIP archive.

## Changes
- Changed the API documentation pages to a light, high-contrast presentation so cards, headings, prose, inputs, and code samples remain readable.
- Fixed the B2C documentation PHP example templates. Literal `<?php` tags embedded inside JavaScript template strings were being interpreted by PHP as real opening tags; they are now emitted through a JavaScript `\\x3C` escape. Removed unnecessary backslashes before PHP variables in the displayed examples.
- Replaced the landing page's legacy logo image reference with the dedicated AvenPay SVG icon and added AvenPay brand SVG assets under `public_html/brand/`.
- Updated page favicon references to the AvenPay SVG icon and refreshed app-icon cache versions.

## Validation
- PHP syntax check: all 186 PHP files passed `php -l`.
- B2C documentation inline JavaScript: passed `node --check`.
- No production server, database, or Safaricom transaction was accessed or changed.
