/* =======================================
 * クラブダンディー 店舗エントランスページ
 * URL: /src/app/club_dandy/page.tsx
 * Referenced in: /src/data/AreaShopData.ts
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */

import type { Metadata } from 'next';
import Entrance from '@/components/entrance/Entrance';
import styles from '@/components/entrance/Entrance.module.scss';

export const metadata: Metadata = {
  title: 'クラブダンディー エントランス',
  description:
    'クラブダンディーの店舗TOPへ進むための年齢確認ページです。18歳以上の方のみご利用ください。',
};

export default function ClubDandyEntrancePage() {
  return (
    <>
      <h1 className={styles.itemH1}>クラブダンディー エントランスページ</h1>
      <Entrance
        scope="club_dandy"
        backPath="/club_dandy/top/"
        excludeStoreId="club_dandy"
      />
    </>
  );
}
