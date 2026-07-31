/* =======================================
 * クラブダンディー 在籍一覧ページ
 * URL: /src/app/club_dandy/cast/page.tsx
 * Referenced in: /src/components/club_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import LayoutWrapperSub from '@/components/club_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastList from '@/components/common/cast/ShopCastList';

export const metadata: Metadata = {
  title: 'クラブダンディー 在籍一覧',
  description: isRealProduction
    ? 'クラブダンディーの在籍一覧ページです。店舗在籍キャストのプロフィール情報を掲載しています。'
    : undefined,
};

export default function ClubDandyCastPage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="在籍一覧" titleEn="cast list" shop="club_dandy" />
      <CastList />
    </LayoutWrapperSub>
  );
}
