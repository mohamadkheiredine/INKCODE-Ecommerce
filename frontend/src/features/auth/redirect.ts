export const SIGN_IN_PATH = '/sign-in'

/**
 * Where to go after signing in. Only same-app paths are accepted, so a crafted
 * `?redirect=` link can never send a User to another site.
 */
export function safeRedirectTarget(candidate: string | null): string {
  if (
    !candidate ||
    !candidate.startsWith('/') ||
    candidate.startsWith('//') ||
    candidate.startsWith('/\\')
  ) {
    return '/'
  }
  return candidate.startsWith(SIGN_IN_PATH) ? '/' : candidate
}

export function signInPathFor(returnTo: string): string {
  return returnTo === '/'
    ? SIGN_IN_PATH
    : `${SIGN_IN_PATH}?redirect=${encodeURIComponent(returnTo)}`
}
