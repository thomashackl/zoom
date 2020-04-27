<?php

/**
 * Class MyMeetingsController
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

class MyMeetingsController extends AuthenticatedController {

    /**
     * Actions and settings taking place before every page call.
     */
    public function before_filter(&$action, &$args)
    {
        $this->plugin = $this->dispatcher->current_plugin;

        if (!$GLOBALS['perm']->have_perm('user')) {
            throw new AccessDeniedException();
        }

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        $this->flash = Trails_Flash::instance();

        PageLayout::addScript($this->plugin->getPluginURL() . '/assets/javascripts/zoom.min.js');
        PageLayout::addStylesheet($this->plugin->getPluginURL() . '/assets/stylesheets/zoom.css');
    }

    /**
     * Show available zoom meetings in my courses.
     */
    public function index_action()
    {
        // Navigation handling.
        Navigation::activateItem('/browse/zoom');
        PageLayout::setTitle(dgettext('zoom', 'Meine Zoom-Meetings'));

        $me = User::findCurrent();

        // Get selected semester.
        $semesterId = Request::option('semester', 'current') ?:
            (UserConfig::get($me->id)->SELECTED_SEMESTER_ZOOM ?: 'current');
        $semester = $semesterId === 'current' ? Semester::findCurrent() : Semester::find($semesterId);

        if (($chosen = Request::option('semester', null)) !== null) {
            UserConfig::get($me->id)->store('SELECTED_SEMESTER_ZOOM', $chosen);
        }

        $sql = "SELECT DISTINCT s.`Seminar_id`, s.`VeranstaltungsNummer`, s.`Name` FROM `seminar_user` u
                JOIN `seminare` s ON (s.`Seminar_id` = u.`Seminar_id`)
            WHERE `user_id` = :me
                AND (
                        s.`start_time` + s.`duration_time` BETWEEN :start AND :end
                        OR s.`start_time` <= :start AND s.`duration_time` = -1
                    )";
        if (Config::get()->DEPUTIES_ENABLE) {
            $sql .= " UNION SELECT DISTINCT d.`range_id`, s.`VeranstaltungsNummer`, s.`Name`
                FROM `deputies` d
                    JOIN `seminare` s ON (s.`Seminar_id` = d.`range_id`)
                    WHERE `user_id` = :me
                AND (
                        s.`start_time` + s.`duration_time` BETWEEN :start AND :end
                        OR s.`start_time` <= :start AND s.`duration_time` = -1
                    )";
        }

        $sql .= " ORDER BY " . (Config::get()->IMPORTANT_SEMNUMBER ? "`VeranstaltungsNummer`, " : "") . "`Name`";

        $myCourses = DBManager::get()->fetchFirst($sql,
            [
                'me' => $me->id,
                'start' => $semester->beginn,
                'end' => $semester->ende
            ]);

        $meetings = ZoomMeeting::findBySQL("`course_id` IN (:courses)", ['courses' => $myCourses]);

        // Sort meetings by date, showing next meetings first.
        usort($meetings, function($a, $b) {
            return $b->zoom_settings->start_time - $a->zoom_settings->start_time;
        });

        $this->host = [];
        $this->participant = [];
        foreach ($meetings as $m) {
            if ($m->isHost($me)) {
                $this->host[] = $m;
            } else {
                $this->participant[] = $m;
            }
        }

        $sidebar = Sidebar::get();

        $widget = new SelectWidget(dgettext('zoom', 'Semesterfilter'),
            $this->link_for('my_meetings'), 'semester');
        $widget->setMaxLength(50);
        $widget->addElement(new SelectElement('current', dgettext('zoom', 'Aktuelles Semester'),
            $semester->id == 'current'));

        $semesters = Semester::getAll();
        if (!empty($semesters)) {
            $group = new SelectGroupElement(dgettext('zoom', 'Semester auswählen'));
            foreach ($semesters as $one) {
                if ($semester->visible) {
                    $group->addElement(new SelectElement($one->id, $one->name,
                        $semesterId !== 'current' && $semester->id == $one->id));
                }
            }
            $widget->addElement($group);
        }
        $sidebar->addWidget($widget);
    }

}
