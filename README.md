# Invite Registration

Eine Nextcloud-App (für **Nextcloud 32.0.x**), mit der neue Benutzerkonten
**ausschließlich über persönliche Einladungslinks** erstellt werden können.
Es gibt keinen öffentlichen Registrierungsbutton.

- Admin erstellt Einladungslinks mit Gültigkeit und Verwendungsanzahl
- Link wird an eine Person weitergegeben
- Die Person registriert einen **normalen** Nextcloud-Account
- Der Account wird erst nach **E-Mail-Bestätigung** aktiv
- Links laufen automatisch ab oder können jederzeit widerrufen werden
- Optional: neue Benutzer werden automatisch einer **Standardgruppe** zugeordnet
- Deutsch und Englisch

> **Kein Build nötig.** Diese App enthält bereits alles, was der Server braucht.
> Du musst **keine Kommandozeile** benutzen, kein Node, kein npm. Nur hochladen
> und in Nextcloud aktivieren.

---

## Inhaltsverzeichnis

1. [Überblick](#überblick)
2. [Schritt 1: App auf den Server laden (Plesk)](#schritt-1-app-auf-den-server-laden-plesk)
3. [Schritt 2: App in Nextcloud aktivieren](#schritt-2-app-in-nextcloud-aktivieren)
4. [Schritt 3: E-Mail-Server prüfen](#schritt-3-e-mail-server-prüfen)
5. [Schritt 4: Benutzung](#schritt-4-benutzung)
6. [Fehlerbehebung](#fehlerbehebung)

---

## Überblick

Nur drei Schritte:

```text
1. App hochladen        →  nach nextcloud/apps/invite_registration/
2. App aktivieren       →  in Nextcloud unter "Apps"
3. E-Mail-Server prüfen  →  damit die Bestätigungsmails ankommen
```

---

## Schritt 1: App auf den Server laden (Plesk)

Die App muss in das `apps`-Verzeichnis deiner Nextcloud, in einen Unterordner
mit dem exakten Namen **`invite_registration`**:

```text
.../nextcloud/apps/invite_registration/
```

Der Ordnername muss **genau** `invite_registration` heißen, sonst startet die
App nicht.

### Über den Plesk-Dateimanager (empfohlen)

1. Packe auf deinem PC den Ordner `invite_registration` zu einer ZIP-Datei
   (Rechtsklick auf den Ordner → „Senden an" → „ZIP-komprimierter Ordner").
2. Melde dich in **Plesk** an.
3. Gehe zu deiner Domain → **Dateien** (Dateimanager).
4. Navigiere in das Nextcloud-Verzeichnis. Häufig liegt Nextcloud unter
   `httpdocs/` oder `httpdocs/nextcloud/`. Das richtige Verzeichnis erkennst du
   daran, dass darin die Ordner `apps/`, `config/` und `data/` liegen.
5. Öffne den Ordner **`apps`**.
6. Lade die ZIP-Datei per **„Datei hochladen"** hoch.
7. Nach dem Upload: Rechtsklick auf die ZIP → **„Extrahieren" / „Entpacken"**.
8. Prüfen: Es muss `apps/invite_registration/` existieren und **direkt darin**
   die Ordner `appinfo/`, `lib/`, `templates/`, `js/`, `css/` liegen.
   - Falls ein doppelter Ordner entstanden ist
     (`apps/invite_registration/invite_registration/...`), verschiebe den
     **inneren** Ordner eine Ebene nach oben, damit die Struktur stimmt.
9. Lösche danach die ZIP-Datei wieder.

> Tipp: Der Ordner `src/` (falls noch vorhanden) und die Datei `.gitignore`
> werden auf dem Server nicht gebraucht. Sie schaden aber auch nicht. Wichtig
> ist nur, dass `js/`, `css/`, `lib/`, `appinfo/` und `templates/` vorhanden sind.

### Dateirechte (falls die App nicht auftaucht)

Nextcloud/PHP läuft unter einem eigenen Systembenutzer. Wenn du über den
Plesk-Dateimanager als der richtige Benutzer hochlädst, stimmen die Rechte
meist automatisch. Falls die App später nicht erscheint oder Fehler zeigt,
liegt es oft an den Dateirechten – dann hilft dein Hoster oder der
Plesk-Support beim Setzen des korrekten Besitzers.

---

## Schritt 2: App in Nextcloud aktivieren

1. Melde dich in Nextcloud als **Administrator** an.
2. Oben rechts auf dein Profilbild → **Apps**.
3. Links auf **„Deaktivierte Apps"** klicken (oder oben nach
   „Invite Registration" suchen).
4. Bei **Invite Registration** auf **Aktivieren** klicken.

Beim Aktivieren legt Nextcloud automatisch die beiden benötigten
Datenbanktabellen an.

Falls die App nicht in der Liste erscheint, siehe [Fehlerbehebung](#fehlerbehebung).

---

## Schritt 3: E-Mail-Server prüfen

Die App verschickt eine Bestätigungsmail. Dafür muss in Nextcloud ein
E-Mail-Server eingerichtet sein.

1. Nextcloud → **Einstellungen → Verwaltung → Grundeinstellungen**.
2. Abschnitt **E-Mail-Server** ausfüllen (SMTP-Daten deines Providers).
3. Mit **„E-Mail senden"** eine Testmail verschicken und prüfen, ob sie ankommt.

Ist kein Mailserver konfiguriert, schlägt die Registrierung mit einer klaren
Fehlermeldung fehl – und es wird **kein** halbfertiger Account angelegt.

---

## Schritt 4: Benutzung

### Einladungslink erstellen (als Admin)

1. Nextcloud → **Einstellungen → Verwaltung → Invite Registration**.
2. **Gültigkeit** wählen (z. B. 24 Stunden) und **Anzahl der Verwendungen**
   festlegen (z. B. 1 für einen Einmal-Link).
3. Auf **„Einladungslink erstellen"** klicken.
4. Der fertige Link wird angezeigt – mit **Kopieren**-Button. Diesen Link gibst
   du der Person weiter.

### Optional: Standardgruppe

Im Abschnitt **Standardgruppe** kannst du eine Gruppe wählen. Neue Benutzer, die
über eine Einladung erstellt werden, landen automatisch in dieser Gruppe.

### Aus Sicht der eingeladenen Person

1. Person öffnet den Link → Registrierungsformular (Benutzername, E-Mail, Passwort).
2. Nach dem Absenden kommt eine **Bestätigungsmail**.
3. Sie klickt den Link in der Mail → Account wird aktiviert.
4. Danach normale Anmeldung an Nextcloud möglich.

### Links verwalten

In der Tabelle **Einladungslinks** siehst du alle Links mit Status
(Aktiv / Abgelaufen / Aufgebraucht / Widerrufen), Nutzung und den Aktionen
**Widerrufen** und **Löschen**. Ein widerrufener Link funktioniert sofort nicht mehr.

---

## Fehlerbehebung

**Die App erscheint nicht in der App-Liste.**
- Ordnername prüfen: Muss exakt `apps/invite_registration/` sein (nicht doppelt
  verschachtelt).
- Prüfen, dass die Ordner `js/`, `css/`, `lib/`, `appinfo/`, `templates/` direkt
  in `invite_registration/` liegen.
- Dateirechte prüfen (siehe Schritt 1).
- Nextcloud-Version prüfen: Die App verlangt Nextcloud 32.

**Die Admin-Seite „Invite Registration" ist leer.**
- Prüfen, dass die Datei `js/invite_registration-admin.js` vorhanden ist.
- Browser-Cache leeren und die Seite mit Strg+F5 neu laden.

**„Die Bestätigungs-E-Mail konnte nicht gesendet werden."**
- E-Mail-Server in Nextcloud ist nicht (korrekt) konfiguriert. Siehe Schritt 3.

**„Einladung ungültig / abgelaufen / bereits verwendet."**
- Der Link ist abgelaufen, aufgebraucht oder widerrufen. Erstelle einen neuen.

**Benutzername wird abgelehnt.**
- Erlaubt sind 3–32 Zeichen, nur Buchstaben, Ziffern sowie `.`, `_`, `-`.

**Änderungen sind nach Upload nicht sichtbar.**
- Seite mit Strg+F5 neu laden (Browser-Cache).

---

## Technische Details (für Interessierte)

- App-ID: `invite_registration`, Namespace `OCA\InviteRegistration`
- Frontend: reines JavaScript in `js/invite_registration-admin.js` (kein Build)
- Tabellen: `oc_invite_reg_invites`, `oc_invite_reg_verify`
- Tokens: kryptografisch zufällig (`ISecureRandom`, 32 Zeichen)
- Passwörter werden **nicht** von der App gespeichert, sondern direkt an die
  Nextcloud-Benutzerverwaltung übergeben
- Sicherheit: CSRF-Schutz, Brute-Force-Drosselung, atomares Verbrauchen der
  Links (keine Race Conditions), generische Fehlermeldungen (keine
  Token-Enumeration)
- Lizenz: AGPL-3.0-or-later
