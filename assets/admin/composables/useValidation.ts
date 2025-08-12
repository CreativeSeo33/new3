import { reactive, computed, readonly } from 'vue';

interface ValidationRule {
  required?: boolean;
  min?: number;
  max?: number;
  email?: boolean;
  pattern?: RegExp;
  custom?: (value: any) => string | null;
}

interface ValidationSchema {
  [field: string]: ValidationRule;
}

export function useValidation<T extends Record<string, any>>(schema: ValidationSchema) {
  const errors = reactive<Record<string, string>>({});
  const touched = reactive<Record<string, boolean>>({});

  const validate = (field: string, value: any): string | null => {
    const rule = schema[field];
    if (!rule) return null;

    if (rule.required && (!value || (typeof value === 'string' && !value.trim()))) {
      return 'This field is required';
    }

    if (!value && !rule.required) return null;

    if (rule.min && typeof value === 'string' && value.length < rule.min) {
      return `Minimum length is ${rule.min}`;
    }

    if (rule.max && typeof value === 'string' && value.length > rule.max) {
      return `Maximum length is ${rule.max}`;
    }

    if (rule.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      return 'Invalid email format';
    }

    if (rule.pattern && !rule.pattern.test(value)) {
      return 'Invalid format';
    }

    if (rule.custom) {
      return rule.custom(value);
    }

    return null;
  };

  const validateField = (field: string, value: any) => {
    touched[field] = true;
    const error = validate(field, value);
    if (error) {
      errors[field] = error;
    } else {
      delete errors[field];
    }
    return !error;
  };

  const validateAll = (data: T): boolean => {
    let isValid = true;
    Object.keys(schema).forEach((field) => {
      const fieldValid = validateField(field, data[field]);
      if (!fieldValid) isValid = false;
    });
    return isValid;
  };

  const hasErrors = computed(() => Object.keys(errors).length > 0);
  const isFieldValid = (field: string) => touched[field] && !errors[field];
  const isFieldInvalid = (field: string) => touched[field] && !!errors[field];

  const reset = () => {
    Object.keys(errors).forEach((key) => delete errors[key]);
    Object.keys(touched).forEach((key) => delete touched[key]);
  };

  return {
    errors: readonly(errors),
    touched: readonly(touched),
    hasErrors,
    validateField,
    validateAll,
    isFieldValid,
    isFieldInvalid,
    reset,
  };
}


