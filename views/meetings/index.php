<?php if ($need_license) : ?>
    <?php echo MessageBox::warning(sprintf(dgettext('zoom',
        'Ihre Veranstaltung hat mehr als %1$u Teilnehmende, was nicht mehr mit regulären ' .
        'Zoom-Meetings abgedeckt werden kann. Um eine Freischaltung zur Erstellung größerer Webinare zu bekommen, ' .
        'wenden Sie sich bitte an den <a href="mailto:%2$s">ZIM-Support</a>.'),
        ZoomAPI::MAX_MEETING_MEMBERS, $GLOBALS['UNI_CONTACT'])) ?>
<?php endif ?>
<?php if ($need_larger_license) : ?>
    <?php echo MessageBox::warning(sprintf(dgettext('zoom',
        'Ihre Veranstaltung hat %1$u Teilnehmende, was nicht mit Ihrer freigeschalteten Webinarlizenz ' .
        '(bis %2$u Personen) abgedeckt werden kann. Um eine Freischaltung zur Erstellung größerer Webinare zu ' .
        'bekommen, wenden Sie sich bitte an den <a href="mailto:%3$s">ZIM-Support</a>.'),
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
            <col width="40">
        </colgroup>
        <thead>
            <tr>
                <th>
                    <?php echo dgettext('zoom', 'Titel') ?>
                </th>
                <th>
                    <?php echo dgettext('zoom', 'Aktionen') ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meetings as $meeting) : ?>
                <tr>
                    <td>
                        <?php echo htmlReady($meeting->zoom_settings->topic) ?>
                        <br>
                        <?php echo sprintf(dgettext('zoom', 'Nächster Termin: %s'), htmlReady($meeting->zoom_settings->start_time->format('d.m.Y H:i'))) ?>
                        <div class="join-meeting">
                            <a href="<?php echo $controller->link_for('meetings/join', $meeting->id) ?>" target="_blank">
                                <?php echo Icon::create('door-enter')->asImg(48) ?>
                                <?php echo dgettext('zoom', 'Teilnehmen') ?>
                            </a>
                        </div>
                    </td>
                    <td>
                        <a href="<?php echo $controller->link_for('meetings/edit', $meeting->id) ?>" data-dialog="size=auto">
                            <?php echo Icon::create('edit') ?>
                        </a>
                        <a href="<?php echo $controller->link_for('meetings/delete', $meeting->id) ?>"
                           data-confirm="<?php echo dgettext('zoom', 'Soll das Meeting wirklich gelöscht werden?') ?>">
                            <?php echo Icon::create('trash') ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif;
