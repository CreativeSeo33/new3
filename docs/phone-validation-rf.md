# Валидация телефона (РФ) — лёгкая версия

Короткие правки без зависимостей. Клиент: подсветка/CustomValidity, Сервер: проверка длины цифр (10–15).

## Изменения

1) Twig поле телефона

```12:20:templates/components/checkout/contact-form.html.twig
      <div>
        <label class="block text-sm mb-1">Телефон</label>
        <input
          name="phone"
          type="tel"
          inputmode="tel"
          autocomplete="tel"
          pattern="^\+?[0-9\s\-\(\)]{10,20}$"
          placeholder="+7 (___) ___-__-__"
          class="w-full border rounded px-3 py-2"
          required
        />
      </div>
```

2) Утиль валидации

```ts
// assets/catalog/src/shared/lib/phone.ts
export function validatePhone(raw: string, country: 'RU' | 'GENERIC' = 'GENERIC') { /* см. файл */ }
```

3) Интеграция в компонент формы

```ts
// assets/catalog/src/features/checkout-form/ui/component.ts
import { validatePhone } from '@shared/lib/phone';
// проверка на input/blur, setCustomValidity перед submit
```

4) Серверная проверка

```php
// src/Controller/Catalog/CheckoutController.php
$digits = preg_replace('/\D+/', '', $phone) ?? '';
$validPhone = (strlen($digits) >= 10 && strlen($digits) <= 15);
if ($name === '' || !$validPhone || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    return $this->json(['error' => 'Проверьте корректность данных'], 400);
}
```

## Поведение
- Подсветка поля при вводе (ring-red-500), сообщение валидатора браузера при submit.
- Сервер — конечный арбитр.

## Почему так
- РФ, отсутствие строгой мультистрановой необходимости → без зависимостей.
- Можно усилить до E.164 или libphonenumber-js при расширении географии.


