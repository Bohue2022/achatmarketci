export type Role = 'user' | 'pro' | 'moderator' | 'admin'

export interface User {
  id: number
  name: string
  email: string
  phone: string
  role: Role
  is_verified_pro: boolean
  company_name?: string | null
  plan?: string | null
  active_announcement_limit?: number | null
  is_moderator?: boolean
  email_verified?: boolean
  bio?: string | null
  whatsapp?: string | null
  rccm_number?: string | null
  city_id?: number | null
  city?: string | null
  avatar?: string | null
}

export interface Photo {
  id: number
  url: string
  is_cover: boolean
  position: number
}

export interface Seller {
  id: number
  name: string
  role: Role
  is_verified_pro: boolean
  company_name?: string | null
  phone?: string | null
  whatsapp?: string | null
  city?: string | null
}

export interface Announcement {
  id: number
  slug: string
  title: string
  full_title: string
  description: string
  price: number
  price_formatted: string
  currency: string
  year: number | null
  mileage: number | null
  fuel_type: string | null
  transmission: string | null
  condition: string | null
  body_type: string | null
  is_dedouane: boolean
  has_grise: boolean
  origin: string | null
  status: string
  rejection_reason?: string | null
  featured: boolean
  boosted: boolean
  views_count: number
  contacts_count: number
  published_at?: string | null
  expires_at?: string | null
  created_at?: string | null
  brand?: { id: number; name: string } | null
  model?: { id: number; name: string } | null
  city?: { id: number; name: string } | null
  commune?: { id: number; name: string } | null
  photos: Photo[]
  seller?: Seller | null
}

export interface City {
  id: number
  name: string
  slug: string
  latitude?: string
  longitude?: string
  communes: City[]
}

export interface Brand {
  id: number
  name: string
  slug: string
  models: { id: number; name: string }[]
}

export interface LoginResponse {
  user: User
  token: string
}

export interface ChatMessage {
  id: number
  body: string
  sender_id: number
  is_mine: boolean
  read_at?: string | null
  created_at?: string | null
}

export interface Conversation {
  id: number
  updated_at?: string | null
  unread_count: number
  other_party: {
    id: number
    name: string
    role: Role
    is_verified_pro: boolean
    company_name?: string | null
  } | null
  announcement?: {
    id: number
    slug: string
    title: string
    price_formatted: string
    cover?: string | null
  } | null
  last_message?: {
    body: string
    sender_id: number
    created_at?: string | null
  } | null
  messages?: ChatMessage[]
}

export interface PublicUserProfile {
  id: number
  name: string
  role: Role
  is_verified_pro: boolean
  company_name?: string | null
  city?: string | null
  bio?: string | null
  avatar?: string | null
  member_since?: string | null
  published_announcements_count: number
  contact?: { phone?: string | null; whatsapp?: string | null } | null
  published_announcements?: {
    id: number
    slug: string
    title: string
    price_formatted: string
    city?: string | null
    cover?: string | null
  }[]
}

export interface Paginated<T> {
  data: T[]
  meta?: { current_page: number; last_page: number; total: number }
}
