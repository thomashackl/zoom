<?php

/**
 * Class ZoomMeetingsController
 * Controller for listing and adding Zoom meetings to a course.
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

class ZoomMeetingsController extends AuthenticatedController {

    /**
     * Actions and settings taking place before every page call.
     */
    public function before_filter(&$action, &$args)
    {
        $this->plugin = $this->dispatcher->current_plugin;

        $this->course = Course::findCurrent();

        if (!$GLOBALS['perm']->have_studip_perm('user', $this->course->id)) {
            throw new AccessDeniedException();
        }

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        $this->flash = Trails_Flash::instance();

        PageLayout::addScript($this->plugin->getPluginURL() . '/assets/javascripts/zoom.min.js');
        PageLayout::addStylesheet($this->plugin->getPluginURL() . '/assets/stylesheets/zoom.css');
    }

    /**
     * Show available zoom meetings for this course.
     */
    public function index_action()
    {
        // Navigation handling.
        Navigation::activateItem('/course/zoom/meetings');
        PageLayout::setTitle(Context::getHeaderLine() . ' - ' . dgettext('zoom', 'Meetings'));

        $this->permission = $GLOBALS['perm']->have_studip_perm('dozent', $this->course->id);

        $this->meetings = array_filter(ZoomMeeting::findByCourse_id($this->course->id), function($meeting) {
            if ($meeting->zoom_settings === 404) {
                $meeting->delete();
                return false;
            } else {
                return true;
            }
        });

        $neededPerm = in_array($this->course->status, studygroup_sem_types()) ? 'tutor' : 'dozent';
        if ($GLOBALS['perm']->have_studip_perm($neededPerm, $this->course->id)) {
            $me = ZoomAPI::getUser();
            if ($me === 404) {
                PageLayout::postWarning(
                    sprintf(
                        dgettext('zoom', 'Für Sie existiert noch keine Kennung in Zoom, '.
                        'daher können Sie momentan kein Meeting anlegen. Bitte loggen Sie sich einmalig '.
                        'unter <a href="%1$s" target="_blank">%1$s</a> ein, damit ihre Kennung angelegt wird. '.
                        'Danach können Sie hier Meetings anlegen.'),
                    Config::get()->ZOOM_LOGIN_URL));
            } else {
                $sidebar = Sidebar::get();
                $actions = new ActionsWidget();
                $actions->addLink(dgettext('zoom', 'Meeting erstellen'),
                    $this->link_for('zoom_meetings/edit'),
                    Icon::create('add'))->asDialog('size="auto"');
                $actions->addLink(dgettext('zoom', 'Meeting aus Zoom importieren'),
                    $this->link_for('zoom_meetings/import'),
                    Icon::create('install'))->asDialog('size="auto"');
                $sidebar->addWidget($actions);
            }
        }

    }

    /**
     * Creates or edits a Zoom meeting.
     *
     * @param int $id if given, $id specifies the ZoomMeeting to edit
     */
    public function edit_action($id = 0)
    {
        // In studygroups, author permissions are sufficient.
        $neededPerm = in_array($this->course->status, studygroup_sem_types()) ? 'tutor' : 'dozent';
        if (!$GLOBALS['perm']->have_studip_perm($neededPerm, $this->course->id)) {
            throw new AccessDeniedException();
        }

        $zoom = Navigation::getItem('/course/zoom');
        $zoom->addSubNavigation('edit', new Navigation($id == 0 ?
            dgettext('zoom', 'Zoom-Meeting anlegen') :
            dgettext('zoom', 'Zoom-Meeting bearbeiten'),
            PluginEngine::getURL($this, [], 'zoom_meetings')));
        Navigation::activateItem('/course/zoom/edit');

        $this->my_meetings = Request::int('my', 0);

        $this->user = ZoomAPI::getUser();

        // Get other users who are lecturers in this course.
        $this->otherLecturers = SimpleCollection::createFromArray(
            CourseMember::findBySQL(
                "`Seminar_id` = :course AND `user_id` != :me AND `status` IN (:perms) ORDER BY `position`",
                [
                    'course' => $this->course->id,
                    'me' => $GLOBALS['user']->id,
                    'perms' => in_array($this->course->status, studygroup_sem_types()) ? ['tutor', 'dozent'] : ['dozent']
                ]
            )
        );

        // Other lecturers are only possible co-hosts if their Zoom account exists.
        $this->possibleCoHosts = count($this->otherLecturers) > 0 ?
            ZoomAPI::usersExist($this->otherLecturers) :
            [];

        // Get number of unavailable persons for extra info message.
        $this->unavailable = count(array_filter($this->possibleCoHosts, function($one) {
            return $one == false;
        }));

        // Check if this course has any future dates.
        $this->dateCount = CourseDate::countBySQL(
            "`range_id` = :course AND `date` >= :now ORDER BY `date` ASC",
            ['course' => $this->course->id, 'now' => time()]
        );

        $this->turnout = CourseMember::countBySQL(
            "`Seminar_id` = :id AND `status` in ('user', 'autor')",
            ['id' => $this->course->id]
        );

        $this->turnout = CourseMember::countBySQL(
            "`Seminar_id` = :id AND `status` in ('user', 'autor')",
            ['id' => $this->course->id]
        );

        // Edit an existing meeting.
        if ($id != 0) {
            $this->meeting = ZoomMeeting::find($id);
            $this->meeting->useCache = false;

            // Check if current user is (alternative) host, only then do we have permission to edit.
            if (!$this->meeting->isHost()) {
                throw new AccessDeniedException();
            }

            $this->roomSettings = ZoomAPI::getRoomSettings(
                $this->meeting->webinar ? 'webinar' : 'meeting');

            // Meeting not found in Zoom, delete it automatically.
            if ($this->meeting->zoom_settings === 404) {
                if ($this->meeting->delete()) {
                    PageLayout::postWarning(
                        dgettext('zoom','Das Meeting ist nicht mehr in Zoom vorhanden und ' .
                            'wurde daher auch in Stud.IP gelöscht.'));
                } else {
                    PageLayout::postWarning(
                        dgettext('zoom','Das Meeting ist nicht mehr in Zoom vorhanden, ' .
                            'konnte aber nicht automatisch in Stud.IP gelöscht werden.'));
                }
                $this->relocate(Request::int('my', 0) == 1 ? 'my_zoom_meetings' : 'zoom_meetings');
            }

        } else {
            // Create a new meeting object.
            $this->meeting = new ZoomMeeting();

            // mode 'coursedates' can only be set if the current course has dates.
            $this->meeting->type = $this->dateCount > 0 ? 'coursedates' : 'manual';

            // Check for turnout and available options.
            $mayCreate = true;
            if ($this->turnout > ZoomAPI::MAX_MEETING_MEMBERS) {
                $settings = ZoomAPI::getUserSettings();

                if (!$settings->feature->webinar) {
                    $mayCreate = false;
                    $this->max_turnout = ZoomAPI::MAX_MEETING_MEMBERS;
                    $this->need_license = true;
                } else if ($settings->feature->webinar_capacity < $this->turnout) {
                    $this->max_turnout = $settings->feature->webinar_capacity;
                    $this->need_larger_license = true;
                }
            }

            $this->meeting->webinar = ($this->turnout > ZoomAPI::MAX_MEETING_MEMBERS && $mayCreate) ? 1 : 0;

            // Set meeting start to next hour per default.
            $nextHour = new DateTime('now +1 hour', new DateTimeZone(ZoomAPI::LOCAL_TIMEZONE));
            $nextHour->setTime($nextHour->format('H'), 0, 0);

            // Create some default zoom settings.
            $settings = new StdClass();
            $settings->topic = $this->course->getFullname();
            $settings->start_time = $nextHour;
            $settings->duration = $this->user->type == ZoomAPI::LICENSE_BASIC ? 40 : 90;
            $settings->password = rand(100000, 9999999999);
            $settings->agenda = '';
            $settings->settings = new StdClass();

            $this->roomSettings = ZoomAPI::getRoomSettings(
                $this->meeting->webinar ? 'webinar' : 'meeting');
            foreach ($this->roomSettings as $name => $one) {
                $settings->settings->$name = $one['default'];
            }

            $settings->settings->alternative_hosts = '';

            $this->meeting->zoom_settings = $settings;
        }

    }

    /**
     * Stores a Zoom meeting, not only to Stud.IP database,
     * but also by using Zoom API to send given data.
     */
    public function store_action()
    {
        // In studygroups, author permissions are sufficient.
        $neededPerm = in_array($this->course->status, studygroup_sem_types()) ? 'tutor' : 'dozent';
        if (!$GLOBALS['perm']->have_studip_perm($neededPerm, $this->course->id)) {
            throw new AccessDeniedException();
        }

        CSRFProtection::verifyUnsafeRequest();

        if (($id = Request::int('meeting_id', 0)) != 0) {

            $meeting = ZoomMeeting::find($id);

        } else {

            $turnout = CourseMember::countBySQL(
                "`Seminar_id` = :id AND `status` in ('user', 'autor')",
                ['id' => $this->course->id]
            );

            $meeting = new ZoomMeeting();
            $meeting->user_id = User::findCurrent()->id;
            $meeting->course_id = $this->course->id;
            $meeting->mkdate = date('Y-m-d H:i:s');

            // Check for turnout and available options.
            $meeting->webinar = 0;
            if ($turnout > ZoomAPI::MAX_MEETING_MEMBERS) {
                $settings = ZoomAPI::getUserSettings();

                if ($settings->feature->webinar) {
                    $meeting->webinar = 1;
                }
            }
        }

        $meeting->chdate = date('Y-m-d H:i:s');

        // Create meeting according to course dates.
        if (Request::option('create_type') == 'coursedates') {
            $meeting->type = 'coursedates';

            $nextDate = CourseDate::findOneBySQL(
                "`range_id` = :course AND `date` >= :now ORDER BY `date` ASC",
                ['course' => $this->course->id, 'now' => time()]
            );

            if ($nextDate) {
                $startTime = new DateTime();
                $startTime->setTimestamp($nextDate->date);
                $duration = ($nextDate->end_time - $nextDate->date) / 60;
            } else {
                $startTime = $meeting->zoom_settings->start_time;
                $duration = $meeting->zoom_settings->duration;
            }

            $type = ZoomAPI::MEETING_SCHEDULED;

        // Create meeting with manual date(s).
        } else {

            $meeting->type = 'manual';

            $startTime = new DateTime(Request::get('start_time'));
            $duration = Request::int('duration');

            if (Request::int('recurring') == 0) {

                $type = $meeting->webinar ?
                    ZoomAPI::WEBINAR_WEBINAR :
                    ZoomAPI::MEETING_SCHEDULED;

            } else {

                $type = $meeting->webinar ?
                    ZoomAPI::WEBINAR_RECURRING_FIXED_TIME :
                    ZoomAPI::MEETING_RECURRING_FIXED_TIME;

                // Try to find the last date before lecture period end.
                $semesterEnd = new DateTime();
                $semesterEnd->setTimestamp($this->course->start_semester->vorles_ende);
                $weekdays = ZoomAPI::getWeekdays();

                $lastDate = new DateTime($semesterEnd->format('d.m.Y') .
                    ' +1 day last ' . $weekdays[$startTime->format('w') + 1]['name_en']);

                $startTimeZoom = $startTime;
                $startTimeZoom->setTimezone(new DateTimeZone(ZoomAPI::ZOOM_TIMEZONE));
                $recurrence = [
                    'type' => ZoomAPI::RECURRENCE_WEEKLY,
                    'repeat_interval' => 1,
                    'weekly_days' => $startTime->format('w') + 1,
                    'end_date_time' => $lastDate->format('Y-m-d') .
                        'T' . $startTimeZoom->format('H:i:s') . 'Z'
                ];

            }

        }

        $zoomArray = Request::getArray('settings');

        $availableOptions = ZoomAPI::getRoomSettings($meeting->webinar ? 'webinar' : 'meeting');

        $options = [];
        foreach ($availableOptions as $one) {
            $options[$one['name']] = $zoomArray[$one['name']] == 1 ? true : false;
        }

        $zoomSettings = [
            'type' => $type,
            'topic' => Request::get('topic'),
            'start_time' => $startTime->format('Y-m-d') . 'T' . $startTime->format('H:i:s'),
            'timezone' => ZoomAPI::LOCAL_TIMEZONE,
            'duration' => $duration,
            'password' => Request::get('password'),
            'agenda' => Request::get('agenda'),
            'settings' => $options
        ];

        if ($cohosts = Request::getArray('co_hosts')) {
            $users = SimpleCollection::createFromArray(User::findMany($cohosts))->pluck('email');

            $zoomSettings['settings']['alternative_hosts'] = implode(',', $users);
        } else {
            $zoomSettings['settings']['alternative_hosts'] = '';
        }

        if (Request::option('create_type') != 'coursedates') {
            if (Request::int('recurring') != 0) {
                $zoomSettings['recurrence'] = $recurrence;
            } else {
                $zoomSettings['recurrence'] = null;
            }
        }

        if (!$meeting->isNew()) {
            $zoomMeeting = ZoomAPI::updateMeeting($meeting->zoom_settings->host_id,
                $meeting->zoom_meeting_id, $zoomSettings, $meeting->webinar);
        } else {
            $zoomMeeting = ZoomAPI::createMeeting($GLOBALS['user']->email, $zoomSettings, $meeting->webinar);
        }

        if ($zoomMeeting != null && $zoomMeeting->id) {
            $meeting->zoom_meeting_id = $zoomMeeting->id;

            if ($meeting->store() !== false) {
                PageLayout::postSuccess(dgettext('zoom', 'Das Meeting wurde gespeichert.'));
            } else {
                PageLayout::postError(dgettext('zoom', 'Das Meeting kann nicht gespeichert werden.'));
            }

        } else {

            PageLayout::postError(dgettext('zoom',
                'Das Meeting kann nicht gespeichert werden, da die Daten nicht an Zoom übertragen werden konnten.'));

        }

        $this->relocate(Request::int('my', 0) == 1 ? 'my_zoom_meetings' : 'zoom_meetings');
    }

    /**
     * Confirm meeting deletion, offering the choice to only
     * delete in Stud.IP and not in Zoom.
     *
     * @param int $id meeting ID
     */
    public function ask_delete_action($id)
    {
        $this->id = $id;
        $this->my = Request::int('my', 0);
    }

    /**
     * Deletes a Zoom meeting.
     *
     * @param int $id meeting ID
     */
    public function delete_action($id)
    {
        CSRFProtection::verifyUnsafeRequest();

        // In studygroups, author permissions are sufficient.
        $neededPerm = in_array($this->course->status, studygroup_sem_types()) ? 'tutor' : 'dozent';
        if (!$GLOBALS['perm']->have_studip_perm($neededPerm, $this->course->id)) {
            throw new AccessDeniedException();
        }

        $this->my_meetings = Request::int('my', 0);

        $meeting = ZoomMeeting::find($id);

        // Check if current user is (alternative) host, only then do we have permission to delete.
        if (!$meeting->isHost()) {
            throw new AccessDeniedException();
        }

        if (Request::int('only_studip') || ZoomAPI::deleteMeeting($meeting->zoom_meeting_id, $meeting->webinar) !== null) {

            if ($meeting->delete()) {
                PageLayout::postSuccess(dgettext('zoom', 'Das Meeting wurde gelöscht.'));
            } else {
                PageLayout::postError(dgettext('zoom', 'Das Meeting konnte nicht aus Stud.IP gelöscht werden.'));
            }

        } else {
            PageLayout::postError(dgettext('zoom', 'Das Meeting konnte nicht in Zoom gelöscht werden.'));
        }

        $this->relocate(Request::int('my', 0) == 1 ? 'my_zoom_meetings' : 'zoom_meetings');
    }

    /**
     * Shows a dialog for importing an existing Zoom meeting into
     * Stud.IP and assigning it to the current course.
     */
    public function import_action()
    {
        // In studygroups, author permissions are sufficient.
        $neededPerm = in_array($this->course->status, studygroup_sem_types()) ? 'tutor' : 'dozent';
        if (!$GLOBALS['perm']->have_studip_perm($neededPerm, $this->course->id)) {
            throw new AccessDeniedException();
        }
    }

    /**
     * Tries to import the given Zoom meeting ID.
     * If the meeting ID is not found in Zoom, an error message is shown.
     * If the meeting already exists in Stud.IP, a warning is shown.
     * Otherwise, a new ZoomMeeting object is created and stored to database.
     */
    public function do_import_action()
    {
        // In studygroups, author permissions are sufficient.
        $neededPerm = in_array($this->course->status, studygroup_sem_types()) ? 'tutor' : 'dozent';
        if (!$GLOBALS['perm']->have_studip_perm($neededPerm, $this->course->id)) {
            throw new AccessDeniedException();
        }

        $zoomId = Request::get('zoom_id', '');
        if ($zoomId == '') {

            PageLayout::postError(dgettext('zoom', 'Es wurde keine ID angegeben.'));
            $data = null;

        } else {

            // Clear hyphens and spaces if necessary.
            $zoomId = str_replace(['-', ' '], '', trim($zoomId));

            // First of all, check if the given meeting is already present.
            $studip = ZoomMeeting::findByZoom_meeting_id($zoomId);

            if (count($studip) > 0) {

                PageLayout::postWarning(dgettext('zoom', 'Das Meeting mit der angegebenen ID ist '.
                    'bereits einer Stud.IP-Veranstaltung zugeordnet.'));
                $data = null;

            } else {

                $data = ZoomAPI::getMeeting($zoomId, false, Request::int('webinar') == 1);

                // Meeting with the given ID not found in Zoom.
                if ($data === 404) {

                    PageLayout::postError(dgettext('zoom', 'Das Meeting mit der angegebenen ID '.
                        'konnte nicht in Zoom gefunden werden.'));

                // Some error occurred on API call.
                } else if ($data === null) {

                    PageLayout::postError(dgettext('zoom', 'Die Daten des Meetings konnten nicht ' .
                        'aus Zoom ausgelesen werden.'));

                }

            }

            // We have a meeting here, import it.
            if ($data !== 404 && $data !== null) {

                $meeting = new ZoomMeeting();

                // Fetch meeting host which is not necessarily myself.
                $user = ZoomAPI::getUserByZoomId($data->host_id);

                /*
                 * Check if current user is (alternative) host in the
                 * meeting to import. If not, abort import.
                 */
                // Fetch my data from Zoom to check if I am host.
                $me = ZoomAPI::getUser();

                // Prevent import of personal meeting rooms.
                if ($zoomId == $user->pmi || $zoomId == $me->pmi) {

                    PageLayout::postError(dgettext('zoom',
                        'Persönliche Meetingräume können nicht importiert werden.'));

                } else {

                    // Get alternative hosts for this meeting.
                    $alternative = $data->settings->alternative_hosts ?: '';

                    // Is the current user host or alternative host?
                    $isHost = ($me->id == $data->host_id ||
                        in_array($GLOBALS['user']->email, explode(',', $alternative)));

                    if ($isHost) {

                        $studipUser = $user ? User::findOneByEmail($user->email) : User::findCurrent();

                        $meeting->user_id = $studipUser->id;
                        $meeting->course_id = $this->course->id;
                        $meeting->type = 'manual';
                        $meeting->zoom_meeting_id = $zoomId;
                        $meeting->webinar = Request::int('webinar');
                        $meeting->mkdate = date('Y-m-d H:i:s');
                        $meeting->chdate = date('Y-m-d H:i:s');

                        if ($meeting->store()) {
                            PageLayout::postSuccess(dgettext('zoom',
                                'Das Meeting wurde erfolgreich importiert.'));
                        } else {
                            PageLayout::postError(dgettext('zoom',
                                'Das Meeting konnte nicht importiert werden.'));
                        }

                    } else {

                        PageLayout::postError(dgettext('zoom',
                            'Sie dürfen nur Meetings importieren, ' .
                            'deren Host oder alternativer Host Sie sind.'));

                    }
                }

            }
        }
        $this->relocate('zoom_meetings');
    }

    /**
     * Joins the given meeting. The URL differs whether you are a (co-)host or a participant.
     *
     * @param int $id the Zoom meeting to join
     */
    public function join_action($id)
    {
        $meeting = ZoomMeeting::find($id);
        if ($meeting->isHost()) {
            $this->relocate($meeting->zoom_settings->start_url);
        } else {
            $this->relocate($meeting->zoom_settings->join_url);
        }
    }

}
