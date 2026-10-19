import { Fragment, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, type UIMatch, useMatches } from 'react-router'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import type { PageKey, RouteHandle } from '@/lib/navigation'

type Crumb = { page: PageKey; to: string }

/** The trail of named routes down to the current page, from each route's `handle.crumb`. */
function useCrumbs(): Crumb[] {
  const matches = useMatches() as UIMatch<unknown, RouteHandle | undefined>[]
  return matches.flatMap((match) =>
    match.handle?.crumb ? [{ page: match.handle.crumb, to: match.pathname }] : [],
  )
}

/** Breadcrumbs on every page, with a way back up at every screen size. */
export function Breadcrumbs() {
  const { t } = useTranslation()
  const crumbs = useCrumbs()
  const current = crumbs.at(-1)

  // The browser tab names the page too.
  const title = current ? `${t(`pages.${current.page}`)} · ${t('appName')}` : t('appName')
  useEffect(() => {
    document.title = title
  }, [title])

  return (
    <Breadcrumb aria-label={t('shell.breadcrumbs')} className="min-w-0">
      <BreadcrumbList className="flex-nowrap">
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1
          const label = t(`pages.${crumb.page}`)
          return (
            <Fragment key={crumb.to}>
              {index > 0 && <BreadcrumbSeparator />}
              <BreadcrumbItem className={isLast ? 'min-w-0' : 'shrink-0'}>
                {isLast ? (
                  <BreadcrumbPage className="truncate font-medium">{label}</BreadcrumbPage>
                ) : (
                  <BreadcrumbLink asChild>
                    <Link to={crumb.to}>{label}</Link>
                  </BreadcrumbLink>
                )}
              </BreadcrumbItem>
            </Fragment>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
}
