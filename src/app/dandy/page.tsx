/* =======================================
 * 横浜ダンディー 店舗エントランスページ
 * URL: /src/app/dandy/page.tsx
 * Referenced in: /src/data/AreaShopData.ts
 * Created: 2026-07-13
 * Last updated: 2026-07-13
 * ======================================= */

import type { Metadata } from 'next';
import Entrance from '@/components/entrance/Entrance';
import styles from '@/components/entrance/Entrance.module.scss';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/dandy/', {
  title: '横浜ダンディー エントランス',
  description:
    '横浜ダンディーの店舗TOPへ進むための年齢確認ページです。18歳以上の方のみご利用ください。',
});

export default function DandyEntrancePage() {
  return (
    <>
      <h1 className={styles.itemH1}>横浜ダンディー エントランスページ</h1>
      <Entrance scope="dandy" backPath="/dandy/top/" excludeStoreId="dandy" />
    </>
  );
}
