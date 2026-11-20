# Partner Client Key implementation

User request: create Client Keys in Settings and require a matching Client Key header in addition to Bearer authentication.

Chosen scope: mandatory `X-Client-Key` for Partner API live/sandbox; one independently random Client Key per Bearer credential. Compare SHA-256 hashes with hash_equals; never store/recover plaintext. Existing credentials without a Client Key fail closed until owner/admin generates one. Rotation preserves Bearer tokens and invalidates the old Client Key. Mobile, Midtrans notifications and browser session authentication remain separate.

- [x] Test missing/wrong/cross-pair/oversized client headers, hashed storage, legacy bootstrap, rotation/revocation and tenant/role boundaries.
- [x] Add nullable hash/prefix migration, model pair issuance, auth guard, session/CSRF-protected rotation and CLI output.
- [x] Add Settings credential-pair UI and rotation; clearly label existing Sanctum token controls as legacy.
- [x] Update API examples/OpenAPI AND authentication; update existing partner fixtures; verify PHP/Node tests, build, migration and browser flow.

## Verification

- Backend full suite: 400 tests, 4007 assertions passed (PHP 512 MB); focused client-key tests: 7 passed.
- Frontend: 46 Node tests passed; Vite build passed after the final UI fix.
- Browser: legacy key bootstrap, create pair, header mandatory, cross-pair denial, tenant outlet isolation, rotation and revocation verified; API controls do not submit the surrounding store form. Desktop/mobile and no page errors checked.
- Local additive migration applied after SQLite backup; down/up roundtrip verified in a separate disposable database.
- OpenAPI JSON/local references and AND authentication checked; formatting and diff checks pass.
- Review corrected ignored filenames: UI/module/test use partner-client-key names so the repository-wide credentials ignore rule does not omit source. No production deployment or push performed.
