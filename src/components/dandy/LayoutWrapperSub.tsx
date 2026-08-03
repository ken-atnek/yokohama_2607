/* =======================================
 * 横浜ダンディー サブレイアウト
 * URL: /src/components/dandy/LayoutWrapperSub.tsx
 * Referenced in: /src/app/dandy/cast/page.tsx
 * Created:  2026-07-15
 * Last updated: 2026-07-31
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
import Footer from '@/components/common/Footer';
import Header from '@/components/common/ShopHeader';
import ContainerShopList from '@/components/common/ContainerShopList';
import ShopFooterMenu from '@/components/common/ShopFooterMenu';
import { navMenu } from '@/data/dandy/navMenuData';
export default function LayoutWrapperMain({
  children,
}: {
  children: React.ReactNode;
}) {
  const activeStoreClass = 'dandy';
  return (
    <>
      <Header
        title="横浜 風俗(ヘルス)｜横浜ダンディー "
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
