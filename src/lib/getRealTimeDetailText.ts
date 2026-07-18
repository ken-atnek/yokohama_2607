/* =======================================
 * リアルタイム表示用の補足文言を返すユーティリティ
 * URL: /src/lib/getRealTimeDetailText.ts
 * Referenced in: /src/components/common/schedule/CastScheduleByDay.tsx
 * Created: 2026-07-17
 * Last updated: 2026-07-17
 * ======================================= */

type GetRealTimeDetailTextParams = {
  detail?: string;
  availableFrom?: string;
  now?: Date;
  shouldCompareTime?: boolean;
};

export const getRealTimeDetailText = ({
  detail = '',
  availableFrom = '',
  now = new Date(),
  shouldCompareTime = true,
}: GetRealTimeDetailTextParams) => {
  if (!shouldCompareTime || !availableFrom) return detail;

  const match = availableFrom.match(/^(\d{2}):(\d{2})$/);
  if (!match) return detail;

  const [, hourText, minuteText] = match;
  const availableHour = Number(hourText);
  const availableMinute = Number(minuteText);
  const nowMinutes = now.getHours() * 60 + now.getMinutes();
  const availableMinutes = availableHour * 60 + availableMinute;

  return nowMinutes >= availableMinutes
    ? '待ち時間なし'
    : `${availableFrom}より案内可能`;
};
