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
 * @property long zoom_meeting_id database column
 * @property string mkdate database column
 * @property string chdate database column
 */

class ZoomMeeting extends SimpleORMap
{

    private $zoomData = null;

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

        $config['registered_callbacks']['after_initialize'][] = 'cbGetZoomSettings';

        parent::configure($config);
    }

    public function getZoom_Settings()
    {
        return ZoomAPI::getMeeting($this->zoom_meeting_id);
    }

    public function setZoom_Settings($data)
    {
        $this->zoom_settings = $data;
    }

    /**
     * Visibilities are stored as strings to database (YYYY-MM-DD HH:ii:ss).
     * Internally, the model class uses DateTime objects for better handling.
     *
     * @param string $type the event
     */
    public function cbDateTimeObject($type)
    {
        foreach (words('visible_from visible_until') as $one) {
            if ($type === 'before_store' && $this->$one != null) {
                $this->$one = $this->$one->format('Y-m-d H:i:s');
            }
            if (in_array($type, ['after_initialize', 'after_store']) && $this->$one != null) {
                $this->$one = new DateTime($this->$one);
            }
        }
    }

    /**
     * Many meeting settings are not stored in Stud.IP as they can always be changed directly in Zoom.
     * So on loading a ZoomMeeting object, we get those settings via Zoom API.
     *
     * @param string $type the event
     */
    public function cbGetZoomSettings($type)
    {
        if (in_array($type, ['after_initialize'])) {
            $this->getZoom_Settings();
        }
    }

}
