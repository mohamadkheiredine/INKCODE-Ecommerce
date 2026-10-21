import { http } from '@/lib/api/client'
import type { Dashboard, DateRange } from '../types'

export const dashboardKeys = {
  all: ['dashboard'] as const,
  show: (range: DateRange) => [...dashboardKeys.all, range] as const,
}

export const dashboardApi = {
  async show(range: DateRange): Promise<Dashboard> {
    const { data } = await http.get<{ data: Dashboard }>('/dashboard', { params: range })
    return data.data
  },
}
