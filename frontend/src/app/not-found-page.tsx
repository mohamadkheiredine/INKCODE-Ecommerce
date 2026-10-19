import { CompassIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty'

export function NotFoundPage() {
  const { t } = useTranslation()

  return (
    <div className="grid min-h-svh place-items-center bg-background p-6">
      <Empty>
        <EmptyHeader>
          <EmptyMedia variant="icon" className="bg-primary/10 text-primary">
            <CompassIcon aria-hidden="true" />
          </EmptyMedia>
          <EmptyTitle>{t('states.notFoundTitle')}</EmptyTitle>
          <EmptyDescription>{t('states.notFoundDescription')}</EmptyDescription>
        </EmptyHeader>
        <EmptyContent>
          <Button asChild>
            <Link to="/">{t('actions.goHome')}</Link>
          </Button>
        </EmptyContent>
      </Empty>
    </div>
  )
}
