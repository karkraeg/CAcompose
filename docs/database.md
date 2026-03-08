# Database Operations

## Backup

```bash
# Create a timestamped, gzip-compressed dump
docker compose exec db \
  mysqldump \
    -uroot -p"${MYSQL_ROOT_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    "${CA_DB_DATABASE}" \
  | gzip > "backup-$(date +%Y%m%d-%H%M).sql.gz"
```

> `--single-transaction` takes a consistent snapshot without locking tables — safe for live systems.

---

## Importing a dump

Use this when migrating from another server, restoring a colleague's dataset, or seeding a fresh install from an existing database.

### Into an existing (empty) database

```bash
# Plain .sql file
docker compose exec -T db \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}" \
  < /path/to/dump.sql

# Gzip-compressed file
gunzip -c /path/to/dump.sql.gz \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"
```

### Into a clean database (drop → recreate → import)

If the target database already has data and you want to replace it entirely:

```bash
# 1. Drop and recreate the database with the correct character set
docker compose exec db \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "
    DROP DATABASE IF EXISTS \`${CA_DB_DATABASE}\`;
    CREATE DATABASE \`${CA_DB_DATABASE}\`
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${CA_DB_DATABASE}\`.* TO '${CA_DB_USER}'@'%';
    FLUSH PRIVILEGES;
  "

# 2. Import the dump
gunzip -c /path/to/dump.sql.gz \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"
```

### Large files — copy into the container first

Piping very large dumps (several GB) through stdin can be slow or prone to disconnection. Copying the file directly into the container is more reliable:

```bash
# Copy the dump file into the db container
docker compose cp /path/to/dump.sql.gz db:/tmp/dump.sql.gz

# Import inside the container (no pipe overhead)
docker compose exec db \
  bash -c "gunzip -c /tmp/dump.sql.gz \
    | mysql -uroot -p\"${MYSQL_ROOT_PASSWORD}\" \"${CA_DB_DATABASE}\""

# Clean up
docker compose exec db rm /tmp/dump.sql.gz
```

### With progress display

If you have [`pv`](https://www.ivarch.com/programs/pv.shtml) installed on the host, you can watch import progress:

```bash
# pv shows a progress bar, estimated time remaining, and throughput
pv /path/to/dump.sql \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"

# Compressed
pv /path/to/dump.sql.gz | gunzip \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"
```

### After importing — rebuild indexes

CollectiveAccess's full-text search index is stored separately from the MySQL data. After importing a dump you must rebuild it:

```bash
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index
```

---

## Restore from backup

Restoring a backup made with the `mysqldump` command above is the same as importing a dump:

```bash
gunzip -c backup-20240101-1200.sql.gz \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"

# Rebuild search index after restore
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index
```

---

## Drop and recreate the database

Useful for a completely fresh install after experimenting:

```bash
docker compose exec db \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "
    DROP DATABASE IF EXISTS \`${CA_DB_DATABASE}\`;
    CREATE DATABASE \`${CA_DB_DATABASE}\`
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${CA_DB_DATABASE}\`.* TO '${CA_DB_USER}'@'%';
    FLUSH PRIVILEGES;
  "
```

Then run the web installer again at `https://127.0.0.1/backend/install/`.

> Set `__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__ = true` in `overrides/providence/setup.php` if the installer refuses to run because it detects an existing installation. **Reset it to `false` afterwards.**
