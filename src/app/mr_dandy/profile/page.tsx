/* =======================================
 * ミスターダンディー キャスト詳細ページ
 * URL: /src/app/mr_dandy/profile/page.tsx
 * Referenced in: /src/components/common/cast/ShopItemCastList.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */
import type { Metadata } from 'next';
import { Suspense } from 'react';
import LayoutWrapperSub from '@/components/mr_dandy/LayoutWrapperSub';
import CastProfile from '@/components/common/profile/CastProfile';

export const metadata: Metadata = {
  title: '横浜 風俗(ヘルス) キャスト詳細｜ミスターダンディー(関内・末吉町)',
  description:
    'ミスターダンディーのキャスト詳細ページです。プロフィール、出勤予定、リアルタイム情報を掲載しています。',
  robots: 'index, follow',
};

export default function MrDandyProfilePage() {
  return (
    <LayoutWrapperSub>
      <Suspense fallback={<div />}>
        <CastProfile />
      </Suspense>
    </LayoutWrapperSub>
  );
}
