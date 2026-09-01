/* =======================================
 * 横浜ダンディー 在籍一覧ページ
 * URL: /src/app/dandy/cast/page.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperSub.tsx
 * Created: 2025-09-18
 * Last updated: 2026-08-11
 * ======================================= */
import LayoutWrapperSub from '@/components/dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastList from '@/components/common/cast/ShopCastList';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/dandy/cast/', {
  title: '横浜 風俗(ヘルス) 在籍一覧｜横浜ダンディー(関内・曙町)',
  description: isRealProduction
    ? '横浜ダンディーの在籍一覧ページです。店舗在籍キャストのプロフィール情報を掲載しています。'
    : undefined,
});

export default function DandyCastPage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="在籍一覧" titleEn="cast list" shop="dandy" />
      <CastList />
    </LayoutWrapperSub>
  );
}
