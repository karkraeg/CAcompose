# Importing Data

## caUtils CLI Reference

`caUtils` is CollectiveAccess's built-in command-line tool for importing data, reindexing, managing users, processing media, and more. It lives at `support/bin/caUtils` inside both the Providence and Pawtucket2 containers, but is most useful in Providence where the full data model and admin privileges live.

### Convenience alias

Typing the full path every time is tedious. Add a shell alias for the current project:

```bash
# Paste into your shell session, or add to ~/.zshrc / ~/.bashrc
alias cautils='docker compose exec providence php /var/www/providence/support/bin/caUtils'
```

Then all examples below can be shortened, e.g. `cautils help`.

> The alias only works when your working directory is the project root (where `docker-compose.yml` lives), because `docker compose` resolves the project from the current directory.

### Passing files into the container

Many caUtils commands read from a file. Two patterns work:

**1. Pipe via stdin** — use `-T` to disable TTY allocation, then redirect with `<`:

```bash
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX --target=ca_objects --mapping=my_mapping \
  < /path/on/host/data.xlsx
```

**2. Copy the file into the container first** — better for large files or when you want to keep the file around:

```bash
docker compose cp /path/on/host/data.xlsx providence:/tmp/data.xlsx

docker compose exec providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX --target=ca_objects --mapping=my_mapping \
    --source=/tmp/data.xlsx

# Clean up afterwards
docker compose exec providence rm /tmp/data.xlsx
```

### Command reference

#### Getting help

```bash
# List every available command with a one-line description
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils help

# Full help for a specific command (options, flags, examples)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils help import-data
```

#### Search index

```bash
# Rebuild the entire search index (run after bulk imports or data migrations)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index

# Rebuild index for a single table only
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index \
    --table=ca_objects
```

#### Media processing

```bash
# Process the background task queue (media transcoding, derivative generation, etc.)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils process-task-queue

# Check media files for missing or broken derivatives
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils check-media \
    --table=ca_objects

# Regenerate all media derivatives (thumbnails, previews) for a table
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils reload-object-checksums \
    --table=ca_objects --regenerate-derivatives
```

#### Importing data

```bash
# Import from an Excel file
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX \
    --target=ca_objects \
    --mapping=my_mapping_code \
  < /path/to/data.xlsx

# Import from a CSV file
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=CSV \
    --target=ca_objects \
    --mapping=my_mapping_code \
  < /path/to/data.csv

# Import MARC records
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=MARC21 \
    --target=ca_objects \
    --mapping=my_mapping_code \
  < /path/to/records.mrc

# Import with verbose output (useful for debugging a mapping)
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX \
    --target=ca_objects \
    --mapping=my_mapping_code \
    --log-level=DEBUG \
  < /path/to/data.xlsx
```

Supported `--format` values include: `XLSX`, `XLS`, `CSV`, `TAB`, `MARC21`, `MARCXML`, `OAI_DC`, `RDF`, `JSON`, `CollectiveAccessXML`, and more. Run `cautils help import-data` for the complete list.

#### Exporting data

```bash
# Export all objects to CSV (file written inside the container)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils export-data \
    --target=ca_objects \
    --format=CSV \
    --filename=/tmp/objects-export.csv

# Copy the result out to the host
docker compose cp providence:/tmp/objects-export.csv ./objects-export.csv
```

#### Searching records

```bash
# Search for records and print a summary
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils find \
    --table=ca_objects \
    --search="venice"

# Find a specific record by ID
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils find \
    --table=ca_objects \
    --id=42
```

#### User management

```bash
# Create a new administrator account
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils create-login \
    --username=newuser \
    --password=s3cret \
    --email=newuser@example.com \
    --fname=Jane \
    --lname=Doe \
    --roles=administrator

# Reset a forgotten password
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils reset-password \
    --username=admin \
    --password=newpassword
```

#### Database / data integrity

```bash
# Check database structure and report problems
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils repair-database

# Find and report duplicate labels
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils check-for-duplicate-labels \
    --table=ca_objects

# Permanently remove records that were soft-deleted
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils remove-deleted-records \
    --table=ca_objects
```

---

## Batch Media Import

Providence's **media importer** scans a staging directory for files to import in bulk. The default location inside the container is `/var/www/providence/import/`.

### Configuration

Set `IMPORT_PATH` in `.env` to any host directory you want to use as the staging area:

```dotenv
IMPORT_PATH=./import          # relative to docker-compose.yml (default)
# or an absolute path:
IMPORT_PATH=/mnt/nas/ca-import
```

The directory is bind-mounted read-write into the Providence container at `/var/www/providence/import/`. No `app.conf` override is needed — CA's default value for `batch_media_import_root_directory` already points to this path.

### Workflow

1. Drop files into `$IMPORT_PATH` (or the `./import/` folder):
   ```bash
   cp /path/to/images/*.jpg ./import/
   ```

2. In the Providence admin UI: **Media → Media importer → Import from directory**.

3. The importer lists everything it finds under the staging directory. Select files and run the import job.

### Tips

- Sub-directories are supported — organize files into folders before importing.
- Files are **not deleted** after import; remove them manually once done.
- The import directory is mounted in Providence only (Pawtucket2 has no importer).
