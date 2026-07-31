/* =======================================
 * クラブダンディー メニュー項目
 * URL: /src/data/club_dandy/navMenuData.ts
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
export const navMenu = [
  { id: 'navTop', href: '/club_dandy/top/', label: 'トップ', labelEn: 'top' },
  {
    id: 'navCastList',
    href: '/club_dandy/cast/',
    label: '在籍一覧',
    labelEn: 'cast list',
  },
  {
    id: 'navRealTime',
    href: '/club_dandy/realtime/',
    label: 'リアルタイム',
    labelEn: 'real time info ',
  },
  {
    id: 'navSchedule',
    href: '/club_dandy/schedule/',
    label: '出勤情報',
    labelEn: 'schedule',
  },
  {
    id: 'navReserve',
    href: 'https://www.cityheaven.net/kanagawa/A1401/A140103/club-dandy/A6ShopReservation/?of=y',
    label: 'ネット予約',
    labelEn: 'reservation',
    target: true,
  },
  {
    id: 'navPhotoBlog',
    href: 'https://www.cityheaven.net/kanagawa/A1401/A140103/club-dandy/diarylist/',
    label: '写メ日記',
    labelEn: 'blog',
    target: true,
  },
  {
    id: 'navReviews',
    href: 'https://www.cityheaven.net/kanagawa/A1401/A140103/club-dandy/reviews/?of=y',
    label: '口コミ',
    labelEn: 'reviews',
    target: true,
  },
  {
    id: 'navWeeklySchedule',
    href: '/club_dandy/weekly-schedule',
    label: '週間出勤',
    labelEn: 'weekly schedule',
  },
  {
    id: 'navSystem',
    href: '/club_dandy/system/',
    label: 'システム',
    labelEn: 'system',
  },
  {
    id: 'navRecruit',
    href: 'https://kanto.qzin.jp/clubdandy/?v=official',
    label: '女子求人',
    labelEn: 'job offer',
    target: true,
  },
  {
    id: 'navRecruitMen',
    href: 'https://mensheaven.jp/5/club-dandy/',
    label: '男子求人',
    labelEn: 'job offer Men',
    target: true,
  },
];
