# Nova Nachfrage

[![Spendier mir einen Kaffee](https://img.shields.io/badge/☕_Spendier_mir_einen_Kaffee-PayPal-0070ba?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/DrdotHouse)

*[English version](README.en.md)*

Shopware-6.7-Plugin für die **Nachfrage nach dem Versand**: Einige Tage, nachdem eine Bestellung
verschickt wurde, bekommt der Kunde **eine** E-Mail mit der Bitte, die gekauften Artikel zu bewerten,
optional einem Link zur Google-Bewertung und Hinweisen auf **eigene ähnliche Produkte**. Widersprüche
werden dauerhaft gespeichert (nur als Hash), damit an diese Adresse nie wieder eine solche Mail geht –
auch nicht nach einer neuen Bestellung oder als Gast.

> **Rechtlicher Hinweis:** Das Plugin ist für Bestandskundenwerbung ohne Einwilligung nach § 7 Abs. 3
> UWG gebaut (Adresse aus dem Verkauf, nur eigene ähnliche Waren, kein Widerspruch, Hinweis bei
> Erhebung und in jeder Mail). Ob Ihre Texte, Ihr Sortiment und Ihr Ablauf diese Voraussetzungen erfüllen,
> kann das Plugin nicht beurteilen. **Bitte vor dem Einsatz rechtlich prüfen lassen.** Das Plugin und
> diese Beschreibung sind keine Rechtsberatung.

## Funktionen

- **Eine Mail je Bestellung**, X Tage (Standard 10) nachdem **alle Lieferungen** der Bestellung auf
  „Versandt“ stehen. Versandprotokoll in einer eigenen Tabelle – keine Bestellung wird doppelt angeschrieben.
- **Inhalt:** gekaufte Artikel mit Bild, Link zur Produktseite und Knopf **„Jetzt bewerten“** (öffnet direkt
  das Bewertungsformular), optional ein Block **Google-Bewertung**, danach **ähnliche Produkte**
  (Cross-Sellings und Bestseller mit gemeinsamer spezifischer Kategorie, gewichtet), ein **Zusatztext je
  Verkaufskanal** und im Fuß der **Widerspruchshinweis mit Abmeldelink**.
- **Eigener Mail-Vorlagentyp** „Nova Nachfrage“ (`nova_nachfrage`) mit Standardvorlage de/en (HTML und
  Text), bearbeitbar unter *Einstellungen → E-Mail-Vorlagen*. Versand über Shopwares Mailversand mit dem
  Absender des Verkaufskanals.
- **Widerspruch:** Abmeldelink in jeder Mail (HMAC-signiert), Bestätigungsseite im Shop (Abmeldung erst
  per Knopf/POST), zusätzlich die Header `List-Unsubscribe` und `List-Unsubscribe-Post` (One-Click-Abmeldung
  in Gmail, Apple Mail usw.). Abmeldungen aus Shopwares Newsletter werden auf Wunsch ebenfalls beachtet.
- **Hinweis bei Erhebung:** kurzer Text unter den E-Mail-/Passwortfeldern (Registrierung und Gastbestellung),
  auf der Bestellbestätigungsseite unter den AGB-Hinweisen und als Variable für die Bestellbestätigungs-Mail.
- **Nur Bestellungen mit Hinweis:** Stichtag „Nur Bestellungen ab“ (leer = Installationsdatum) und standardmäßig
  nur Bestellungen aus dem Shop – im Admin angelegte Bestellungen (z. B. telefonisch) bekommen keine Mail.
- **Schutzregeln:** keine Mail bei stornierten Bestellungen, erstatteten/stornierten Zahlungen,
  zurückgesendeten Lieferungen und Bestellungen ohne Artikel; Mindestabstand je Kunde (Standard 30 Tage);
  Bestellungen, deren Versand länger als 60 Tage zurückliegt, werden nie nachgeholt.
- **Testmodus:** alle Mails an eine Testadresse statt an Kunden.
- **Übersicht in der Administration** unter *Einstellungen → Erweiterungen → Nova Nachfrage*: Kennzahlen,
  demnächst fällige und zuletzt verarbeitete Bestellungen, Widersprüche (nur gekürzter Hash) und
  manuelles Eintragen eines Widerspruchs.
- **Konsole:** `nova-nachfrage:send` (mit `--dry-run`) und `nova-nachfrage:optouts`.

## Installation

**Über den Nova Updater:** *Erweiterungen → Meine Erweiterungen → Plugin-Katalog* → „Nova Nachfrage“ →
*Installieren*. Danach aktivieren.

**Per ZIP:** Das ZIP `NovaNachfrage-<Version>.zip` aus den
[Releases](https://github.com/DrdotHouse2106/SW6-Nova-Nachfrage/releases) unter *Erweiterungen → Meine
Erweiterungen → Erweiterung hochladen* hochladen, installieren und aktivieren. Danach die Administration
neu laden (Strg+F5) und das Theme kompilieren lassen (passiert beim Aktivieren automatisch).

Nach der Installation ist **nichts eingeschaltet** – es geht keine Mail, bis Sie das Plugin je
Verkaufskanal aktivieren.

## Einrichten – Schritt für Schritt

1. **Geplante Aufgaben** müssen laufen (Cron für `bin/console scheduled-task:run` und
   `messenger:consume`, oder der Admin-Worker). Die Aufgabe `nova_nachfrage.send` läuft stündlich.
2. **Mail-Vorlage prüfen:** *Einstellungen → E-Mail-Vorlagen* → Typ „Nova Nachfrage“. Texte nach Wunsch
   anpassen – den **Fuß mit dem Widerspruchshinweis und `{{ unsubscribeUrl }}` stehen lassen**.
3. **Plugin konfigurieren** (*Erweiterungen → Meine Erweiterungen → Nova Nachfrage → Konfigurieren*), oben
   den Verkaufskanal wählen. Zuerst **Testmodus** an und eine **Testadresse** eintragen, dann **Aktiv**.
4. **Probelauf:** `bin/console nova-nachfrage:send --dry-run --pending` zeigt, welche Bestellung wann welche
   Mail bekäme (ohne zu senden oder zu speichern). `bin/console nova-nachfrage:send` sendet sofort – im
   Testmodus an die Testadresse.
5. **Bestellbestätigung ergänzen** (siehe unten), damit der Hinweis schon bei der Erhebung der Adresse
   auch in der Mail steht.
6. **Datenschutzerklärung ergänzen** (mit Nova Rechtstexte ab 1.8.7: Baustein `bestandskundenwerbung`
   wird automatisch eingesetzt, sobald Nova Nachfrage installiert und aktiv ist).
7. **Testmodus aus.** Bestellungen, die im Testmodus an die Testadresse gingen, bekommen danach ihre
   echte Mail, sofern sie noch im Zeitfenster liegen.

## Einstellungen (je Verkaufskanal)

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Aktiv | aus | Plugin in diesem Verkaufskanal eingeschaltet |
| Nur Bestellungen ab | leer = Installationsdatum | Ältere Bestellungen bekommen nie eine Mail (ihnen fehlte der Hinweis bei Erhebung) |
| Nur Bestellungen aus dem Shop | an | Im Admin angelegte Bestellungen überspringen |
| Kästchen „Keine E-Mail nach dem Versand“ in der Kasse | aus | Nicht vorbelegt; angehakt = Widerspruch sofort gespeichert (Quelle „Kasse“) |
| Tage nach Versand | 10 | Wartezeit ab „Versandt“ |
| Nie nachholen nach (Tage nach Versand) | 60 | Ältere Versände bekommen nie eine Mail (Schutz beim ersten Einschalten); mindestens Tage + 2 |
| Mindestabstand je Kunde (Tage) | 30 | Hat dieselbe Adresse in diesem Zeitraum schon eine Nachfrage-Mail bekommen (gleich aus welchem Kanal), entfällt die Mail für weitere Bestellungen. 0 = aus |
| Höchstens ähnliche Produkte | 4 | 0 = keine Vorschläge |
| Fahrzeug-Eigenschaften für ähnliche Produkte | – | z. B. „Geeignet für“: Ausprägungen zählen wie spezifische Kategorien (Fahrzeugsignal ohne Modell-Kategorien) |
| Kategorien für ähnliche Produkte ignorieren | – | Sammelkategorien wie „Alle Artikel“ |
| Zusatztext dieses Verkaufskanals (HTML) | leer | Variable `extraText`, erscheint unter den Vorschlägen |
| Link für Google-Bewertung | leer | nur `https://…`, z. B. `https://g.page/r/…/review`; leer = Block entfällt |
| Text zur Google-Bewertung | leer | leer = Standardtext der Vorlage |
| Hinweis in der Kasse | leer | leer = Textbaustein `novaNachfrage.checkoutNotice` |
| Abmeldung gilt für alle Verkaufskanäle | an | gilt für Abmeldungen über den Link in Mails dieses Kanals; aus = nur dieser Kanal |
| Newsletter-Abmeldungen ebenfalls respektieren | an | keine Mail, wenn die Adresse bei den Newsletter-Empfängern als „abgemeldet“ steht |
| Header „List-Unsubscribe“ senden | an | One-Click-Abmeldung im Mailprogramm (nur bei https-Domains) |
| Testmodus / Testadresse | aus / leer | alle Mails an die Testadresse, Betreff mit „[Test]“ |

Beispiel für einen **Zusatztext** in einem Kanal mit wenigen Standardartikeln:

```html
<p>Sie haben noch ein weiteres Problem in Ihrem Haushalt? <a href="https://shop.example.com/anfrage">Fragen Sie doch ein neues Teil an</a> – wir fertigen auch Kleinserien.</p>
```

## Wann geht die Mail? (Entscheidungen)

- **Status „Versandt“, nicht „Teilweise versandt“.** Bei Teillieferungen hat der Kunde noch nicht alles;
  eine Bewertungsanfrage und Produktvorschläge wären verfrüht. Erst wenn **alle** Lieferungen der
  Bestellung auf „Versandt“ stehen, zählt die Frist (stornierte Lieferungen zählen nicht mit).
- **Versandzeitpunkt** = Zeitpunkt des Statuswechsels auf „Versandt“ aus der Statushistorie
  (`state_machine_history`) der Lieferung; bei mehreren Lieferungen der späteste. Fehlt die Historie (Status
  ohne Übergang direkt gesetzt, z. B. durch einen Import), zählt die letzte Änderung der Lieferung.
- **Keine Mail**, wenn die Bestellung storniert ist, die letzte Zahlung „erstattet“, „storniert“,
  „fehlgeschlagen“ oder „Rückbuchung“ ist, eine Lieferung „zurückgesendet“/„teilweise zurückgesendet“ ist
  oder die Bestellung keine Artikel enthält. Teilweise erstattete Bestellungen bekommen die Mail.
- **Höchstens eine Mail je Bestellung.** Verschickte, übersprungene (dauerhafte Gründe) und gescheiterte
  Bestellungen stehen im Versandprotokoll; gescheiterte werden bis zu dreimal neu versucht.
- **Je Lauf höchstens 100 Mails** (stündlich), damit der Mailserver nicht überlastet wird.
- Zeitfenster: Bestelldatum höchstens „Nie nachholen nach“ + 365 Tage zurück (sehr lange
  Fertigungszeiten darüber hinaus werden nicht berücksichtigt).

## Gekaufte Artikel und Bewertungslink

- Variable `products`: je gekauftem Artikel `name`, `productNumber`, `quantity`, `url` (Produktseite, `null`,
  wenn der Artikel im Kanal nicht mehr sichtbar ist), `reviewUrl`, `imageUrl`.
- **Bewertungsformular per URL:** Shopware 6.7 öffnet den Reiter „Bewertungen“ nicht über die Adresse
  (Bootstrap-Reiter reagieren nicht auf Anker; der Parameter `?success=1` würde fälschlich „Danke für Ihre
  Bewertung“ anzeigen). Das Plugin hängt deshalb `#nova-review` an die Produkt-URL und liefert auf
  Produktseiten ein kleines Skript aus, das bei diesem Anker den Reiter „Bewertungen“ öffnet (mobil das
  Off-Canvas), das Formular aufklappt und hinscrollt. Ohne Anmeldung zeigt Shopware dort die Anmeldung.
  Ihr Theme muss das Standard-Element „Beschreibung & Bewertungen“ verwenden (Klasse `review-tab`).
- **Gäste können in Shopware nicht bewerten** (das Formular verlangt ein Kundenkonto). Für Gastbestellungen
  (`canReview` = false) zeigt die Standardvorlage statt „Jetzt bewerten“ den Link zum Artikel und bittet um
  eine Antwort per Mail. Sind Bewertungen im Kanal ausgeschaltet (`reviewsEnabled` = false), ebenso.

## Ähnliche Produkte

Variable `recommendations` (`name`, `productNumber`, `url`, `imageUrl`, `price`, `referencePrice` mit `price` und `unit` oder `null`). Die Standardvorlage zeigt den Grundpreis und darunter „* inkl. MwSt., zzgl. Versandkosten“ (bei Netto-Kanälen `pricesNet` → „zzgl. MwSt.“):

Kandidaten sind die **Cross-Sellings** der gekauften Artikel (auch „Kunden kauften auch“) und die
**meistverkauften Artikel**. Vorgeschlagen wird nur, was eine **spezifische Kategorie** mit dem Kauf teilt:

- Spezifisch heißt: direkt am gekauften Artikel zugeordnet, im Kategoriebaum **dieses** Verkaufskanals, nicht
  die Hauptnavigation, keine Oberkategorie einer anderen zugeordneten Kategorie (z. B. zählt „CX“, aber nicht
  „Fahrzeuge“) und nicht in **„Kategorien für ähnliche Produkte ignorieren“** (Sammelkategorien wie
  „Alle Artikel“ dort eintragen).
- **Gewichtung:** Jeder gekaufte Artikel verteilt 1 auf seine Kategorien. Ein Teil, das zu sieben Modellen
  passt, zählt je Modell nur 1/7. Kategorien unter der Hälfte des Höchstgewichts entfallen – wer zwei
  CX-Federkugeln und einen Universal-Druckspeicher kauft, bekommt nur CX-Vorschläge.
- Höchste Summe zuerst, bei Gleichstand Cross-Selling vor Bestseller. Passt nichts, bleibt der Block leer.

Nur Artikel, die im Verkaufskanal **aktiv**, **sichtbar** (Kategorie-Vorschläge: auch in Listen und Suche)
und **lieferbar** sind; keine Artikel aus der Bestellung und keine anderen Varianten davon, je Hauptartikel
höchstens ein Vorschlag. Preise ohne kundenspezifische Preisregeln (Standardpreis des Kanals).

**Achten Sie darauf, dass Cross-Sellings und Kategorien wirklich „ähnliche eigene Waren“ liefern** – für
§ 7 Abs. 3 UWG ist das eine Voraussetzung. Wer nur einzelne Kategorien zulassen will, setzt
„Höchstens ähnliche Produkte“ auf 0 und pflegt die Empfehlung im Zusatztext.

## Mail-Variablen (Vorlage „Nova Nachfrage“)

| Variable | Inhalt |
|---|---|
| `order` | Bestellung (u. a. `orderNumber`, `orderDateTime`, `currency`) |
| `customer` | Besteller (`order.orderCustomer`): `salutation.translated.letterName`, `firstName`, `lastName`, `email` |
| `salesChannel` | Verkaufskanal (`salesChannel.translated.name`) |
| `products` | gekaufte Artikel, siehe oben |
| `reviewsEnabled`, `canReview` | Bewertungen im Kanal an / Kunde kann bewerten (Kundenkonto) |
| `googleReviewUrl`, `googleReviewText` | Google-Bewertung (leer = kein Block) |
| `recommendations` | ähnliche Produkte, siehe oben |
| `extraText` | Zusatztext des Kanals (HTML, wird in der Vorlage unverändert ausgegeben) |
| `unsubscribeUrl` | Abmeldelink – **in jeder Mail Pflicht** |
| `shopUrl`, `currencyIsoCode`, `optOutAllChannels` | Startseite, Währung, Geltungsbereich der Abmeldung |

## Hinweis bei Erhebung

**Registrierung/Gastbestellung:** Unter den Feldern für E-Mail und Passwort (Block
`component_account_register_personal_account_fields`).

**Kasse:** Auf der Bestellbestätigungsseite steht unter den AGB-Hinweisen (Block
`page_checkout_confirm_revocation_control`) derselbe Text, abgesetzt mit einem Strich links – Standard:

> Wir nutzen Ihre E-Mail-Adresse, um Sie nach dem Versand nach Ihrer Meinung zu fragen und auf eigene
> ähnliche Produkte hinzuweisen. Dem können Sie jederzeit widersprechen, z. B. per Link in jeder dieser
> E-Mails, ohne dass andere als die Übermittlungskosten nach den Basistarifen entstehen.

Eigener Text je Kanal über „Hinweis in der Kasse“, sonst Textbaustein `novaNachfrage.checkoutNotice`
(*Einstellungen → Textbausteine*). Template zum Anpassen: `storefront/nova-nachfrage/checkout-notice.html.twig`
(Block `nova_nachfrage_checkout_notice`).

**Bestellbestätigung (Mail):** Das Plugin stellt in **allen** Mail-Vorlagen die Variable
`novaNachfrageNotice` bereit (Text des Kanals, leer, wenn das Plugin im Kanal nicht aktiv ist). In
*Einstellungen → E-Mail-Vorlagen → Bestellbestätigung* einfügen – im HTML-Teil:

```twig
{% if novaNachfrageNotice is defined and novaNachfrageNotice %}
    <p style="font-size:12px; color:#777;">{{ novaNachfrageNotice }}</p>
{% endif %}
```

im Text-Teil:

```twig
{% if novaNachfrageNotice is defined and novaNachfrageNotice %}
{{ novaNachfrageNotice }}
{% endif %}
```

## Widerspruch (Abmeldung)

- **Link in jeder Mail:** `/nova-nachfrage/abmelden/{Hash}/{Kanal}/{Signatur}`. Die Signatur ist ein HMAC
  mit dem Geheimnis der Installation (`APP_SECRET`); im Link steht nur der Hash, nicht die Adresse. Der Link
  zeigt eine Seite mit Knopf – erst der Knopf (POST) trägt den Widerspruch ein, damit Mail-Scanner und
  Link-Vorschauen niemanden abmelden.
- **One-Click:** Mit `List-Unsubscribe`/`List-Unsubscribe-Post` (RFC 8058) melden Mailprogramme per POST auf
  dieselbe Adresse ab, ohne Zwischenseite. Nur bei https-Domains des Verkaufskanals.
- **Dauerhaft:** gespeichert wird der Hash der normalisierten Adresse (klein geschrieben, ohne
  Leerzeichen); er gilt für alle künftigen Bestellungen, auch als Gast. `name+shop@…` gilt als eigene Adresse.
- **Geltungsbereich:** Standard alle Verkaufskanäle; mit „Abmeldung gilt für alle Verkaufskanäle“ = aus nur
  für den Kanal der Mail. Manuell eingetragene Widersprüche gelten immer für alle Kanäle.
- **Newsletter:** Steht die Adresse bei Shopwares Newsletter-Empfängern mit Status „abgemeldet“, geht keine
  Mail (abschaltbar). Wer sich später wieder zum Newsletter anmeldet, ist dort nicht mehr „abgemeldet“ – ein
  Widerspruch über Nova Nachfrage bleibt davon unberührt.
- **Wird `APP_SECRET` geändert**, werden alte Abmeldelinks ungültig (die Seite bittet dann um eine kurze
  Nachricht). Bereits gespeicherte Widersprüche bleiben gültig, weil sie nicht vom Geheimnis abhängen.
- **Widerspruch per Telefon, Brief oder Mail:** in der Administration (*Einstellungen → Erweiterungen →
  Nova Nachfrage*, Recht „Einstellungen bearbeiten“) oder per Konsole eintragen:

```bash
bin/console nova-nachfrage:optouts add kunde@example.com          # alle Kanäle
bin/console nova-nachfrage:optouts add kunde@example.com --sales-channel=<ID>
bin/console nova-nachfrage:optouts check kunde@example.com
bin/console nova-nachfrage:optouts list --limit=100
bin/console nova-nachfrage:optouts remove kunde@example.com       # z. B. versehentlich eingetragen
```

## Konsole

```bash
bin/console nova-nachfrage:send --dry-run            # wer bekäme jetzt was (nichts senden, nichts speichern)
bin/console nova-nachfrage:send --dry-run --pending  # zusätzlich noch nicht fällige mit Fälligkeit
bin/console nova-nachfrage:send --order=10023        # nur diese Bestellung
bin/console nova-nachfrage:send --sales-channel=<ID> --limit=20
```

Ausgegeben werden Bestellung, Empfänger, Aktion (`would_send`, `sent`, `test`, `skipped`, `failed`, `wait`),
Grund, Fälligkeit (UTC), gekaufte Artikel und Vorschläge.

## Datenschutz

| Tabelle | Inhalt | Aufbewahrung |
|---|---|---|
| `nova_nachfrage_optout` | SHA-256-Hash der normalisierten Adresse (mit festem Präfix), Zeitpunkt, Quelle (`link`, `one_click`, `admin`, `cli`), optional Verkaufskanal | dauerhaft – sonst könnte der Widerspruch nicht beachtet werden |
| `nova_nachfrage_log` | `order_id`, Verkaufskanal, Hash der Adresse, Status, Grund, Versuche, Zeitpunkte | 400 Tage, danach automatisch gelöscht (die Bestellung ist dann ohnehin außerhalb des Zeitfensters) |

- Keine E-Mail-Adresse im Klartext in den Tabellen des Plugins. Der Hash ist **pseudonym, nicht anonym**:
  Wer eine Adresse kennt, kann prüfen, ob sie eingetragen ist. Bewusst kein HMAC mit `APP_SECRET`, damit
  Widersprüche einen Wechsel des Geheimnisses überstehen.
- Die Administration zeigt nur die ersten 12 Zeichen des Hashs, nie Adressen.
- Wird eine Bestellung gelöscht, bleibt ihr Protokolleintrag bis zur Löschfrist ohne Bezug stehen.
- Deinstallation **ohne** „Daten behalten“ löscht beide Tabellen – **auch die Widersprüche**. Wer das Plugin
  später wieder nutzen will, sollte „Daten behalten“ wählen.
- Datenschutzerklärung: Nova Rechtstexte (ab 1.8.7) bringt den Baustein `bestandskundenwerbung` mit
  (Rechtsgrundlage Art. 6 Abs. 1 lit. f DSGVO, Widerspruchsrecht, Speicherung des Widerspruchs als Hash).

## Hinweise

- **Google-Bewertung:** Der Block erscheint für **alle** Empfänger gleich, gleichrangig nach den
  Produktbewertungen. Bitte kein „Review Gating“ einbauen (nur Zufriedene zu Google, Unzufriedene zum
  Kontakt leiten) und keine Anreize oder Gutscheine für Bewertungen anbieten – beides verbieten die
  Richtlinien von Google, und Anreize für Bewertungen sind auch wettbewerbsrechtlich heikel.
- Die Mail ist Werbung. Halten Sie den Inhalt bei eigenen, **ähnlichen** Produkten – keine allgemeinen
  Angebote, keine Fremdprodukte.
- Bestellungen, die schon vor der Installation verschickt wurden, bekommen die Mail nur, wenn ihr Versand
  höchstens „Nie nachholen nach“ Tage zurückliegt. Wer gar keine Altbestellungen anschreiben will, setzt den
  Wert beim ersten Einschalten auf „Tage nach Versand“ + 2 und erhöht ihn später.

## Template-Blöcke

| Template | Blöcke |
|---|---|
| `storefront/nova-nachfrage/checkout-notice.html.twig` | `nova_nachfrage_checkout_notice` |
| `storefront/nova-nachfrage/unsubscribe.html.twig` | `nova_nachfrage_unsubscribe`, `…_confirm`, `…_done`, `…_invalid` |
| `storefront/base.html.twig` | `nova_nachfrage_review_anchor_script` |

## Entwicklung

Die Administration liegt fertig gebaut unter `src/Resources/public/administration` (wird eingecheckt).
Nach Änderungen an `src/Resources/app/administration` neu bauen – innerhalb einer Shopware-Installation mit
`bin/build-administration.sh`, oder nur dieses Plugin mit dem Administration-Quellcode von Shopware 6.7:

```bash
# PROJECT_ROOT enthält var/plugins.json (Eintrag „NovaNachfrage“ mit basePath custom/plugins/NovaNachfrage/src/)
# und custom/plugins/NovaNachfrage (Kopie dieses Repositorys)
cd <shopware>/src/Administration/Resources/app/administration
PROJECT_ROOT=<projekt> SHOPWARE_ADMIN_BUILD_ONLY_EXTENSIONS=1 VITE_MODE=production npx ts-node -T build.ts
```

Unit-Tests der reinen Logik (Fälligkeit, Token, Normalisierung, Auswahl) laufen ohne Shopware-Kernel:

```bash
php phpunit.phar   # oder vendor/bin/phpunit
```

## Häufige Probleme

### Es geht keine Mail

**Ursache:** Plugin im Verkaufskanal nicht aktiv, geplante Aufgaben laufen nicht, Lieferung nur „teilweise
versandt“, Versand liegt außerhalb des Zeitfensters, Mindestabstand oder Widerspruch

**Lösung:** `bin/console nova-nachfrage:send --dry-run --pending` zeigt je Bestellung Aktion und Grund;
Cron/Admin-Worker prüfen

### „Jetzt bewerten“ öffnet nur die Produktseite

**Ursache:** Das Theme nutzt nicht Shopwares Element „Beschreibung & Bewertungen“, Bewertungen sind
ausgeschaltet, oder ein Cookie-/Skriptblocker unterdrückt Inline-Skripte

**Lösung:** Produktseiten-Layout prüfen; ggf. den Block `nova_nachfrage_review_anchor_script` im eigenen
Theme an die eigenen Selektoren anpassen

### Kein „Abmelden“-Knopf im Mailprogramm

**Ursache:** Domain des Verkaufskanals nicht https, Header in der Konfiguration aus, oder das Mailprogramm
zeigt ihn erst bei ausreichender Absender-Reputation (DKIM/SPF/DMARC)

**Lösung:** https-Domain und DKIM-Signatur des Mailservers prüfen; der Link im Mailfuß funktioniert immer

## Deinstallation

Ohne „Daten behalten“ werden die Tabellen `nova_nachfrage_log` und `nova_nachfrage_optout` (also auch alle
Widersprüche), der Mail-Vorlagentyp „Nova Nachfrage“ samt Vorlagen und die Plugin-Konfiguration gelöscht.

## Entstehung

Dieses Plugin ist mit Unterstützung von KI entstanden (Claude von Anthropic). Die Anforderungen kommen aus dem täglichen Betrieb mehrerer Shopware-Shops; der Code wurde mit KI geschrieben und überarbeitet. Geprüft wird er mit automatischen Tests und statischer Analyse (soweit im Repository vorhanden), mit regelmäßigen Sicherheitsprüfungen des Codes und im Echtbetrieb in den eigenen Shops. Trotzdem gilt: Nutzung auf eigene Verantwortung und ohne Garantie (siehe Lizenz). Fehler und Verbesserungsvorschläge bitte als Issue melden. Mehr zum Hintergrund: [SW6-Nova-Plugins](https://github.com/DrdotHouse2106/SW6-Nova-Plugins).

## Lizenz

MIT – siehe [LICENSE](LICENSE).
