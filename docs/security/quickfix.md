### Quick Fixes

- API Platform: добавить security/securityPostDenormalize, ограничить paginationItemsPerPage/maximumItemsPerPage.
- Контроллеры: #[IsGranted('ROLE_USER')] и voter на владение; явно указывать methods в #[Route].
- CORS: явный allow_origin и allow_credentials: true, без * при куки.
- Cookies: Secure, HttpOnly, SameSite=Lax|Strict.
- Клиент: Content-Type: application/json для запросов с телом, Authorization: Bearer для защищённых.