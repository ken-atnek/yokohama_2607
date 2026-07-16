/* =======================================
 * 横浜ダンディーグループ キャストアクセスカウンターAPI
 * URL: /src/lib/accessCastCounterApi.ts
 * Created: 2025-10-08
 * Last updated: 2026-07-16
 * ======================================= */

export async function fetchCastCounter(
  castId: string,
  apiUrl?: string
): Promise<{ success: boolean; count?: number; error?: string }> {
  const url = apiUrl || `/backend/cast_counter.php?cid=${castId}`;
  try {
    const res = await fetch(url);
    //console.log(`キャストカウント開始 ${castId}`);
    if (!res.ok) {
      //console.error(`キャストカウンターAPIエラー (${res.status})`);
      return { success: false, error: `HTTP ${res.status}` };
    }
    const data = await res.text();
    //レスポンスが数値の場合はカウントとして返す
    const count = parseInt(data, 10);
    if (!isNaN(count)) {
      return { success: true, count };
    }
    return { success: true };
  } catch (err) {
    //console.error('fetchCastCounter取得失敗:', err);
    return { success: false, error: err instanceof Error ? err.message : 'Unknown error' };
  }
}
// バックグラウンドでアクセスカウンターを呼び出す関数
export async function trackCastAccess(castId: string): Promise<void> {
  try {
    await fetchCastCounter(castId);
  } catch {
    // バックグラウンド処理なのでエラーは無視
    //console.warn('キャストアクセス追跡エラー:', err);
  }
}
