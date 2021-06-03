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
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Google as GoogleModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Importer\Google as GoogleImporter;
use Piwik\DataTable;
use Piwik\Log;

/**
 * Archiver for Google Keywords
 *
 * @see PluginsArchiver
 */
class Google extends \Piwik\Plugin\Archiver
{
    /**
     * Key used to archive web keywords
     */
    const KEYWORDS_GOOGLE_WEB_RECORD_NAME = 'SearchEngineKeywordsPerformance_google_keywords_web';

    /**
     * Key used to archive image keywords
     */
    const KEYWORDS_GOOGLE_IMAGE_RECORD_NAME = 'SearchEngineKeywordsPerformance_google_keywords_image';

    /**
     * Key used to archive video keywords
     */
    const KEYWORDS_GOOGLE_VIDEO_RECORD_NAME = 'SearchEngineKeywordsPerformance_google_keywords_video';

    /**
     * Key used to archive news keywords
     */
    const KEYWORDS_GOOGLE_NEWS_RECORD_NAME = 'SearchEngineKeywordsPerformance_google_keywords_news';

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

        $searchConsoleUrl = $this->settings->googleSearchConsoleUrl;

        if (empty($searchConsoleUrl) || !$searchConsoleUrl->getValue() || false === strpos($searchConsoleUrl->getValue(), '##')) {
            return false; // search console not activated for that site
        }

        return true;
    }

    /**
     * Returns the records that are configured to archive
     *
     * @return array
     */
    protected function getRecordsForArchiving()
    {
        $archives = [];

        if ($this->settings->googleWebKeywords->getValue()) {
            $archives[] = self::KEYWORDS_GOOGLE_WEB_RECORD_NAME;
        }
        if ($this->settings->googleImageKeywords->getValue()) {
            $archives[] = self::KEYWORDS_GOOGLE_IMAGE_RECORD_NAME;
        }
        if ($this->settings->googleVideoKeywords->getValue()) {
            $archives[] = self::KEYWORDS_GOOGLE_VIDEO_RECORD_NAME;
        }
        if ($this->settings->googleNewsKeywords->getValue()) {
            $archives[] = self::KEYWORDS_GOOGLE_NEWS_RECORD_NAME;
        }

        return $archives;
    }

    /**
     * Aggregate data for day reports
     */
    public function aggregateDayReport()
    {
        if (!$this->isEnabled()) {
            return;
        }

        $parameters           = $this->getProcessor()->getParams();
        $date                 = $parameters->getDateStart()->setTimezone('UTC')->toString('Y-m-d');
        $searchConsoleSetting = $this->settings->googleSearchConsoleUrl->getValue();
        list($accountId, $searchConsoleUrl) = explode('##', $searchConsoleSetting);

        $archives = $this->getRecordsForArchiving();

        if (!empty($archives)) {

            foreach ($archives as $archive) {
                $this->aggregateDayBySearchType($archive, $accountId, $searchConsoleUrl, $date);
            }
        }
    }

    /**
     * Aggregates data for a given day by type of search
     *
     * @param string $recordName
     * @param string $accountId
     * @param string $searchConsoleUrl
     * @param string $date
     */
    protected function aggregateDayBySearchType($recordName, $accountId, $searchConsoleUrl, $date)
    {
        $types = [
            self::KEYWORDS_GOOGLE_WEB_RECORD_NAME   => 'web',
            self::KEYWORDS_GOOGLE_IMAGE_RECORD_NAME => 'image',
            self::KEYWORDS_GOOGLE_VIDEO_RECORD_NAME => 'video',
            self::KEYWORDS_GOOGLE_NEWS_RECORD_NAME  => 'news',
        ];

        Log::debug("[SearchEngineKeywordsPerformance] Archiving {$types[$recordName]} keywords for $date and $searchConsoleUrl");

        $dataTable = $this->getKeywordsAsDataTable($accountId, $searchConsoleUrl, $date, $types[$recordName]);

        if (empty($dataTable)) {
            return;
        }

        $report = $dataTable->getSerialized($this->maximumRows, null, Metrics::NB_CLICKS);
        unset($dataTable);
        $this->getProcessor()->insertBlobRecord($recordName, $report);
    }

    /**
     * Returns keyword data for given parameters as DataTable
     *
     * @param string $accountId google account id
     * @param string $url       url, eg http://matomo.org
     * @param string $date      date string, eg 2016-12-24
     * @param string $type      'web', 'image', 'video' or 'news'
     * @return null|DataTable
     */
    protected function getKeywordsAsDataTable($accountId, $url, $date, $type)
    {
        // ensure keywords are present (if available)
        $googleImporter = new GoogleImporter($this->getProcessor()->getParams()->getSite()->getId());
        $googleImporter->importKeywordsIfNecessary($accountId, $url, $date, $type);

        $model       = new GoogleModel();
        $keywordData = $model->getKeywordData($url, $date, $type);

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

        $archives = $this->getRecordsForArchiving();

        Log::debug("[SearchEngineKeywordsPerformance] Archiving Google data for " . $this->getProcessor()->getParams()->getPeriod()->getRangeString());

        if (!empty($archives)) {
            $aggregationOperations = Metrics::getColumnsAggregationOperations();

            $this->getProcessor()->aggregateDataTableRecords(
                $archives,
                $this->maximumRows,
                $maximumRowsInSubDataTable = null,
                $columnToSortByBeforeTruncation = Metrics::NB_CLICKS,
                $aggregationOperations,
                $columnsToRenameAfterAggregation = null,
                $countRowsRecursive = []
            );
        }
    }

    /**
     * public wrapper for finalizing an archive
     */
    public function finalize()
    {
        $this->getProcessor()->getArchiveWriter()->flushSpools();
    }
}

