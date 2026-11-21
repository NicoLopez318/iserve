DATABASE CONTENTS
phpmyadmin-fresh-schema.sql: MySQL/MariaDB schema for a NEW EMPTY database. This contains no accounts or transaction records. In phpMyAdmin, create/select an empty database, choose Import, select this SQL file and click Go. Configure BOTH applications to use the same intended database. Run reviewed outstanding Laravel migrations after installation. Never import into an existing live database without a backup.

legacy-schema-20261003.sql: older empty schema retained for reference; do NOT import it together with the fresh schema.
local-snapshots/: original local SQLite database snapshots from October 1 and 3, 2026. These are not MySQL files and cannot be directly imported into phpMyAdmin. They are historical local data, not the current shared production database.

LIVE DATABASE IS NOT INCLUDED: the Hostinger database could not be accessed. Export its database through phpMyAdmin (Export > Quick > SQL) for a true full backup of live accounts and transactions. Private uploaded documents and .env credentials are also not included in this package. Store live exports privately.