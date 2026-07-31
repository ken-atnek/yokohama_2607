/* =======================================
 * クラブダンディー メインレイアウト
 * URL: /src/components/club_dandy/LayoutWrapperMain.tsx
 * Referenced in: /src/app/club_dandy/top/page.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
import Footer from '@/components/common/Footer';
import Header from '@/components/common/ShopHeader';
import ShopLeft, {
  type ShopLeftIframeItem,
} from '@/components/common/ShopLeft';
import ContainerShopList from '@/components/common/ContainerShopList';
import { navMenu } from '@/data/club_dandy/navMenuData';
import ShopFooterMenu from '@/components/common/ShopFooterMenu';

export default function LayoutWrapperMain({
  children,
}: {
  children: React.ReactNode;
}) {
  const storeId = 'club_dandy';
  const bottomIframes: ShopLeftIframeItem[] = [];

  return (
    <>
      <Header
        title="横浜 風俗(ヘルス)｜クラブダンディー "
        navMenu={navMenu}
        selectedNavIds={[
          'navRealTime',
          'navSchedule',
          'navCastList',
          'navPhotoBlog',
        ]}
      />
      <div className={styles.innerMain}>
        <main className={styles.shopMainContainer}>
          <ShopLeft shop={storeId} bottomIframes={bottomIframes} />
          {children}
        </main>
      </div>
      <ShopFooterMenu navMenu={navMenu} />
      <ContainerShopList activeStoreClass={storeId} />
      <Footer />
    </>
  );
}
