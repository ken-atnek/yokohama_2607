/* =======================================
 * 横浜ダンディーグループ グループエントランスページ
 * URL: /src/app/page.tsx
 * Referenced in: /src/app/page.tsx
 * Created: 2026-07-08
 * Last updated: 2026-08-11
 * ======================================= */

import type { Metadata } from 'next';
import Entrance from '@/components/entrance/Entrance';
import styles from '@/components/entrance/Entrance.module.scss';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/', {
  title: '横浜 風俗(ヘルス)｜横浜ダンディーグループ',
  description:
    '横浜の風俗店「ダンディーグループ」の女の子紹介や各店舗情報へ進むためのグループエントランスです。',
});

export default function HomePage() {
  return (
    <>
      <h1 className={styles.itemH1}>
        横浜 風俗(ヘルス)｜横浜ダンディーグループ
      </h1>
      <Entrance scope="group" backPath="/top/" />
    </>
  );
}
