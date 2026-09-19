# Themenpakete für Skins

Stand: 13. September 2026.

Jede Premium-Themenreihe besteht aus drei Paketen, die pro Thema nacheinander freigeschaltet werden. Jedes Paket enthält Rohstoffe, Edelsteine und genau eine passende Kosmetik. Besitz und Freischaltung kommen vollständig aus dem Serverzustand; das Frontend schreibt keine Belohnungen gut.

| Stufe | Kosmetik | Nahrung | Holz | Stein | Gold | Edelsteine | Preis |
|---:|---|---:|---:|---:|---:|---:|---:|
| 1 | Namensrahmen | 100.000 | 100.000 | 50.000 | 25.000 | 500 | 5,99 € |
| 2 | Marsch-Skin | 250.000 | 250.000 | 100.000 | 50.000 | 750 | 9,99 € |
| 3 | Burg-Skin | 500.000 | 500.000 | 200.000 | 100.000 | 1.000 | 9,99 € |

Der Gesamtpreis einer vollständigen Reihe beträgt 25,97 €. Ein Marsch-Skin bleibt parallel direkt für erspielte Edelsteine erhältlich. Bereits separat besessene Kosmetik wird als „bereits im Besitz“ ausgewiesen; das Paket bleibt wegen seiner Rohstoffe und Edelsteine kaufbar.

## Serververtrag

`GET /api/kingdom/state` liefert `theme_bundles` mit `payment_configured`, `currency`, optionaler `provider_message` und `entries`. Jeder Eintrag enthält `id`, `theme_id`, `theme_title`, `step`, `title`, `price_cents`, `currency`, `status`, `owned`, `cosmetic_owned`, `unlocked`, `next`, `contents.resources`, `contents.gems` und `contents.cosmetic`.

Der Checkout verwendet den bestehenden authentifizierten und CSRF-geschützten Endpunkt `POST /api/kingdom/action`:

```json
{
  "action": "theme_bundle.checkout",
  "bundle_id": "dragon_1",
  "operation_key": "eine-stabile-idempotenz-kennung"
}
```

Das Frontend hält den `operation_key` je Paket und Browsersitzung stabil, damit ein wiederholter Checkout nicht doppelt angelegt wird. Eine vom Server zurückgegebene `result.checkout.checkout_url` oder `redirect_url` wird nur mit `http` oder `https` geöffnet. Nach Rückkehr muss der Serverzustand den Kauf bestätigen, bevor Besitz oder Guthaben als geändert angezeigt werden.

Ist kein Zahlungsanbieter eingerichtet, zeigt die Sammlung Katalog und Inhalte weiterhin vollständig, deaktiviert aber den Kauf und nennt den Grund. Bei `status: "pending"` bleibt der Kauf ebenfalls gesperrt, bis der Server die Zahlung bestätigt. Der Client simuliert in keinem dieser Zustände Kauf oder Belohnung.

## Oberfläche und Prüfung

Die Skin-Sammlung besitzt die vier Reiter Burg, Marsch, Rahmen und Pakete. Die Paketansicht zeigt die Themenwahl, alle drei Stufen, Besitz, Sperrgrund, nächsten Schritt und Gesamtpreis. Desktop verwendet drei Spalten. Kleine Hochformate verwenden eine vertikal scrollbare Folge; kurzes Querformat behält drei kompakte Spalten. Alle Aktionen bleiben mindestens 40 bis 44 Pixel hoch, soweit die kurze Querformatansicht keine kompakte 32-Pixel-Aktion benötigt.

`tests/theme_bundle_shop.cjs` prüft ausschließlich eine isolierte Testseite. Es kontrolliert Serverwerte, Reihenfolge, Besitz und Sperren, nicht konfigurierten Checkout, stabilen Idempotenzschlüssel, das reale Checkout-Wrapperformat, Burg-Skin-Besitz sowie Desktop, 390 px, 320 px und kurzes Querformat. Screenshots liegen in `artifacts/theme-bundle-ui/`.
