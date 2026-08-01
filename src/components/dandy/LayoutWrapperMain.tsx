/* =======================================
 * 横浜ダンディー メインレイアウト
 * URL: /src/components/dandy/LayoutWrapperMain.tsx
 * Referenced in: /src/app/dandy/top/page.tsx
 * Created: 2025-10-11
 * Last updated: 2026-08-01
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
import Footer from '@/components/common/Footer';
import Header from '@/components/common/ShopHeader';
import ShopLeft, {
  type ShopLeftIframeItem,
} from '@/components/common/ShopLeft';
import ContainerShopList from '@/components/common/ContainerShopList';
import { navMenu } from '@/data/dandy/navMenuData';
import ShopFooterMenu from '@/components/common/ShopFooterMenu';
export default function LayoutWrapperMain({
  children,
  hideShopLeftOnMobile = false,
}: {
  children: React.ReactNode;
  hideShopLeftOnMobile?: boolean;
}) {
  const storeId = 'dandy';
  const bottomIframes: ShopLeftIframeItem[] = [
    // {
    //   url: 'https://qzin.jp/1021/widget?d=0',
    //   className: 'bottomDandyVanilla01',
    // },
    {
      url: 'https://blogparts.girlsheaven-job.net/widget/?cid=3470&mode=2&type=18&num=4&color=2&fontsize=12',
      className: 'bottomDandyVanilla01',
    },
  ];
  return (
    <>
      <Header
        title="横浜 風俗(ヘルス)｜横浜ダンディー "
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
          <ShopLeft
            shop={storeId}
            photoDiaryUrl="https://blogparts.cityheaven.net/widget/?shopId=3470&mode=2&type=14&num=12&col=3&color=8&fontsize=12&width=300"
            bottomIframes={bottomIframes}
            hideOnMobile={hideShopLeftOnMobile}
          />
          {children}
        </main>
      </div>
      <ShopFooterMenu navMenu={navMenu} />
      <ContainerShopList activeStoreClass={storeId} />
      <Footer />
    </>
  );
}
