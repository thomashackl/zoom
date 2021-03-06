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
    /*
     * Webinar types
     */
    const WEBINAR_WEBINAR = 5;
    const WEBINAR_RECURRING_NO_FIXED_TIME = 6;
    const WEBINAR_RECURRING_FIXED_TIME = 9;
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
     * Default cache lifetime for Zoom entries in seconds
     */
    const CACHE_LIFETIME = 1800;

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
                $cache->write('zoom-user-' . $userId, json_encode($user), self::CACHE_LIFETIME);

            // User not found, return a special code.
            } else if ($result['statuscode'] == 404) {

                return 404;

            // Some other problem, return null.
            } else {
                $user = null;
            }

            return $user;
        }
    }

    /**
     * Gets a single user's data, specified by Zoom ID.
     * We expect to find the given user, so no checks are done.
     *
     * @param null $userId the user to check
     * @return mixed
     * @throws Exception
     */
    public static function getUserByZoomId($zoomId) {
        $cache = StudipCacheFactory::getCache();

        if ($user = $cache->read('zoom-user-' . $zoomId)) {
            return json_decode($user);
        } else {
            $result = self::_call('users/' . $zoomId);

            $user = $result['response'];
            $cache->write('zoom-user-' . $zoomId, json_encode($user), self::CACHE_LIFETIME);

            return $user;
        }
    }

    /**
     * Get settings for given user (which contain webinar permissions)
     *
     * @param string|null $userId
     * @param string $option empty, or 'meeting_authentication' or 'recording_authentication'
     *
     * @return int|mixed|null
     */
    public static function getUserSettings($userId = null, $option = null)
    {
        if ($userId == null) {
            $userId = User::findCurrent()->email;
        }

        $cache = StudipCacheFactory::getCache();

        if ($option === null && $settings = $cache->read('zoom-usersettings-' .
                ($option !== null ? $option . '-' : '') . $userId)) {
            return json_decode($settings);
        } else {
            $result = self::_call('users/' . $userId . '/settings',
                $option !== null ? ['option' => $option] : []);

            if ($result['statuscode'] == 200) {
                if ($option === null) {
                    $cache->write('zoom-usersettings-' . ($option !== null ? $option . '-' : '') . $userId,
                        json_encode($result['response']), self::CACHE_LIFETIME);
                }
                return $result['response'];
            } else if ($result['statuscode'] == 404) {
                return 404;
            } else {
                return null;
            }
        }
    }

    /**
     * @param string $userId the user's email address which is used as ID
     * @param array $settings
     * @param string|null $option empty, or 'meeting_authentication' or 'recording_authentication'
     */
    public static function setUserSettings($userId, $settings, $option = null)
    {
        return self::_call('users/' . $userId . '/settings',
            $option !== null ? ['option' => $option] : [], $settings, 'PATCH');
    }

    /**
     * Get all meetings for the given user.
     *
     * @param string|null $userId the user to check. Defaults to the current user.
     * @return array
     */
    public static function getUserMeetings($userId = null)
    {
        if ($userId == null) {
            $userId = User::findCurrent()->email;
        }

        $cache = StudipCacheFactory::getCache();

        if ($meetings = $cache->read('zoom-user-meetings-' . $userId)) {
            return json_decode($meetings);
        } else {
            $result = self::_call('users/' . $userId . '/meetings', ['page_size' => 50]);

            if ($result['statuscode'] == 200) {
                $cache->write('zoom-user-meetings-' . $userId,
                    json_encode($result['response']->meetings), self::CACHE_LIFETIME);
                return $result['response']->meetings;
            } else {
                return [];
            }
        }

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
            if ($one->email) {
                $user = self::getUser($one->email);
                $existing[$one->user_id] = ($user !== 404 && $user !== null);
            } else {
                $existing[$one->user_id] = false;
            }
        }

        return $existing;
    }

    /**
     * Creates a new Zoom meeting with the given data.
     *
     * @param string $userId settings for the new meeting
     * @param array $settings settings for the new meeting
     * @param bool $isWebinar is this a regular meeting or a Webinar?
     *
     * @return object|null A meeting object or null if an error occurred.
     */
    public static function createMeeting($userId, $settings, $isWebinar = false)
    {
        // Ensure that password will be encoded in join link.
        self::forcePasswordInJoinLink($userId);

        $result = self::_call('users/' . $userId . ($isWebinar ? '/webinars' : '/meetings'),
            [], $settings, 'POST');

        if ($result['statuscode'] == 201) {
            $meeting = $result['response'];
        } else {
            $meeting = null;
        }

        if ($meeting != null) {
            $cache = StudipCacheFactory::getCache();
            $cache->write('zoom-meeting-' . $meeting->id, json_encode($meeting), self::CACHE_LIFETIME);
        }

        return $meeting;
    }

    /**
     * Updates the given meeting with the given settings.
     *
     * @param string $userId Zoom User ID, either email adress or real Zoom ID
     * @param int $meetingId
     * @param array $settings
     * @param bool $isWebinar is this a regular meeting or a Webinar?
     *
     * @return object|int|null A meeting object, '404' as 'not found' or null if another error occurred.
     */
    public static function updateMeeting($userId, $meetingId, $settings, $isWebinar = false)
    {
        // Ensure that password will be encoded in join link.
        $forced = self::forcePasswordInJoinLink($userId);

        $result = self::_call(($isWebinar ? 'webinars/' : 'meetings/') . $meetingId,
            [], $settings, 'PATCH');

        if ($result['statuscode'] == 204) {
            $meeting = new StdClass();
            $meeting->id = $meetingId;
        } else if ($result['statuscode'] == 404) {
            $meeting = 404;
        } else {
            $meeting = null;
        }

        $cache = StudipCacheFactory::getCache();
        $cache->expire('zoom-meeting-' . $meetingId, self::CACHE_LIFETIME);

        return $meeting;
    }

    /**
     * Gets a single meeting.
     *
     * @param long $meetingId the Zoom meeting ID
     * @param bool $useCache use cached entry if available?
     * @param bool $isWebinar is this a regular meeting or a Webinar?
     *
     * @return mixed
     */
    public static function getMeeting($meetingId, $useCache = true, $isWebinar = false)
    {
        $cache = StudipCacheFactory::getCache();

        if ($useCache && $meeting = $cache->read('zoom-meeting-' . $meetingId)) {
            $meeting = json_decode($meeting);

            // Convert start time to DateTime object for convenience.
            $time = is_array($meeting->occurrences) ? $meeting->occurrences[0]->start_time : $meeting->start_time;
            $start_time = new DateTime($time, new DateTimeZone(self::ZOOM_TIMEZONE));
            $start_time->setTimezone(new DateTimeZone(self::LOCAL_TIMEZONE));
            $meeting->start_time = $start_time;

            return $meeting;
        } else {
            $result = self::_call(($isWebinar ? 'webinars/' : 'meetings/') . $meetingId);

            // Meeting found, all is well.
            if ($result['statuscode'] == 200) {
                $meeting = $result['response'];

                $cache->write('zoom-meeting-' . $meetingId, json_encode($meeting), self::CACHE_LIFETIME);
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

    public static function deleteMeeting($meetingId, $isWebinar = false)
    {
        $cache = StudipCacheFactory::getCache();
        $cache->expire('zoom-meeting-' . $meetingId);

        $result = self::_call(($isWebinar ? 'webinars/' : 'meetings/') . $meetingId, [], [], 'DELETE');

        if ($result['statuscode'] == 204 || $result['statuscode'] == 404) {
            $response = $meetingId;
        } else {
            $response = null;
        }

        return $response;
    }

    /**
     * Forces the user setting that passwords are encoded in meeting join links
     *
     * @param string userId user's email which is used as ID here
     */
    public static function forcePasswordInJoinLink($userId)
    {
        return self::setUserSettings($userId,
            [
                'schedule_meeting' => [
                    'embed_password_in_join_link' => true
                ]
            ]
        );
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
                $value = $index . '=' . urlencode($value);
            });

            $url .= '?' . implode('&', $query_args);
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

    /**
     * Get available options in Zoom for configuring a meeting or webinar.
     * Not all options are provided in Stud.IP.
     *
     * @param string $type 'meeting' or 'webinar'
     *
     * @return array Configuration options for given type
     */
    public static function getRoomSettings($type = 'meeting')
    {
        $settings = [
            'meeting' => [
                'host_video' => [
                    'name' => 'host_video',
                    'label' => dgettext('zoom', 'Video starten, wenn ein Host den Raum betritt'),
                    'default' => false
                ],
                'participant_video' => [
                    'name' => 'participant_video',
                    'label' => dgettext('zoom', 'Video starten, wenn ein(e) Teilnehmer(in) den Raum betritt'),
                    'default' => false
                ],
                'join_before_host' => [
                    'name' => 'join_before_host',
                    'label' => dgettext('zoom', 'Teilnehmende dürfen den Raum vor dem Host betreten'),
                    'default' => false
                ],
                'mute_upon_entry' => [
                    'name' => 'mute_upon_entry',
                    'label' => dgettext('zoom', 'Teilnehmende beim Betreten automatisch stumm schalten'),
                    'default' => true
                ],
                'waiting_room' => [
                    'name' => 'waiting_room',
                    'label' => dgettext('zoom', 'Warteraum aktivieren'),
                    'default' => false
                ],
                'meeting_authentication' => [
                    'name' => 'meeting_authentication',
                    'label' => dgettext('zoom', 'Nur angemeldete Nutzer dürfen teilnehmen (keine Gäste)'),
                    'default' => false
                ]
            ],
            'webinar' => [
                'host_video' => [
                    'name' => 'host_video',
                    'label' => dgettext('zoom', 'Video starten, wenn ein Host den Raum betritt'),
                    'default' => false
                ],
                'panelists_video' => [
                    'name' => 'panelists_video',
                    'label' => dgettext('zoom', 'Video starten, wenn ein(e) Teilnehmer(in) den Raum betritt'),
                    'default' => false
                ],
                'meeting_authentication' => [
                    'name' => 'meeting_authentication',
                    'label' => dgettext('zoom', 'Nur angemeldete Nutzer dürfen teilnehmen (keine Gäste)'),
                    'default' => false
                ]
            ]
        ];

        return $settings[$type];
    }

}
