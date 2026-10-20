import { PlusIcon, SearchIcon } from 'lucide-react'
import { type ReactNode, useEffect, useState } from 'react'
import { SEARCH_DELAY_MS } from '@/components/data-table/data-table-toolbar'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'

type SearchPickerProps<T> = {
  label: string
  placeholder: string
  noMatches: string
  /** Runs the search (debounced); `search` is '' until the User types. */
  useResults: (search: string) => { data: T[] | undefined; isFetching: boolean }
  getKey: (item: T) => string | number
  /** The accessible name of the button that picks an item. */
  getLabel: (item: T) => string
  renderItem: (item: T) => ReactNode
  onPick: (item: T) => void
}

/** A search box whose matches are buttons: typing searches, clicking picks and clears. */
export function SearchPicker<T>({
  label,
  placeholder,
  noMatches,
  useResults,
  getKey,
  getLabel,
  renderItem,
  onPick,
}: SearchPickerProps<T>) {
  const [value, setValue] = useState('')
  const [search, setSearch] = useState('')
  const results = useResults(search)

  useEffect(() => {
    const timer = window.setTimeout(() => setSearch(value.trim()), SEARCH_DELAY_MS)
    return () => window.clearTimeout(timer)
  }, [value])

  return (
    <div className="flex flex-col gap-2">
      <InputGroup className="h-10 rounded-lg bg-background">
        <InputGroupAddon>
          <SearchIcon aria-hidden="true" />
        </InputGroupAddon>
        <InputGroupInput
          type="search"
          aria-label={label}
          placeholder={placeholder}
          value={value}
          maxLength={100}
          onChange={(event) => setValue(event.target.value)}
        />
        {results.isFetching && (
          <InputGroupAddon align="inline-end">
            <InlineSpinner className="size-4" />
          </InputGroupAddon>
        )}
      </InputGroup>
      {search !== '' && results.data && (
        <ul className="flex flex-col divide-y rounded-xl border bg-card" aria-label={label}>
          {results.data.length === 0 && (
            <li className="p-3 text-sm text-muted-foreground">{noMatches}</li>
          )}
          {results.data.map((item) => (
            <li key={getKey(item)}>
              <button
                type="button"
                className="flex w-full items-center justify-between gap-3 p-3 text-start transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                aria-label={getLabel(item)}
                onClick={() => {
                  onPick(item)
                  setValue('')
                  setSearch('')
                }}
              >
                {renderItem(item)}
                <PlusIcon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
