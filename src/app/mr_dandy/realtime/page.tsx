/* =======================================
 * ミスターダンディー リアルタイムページ
 * URL: /src/app/mr_dandy/realtime/page.tsx
 * Referenced in: /src/components/mr_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */
import LayoutWrapperSub from '@/components/mr_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import ContainerRealtime from '@/components/common/realtime/ContainerRealTime';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/mr_dandy/realtime/', {
  title: '横浜 風俗(ヘルス) リアルタイム｜ミスターダンディー(関内・末吉町)',
  description: isRealProduction
    ? 'ミスターダンディーのリアルタイム情報ページです。現在のご案内状況や出勤中キャストの最新情報を掲載しています。'
    : undefined,
});

export default function MrDandyRealTimePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle
        titleJp="リアルタイム"
        titleEn="realtime"
        shop="mr_dandy"
      />
      <ContainerRealtime />
    </LayoutWrapperSub>
  );
}
