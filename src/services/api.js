/**
 * PakDava API Service
 * ═══════════════════════════════════════════════════════
 * این فایل دقیقاً با API‌های PHP موجود مطابقت دارد:
 *   api/auth.php      → login/logout
 *   api/auth_jwt.php  → JWT token (جدید)
 *   api/data.php      → دریافت تمام داده‌ها
 *   api/sync.php      → ارسال صف آفلاین
 *   api/push_subscribe.php → ثبت push
 */

import { Network } from '@capacitor/network';
import { Preferences } from '@capacitor/preferences';

// ── تنظیم base URL ────────────────────────────────────────────────────────
const getBaseURL = () => {
  // در Capacitor APK: از متغیر ذخیره‌شده استفاده می‌شود
  const stored = window.__PAKDAVA_SERVER_URL__;
  if (stored) return stored.replace(/\/$/, '');
  // fallback
  return 'https://myhealthcare.ir/packdava';
};

// ── Token management ──────────────────────────────────────────────────────
const TokenStore = {
  async get() {
    const { value } = await Preferences.get({ key: 'pd_jwt' });
    return value;
  },
  async set(token) {
    await Preferences.set({ key: 'pd_jwt', value: token });
  },
  async clear() {
    await Preferences.remove({ key: 'pd_jwt' });
  },
};

// ── HTTP helper ───────────────────────────────────────────────────────────
async function apiFetch(path, options = {}) {
  const base  = getBaseURL();
  const url   = `${base}/${path}`;
  const token = await TokenStore.get();

  const headers = {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
    'X-PakDava-Client': 'android-app',
    ...options.headers,
  };

  // اضافه کردن JWT اگر موجود باشد
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const response = await fetch(url, {
    credentials: 'include', // برای session PHP هم کار کند
    ...options,
    headers,
  });

  if (response.status === 401) {
    // token منقضی شده → تلاش برای refresh
    const refreshed = await refreshToken();
    if (refreshed) {
      headers['Authorization'] = `Bearer ${await TokenStore.get()}`;
      return fetch(url, { credentials: 'include', ...options, headers });
    }
    throw new Error('UNAUTHORIZED');
  }

  return response;
}

// ── Auth ──────────────────────────────────────────────────────────────────
export const AuthAPI = {
  async login(username, password, role) {
    // اول JWT امتحان می‌کنیم
    try {
      const res = await apiFetch('api/auth_jwt.php', {
        method: 'POST',
        body: JSON.stringify({ username, password, role, action: 'login' }),
      });
      const data = await res.json();
      if (data.success && data.token) {
        await TokenStore.set(data.token);
        // ذخیره refresh token
        if (data.refresh_token) {
          await Preferences.set({ key: 'pd_refresh', value: data.refresh_token });
        }
        return data;
      }
    } catch {}

    // fallback به session PHP
    const res = await apiFetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ username, password, role, action: 'login_json' }),
    });
    const data = await res.json();
    if (data.success) await TokenStore.set(data.token || '');
    return data;
  },

  async logout() {
    try {
      await apiFetch('api/auth_jwt.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'logout' }),
      });
    } catch {}
    await TokenStore.clear();
    await Preferences.remove({ key: 'pd_refresh' });
  },

  async check() {
    const token = await TokenStore.get();
    if (!token) return null;
    try {
      const res  = await apiFetch('api/auth_jwt.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'verify' }),
      });
      const data = await res.json();
      return data.success ? data.user : null;
    } catch {
      return null;
    }
  },
};

async function refreshToken() {
  try {
    const { value: refresh } = await Preferences.get({ key: 'pd_refresh' });
    if (!refresh) return false;
    const res  = await apiFetch('api/auth_jwt.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'refresh', refresh_token: refresh }),
    });
    const data = await res.json();
    if (data.token) { await TokenStore.set(data.token); return true; }
    return false;
  } catch {
    return false;
  }
}

// ── Data ──────────────────────────────────────────────────────────────────
export const DataAPI = {
  async fetchAll() {
    const res  = await apiFetch('api/data.php');
    return res.json();
  },

  async sync(store, records) {
    const res = await apiFetch('api/sync.php', {
      method: 'POST',
      body:   JSON.stringify({ store, records }),
    });
    return res.json();
  },

  async subscribePush(subscription) {
    const res = await apiFetch('api/push_subscribe.php', {
      method: 'POST',
      body:   JSON.stringify(subscription),
    });
    return res.json();
  },
};

// ── Network status ────────────────────────────────────────────────────────
export const NetworkService = {
  async isOnline() {
    const status = await Network.getStatus();
    return status.connected;
  },

  onChange(callback) {
    return Network.addListener('networkStatusChange', status => {
      callback(status.connected);
    });
  },
};

export { TokenStore };
