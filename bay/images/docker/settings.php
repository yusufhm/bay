<?php

/**
 * @file
 * Bay Drupal configuration file.
 *
 * @var string $app_root
 *   The path to the application root.
 * @var string $site_path
 *   The active site directory for a request, relative to '$app_root'.
 * @var array $databases
 *   The database connections that Drupal may use.
 * @var array $settings
 *   Settings for read-only, low bootstrap, environment specific configuration.
 * @var array $config
 *   Configuration overrides.
 */

$bay_settings_path = __DIR__;
$settings_path = $app_root . DIRECTORY_SEPARATOR . 'sites/default';
$contrib_path = $app_root . DIRECTORY_SEPARATOR . (is_dir('modules/contrib') ? 'modules/contrib' : 'modules');
$lagoon_env_type = getenv('LAGOON_ENVIRONMENT_TYPE') ?: 'local';

// Database connection.
$connection_info = [
  'driver' => 'mysql',
  'database' => getenv('MARIADB_DATABASE') ?: 'drupal',
  'username' => getenv('MARIADB_USERNAME') ?: 'drupal',
  'password' => getenv('MARIADB_PASSWORD') ?: 'drupal',
  'host' => getenv('MARIADB_HOST') ?: 'mariadb',
  'port' => 3306,
  'prefix' => '',
];

$databases['default']['default'] = $connection_info;

// If Lagoon defines the DB_READREPLICA_HOSTS we add this to the available
// database connections. This allows core services to offload some
// queries to the replica.
//
// @see core/services.yml database.replica
if (getenv('DB_READREPLICA_HOSTS')) {
  $replica_hosts = explode(' ', getenv('DB_READREPLICA_HOSTS'));
  $replica_hosts = array_map('trim', $replica_hosts);

  // This allows --database=reader so Drush can be set to target the
  // reader (specifically for sql-dump and sql-sync).
  $database['reader']['default'] = array_merge(
    $connection_info,
    ['host' => $replica_hosts[0]]
  );

  foreach ($replica_hosts as $replica_host) {
    $databases['default']['replica'][] = array_merge(
      $connection_info,
      ['host' => $replica_host]
    );
  }

}

// Varnish & Reverse proxy settings.
$settings['reverse_proxy'] = TRUE;

$settings['update_free_access'] = FALSE;

// Defines where the sync folder of your configuration lives. In this case it's
// inside the Drupal root, which is protected by lagoon nginx configs,
// so it cannot be read via the browser. If your Drupal root is inside a
// subfolder (like 'web') you can put the config folder outside this subfolder
// for an advanced security measure: '../config/sync'.
if (defined('CONFIG_SYNC_DIRECTORY')) {
  $config_directories[CONFIG_SYNC_DIRECTORY] = '../config/sync';
}
$settings['config_sync_directory'] = '../config/sync';

// The default list of directories that will be ignored by Drupal's file API.
$settings['file_scan_ignore_directories'] = [
  'node_modules',
];

// The default number of entities to update in a batch process.
$settings['entity_update_batch_size'] = 50;

// Environment indicator settings.
$config['environment_indicator.indicator']['name'] = isset($settings['environment']) ? $settings['environment'] : 'local';
$config['environment_indicator.indicator']['bg_color'] = !empty($config['environment_indicator.indicator']['bg_color']) ? $config['environment_indicator.indicator']['bg_color'] : 'green';

// Disable local split.
$config['config_split.config_split.local']['status'] = FALSE;

// Set Stage File Proxy to use hotlink platform-wide
$config['stage_file_proxy.settings']['hotlink'] = TRUE;

// Skip unprotected files error.
$settings['skip_permissions_hardening'] = TRUE;

if (getenv('ENABLE_REDIS')) {
  $redis_host = getenv('REDIS_HOST') ?: 'redis';
  $redis_port = getenv('REDIS_SERVICE_PORT') ?: '6379';
  $redis_timeout = getenv('REDIS_TIMEOUT') ?: 2;
  $redis_password = getenv('REDIS_PASSWORD') ?: '';

  try {
    if (\Drupal\Core\Installer\InstallerKernel::installationAttempted()) {
      throw new \Exception('Drupal installation underway.');
    }

    $redis = new \Redis();

    if ($redis->connect($redis_host, $redis_port, $redis_timeout) === FALSE) {
      throw new \Exception('Redis service unreachable');
    }

    if (!empty($redis_password)) {
      $redis->auth($redis_password);
    }

    // Check the redis mode - to determine which interface we use to connect.
    $info = $redis->info();
    $redis_interface = 'PhpRedis';

    if (isset($info['redis_mode']) && $info['redis_mode'] == 'cluster' && class_exists('\Drupal\redis\Cache\PhpRedisCluster')) {
      $redis_interface = 'PhpRedisCluster';
    }

    if (getenv('REDIS_INTERFACE')) {
      // Allow environment variable override for the interface (eg. forcing
      // connection via a standalone pod).
      $redis_interface = getenv('REDIS_INTERFACE');
    }

    if (strpos($redis->ping(), 'PONG') === FALSE) {
      throw new \Exception('Redis reachable but is not responding correctly.');
    }

    $settings['redis.connection']['host'] = $redis_host;
    $settings['redis.connection']['port'] = $redis_port;
    $settings['redis.connection']['password'] = $redis_password;
    $settings['redis.connection']['base'] = 0;
    $settings['redis.connection']['interface'] = $redis_interface;
    $settings['redis.connection']['read_timeout'] = $redis_timeout;
    $settings['redis.connection']['timeout'] = $redis_timeout;
    $settings['cache']['default'] = 'cache.backend.redis';
    $settings['cache']['bins']['bootstrap'] = 'cache.backend.chainedfast';
    $settings['cache']['bins']['discovery'] = 'cache.backend.chainedfast';
    $settings['cache']['bins']['config'] = 'cache.backend.chainedfast';

    $settings['cache_prefix']['default'] = getenv('REDIS_CACHE_PREFIX') ?: getenv('LAGOON_PROJECT') . '_' . getenv('LAGOON_GIT_SAFE_BRANCH');

    if ($redis_interface == 'PhpRedisCluster') {
      $settings['redis.connection']['seeds'] = ["$redis_host:$redis_port"];
      $settings['container_yamls'][] = '/bay/redis-cluster.services.yml';
    } else {
      $settings['container_yamls'][] = '/bay/redis-single.services.yml';
    }

  } catch (\Exception $error) {
    // Make the reqeust unacacheable until redis is available.
    // This will ensure that cache partials are not added to separate bins,
    // Drupal is available even when Redis is down and that when redis is
    // available again we can start filling the correct bins up again.
    $settings['container_yamls'][] = '/bay/redis-unavailable.services.yml';
    $settings['cache']['default'] = 'cache.backend.null';
  }
}

// Expiration of cached pages on Varnish to 15 min
$config['system.performance']['cache']['page']['max_age'] = 900;

// Aggregate CSS files on
$config['system.performance']['css']['preprocess'] = 1;

// Aggregate JavaScript files on
$config['system.performance']['js']['preprocess'] = 1;

if ($smtp_on = getenv('ENABLE_SMTP')) {
  $config['smtp.settings']['smtp_on'] = (strtolower($smtp_on) == "true");
  $config['smtp.settings']['smtp_host'] = getenv('SMTP_HOST') ?: 'email-smtp.ap-southeast-2.amazonaws.com';
  $config['smtp.settings']['smtp_port'] = getenv('SMTP_PORT') ?: '587';
  $config['smtp.settings']['smtp_protocol'] = getenv('SMTP_PROTOCOL') ?: 'tls';
  $config['smtp.settings']['smtp_username'] = getenv('SMTP_USERNAME') ?: '';
  $config['smtp.settings']['smtp_password'] = getenv('SMTP_PASSWORD') ?: '';
  $config['smtp.settings']['smtp_timeout'] = getenv('SMTP_TIMEOUT') ?: 15;

  // @see baywatch.module for SMTP_REPLYTO setting.
  $config['system.site']['mail'] = getenv('SMTP_FROM') ?: 'admin@vic.gov.au';
}

/**
 * Fast 404 settings.
 *
 * Fast 404 will do two separate types of 404 checking.
 *
 * The first is to check for URLs which appear to be files or images. If Drupal
 * is handling these items, then they were not found in the file system and are
 * a 404.
 *
 * The second is to check whether or not the URL exists in Drupal by checking
 * with the menu router, aliases and redirects. If the page does not exist, we
 * will server a fast 404 error and exit.
 */
// Disallowed extensions. Any extension in here will not be served by Drupal and
// will get a fast 404. This will not affect actual files on the filesystem as
// requests hit them before defaulting to a Drupal request.
// Default extension list, this is considered safe and is even in queue for
// Drupal 8 (see: http://drupal.org/node/76824).
$settings['fast404_exts'] = '/^(?!robots).*\.(txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i';
// If you would prefer a stronger version of NO then return a 410 instead of a
// 404. This informs clients that not only is the resource currently not present
// but that it is not coming back and kindly do not ask again for it.
// Reference: http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
// $conf['fast_404_return_gone'] = TRUE;
// Allow anonymous users to hit URLs containing 'imagecache' even if the file
// does not exist. TRUE is default behavior. If you know all imagecache
// variations are already made set this to FALSE.
$settings['fast404_allow_anon_imagecache'] = TRUE;
// If you use FastCGI, uncomment this line to send the type of header it needs.
// Reference: http://php.net/manual/en/function.header.php
//$conf['fast_404_HTTP_status_method'] = 'FastCGI';
// BE CAREFUL with this setting as some modules
// use their own php files and you need to be certain they do not bootstrap
// Drupal. If they do, you will need to whitelist them too.
$conf['fast404_url_whitelisting'] = FALSE;
// Array of whitelisted files/urls. Used if whitelisting is set to TRUE.
$settings['fast404_whitelist'] = [
  'index.php',
  'rss.xml',
  'install.php',
  'cron.php',
  'update.php',
  'xmlrpc.php',
];
// Array of whitelisted URL fragment strings that conflict with fast404.
$settings['fast404_string_whitelisting'] = ['/advagg_'];
// Fast 404 error message.
$settings['fast404_html'] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>';
// Load the fast404.inc file. This is needed if you wish to do extension
// checking in settings.php.
if (file_exists($contrib_path . '/fast404/fast404.inc')) {
  include_once $contrib_path . '/fast404/fast404.inc';
  fast404_preboot($settings);
}

// Temp directory.
if (getenv('TMP')) {
  $config['system.file']['path']['temporary'] = getenv('TMP');
}

// Hash Salt.
$settings['hash_salt'] = hash('sha256', getenv('LAGOON_PROJECT'));

// Configure platformwide cache tag blacklists.
// These will be excluded when Drupal outputs the cache tag list
// and will not be forwarded to the reverse proxy.
$tag_list = [
  '4xx-response',
  'theme_registry',
  'route_match',
  'routes',
  'config:filter.format',
  'config:filter.settings',
  'config:jsonapi_extras',
  'config:jsonapi_resource_config_list',
  'config:paragraphs',
  'config:system.menu.site-main',
  'config:user.role.anonymous',
  'extensions',
  'media_view',
  'http_response',
];

// Allow projects to extend the blacklist.
$tag_additions = getenv('SDP_CACHE_TAG_BLACKLIST');

if (!empty($tag_additions)) {
  $tag_additions = explode(',', $tag_additions);
  $tag_additions = array_map('trim', $tag_additions);
  $tag_list = array_merge($tag_list, $tag_additions);
}

// Allow projects to remove tags from the blacklist.
$tag_whitelist = getenv('SDP_CACHE_TAG_WHITELIST');

if (!empty($tag_whitelist)) {
  $tag_whitelist = explode(',', $tag_whitelist);
  $tag_whitelist = array_map('trim', $tag_whitelist);
  $tag_list = array_diff($tag_list, $tag_whitelist);
}

$config['purge_queuer_coretags.settings']['blacklist'] = $tag_list;

// Configure ClamAV connections.
$clamav_scan = getenv('CLAMAV_SCANMODE') ?: 0;
$clamav_host = getenv('CLAMAV_HOST') ?: 'clamav.sdp-central-clamav-master.svc.cluster.local';
$clamav_port = getenv('CLAMAV_PORT') ?: 3000;

if ($lagoon_env_type == 'local') {
  $clamav_host = getenv('CLAMAV_HOST') ?: 'clamav';
}

$config['clamav.settings']['scan_mode'] = $clamav_scan;
$config['clamav.settings']['mode_daemon_tcpip']['hostname'] = $clamav_host;
$config['clamav.settings']['mode_daemon_tcpip']['port'] = $clamav_port;

// Configure elasticsearch connections from environment variables.
if (getenv('SEARCH_HASH') && getenv('SEARCH_URL')) {
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['url'] = sprintf('http://%s.%s', getenv('SEARCH_HASH'), getenv('SEARCH_URL'));
} else {
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['url'] =  "http://elasticsearch:9200";
}

if (getenv('SEARCH_INDEX')) {
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['rewrite']['rewrite_index'] = 1;
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['rewrite']['index'] = [
    'prefix' => getenv('SEARCH_INDEX'),
    'suffix' => '',
  ];
} else {
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['rewrite']['index'] = [
    'prefix' => 'elasticsearch_index_default_',
    'suffix' => '',
  ];
}

if (getenv('SEARCH_AUTH_USERNAME') && getenv('SEARCH_AUTH_PASSWORD')) {
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['username'] = getenv('SEARCH_AUTH_USERNAME');
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['password'] = getenv('SEARCH_AUTH_PASSWORD');
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['use_authentication'] = 1;
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['authentication_type'] = 'Basic';
} else {
  $config['elasticsearch_connector.cluster.elasticsearch_bay']['options']['use_authentication'] = 0;
}

////////////////////////////////////////////////////////////////////////////////
//////////////////////// PER-ENVIRONMENT SETTINGS //////////////////////////////
////////////////////////////////////////////////////////////////////////////////

// Include Bay services.
if (file_exists($bay_settings_path . '/services.yml')) {
  $settings['container_yamls'][] = $bay_settings_path . '/services.yml';
}

// Include environment specific settings and services files.
if (getenv('LAGOON_ENVIRONMENT_TYPE')) {
  if (file_exists($settings_path . '/envs/' . getenv('LAGOON_ENVIRONMENT_TYPE') . '/settings.php')) {
    include $settings_path . '/envs/' . getenv('LAGOON_ENVIRONMENT_TYPE') . '/settings.php';
  }
  if (file_exists($settings_path . '/envs/' . getenv('LAGOON_ENVIRONMENT_TYPE') . '/services.yml')) {
    $settings['container_yamls'][] = $settings_path . '/envs/' . getenv('LAGOON_ENVIRONMENT_TYPE') . '/services.yml';
  }
}

// Include branch specific settings and services files.
if (getenv('LAGOON_GIT_SAFE_BRANCH')) {
  if (file_exists($settings_path . '/branch/' . getenv('LAGOON_GIT_SAFE_BRANCH') . '/settings.php')) {
    include $settings_path . '/branch/' . getenv('LAGOON_GIT_SAFE_BRANCH') . '/settings.php';
  }
  if (file_exists($settings_path . '/branch/' . getenv('LAGOON_GIT_SAFE_BRANCH') . '/services.yml')) {
    $settings['container_yamls'][] = $settings_path . '/branch/' . getenv('LAGOON_GIT_SAFE_BRANCH') . '/services.yml';
  }
}
