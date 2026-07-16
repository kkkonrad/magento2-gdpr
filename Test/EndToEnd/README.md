# E2E

Włącz testowane feature flags i przygotuj treści/grupy w store view, następnie:

```bash
cd app/code/Kkkonrad/Gdpr/Test/EndToEnd
npm ci
npx playwright install chromium
GDPR_BASE_URL=https://store.example/ npm test
```

Testy CMP same oznaczają się jako skipped, gdy moduł lub banner jest wyłączony. Uruchomienie nie powinno odbywać się na produkcji, ponieważ zapisuje dowody decyzji cookies.
