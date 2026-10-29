# Database backup and versioning operations

Status: backup procedure `VERIFIED`; ordered SQL migration approach `APPROVED` and `COMPLETED`.

## Current authority

- The live local database is `ShoppingCart` and uses `utf8mb4`.
- `ShoppingCart (1).sql` is a historical data dump, not a migration mechanism.
- `setup_db.php` and `setup_security.php` are legacy one-time setup utilities. They are now
  command-line only, but they are not ordered or transactional migrations.
- Existing files in `backups/` are historical user data and must not be edited or deleted
  without explicit approval.

## Create a logical backup

Store production backups outside the public web root and outside Git. On Windows PowerShell,
use a timestamped destination and allow `mysqldump` to prompt for the password:

```powershell
$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 `
  --host=$env:SHOPPINGCART_DB_HOST --port=$env:SHOPPINGCART_DB_PORT `
  --user=$env:SHOPPINGCART_DB_USER -p `
  --result-file="D:\secure-backups\ShoppingCart_$stamp.sql" `
  $env:SHOPPINGCART_DB_NAME
```

Do not place the password directly in the command line. Confirm the file is non-empty, protect
it with restricted filesystem permissions, encrypt it at rest, and record its checksum:

```powershell
Get-Item -LiteralPath "D:\secure-backups\ShoppingCart_$stamp.sql"
Get-FileHash -Algorithm SHA256 -LiteralPath "D:\secure-backups\ShoppingCart_$stamp.sql"
```

## Verify restoration safely

Restoration testing must use a disposable database, never the live database. Creating or
dropping that disposable database requires explicit operator approval:

```powershell
mysql --host=$env:SHOPPINGCART_DB_HOST --port=$env:SHOPPINGCART_DB_PORT `
  --user=$env:SHOPPINGCART_DB_USER -p `
  --execute="CREATE DATABASE ShoppingCart_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"

mysql --host=$env:SHOPPINGCART_DB_HOST --port=$env:SHOPPINGCART_DB_PORT `
  --user=$env:SHOPPINGCART_DB_USER -p ShoppingCart_restore_check `
  --execute="SOURCE D:/secure-backups/ShoppingCart_YYYYMMDD_HHMMSS.sql"
```

Verify table counts, foreign keys, representative reads, and order-total consistency before an
operator explicitly approves dropping the disposable database.

## Schema versioning

The approved workflow uses ordered `database/migrations/YYYYMMDDHHMM_description.sql` files and
the command-line-only `migrate.php` runner. Applied versions and SHA-256 checksums are recorded in
`schema_migrations`; a changed checksum stops execution. Each migration documents its
preconditions, forward SQL, rollback guidance, and backup requirement.

Check pending versions with `php migrate.php --status`. After creating and verifying a backup,
apply them in PowerShell with:

```powershell
$env:ALLOW_DB_MIGRATIONS='1'
php .\migrate.php --apply
Remove-Item Env:ALLOW_DB_MIGRATIONS
```

The runner returns HTTP 404 outside the CLI and migrations never execute during ordinary page
requests. `ShoppingCart (1).sql` remains a historical reference rather than migration authority.
