/* =======================================
 * 神戸ホットポイント メニュー項目
 * URL: src/data/hot/navMenuData.ts
 * Created: 2025-08-21
 * Last updated: 2025-09-15
 * ======================================= */
export const navMenu = [
  { id: 'navTop', href: '/hot/top/', label: 'トップ', labelEn: 'top' },
  {
    id: 'navReserve',
    href: '#',
    // href: 'https://www.cityheaven.net/hyogo/A2802/A280201/koubehp/S6ShopReservation/?pcmode=sp',
    label: 'ネット予約',
    labelEn: 'reservation',
    target: true,
  },
  {
    id: 'navRealTime',
    // href: '/hot/realtime/',
    href: '#',
    label: 'リアルタイム',
    labelEn: 'real time info ',
  },
  {
    id: 'navSchedule',
    // href: '/hot/schedule/',
    href: '#',
    label: '出勤情報',
    labelEn: 'schedule',
  },
  {
    id: 'navCastList',
    // href: '/hot/cast/',
    href: '#',
    label: '在籍一覧',
    labelEn: 'cast list',
  },
  {
    id: 'navPhotoBlog',
    // href: 'https://www.cityheaven.net/hyogo/A2802/A280201/koubehp/diarylist/?of=y',
    href: '#',
    label: '写メ日記',
    labelEn: 'blog',
    target: true,
  },
  {
    id: 'navEvent',
    // href: 'https://www.cityheaven.net/hyogo/A2802/A280201/koubehp/shopevent/',
    href: '#',
    label: 'イベント',
    labelEn: 'event',
    target: true,
  },
  {
    id: 'navSystem',
    // href: '/hot/system/',
    href: '#',
    label: 'システム',
    labelEn: 'system',
  },
  {
    id: 'navRecruit',
    // href: 'https://kobe-baito.jp/',
    href: '#',
    label: '求人情報',
    labelEn: 'job offer',
    target: true,
  },
];
