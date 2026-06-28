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
  const stored = window.__PAKDAVA_SERVER_URL__;
  if (stored) return stored.replace(/\/$/, '');
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
const DEFAULT_TIMEOUT = 15000; // 15 seconds

async function fetchWithTimeout(url, options = {}, timeout = DEFAULT_TIMEOUT) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);
  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
    });
    clearTimeout(timeoutId);
    return response;
  } catch (error) {
    clearTimeout(timeoutId);
    if (error.name === 'AbortError') {
      throw new Error('REQUEST_TIMEOUT');
    }
    throw error;
  }
}

async function apiFetch(path, options = {}, retries = 2) {
  try {
    const base = getBaseURL();
    const url = `${base}/${path}`;
    const token = await TokenStore.get();

    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-PakDava-Client': 'android-app',
      ...options.headers,
    };

    if (token) headers['Authorization'] = `Bearer ${token}`;

    const response = await fetchWithTimeout(url, {
      credentials: 'include',
      ...options,
      headers,
    });

    if (response.status === 401) {
      const refreshed = await refreshToken();
      if (refreshed) {
        const newToken = await TokenStore.get();
        headers['Authorization'] = `Bearer ${newToken}`;
        return fetchWithTimeout(url, {
          credentials: 'include',
          ...options,
          headers,
        });
      }
      throw new Error('UNAUTHORIZED');
    }

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }

    return response;
  } catch (error) {
    console.error('API Fetch Error:', error);

    if (retries > 0 && error.message !== 'UNAUTHORIZED' && error.message !== 'REQUEST_TIMEOUT') {
      console.warn(`Retrying... (${retries} attempts left)`);
      await new Promise(resolve => setTimeout(resolve, 1000));
      return apiFetch(path, options, retries - 1);
    }

    return new Response(JSON.stringify({
      error: error.message || 'Unknown error',
      offline: true,
      timestamp: new Date().toISOString(),
    }), {
      status: 500,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

// ── Auth ──────────────────────────────────────────────────────────────────
export const AuthAPI = {
  async login(username, password, role) {
    try {
      const res = await apiFetch('api/auth_jwt.php', {
        method: 'POST',
        body: JSON.stringify({ username, password, role, action: 'login' }),
      });
      const data = await res.json();

      if (data.success && data.token) {
        await TokenStore.set(data.token);
        if (data.refresh_token) {
          await Preferences.set({ key: 'pd_refresh', value: data.refresh_token });
        }
        return data;
      }
      throw new Error(data.message || 'Login failed');
    } catch (error) {
      console.error('Login error:', error);
      return {
        success: false,
        message: error.message || 'خطا در ارتباط با سرور',
      };
    }
  },

  async logout() {
    try {
      await apiFetch('api/auth_jwt.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'logout' }),
      });
    } catch (error) {
      console.warn('Logout error:', error);
    }
    await TokenStore.clear();
    await Preferences.remove({ key: 'pd_refresh' });
  },

  async check() {
    const token = await TokenStore.get();
    if (!token) return null;
    try {
      const res = await apiFetch('api/auth_jwt.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'verify' }),
      });
      const data = await res.json();
      return data.success ? data.user : null;
    } catch (error) {
      console.error('Check auth error:', error);
      return null;
    }
  },
};

async function refreshToken() {
  try {
    const { value: refresh } = await Preferences.get({ key: 'pd_refresh' });
    if (!refresh) return false;
    const res = await apiFetch('api/auth_jwt.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'refresh', refresh_token: refresh }),
    });
    const data = await res.json();
    if (data.token) {
      await TokenStore.set(data.token);
      return true;
    }
    return false;
  } catch (error) {
    console.error('Refresh token error:', error);
    return false;
  }
}

// ── Data ──────────────────────────────────────────────────────────────────
export const DataAPI = {
  async fetchAll() {
    try {
      const res = await apiFetch('api/data.php');
      const data = await res.json();
      if (data && data.error) {
        throw new Error(data.error);
      }
      return data;
    } catch (error) {
      console.error('Fetch data error:', error);
      return {
        _meta: {
          error: error.message,
          offline: true,
          timestamp: new Date().toISOString(),
        },
      };
    }
  },

  async sync(store, records) {
    try {
      const res = await apiFetch('api/sync.php', {
        method: 'POST',
        body: JSON.stringify({ store, records }),
      });
      return await res.json();
    } catch (error) {
      console.error('Sync error:', error);
      return {
        success: false,
        synced: 0,
        error: error.message,
      };
    }
  },

  async subscribePush(subscription) {
    try {
      const res = await apiFetch('api/push_subscribe.php', {
        method: 'POST',
        body: JSON.stringify(subscription),
      });
      return await res.json();
    } catch (error) {
      console.error('Push subscribe error:', error);
      return {
        success: false,
        error: error.message,
      };
    }
  },
};

// ── Network status ────────────────────────────────────────────────────────
export const NetworkService = {
  async isOnline() {
    try {
      const status = await Network.getStatus();
      return status.connected;
    } catch {
      return false;
    }
  },

  onChange(callback) {
    return Network.addListener('networkStatusChange', status => {
      callback(status.connected);
    });
  },
};

export { TokenStore };
