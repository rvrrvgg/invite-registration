# Veröffentlichung im Nextcloud App Store

Diese Anleitung führt dich durch die Veröffentlichung von **Invite Registration**
im offiziellen Nextcloud App Store (apps.nextcloud.com) — **ohne Kommandozeile**.
Alle rechenintensiven Schritte (Paketieren, Signieren, Hochladen) übernimmt
GitHub automatisch über die mitgelieferte Workflow-Datei.

> Der einzige knifflige Teil ist das **Signaturzertifikat**. Dafür müssen einmalig
> ein privater Schlüssel und eine Zertifikatsanfrage (CSR) erzeugt werden. Das
> geht normalerweise per OpenSSL-Befehl. Da du keine Kommandozeile nutzen willst,
> beschreibt Abschnitt 3 einen browserbasierten Weg über GitHub.

---

## Überblick der Schritte

```text
1. GitHub-Konto + Repository anlegen
2. App-Code ins Repository hochladen
3. Signaturschlüssel + CSR erzeugen (über GitHub, kein Terminal)
4. Zertifikat bei Nextcloud beantragen
5. App-ID bei Nextcloud registrieren
6. Secrets im GitHub-Repo hinterlegen
7. Version-Tag setzen -> Veröffentlichung läuft automatisch
```

---

## 1. GitHub-Konto und Repository

1. Falls noch nicht vorhanden: kostenloses Konto auf <https://github.com> anlegen.
2. Oben rechts auf **+** -> **New repository**.
3. Repository-Name: **`invite_registration`** (genau so).
4. Sichtbarkeit: **Public** (der App Store verlangt öffentlichen Code).
5. **Create repository**.

Dein Repo-Pfad ist: `github.com/rvrrvgg/invite_registration`.

---

## 2. App-Code hochladen

Am einfachsten über die GitHub-Weboberfläche:

1. Auf der leeren Repo-Seite: **uploading an existing file** anklicken.
2. Den **gesamten Inhalt** des App-Ordners `invite_registration` per Drag & Drop
   hochladen (alle Ordner: `appinfo`, `lib`, `templates`, `js`, `css`, `l10n`,
   `img`, `.github`, sowie die Dateien `README.md`, `PUBLISHING.md`, `.gitignore`).
3. Unten **Commit changes** klicken.

> Wichtig: Die Struktur muss so sein, dass `appinfo/info.xml` direkt im
> Repo-Stammverzeichnis liegt — nicht in einem Unterordner `invite_registration/`.

---

## 3. Signaturschlüssel + CSR erzeugen (ohne Terminal)

Nextcloud verlangt einen **privaten Schlüssel** und einen **CSR** (Certificate
Signing Request). Da du kein Terminal nutzt, nutzen wir eine GitHub Action, die
das für dich erzeugt und als herunterladbare Datei bereitstellt.

1. Lege in deinem Repo die Datei `.github/workflows/make-cert.yml` an
   (über **Add file -> Create new file** in der GitHub-Weboberfläche) und füge
   den Inhalt aus dem Abschnitt **„Anhang A"** unten ein. **Commit changes.**
2. Gehe im Repo auf den Reiter **Actions**.
3. Wähle links **„Create signing key + CSR"** und klicke **Run workflow**.
4. Wenn der Lauf fertig ist (grüner Haken), öffne ihn und lade unten unter
   **Artifacts** das Paket **`signing-material`** herunter. Es enthält:
   - `invite_registration.key` — dein **privater Schlüssel** (geheim halten!)
   - `invite_registration.csr` — die Zertifikatsanfrage (die reichst du ein)
5. **Wichtig:** Lösche die Datei `.github/workflows/make-cert.yml` danach wieder
   aus dem Repo, damit der Schlüssel nicht erneut erzeugt/überschrieben wird.

> Bewahre `invite_registration.key` sicher auf. Wer ihn hat, kann Releases in
> deinem Namen signieren.

---

## 4. Zertifikat bei Nextcloud beantragen

1. Öffne den Inhalt von `invite_registration.csr` (mit einem Texteditor) und
   kopiere den **gesamten** Text (inklusive der `-----BEGIN CERTIFICATE REQUEST-----`
   und `-----END ...-----` Zeilen).
2. Gehe zu <https://github.com/nextcloud/app-certificate-requests>.
3. Folge der dortigen README: Du legst per Pull Request eine Datei
   `invite_registration/invite_registration.csr` mit deinem CSR-Inhalt an.
   (Auch das geht komplett über die GitHub-Weboberfläche: Fork -> Datei anlegen
   -> Pull Request öffnen.)
4. Das Nextcloud-Team prüft und stellt dir das **Zertifikat**
   (`invite_registration.crt`) aus. Du bekommst es über den Pull Request.

---

## 5. App-ID bei Nextcloud registrieren

1. Konto anlegen / anmelden auf <https://apps.nextcloud.com>.
2. Profil -> **Account settings** -> **API token**: den Token kopieren
   (brauchst du in Schritt 6).
3. Die App-ID `invite_registration` wird beim ersten Upload automatisch
   registriert, sofern sie noch frei ist und dein Zertifikat dazu passt.

---

## 6. Secrets im GitHub-Repo hinterlegen

Im Repo: **Settings -> Secrets and variables -> Actions -> New repository secret**.
Lege drei Secrets an:

| Name | Inhalt |
|---|---|
| `NEXTCLOUD_SIGNING_KEY`   | kompletter Inhalt von `invite_registration.key` |
| `NEXTCLOUD_SIGNING_CERT`  | kompletter Inhalt von `invite_registration.crt` (aus Schritt 4) |
| `NEXTCLOUD_APPSTORE_TOKEN`| dein API-Token aus Schritt 5 |

---

## 7. Veröffentlichen

Jetzt setzt du nur noch ein Versions-Tag, der Rest läuft automatisch:

1. Im Repo: **Releases** (rechte Seite) -> **Draft a new release**
   (oder **Create a new release**).
2. Bei **Choose a tag** ein neues Tag eingeben: **`v1.1.1`**
   (muss zur `<version>` in `appinfo/info.xml` passen).
3. **Publish release**.

Das Tag löst die Workflow-Datei `.github/workflows/release.yml` aus. Sie
paketiert, signiert und meldet die App bei apps.nextcloud.com an. Unter
**Actions** siehst du den Fortschritt. Bei Erfolg erscheint die App im Store
(nach einer eventuellen Freigabeprüfung).

Für jede neue Version: `<version>` in `appinfo/info.xml` erhöhen, hochladen,
neues Tag `vX.Y.Z` setzen.

---

## Anhang A — Inhalt von `.github/workflows/make-cert.yml`

```yaml
name: Create signing key + CSR

on:
  workflow_dispatch:

jobs:
  make-cert:
    runs-on: ubuntu-latest
    steps:
      - name: Generate key and CSR
        run: |
          set -e
          APP_ID="invite_registration"
          mkdir -p out
          openssl req -nodes -newkey rsa:4096 -keyout "out/$APP_ID.key" \
            -out "out/$APP_ID.csr" -subj "/CN=$APP_ID"
          echo "Generated key and CSR for $APP_ID"
      - name: Upload as downloadable artifact
        uses: actions/upload-artifact@v4
        with:
          name: signing-material
          path: out/
          retention-days: 1
```

> Dieser Hilfs-Workflow läuft nur, wenn du ihn manuell startest
> (`workflow_dispatch`). Nach dem Herunterladen der Artefakte wieder löschen.
