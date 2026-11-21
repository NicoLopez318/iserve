# Validation, October 3, 2026

- Staff: 14 tests, 445 assertions passed on SQLite and isolated MySQL.
- Updated normal-user app: 24 tests, 446 assertions passed on SQLite.
- Staff tests render all 37 primary role pages with empty data, and populated management screens.
- Checked unit scoping, private attachments, revision and forwarding, final-only effects, negative point adjustments, configured clearance capacity, report snapshots, checklist validation, settings backup, and draft/expired content access.
- Browser-verified Admin dashboard at desktop and 390px width; responsive cards and horizontal navigation render.
- Existing shared database rows were preserved. The signed points compatibility update did not change row counts. Tests used isolated databases.
- Pixel-perfect matching of every Figma contextual panel remains unverified. Known functional and visual gaps are in DESIGN_COVERAGE.md.
