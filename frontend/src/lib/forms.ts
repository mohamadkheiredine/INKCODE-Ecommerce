import type { FieldPath, FieldValues, UseFormSetError } from 'react-hook-form'
import i18n from './i18n'
import { isApiError } from './api/api-error'

/**
 * Puts each field error from a 422 answer under its field (the API's field names are
 * the form's field names). Returns false when the error is not about the form's fields,
 * so the caller shows it another way (a toast or an alert).
 *
 * Server messages arrive already translated; they are marked `type: 'server'` so the
 * fields show them as they are, while client-side (Zod) messages are i18n keys.
 */
export function showServerErrors<T extends FieldValues>(
  setError: UseFormSetError<T>,
  error: unknown,
  fields: readonly FieldPath<T>[],
): boolean {
  if (!isApiError(error) || error.status !== 422) return false

  const rejected = fields.filter((field) => error.fieldError(field))
  rejected.forEach((field, index) =>
    setError(
      field,
      { type: 'server', message: error.fieldError(field) },
      { shouldFocus: index === 0 },
    ),
  )
  return rejected.length > 0
}

/**
 * What to tell the User about a failed request. Refusals the API explains on purpose
 * (403, 409, 422, 429) arrive in the interface language and are shown as they are;
 * anything else gets the message for its error code.
 */
export function apiErrorMessage(error: unknown): string {
  if (isApiError(error) && [403, 409, 422, 429].includes(error.status) && error.message) {
    return error.message
  }
  const code = isApiError(error) ? error.code : 'unknown'
  return i18n.t(`errors:${code}`, { defaultValue: i18n.t('errors:unknown') })
}
