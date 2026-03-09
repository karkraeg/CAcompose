# Customizing CAcompose

## File Override System

The override system lets you replace or add any file inside either container without modifying the Docker image or rebuilding.

### How it works

On every container start, the entrypoint script runs:

```bash
cp -r /overrides/{app}/. /var/www/{app}/
```

Files present in `overrides/` are copied over the corresponding paths in the app directory. New files (e.g. a theme folder) are added; existing files are replaced.

### Directory structure

```
overrides/
├── providence/          # Mirrors /var/www/providence/
│   └── app/
│       └── conf/
│           └── app.conf      ← replaces the built-in app.conf
└── pawtucket2/          # Mirrors /var/www/pawtucket2/
    ├── app/
    │   └── conf/
    │       └── app.conf      ← replaces the built-in app.conf
    └── themes/
        └── mytheme/          ← adds a new theme (see Themes section)
```

### Common override examples

**Replace a configuration file:**
```bash
# Get a copy of the original from the running container
docker compose cp providence:/var/www/providence/app/conf/app.conf \
    overrides/providence/app/conf/app.conf

# Edit it, then restart to apply
$EDITOR overrides/providence/app/conf/app.conf
docker compose restart providence
```

**Add a custom authentication adapter:**
```bash
# Place in the correct plugin directory — no rebuild needed
overrides/providence/app/lib/Auth/Adapters/MyLDAPAdapter.php
```

**Override setup.php entirely** (bypasses the getenv() template):
```bash
# Place a complete setup.php — it replaces the generated one
overrides/providence/setup.php
```

### Applying overrides after change

Overrides are applied at container **start**, so changes require restarting the container:

```bash
docker compose restart providence
# or
docker compose restart pawtucket2
```

**However**, if you also changed environment variables in `.env` (like `CA_PAWTUCKET2_THEME`), you must **recreate** the container instead:

```bash
docker compose up -d providence
# or
docker compose up -d pawtucket2
```

Verify the copy happened:
```bash
docker compose logs providence 2>&1 | grep -i override
# Should print: [entrypoint] Applying Providence overrides from /overrides/providence …
```

---

## Themes

Pawtucket2 ships with a `default` theme. Custom themes live in `themes/<theme-name>/`.

### Adding a custom theme

1. Copy the default theme from a running container as a starting point:
   ```bash
   docker compose cp pawtucket2:/var/www/pawtucket2/themes/default ./mytheme-base
   mkdir -p overrides/pawtucket2/themes/mytheme
   cp -r ./mytheme-base/. overrides/pawtucket2/themes/mytheme/
   ```

2. The theme directory structure:
   ```
   overrides/pawtucket2/themes/mytheme/
   ├── conf/
   │   └── theme.conf        ← theme metadata
   ├── views/                ← templates
   │   ├── Browse/
   │   ├── Detail/
   │   └── ...
   └── assets/
       ├── css/
       ├── js/
       └── img/
   ```

3. Activate the theme in `.env`:
   ```dotenv
   CA_PAWTUCKET2_THEME=mytheme
   ```

4. Recreate the Pawtucket2 container to apply the environment variable change:
   ```bash
   docker compose up -d pawtucket2
   ```
   
   **Note:** Environment variable changes require recreating the container with `up -d`, not just `restart`.

---

## Adding a Custom InformationService

CollectiveAccess **InformationServices** are plugins that let fields in Providence look up data from external authorities — Getty ULAN, VIAF, GeoNames, a custom REST API, etc. The looked-up data can auto-populate fields or link records to external URIs.

### Plugin file

Create the PHP class in your overrides directory:

```
overrides/providence/app/lib/Plugins/InformationService/
└── WLPlugInformationService_MyService.php
```

The filename and class name must follow the pattern `WLPlugInformationService_<Name>`.

**Minimal implementation:**

```php
<?php
/**
 * Custom InformationService that queries an internal REST API.
 * File: WLPlugInformationService_MyService.php
 */

require_once(__CA_LIB_DIR__ . '/Plugins/InformationService/BaseInformationServicePlugin.php');
require_once(__CA_LIB_DIR__ . '/Plugins/IWLPlugInformationService.php');

class WLPlugInformationService_MyService
    extends BaseInformationServicePlugin
    implements IWLPlugInformationService
{
    /** Settings exposed in the Providence UI for this service */
    static $s_settings = [];

    public function __construct($settings, $type) {
        parent::__construct($settings, $type);
    }

    /**
     * Human-readable name shown in the Providence UI.
     */
    public function getDisplayName(): string {
        return 'My Custom Authority Service';
    }

    /**
     * Perform a keyword lookup.
     *
     * @param  BaseModel  $t_instance  The record being edited
     * @param  array      $settings    Plugin settings (from $s_settings)
     * @param  string     $query       The search string typed by the user
     * @param  array|null $pa_data     Extra context (optional)
     * @return array  List of results: [['label'=>..., 'url'=>..., 'idno'=>...], ...]
     */
    public function lookup($t_instance, $settings, $query, $pa_data = null): array {
        $results = [];

        $url = 'https://api.example.com/authorities?q=' . urlencode($query);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $raw = curl_exec($ch);
        curl_close($ch);

        if (!$raw) { return $results; }

        $data = json_decode($raw, true);
        foreach (($data['items'] ?? []) as $item) {
            $results[] = [
                'label' => $item['preferred_label'],
                'url'   => $item['uri'],
                'idno'  => $item['id'],
                // 'description' => $item['note'],
            ];
        }

        return $results;
    }

    /**
     * Return extended information for a specific URI (shown when user
     * hovers over or selects a result).
     *
     * @return array  ['display' => '<html snippet>']
     */
    public function getExtendedInformation($t_instance, $settings, $url): array {
        $ch = curl_init($url . '/json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $raw = curl_exec($ch);
        curl_close($ch);

        $item = json_decode($raw, true) ?? [];
        $html = '<p><strong>' . htmlspecialchars($item['preferred_label'] ?? '') . '</strong></p>';
        if (!empty($item['note'])) {
            $html .= '<p>' . htmlspecialchars($item['note']) . '</p>';
        }

        return ['display' => $html];
    }
}
```

### Registering the service

Providence's `information_service.conf` maps **attribute types** to the list of available services. You need to add your plugin to that list.

1. Copy the original config from a running container:
   ```bash
   docker compose cp \
     providence:/var/www/providence/app/conf/information_service.conf \
     overrides/providence/app/conf/information_service.conf
   ```

2. Edit the copy and add your service name to the relevant type block:

   ```conf
   # overrides/providence/app/conf/information_service.conf

   ca_attribute_values_informationservice = {
       Entities = {
           sources = {
               ULAN       = { ... },
               VIAF       = { ... },
               MyService  = {
                   displayName = My Custom Authority Service,
                   url         = https://api.example.com,
               }
           }
       }
   }
   ```

   Consult the [CA documentation on InformationServices](https://docs.collectiveaccess.org/wiki/Information_Services) for the full list of attribute types and configuration keys.

3. Restart Providence to apply:
   ```bash
   docker compose restart providence
   ```

The service will now appear in the drop-down on any attribute field of the matching type when editing records in Providence.
