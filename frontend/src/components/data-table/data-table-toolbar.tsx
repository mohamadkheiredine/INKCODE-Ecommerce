import { SearchIcon, XIcon } from 'lucide-react'
import { type ReactNode, useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from '@/components/ui/input-group'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import type { ListQueryControls } from '@/hooks/use-list-query'

/** How long typing pauses before the list searches. */
export const SEARCH_DELAY_MS = 300

/** The row above a list: its search box and filters, with "Clear filters" once narrowed. */
export function DataTableToolbar({
  list,
  children,
}: {
  list: ListQueryControls
  children?: ReactNode
}) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      {children}
      {list.isFiltered && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={list.clearFilters}
          className="gap-1.5 self-start sm:self-auto"
        >
          <XIcon className="size-4" aria-hidden="true" />
          {t('dataTable.clearFilters')}
        </Button>
      )}
    </div>
  )
}

/**
 * Searches as the User types (after a short pause). The box follows the URL, so back,
 * forward and "Clear filters" update what it shows.
 */
export function DataTableSearch({ list, label }: { list: ListQueryControls; label: string }) {
  const { t } = useTranslation()
  const applied = list.query.search
  const [value, setValue] = useState(applied)
  const [previousApplied, setPreviousApplied] = useState(applied)
  const { setSearch } = list

  // The URL changed from outside the box (back, forward, "Clear filters"): show it.
  if (previousApplied !== applied) {
    setPreviousApplied(applied)
    if (value.trim() !== applied) setValue(applied)
  }

  useEffect(() => {
    if (value.trim() === applied) return
    const timer = window.setTimeout(() => setSearch(value), SEARCH_DELAY_MS)
    return () => window.clearTimeout(timer)
  }, [value, applied, setSearch])

  return (
    <InputGroup className="h-9 rounded-lg bg-card sm:w-72">
      <InputGroupAddon>
        <SearchIcon aria-hidden="true" />
      </InputGroupAddon>
      <InputGroupInput
        type="search"
        aria-label={label}
        placeholder={label}
        value={value}
        maxLength={100}
        onChange={(event) => setValue(event.target.value)}
      />
      {value && (
        <InputGroupAddon align="inline-end">
          <InputGroupButton
            size="icon-xs"
            aria-label={t('dataTable.clearSearch')}
            onClick={() => {
              setValue('')
              setSearch('')
            }}
          >
            <XIcon aria-hidden="true" />
          </InputGroupButton>
        </InputGroupAddon>
      )}
    </InputGroup>
  )
}

/** One filter as a native select: "All" plus the allowed values, kept in the URL. */
export function DataTableFilter({
  list,
  name,
  label,
  allLabel,
  options,
}: {
  list: ListQueryControls
  name: string
  label: string
  allLabel: string
  options: { value: string; label: string }[]
}) {
  return (
    <NativeSelect
      aria-label={label}
      value={list.query.filters[name] ?? ''}
      onChange={(event) => list.setFilter(name, event.target.value || null)}
      className="min-w-40"
      size="default"
    >
      <NativeSelectOption value="">{allLabel}</NativeSelectOption>
      {options.map((option) => (
        <NativeSelectOption key={option.value} value={option.value}>
          {option.label}
        </NativeSelectOption>
      ))}
    </NativeSelect>
  )
}
