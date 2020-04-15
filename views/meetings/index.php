<?php if (count($meetings) == 0) : ?>
    <?php echo MessageBox::info(
        dgettext('zoom', 'Es wurden keine Zoom-Meetings für diese Veranstaltung gefunden.')) ?>
<?php else : ?>
    <table class="default">
        <caption>
            <?php echo sprintf(dngettext('zoom', 'Ein Zoom-Meeting zu dieser Veranstaltung',
                '%u Zoom-Meetings zu dieser Veranstaltung', count($meetings)), count($meetings)) ?>
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
                    <td><?php echo htmlReady($meeting->zoom_settings->topic) ?></td>
                    <td>
                        <a href="<?php echo $controller->link_for('meetings/join', $meeting->id)?>" target="_blank">
                            <?php echo Icon::create('link_extern')->asImg(20) ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif;
