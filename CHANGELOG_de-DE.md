# 1.0.0

- Erste Version: **Nachfrage-Mail nach dem Versand** – je Bestellung höchstens eine Mail, X Tage (Standard 10) nachdem alle Lieferungen auf „Versandt“ stehen (Zeitpunkt aus der Statushistorie); stündliche geplante Aufgabe und Konsolenbefehl `nova-nachfrage:send` (mit `--dry-run`, `--pending`, `--order`)
- Inhalt: gekaufte Artikel mit Link „Jetzt bewerten“ (Anker `#nova-review` öffnet das Bewertungsformular), optional Block **Google-Bewertung** (Link je Verkaufskanal, für alle Empfänger gleich), ähnliche Produkte (Cross-Sellings, dann Bestseller derselben Kategorien; nur aktiv, sichtbar, lieferbar, nicht gekauft), Zusatztext je Verkaufskanal, Pflichtfuß mit Widerspruchshinweis
- Eigener Mail-Vorlagentyp „Nova Nachfrage“ (`nova_nachfrage`) mit Standardvorlage de/en
- Widerspruch: signierter Abmeldelink (Abmeldung erst per Knopf), One-Click-Abmeldung über `List-Unsubscribe`/`List-Unsubscribe-Post`, dauerhafte Speicherung nur als Hash, optional Newsletter-Abmeldungen beachten, Geltung für alle oder einen Verkaufskanal
- Schutzregeln: keine Mail bei Storno, Erstattung, Rücksendung oder Bestellungen ohne Artikel; Mindestabstand je Kunde (Standard 30 Tage); Versand älter als 60 Tage wird nie nachgeholt; höchstens 100 Mails je Lauf
- Hinweis bei Erhebung auf der Bestellbestätigungsseite und als Variable `novaNachfrageNotice` für alle Mail-Vorlagen (z. B. Bestellbestätigung)
- Testmodus (alle Mails an eine Testadresse)
- Administration: Übersicht unter Einstellungen → Erweiterungen (Kennzahlen, demnächst fällig, zuletzt verarbeitet, Widersprüche, Widerspruch eintragen); Konsolenbefehl `nova-nachfrage:optouts` (list, add, check, remove)
