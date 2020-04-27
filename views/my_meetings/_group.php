<table class="default zoom-meeting-overview">
    <caption><?php echo htmlReady($title) ?> <?php count($meetings) ?></caption>
    <colgroup>
        <col width="280">
        <col width="280">
        <col width="150">
        <?php if ($admin) : ?>
            <col width="60">
        <?php endif ?>
    </colgroup>
    <tr>
        <th><?php echo dgettext('zoom', 'Veranstaltung') ?></th>
        <th><?php echo dgettext('zoom', 'Meeting') ?></th>
        <th></th>
        <?php if ($admin) : ?>
            <th><?php echo dgettext('zoom', 'Aktionen') ?></th>
        <?php endif ?>
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
                <a href="<?php echo $controller->link_for('meetings/join/' . $m->id . '?cid=' . $m->course_id) ?>"
                   title="<?php echo dgettext('zoom', 'Teilnehmen') ?>" target="_blank">
                    <?php echo Icon::create('door-enter')->asImg(30) ?>
                    <?php echo dgettext('zoom', 'Teilnehmen') ?>
                </a>
            </td>
            <?php if ($admin) : ?>
                <td>
                    <a href="<?php echo $controller->link_for('meetings/edit/' . $m->id . '?my=1&cid=' . $m->course_id) ?>"
                       title="<?php echo dgettext('zoom', 'Bearbeiten') ?>"
                       data-dialog="size=auto">
                        <?php echo Icon::create('edit')->asImg(20) ?>
                    </a>
                    <a href="<?php echo $controller->link_for('meetings/delete/' . $m->id . '?my=1&cid=' . $m->course_id) ?>"
                       title="<?php echo dgettext('zoom', $admin ? 'Starten' : 'Teilnehmen') ?>"
                       data-confirm="<?php echo dgettext('zoom', 'Soll das Meeting wirklich gelöscht werden?') ?>">
                        <?php echo Icon::create('trash')->asImg(20) ?>
                    </a>
                </td>
            <?php endif ?>
        </tr>
    <?php endforeach ?>
</table>
