<form class="default" action="<?php echo $controller->link_for('meetings/store') ?>" method="post">
    <fieldset>
        <legend>
            <?php echo dgettext('zoom', 'Wie soll das Meeting angelegt werden?') ?>
        </legend>
        <section>
            <input type="radio" name="create_type" value="coursedates" id="create-coursedates"
                <?php echo $meeting->type == 'coursedates' ? 'checked' : '' ?>>
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
            <input type="radio" name="create_type" value="manual" id="create-manual"
                <?php echo $meeting->type == 'manual' ? 'checked' : '' ?>>
            <label for="create-manual" class="undecorated">
                <?php echo dgettext('zoom', 'Meeting mit manuellem Termin') ?>
            </label>
            <div class="info">
                <?php echo dgettext('zoom', 'Die Zeit, zu der das Meeting stattfindet, '.
                    'kann hier angegeben werden und bleibt unverändlich, außer, sie wird manuell bearbeitet. '.
                    'Es kann auch ein wöchentlicher Wiederholungszyklus an mehreren Wochentagen eingestellt werden.') ?>
            </div>
        </section>
    </fieldset>
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
                        <?php echo $meeting->isNew() || $meeting->zoom_settings->type == ZoomAPI::MEETING_SCHEDULED ?
                            'checked' : '' ?>>
                    <label for="once" class="undecorated">
                        <?php echo dgettext('zoom', 'Keine Wiederholung') ?>
                    </label>
                </section>
                <section class="col-3">
                    <input type="radio" name="recurring" value="1" id="weekly"
                        <?php echo $meeting->zoom_settings->type == ZoomAPI::MEETING_RECURRING_FIXED_TIME ?
                            'checked' : '' ?>>
                    <label for="weekly" class="undecorated">
                        <?php echo dgettext('zoom', 'Wöchentlich') ?>
                    </label>
                </section>
            </section>
        </fieldset>
    <?php endif ?>
        <?php if (count($otherLecturers) > 0) : ?>
            <fieldset>
                <legend>
                    <?php echo dgettext('zoom', 'Weitere Personen mit Vollzugriff auf dieses Meeting (Co-Hosts)') ?>
                </legend>
                <section>
                    <?php foreach ($otherLecturers as $lecturer) : ?>
                        <div>
                            <input type="checkbox" name="co_hosts[]" id="co-host-<?php echo $lecturer->user_id ?>"
                                   value="<?php echo htmlReady($lecturer->user_id) ?>"
                                <?php echo $possibleCoHosts[$lecturer->user_id] ? (
                                        in_array($lecturer->user->email,
                                            explode(',', $meeting->zoom_settings->settings->alternative_hosts)) ?
                                            ' checked' : '') :
                                    ' disabled' ?>>
                            <label for="co-host-<?php echo $lecturer->user_id ?>" class="undecorated">
                                <?php if (!$possibleCoHosts[$lecturer->user_id]) : ?>
                                    <span class="not-available">
                                <?php endif ?>
                                <?php echo htmlReady($lecturer->getUserFullname()) ?>
                                <?php if (!$possibleCoHosts[$lecturer->user_id]) : ?>
                                    </span>
                                <?php endif ?>
                            </label>
                        </div>
                    <?php endforeach ?>
                    <?php if ($unavailable > 0) : ?>
                        <?php echo MessageBox::warning(sprintf(dgettext('zoom', 'Für eine(n) oder ' .
                            'mehrere Lehrende dieser Veranstaltung existiert noch keine Kennung in Zoom, daher ' .
                            'können diese Personen momentan nicht als Co-Host eingetragen werden. Bitten Sie die ' .
                            'Personen, sich einmalig unter <a href="%1$s" target="_blank">%1$s</a> einzuloggen, ' .
                            'damit die Kennung angelegt wird. Danach können Sie die Personen als Co-Host eintragen.'),
                            Config::get()->ZOOM_LOGIN_URL)) ?>
                    <?php endif ?>
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
        <?php if (!$meeting->isNew()) : ?>
            <input type="hidden" name="meeting_id" value="<?php echo $meeting->id ?>">
        <?php endif ?>
        <?= CSRFProtection::tokenTag() ?>
        <?= Studip\Button::createAccept(_('Speichern'), 'store') ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->link_for('meetings'),
            ['data-dialog-close' => true]) ?>
    </footer>
</form>
