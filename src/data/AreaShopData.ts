/* =======================================
 * 店舗データ
 * URL:src/data/AreaShopData.ts
 * Created: 2025-08-19
 * Last updated: 2025-08-19
 * ======================================= */

import LogoYokohama from '/public/images/logo/logo-y-dandy.svg';
import LogoMr from '/public/images/logo/logo-mr-dandy.svg';
import LogoClub from '/public/images/logo/logo-club-dandy.svg';
import { StaticImageData } from 'next/image';

export type Shop = {
  storeId: string;
  name: string;
  nameEn: string;
  post: string;
  address: string;
  logo: StaticImageData;
  phone: string;
  url: string;
  shopColor?: string;
  mapUrl?: string;
};

export const Shops = [
  {
    storeId: 'dandy',
    name: '横浜 ダンディー',
    nameEn: 'yokohama dandy',
    logo: LogoYokohama,
    post: '〒231-0057',
    address: '神奈川県横浜市中区曙町3丁目32-17 楽園ビル 6F',
    phone: '045-242-8030',
    url: '/dandy/top/',
    shopColor: '#e776ad',
    mapUrl: 'https://maps.app.goo.gl/4dH2P9VdYTJLfZbw7',
  },
  {
    storeId: 'mr_dandy',
    name: 'ミスター ダンディー',
    nameEn: 'mr dandy',
    logo: LogoMr,
    post: '〒231-0055',
    address: '奈川県横浜市中区末吉町3丁目45番地 川村ビル 2F',
    phone: '045-243-9555',
    url: '/mr_dandy/top/',
    shopColor: '#FF9900',
    mapUrl: 'https://maps.app.goo.gl/RNUGBDKCy786HKpb9',
  },
  {
    storeId: 'club_dandy',
    name: 'クラブ ダンディー',
    nameEn: 'club dandy',
    logo: LogoClub,
    post: '〒231-0057',
    address: '神奈川県横浜市中区曙町2丁目25番地 中山ビル 1階',
    phone: '045-243-2468',
    url: '/club_dandy/top/',
    shopColor: '#00CC33',
    mapUrl: 'https://maps.app.goo.gl/uWVi9wRDDHJgjhRd6',
  },
];
