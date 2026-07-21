/* =======================================
 * 横浜ダンディーグループ 店舗ページタイトル
 * URL: /src/components/common/ShopPageTitle.tsx
 * Referenced in: /src/app/dandy/cast/page.tsx
 * Created: 2025-08-28
 * Last updated: 2026-07-21
 * ======================================= */
import styles from './ShopPageTitle.module.scss';
import Image from 'next/image';
import clsx from 'clsx';
import { getStoreClass, getLogoImageSrc } from '@/lib/shopUtils';

type PageTitleProps = {
  titleJp: string;
  titleEn: string;
  shop?: string;
  variant?: 'default' | 'noLogo';
};

const shopLogoAltMap: Record<string, string> = {
  dandy: '横浜ダンディー',
  mr_dandy: 'ミスターダンディ',
  club_dandy: 'クラブダンディ',
};

const PageTitle = ({
  titleJp,
  titleEn,
  shop = 'dandy',
  variant = 'default',
}: PageTitleProps) => {
  const activeStoreClass = getStoreClass(shop);
  const logoSrc = getLogoImageSrc(shop);
  const logoAlt = shopLogoAltMap[shop] || shopLogoAltMap['dandy'];
  const shouldShowLogo = variant !== 'noLogo';

  return (
    <section
      className={clsx(styles.containerPageTitle, styles[activeStoreClass])}
    >
      <article className={styles.innerPageTitle}>
        <div className={styles.boxH2}>
          <span className={styles.sidebarH2}>{titleEn}</span>
          <h1>{titleJp}</h1>
        </div>
        {shouldShowLogo && (
          <div className={styles.itemLogo}>
            <Image
              className={styles.logoImage}
              src={logoSrc}
              alt={logoAlt}
              width={200}
              height={50}
            />
          </div>
        )}
      </article>
    </section>
  );
};
export default PageTitle;
