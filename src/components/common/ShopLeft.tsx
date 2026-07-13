/* =======================================
 * 店舗 LEFTコンテンツ
 * URL: src/components/common/ShopLeft.tsx
 * Created: 2025-08-22
 * Last updated: 2025-09-19
 * ======================================= */
import styles from './ShopLeft.module.scss';
import BannerGroup from '@/components/common/BannerGroup';
import clsx from 'clsx';
import { Shops } from '@/data/AreaShopData';
import ExternalLink from '@/components/common/ExternalLink';
import Image from 'next/image';
import type { CSSProperties } from 'react';

type ShopLeftProps = {
  photoDiaryUrl?: string;
  shop: string;
  bottomIframes?: ShopLeftIframeItem[];
};

export type ShopLeftIframeItem = {
  url: string;
  className: string;
};

const ShopLeft = ({ photoDiaryUrl, shop, bottomIframes = [] }: ShopLeftProps) => {
  const storeId = shop;
  const activeStoreClass = storeId;
  const shopData = Shops.find((item) => item.storeId === storeId);

  // 🔽 タイムスタンプでキャッシュバスティング
  const timestamp = Date.now();

  // 🔽 JSON パスを店舗別に切り替え（タイムスタンプ付き）
  const jsonBasePath = `/db/contents/${shop}`;
  const jsonPathTop = `${jsonBasePath}/LeftBan01.json?t=${timestamp}`;
  const jsonPathBottom = `${jsonBasePath}/LeftBan02.json?t=${timestamp}`;

  return (
    <section
      className={clsx(styles.containerShopLeft, styles[activeStoreClass])}
      style={
        {
          '--baseColor': shopData?.shopColor ?? '#e776ad',
        } as CSSProperties
      }
    >
      <div className={styles.boxHead}>
        <div className={styles.shopLogoWrap}>
          {shopData ? (
            <Image
              src={shopData.logo}
              alt={shopData.name}
              width={240}
              height={80}
            />
          ) : null}
        </div>
        {shopData && (
          <div className={styles.innerShopInfo}>
            <div className={styles.sidebarH2}>{shopData.nameEn}</div>
            <h2 className={styles.shopNameHeading}>{shopData.name}</h2>
            <ExternalLink
              href={`tel:${shopData.phone}`}
              className={styles.itemTel}
            >
              {shopData.phone}
            </ExternalLink>
          </div>
        )}
      </div>
      <div className={styles.boxPhotoDiary}>
        <div className={styles.wrapTitle}>
          <h2>
            <span>
              photo
              <i>diary</i>
            </span>
            写メ日記
          </h2>
        </div>
        <div className={styles.titleObject}>
          <div className={styles.wrapText}>
            <div className={styles.shopName}>{shopData?.nameEn}</div>
            <div className={styles.title}>
              photo
              <i>diary</i>
            </div>
          </div>
        </div>
        <div className={styles.wrapContents}>
          <iframe src={photoDiaryUrl} />
        </div>
      </div>
      <BannerGroup jsonPath={jsonPathTop} className={styles.boxBanTop} />
      <div className={styles.titleContents}>
        <span>contents</span>
        <h2 className={styles.titleContentsHeading}>コンテンツ</h2>
      </div>
      <BannerGroup jsonPath={jsonPathBottom} className={styles.boxBanBottom} />
      {bottomIframes.map((item) => (
        <div
          key={`${item.className}-${item.url}`}
          className={styles[item.className as keyof typeof styles]}
        >
          <iframe src={item.url} className={styles.bottomIframeFrame} />
        </div>
      ))}
    </section>
  );
};

export default ShopLeft;
