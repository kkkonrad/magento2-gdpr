# Integracja cookies i tagów

Każdy cookie/storage musi należeć do aktywnej grupy. Pattern obsługuje dopasowanie dokładne albo pojedynczy wildcard wyłącznie na końcu, np. `_ga*`. Nakładające się wzorce tego samego typu storage są odrzucane.

## Gating

Skrypty opcjonalne powinny być renderowane jako `type="text/plain"` z `data-kkkonrad-consent="kod_grupy"`. CMP odtworzy skrypt dopiero po zgodzie. Dla GTM zalecane jest mapowanie eventu `kkkonrad:consent-changed` i typów Consent Mode, nie sam cleanup cookies.

## Ograniczenia techniczne

- JavaScript nie odczyta ani nie usunie HttpOnly cookies.
- Cookie ustawione dla innego domain/path może wymagać adaptera integracji.
- Moduł nie przechwytuje niezawodnie każdego nagłówka `Set-Cookie` wysłanego przez obcy backend.
- Local/session storage jest czyszczony tylko dla skatalogowanych kluczy.
- Diagnostyka wysyła nazwę i domenę, nigdy wartość; endpoint ma limit żądań.

Krytyczne tagi płatnicze, analityczne i marketingowe należy przetestować na staging z realną konfiguracją GTM/GA4.
