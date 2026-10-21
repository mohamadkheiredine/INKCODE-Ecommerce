import { isAxiosError } from 'axios'

/** Stable error codes returned by the API envelope, plus client-side ones. */
export type ApiErrorCode =
  | 'unauthenticated'
  | 'forbidden'
  | 'not_found'
  | 'method_not_allowed'
  | 'conflict'
  | 'csrf_token_mismatch'
  | 'validation_failed'
  | 'too_many_requests'
  | 'server_error'
  | 'network_error'
  | (string & {})

export type FieldErrors = Record<string, string[]>

/** Extra facts some refusals carry (for example `lines` lacking stock). */
export type ErrorDetails = Record<string, unknown>

type ErrorEnvelope = {
  message?: string
  code?: string
  errors?: FieldErrors
  details?: ErrorDetails
}

/** Every failed API call surfaces as one of these, never as a raw Axios error. */
export class ApiError extends Error {
  readonly status: number
  readonly code: ApiErrorCode
  readonly fieldErrors: FieldErrors
  readonly retryAfterSeconds: number | null
  readonly details: ErrorDetails

  constructor(
    status: number,
    code: ApiErrorCode,
    message: string,
    fieldErrors: FieldErrors = {},
    retryAfterSeconds: number | null = null,
    details: ErrorDetails = {},
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fieldErrors = fieldErrors
    this.retryAfterSeconds = retryAfterSeconds
    this.details = details
  }

  /**
   * First server message for a field, if the API rejected it: a validation error, or a
   * business refusal that names the field in `details.field` (`coupon_invalid`).
   */
  fieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0] ?? (this.details.field === field ? this.message : undefined)
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error

  if (isAxiosError<ErrorEnvelope>(error)) {
    if (!error.response) return new ApiError(0, 'network_error', error.message)

    const { status, data, headers } = error.response
    const retryAfter = Number(headers['retry-after'])

    return new ApiError(
      status,
      data?.code ?? (status >= 500 ? 'server_error' : 'http_error'),
      data?.message ?? error.message,
      data?.errors ?? {},
      Number.isFinite(retryAfter) ? retryAfter : null,
      data?.details ?? {},
    )
  }

  return new ApiError(0, 'unknown', error instanceof Error ? error.message : String(error))
}
