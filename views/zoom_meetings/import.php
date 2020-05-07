<form class="default" action="<?php echo $controller->link_for('zoom_meetings/do_import') ?>" method="post">
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Importieren sie ein bestehendes Meeting aus Zoom') ?>
        </legend>
        <section>
            <label for="zoom-id">
                <?php echo dgettext('zoom', 'Zoom-Meeting-ID') ?>
            </label>
            <input type="text" name="zoom_id" id="zoom-id" size="75" value="">
            <br>
            <div>
                <?php echo dgettext('zoom', 'Hier können Sie ein bereits in Zoom angelegtes '.
                    'Meeting Ihrer Stud.IP-Veranstaltung zuordnen, indem Sie die Zoom-Meeting-ID eingeben.<br>'.
                    'Diese ID finden Sie in der Liste Ihrer Meetings in Zoom.') ?>
            </div>
        </section>
    </fieldset>
    <footer data-dialog-button>
        <?= CSRFProtection::tokenTag() ?>
        <?= Studip\Button::createAccept(_('Speichern'), 'store') ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->link_for('zoom_meetings'),
            ['data-dialog-close' => true]) ?>
    </footer>
</form>
