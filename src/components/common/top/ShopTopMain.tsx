'use client';
/* =======================================
 * 横浜ダンディーグループ 店舗 TOP MAIN
 * URL: /src/components/common/top/ShopTopMain.tsx
 * Referenced in: /src/app/dandy/top/page.tsx
 * Created: 2025-08-22
 * Last updated: 2026-07-16
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
// import TopPickUp from '@/components/Shop/TopPickUp';
import CastSlide from '@/components/common/top/TopCastSlide';
import { usePathname } from 'next/navigation';
import { useEffect } from 'react';
import clsx from 'clsx';
import TopPickUpBan from '@/components/common/top/TopPickUpBan';
import CastRanking from '@/components/common/top/TopCastRanking';
import TopSlideSquareBan from '@/components/common/top/TopSlideSquareBan';
import BannerGroup from '@/components/common/BannerGroup';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import { trackPageAccess } from '@/lib/accessCounterApi';
import BlockAccess from '@/components/common/system/BlockAccess';
import { getShopData } from '@/components/common/ShopHeader';

const ShopTopMain = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);
  const shopData = getShopData(shop);

  //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
  useEffect(() => {
    //ページのID
    const pageId = 'top';
    trackPageAccess(shop, pageId);
  }, [shop]);
  //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する

  // 🔽 タイムスタンプでキャッシュバスティング
  const timestamp = Date.now();

  // 🔽 JSON パスを店舗別に切り替え（タイムスタンプ付き）
  const jsonBasePath = `/db/contents/${shop}`;
  const jsonPathBanMain = `${jsonBasePath}/TopMainBan01.json?t=${timestamp}`;
  const jsonPathNewFace = `${jsonBasePath}/TopNewFace.json?t=${timestamp}`;
  const jsonPathPickUpCast = `${jsonBasePath}/TopPickUpCast.json?t=${timestamp}`;
  const jsonPathPickUpBan = `${jsonBasePath}/TopPickUpBan.json?t=${timestamp}`;
  const jsonPathRanking = `${jsonBasePath}/TopRanking.json?t=${timestamp}`;
  const jsonPathSlideBan = `${jsonBasePath}/TopSlideBan.json?t=${timestamp}`;

  return (
    <section
      className={clsx(styles.containerShopTopMain, styles[activeStoreClass])}
    >
      <h1 className="sr-only">
        {shopData?.name ? `${shopData.name}の店舗情報` : '店舗情報'}
      </h1>
      <TopPickUpBan jsonPath={jsonPathPickUpBan} />
      <CastSlide
        titleJp="ピックアップ"
        titleEn="pick up"
        variant="pickUp"
        jsonPath={jsonPathPickUpCast}
      />
      <CastRanking
        titleJp="キャストランキング"
        titleEn="cast"
        titleEnSub="ranking"
        jsonPath={jsonPathRanking}
      />
      <TopSlideSquareBan jsonPath={jsonPathSlideBan} />
      <BannerGroup jsonPath={jsonPathBanMain} className={styles.boxBanMain} />
      <CastSlide
        titleJp="新人紹介"
        titleEn="new face"
        titleEnSub="cast"
        variant="newFace"
        jsonPath={jsonPathNewFace}
      />
      <BlockAccess variant="topPage" />
    </section>
  );
};
export default ShopTopMain;
