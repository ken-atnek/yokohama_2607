/* =======================================
 * クラブダンディー リアルタイムページ
 * URL: /src/app/club_dandy/realtime/page.tsx
 * Referenced in: /src/components/club_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */
import LayoutWrapperSub from '@/components/club_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import ContainerRealtime from '@/components/common/realtime/ContainerRealTime';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/club_dandy/realtime/', {
  title: '横浜 風俗(ヘルス) リアルタイム｜クラブダンディー(関内・曙町)',
  description: isRealProduction
    ? 'クラブダンディーのリアルタイム情報ページです。現在のご案内状況や出勤中キャストの最新情報を掲載しています。'
    : undefined,
});

export default function ClubDandyRealTimePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle
        titleJp="リアルタイム"
        titleEn="realtime"
        shop="club_dandy"
      />
      <ContainerRealtime />
    </LayoutWrapperSub>
  );
}
