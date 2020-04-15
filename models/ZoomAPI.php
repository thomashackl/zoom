<?php

/**
 * Class ZoomAPI
 * Helper class for collecting communication with Zoom API in one place.
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

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

class ZoomAPI {

    private static $API_URL = 'https://api.zoom.us/v2/';
    /*
     * License types
     */
    public static $LICENSE_BASIC = 1;
    public static $LICENSE_LICENSED = 2;
    public static $LICENSE_ONPREM = 3;
    /*
     * Meeting types
     */
    public static $MEETING_INSTANT = 1;
    public static $MEETING_SCHEDULED = 2;
    public static $MEETING_RECURRING_NO_FIXED_TIME = 3;
    public static $MEETING_RECURRING_FIXED_TIME = 4;

    /**
     * Gets a single user's data.
     *
     * @param string|null $userId Zoom users are identified by their E-Mail address
     * @return mixed
     * @throws Exception
     */
    public static function getUser($userId = null) {
        if ($userId == null) {
            $userId = User::findCurrent()->email;
        }

        $cache = StudipCacheFactory::getCache();

        if ($user = $cache->read('zoom-user-' . $userId)) {
            return json_decode($user);
        } else {
            $user = self::_call('users/' . $userId);

            if ($user != null) {
                $cache->write(json_encode($user), 'zoom-user-' . $userId, 86400);
            }

            return $user;
        }
    }

    /**
     * Creates a new Zoom meeting with the given data.
     *
     * @param string $userId settings for the new meeting
     * @param array $settings settings for the new meeting
     */
    public static function createMeeting($userId, $settings)
    {
        $meeting = self::_call('users/' . $userId . '/meetings', [], $settings, 'POST');

        if ($meeting != null) {
            $cache = StudipCacheFactory::getCache();
            $cache->write(json_encode($meeting), 'zoom-meeting-' . $meeting->id, 10800);
        }

        return $meeting;
    }

    /**
     * Gets a single meeting.
     *
     * @param long $meetingId the Zoom meeting ID
     * @return mixed
     * @throws Exception
     */
    public static function getMeeting($meetingId)
    {
        $cache = StudipCacheFactory::getCache();

        if ($meeting = $cache->read('zoom-meeting-' . $meetingId)) {
            return json_decode($meeting);
        } else {
            $meeting = self::_call('meetings/' . $meetingId);
            $cache->write(json_encode($meeting), 'zoom-meeting-' . $meetingId, 10800);
            return $meeting;
        }
    }

    /**
     * Calls the given API endpoint and returns the response.
     * If an error occurs, an exception is thrown.
     * @param string $endpoint the API endpoint to call
     * @param array $query_args extra parameters which will be appended as GET parameters
     * @param array $body extra parameters which will be set as request body
     * @param string $method request method, GET, POST, PUT, PATCH, DELETE
     */
    private static function _call($endpoint, $query_args = [], $body = [], $method = 'GET')
    {
        // Generate a valid JWT token.
        $payload = [
            'iss' => Config::get()->ZOOM_APIKEY,
            'aud' => $GLOBALS['ABSOLUTE_URI_STUDIP'],
            'iat' => time(),
            'exp' => time() + 60
        ];
        $token = \Firebase\JWT\JWT::encode($payload, Config::get()->ZOOM_APISECRET);

        // Build API call URL.
        $url = self::$API_URL . $endpoint;
        // Parameters passed, generate GET query string.
        if (count($query_args) > 0) {
            array_walk($query_args, function (&$value, $index) {
                $value = $index . '=' . $value;
            });

            $url .= '?' . urlencode(implode('&', $query_args));
        }

        // Now the real call via CURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'authorization: Bearer ' . $token,
                'content-type: application/json'
            ],
        ]);

        if ($method == 'POST' && count($body) > 0) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = json_decode(curl_exec($curl));
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return null;
        } else {
            return $response;
        }
    }

}
