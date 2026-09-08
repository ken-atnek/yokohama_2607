/* =======================================
 * 店舗データ
 * URL:src/data/AreaShopData.ts
 * Created: 2025-08-19
 * Last updated: 2026-08-01
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
  noticeMailAddress?: string;
  url: string;
  businessHours: string;
  shopColor?: string;
  mapUrl?: string;
  mapEmbedUrl?: string;
};

export const Shops = [
  {
    storeId: 'club_dandy',
    name: 'クラブ ダンディー',
    nameEn: 'club dandy',
    logo: LogoClub,
    post: '〒231-0057',
    address: '神奈川県横浜市中区曙町２丁目２５番地 中山ビル １階',
    phone: '045-243-2468',
    noticeMailAddress: '',
    url: '/club_dandy/top/',
    businessHours: '6:00～24:00',
    shopColor: '#3cac46',
    mapUrl: 'https://maps.app.goo.gl/h8MwKXEJHBj3Sv5m6',
    mapEmbedUrl:
      'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3250.571252828823!2d139.62598609755258!3d35.440649529476254!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x60185c8cc39a7649%3A0xb416bc1e33a32618!2z44Kv44Op44OW44OA44Oz44OH44Kj44O8!5e0!3m2!1sja!2sjp!4v1785553373916!5m2!1sja!2sjp',
  },
  {
    storeId: 'dandy',
    name: '横浜 ダンディー',
    nameEn: 'yokohama dandy',
    logo: LogoYokohama,
    post: '〒231-0057',
    address: '神奈川県横浜市中区曙町3丁目32-17 楽園ビル 6F',
    phone: '045-242-8030',
    noticeMailAddress: 'workgmail_dy72gu0x@hot-point.jp',
    url: '/dandy/top/',
    businessHours: '6:00～24:00',
    shopColor: '#e776ad',
    mapUrl: 'https://maps.app.goo.gl/fGFH7JUsyEdTEZVm7',
    mapEmbedUrl:
      'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2872.6258587843454!2d139.6244089586518!3d35.44009884705389!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x60185d00768bc0a7%3A0xb3942890b9978374!2z5qiq5rWc44OA44Oz44OH44Kj44O8!5e0!3m2!1sja!2sjp!4v1785553022822!5m2!1sja!2sjp',
  },
  {
    storeId: 'mr_dandy',
    name: 'ミスター ダンディー',
    nameEn: 'mr dandy',
    logo: LogoMr,
    post: '〒231-0055',
    address: '神奈川県横浜市中区末吉町３丁目４５番地 川村ビル 2F',
    phone: '045-243-9555',
    noticeMailAddress: '',
    url: '/mr_dandy/top/',
    businessHours: '6:00～24:00',
    shopColor: '#FF9900',
    mapUrl: 'https://maps.app.goo.gl/5biNPtxodkRBvhGp7',
    mapEmbedUrl:
      'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3250.597827076464!2d139.62139419678954!3d35.43999139999999!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x60185c8c3f664adf%3A0xbd9b60ec194d3e7!2z44Of44K544K_44O844OA44Oz44OH44Kj44O8!5e0!3m2!1sja!2sjp!4v1785553408750!5m2!1sja!2sjp',
  },
];
