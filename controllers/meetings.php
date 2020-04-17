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

        $this->meetings = ZoomMeeting::findByCourse_id($this->course->id);

        if ($GLOBALS['perm']->have_studip_perm('dozent', $this->course->id)) {
            $sidebar = Sidebar::get();
            $actions = new ActionsWidget();
            $actions->addLink(dgettext('zoom', 'Meeting erstellen'),
                $this->link_for('meetings/edit'),
                Icon::create('add'))->asDialog('size="auto"');
            $sidebar->addWidget($actions);
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

        $videos = Navigation::getItem('/course/zoom');
        $videos->addSubNavigation('edit', new Navigation($id == 0 ?
            dgettext('zoom', 'Zoom-Meeting anlegen') :
            dgettext('zoom', 'Zoom-Meeting bearbeiten'),
            PluginEngine::getURL($this, [], 'meetings')));
        Navigation::activateItem('/course/zoom/edit');

        $this->user = ZoomAPI::getUser();

        if ($id != 0) {
            $this->meeting = ZoomMeeting::find($id);
        } else {
            $this->meeting = new ZoomMeeting();

            $nextHour = new DateTime('now +1 hour', new DateTimeZone(ZoomAPI::LOCAL_TIMEZONE));

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

        if (Request::option('create_type') == 'coursedates') {
            $nextDate = CourseDate::findOneBySQL(
                "`range_id` = :course AND `date` >= :now",
                ['course' => $this->course->id, 'now' => time()]
            );
            $startTime = new DateTime();
            $startTime->setTimestamp($nextDate->date);
            $duration = ($nextDate->end_time - $nextDate->date) / 60;
            $type = ZoomAPI::MEETING_SCHEDULED;

        } else {

            $startTime = new DateTime(Request::get('start_time'));
            $duration = Request::int('duration');
            $type = ZoomAPI::MEETING_SCHEDULED;

        }

        // Add other lecturers as co-hosts.
        $otherLecturers = SimpleCollection::createFromArray(
            CourseMember::findBySQL(
                "`Seminar_id` = :course AND `user_id` != :me AND `status` = 'dozent'",
                ['course' => $this->course->id, 'me' => $GLOBALS['user']->id]
            )
        )->pluck('email');

        $zoomSettings = [
            'type' => $type,
            'topic' => Request::get('topic'),
            'start_time' => $startTime->format('Y-m-d') . 'T' . $startTime->format('H:i:s'),
            'timezone' => ZoomAPI::LOCAL_TIMEZONE,
            'duration' => $duration,
            'password' => Request::get('password'),
            'agenda' => Request::get('agenda'),
            'settings' => [
                'host_video' => Request::int('host_video') == 1 ? true : false,
                'participant_video' => Request::int('participant_video') == 1 ? true : false,
                'join_before_host' => Request::int('join_before_host') == 1 ? true : false,
                'mute_upon_entry' => Request::int('mute_upon_entry') == 1 ? true : false,
                'alternative_hosts' => implode(',', $otherLecturers)
            ]
        ];

        if (($id = Request::int('meeting_id', 0)) != 0) {

        } else {

            $zoomMeeting = ZoomAPI::createMeeting($GLOBALS['user']->email, $zoomSettings);

        }

        if ($zoomMeeting != null) {
            $meeting->zoom_meeting_id = $zoomMeeting->id;

            if ($meeting->store()) {
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
     * Joins the given meeting. The URL differs whether you are a (co-)host or a participant.
     *
     * @param int $id the Zoom meeting to join
     */
    public function join_action($id)
    {
        $meeting = ZoomMeeting::find($id);
        if ($GLOBALS['perm']->have_studip_perm('dozent', $this->course->id) &&
                !$GLOBALS['perm']->have_studip_perm('admin', $this->course->id)) {
            $this->relocate($meeting->zoom_settings->start_url);
        } else {
            $this->relocate($meeting->zoom_settings->join_url);
        }
    }

}
