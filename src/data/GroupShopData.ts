/* =======================================
 * グループショップ DATA
 * URL:src/data/GroupShopData.ts
 * Created: 2025-08-19
 * Last updated: 2025-08-19
 * ======================================= */

export type Shop = {
  storeId: string;
  name: string;
  area: string;
  url: string;
};

export const GroupShops = [
  /* === [ 横浜エリア ] ================= */
  {
    storeId: 'yh_club',
    name: 'クラブダンディ',
    area: 'yokohama',
    url: 'https://www.dandy-g.jp/club_dandy/top.html',
  },
  {
    storeId: 'yh_dandy',
    name: '横浜ダンディ',
    area: 'yokohama',
    url: 'https://www.dandy-g.jp/dandy/top.html',
  },
  {
    storeId: 'yh_mr',
    name: 'ミスターダンディ',
    area: 'yokohama',
    url: 'https://www.dandy-g.jp/mr_dandy/top.html',
  },

  /* === [ 京都エリア ] ================= */
  {
    storeId: 'kt_hot',
    name: '京都ホットポイント',
    area: 'kyoto',
    url: 'https://www.hot-point.co.jp/hot/top.html',
  },
  {
    storeId: 'kt_part2',
    name: 'ホットポイントパート2',
    area: 'kyoto',
    url: 'https://www.hot-point.co.jp/hot2/top.html',
  },
  {
    storeId: 'kt_lip',
    name: 'リップスティック',
    area: 'kyoto',
    url: 'https://www.hot-point.co.jp/lip/top.html',
  },
  {
    storeId: 'kt_villa',
    name: 'ホットポイントヴィラ',
    area: 'kyoto',
    url: 'https://www.hot-point.co.jp/villa/top.html',
  },

  /* === [ 神戸エリア ] ================= */
  {
    storeId: 'kb_hot',
    name: '神戸ホットポイント',
    area: 'kobe',
    url: 'http://www.hpg-kobe.jp/hot/',
  },
  {
    storeId: 'kb_style',
    name: 'ホットポイントスタイル',
    area: 'kobe',
    url: 'http://www.hpg-kobe.jp/style/',
  },
  {
    storeId: 'kb_villa',
    name: 'ホットポイントヴィラ',
    area: 'kobe',
    url: 'http://www.hpg-kobe.jp/villa/',
  },

  /* === [ 福岡エリア ] ================= */
  {
    storeId: 'fu_hot',
    name: '福岡ホットポイント',
    area: 'fukuoka',
    url: 'https://www.fukuoka-hotpoint.jp/hot/top.html',
  },
  {
    storeId: 'fu_villa',
    name: 'ホットポイントヴィラ',
    area: 'fukuoka',
    url: 'https://www.fukuoka-hotpoint.jp/villa/top.html',
  },
  /* === [ 熊本エリア ] ================= */
  {
    storeId: 'km_hot',
    name: '熊本ホットポイント',
    area: 'kumamoto',
    url: 'https://www.kumamoto-hotpoint.cc/top.html',
  },
  {
    storeId: 'km_villa',
    name: 'ホットポイントヴィラ',
    area: 'kumamoto',
    url: 'https://www.hotpoint-villa.cc/top.html',
  },
];
