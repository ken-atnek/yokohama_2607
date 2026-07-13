'use client';
/* =======================================
 *店舗 TOP MAIN
 * URL: src/components/common/ShopTopMain.tsx
 * Created: 2025-08-22
 * Last updated: 2025-10-07
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
// import castSlideStyles from '@/styles/components/ShopTopCastSlide.module.scss';
// import TopPickUp from '@/components/Shop/TopPickUp';
// import CastSlide from '@/components/Shop/TopCastSlide';
import { usePathname } from 'next/navigation';
import { useEffect } from 'react';
import clsx from 'clsx';
// import CastRanking from '@/components/Shop/TopCastRanking';
// import TopSlideBan from '@/components/Shop/TopSlideBan';
// import BannerGroup from '@/components/common/BannerGroup';
// import ShopNews from '@/components/Shop/TopNews';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import { trackPageAccess } from '@/lib/accessCounterApi';
import BlockAccess from '@/components/common/system/BlockAccess';

const ShopTopMain = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

  //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
  useEffect(() => {
    //ページのID
    const pageId = 'top';
    trackPageAccess(shop, pageId);
  }, [shop]);
  //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する

  // 🔽 タイムスタンプでキャッシュバスティング
  // const timestamp = Date.now();

  // 🔽 JSON パスを店舗別に切り替え（タイムスタンプ付き）
  const jsonBasePath = `/data/${shop}`;
  // const jsonPathBanMain = `${jsonBasePath}/TopMainBan01.json?t=${timestamp}`;
  // const jsonPathNewFace = `${jsonBasePath}/TopNewFace.json?t=${timestamp}`;
  // const jsonPathNews = `${jsonBasePath}/TopNews.json?t=${timestamp}`;
  // const jsonPathRanking = `${jsonBasePath}/TopRanking.json?t=${timestamp}`;

  return (
    <section
      className={clsx(styles.containerShopTopMain, styles[activeStoreClass])}
    >
      {/* <TopPickUp /> */}
      {/* <CastSlide
        titleJp="新人紹介"
        titleEn="new face"
        titleEnSub="cast"
        classNameStyles={castSlideStyles.newFace}
        jsonPath={jsonPathNewFace}
      /> */}
      {/* <ShopNews
        titleJp="新着情報・トピックス"
        titleEn="news"
        jsonPath={jsonPathNews}
      /> */}
      {/* <CastRanking
        titleJp="キャストランキング"
        titleEn="cast"
        titleEnSub="ranking"
        jsonPath={jsonPathRanking}
      /> */}
      {/* <TopSlideBan /> */}
      {/* <BannerGroup jsonPath={jsonPathBanMain} className={styles.boxBanMain} /> */}
      <BlockAccess variant="topPage" />
    </section>
  );
};
export default ShopTopMain;
