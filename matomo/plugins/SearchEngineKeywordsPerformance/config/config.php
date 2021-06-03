<?php

require dirname(dirname(__FILE__)) . '/vendor/autoload.php';

return [
    'Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google' => DI\autowire(),
    'diagnostics.optional'                              => DI\add([
        DI\get('Piwik\Plugins\SearchEngineKeywordsPerformance\Diagnostic\BingAccountDiagnostic'),
        DI\get('Piwik\Plugins\SearchEngineKeywordsPerformance\Diagnostic\GoogleAccountDiagnostic'),
        DI\get('Piwik\Plugins\SearchEngineKeywordsPerformance\Diagnostic\YandexAccountDiagnostic'),
    ]),
    // defines the number of days the plugin will try to import Google keywords for
    // Google API itself currently supports up to 500 days in the past
    'SearchEngineKeywordsPerformance.Google.ImportLastDaysMax' => 365
];