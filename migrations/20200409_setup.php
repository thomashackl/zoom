<?php

/**
 * Creates config entries for connecting to the Zoom API.
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

class Setup extends Migration {

    public function description()
    {
        return 'Creates config entries for connecting to the Zoom API.';
    }

    /**
     * Migration UP: We have just installed the plugin
     * and need to prepare all necessary data.
     */
    public function up()
    {
        // API key of your Zoom app
        Config::get()->create('ZOOM_APIKEY', [
            'value' => '',
            'type' => 'string',
            'range' => 'global',
            'section' => 'zoom',
            'description' => 'API key der Zoom-App'
        ]);
        // API secret of your Zoom app
        Config::get()->create('ZOOM_APISECRET', [
            'value' => '',
            'type' => 'string',
            'range' => 'global',
            'section' => 'zoom',
            'description' => 'API secret der Zoom-App'
        ]);

        // Table for storing Stud.IP-specific data for Zoom meetings
        DBManager::get()->execute("CREATE TABLE IF NOT EXISTS `zoom_meetings`
        (
            `meeting_id` INT NOT NULL AUTO_INCREMENT,
            `user_id` VARCHAR(32) NOT NULL REFERENCES `auth_user_md5`.`user_id`,
            `course_id` VARCHAR(32) NOT NULL REFERENCES `seminare`.`Seminar_id`,
            `type` SET('coursedates','manual') NOT NULL DEFAULT 'coursedates',
            `zoom_meeting_id` BIGINT NOT NULL,
            `mkdate` DATETIME NOT NULL,
            `chdate` DATETIME NOT NULL,
            PRIMARY KEY (`meeting_id`),
            INDEX course_id (`course_id`)
        ) ENGINE InnoDB ROW_FORMAT=DYNAMIC");
    }

    /**
     * Migration DOWN: cleanup all created data.
     */
    public function down()
    {
        // Remove config entries.
        foreach (words('ZOOM_API_URL ZOOM_APIKEY ZOOM_APISECRET') as $entry) {
            Config::get()->delete($entry);
        }
    }

}
