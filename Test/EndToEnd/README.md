# E2E

Włącz testowane feature flags i przygotuj treści/grupy w store view, następnie:

```bash
cd app/code/Kkkonrad/Gdpr/Test/EndToEnd
npm ci
npx playwright install chromium
GDPR_BASE_URL=https://store.example/ npm test
```

Dla osobnych przebiegów kompatybilności ustaw oczekiwany adapter:

```bash
GDPR_BASE_URL=https://luma.example/ GDPR_FRONTEND_ADAPTER=luma npm test
GDPR_BASE_URL=https://hyva.example/ GDPR_FRONTEND_ADAPTER=hyva npm test
```

Testy CMP same oznaczają się jako skipped, gdy moduł lub banner jest wyłączony. Uruchomienie nie powinno odbywać się na produkcji, ponieważ zapisuje dowody decyzji cookies.
