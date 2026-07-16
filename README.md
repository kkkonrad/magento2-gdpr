# Kkkonrad_Gdpr

Jeden moduł Magento 2 obsługujący prawa do danych, dowody zgód formularzowych, CMP cookies, Google Consent Mode v2, anonimizację, usunięcie konta i retencję. Każdy obszar ma osobną flagę w `Stores > Configuration > Kkkonrad > GDPR Settings`; wyłączenie funkcji zatrzymuje nowe działania, ale nie usuwa historii.

## Instalacja

```bash
bin/magento module:enable Kkkonrad_Gdpr
bin/magento setup:upgrade
bin/magento cache:flush
```

W trybie produkcyjnym wykonaj także `bin/magento setup:di:compile` i standardowy static content deploy. Moduł jest domyślnie całkowicie wyłączony. Włączaj funkcje etapami po zatwierdzeniu treści, klasyfikacji cookies, okresów retencji i mapy anonimizacji przez IOD/biznes.

## Bezpieczna kolejność aktywacji

1. `General > Enable GDPR`.
2. Dashboard i eksport dla kont testowych.
3. Zgody formularzowe po utworzeniu i opublikowaniu definicji per store view.
4. Cookie Consent w staging, następnie banner i integracje tagów.
5. Google Consent Mode po weryfikacji kolejności w Tag Assistant.
6. Anonimizacja/usunięcie dopiero po testach na kopii danych.
7. Retencja automatyczna na końcu, najpierw z dużymi okresami i małym batchem.

## Operacje

- Worker: `bin/magento kkkonrad:gdpr:cron --limit=100`.
- Podgląd kolejki: `bin/magento kkkonrad:gdpr:cron --dry-run`.
- Cron Magento uruchamia worker co minutę, retencję o 02:15 i cleanup eksportów co godzinę.
- Eksporty trafiają do `var/kkkonrad/gdpr/exports` z prawami `0600`, nigdy do `pub/`.
- Decyzje i zdarzenia audytowe są append-only; rollback kodu nie odwraca anonimizacji ani usunięcia.

## Integracja tagów

Opcjonalny skrypt można zablokować deklaratywnie:

```html
<script type="text/plain" data-kkkonrad-consent="marketing" src="https://example.invalid/tag.js"></script>
```

Stan jest dostępny przez `window.kkkonradConsent.has('marketing')`, a zmiana emituje `kkkonrad:consent-changed`. Szczegóły i ograniczenia znajdują się w [docs/cookie-integration.md](docs/cookie-integration.md).

## Testy

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Kkkonrad/Gdpr/Test/Unit
vendor/bin/phpstan analyse -c app/code/Kkkonrad/Gdpr/phpstan.neon --no-progress
vendor/bin/phpcs --standard=Magento2 --extensions=php app/code/Kkkonrad/Gdpr --ignore='*/Test/*'
bin/magento setup:di:compile
```

PHPCS dla obecnej bazy zgłasza wyłącznie ostrzeżenia dokumentacyjne/rozszerzalności, bez błędów standardu. Ostrzeżenie PHPUnit o brakującym `allure/allure.config.php` pochodzi z konfiguracji projektu, nie z modułu.

## Wsparcie

- Magento Open Source 2.4.8-p5.
- PHP 8.2–8.4.
- Frontend Luma i Hyvä; skrypty storefront nie wymagają `unsafe-eval` ani inline handlerów.
- MySQL/MariaDB zgodne z wymaganiami bieżącej wersji Magento.

Moduł jest narzędziem technicznym. Nie stanowi porady prawnej i nie zastępuje zatwierdzenia polityk przez IOD.
