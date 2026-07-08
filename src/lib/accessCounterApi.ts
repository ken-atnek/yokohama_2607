/* =======================================
 * 神戸ホットポイントグループ アクセスカウンターAPI
 * URL: src/lib/accessCounterApi.ts
 * Created: 2025-10-07
 * Last updated: 2025-10-07
 * ======================================= */

export async function fetchCounter(
  shopId: string,
  pageId: string,
  apiUrl?: string
): Promise<{ success: boolean; count?: number; error?: string }> {
  const url = apiUrl || `/backend/page_counter.php?sid=${shopId}&p=${pageId}`;
  try {
    const res = await fetch(url);
    //console.log(`カウント開始 ${shopId}`);
    if (!res.ok) {
      //console.error(`カウンターAPIエラー (${res.status})`);
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
    //console.error('fetchCounter取得失敗:', err);
    return { success: false, error: err instanceof Error ? err.message : 'Unknown error' };
  }
}
// バックグラウンドでアクセスカウンターを呼び出す関数
export async function trackPageAccess(shopId: string, pageId: string): Promise<void> {
  try {
    await fetchCounter(shopId, pageId);
  } catch {
    // バックグラウンド処理なのでエラーは無視
    //console.warn('ページアクセス追跡エラー:', err);
  }
}