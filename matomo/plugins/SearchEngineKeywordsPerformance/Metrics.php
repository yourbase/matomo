<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\SearchEngineKeywordsPerformance;

use Piwik\Piwik;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Bing as BingArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Yandex as YandexArchiver;

/**
 * Defines Metrics used in SearchEngineKeywordsPerformance plugin
 */
class Metrics
{
    const NB_CLICKS      = 'nb_clicks';
    const NB_IMPRESSIONS = 'nb_impressions';
    const CTR            = 'ctr';
    const POSITION       = 'position';
    const NB_PAGES       = 'nb_pages';

    /**
     * Returns list of available keyword metrics
     *
     * @return array
     */
    public static function getKeywordMetrics()
    {
        return [
            self::NB_CLICKS,
            self::NB_IMPRESSIONS,
            self::CTR,
            self::POSITION,
        ];
    }

    /**
     * Returns metric translations
     *
     * @return array
     */
    public static function getMetricsTranslations()
    {
        return [
            self::NB_CLICKS      => Piwik::translate('SearchEngineKeywordsPerformance_Clicks'),
            self::NB_IMPRESSIONS => Piwik::translate('SearchEngineKeywordsPerformance_Impressions'),
            self::CTR            => Piwik::translate('SearchEngineKeywordsPerformance_Ctr'),
            self::POSITION       => Piwik::translate('SearchEngineKeywordsPerformance_Position'),
        ];
    }

    /**
     * Return metric documentations
     *
     * @return array
     */
    public static function getMetricsDocumentation()
    {
        return [
            self::NB_CLICKS      => Piwik::translate('SearchEngineKeywordsPerformance_ClicksDocumentation'),
            self::NB_IMPRESSIONS => Piwik::translate('SearchEngineKeywordsPerformance_ImpressionsDocumentation'),
            self::CTR            => Piwik::translate('SearchEngineKeywordsPerformance_CtrDocumentation'),
            self::POSITION       => Piwik::translate('SearchEngineKeywordsPerformance_PositionDocumentation'),

            BingArchiver::CRAWLSTATS_OTHER_CODES_RECORD_NAME    => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlStatsOtherCodesDesc'),
            BingArchiver::CRAWLSTATS_BLOCKED_ROBOTS_RECORD_NAME => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlBlockedByRobotsTxtDesc'),
            BingArchiver::CRAWLSTATS_CODE_2XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus2xxDesc'),
            BingArchiver::CRAWLSTATS_CODE_301_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus301Desc'),
            BingArchiver::CRAWLSTATS_CODE_302_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus302Desc'),
            BingArchiver::CRAWLSTATS_CODE_4XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus4xxDesc'),
            BingArchiver::CRAWLSTATS_CODE_5XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlHttpStatus5xxDesc'),
            BingArchiver::CRAWLSTATS_TIMEOUT_RECORD_NAME        => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlConnectionTimeoutDesc'),
            BingArchiver::CRAWLSTATS_MALWARE_RECORD_NAME        => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlMalwareInfectedDesc'),
            BingArchiver::CRAWLSTATS_ERRORS_RECORD_NAME         => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlErrorsDesc'),
            BingArchiver::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME  => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlCrawledPagesDesc'),
            BingArchiver::CRAWLSTATS_DNS_FAILURE_RECORD_NAME    => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlDNSFailuresDesc'),
            BingArchiver::CRAWLSTATS_IN_INDEX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlPagesInIndexDesc'),
            BingArchiver::CRAWLSTATS_IN_LINKS_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_BingCrawlInboundLinkDesc'),

            YandexArchiver::CRAWLSTATS_IN_INDEX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlInIndexDesc'),
            YandexArchiver::CRAWLSTATS_APPEARED_PAGES_RECORD_NAME => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlAppearedPagesDesc'),
            YandexArchiver::CRAWLSTATS_REMOVED_PAGES_RECORD_NAME  => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlRemovedPagesDesc'),
            YandexArchiver::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME  => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlCrawledPagesDesc'),
            YandexArchiver::CRAWLSTATS_CODE_2XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlHttpStatus2xxDesc'),
            YandexArchiver::CRAWLSTATS_CODE_3XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlHttpStatus3xxDesc'),
            YandexArchiver::CRAWLSTATS_CODE_4XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlHttpStatus4xxDesc'),
            YandexArchiver::CRAWLSTATS_CODE_5XX_RECORD_NAME       => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlHttpStatus5xxDesc'),
            YandexArchiver::CRAWLSTATS_ERRORS_RECORD_NAME         => Piwik::translate('SearchEngineKeywordsPerformance_YandexCrawlErrorsDesc'),
        ];
    }

    public static function getMetricIdsToProcessReportTotal()
    {
        return [
            self::NB_CLICKS,
            self::NB_IMPRESSIONS
        ];
    }

    /**
     * Returns operations used to aggregate the metric columns
     *
     * @return array
     */
    public static function getColumnsAggregationOperations()
    {
        /*
         * Calculate average CTR based on summed impressions and summed clicks
         */
        $calcCtr = function ($val1, $val2, $thisRow, $rowToSum) {
            $sumImpressions = $thisRow->getColumn(Metrics::NB_IMPRESSIONS) + $rowToSum->getColumn(Metrics::NB_IMPRESSIONS);
            $sumClicks      = $thisRow->getColumn(Metrics::NB_CLICKS) + $rowToSum->getColumn(Metrics::NB_CLICKS);
            if (!$sumImpressions) {
                return 0.0;
            }
            return round($sumClicks / $sumImpressions, 2);
        };

        /*
         * Calculate average position based on impressions and positions
         */
        $calcPosition = function ($val1, $val2, $thisRow, $rowToSum) {
            return round((($thisRow->getColumn(Metrics::NB_IMPRESSIONS) * $thisRow->getColumn(Metrics::POSITION)) +
                    ($rowToSum->getColumn(Metrics::NB_IMPRESSIONS) * $rowToSum->getColumn(Metrics::POSITION))) /
                ($thisRow->getColumn(Metrics::NB_IMPRESSIONS) + $rowToSum->getColumn(Metrics::NB_IMPRESSIONS)), 2);
        };

        return [
            Metrics::CTR      => $calcCtr,
            Metrics::POSITION => $calcPosition,
        ];
    }
}