const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://127.0.0.1:8000/api'

import type { LoginResponse, User } from './types'

export class ApiError extends Error {
  status: number
  data: any

  constructor(status: number, data: any) {
    super(typeof data === 'string' ? data : data?.message ?? 'Erreur serveur')
    this.status = status
    this.data = data
  }
}

/** Récupère le token d'authentification stocké côté client. */
export function getToken(): string | null {
  if (typeof window === 'undefined') return null
  return window.localStorage.getItem('token')
}

export function setSession(token: string): void {
  window.localStorage.setItem('token', token)
}

export function clearSession(): void {
  window.localStorage.removeItem('token')
}

export function isLoggedIn(): boolean {
  return Boolean(getToken())
}

async function parse<T = unknown>(res: Response): Promise<T> {
  let data: any = null
  const text = await res.text()
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = { message: text }
    }
  }
  if (!res.ok) throw new ApiError(res.status, data)
  return data as T
}

interface RequestOptions {
  headers?: Record<string, string>
  auth?: boolean
}

export async function api<T = unknown>(
  path: string,
  options: RequestInit & RequestOptions = {},
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
  }

  // FormData : laisser le navigateur poser le Content-Type (avec boundary).
  // Tous les autres corps (JSON stringifié, objets…) utilisent application/json.
  if (options.body && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
  }
  if (options.headers) Object.assign(headers, options.headers)

  if (options.auth !== false) {
    const token = getToken()
    if (token) headers['Authorization'] = `Bearer ${token}`
  }

  const res = await fetch(`${API_URL}${path}`, { ...options, headers })
  return parse<T>(res)
}

export async function login(email: string, password: string) {
  return api<LoginResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
    auth: false,
  })
}

export async function register(payload: Record<string, unknown>) {
  return api<{ user: User; dev_code?: string }>('/auth/register', {
    method: 'POST',
    body: JSON.stringify(payload),
    auth: false,
  })
}

export async function verifyOtp(email: string, otp: string) {
  return api<LoginResponse>('/auth/verify-otp', {
    method: 'POST',
    body: JSON.stringify({ email, otp }),
    auth: false,
  })
}

export async function resendOtp(email: string) {
  return api<{ message: string; dev_code?: string }>('/auth/resend-otp', {
    method: 'POST',
    body: JSON.stringify({ email }),
    auth: false,
  })
}

export async function fetchMe() {
  return api<{ user: User }>('/auth/me')
}