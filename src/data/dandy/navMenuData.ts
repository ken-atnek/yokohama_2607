/* =======================================
 * 横浜ダンディー メニュー項目
 * URL: /src/data/dandy/navMenuData.ts
 * Created: 2025-08-21
 * Last updated: 2026-07-16
 * ======================================= */
export const navMenu = [
  { id: 'navTop', href: '/dandy/top/', label: 'トップ', labelEn: 'top' },
  {
    id: 'navCastList',
    href: '/dandy/cast/',
    label: '在籍一覧',
    labelEn: 'cast list',
  },
  {
    id: 'navRealTime',
    // href: '/dandy/realtime/',
    href: '#',
    label: 'リアルタイム',
    labelEn: 'real time info ',
  },
  {
    id: 'navReserve',
    href: '#',
    // href: '店舗の予約URLを設定',
    label: 'ネット予約',
    labelEn: 'reservation',
    target: true,
  },

  {
    id: 'navSchedule',
    // href: '/dandy/schedule/',
    href: '#',
    label: '出勤情報',
    labelEn: 'schedule',
  },

  {
    id: 'navPhotoBlog',
    // href: '店舗の写メ日記URLを設定',
    href: '#',
    label: '写メ日記',
    labelEn: 'blog',
    target: true,
  },
  {
    id: 'navEvent',
    // href: '店舗のイベントURLを設定',
    href: '#',
    label: 'イベント',
    labelEn: 'event',
    target: true,
  },
  {
    id: 'navSystem',
    // href: '/dandy/system/',
    href: '#',
    label: 'システム',
    labelEn: 'system',
  },
  {
    id: 'navRecruit',
    // href: '店舗の求人URLを設定',
    href: '#',
    label: '求人情報',
    labelEn: 'job offer',
    target: true,
  },
];
