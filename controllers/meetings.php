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
    }

    /**
     * Show available zoom meetings for this course.
     */
    public function index_action($page = 1)
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
        $this->user = ZoomAPI::getUser();

        if ($id != 0) {
            $this->meeting = ZoomMeeting::find($id);
        } else {
            $this->meeting = new ZoomMeeting();

            $nextHour = new DateTime('now +1 hour', new DateTimeZone('Europe/Berlin'));

            $this->meeting->zoom_settings = [
                'topic' => $this->course->getFullname(),
                'start_time' => $nextHour->format('d.m.Y H:00'),
                'duration' => $this->user->type == ZoomAPI::$LICENSE_BASIC ? 40 : 90,
                'password' => rand(100000, 9999999999),
                'agenda' => '',
                'host_video' => 0,
                'participant_video' => 0,
                'join_before_host' => 0,
                'mute_upon_entry' => 1
            ];
        }
    }

    /**
     * Stores a Zoom meeting, not only to Stud.IP database,
     * but also by using Zoom API to send given data.
     */
    public function store_action()
    {
        if (($id = Request::int('meeting_id', 0)) != 0) {
            $meeting = ZoomMeeting::find($id);
        } else {
            $meeting = new ZoomMeeting();
            $meeting->user_id = User::findCurrent()->id;
            $meeting->course_id = $this->course->id;
            $meeting->mkdate = date('Y-m-d H:i:s');
        }
        $meeting->visible_from = Request::get('visible_from') ? new DateTime(Request::get('visible_from')) : null;
        $meeting->visible_until = Request::get('visible_until') ? new DateTime(Request::get('visible_until')) : null;
        $meeting->chdate = date('Y-m-d H:i:s');

        $startTime = new DateTime(Request::get('start_time'));

        $zoomSettings = [
            'type' => ZoomAPI::$MEETING_SCHEDULED,
            'topic' => Request::get('topic'),
            'start_time' => $startTime->format('Y-m-d') . 'T' . $startTime->format('H:i:s'),
            'timezone' => 'Europe/Berlin',
            'duration' => Request::int('duration'),
            'password' => Request::get('password'),
            'agenda' => Request::get('agenda'),
            'settings' => [
                'host_video' => Request::get('host_video') == 1 ? true : false,
                'participant_video' => Request::get('participant_video') == 1 ? true : false,
                'join_before_host' => Request::get('join_before_host') == 1 ? true : false,
                'mute_upon_entry' => Request::get('mute_upon_entry') == 1 ? true : false
            ]
        ];

        $zoomMeeting = ZoomAPI::createMeeting($GLOBALS['user']->email, $zoomSettings);

        if ($zoomMeeting != null) {
            $meeting->zoom_meeting_id = $zoomMeeting->id;

            if ($meeting->store()) {
                PageLayout::postSuccess(dgettext('zoom', 'Das Meeting wurde gespeichert.'));
                PageLayout::postInfo('Meeting ' . $zoomMeeting->id . ': start with ' . $zoomMeeting->start_url . ', join with ' . $zoomMeeting->join_url);
            } else {
                PageLayout::postError(dgettext('zoom', 'Das Meeting kann nicht gespeichert werden.'));
            }
        } else {
            PageLayout::postError(dgettext('zoom',
                'Das Meeting kann nicht gespeichert werden, da die Daten nicht an Zoom übertragen werden konnten.'));
        }

        $this->relocate('meetings');
    }

}
