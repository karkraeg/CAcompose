<?php
# --------------------------------------------------------------------------------------------
#  Pawtucket2 setup.php  —  reads configuration from environment variables.
#
#  All CA_* environment variables are set in docker-compose.yml / .env.
#  To override any value, either:
#    (a) edit .env, or
#    (b) place a custom setup.php in overrides/pawtucket2/setup.php
# --------------------------------------------------------------------------------------------

# Database (shared with Providence — same database, different app layer)
if (!defined('__CA_DB_HOST__'))     { define('__CA_DB_HOST__',     getenv('CA_DB_HOST')     ?: 'db');   }
if (!defined('__CA_DB_PORT__'))     { define('__CA_DB_PORT__',     getenv('CA_DB_PORT')     ?: '3306'); }
if (!defined('__CA_DB_USER__'))     { define('__CA_DB_USER__',     getenv('CA_DB_USER')     ?: '');     }
if (!defined('__CA_DB_PASSWORD__')) { define('__CA_DB_PASSWORD__', getenv('CA_DB_PASSWORD') ?: '');     }
if (!defined('__CA_DB_DATABASE__')) { define('__CA_DB_DATABASE__', getenv('CA_DB_DATABASE') ?: '');     }

# Application identity
if (!defined('__CA_APP_NAME__'))         { define('__CA_APP_NAME__',         'collectiveaccess'); }
if (!defined('__CA_APP_DISPLAY_NAME__')) { define('__CA_APP_DISPLAY_NAME__', getenv('CA_APP_DISPLAY_NAME') ?: 'My CollectiveAccess'); }
if (!defined('__CA_ADMIN_EMAIL__'))      { define('__CA_ADMIN_EMAIL__',      getenv('CA_ADMIN_EMAIL')      ?: 'admin@example.com');  }

# Locale / timezone
if (!defined('__CA_DEFAULT_LOCALE__')) { define('__CA_DEFAULT_LOCALE__', getenv('CA_DEFAULT_LOCALE') ?: 'en_US'); }
date_default_timezone_set(getenv('TZ') ?: 'UTC');

# URL scheme
if (!defined('__CA_USE_CLEAN_URLS__')) { define('__CA_USE_CLEAN_URLS__', 0); }

# Cache — Redis
if (!defined('__CA_CACHE_BACKEND__')) { define('__CA_CACHE_BACKEND__', getenv('CA_CACHE_BACKEND') ?: 'redis'); }
if (!defined('__CA_REDIS_HOST__'))    { define('__CA_REDIS_HOST__',    getenv('CA_REDIS_HOST')    ?: 'redis'); }
if (!defined('__CA_REDIS_PORT__'))    { define('__CA_REDIS_PORT__',    (int)(getenv('CA_REDIS_PORT') ?: 6379)); }
if (!defined('__CA_REDIS_DB__'))      { define('__CA_REDIS_DB__',      (int)(getenv('CA_REDIS_DB')   ?: 1));    }

# Background task queue
if (!defined('__CA_QUEUE_ENABLED__')) { define('__CA_QUEUE_ENABLED__', 0); }

# Google reCAPTCHA (for contact forms — optional)
if (!defined('__CA_GOOGLE_RECAPTCHA_KEY__')) {
    define('__CA_GOOGLE_RECAPTCHA_KEY__', getenv('CA_GOOGLE_RECAPTCHA_KEY') ?: '');
}
if (!defined('__CA_GOOGLE_RECAPTCHA_SECRET_KEY__')) {
    define('__CA_GOOGLE_RECAPTCHA_SECRET_KEY__', getenv('CA_GOOGLE_RECAPTCHA_SECRET_KEY') ?: '');
}

# SSL database connection (disabled by default)
if (!defined('__CA_DB_USE_SSL__'))            { define('__CA_DB_USE_SSL__',            false); }
if (!defined('__CA_DB_SSL_VERIFY_CERT__'))    { define('__CA_DB_SSL_VERIFY_CERT__',    true);  }
if (!defined('__CA_DB_SSL_KEY__'))            { define('__CA_DB_SSL_KEY__',            null);  }
if (!defined('__CA_DB_SSL_CERTIFICATE__'))    { define('__CA_DB_SSL_CERTIFICATE__',    null);  }
if (!defined('__CA_DB_SSL_CA_CERTIFICATE__')) { define('__CA_DB_SSL_CA_CERTIFICATE__', null);  }
if (!defined('__CA_DB_SSL_CA_PATH__'))        { define('__CA_DB_SSL_CA_PATH__',        null);  }

# Security
if (!defined('__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__')) {
    define('__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__', false);
}
if (!defined('__CA_STACKTRACE_ON_EXCEPTION__')) {
    define('__CA_STACKTRACE_ON_EXCEPTION__', (bool)(getenv('CA_STACKTRACE_ON_EXCEPTION') ?: false));
}

# ── Theme configuration ────────────────────────────────────────────────────────
# Set the active theme via CA_PAWTUCKET2_THEME in .env (default: "default").
# To add a custom theme, place your theme folder under:
#   overrides/pawtucket2/themes/your-theme-name/
# Then set CA_PAWTUCKET2_THEME=your-theme-name in .env.
$_CA_THEMES_BY_DEVICE = [
    '_default_' => (getenv('CA_PAWTUCKET2_THEME') ?: 'default'),
];

require(__DIR__ . '/app/helpers/post-setup.php');
