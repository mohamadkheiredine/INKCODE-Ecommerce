import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { couponKeys, couponsApi } from '../api/coupons-api'
import type { CouponInput } from '../types'

/** A page of Coupons; the previous page stays on screen while the next one loads. */
export function useCoupons(query: ListQuery) {
  return useQuery({
    queryKey: couponKeys.list(query),
    queryFn: () => couponsApi.list(query),
    placeholderData: keepPreviousData,
  })
}

export function useCoupon(id: number) {
  return useQuery({ queryKey: couponKeys.detail(id), queryFn: () => couponsApi.get(id) })
}

/** The Orders and Customers that used a Coupon. */
export function useCouponRedemptions(id: number, query: ListQuery) {
  return useQuery({
    queryKey: couponKeys.redemptions(id, query),
    queryFn: () => couponsApi.redemptions(id, query),
    placeholderData: keepPreviousData,
  })
}

export function useSaveCoupon() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: CouponInput }) =>
      id === null ? couponsApi.create(input) : couponsApi.update(id, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: couponKeys.all }),
  })
}

export function useDeleteCoupon() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => couponsApi.remove(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: couponKeys.all }),
  })
}
