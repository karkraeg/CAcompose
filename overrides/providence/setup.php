<?php
# --------------------------------------------------------------------------------------------
#  Providence setup.php  —  reads configuration from environment variables.
#
#  All CA_* environment variables are set in docker-compose.yml / .env.
#  To override any value, either:
#    (a) edit .env, or
#    (b) place a custom setup.php in overrides/providence/setup.php
# --------------------------------------------------------------------------------------------

# Database
if (!defined('__CA_DB_HOST__'))     { define('__CA_DB_HOST__',     getenv('CA_DB_HOST')     ?: 'db');   }
if (!defined('__CA_DB_PORT__'))     { define('__CA_DB_PORT__',     getenv('CA_DB_PORT')     ?: '3306'); }
if (!defined('__CA_DB_USER__'))     { define('__CA_DB_USER__',     getenv('CA_DB_USER')     ?: '');     }
if (!defined('__CA_DB_PASSWORD__')) { define('__CA_DB_PASSWORD__', getenv('CA_DB_PASSWORD') ?: '');     }
if (!defined('__CA_DB_DATABASE__')) { define('__CA_DB_DATABASE__', getenv('CA_DB_DATABASE') ?: '');     }

# Application identity
if (!defined('__CA_APP_NAME__'))         { define('__CA_APP_NAME__',         'collectiveaccess'); }
if (!defined('__CA_APP_DISPLAY_NAME__')) { define('__CA_APP_DISPLAY_NAME__', getenv('CA_APP_DISPLAY_NAME') ?: 'My CollectiveAccess'); }
if (!defined('__CA_ADMIN_EMAIL__'))      { define('__CA_ADMIN_EMAIL__',      getenv('CA_ADMIN_EMAIL')      ?: 'admin@example.com');  }
if (!defined('__CA_AUTH_ADAPTER__'))     { define('__CA_AUTH_ADAPTER__',     'CaUsers'); }

# Locale / timezone
if (!defined('__CA_DEFAULT_LOCALE__')) { define('__CA_DEFAULT_LOCALE__', getenv('CA_DEFAULT_LOCALE') ?: 'en_US'); }
date_default_timezone_set(getenv('TZ') ?: 'UTC');

# URL scheme
# Set to 1 to enable clean (mod_rewrite) URLs.
# When running under /backend/ you must also update RewriteBase in
# /var/www/providence/.htaccess to: RewriteBase /backend
if (!defined('__CA_USE_CLEAN_URLS__')) { define('__CA_USE_CLEAN_URLS__', 0); }

# URL root path (the subdirectory under which CA is served)
# Since Providence is served under /backend/ via nginx proxy, set this explicitly
if (!defined('__CA_URL_ROOT__')) { define('__CA_URL_ROOT__', '/backend'); }

# Protocol (http or https)
# When running behind a reverse proxy with SSL termination, set this to https
if (!defined('__CA_SITE_PROTOCOL__')) { define('__CA_SITE_PROTOCOL__', 'https'); }

# Cache — Redis
if (!defined('__CA_CACHE_BACKEND__')) { define('__CA_CACHE_BACKEND__', getenv('CA_CACHE_BACKEND') ?: 'redis'); }
if (!defined('__CA_REDIS_HOST__'))    { define('__CA_REDIS_HOST__',    getenv('CA_REDIS_HOST')    ?: 'redis'); }
if (!defined('__CA_REDIS_PORT__'))    { define('__CA_REDIS_PORT__',    (int)(getenv('CA_REDIS_PORT') ?: 6379)); }
if (!defined('__CA_REDIS_DB__'))      { define('__CA_REDIS_DB__',      (int)(getenv('CA_REDIS_DB')   ?: 0));    }

# Background task queue (disabled by default; enable via CRON if needed)
if (!defined('__CA_QUEUE_ENABLED__')) { define('__CA_QUEUE_ENABLED__', 0); }

# Google Maps (optional)
if (!defined('__CA_GOOGLE_MAPS_KEY__')) { define('__CA_GOOGLE_MAPS_KEY__', getenv('CA_GOOGLE_MAPS_KEY') ?: ''); }

# Health-check endpoint key (null = disabled)
if (!defined('__CA_HC_INFO_KEY__')) { define('__CA_HC_INFO_KEY__', null); }

# SSL database connection (disabled by default)
if (!defined('__CA_DB_USE_SSL__'))          { define('__CA_DB_USE_SSL__',          false); }
if (!defined('__CA_DB_SSL_VERIFY_CERT__'))  { define('__CA_DB_SSL_VERIFY_CERT__',  true);  }
if (!defined('__CA_DB_SSL_KEY__'))          { define('__CA_DB_SSL_KEY__',          null);  }
if (!defined('__CA_DB_SSL_CERTIFICATE__'))  { define('__CA_DB_SSL_CERTIFICATE__',  null);  }
if (!defined('__CA_DB_SSL_CA_CERTIFICATE__')) { define('__CA_DB_SSL_CA_CERTIFICATE__', null); }
if (!defined('__CA_DB_SSL_CA_PATH__'))      { define('__CA_DB_SSL_CA_PATH__',      null);  }

# Security
if (!defined('__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__')) {
    define('__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__', false);
}
if (!defined('__CA_STACKTRACE_ON_EXCEPTION__')) {
    define('__CA_STACKTRACE_ON_EXCEPTION__', (bool)(getenv('CA_STACKTRACE_ON_EXCEPTION') ?: false));
}

require(__DIR__ . '/app/helpers/post-setup.php');
