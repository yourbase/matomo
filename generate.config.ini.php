<?php

$db = parse_url(getenv('JAWSDB_MARIA_URL'));
$host = $db['host'];
$user = $db['user'];
$pass = $db['pass'];
$port = $db['port'];
$dbname = trim($db['path'], '/');

$redis = parse_url(getenv('REDIS_URL'));
$redis_host = $redis['host'];
$redis_port = $redis['port'];
$redis_pass = $redis['pass'];

$maintenance_mode = empty(getenv('MAINTENANCE_MODE')) ? '0' : '1';
$record_statistics = empty(getenv('DISABLE_TRACKING')) ? '1' : '0';

$salt = getenv('SALT');
$trusted_hosts = getenv('TRUSTED_HOSTS');
if (empty($trusted_hosts)) {
  $trusted_hosts = getenv('TRUSTED_HOST');
}
if (strpos($trusted_hosts, 'localhost') === false) {
  $secure_protocol = '1';
} else {
  $secure_protocol = '0';
}
if (strpos($host, 'rds.amazonaws.com') !== false) {
  exec("curl -f https://s3.amazonaws.com/rds-downloads/rds-combined-ca-bundle.pem -o ".__DIR__."/rds-combined-ca-bundle.pem");
  $enable_ssl = '1';
  $ssl_ca = "ssl_ca = ".__DIR__."/rds-combined-ca-bundle.pem";
} else {
  $enable_ssl = '0';
  $ssl_ca = '';
}

$trusted_hosts_lines = implode("\n", array_map(function($host) {
  return 'trusted_hosts[] = "'.$host.'"';
}, explode(',', $trusted_hosts)));

$contents = <<<EOD
[database]
host = "$host"
username = "$user"
password = "$pass"
dbname = "$dbname"
port = $port
tables_prefix = "matomo_"
enable_ssl = $enable_ssl
$ssl_ca

[Tracker]
record_statistics = $record_statistics

[General]
maintenance_mode = $maintenance_mode
browser_archiving_disabled_enforce = 1
enable_processing_unique_visitors_year = 1
secure_protocol = $secure_protocol
force_ssl = $secure_protocol
enable_trusted_host_check=0
multi_server_environment = $secure_protocol
proxy_client_headers[] = HTTP_X_FORWARDED_FOR

[Plugins]
Plugins[] = "CorePluginsAdmin"
Plugins[] = "CoreAdminHome"
Plugins[] = "CoreHome"
Plugins[] = "WebsiteMeasurable"
Plugins[] = "IntranetMeasurable"
Plugins[] = "Diagnostics"
Plugins[] = "CoreVisualizations"
Plugins[] = "Proxy"
Plugins[] = "API"
Plugins[] = "ExamplePlugin"
Plugins[] = "Widgetize"
Plugins[] = "Transitions"
Plugins[] = "LanguagesManager"
Plugins[] = "Actions"
Plugins[] = "Dashboard"
Plugins[] = "MultiSites"
Plugins[] = "Referrers"
Plugins[] = "UserLanguage"
Plugins[] = "DevicesDetection"
Plugins[] = "Goals"
Plugins[] = "Ecommerce"
Plugins[] = "SEO"
Plugins[] = "Events"
Plugins[] = "UserCountry"
Plugins[] = "VisitsSummary"
Plugins[] = "VisitFrequency"
Plugins[] = "VisitTime"
Plugins[] = "VisitorInterest"
Plugins[] = "ExampleAPI"
Plugins[] = "RssWidget"
Plugins[] = "Feedback"
Plugins[] = "Monolog"
Plugins[] = "Login"
Plugins[] = "TwoFactorAuth"
Plugins[] = "UsersManager"
Plugins[] = "SitesManager"
Plugins[] = "Installation"
Plugins[] = "CoreUpdater"
Plugins[] = "CoreConsole"
Plugins[] = "ScheduledReports"
Plugins[] = "UserCountryMap"
Plugins[] = "Live"
Plugins[] = "CustomVariables"
Plugins[] = "PrivacyManager"
Plugins[] = "ImageGraph"
Plugins[] = "Annotations"
Plugins[] = "MobileMessaging"
Plugins[] = "Overlay"
Plugins[] = "SegmentEditor"
Plugins[] = "Insights"
Plugins[] = "Morpheus"
Plugins[] = "Contents"
Plugins[] = "BulkTracking"
Plugins[] = "Resolution"
Plugins[] = "DevicePlugins"
Plugins[] = "Heartbeat"
Plugins[] = "Intl"
Plugins[] = "Marketplace"
Plugins[] = "ProfessionalServices"
Plugins[] = "UserId"
Plugins[] = "CustomJsTracker"
Plugins[] = "InvalidateReports"
Plugins[] = "MarketingCampaignsReporting"
Plugins[] = "GeoIp2"
Plugins[] = "QueuedTracking"

[PluginsInstalled]
PluginsInstalled[] = "Diagnostics"
PluginsInstalled[] = "Login"
PluginsInstalled[] = "CoreAdminHome"
PluginsInstalled[] = "UsersManager"
PluginsInstalled[] = "SitesManager"
PluginsInstalled[] = "Installation"
PluginsInstalled[] = "Monolog"
PluginsInstalled[] = "Intl"
PluginsInstalled[] = "CorePluginsAdmin"
PluginsInstalled[] = "CoreHome"
PluginsInstalled[] = "WebsiteMeasurable"
PluginsInstalled[] = "CoreVisualizations"
PluginsInstalled[] = "Proxy"
PluginsInstalled[] = "API"
PluginsInstalled[] = "ExamplePlugin"
PluginsInstalled[] = "Widgetize"
PluginsInstalled[] = "Transitions"
PluginsInstalled[] = "LanguagesManager"
PluginsInstalled[] = "Actions"
PluginsInstalled[] = "Dashboard"
PluginsInstalled[] = "MultiSites"
PluginsInstalled[] = "Referrers"
PluginsInstalled[] = "UserLanguage"
PluginsInstalled[] = "DevicesDetection"
PluginsInstalled[] = "Goals"
PluginsInstalled[] = "Ecommerce"
PluginsInstalled[] = "SEO"
PluginsInstalled[] = "Events"
PluginsInstalled[] = "UserCountry"
PluginsInstalled[] = "VisitsSummary"
PluginsInstalled[] = "VisitFrequency"
PluginsInstalled[] = "VisitTime"
PluginsInstalled[] = "VisitorInterest"
PluginsInstalled[] = "ExampleAPI"
PluginsInstalled[] = "RssWidget"
PluginsInstalled[] = "Feedback"
PluginsInstalled[] = "CoreUpdater"
PluginsInstalled[] = "CoreConsole"
PluginsInstalled[] = "ScheduledReports"
PluginsInstalled[] = "UserCountryMap"
PluginsInstalled[] = "Live"
PluginsInstalled[] = "CustomVariables"
PluginsInstalled[] = "PrivacyManager"
PluginsInstalled[] = "ImageGraph"
PluginsInstalled[] = "Annotations"
PluginsInstalled[] = "MobileMessaging"
PluginsInstalled[] = "Overlay"
PluginsInstalled[] = "SegmentEditor"
PluginsInstalled[] = "Insights"
PluginsInstalled[] = "Morpheus"
PluginsInstalled[] = "Contents"
PluginsInstalled[] = "BulkTracking"
PluginsInstalled[] = "Resolution"
PluginsInstalled[] = "DevicePlugins"
PluginsInstalled[] = "Heartbeat"
PluginsInstalled[] = "Marketplace"
PluginsInstalled[] = "ProfessionalServices"
PluginsInstalled[] = "UserId"
PluginsInstalled[] = "CustomJsTracker"
PluginsInstalled[] = "InvalidateReports"
PluginsInstalled[] = "MarketingCampaignsReporting"
PluginsInstalled[] = "IntranetMeasurable"
PluginsInstalled[] = "TwoFactorAuth"
PluginsInstalled[] = "GeoIp2"
EOD;


file_put_contents(__DIR__.'/matomo/config/config.ini.php', $contents);

?>
