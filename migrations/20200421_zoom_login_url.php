<?php

/**
 * Creates a config entry for storing the Zoom login address.
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

class ZoomLoginUrl extends Migration {

    public function description()
    {
        return 'Creates a config entry for storing the Zoom login address.';
    }

    /**
     * Migration UP: create config entry.
     */
    public function up()
    {
        // API key of your Zoom app
        Config::get()->create('ZOOM_LOGIN_URL', [
            'value' => '',
            'type' => 'string',
            'range' => 'global',
            'section' => 'zoom',
            'description' => 'Unter welcher Adresse müssen die Nutzer sich einloggen?'
        ]);
    }

    /**
     * Migration DOWN: cleanup all created data.
     */
    public function down()
    {
        // Remove config entry.
        Config::get()->delete('ZOOM_LOGIN_URL');
    }

}
