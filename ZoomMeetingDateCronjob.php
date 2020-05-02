<?php
/**
 * ZoomMeetingDateCronjob.php
 *
 * Cronjob for autmagically moving Zoom meetings along course dates.
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

class ZoomMeetingDateCronjob extends CronJob {

    public static function getName() {
        return dgettext('zoom', 'Zoom-Meetings automatisch an Veranstaltungstermine anpassen');
    }

    public static function getDescription() {
        return dgettext('zoom', 'Aktualisiert vergangene Zoom-Meetings in Veranstaltungen '.
            'und setzt die Zeit auf den jeweils nächsten Termin.');
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameters()
    {
        return [
            'verbose' => [
                'type' => 'boolean',
                'default' => false,
                'status' => 'optional',
                'description' => _('Sollen Ausgaben erzeugt werden?'),
            ],
        ];
    }

    /**
     * Update all relevant Zoom meetings.
     */
    public function execute($last_result, $parameters = [])
    {
        StudipAutoloader::addAutoloadPath(__DIR__ . '/models');

        $semester = Semester::findCurrent();

        // Find all Zoom meetings belonging to courses in current semester which are set to follow the course dates.
        $meetings = DBManager::get()->fetchAll("SELECT DISTINCT m.* FROM `zoom_meetings` m
                JOIN `seminare` s ON (s.`Seminar_id` = m.`course_id`)
                JOIN `termine` t ON (t.`range_id` = s.`Seminar_id`)
            WHERE (s.`start_time` + s.`duration_time` BETWEEN :start AND :end
                    OR (s.`duration_time` = -1 AND s.`start_time` <= :start))
                AND m.`type` = 'coursedates'
            ORDER BY s.`VeranstaltungsNummer`, s.`Name`",
            ['start' => $semester->beginn, 'end' => $semester->ende],
            'ZoomMeeting::buildExisting');

        $now = time();

        foreach ($meetings as $m) {

            $m->useCache = false;

            if ($parameters['verbose']) {
                echo sprintf("Processing course %s with Zoom ID %s.\n",
                    $m->course_id, $m->zoom_meeting_id);
            }

            if ($m->zoom_settings === null || $m->zoom_settings === 404) {
                // Meeting not found in Zoom, delete it locally.
                if ($m->zoom_settings === 404) {
                    echo sprintf("Zoom meeting %s not found in Zoom, deleting.\n",
                        $m->course_id, $m->zoom_meeting_id);
                    $m->delete();
                } else {
                    echo sprintf("Could not retrieve settings for course %s with Zoom ID %s.\n",
                        $m->course_id, $m->zoom_meeting_id);
                }
            } else {
                // Consider only meetings which are already finished.
                if ($m->zoom_settings->start_time->getTimestamp() + ($m->zoom_settings->duration * 60) < $now) {

                    // Find next course date.
                    $nextDate = CourseDate::findOneBySQL(
                        "`range_id` = :course AND `date` >= :now ORDER BY `date` ASC",
                        ['course' => $m->course_id, 'now' => $now]
                    );

                    if ($nextDate) {
                        $startTime = new DateTime();
                        $startTime->setTimestamp($nextDate->date);
                        $duration = ($nextDate->end_time - $nextDate->date) / 60;

                        if ($parameters['verbose']) {
                            echo sprintf("Next date is %s.\n",
                                date('Y-m-d H:i', $nextDate->date));
                        }

                        $newSettings = $m->zoom_settings;

                        // Some values must not be updated by Stud.IP
                        foreach (words('option domains name') as $one) {
                            unset('authentication_' . $one, $newSettings);
                        }

                        $newSettings->start_time =
                            $startTime->format('Y-m-d') . 'T' . $startTime->format('H:i:s');
                        $newSettings->duration = $duration;
                        $result = ZoomAPI::updateMeeting($m->zoom_meeting_id, $newSettings);

                        if ($result !== null && $result !== 404) {
                            if ($parameters['verbose']) {
                                echo sprintf("Updated settings for course %s with Zoom ID %s.\n",
                                    $m->course_id, $m->zoom_meeting_id);
                            }
                        } else {
                            echo sprintf("Could not store settings for course %s with Zoom ID %s.\n",
                                $m->course_id, $m->zoom_meeting_id);
                        }
                    } else {
                        echo sprintf("Course %s with Zoom ID %s has no next date, keeping data.\n",
                            $m->course_id, $m->zoom_meeting_id);
                    }
                }
            }
        }
    }

}
