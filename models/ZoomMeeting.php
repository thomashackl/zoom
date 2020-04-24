<?php

/**
 * ZoomMeeting.php
 * model class for zoom meetings.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Thomas Hackl <thomas.hackl@uni-passau.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Zoom
 *
 * @property int meeting_id database column
 * @property string user_id database column
 * @property string course_id database column
 * @property string type database column
 * @property int webinar database column
 * @property long zoom_meeting_id database column
 * @property string mkdate database column
 * @property string chdate database column
 */

class ZoomMeeting extends SimpleORMap
{

    private $zoomData = null;
    public $useCache = true;

    protected static function configure($config = [])
    {
        $config['db_table'] = 'zoom_meetings';
        $config['has_one']['creator'] = [
            'class_name' => 'User',
            'foreign_key' => 'user_id',
            'assoc_foreign_key' => 'user_id'
        ];
        $config['belongs_to']['course'] = [
            'class_name' => 'Course',
            'foreign_key' => 'course_id',
            'assoc_foreign_key' => 'seminar_id'
        ];

        $config['additional_fields']['zoom_settings'] = true;

        parent::configure($config);
    }

    public function isHost($user)
    {
        $zoomUser = ZoomAPI::getUser($user->email);
        $alternative = $this->zoom_settings->settings->alternative_hosts ?: [];
        return ($zoomUser->id == $this->zoom_settings->host_id ||
            in_array($user->email, explode(',', $alternative)));
    }

    public function getZoom_Settings()
    {
        if ($this->zoomData == null) {
            $this->zoomData = ZoomAPI::getMeeting($this->zoom_meeting_id, $this->useCache, $this->webinar);
        }
        return $this->zoomData;
    }

    public function setZoom_Settings($data)
    {
        $this->zoomData = $data;
    }

}
