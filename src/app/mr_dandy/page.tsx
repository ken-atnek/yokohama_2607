/* =======================================
 * ミスターダンディー 店舗エントランスページ
 * URL: /src/app/mr_dandy/page.tsx
 * Referenced in: /src/data/AreaShopData.ts
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */

import type { Metadata } from 'next';
import Entrance from '@/components/entrance/Entrance';
import styles from '@/components/entrance/Entrance.module.scss';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/mr_dandy/', {
  title: '横浜 風俗(ヘルス)｜ミスターダンディー(関内・末吉町)',
  description:
    'ミスターダンディーの店舗TOPへ進むための年齢確認ページです。18歳以上の方のみご利用ください。',
});

export default function MrDandyEntrancePage() {
  return (
    <>
      <h1 className={styles.itemH1}>ミスターダンディー エントランスページ</h1>
      <Entrance
        scope="mr_dandy"
        backPath="/mr_dandy/top/"
        excludeStoreId="mr_dandy"
      />
    </>
  );
}
