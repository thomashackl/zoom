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

        if ($GLOBALS['perm']->have_studip_perm('dozent', $this->course->id)) {
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
                    $this->link_for('meetings/edit'),
                    Icon::create('add'))->asDialog('size="auto"');
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
        if (!$GLOBALS['perm']->have_studip_perm('dozent', $this->course->id)) {
            throw new AccessDeniedException();
        }

        $zoom = Navigation::getItem('/course/zoom');
        $zoom->addSubNavigation('edit', new Navigation($id == 0 ?
            dgettext('zoom', 'Zoom-Meeting anlegen') :
            dgettext('zoom', 'Zoom-Meeting bearbeiten'),
            PluginEngine::getURL($this, [], 'meetings')));
        Navigation::activateItem('/course/zoom/edit');

        $this->user = ZoomAPI::getUser();

        $this->otherLecturers = SimpleCollection::createFromArray(
            CourseMember::findBySQL(
                "`Seminar_id` = :course AND `user_id` != :me AND `status` = 'dozent' ORDER BY `position`",
                ['course' => $this->course->id, 'me' => $GLOBALS['user']->id]
            )
        );

        $this->possibleCoHosts = count($this->otherLecturers) > 0 ?
            ZoomAPI::usersExist($this->otherLecturers) :
            [];

        $this->unavailable = count(array_filter($this->possibleCoHosts, function($one) {
            return $one == false;
        }));

        if ($id != 0) {
            $this->meeting = ZoomMeeting::find($id);
            $this->meeting->useCache = false;
            $this->meeting->getZoom_Settings();
        } else {
            $this->meeting = new ZoomMeeting();
            $this->meeting->type = 'coursedates';

            $nextHour = new DateTime('now +1 hour', new DateTimeZone(ZoomAPI::LOCAL_TIMEZONE));
            $nextHour->setTime($nextHour->format('H'), 0, 0);

            $settings = new StdClass();
            $settings->topic = $this->course->getFullname();
            $settings->start_time = $nextHour;
            $settings->duration = $this->user->type == ZoomAPI::LICENSE_BASIC ? 40 : 90;
            $settings->password = rand(100000, 9999999999);
            $settings->agenda = '';
            $settings->settings = new StdClass();
            $settings->settings->host_video = 0;
            $settings->settings->participant_video = 0;
            $settings->settings->join_before_host = 0;
            $settings->settings->mute_upon_entry = 1;
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
        if (!$GLOBALS['perm']->have_studip_perm('dozent', $this->course->id)) {
            throw new AccessDeniedException();
        }

        CSRFProtection::verifyUnsafeRequest();

        if (($id = Request::int('meeting_id', 0)) != 0) {

            $meeting = ZoomMeeting::find($id);

        } else {

            $meeting = new ZoomMeeting();
            $meeting->user_id = User::findCurrent()->id;
            $meeting->course_id = $this->course->id;
            $meeting->mkdate = date('Y-m-d H:i:s');

        }

        $meeting->chdate = date('Y-m-d H:i:s');

        // Create meeting according to course dates.
        if (Request::option('create_type') == 'coursedates') {
            $meeting->type = 'coursedates';

            $nextDate = CourseDate::findOneBySQL(
                "`range_id` = :course AND `date` >= :now ORDER BY `date` ASC",
                ['course' => $this->course->id, 'now' => time()]
            );
            $startTime = new DateTime();
            $startTime->setTimestamp($nextDate->date);
            $duration = ($nextDate->end_time - $nextDate->date) / 60;
            $type = ZoomAPI::MEETING_SCHEDULED;

        // Create meeting with manual date(s).
        } else {

            $meeting->type = 'manual';

            $startTime = new DateTime(Request::get('start_time'));
            $duration = Request::int('duration');

            if (Request::int('recurring') == 0) {

                $type = ZoomAPI::MEETING_SCHEDULED;

            } else {

                $type = ZoomAPI::MEETING_RECURRING_FIXED_TIME;

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

        $zoomSettings = [
            'type' => $type,
            'topic' => Request::get('topic'),
            'start_time' => $startTime->format('Y-m-d') . 'T' . $startTime->format('H:i:s'),
            'timezone' => ZoomAPI::LOCAL_TIMEZONE,
            'duration' => $duration,
            'password' => Request::get('password'),
            'agenda' => Request::get('agenda'),
            'settings' => [
                'host_video' => $zoomArray['host_video'] == 1 ? true : false,
                'participant_video' => $zoomArray['participant_video'] == 1 ? true : false,
                'join_before_host' => $zoomArray['join_before_host'] == 1 ? true : false,
                'mute_upon_entry' => $zoomArray['mute_upon_entry'] == 1 ? true : false
            ]
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
            $zoomMeeting = ZoomAPI::updateMeeting($meeting->zoom_meeting_id, $zoomSettings);
        } else {
            $zoomMeeting = ZoomAPI::createMeeting($GLOBALS['user']->email, $zoomSettings);
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

        $this->relocate('meetings');
    }

    /**
     * Deletes a Zoom meeting.
     *
     * @param $id
     */
    public function delete_action($id)
    {
        if (!$GLOBALS['perm']->have_studip_perm('dozent', $this->course->id)) {
            throw new AccessDeniedException();
        }

        $meeting = ZoomMeeting::find($id);
        if (ZoomAPI::deleteMeeting($meeting->zoom_meeting_id) !== null) {

            if ($meeting->delete()) {
                PageLayout::postSuccess(dgettext('zoom', 'Das Meeting wurde gelöscht.'));
            } else {
                PageLayout::postError(dgettext('zoom', 'Das Meeting konnte nicht aus Stud.IP gelöscht werden.'));
            }

        } else {
            PageLayout::postError(dgettext('zoom', 'Das Meeting konnte nicht in Zoom gelöscht werden.'));
        }

        $this->relocate('meetings');
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
