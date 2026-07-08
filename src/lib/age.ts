/* =======================================
 * 横浜ダンディーグループ 年齢認証保持ユーティリティ
 * URL: /src/lib/age.ts
 * Referenced in: /src/components/entrance/Entrance.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */

const STORAGE_KEY = 'age-ok:group';
const TTL_MS = 1000 * 60 * 60 * 24 * 7;

type AgePayload = {
  verifiedAt: number;
  expiresAt: number;
};

export const setAgeVerified = () => {
  if (typeof window === 'undefined') return;

  const now = Date.now();
  const payload: AgePayload = {
    verifiedAt: now,
    expiresAt: now + TTL_MS,
  };

  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
};

export const isAgeVerified = () => {
  if (typeof window === 'undefined') return false;

  const raw = window.localStorage.getItem(STORAGE_KEY);
  if (!raw) return false;

  try {
    const payload = JSON.parse(raw) as Partial<AgePayload>;
    if (!payload.expiresAt || payload.expiresAt < Date.now()) {
      window.localStorage.removeItem(STORAGE_KEY);
      return false;
    }
    return true;
  } catch {
    window.localStorage.removeItem(STORAGE_KEY);
    return false;
  }
};
