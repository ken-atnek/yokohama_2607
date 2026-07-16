/* =======================================
 * 横浜ダンディー キャスト詳細ページ
 * URL: /src/app/dandy/profile/page.tsx
 * Referenced in: /src/components/common/ShopItemCastList.tsx
 * Created: 2025-09-06
 * Last updated: 2026-07-16
 * ======================================= */
import { Suspense } from 'react';
import LayoutWrapperSub from '@/components/dandy/LayoutWrapperSub';
import CastProfile from '@/components/common/profile/CastProfile';

export default function DandyProfilePage() {
  return (
    <LayoutWrapperSub>
      <Suspense fallback={<div />}>
        <CastProfile />
      </Suspense>
    </LayoutWrapperSub>
  );
}
