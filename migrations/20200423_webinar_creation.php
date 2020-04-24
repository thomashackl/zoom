<?php

/**
 * Adds a database table for specifying whether an entry is a meeting or a webinar.
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

class WebinarCreation extends Migration {

    public function description()
    {
        return 'Adds a database table for specifying whether an entry is a meeting or a webinar.';
    }

    /**
     * Migration UP: We have just installed the plugin
     * and need to prepare all necessary data.
     */
    public function up()
    {
        // Add database field.
        DBManager::get()->execute("ALTER TABLE `zoom_meetings`
            ADD `webinar` TINYINT(1) NOT NULL DEFAULT 0 AFTER `type`");
    }

    /**
     * Migration DOWN: cleanup all created data.
     */
    public function down()
    {
        // Remove database field.
        DBManager::get()->execute("ALTER TABLE `zoom_meetings` DROP `webinar`");
    }

}
