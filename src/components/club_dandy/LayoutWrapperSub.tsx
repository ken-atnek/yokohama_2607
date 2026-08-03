/* =======================================
 * クラブダンディー サブレイアウト
 * URL: /src/components/club_dandy/LayoutWrapperSub.tsx
 * Referenced in: /src/app/club_dandy/cast/page.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
import Footer from '@/components/common/Footer';
import Header from '@/components/common/ShopHeader';
import ContainerShopList from '@/components/common/ContainerShopList';
import ShopFooterMenu from '@/components/common/ShopFooterMenu';
import { navMenu } from '@/data/club_dandy/navMenuData';

export default function LayoutWrapperSub({
  children,
}: {
  children: React.ReactNode;
}) {
  const activeStoreClass = 'club_dandy';

  return (
    <>
      <Header
        title="横浜 風俗(ヘルス)｜クラブダンディー "
        navMenu={navMenu}
        selectedNavIds={[
          'navCastList',
          'navRealTime',
          'navSchedule',
          'navReserve',
          'navPhotoBlog',
          'navReviews',
          'navSystem',
          'navRecruit',
          'navRecruitMen',
        ]}
      />
      <div className={styles.innerSub}>
        <main className={styles.shopSubContainer}>{children}</main>
      </div>
      <ShopFooterMenu navMenu={navMenu} />
      <ContainerShopList activeStoreClass={activeStoreClass} />
      <Footer />
    </>
  );
}
