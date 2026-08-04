/* =======================================
 * 横浜ダンディー リアルタイムページ
 * URL: /src/app/dandy/realtime/page.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperSub.tsx
 * Created: 2025-09-18
 * Last updated: 2026-07-17
 * ======================================= */
import LayoutWrapperSub from '@/components/dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import ContainerRealtime from '@/components/common/realtime/ContainerRealTime';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/dandy/realtime/', {
  title: '横浜ダンディー リアルタイム',
  description: isRealProduction
    ? '横浜ダンディーのリアルタイム情報ページです。現在のご案内状況や出勤中キャストの最新情報を掲載しています。'
    : undefined,
});

export default function DandyRealTimePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="リアルタイム" titleEn="realtime" shop="dandy" />
      <ContainerRealtime />
    </LayoutWrapperSub>
  );
}
