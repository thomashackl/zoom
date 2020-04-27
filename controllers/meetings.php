<?php

/**
 * Class MeetingsController
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

class MeetingsController extends AuthenticatedController {

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
                $this->turnout = CourseMember::countBySQL(
                    "`Seminar_id` = :id AND `status` in ('user', 'autor')",
                    ['id' => $this->course->id]
                );

                $mayCreate = true;
                if ($this->turnout > ZoomAPI::MAX_MEETING_MEMBERS) {
                    $settings = ZoomAPI::getUserSettings();

                    if (!$settings->feature->webinar) {
                        $mayCreate = false;

                        $this->need_license = true;
                    } else if ($settings->feature->webinar_capacity < $this->turnout) {
                        $mayCreate = false;

                        $this->max_turnout = $settings->feature->webinar_capacity;
                        $this->need_larger_license = true;
                    }
                }

                if ($mayCreate) {
                    $sidebar = Sidebar::get();
                    $actions = new ActionsWidget();
                    $actions->addLink(dgettext('zoom', 'Meeting erstellen'),
                        $this->link_for('meetings/edit'),
                        Icon::create('add'))->asDialog('size="auto"');
                    $sidebar->addWidget($actions);
                }
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
            PluginEngine::getURL($this, [], 'meetings')));
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

        if ($id != 0) {
            $this->meeting = ZoomMeeting::find($id);
            $this->meeting->useCache = false;

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
                $this->relocate(Request::int('my', 0) == 1 ? 'my_meetings' : 'meetings');
            }

        } else {
            // Create a new meeting object.
            $this->meeting = new ZoomMeeting();

            // mode 'coursedates' can only be set if the current course has dates.
            $this->meeting->type = $this->dateCount > 0 ? 'coursedates' : 'manual';
            // Check for turnout.
            $this->meeting->webinar = $this->turnout <= ZoomAPI::MAX_MEETING_MEMBERS ? 0 : 1;

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

            $settings = ZoomAPI::getUserSettings();

            $meeting = new ZoomMeeting();
            $meeting->user_id = User::findCurrent()->id;
            $meeting->course_id = $this->course->id;
            $meeting->mkdate = date('Y-m-d H:i:s');

            if ($turnout > ZoomAPI::MAX_MEETING_MEMBERS) {
                if ($settings->feature->webinar && $settings->feature->webinar_capacity >= $turnout) {
                    $meeting->webinar = 1;
                } else {
                    throw new AccessDeniedException(sprintf(dgettext('zoom',
                        'Ihre Veranstaltung hat mehr als %1$u Teilnehmende, was nicht mehr mit regulären ' .
                        'Zoom-Meetings abgedeckt werden kann. Um eine Freischaltung zur Erstellung größerer '.
                        'Webinare zu bekommen, wenden Sie sich bitte an <a href="mailto:%2$s">%2$s</a>.'),
                        ZoomAPI::MAX_MEETING_MEMBERS, $GLOBALS['UNI_CONTACT']));
                }
            } else {
                $meeting->webinar = 0;
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
                $startTimeZoom->setTimezone(ZoomAPI::ZOOM_TIMEZONE);
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
            $zoomMeeting = ZoomAPI::updateMeeting($meeting->zoom_meeting_id, $zoomSettings, $meeting->webinar);
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

        $this->relocate(Request::int('my', 0) == 1 ? 'my_meetings' : 'meetings');
    }

    /**
     * Deletes a Zoom meeting.
     *
     * @param $id
     */
    public function delete_action($id)
    {
        // In studygroups, author permissions are sufficient.
        $neededPerm = in_array($this->course->status, studygroup_sem_types()) ? 'tutor' : 'dozent';
        if (!$GLOBALS['perm']->have_studip_perm($neededPerm, $this->course->id)) {
            throw new AccessDeniedException();
        }

        $this->my_meetings = Request::int('my', 0);

        $meeting = ZoomMeeting::find($id);
        if (ZoomAPI::deleteMeeting($meeting->zoom_meeting_id, $meeting->webinar) !== null) {

            if ($meeting->delete()) {
                PageLayout::postSuccess(dgettext('zoom', 'Das Meeting wurde gelöscht.'));
            } else {
                PageLayout::postError(dgettext('zoom', 'Das Meeting konnte nicht aus Stud.IP gelöscht werden.'));
            }

        } else {
            PageLayout::postError(dgettext('zoom', 'Das Meeting konnte nicht in Zoom gelöscht werden.'));
        }

        $this->relocate(Request::int('my', 0) == 1 ? 'my_meetings' : 'meetings');
    }

    /**
     * Joins the given meeting. The URL differs whether you are a (co-)host or a participant.
     *
     * @param int $id the Zoom meeting to join
     */
    public function join_action($id)
    {
        $meeting = ZoomMeeting::find($id);
        if ($meeting->isHost($GLOBALS['user'])) {
            $this->relocate($meeting->zoom_settings->start_url);
        } else {
            $this->relocate($meeting->zoom_settings->join_url);
        }
    }

}
