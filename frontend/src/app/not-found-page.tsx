import { CompassIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { EmptyState } from '@/components/feedback/empty-state'
import { Button } from '@/components/ui/button'

export function NotFoundPage() {
  const { t } = useTranslation()

  return (
    <EmptyState
      className="mt-6"
      icon={CompassIcon}
      title={t('states.notFoundTitle')}
      description={t('states.notFoundDescription')}
      action={
        <Button asChild>
          <Link to="/">{t('actions.goHome')}</Link>
        </Button>
      }
    />
  )
}
