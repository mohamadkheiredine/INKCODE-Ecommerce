/**
 * What the API accepts as a logo (`App\Support\LogoFile`): a PNG, JPEG or WebP image of
 * at most 1 MB. Checked here too, so a wrong file is refused before it is uploaded.
 */
export const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'] as const
export const LOGO_MAX_BYTES = 1024 * 1024

export const isLogoType = (file: File): boolean =>
  (LOGO_TYPES as readonly string[]).includes(file.type)

export const isLogoSize = (file: File): boolean => file.size <= LOGO_MAX_BYTES
