import { FolderTreeIcon, TagIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Every Role may look at the catalog (catalog.view); changing it is gated per action. */
export const categoriesNav: NavEntry = {
  page: 'categories',
  group: 'catalog',
  to: '/catalog/categories',
  icon: FolderTreeIcon,
  permission: 'catalog.view',
}

export const brandsNav: NavEntry = {
  page: 'brands',
  group: 'catalog',
  to: '/catalog/brands',
  icon: TagIcon,
  permission: 'catalog.view',
}
