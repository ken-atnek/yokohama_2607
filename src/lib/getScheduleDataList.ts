/* =======================================
 * 指定された「日本時間の基準時刻」で日付切り替えした日付リストを返す
 * URL:src/lib/getScheduleDataList.ts
 * Referenced in: src/components/Shop/schedule/CastScheduleByPeriod.tsx
 * Created: 2025-09-01
 * Last updated: 2025-09-01
 * ======================================= */

/**
 * 指定された「日本時間の基準時刻」で日付切り替えを行い、
 * 指定された日数分の日付リスト（YYYY-MM-DD形式）を返す
 * @param hour 日付切り替え時刻（例：0=0時, 2=午前2時）
 * @param days 日数（デフォルトは3日）
 * @returns YYYY-MM-DD 形式の日付文字列配列
 */

export const getDateList = (hour = 0, days = 3): string[] => {
  const now = new Date();

  // 現在の日本時間を取得（ローカル環境に依存しないよう UTCから作る）
  const utc = now.getTime() + 9 * 60 * 60 * 1000;
  const japanTime = new Date(utc);

  const japanYear = japanTime.getUTCFullYear();
  const japanMonth = japanTime.getUTCMonth();
  const japanDate = japanTime.getUTCDate();
  const japanHour = japanTime.getUTCHours(); // ← JSTとして正しく機能

  // 指定時刻未満なら前日扱い
  const baseDate = new Date(Date.UTC(japanYear, japanMonth, japanDate));
  if (japanHour < hour) {
    baseDate.setUTCDate(baseDate.getUTCDate() - 1);
  }

  // 日付リストを生成
  return Array.from({ length: days }, (_, i) => {
    const d = new Date(baseDate);
    d.setUTCDate(d.getUTCDate() + i);
    return d.toISOString().slice(0, 10);
  });
};
