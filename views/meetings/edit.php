<form class="default" action="<?php echo $controller->link_for('meetings/store') ?>" method="post">
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Grunddaten') ?>
        </legend>
        <section>
            <label for="topic">
                <?php echo dgettext('zoom', 'Titel') ?>
            </label>
            <input type="text" name="topic" id="topic" size="75" maxlength="255"
                   value="<?php echo htmlReady($meeting->zoom_settings['topic']) ?>">
        </section>
        <section class="col-4">
            <label for="start-time">
                <?php echo dgettext('zoom', 'Beginn') ?>
            </label>
            <input type="text" name="start_time" id="start-time" data-datetime-picker
                   value="<?php echo htmlReady($meeting->zoom_settings['start_time']) ?>">
        </section>
        <section class="col-2">
            <label for="duration">
                <?php echo dgettext('zoom', 'Dauer (in Minuten)') ?>
            </label>
            <input type="number" name="duration" id="duration" min="1"
                   max="<?php echo $user->type === ZoomAPI::$LICENSE_BASIC ? '40' : '720' ?>"
                   value="<?php echo htmlReady($meeting->zoom_settings['duration']) ?>">
        </section>
        <section>
            <label for="password">
                <?php echo dgettext('zoom', 'Passwort') ?>
            </label>
            <input type="text" name="password" id="password" size="75" maxlength="10"
                   value="<?php echo htmlReady($meeting->zoom_settings['password']) ?>">
        </section>
        <section>
            <label for="agenda">
                <?php echo dgettext('zoom', 'Beschreibung') ?>
            </label>
            <textarea name="agenda" id="agenda" cols="75" rows="3"
                      maxlength="2000"><?php echo htmlReady($meeting->zoom_settings['agenda']) ?></textarea>
        </section>
    </fieldset>
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Zoom-Einstellungen') ?>
        </legend>
        <section class="col-3">
            <input type="checkbox" name="settings[host_video]" id="host-video" value="1"
                <?php echo $meeting->zoom_settings['host_video'] ? ' checked' : '' ?>>
            <label for="host-video" class="undecorated">
                <?php echo dgettext('zoom', 'Video starten, wenn ein Host den Raum betritt') ?>
            </label>
        </section>
        <section class="col-3">
            <input type="checkbox" name="settings[participant_video]" id="participant-video" value="1"
                <?php echo $meeting->zoom_settings['participant_video'] ? ' checked' : '' ?>>
            <label for="participant-video" class="undecorated">
                <?php echo dgettext('zoom', 'Video starten, wenn ein(e) Teilnehmer(in) den Raum betritt') ?>
            </label>
        </section>
        <section class="col-3">
            <input type="checkbox" name="settings[join_before_host]" id="join-before-host" value="1"
                <?php echo $meeting->zoom_settings['join_before_host'] ? ' checked' : '' ?>>
            <label for="join-before-host" class="undecorated">
                <?php echo dgettext('zoom', 'Teilnehmende dürfen den Raum vor dem Host betreten') ?>
            </label>
        </section>
        <section class="col-3">
            <input type="checkbox" name="settings[mute_upon_entry]" id="mute-upon-entry" value="1"
                <?php echo $meeting->zoom_settings['mute_upon_entry'] ? ' checked' : '' ?>>
            <label for="mute-upon-entry" class="undecorated">
                <?php echo dgettext('zoom', 'Teilnehmende beim Betreten automatisch stumm schalten') ?>
            </label>
        </section>
    </fieldset>
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Sichtbarkeit in Stud.IP') ?>
        </legend>
        <section class="col-3">
            <label for="visible-from" class="undecorated">
                <?php echo dgettext('zoom', 'von') ?>
            </label>
            <input type="text" name="visible_from" id="visible-from" maxlength="15"
                   placeholder="<?php echo dgettext('zoom', 'unbegrenzt') ?>"
                   value="<?php echo $meeting->visible_from ? $meeting->visible_from->format('d.m.Y H:i') : '' ?>"
                   data-datetime-picker>
        </section>
        <section class="col-3">
            <label for="visible-until" class="undecorated">
                <?php echo dgettext('zoom', 'bis') ?>
            </label>
            <input type="text" name="visible_until" id="visible-until" maxlength="15"
                   placeholder="<?php echo dgettext('zoom', 'unbegrenzt') ?>"
                   value="<?php echo $meeting->visible_until ? $meeting->visible_until->format('d.m.Y H:i') : '' ?>"
                   data-datetime-picker='{">=":"#visible-from"}'>
        </section>
    </fieldset>
    <footer data-dialog-button>
        <?= CSRFProtection::tokenTag() ?>
        <?= Studip\Button::createAccept(_('Speichern'), 'store') ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->link_for('meetings'),
            ['data-dialog-close' => true]) ?>
    </footer>
</form>
