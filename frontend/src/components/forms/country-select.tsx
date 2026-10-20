import type { ComponentProps } from 'react'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { useCountryOptions } from '@/lib/countries'
import { cn } from '@/lib/utils'

type CountrySelectProps = Omit<ComponentProps<typeof NativeSelect>, 'children'> & {
  /** The first, empty choice ("Choose a country", "All countries"). */
  placeholder: string
}

/**
 * Every country by its name in the interface language, sorted that way, as a native
 * select (the phone's own picker on mobile). The value is the ISO code ("LB").
 */
export function CountrySelect({ placeholder, className, ...selectProps }: CountrySelectProps) {
  const options = useCountryOptions()

  return (
    <NativeSelect {...selectProps} className={cn('w-full [&_select]:h-10', className)}>
      <NativeSelectOption value="">{placeholder}</NativeSelectOption>
      {options.map(({ value, label }) => (
        <NativeSelectOption key={value} value={value}>
          {label}
        </NativeSelectOption>
      ))}
    </NativeSelect>
  )
}
