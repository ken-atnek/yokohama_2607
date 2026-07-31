/* =======================================
 * ミスターダンディー リアルタイムページ
 * URL: /src/app/mr_dandy/realtime/page.tsx
 * Referenced in: /src/components/mr_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import LayoutWrapperSub from '@/components/mr_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import ContainerRealtime from '@/components/common/realtime/ContainerRealTime';

export const metadata: Metadata = {
  title: 'ミスターダンディー リアルタイム',
  description: isRealProduction
    ? 'ミスターダンディーのリアルタイム情報ページです。現在のご案内状況や出勤中キャストの最新情報を掲載しています。'
    : undefined,
};

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
