'use client';
/* =======================================
 * 横浜ダンディーグループ 店舗 TOP キャストスライド
 * URL: /src/components/common/top/TopCastSlide.tsx
 * Referenced in: /src/components/common/top/ShopTopMain.tsx
 * Created: 2025-08-22
 * Last updated: 2026-07-14
 * ======================================= */
import styles from './TopCastSlide.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import { useEffect, useState } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Splide, SplideSlide } from '@splidejs/react-splide';
import '@splidejs/react-splide/css';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import { Shops } from '@/data/AreaShopData';

type ContentsProps = {
  titleJp?: string;
  titleEn?: string;
  titleEnSub?: string;
  jsonPath: string;
  variant?: 'newFace' | 'pickUp';
};

type CastSlideItem = {
  category: number;
  castId: string;
  castName: string;
  castImage: string;
};

const CastSlide = ({
  titleJp,
  titleEn,
  titleEnSub,
  jsonPath,
  variant,
}: ContentsProps) => {
  const pathname = usePathname();
  const pathShop = getShopFromPath(pathname);
  const shop = pathShop || pathname.split('/')[1] || '';
  const activeStoreClass = getStoreClass(shop);
  const shopData = Shops.find((item) => item.storeId === shop);
  const storeName = shopData?.nameEn ?? '';

  const [castData, setCastData] = useState<
    {
      category: number;
      castId: string;
      castName: string;
      castImage: string;
    }[]
  >([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchCastData = async () => {
      try {
        // キャッシュバスティング設定（開発環境のみ）
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = timestamp
          ? `${jsonPath}${jsonPath.includes('?') ? '&' : '?'}t=${timestamp}`
          : jsonPath;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data: CastSlideItem[] = await response.json();
        setCastData(data);
      } catch {
        setCastData([]);
      } finally {
        setIsLoading(false);
      }
    };

    fetchCastData();
  }, [jsonPath]);

  useEffect(() => {
    if (castData.length > 0) {
      const castOrder = castData.map((c) => ({
        id: c.castId,
        name: c.castName,
      }));
      const orderKey =
        variant === 'pickUp' ? 'castOrder_pickup' : 'castOrder_newface';
      sessionStorage.setItem(orderKey, JSON.stringify(castOrder));
    }
  }, [castData, variant]);

  // データが空またはロード中の場合は非表示
  if (isLoading || castData.length === 0) {
    return null;
  }

  return (
    <article
      className={clsx(
        styles.boxCastSlide,
        styles[activeStoreClass],
        variant && styles[variant]
      )}
    >
      <div className={styles.wrapTitle}>
        <h2>
          <span>
            {titleEn}
            <i>{titleEnSub}</i>
          </span>
          {titleJp}
        </h2>
      </div>
      <div className={styles.titleObject}>
        <div className={styles.wrapText}>
          <div className={styles.shopName}>{storeName}</div>
          <div className={styles.title}>
            {titleEn}
            <i>{titleEnSub}</i>
          </div>
        </div>
      </div>
      <div className={styles.wrapSlideList}>
        <Splide
          options={{
            type: 'loop',
            perPage: 4,
            perMove: 1,
            gap: '0.8rem',
            arrows: false,
            pagination: false,
            autoplay: true,
            interval: 0,
            speed: 6000,
            easing: 'linear',
            pauseOnHover: false,
            pauseOnFocus: false,
            drag: true,
            waitForTransition: true,
            breakpoints: {
              767: {
                perPage: 2,
                speed: 4500,
              },
            },
          }}
          aria-label={`${titleJp ?? titleEn ?? 'cast'} slider`}
        >
          {castData.map((cast) => (
            <SplideSlide
              className={clsx(
                styles.itemCast,
                cast.category === 1 && styles.newFace,
                cast.category === 2 && styles.photoUp
              )}
              key={cast.castId}
            >
              <Link
                href={`/${shop}/profile/?id=${cast.castId}&type=${variant === 'pickUp' ? 'pickup' : 'newface'}`}
              >
                <div className={styles.image}>
                  <Image
                    src={
                      cast.castImage && cast.castImage !== ''
                        ? cast.castImage
                        : '/images/common/no-image.webp'
                    }
                    alt={cast.castName}
                    fill
                    style={{ objectFit: 'cover' }}
                  />
                </div>
                <div className={styles.castName}>{cast.castName}</div>
              </Link>
            </SplideSlide>
          ))}
        </Splide>
      </div>
    </article>
  );
};
export default CastSlide;
