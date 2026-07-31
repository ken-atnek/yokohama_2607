/* =======================================
 * ミスターダンディー キャスト詳細ページ
 * URL: /src/app/mr_dandy/profile/page.tsx
 * Referenced in: /src/components/common/cast/ShopItemCastList.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import { Suspense } from 'react';
import LayoutWrapperSub from '@/components/mr_dandy/LayoutWrapperSub';
import CastProfile from '@/components/common/profile/CastProfile';

export default function MrDandyProfilePage() {
  return (
    <LayoutWrapperSub>
      <Suspense fallback={<div />}>
        <CastProfile />
      </Suspense>
    </LayoutWrapperSub>
  );
}
