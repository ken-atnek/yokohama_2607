/* =======================================
 * 横浜ダンディーグループ 店舗 TOP スライドバナー
 * URL: /src/components/common/top/TopSlideBan.tsx
 * Referenced in: /src/components/common/top/ShopTopMain.tsx
 * Created: 2025-08-22
 * Last updated: 2026-07-14
 * ======================================= */
'use client';
import clsx from 'clsx';
import styles from './TopSlideSquareBan.module.scss';
import { Splide, SplideSlide } from '@splidejs/react-splide';
import '@splidejs/react-splide/css';
import {
  renderBannerItem,
  useBannerItems,
} from '@/components/common/renderBannerItem';
import { usePathname } from 'next/navigation';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import Image from 'next/image';
import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';

type TopSlideBanProps = {
  jsonPath: string;
};

const TopSlideBan = ({ jsonPath }: TopSlideBanProps) => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);
  const [mounted, setMounted] = useState(false);
  const [items, setModalImage, modalImage] = useBannerItems(jsonPath);

  useEffect(() => {
    setMounted(true);
  }, []);

  // スライド数が足りないときは複製してループ対応
  const minSlideCount = 8;
  const visibleItems =
    items.length === 0
      ? []
      : items.length < minSlideCount
        ? Array(Math.ceil(minSlideCount / items.length))
            .fill(items)
            .flat()
            .slice(0, minSlideCount)
        : items;

  // ループ有効判定（2枚以上のときのみ有効）
  const enableLoop = visibleItems.length > 1;

  const modal =
    modalImage && mounted
      ? createPortal(
          <div className="modal-overlay" onClick={() => setModalImage(null)}>
            <div className="modal-content">
              <Image
                src={modalImage}
                alt="ポップアップ画像"
                width={650}
                height={238}
              />
            </div>
          </div>,
          document.body
        )
      : null;

  if (items.length === 0) return null;

  return (
    <>
      <article
        className={clsx(styles.boxTopSlideBan, styles[activeStoreClass])}
      >
        <div className={styles.wrapImageList}>
          <Splide
            options={{
              type: enableLoop ? 'loop' : 'slide',
              perPage: 1,
              focus: 'center',
              gap: '0px',
              padding: {
                left: '22%',
                right: '22%',
              },
              arrows: false,
              pagination: false,
              autoplay: true,
              interval: 4000,
              pauseOnHover: false,
              pauseOnFocus: false,
              speed: 1000,
              drag: true,
              breakpoints: {
                767: {
                  perPage: 1,
                  gap: '8px',
                  padding: {
                    left: '12%',
                    right: '12%',
                  },
                  speed: 1000,
                  interval: 4000,
                },
              },
            }}
            aria-label="top slide banner"
          >
            {visibleItems.map((item, index) => (
              <SplideSlide key={`${item.banId}-${index}`}>
                <div className={styles.aspectWrapper}>
                  {renderBannerItem(item, setModalImage)}
                </div>
              </SplideSlide>
            ))}
          </Splide>
        </div>
      </article>
      {modal}
    </>
  );
};
export default TopSlideBan;
