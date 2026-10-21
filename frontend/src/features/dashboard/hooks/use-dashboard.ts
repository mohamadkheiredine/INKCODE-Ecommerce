import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { dashboardApi, dashboardKeys } from '../api/dashboard-api'
import type { DateRange } from '../types'

export function useDashboard(range: DateRange) {
  return useQuery({
    queryKey: dashboardKeys.show(range),
    queryFn: () => dashboardApi.show(range),
    placeholderData: keepPreviousData,
  })
}
