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

    const API_URL = 'https://api.zoom.us/v2/';
    // What timezone are Zoom timestamps in?
    const ZOOM_TIMEZONE = 'UTC';
    // What is our local timezone?
    const LOCAL_TIMEZONE = 'Europe/Berlin';
    /*
     * License types
     */
    const LICENSE_BASIC = 1;
    const LICENSE_LICENSED = 2;
    const LICENSE_ONPREM = 3;
    /*
     * Meeting types
     */
    const MEETING_INSTANT = 1;
    const MEETING_SCHEDULED = 2;
    const MEETING_RECURRING_NO_FIXED_TIME = 3;
    const MEETING_RECURRING_FIXED_TIME = 8;
    /**
     * Recurrence types
     */
    const RECURRENCE_DAILY = 1;
    const RECURRENCE_WEEKLY = 2;
    const RECURRENCE_MONTHLY = 3;
    /**
     * Threshold for regular meetings. If there are
     * more participants, a Webinar plan is necessary.
     */
    const MAX_MEETING_MEMBERS = 300;

    /**
     * Gets a single user's data.
     *
     * @param User|null $userId the user to check
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
            $result = self::_call('users/' . $userId);

            // User found, return data.
            if ($result['statuscode'] == 200) {

                $user = $result['response'];

            // User not found, return a special code.
            } else if ($result['statuscode'] == 404) {

                return 404;

            // Some other problem, return null.
            } else {
                $user = null;
            }

            if ($user != null) {
                $cache->write('zoom-user-' . $userId, json_encode($user), 86400);
            }

            return $user;
        }
    }

    public static function getUserPermissions($userId)
    {
        return self::_call('users/' . $userId . '/permissions');
    }

    /**
     * Checks if the given userIds exist as Zoom users.
     *
     * @param array $userIds Stud.IP Users
     * @return array Indicator who exists in Zoom and who doesn't ([$userId => true|false])
     */
    public function usersExist($users)
    {
        $existing = [];

        foreach ($users as $one) {
            $user = self::getUser($one->email);
            $existing[$one->user_id] = ($user !== 404 && $user !== null);
        }

        return $existing;
    }

    /**
     * Creates a new Zoom meeting with the given data.
     *
     * @param string $userId settings for the new meeting
     * @param array $settings settings for the new meeting
     *
     * @return object|null A meeting object or null if an error occurred.
     */
    public static function createMeeting($userId, $settings)
    {
        $result = self::_call('users/' . $userId . '/meetings', [], $settings, 'POST');

        if ($result['statuscode'] == 201) {
            $meeting = $result['response'];
        } else {
            $meeting = null;
        }

        if ($meeting != null) {
            $cache = StudipCacheFactory::getCache();
            $cache->write('zoom-meeting-' . $meeting->id, json_encode($meeting), 10800);
        }

        return $meeting;
    }

    /**
     * Updates the given meeting with the given settings.
     *
     * @param $meetingId
     * @param $settings
     *
     * @return object|int|null A meeting object, '404' as 'not found' or null if another error occurred.
     */
    public static function updateMeeting($meetingId, $settings)
    {
        $result = self::_call('meetings/' . $meetingId, [], $settings, 'PATCH');

        if ($result['statuscode'] == 204) {
            $meeting = new StdClass();
            $meeting->id = $meetingId;
        } else if ($result['statuscode'] == 404) {
            $meeting = 404;
        } else {
            $meeting = null;
        }

        $cache = StudipCacheFactory::getCache();
        $cache->expire('zoom-meeting-' . $meetingId, 10800);

        return $meeting;
    }

    /**
     * Gets a single meeting.
     *
     * @param long $meetingId the Zoom meeting ID
     * @param bool $useCache use cached entry if available?
     * @return mixed
     * @throws Exception
     */
    public static function getMeeting($meetingId, $useCache = true)
    {
        $cache = StudipCacheFactory::getCache();

        if ($useCache && $meeting = $cache->read('zoom-meeting-' . $meetingId)) {
            $meeting = json_decode($meeting);

            // Convert start time to DateTime object for convenience.
            $start_time = new DateTime($meeting->start_time, new DateTimeZone(self::ZOOM_TIMEZONE));
            $start_time->setTimezone(new DateTimeZone(self::LOCAL_TIMEZONE));
            $meeting->start_time = $start_time;

            return $meeting;
        } else {
            $result = self::_call('meetings/' . $meetingId);

            // Meeting found, all is well.
            if ($result['statuscode'] == 200) {
                $meeting = $result['response'];

                $cache->write('zoom-meeting-' . $meetingId, json_encode($meeting), 10800);
                // Convert start time to DateTime object for convenience.
                $time = is_array($meeting->occurrences) ? $meeting->occurrences[0]->start_time : $meeting->start_time;
                $start_time = new DateTime($time, new DateTimeZone(self::ZOOM_TIMEZONE));
                $start_time->setTimezone(new DateTimeZone(self::LOCAL_TIMEZONE));
                $meeting->start_time = $start_time;
                $meeting->duration = is_array($meeting->occurrences) ?
                    $meeting->occurrences[0]->duration :
                    $meeting->duration;

            // Meeting not found in Zoom
            } else if ($result['statuscode'] == 404) {
                $cache->expire('zoom-meeting-' . $meetingId);
                $meeting = 404;
            // Some other problem.
            } else {
                $meeting = null;
            }

            return $meeting;
        }
    }

    public static function deleteMeeting($meetingId)
    {
        $cache = StudipCacheFactory::getCache();
        $cache->expire('zoom-meeting-' . $meetingId);

        $result = self::_call('meetings/' . $meetingId, [], [], 'DELETE');

        if ($result['statuscode'] == 204 || $result['statuscode'] == 404) {
            $response = $meetingId;
        } else {
            $response = null;
        }

        return $response;
    }

    /**
     * Calls the given API endpoint and returns the response.
     * If an error occurs, an exception is thrown.
     * @param string $endpoint the API endpoint to call
     * @param array $query_args extra parameters which will be appended as GET parameters
     * @param mixed|null $body extra parameters which will be set as request body
     * @param string $method request method, GET, POST, PUT, PATCH, DELETE
     */
    private static function _call($endpoint, $query_args = [], $body = null, $method = 'GET')
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
        $url = self::API_URL . $endpoint;
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

        if (($method == 'POST' || $method == 'PATCH') && $body != null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = json_decode(curl_exec($curl));
        $error = curl_error($curl);
        $statuscode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        if ($error) {
            return null;
        } else {
            return [
                'response' => $response,
                'statuscode' => $statuscode
            ];
        }
    }

    /**
     * Get Weekdays (Zoom uses some own numbering here)
     */
    public static function getWeekdays()
    {
        return [
            [
                'id' => 2,
                'name' => dgettext('zoom', 'Montag'),
                'name_en' => 'Monday'
            ],
            [
                'id' => 3,
                'name' => dgettext('zoom', 'Dienstag'),
                'name_en' => 'Tuesday'
            ],
            [
                'id' => 4,
                'name' => dgettext('zoom', 'Mittwoch'),
                'name_en' => 'Wednesday'
            ],
            [
                'id' => 5,
                'name' => dgettext('zoom', 'Donnerstag'),
                'name_en' => 'Thursday'
            ],
            [
                'id' => 6,
                'name' => dgettext('zoom', 'Freitag'),
                'name_en' => 'Friday'
            ],
            [
                'id' => 7,
                'name' => dgettext('zoom', 'Samstag'),
                'name_en' => 'Saturday'
            ],
            [
                'id' => 1,
                'name' => dgettext('zoom', 'Sonntag'),
                'name_en' => 'Sunday'
            ]
        ];
    }

}
