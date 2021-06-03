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

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\CustomJsTracker\TrackerUpdater;
use Piwik\Plugins\HeatmapSessionRecording\Input\Breakpoint;
use Piwik\Plugins\HeatmapSessionRecording\Settings\TrackingDisableDefault;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $breakpointTablet;

    /** @var Setting */
    public $breakpointMobile;

    /** @var TrackingDisableDefault */
    public $disableTrackingByDefault;

    protected function init()
    {
        $this->breakpointMobile = $this->createBreakpointMobileSetting();
        $this->breakpointTablet = $this->createBreakpointTabletSetting();
        $this->disableTrackingByDefault = $this->createDisableTrackingByDefaultSetting();

        if (Plugin\Manager::getInstance()->isPluginActivated('CustomJsTracker')) {
            $trackerUpdater = StaticContainer::get(TrackerUpdater::class);
            if (!$trackerUpdater || !$trackerUpdater->getToFile() || !$trackerUpdater->getToFile()->hasWriteAccess()) {
                // only works if matomo file can be updated
                $this->disableTrackingByDefault->setIsWritableByCurrentUser(false);
            }
        } else {
            $this->disableTrackingByDefault->setIsWritableByCurrentUser(false);
        }
    }

    private function createDisableTrackingByDefaultSetting()
    {
        $setting = new TrackingDisableDefault('trackingDisabledDefault', false, FieldConfig::TYPE_BOOL, $this->pluginName);
        $setting->setConfigureCallback(function (FieldConfig $field) {
            $field->title = Piwik::translate('HeatmapSessionRecording_TrackingDisabledDefaultSettingTitle');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            $field->description = Piwik::translate('HeatmapSessionRecording_TrackingDisabledDefaultSettingDescription');
        });
        $this->addSetting($setting);
        return $setting;
    }

    private function createBreakpointMobileSetting()
    {
        return $this->makeSetting('breakpointMobile', Breakpoint::DEFAULT_MOBILE, FieldConfig::TYPE_INT, function (FieldConfig $field) {
            $field->title = Piwik::translate('HeatmapSessionRecording_BreakpointX', Piwik::translate('General_Mobile'));
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('HeatmapSessionRecording_BreakpointGeneralHelp');
            $field->validate = function ($value) {
                $breakpoint = new Breakpoint($value, Piwik::translate('General_Mobile'));
                $breakpoint->check();
            };
        });
    }

    private function createBreakpointTabletSetting()
    {
        return $this->makeSetting('breakpointTablet', Breakpoint::DEFAULT_TABLET, FieldConfig::TYPE_INT, function (FieldConfig $field) {
            $field->title = Piwik::translate('HeatmapSessionRecording_BreakpointX', Piwik::translate('DevicesDetection_Tablet'));
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('HeatmapSessionRecording_BreakpointGeneralHelp');
            $field->validate = function ($value) {
                $breakpoint = new Breakpoint($value, Piwik::translate('DevicesDetection_Tablet'));
                $breakpoint->check();
            };
        });
    }

    public function save()
    {
        parent::save();

        if (!empty($this->disableTrackingByDefault)) {
            $oldValue = $this->disableTrackingByDefault->getOldValue();
            $newValue = $this->disableTrackingByDefault->getValue();
            if ($oldValue != $newValue) {
                $plugin = Plugin\Manager::getInstance()->getLoadedPlugin($this->pluginName);
                if (!empty($plugin) && $plugin instanceof HeatmapSessionRecording){
                    $plugin->updatePiwikTracker();
                }
            }
        }

    }
}
