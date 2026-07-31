/* =======================================
 * ミスターダンディー 在籍一覧ページ
 * URL: /src/app/mr_dandy/cast/page.tsx
 * Referenced in: /src/components/mr_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import LayoutWrapperSub from '@/components/mr_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastList from '@/components/common/cast/ShopCastList';

export const metadata: Metadata = {
  title: 'ミスターダンディー 在籍一覧',
  description: isRealProduction
    ? 'ミスターダンディーの在籍一覧ページです。店舗在籍キャストのプロフィール情報を掲載しています。'
    : undefined,
};

export default function MrDandyCastPage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="在籍一覧" titleEn="cast list" shop="mr_dandy" />
      <CastList />
    </LayoutWrapperSub>
  );
}
