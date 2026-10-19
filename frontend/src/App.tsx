import { useState } from 'react'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { AppProviders } from '@/app/providers'
import { routes } from '@/app/routes'
import { createAppQueryClient } from '@/app/query-client'

const router = createBrowserRouter(routes)

export default function App() {
  const [queryClient] = useState(createAppQueryClient)

  return (
    <AppProviders queryClient={queryClient}>
      <RouterProvider router={router} />
    </AppProviders>
  )
}
