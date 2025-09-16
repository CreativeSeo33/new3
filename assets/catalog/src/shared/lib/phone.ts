// ai:base-utils name=phone purpose=validation/normalization
/**
 * Утилиты для нормализации и валидации телефона (облегчённая версия, РФ/Generic)
 */

export interface PhoneValidationResult {
  valid: boolean;
  normalized: string; // E.164-подобный вид (+71234567890)
  compact: string; // только цифры
  error: string | null;
}

export function normalizePhone(raw: string): { compact: string; e164Guess: string } {
  const compact = (raw || '').replace(/\D+/g, '');
  const e164Guess = compact ? `+${compact}` : '';
  return { compact, e164Guess };
}

export function isPhoneValidGeneric(raw: string, opts?: { min?: number; max?: number }): boolean {
  const { compact } = normalizePhone(raw);
  const min = opts?.min ?? 10;
  const max = opts?.max ?? 15;
  return compact.length >= min && compact.length <= max;
}

/**
 * РФ: допускаем 10 цифр (без кода) или 11 цифр, начинающихся с 7/8
 */
export function isPhoneValidRU(raw: string): boolean {
  const { compact } = normalizePhone(raw);
  if (compact.length === 10) return true;
  if (compact.length === 11 && /^(7|8)\d{10}$/.test(compact)) return true;
  return false;
}

export function validatePhone(
  raw: string,
  country: 'RU' | 'GENERIC' = 'GENERIC'
): PhoneValidationResult {
  const { compact, e164Guess } = normalizePhone(raw);
  const valid = country === 'RU' ? isPhoneValidRU(raw) : isPhoneValidGeneric(raw);
  return {
    valid,
    normalized: e164Guess,
    compact,
    error: valid ? null : 'Введите корректный телефон (10–15 цифр)',
  };
}


