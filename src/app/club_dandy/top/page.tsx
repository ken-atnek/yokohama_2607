/* =======================================
 * クラブダンディー 店舗TOPページ
 * URL: /src/app/club_dandy/top/page.tsx
 * Referenced in: /src/app/club_dandy/page.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */

import type { Metadata } from 'next';
import ShopTopMain from '@/components/common/top/ShopTopMain';
import LayoutWrapperMain from '@/components/club_dandy/LayoutWrapperMain';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/club_dandy/top/', {
  title: 'クラブダンディー TOP',
  description:
    'クラブダンディーの店舗TOPページです。店舗コンセプト、お知らせ、アクセス情報を掲載しています。',
});

export default function ClubDandyTopPage() {
  return (
    <LayoutWrapperMain>
      <ShopTopMain />
    </LayoutWrapperMain>
  );
}
