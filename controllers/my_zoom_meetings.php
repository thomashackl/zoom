<?php

/**
 * Class MyZoomMeetingsController
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

class MyZoomMeetingsController extends AuthenticatedController {

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

        $this->me = $GLOBALS['user'];

        // Get selected semester.
        $semesterId = Request::option('semester', 'current') ?:
            (UserConfig::get($this->me->id)->SELECTED_SEMESTER_ZOOM ?: 'current');
        $this->semester = $semesterId === 'current' ? Semester::findCurrent() : Semester::find($semesterId);

        PageLayout::setTitle(dgettext('zoom', 'Meine Zoom-Meetings'));

        if (($chosen = Request::option('semester', null)) !== null) {
            UserConfig::get($this->me->id)->store('SELECTED_SEMESTER_ZOOM', $chosen);
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
                'me' => $this->me->id,
                'start' => $this->semester->beginn,
                'end' => $this->semester->ende
            ]);

        $this->meetings = ZoomMeeting::findBySQL("`course_id` IN (:courses)", ['courses' => $myCourses]);

        // Sort meetings by date, showing next meetings first.
        usort($this->meetings, function($a, $b) {
            return $a->zoom_settings->start_time == $b->zoom_settings->start_time ? 0 :
                ($a->zoom_settings->start_time < $b->zoom_settings->start_time) ? -1 : 1;
        });

        $sidebar = Sidebar::get();

        $widget = new SelectWidget(dgettext('zoom', 'Semesterfilter'),
            $this->link_for('my_zoom_meetings'), 'semester');
        $widget->setMaxLength(50);
        $widget->addElement(new SelectElement('current', dgettext('zoom', 'Aktuelles Semester'),
            $this->semester->id == 'current'));

        $semesters = Semester::getAll();
        if (!empty($semesters)) {
            $group = new SelectGroupElement(dgettext('zoom', 'Semester auswählen'));
            foreach ($semesters as $one) {
                if ($this->semester->visible) {
                    $group->addElement(new SelectElement($one->id, $one->name,
                        $semesterId !== 'current' && $this->semester->id == $one->id));
                }
            }
            $widget->addElement($group);
        }
        $sidebar->addWidget($widget);
    }

}
