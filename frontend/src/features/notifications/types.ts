/**
 * One notification in the User's bell. The API writes the message in the interface
 * language; `link` is the path of the record it is about. `type` picks the icon, and
 * stays a string so a type added by a later module still shows (with the default icon).
 */
export type AppNotification = {
  id: string
  type: string
  message: string
  link: string
  read_at: string | null
  created_at: string
}

export type NotificationPage = {
  data: AppNotification[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    /** Unread across all pages, for the badge. */
    unread_count: number
  }
}
