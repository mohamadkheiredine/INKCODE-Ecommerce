import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { Coupon, CouponInput, CouponRedemption } from '../types'

export const couponKeys = {
  all: ['coupons'] as const,
  lists: () => [...couponKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...couponKeys.lists(), query] as const,
  detail: (id: number) => [...couponKeys.all, 'detail', id] as const,
  redemptions: (id: number, query: ListQuery) =>
    [...couponKeys.all, 'redemptions', id, query] as const,
}

export const couponsApi = {
  async list(query: ListQuery): Promise<ListPage<Coupon>> {
    const { data } = await http.get<ListPage<Coupon>>('/coupons', { params: toApiParams(query) })
    return data
  },

  async get(id: number): Promise<Coupon> {
    const { data } = await http.get<{ data: Coupon }>(`/coupons/${id}`)
    return data.data
  },

  async create(input: CouponInput): Promise<Coupon> {
    const { data } = await http.post<{ data: Coupon }>('/coupons', input)
    return data.data
  },

  async update(id: number, input: CouponInput): Promise<Coupon> {
    const { data } = await http.put<{ data: Coupon }>(`/coupons/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/coupons/${id}`)
  },

  async redemptions(id: number, query: ListQuery): Promise<ListPage<CouponRedemption>> {
    const { data } = await http.get<ListPage<CouponRedemption>>(`/coupons/${id}/redemptions`, {
      params: toApiParams(query),
    })
    return data
  },
}
