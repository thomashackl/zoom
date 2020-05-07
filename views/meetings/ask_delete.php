<form class="default" action="<?php echo $controller->link_for('meetings/delete', $id) ?>" method="post">
    <?php echo MessageBox::warning(dgettext('zoom', 'Soll das Meeting wirklich gelöscht werden?')) ?>
    <section>
        <input type="checkbox" name="only_studip" id="only-studip" value="1">
        <label for="only-studip" class="undecorated">
            <?php echo dgettext('zoom', 'Meeting nicht in Zoom, sondern nur in Stud.IP löschen') ?>
        </label>
    </section>
    <?php if ($my) : ?>
        <input type="hidden" name="my" value="1">
    <?php endif ?>
    <footer data-dialog-button>
        <?= CSRFProtection::tokenTag() ?>
        <?= Studip\Button::createAccept(_('Löschen'), 'store') ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->link_for('meetings'),
            ['data-dialog-close' => true]) ?>
    </footer>
</form>
