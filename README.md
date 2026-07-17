# Kkkonrad_Gdpr

Jeden moduł Magento 2 obsługujący prawa do danych, dowody zgód formularzowych, CMP cookies, Google Consent Mode v2, anonimizację, usunięcie konta i retencję. Każdy obszar ma osobną flagę w `Stores > Configuration > Kkkonrad > GDPR Settings`; wyłączenie funkcji zatrzymuje nowe działania, ale nie usuwa historii.

## Instalacja

Pakiet Composer: `kkkonrad/module-gdpr`  
Moduł Magento: `Kkkonrad_Gdpr`  
Repozytorium Git: `https://github.com/kkkonrad/magento2-gdpr.git`

### Composer z repozytorium Git

W katalogu głównym projektu Magento:

```bash
composer config repositories.kkkonrad-gdpr vcs https://github.com/kkkonrad/magento2-gdpr.git
composer require kkkonrad/module-gdpr
```

Warianty wersji (Composer bierze wersję z **tagów Git**, nie z `composer.json`):

```bash
# gałąź master (dopóki nie ma opublikowanych tagów)
composer require kkkonrad/module-gdpr:dev-master

# po utworzeniu taga, np. v1.0.0
composer require kkkonrad/module-gdpr:^1.0
# albo dokładnie:
composer require kkkonrad/module-gdpr:1.0.0
```

Prywatne repozytorium (SSH):

```bash
composer config repositories.kkkonrad-gdpr vcs git@github.com:kkkonrad/magento2-gdpr.git
composer require kkkonrad/module-gdpr
```

Po pobraniu pakietu włącz moduł w Magento:

```bash
bin/magento module:enable Kkkonrad_Gdpr
bin/magento setup:upgrade
bin/magento cache:flush
```

W trybie produkcyjnym dodatkowo:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

### Po instalacji

Moduł jest domyślnie całkowicie wyłączony w konfiguracji (`Stores > Configuration > Kkkonrad > GDPR Settings`). Włączaj funkcje etapami po zatwierdzeniu treści, klasyfikacji cookies, okresów retencji i mapy anonimizacji przez IOD/biznes.

W grupie `General` dostępne są jednorazowe profile startowe `UE — strict/default denied` oraz `Global informational notice`. Zastosowanie wymaga jawnego potwierdzenia, jest audytowane i nie włącza głównego przełącznika modułu. Profil globalny dopuszczający niezarządzane integracje wymaga osobnej oceny prawnej.

## Bezpieczna kolejność aktywacji

1. `General > Enable GDPR`.
2. Dashboard i eksport dla kont testowych.
3. Zgody formularzowe po utworzeniu i opublikowaniu definicji per store view.
4. Cookie Consent w staging, następnie banner i integracje tagów.
5. Google Consent Mode po weryfikacji kolejności w Tag Assistant.
6. Anonimizacja/usunięcie dopiero po testach na kopii danych.
7. Retencja automatyczna na końcu, najpierw z dużymi okresami i małym batchem.

Przy automatycznym usuwaniu porzuconych kont można włączyć ostrzeżenie z wyprzedzeniem. Usunięcie jest wtedy odroczone, a aktywność konta zostaje sprawdzona ponownie tuż przed wykonaniem. Alerty o nowych żądaniach usunięcia i terminalnych błędach automatyzacji można skierować do aktywnych administratorów wybranych ról.

## Operacje

- Worker: `bin/magento kkkonrad:gdpr:cron --limit=100`.
- Podgląd kolejki: `bin/magento kkkonrad:gdpr:cron --dry-run`.
- Cron Magento uruchamia worker co minutę, retencję o 02:15 i cleanup eksportów co godzinę.
- Eksporty trafiają do `var/kkkonrad/gdpr/exports` z prawami `0600`, nigdy do `pub/`.
- Administrator z dedykowanym ACL może z gridu żądań zlecić eksport dla klienta; wymagane uzasadnienie jest szyfrowane, a tożsamość administratora trafia do historii.
- Decyzje i zdarzenia audytowe są append-only; rollback kodu nie odwraca anonimizacji ani usunięcia.
- Przerwane wysyłki outbox są odzyskiwane po 15 minutach; zaszyfrowane dane adresata są czyszczone po wysłaniu lub wygaśnięciu TTL.
- Domyślna reautoryzacja używa aktualnego hasła. Sklep passwordless może podmienić publiczny kontrakt `Kkkonrad\Gdpr\Api\DataRights\ReauthenticationInterface` własnym adapterem dostawcy logowania.

## Integracja tagów

Opcjonalny skrypt można zablokować deklaratywnie:

```html
<script type="text/plain" data-kkkonrad-consent="marketing" src="https://example.invalid/tag.js"></script>
```

Stan jest dostępny przez `window.kkkonradConsent.has('marketing')`, a zmiana emituje `kkkonrad:consent-changed`. Szczegóły i ograniczenia znajdują się w [docs/cookie-integration.md](docs/cookie-integration.md).

## Testy

```bash
vendor/bin/phpunit --no-extensions -c dev/tests/unit/phpunit.xml.dist app/code/Kkkonrad/Gdpr/Test/Unit
vendor/bin/phpstan analyse -c app/code/Kkkonrad/Gdpr/phpstan.neon --no-progress
vendor/bin/phpcs --standard=Magento2 --extensions=php app/code/Kkkonrad/Gdpr --ignore='*/Test/*'
bin/magento setup:di:compile
```

PHPCS dla obecnej bazy zgłasza wyłącznie ostrzeżenia dokumentacyjne/rozszerzalności, bez błędów standardu. Opcja `--no-extensions` izoluje testy modułu od niekompletnej globalnej konfiguracji Allure projektu.

## Wsparcie

- Magento Open Source 2.4.8-p5.
- PHP 8.2–8.4.
- Frontend Luma i Hyvä; skrypty storefront nie wymagają `unsafe-eval` ani inline handlerów.
- MySQL/MariaDB zgodne z wymaganiami bieżącej wersji Magento.

Moduł jest narzędziem technicznym. Nie stanowi porady prawnej i nie zastępuje zatwierdzenia polityk przez IOD.
