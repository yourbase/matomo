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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver;

use Piwik\ArchiveProcessor;
use Piwik\Config as PiwikConfig;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Metrics;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Yandex as YandexModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Importer\Yandex as YandexImporter;
use Piwik\DataTable;
use Piwik\Log;

/**
 * Archiver for Yandex
 *
 * @see PluginsArchiver
 */
class Yandex extends \Piwik\Plugin\Archiver
{
    /**
     * Key used for archives
     */
    const KEYWORDS_YANDEX_RECORD_NAME = 'SearchEngineKeywordsPerformance_yandex_keywords';

    /**
     * Keys used for crawl stats archives / metrics
     */
    const CRAWLSTATS_IN_INDEX_RECORD_NAME      = 'SearchEngineKeywordsPerformance_yandex_crawlstats_inindex';
    const CRAWLSTATS_APPEARED_PAGES_RECORD_NAME      = 'SearchEngineKeywordsPerformance_yandex_crawlstats_appeared';
    const CRAWLSTATS_REMOVED_PAGES_RECORD_NAME      = 'SearchEngineKeywordsPerformance_yandex_crawlstats_removed';
    const CRAWLSTATS_CODE_2XX_RECORD_NAME      = 'SearchEngineKeywordsPerformance_yandex_crawlstats_code_2xx';
    const CRAWLSTATS_CODE_3XX_RECORD_NAME      = 'SearchEngineKeywordsPerformance_yandex_crawlstats_code_3xx';
    const CRAWLSTATS_CODE_4XX_RECORD_NAME      = 'SearchEngineKeywordsPerformance_yandex_crawlstats_code_4xx';
    const CRAWLSTATS_CODE_5XX_RECORD_NAME      = 'SearchEngineKeywordsPerformance_yandex_crawlstats_code_5xx';
    const CRAWLSTATS_ERRORS_RECORD_NAME        = 'SearchEngineKeywordsPerformance_yandex_crawlstats_errors';
    const CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME = 'SearchEngineKeywordsPerformance_yandex_crawlstats_crawledpages';

    /**
     * @var MeasurableSettings
     */
    protected $settings = null;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $parameters        = $this->getProcessor()->getParams();
        $site              = $parameters->getSite();
        $this->settings    = new MeasurableSettings($site->getId(), $site->getType());
        $this->maximumRows = PiwikConfig::getInstance()->General['datatable_archiving_maximum_rows_referrers'];
    }

    /**
     * Returns whether this archiver should be used
     *
     * @return bool
     */
    public function isEnabled()
    {
        $parameters = $this->getProcessor()->getParams();
        $segment    = $parameters->getSegment();

        if (!$segment->isEmpty()) {
            Log::debug("Skip Archiving for SearchEngineKeywordsPerformance plugin for segments");
            return false; // do not archive data for segments
        }

        $yandexConfig = $this->settings->yandexAccountAndHostId;

        if (empty($yandexConfig) || !$yandexConfig->getValue() || false === strpos($yandexConfig->getValue(), '##')) {
            return false; // yandex api not activated for that site
        }

        return true;
    }

    /**
     * Aggregate data for day reports
     */
    public function aggregateDayReport()
    {
        if (!$this->isEnabled()) {
            return;
        }

        $parameters = $this->getProcessor()->getParams();
        $date       = $parameters->getDateStart()->setTimezone('UTC')->toString('Y-m-d');

        $yandexConfig = $this->settings->yandexAccountAndHostId;

        [$accountId, $hostId] = explode('##', $yandexConfig->getValue());

        Log::debug("[SearchEngineKeywordsPerformance] Archiving yandex records for $date and $hostId");

        $dataTable = $this->getKeywordsAsDataTable($hostId, $date);

        if (empty($dataTable)) {
            // ensure data is present (if available)
            YandexImporter::importAvailableDataForDate($accountId, $hostId, $date);
            $dataTable = $this->getKeywordsAsDataTable($hostId, $date);
        }

        if (!empty($dataTable)) {
            Log::debug("[SearchEngineKeywordsPerformance] Archiving yandex keywords for $date and $hostId");

            $report = $dataTable->getSerialized($this->maximumRows, null, Metrics::NB_CLICKS);
            unset($dataTable);
            $this->getProcessor()->insertBlobRecord(self::KEYWORDS_YANDEX_RECORD_NAME, $report);
        }

        $this->archiveDayCrawlStatNumerics($hostId, $date);
    }

    /**
     * Returns keyword data for given parameters as DataTable
     *
     * @param string $hostId  host id, eg https:piwik.org:443
     * @param string $date date string, eg 2016-12-24
     */
    protected function archiveDayCrawlStatNumerics($hostId, $date)
    {
        $dataTable = $this->getCrawlStatsAsDataTable($hostId, $date);

        if (!empty($dataTable)) {

            Log::debug("[SearchEngineKeywordsPerformance] Archiving yandex crawl stats for $date and $hostId");

            $getValue = function ($label) use ($dataTable) {
                $row = $dataTable->getRowFromLabel($label);
                if ($row) {
                    return (int)$row->getColumn(Metrics::NB_PAGES);
                }

                return 0;
            };

            $numericRecords = [
                self::CRAWLSTATS_IN_INDEX_RECORD_NAME       => $getValue('SEARCHABLE'),
                self::CRAWLSTATS_APPEARED_PAGES_RECORD_NAME => $getValue('APPEARED_IN_SEARCH'),
                self::CRAWLSTATS_REMOVED_PAGES_RECORD_NAME  => $getValue('REMOVED_FROM_SEARCH'),
                self::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME  => $getValue('HTTP_2XX') + $getValue('HTTP_3XX') + $getValue('HTTP_4XX') + $getValue('HTTP_5XX') + $getValue('OTHER'),
                self::CRAWLSTATS_CODE_2XX_RECORD_NAME       => $getValue('HTTP_2XX'),
                self::CRAWLSTATS_CODE_3XX_RECORD_NAME       => $getValue('HTTP_3XX'),
                self::CRAWLSTATS_CODE_4XX_RECORD_NAME       => $getValue('HTTP_4XX'),
                self::CRAWLSTATS_CODE_5XX_RECORD_NAME       => $getValue('HTTP_5XX'),
                self::CRAWLSTATS_ERRORS_RECORD_NAME         => $getValue('OTHER'),
            ];

            unset($dataTable);

            $this->getProcessor()->insertNumericRecords($numericRecords);
        }
    }

    /**
     * Returns keyword data for given parameters as DataTable
     *
     * @param string $hostId  host id, eg https:piwik.org:443
     * @param string $date date string, eg 2016-12-24
     * @return null|DataTable
     */
    protected function getKeywordsAsDataTable($hostId, $date)
    {
        $model       = new YandexModel();
        $keywordData = $model->getKeywordData($hostId, $date);

        if (!empty($keywordData)) {
            $dataTable = new DataTable();
            $dataTable->addRowsFromSerializedArray($keywordData);
            return $dataTable;
        }

        return null;
    }

    /**
     * Returns crawl stats for given parameters as DataTable
     *
     * @param string $hostId host id, eg https:piwik.org:443
     * @param string $date date string, eg 2016-12-24
     * @return null|DataTable
     */
    protected function getCrawlStatsAsDataTable($hostId, $date)
    {
        $model       = new YandexModel();
        $keywordData = $model->getCrawlStatsData($hostId, $date);

        if (!empty($keywordData)) {
            $dataTable = new DataTable();
            $dataTable->addRowsFromSerializedArray($keywordData);
            return $dataTable;
        }

        return null;
    }

    /**
     * Period archiving: combine daily archives
     */
    public function aggregateMultipleReports()
    {
        if (!$this->isEnabled()) {
            return;
        }

        Log::debug("[SearchEngineKeywordsPerformance] Archiving yandex records for " . $this->getProcessor()->getParams()->getPeriod()->getRangeString());

        $aggregationOperations = Metrics::getColumnsAggregationOperations();

        $this->getProcessor()->aggregateDataTableRecords(
            [self::KEYWORDS_YANDEX_RECORD_NAME],
            $this->maximumRows,
            $maximumRowsInSubDataTable = null,
            $columnToSortByBeforeTruncation = Metrics::NB_CLICKS,
            $aggregationOperations,
            $columnsToRenameAfterAggregation = null,
            $countRowsRecursive = []
        );

        $this->getProcessor()->aggregateNumericMetrics([
            self::CRAWLSTATS_IN_INDEX_RECORD_NAME,
            self::CRAWLSTATS_APPEARED_PAGES_RECORD_NAME,
            self::CRAWLSTATS_REMOVED_PAGES_RECORD_NAME,
            self::CRAWLSTATS_CRAWLED_PAGES_RECORD_NAME,
            self::CRAWLSTATS_CODE_2XX_RECORD_NAME,
            self::CRAWLSTATS_CODE_3XX_RECORD_NAME,
            self::CRAWLSTATS_CODE_4XX_RECORD_NAME,
            self::CRAWLSTATS_CODE_5XX_RECORD_NAME,
            self::CRAWLSTATS_ERRORS_RECORD_NAME,
        ], 'max');
    }


    /**
     * public wrapper for finalizing an archive
     */
    public function finalize()
    {
        $this->getProcessor()->getArchiveWriter()->flushSpools();
    }
}

