import { FolderTreeIcon, PackageIcon, SlidersHorizontalIcon, TagIcon } from 'lucide-react'
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

export const productsNav: NavEntry = {
  page: 'products',
  group: 'catalog',
  to: '/catalog/products',
  icon: PackageIcon,
  permission: 'catalog.view',
}

export const attributesNav: NavEntry = {
  page: 'attributes',
  group: 'catalog',
  to: '/catalog/attributes',
  icon: SlidersHorizontalIcon,
  permission: 'catalog.view',
}
