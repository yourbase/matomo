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
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\HeatmapSessionRecording;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Http;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Plugins\HeatmapSessionRecording\Dao\LogHsr;
use Piwik\Plugins\HeatmapSessionRecording\Dao\LogHsrEvent;
use Piwik\Plugins\HeatmapSessionRecording\Dao\SiteHsrDao;
use Piwik\Plugins\HeatmapSessionRecording\Input\Validator;
use Piwik\Plugins\HeatmapSessionRecording\Model\SiteHsrModel;
use Piwik\Plugins\HeatmapSessionRecording\Tracker\RequestProcessor;
use Piwik\Plugins\Live\Live;
use Piwik\Session\SessionInitializer;
use Piwik\Settings\Storage\Backend\PluginSettingsTable;
use Piwik\Tracker\PageUrl;
use Piwik\Url;

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var SiteHsrModel
     */
    private $siteHsrModel;

    /**
     * @var SystemSettings
     */
    private $systemSettings;

    public function __construct(Validator $validator, SiteHsrModel $model, SystemSettings $settings)
    {
        parent::__construct();
        $this->validator = $validator;
        $this->siteHsrModel = $model;
        $this->systemSettings = $settings;
    }

    public function manageHeatmap()
    {
        $idSite = Common::getRequestVar('idSite');

        if (strtolower($idSite) === 'all') {
            // prevent fatal error... redirect to a specific site as it is not possible to manage for all sites
            $this->validator->checkHasSomeWritePermission();
            $this->redirectToIndex('HeatmapSessionRecording', 'manageHeatmap');
            exit;
        }

        $this->checkSitePermission();
        $this->validator->checkWritePermission($this->idSite);

        return $this->renderTemplate('manageHeatmap', array(
            'breakpointMobile' => (int) $this->systemSettings->breakpointMobile->getValue(),
            'breakpointTablet' => (int) $this->systemSettings->breakpointTablet->getValue()
        ));
    }

    public function manageSessions()
    {
        $idSite = Common::getRequestVar('idSite');

        if (strtolower($idSite) === 'all') {
            // prevent fatal error... redirect to a specific site as it is not possible to manage for all sites
            $this->validator->checkHasSomeWritePermission();
            $this->redirectToIndex('HeatmapSessionRecording', 'manageSessions');
            exit;
        }

        $this->checkSitePermission();
        $this->validator->checkWritePermission($this->idSite);

        return $this->renderTemplate('manageSessions');
    }

    private function checkNotInternetExplorerWhenUsingToken()
    {
        if (Common::getRequestVar('token_auth', '', 'string') && !empty($_SERVER['HTTP_USER_AGENT'])) {
            // we want to detect device type only once for faster performance
            $ddFactory = StaticContainer::get(\Piwik\DeviceDetector\DeviceDetectorFactory::class);
            $deviceDetector = $ddFactory->makeInstance($_SERVER['HTTP_USER_AGENT']);
            $client = $deviceDetector->getClient();

            if ((!empty($client['short_name']) && $client['short_name'] === 'IE')
             || (!empty($client['name']) && $client['name'] === 'Internet Explorer')
             || (!empty($client['name']) && $client['name'] === 'Opera Mini')) {
               // see https://caniuse.com/?search=noreferrer
                // and https://caniuse.com/?search=referrerpolicy
               throw new \Exception('For security reasons this feature doesn\'t work in this browser when using authentication using token_auth. Please try a different browser or log in to view this.');
            }
        }
    }

    public function replayRecording()
    {
        $this->validator->checkSessionReportViewPermission($this->idSite);
        $this->checkNotInternetExplorerWhenUsingToken();

        $idLogHsr = Common::getRequestVar('idLogHsr', null, 'int');
        $idSiteHsr = Common::getRequestVar('idSiteHsr', null, 'int');

        $_GET['period'] = 'year'; // setting it randomly to not having to pass it in the URL
        $_GET['date'] = 'today'; // date is ignored anyway

        $recording = Request::processRequest('HeatmapSessionRecording.getRecordedSession', array(
            'idSite' => $this->idSite,
            'idLogHsr' => $idLogHsr,
            'idSiteHsr' => $idSiteHsr,
            'filter_limit' => '-1'
        ), $default = []);

        $currentPage = null;
        if (!empty($recording['pageviews']) && is_array($recording['pageviews'])) {
            $allPageviews = array_values($recording['pageviews']);
            foreach ($allPageviews as $index => $pageview) {
                if (!empty($pageview['idloghsr']) && $idLogHsr == $pageview['idloghsr']) {
                    $currentPage = $index + 1;
                    break;
                }
            }
        }

        $settings = $this->getPluginSettings();
        $settings = $settings->load();
        $skipPauses = !empty($settings['skip_pauses']);
        $autoPlayEnabled = !empty($settings['autoplay_pageviews']);
        $replaySpeed = !empty($settings['replay_speed']) ? (int) $settings['replay_speed'] : 1;
        $isVisitorProfileEnabled = Manager::getInstance()->isPluginActivated('Live') && Live::isVisitorProfileEnabled();

        return $this->renderTemplate('replayRecording', array(
            'idLogHsr' => $idLogHsr,
            'idSiteHsr' => $idSiteHsr,
            'recording' => $recording,
            'scrollAccuracy' => LogHsr::SCROLL_ACCURACY,
            'offsetAccuracy' => LogHsrEvent::OFFSET_ACCURACY,
            'autoPlayEnabled' => $autoPlayEnabled,
            'visitorProfileEnabled' => $isVisitorProfileEnabled,
            'skipPausesEnabled' => $skipPauses,
            'replaySpeed' => $replaySpeed,
            'currentPage' => $currentPage
        ));
    }

    protected function setBasicVariablesView($view)
    {
        parent::setBasicVariablesView($view);

        if (Common::getRequestVar('module', '', 'string') === 'Widgetize'
          && Common::getRequestVar('action', '', 'string') === 'iframe'
          && Common::getRequestVar('moduleToWidgetize', '', 'string') === 'HeatmapSessionRecording') {
            $action = Common::getRequestVar('actionToWidgetize', '', 'string');
            if (in_array($action, array('replayRecording', 'showHeatmap'), true)) {
                $view->enableFrames = true;
            }
        }
    }

    private function getPluginSettings()
    {
        $login = Piwik::getCurrentUserLogin();

        $settings = new PluginSettingsTable('HeatmapSessionRecording', $login);
        return $settings;
    }

    public function saveSessionRecordingSettings()
    {
        Piwik::checkUserHasSomeViewAccess();

        $autoPlay = Common::getRequestVar('autoplay', '0', 'int');
        $replaySpeed = Common::getRequestVar('replayspeed', '1', 'int');
        $skipPauses = Common::getRequestVar('skippauses', '0', 'int');

        $settings = $this->getPluginSettings();
        $settings->save(array('autoplay_pageviews' => $autoPlay, 'replay_speed' => $replaySpeed, 'skip_pauses' => $skipPauses));
    }

    private function initHeatmapAuth()
    {
        // todo remove in Matomo 5 when we hopefully no longer support IE 11.
        // This is mostly there to prevent forwarding tokens through referrer to third parties
        // most browsers support this except IE11
        // we said we're technically OK with IE11 forwarding a view token in worst case but we still have this here for now
        $token_auth = Common::getRequestVar('token_auth', '', 'string');

        if (!empty($token_auth)) {
            $auth = StaticContainer::get('Piwik\Auth');
            $auth->setTokenAuth($token_auth);
            $auth->setPassword(null);
            $auth->setPasswordHash(null);
            $auth->setLogin(null);

            $sessionInitializer = new SessionInitializer();
            $sessionInitializer->initSession($auth);

            $url = preg_replace('/&token_auth=[^&]{20,38}|$/i', '', Url::getCurrentUrl());
            if ($url) {
                Url::redirectToUrl($url);
                return;
            }
        }

        // if no token_auth, we just rely on an existing session auth check
    }

    protected function setBasicVariablesNoneAdminView($view)
    {
        parent::setBasicVariablesNoneAdminView($view);
        if (Piwik::getAction() === 'embedPage' && Piwik::getModule() === 'HeatmapSessionRecording') {
            $view->setXFrameOptions('allow');
        }
    }

    public function embedPage()
    {
        $this->checkNotInternetExplorerWhenUsingToken();
        $this->initHeatmapAuth();

        $pathPrefix = HeatmapSessionRecording::getPathPrefix();
        $jQueryPath = 'node_modules/jquery/dist/jquery.min.js';
        if (HeatmapSessionRecording::isMatomoForWordPress()) {
            $jQueryPath = includes_url('js/jquery/jquery.js');
        }

        $idLogHsr = Common::getRequestVar('idLogHsr', 0, 'int');
        $idSiteHsr = Common::getRequestVar('idSiteHsr', null, 'int');

        $_GET['period'] = 'year'; // setting it randomly to not having to pass it in the URL
        $_GET['date'] = 'today'; // date is ignored anyway

        if (empty($idLogHsr)) {
            $this->validator->checkHeatmapReportViewPermission($this->idSite);

            $heatmap = $this->getHeatmap($this->idSite, $idSiteHsr);

            if (isset($heatmap[0])) {
                $heatmap = $heatmap[0];
            }

            $baseUrl = $heatmap['screenshot_url'];
            $initialMutation = $heatmap['page_treemirror'];
        } else {
            $this->validator->checkSessionReportViewPermission($this->idSite);
            $this->checkSessionRecordingExists($this->idSite, $idSiteHsr);

            $recording = Request::processRequest('HeatmapSessionRecording.getEmbedSessionInfo', [
                'idSite' => $this->idSite,
                'idSiteHsr' => $idSiteHsr,
                'idLogHsr' => $idLogHsr,
            ], $default = []);

            if (empty($recording)) {
                throw new \Exception(Piwik::translate('HeatmapSessionRecording_ErrorSessionRecordingDoesNotExist'));
            }

            $baseUrl = $recording['base_url'];
            $map = array_flip(PageUrl::$urlPrefixMap);

            if (isset($recording['url_prefix']) !== null && isset($map[$recording['url_prefix']])) {
                $baseUrl = $map[$recording['url_prefix']] . $baseUrl;
            }

            if (!empty($recording['initial_mutation'])) {
                $initialMutation = $recording['initial_mutation'];
            } else {
                $initialMutation = '';
            }
        }

        return $this->renderTemplate('embedPage', array(
            'idLogHsr' => $idLogHsr,
            'idSiteHsr' => $idSiteHsr,
            'initialMutation' => $initialMutation,
            'baseUrl' => $baseUrl,
            'pathPrefix' => $pathPrefix,
            'jQueryPath' => $jQueryPath,
        ));
    }

    public function showHeatmap()
    {
        $this->validator->checkHeatmapReportViewPermission($this->idSite);
        $this->checkNotInternetExplorerWhenUsingToken();

        $idSiteHsr = Common::getRequestVar('idSiteHsr', null, 'int');
        $heatmapType = Common::getRequestVar('heatmapType', RequestProcessor::EVENT_TYPE_CLICK, 'int');
        $deviceType = Common::getRequestVar('deviceType', LogHsr::DEVICE_TYPE_DESKTOP, 'int');

        $heatmap = Request::processRequest('HeatmapSessionRecording.getHeatmap', array(
            'idSite' => $this->idSite,
            'idSiteHsr' => $idSiteHsr
        ), $default = []);

        if (isset($heatmap[0])) {
            $heatmap = $heatmap[0];
        }

        if (Common::getRequestVar('useDateUrl', 0, 'int')) {
            $period = Common::getRequestVar('period', null, 'string');
            $dateRange = Common::getRequestVar('date', null, 'string');
        } else {
            $requestDate = $this->siteHsrModel->getPiwikRequestDate($heatmap);
            $period = $requestDate['period'];
            $dateRange = $requestDate['date'];
        }

        $metadata = Request::processRequest('HeatmapSessionRecording.getRecordedHeatmapMetadata', array(
            'idSite' => $this->idSite,
            'idSiteHsr' => $idSiteHsr,
            'period' => $period,
            'date' => $dateRange
        ), $default = []);

        if (isset($metadata[0])) {
            $metadata = $metadata[0];
        }

        $editUrl = 'index.php' . Url::getCurrentQueryStringWithParametersModified(array(
                'module' => 'HeatmapSessionRecording',
                'action' => 'manageHeatmap'
            )) . '#?idSiteHsr=' . (int)$idSiteHsr;

        $reportDocumentation = '';
        if ($heatmap['status'] == SiteHsrDao::STATUS_ACTIVE) {
            $reportDocumentation = Piwik::translate('HeatmapSessionRecording_RecordedHeatmapDocStatusActive', array($heatmap['sample_limit'], $heatmap['sample_rate'] . '%'));
        } elseif ($heatmap['status'] == SiteHsrDao::STATUS_ENDED) {
            $reportDocumentation = Piwik::translate('HeatmapSessionRecording_RecordedHeatmapDocStatusEnded');
        }

        return $this->renderTemplate('showHeatmap', array(
            'idSiteHsr' => $idSiteHsr,
            'editUrl' => $editUrl,
            'heatmapType' => $heatmapType,
            'deviceType' => $deviceType,
            'heatmapPeriod' => $period,
            'heatmapDate' => $dateRange,
            'heatmap' => $heatmap,
            'isActive' => $heatmap['status'] == SiteHsrDao::STATUS_ACTIVE ? '1' : '0',
            'heatmapMetadata' => $metadata,
            'reportDocumentation' => $reportDocumentation,
            'isScroll' => $heatmapType == RequestProcessor::EVENT_TYPE_SCROLL,
            'offsetAccuracy' => LogHsrEvent::OFFSET_ACCURACY,
            'heatmapTypes' => API::getInstance()->getAvailableHeatmapTypes(),
            'deviceTypes' => API::getInstance()->getAvailableDeviceTypes(),
        ));
    }

    private function getHeatmap($idSite, $idSiteHsr)
    {
        $heatmap = Request::processRequest('HeatmapSessionRecording.getHeatmap', [
            'idSite' => $idSite,
            'idSiteHsr' => $idSiteHsr,
        ], $default = []);
        if (empty($heatmap)) {
            throw new \Exception(Piwik::translate('HeatmapSessionRecording_ErrorHeatmapDoesNotExist'));
        }
        return $heatmap;
    }

    private function checkSessionRecordingExists($idSite, $idSiteHsr)
    {
        $sessionRecording = Request::processRequest('HeatmapSessionRecording.getSessionRecording', [
            'idSite' => $idSite,
            'idSiteHsr' => $idSiteHsr,
        ], $default = []);
        if (empty($sessionRecording)) {
            throw new \Exception(Piwik::translate('HeatmapSessionRecording_ErrorSessionRecordingDoesNotExist'));
        }
    }
}
