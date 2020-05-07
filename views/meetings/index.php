<?php if ($need_license) : ?>
    <?php echo MessageBox::warning(sprintf(dgettext('zoom',
        'Ihre Veranstaltung hat mehr als %1$u Teilnehmende, was nicht mehr mit regulären ' .
        'Zoom-Meetings abgedeckt werden kann. Um eine Freischaltung zur Erstellung größerer Webinare zu bekommen, ' .
        'wenden Sie sich bitte an <a href="mailto:%2$s">%2$s</a>.'),
        ZoomAPI::MAX_MEETING_MEMBERS, $GLOBALS['UNI_CONTACT'])) ?>
<?php endif ?>
<?php if ($need_larger_license) : ?>
    <?php echo MessageBox::warning(sprintf(dgettext('zoom',
        'Ihre Veranstaltung hat %1$u Teilnehmende, was nicht mit Ihrer freigeschalteten Webinarlizenz ' .
        '(bis %2$u Personen) abgedeckt werden kann. Um eine Freischaltung zur Erstellung größerer Webinare zu ' .
        'bekommen, wenden Sie sich bitte an <a href="mailto:%3$s">%3$s</a>.'),
        $turnout, $max_turnout, $GLOBALS['UNI_CONTACT'])) ?>
<?php endif ?>
<?php if (count($meetings) == 0) : ?>
    <?php echo MessageBox::info(
        dgettext('zoom', 'Es wurden keine Zoom-Meetings für diese Veranstaltung gefunden.')) ?>
<?php else : ?>
    <table class="default">
        <caption>
            <?php echo sprintf(dgettext('zoom', 'Zoom-Meetings zu dieser Veranstaltung')) ?>
        </caption>
        <colgroup>
            <col>
            <?php if ($permission) : ?>
                <col width="40">
            <?php endif ?>
        </colgroup>
        <thead>
            <tr>
                <th>
                    <?php echo dgettext('zoom', 'Titel') ?>
                </th>
                <?php if ($permission) : ?>
                    <th>
                        <?php echo dgettext('zoom', 'Aktionen') ?>
                    </th>
                <?php endif ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meetings as $meeting) : ?>
                <tr>
                    <td>
                        <?php echo htmlReady($meeting->zoom_settings->topic) ?>
                        <br>
                        <?php echo sprintf(dgettext('zoom', 'Nächster Termin: %s'),
                            htmlReady($meeting->zoom_settings->start_time->format('d.m.Y H:i'))) ?>
                        <div class="join-meeting">
                            <a href="<?php echo $controller->link_for('meetings/join', $meeting->id) ?>" target="_blank">
                                <?php echo Icon::create('door-enter')->asImg(48) ?>
                                <?php echo dgettext('zoom', 'Teilnehmen') ?>
                            </a>
                        </div>
                    </td>
                    <?php if ($permission) : ?>
                        <td>
                            <?php if ($meeting->isHost()) : ?>
                                <a href="<?php echo $controller->link_for('meetings/edit', $meeting->id) ?>"
                                   data-dialog="size=auto">
                                    <?php echo Icon::create('edit') ?>
                                </a>
                                <a href="<?php echo $controller->link_for('meetings/ask_delete', $meeting->id) ?>"
                                   data-dialog="size=auto">
                                    <?php echo Icon::create('trash') ?>
                                </a>
                            <?php endif ?>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif;
