<?php if (count($host) < 1 && count($participant) < 1) : ?>
    <?php echo MessageBox::info(
        dgettext('zoom', 'Es wurden keine Meetings in Ihren Veranstaltungen gefunden.')) ?>
<?php endif ?>
<?php if (count($host) > 0) : ?>
    <?php echo $this->render_partial('my_meetings/_group', [
        'title' => dgettext('zoom', 'Meine Zoom-Meetings (hier bin ich Host oder Co-Host)'),
        'meetings' => $host,
        'admin' => true
    ]) ?>
<?php endif ?>
<?php if (count($participant) > 0) : ?>
    <?php echo $this->render_partial('my_meetings/_group', [
        'title' => dgettext('zoom', 'Zoom-Meetings in meinen Veranstaltungen (hier kann ich teilnehmen)'),
        'meetings' => $participant,
        'admin' => false
    ]) ?>
<?php endif ?>
