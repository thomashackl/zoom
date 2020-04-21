<?php
/**
 * ZoomPlugin.class.php
 *
 * Plugin for creating and managing Zoom meetings.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Thomas Hackl <thomas.hackl@uni-passau.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Zoom
 */

class ZoomPlugin extends StudIPPlugin implements StandardPlugin {

    public function __construct() {
        parent::__construct();

        StudipAutoloader::addAutoloadPath(__DIR__ . '/models');

        // Localization
        bindtextdomain('zoom', realpath(__DIR__.'/locale'));
    }

    /**
     * Plugin name to show in navigation.
     */
    public function getDisplayName()
    {
        return dgettext('zoom', 'Zoom');
    }

    public function getVersion()
    {
        $metadata = $this->getMetadata();
        return $metadata['version'];
    }

    public function getIconNavigation($course_id, $last_visit, $user_id)
    {
        return null;
    }

    public function getTabNavigation($course_id)
    {
        if ($GLOBALS['user']->id == 'nobody') {
            return [];
        }

        $zoom = new Navigation($this->getDisplayName());
        $zoom->addSubNavigation('meetings', new Navigation(dgettext('zoom', 'Meetings'),
            PluginEngine::getURL($this, [], 'meetings')));

        return compact('zoom');
    }

    /**
     * @see StudipModule::getMetadata()
     */
    public function getMetadata()
    {
        return [
            'summary' => dgettext('zoom', 'Anlegen und Verwalten von Zoom-Meetings'),
            'description' => dgettext('zoom', 'Legen Sie Zoom-Meetingräume an, terminieren und verwalten Sie diese, laden Sie Teilnehmende ein.'),
            'category' => _('Lehr- und Lernorganisation'),
            'icon' => Icon::create('video', 'info'),
            'screenshot' => 'assets/images/zoom-logo.png'
        ];
    }

    /**
     * @see StandardPlugin::getInfoTemplate()
     */
    public function getInfoTemplate($course_id)
    {
        return null;
    }

    public function perform($unconsumed_path) {
        $range_id = Request::option('cid', Context::get()->id);

        URLHelper::removeLinkParam('cid');
        $dispatcher = new Trails_Dispatcher(
            $this->getPluginPath(),
            rtrim(PluginEngine::getLink($this, [], null), '/'),
            'meetings'
        );
        URLHelper::addLinkParam('cid', $range_id);

        $dispatcher->current_plugin = $this;
        $dispatcher->range_id       = $range_id;
        $dispatcher->dispatch($unconsumed_path);
    }

    public static function onEnable($pluginId) {
        parent::onEnable($pluginId);
        StudipAutoloader::addAutoloadPath(__DIR__);
        ZoomMeetingDateCronjob::register()->schedulePeriodic(10)->activate();
    }

    public static function onDisable($pluginId) {
        StudipAutoloader::addAutoloadPath(__DIR__);
        ZoomMeetingDateCronjob::unregister();
        parent::onDisable($pluginId);
    }

}
