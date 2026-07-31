/* =======================================
 * ミスターダンディー メインレイアウト
 * URL: /src/components/mr_dandy/LayoutWrapperMain.tsx
 * Referenced in: /src/app/mr_dandy/top/page.tsx
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
import { navMenu } from '@/data/mr_dandy/navMenuData';
import ShopFooterMenu from '@/components/common/ShopFooterMenu';

export default function LayoutWrapperMain({
  children,
}: {
  children: React.ReactNode;
}) {
  const storeId = 'mr_dandy';
  const bottomIframes: ShopLeftIframeItem[] = [
    {
      url: 'https://qzin.jp/mrdandy0/blogwidget?width=300&height=460',
      className: 'bottomDandyVanilla01',
    },
    {
      url: 'https://qzin.jp/mrdandy0/widget?d=0',
      className: 'bottomDandyVanilla02',
    },
  ];

  return (
    <>
      <Header
        title="横浜 風俗(ヘルス)｜ミスターダンディー "
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
            photoDiaryUrl="https://blogparts.cityheaven.net/widget/?shopId=3352&mode=2&type=14&num=12&col=3&color=2&fontsize=12&width=300"
            bottomIframes={bottomIframes}
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
