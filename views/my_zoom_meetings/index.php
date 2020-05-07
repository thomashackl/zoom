<h1><?php echo $semester->name ?></h1>
<?php if (count($meetings) < 1) : ?>
    <?php echo MessageBox::info(
        dgettext('zoom', 'Es wurden keine Meetings in Ihren Veranstaltungen gefunden.')) ?>
<?php else : ?>
    <table class="default zoom-meeting-overview">
        <caption>
            <?php echo dgettext('zoom', 'Meine Zoom-Meetings') ?>
        </caption>
        <colgroup>
            <col>
            <col>
            <col width="150">
        </colgroup>
        <tr>
            <th><?php echo dgettext('zoom', 'Veranstaltung') ?></th>
            <th><?php echo dgettext('zoom', 'Meeting') ?></th>
            <th><?php echo dgettext('zoom', 'Aktionen') ?></th>
        </tr>
        <?php foreach ($meetings as $m) : ?>
            <tr>
                <td>
                    <a href="<?php echo URLHelper::getLink('dispatch.php/course/overview', ['cid' => $m->course_id]) ?>"
                       title="<?php echo dgettext('zoom', 'Zur Veranstaltung') ?>">
                        <?php echo htmlReady($m->course->getFullname()) ?>
                    </a>
                </td>
                <td>
                    <?php echo htmlReady($m->zoom_settings->topic) ?>
                    <br>
                    <?php echo sprintf(dgettext('zoom', 'Nächster Termin: %s'),
                        htmlReady($m->zoom_settings->start_time->format('d.m.Y H:i'))) ?>
                </td>
                <td class="join-meeting">
                    <a href="<?php echo $controller->link_for('zoom_meetings/join/' . $m->id . '?cid=' . $m->course_id) ?>"
                       title="<?php echo dgettext('zoom', 'Teilnehmen') ?>" target="_blank">
                        <?php echo Icon::create('door-enter')->asImg(30) ?>
                        <?php echo dgettext('zoom', 'Teilnehmen') ?>
                    </a>
                <?php if ($m->isHost($me->id)) : ?>
                    <?php
                        $actions = ActionMenu::get();
                        $actions->addLink(
                            $controller->link_for('zoom_meetings/edit', $m->id) . '?cid=' . $m->course_id . '&my=1',
                            dgettext('zoom', 'Bearbeiten'),
                            Icon::create('edit'),
                            ['data-dialog' => 'size=auto']
                        );
                        $actions->addLink(
                            $controller->link_for('zoom_meetings/ask_delete', $m->id) . '?cid=' . $m->course_id . '&my=1',
                            dgettext('zoom', 'Löschen'),
                            Icon::create('trash'),
                            ['data-dialog' => 'size=auto']
                        );

                        echo $actions->render();
                    ?>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
    </table>
<?php endif ?>
