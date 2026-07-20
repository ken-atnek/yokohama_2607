/* =======================================
 * 横浜ダンディーグループ リアルタイム共通ユーティリティ
 * URL: /src/lib/realtime.ts
 * Referenced in: /src/components/common/realtime/ContainerRealTime.tsx
 * Created: 2026-07-20
 * Last updated: 2026-07-20
 * ======================================= */

export type RealTimeData = {
  realTimeStatus: number;
  realTimeUpdatedAt?: string;
  realTimeDetail: string;
  realTimeComment: string;
  availableFrom?: string;
};

export const REALTIME_STATUS_LABELS: Record<number, string> = {
  1: '現在受付中',
  2: '残りわずか',
  3: '残り1枠 電話にてお問い合わせください',
  4: 'キャンセル待ち',
  5: '予約受付中',
  6: '受付終了、次回のご予約受付致します',
};

export function normalizeRealTimeData(
  source: Partial<RealTimeData> & Record<string, unknown>
): RealTimeData {
  return {
    realTimeStatus:
      typeof source.realTimeStatus === 'number' ? source.realTimeStatus : 0,
    realTimeUpdatedAt:
      typeof source.realTimeUpdatedAt === 'string'
        ? source.realTimeUpdatedAt
        : undefined,
    realTimeDetail:
      typeof source.realTimeDetail === 'string' ? source.realTimeDetail : '',
    realTimeComment:
      typeof source.realTimeComment === 'string' ? source.realTimeComment : '',
    availableFrom:
      typeof source.availableFrom === 'string' && source.availableFrom !== ''
        ? source.availableFrom
        : undefined,
  };
}

export function formatRealtimeTime(value?: string): string {
  if (!value) {
    return '';
  }

  return new Date(value).toLocaleTimeString('ja-JP', {
    hour: '2-digit',
    minute: '2-digit',
  });
}
