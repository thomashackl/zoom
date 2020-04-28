# Zoom für Stud.IP

## Was macht dieses Plugin?
Mit diesem Plugin können Sie Ihre Zoom-Meetings direkt von Ihren
Stud.IP-Veranstaltungen aus anlegen und verwalten. Schicken Sie nie
wieder kryptische Links oder Passwörter per Mail an potenzielle
Teilnehmende oder posten Sie diese Daten in Ankündigungen Ihrer
Veranstaltungen. Einfach das Meeting in Stud.IP anlegen, dort sehen
Sie dann auch den entsprechenden Button, den man einfach nur klicken
muss, um am Meeting teilzunehmen.

## Was muss ich tun, um das Plugin nutzen zu können?
### Anlegen einer Zoom-App
Loggen Sie sich mit Ihrem Zoom-Account (der die Rechte haben muss,
um Apps anzulegen) unter https://marketplace.zoom.us ein und klicken
Sie rechts oben auf "Develop" -> "Build App". Danach wählen Sie als
App-Typ "JWT". Die App sollte am besten eine "Account Level"-App
sein, damit alle Aktionen damit durchführbar sind. Veröffentlichen
müssen Sie sie nicht.

### Installation in Stud.IP
Ist die App aktiviert, finden Sie im Bereich "App Credentials" Ihren
API Key und das das API Secret. Diese beiden Werte fügt Ihr
Stud.IP-Root nach Installation des Plugins in Stud.IP einfach ein:
Admin -> System -> Konfiguration -> Abschnitt "zoom". Dort gibt es
entsprechende Felder für Key und Secret. Weiter tragen sie bitte noch
die Login-URL für Zoom ein. Wenn Sie z.B. SSO für den Login verwenden,
bekommen Sie eine eigene URL zum Zoom-Login
(https://ihre-einrichtung.zoom.us).

Am besten überprüfen Sie noch, ob das Plugin auch in den verschiedenen
Veranstaltungskategorien verfügbar und/oder aktiviert ist.

### FERTIG!

Jetzt können Sie das Plugin in Veranstaltungen aktivieren und Meetings
anlegen oder bestehende aus Zoom importieren und mit Ihrer Veranstaltung
verknüpfen.

Weiter haben Sie eine Übersicht neben "Meine Veranstaltungen":
"Meine Zoom-Meetings" listet alle Meetings in Ihren Veranstaltungen
auf, filterbar nach Semester.

## Ich brauche Hilfe - was kann oder soll ich alles einstellen?
Schauen sie sich gerne den Screencast unter https://vimeo.com/412311523/eae4c6fbcf
an, der die Nutzung und die einzelnen Optionen erklärt.
