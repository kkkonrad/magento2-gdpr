# Runbook

## Kolejka nie pracuje

1. `bin/magento cron:run --group=default`.
2. `bin/magento kkkonrad:gdpr:cron --dry-run`.
3. Sprawdź master flag i flagę funkcji zadania; wyłączona funkcja pozostawia job w `queued`.
4. Claim starszy niż 15 minut jest automatycznie zwalniany przy następnym przebiegu.
5. Uruchom kontrolowany przebieg: `bin/magento kkkonrad:gdpr:cron --limit=10`.

## Eksport

Plik istnieje wyłącznie w `var/kkkonrad/gdpr/exports`. Pobranie wymaga aktywnej sesji właściciela i niewygasłego rekordu. Cleanup usuwa plik oraz rekord po TTL. Nie kopiuj eksportów do publicznego storage ani backupów bez szyfrowania.

## Nieudana anonimizacja/usunięcie

Nie wykonuj ręcznego rollbacku danych. Sprawdź `kkkonrad_gdpr_job`, `kkkonrad_gdpr_processor_result`, timeline requestu i bezpieczny kod błędu. Operacje są nieodwracalne; retry powinien wykonać uprawniony administrator po usunięciu przyczyny i ponownej kontroli legal hold/otwartych zamówień.

## Rollback wydania

Wyłącz funkcje w konfiguracji, zatrzymaj nowe joby i przywróć poprzedni kod. Nie usuwaj tabel. Rollback kodu nie cofa decyzji, eksportów pobranych przez klienta, anonimizacji ani erasure.
