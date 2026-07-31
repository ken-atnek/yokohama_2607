/* =======================================
 * クラブダンディー キャスト詳細ページ
 * URL: /src/app/club_dandy/profile/page.tsx
 * Referenced in: /src/components/common/cast/ShopItemCastList.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import { Suspense } from 'react';
import LayoutWrapperSub from '@/components/club_dandy/LayoutWrapperSub';
import CastProfile from '@/components/common/profile/CastProfile';

export default function ClubDandyProfilePage() {
  return (
    <LayoutWrapperSub>
      <Suspense fallback={<div />}>
        <CastProfile />
      </Suspense>
    </LayoutWrapperSub>
  );
}
