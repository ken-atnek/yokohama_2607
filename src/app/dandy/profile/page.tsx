/* =======================================
 * 横浜ダンディー キャスト詳細ページ
 * URL: /src/app/dandy/profile/page.tsx
 * Referenced in: /src/components/common/cast/ShopItemCastList.tsx
 * Created: 2025-09-06
 * Last updated: 2026-08-11
 * ======================================= */
import type { Metadata } from 'next';
import { Suspense } from 'react';
import LayoutWrapperSub from '@/components/dandy/LayoutWrapperSub';
import CastProfile from '@/components/common/profile/CastProfile';

export const metadata: Metadata = {
  title: '横浜 風俗(ヘルス) キャスト詳細｜横浜ダンディー(関内・曙町)',
  description:
    '横浜ダンディーのキャスト詳細ページです。プロフィール、出勤予定、リアルタイム情報を掲載しています。',
  robots: 'index, follow',
};

export default function DandyProfilePage() {
  return (
    <LayoutWrapperSub>
      <Suspense fallback={<div />}>
        <CastProfile />
      </Suspense>
    </LayoutWrapperSub>
  );
}
