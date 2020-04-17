<form class="default" action="<?php echo $controller->link_for('meetings/store') ?>" method="post">
    <?php if ($meeting->isNew()) : ?>
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Wie soll das Meeting angelegt werden?') ?>
        </legend>
        <section>
            <input type="radio" name="create_type" value="coursedates" id="create-coursedates" checked>
            <label for="create-coursedates" class="undecorated">
                <?php echo dgettext('zoom', 'Meeting mit Veranstaltungsterminen verknüpfen') ?>
            </label>
            <div class="info">
                <?php echo dgettext('zoom', 'Das angelegte Meeting wird automatisch seine ' .
                    'Zeiten aktualisieren und immer den nächsten verfügbaren Veranstaltungstermin als Zeit ' .
                    'gesetzt haben.') ?>
            </div>
        </section>
        <section>
            <input type="radio" name="create_type" value="manual" id="create-manual">
            <label for="create-manual" class="undecorated">
                <?php echo dgettext('zoom', 'Meeting mit manuellem Termin') ?>
            </label>
            <div class="info">
                <?php echo dgettext('zoom', 'Die Zeit, zu der das Meeting stattfindet, '.
                    'kann hier angegeben werden und bleibt unverändlich, außer, sie wird manuell bearbeitet.') ?>
            </div>
        </section>
    </fieldset>
    <?php endif ?>
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Grunddaten') ?>
        </legend>
        <section>
            <label for="topic">
                <?php echo dgettext('zoom', 'Titel') ?>
            </label>
            <input type="text" name="topic" id="topic" size="75" maxlength="255"
                   value="<?php echo htmlReady($meeting->zoom_settings->topic) ?>">
        </section>
        <section>
            <label for="agenda">
                <?php echo dgettext('zoom', 'Beschreibung') ?>
            </label>
            <textarea name="agenda" id="agenda" cols="75" rows="3"
                      maxlength="2000"><?php echo htmlReady($meeting->zoom_settings->agenda) ?></textarea>
        </section>
        <section>
            <label for="password">
                <?php echo dgettext('zoom', 'Passwort') ?>
            </label>
            <input type="text" name="password" id="password" size="75" maxlength="10"
                   value="<?php echo htmlReady($meeting->zoom_settings->password) ?>">
        </section>
    </fieldset>
    <?php if ($meeting->isNew() || $meeting->type == 'manual') : ?>
        <fieldset class="manual-time">
            <legend>
                <?php echo dgettext('zoom', 'Wann findet das Meeting statt?') ?>
            </legend>
            <section class="col-4">
                <label for="start-time">
                    <?php echo dgettext('zoom', 'Beginn') ?>
                </label>
                <input type="text" name="start_time" id="start-time" data-datetime-picker
                       value="<?php echo htmlReady($meeting->zoom_settings->start_time->format('d.m.Y H:i')) ?>">
            </section>
            <section class="col-2">
                <label for="duration">
                    <?php echo dgettext('zoom', 'Dauer (in Minuten)') ?>
                </label>
                <input type="number" name="duration" id="duration" min="1"
                       max="<?php echo $user->type === ZoomAPI::LICENSE_BASIC ? '40' : '720' ?>"
                       value="<?php echo htmlReady($meeting->zoom_settings->duration) ?>">
            </section>
            <section>
                <label for="once">
                    <?php echo dgettext('zoom', 'Findet das Meeting wiederholt statt?') ?>
                </label>
                <section class="col-3">
                    <input type="radio" name="recurring" value="0" id="once"
                        <?php echo $meeting->zoom_settings->type == ZoomAPI::MEETING_SCHEDULED ? 'checked' : '' ?>>
                    <label for="once" class="undecorated">
                        <?php echo dgettext('zoom', 'Keine Wiederholung') ?>
                    </label>
                </section>
                <section class="col-3">
                    <input type="radio" name="recurring" value="1" id="recurring"
                        <?php echo $meeting->zoom_settings->type == ZoomAPI::MEETING_RECURRING_FIXED_TIME ? 'checked' : '' ?>>
                    <label for="recurring" class="undecorated">
                        <?php echo dgettext('zoom', 'Wöchentlich') ?>
                    </label>
                </section>
            </section>
        </fieldset>
    <?php endif ?>
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Zoom-Einstellungen') ?>
        </legend>
        <section class="col-3">
            <input type="checkbox" name="settings[host_video]" id="host-video" value="1"
                <?php echo $meeting->zoom_settings->settings->host_video ? ' checked' : '' ?>>
            <label for="host-video" class="undecorated">
                <?php echo dgettext('zoom', 'Video starten, wenn ein Host den Raum betritt') ?>
            </label>
        </section>
        <section class="col-3">
            <input type="checkbox" name="settings[participant_video]" id="participant-video" value="1"
                <?php echo $meeting->zoom_settings->settings->participant_video ? ' checked' : '' ?>>
            <label for="participant-video" class="undecorated">
                <?php echo dgettext('zoom', 'Video starten, wenn ein(e) Teilnehmer(in) den Raum betritt') ?>
            </label>
        </section>
        <section class="col-3">
            <input type="checkbox" name="settings[join_before_host]" id="join-before-host" value="1"
                <?php echo $meeting->zoom_settings->settings->join_before_host ? ' checked' : '' ?>>
            <label for="join-before-host" class="undecorated">
                <?php echo dgettext('zoom', 'Teilnehmende dürfen den Raum vor dem Host betreten') ?>
            </label>
        </section>
        <section class="col-3">
            <input type="checkbox" name="settings[mute_upon_entry]" id="mute-upon-entry" value="1"
                <?php echo $meeting->zoom_settings->settings->mute_upon_entry ? ' checked' : '' ?>>
            <label for="mute-upon-entry" class="undecorated">
                <?php echo dgettext('zoom', 'Teilnehmende beim Betreten automatisch stumm schalten') ?>
            </label>
        </section>
    </fieldset>
    <footer data-dialog-button>
        <?= CSRFProtection::tokenTag() ?>
        <?= Studip\Button::createAccept(_('Speichern'), 'store') ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->link_for('meetings'),
            ['data-dialog-close' => true]) ?>
    </footer>
</form>
