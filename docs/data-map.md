# Mapa eksportu i anonimizacji

## Eksport 1.0

ZIP zawiera `manifest.json` oraz CSV: `customer`, `addresses`, `orders`, `order-items`, `consents`, `newsletter`. Pola są jawnie allowlistowane. Nie są eksportowane hashe haseł, recovery tokeny, gateway tokens, dane Vault, IP/proxy ani sekrety płatności. CSV ma BOM UTF-8, a wartości zaczynające się od `=`, `+`, `-`, `@` są neutralizowane.

## Anonimizacja

- customer i customer address: imię/nazwisko, e-mail, adres, telefon, VAT, DOB, płeć, token resetu, hasło i sesje;
- quote i quote address: dane klienta, adresowe, IP, notatki i hashe gościa;
- sales order i sales order address: PII, IP, notatki i powiązanie customer ID; kwoty/statusy/SKU pozostają;
- newsletter: subskrypcja jest usuwana;
- review: customer ID jest odłączany, nickname zastępowany;
- erasure dodatkowo usuwa Vault tokens, wishlist, quote, newsletter, adresy i konto przez service contract Magento.

Pseudonimy powstają z losowego identyfikatora operacji oraz ID encji, bez użycia oryginalnego PII i bez mapy odwracającej.
