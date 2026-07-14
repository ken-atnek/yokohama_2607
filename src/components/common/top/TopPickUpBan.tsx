/* =======================================
 * 横浜ダンディーグループ 店舗 TOP ピックアップバナー
 * URL: /src/components/common/top/TopPickUpBan.tsx
 * Referenced in: /src/components/common/top/ShopTopMain.tsx
 * Created: 2025-08-22
 * Last updated: 2026-07-14
 * ======================================= */
'use client';
import styles from './TopPickUpBan.module.scss';
import { Splide, SplideSlide } from '@splidejs/react-splide';
import '@splidejs/react-splide/css';
import {
  renderBannerItem,
  useBannerItems,
} from '@/components/common/renderBannerItem';
import Image from 'next/image';
import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';

type TopPickUpBanProps = {
  jsonPath?: string;
};

const TopPickUpBan = ({ jsonPath }: TopPickUpBanProps) => {
  const [mounted, setMounted] = useState(false);
  const [items, setModalImage, modalImage] = useBannerItems(jsonPath ?? '');

  useEffect(() => {
    setMounted(true);
  }, []);

  if (!jsonPath) return null;

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

  // スライド数が足りない場合は複製してループ対応
  const minSlides = 4;
  const visibleItems =
    items.length === 0
      ? []
      : items.length < minSlides
        ? Array(Math.ceil(minSlides / items.length))
            .fill(items)
            .flat()
            .slice(0, minSlides)
        : items;

  const enableLoop = visibleItems.length > 1;

  return (
    <>
      <article className={styles.boxTopPickUp}>
        <div className={styles.wrapImageList}>
          <Splide
            options={{
              type: enableLoop ? 'loop' : 'slide',
              perPage: 1,
              gap: '0px',
              arrows: false,
              pagination: true,
              autoplay: true,
              interval: 3000,
              pauseOnHover: false,
              pauseOnFocus: false,
              speed: 800,
              drag: true,
            }}
            aria-label="top pickup banner"
          >
            {visibleItems.map((item, index) => (
              <SplideSlide key={`${item.banId}-${index}`}>
                <div className={styles.aspectWrapper}>
                  {renderBannerItem(item, setModalImage, index === 0)}
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
export default TopPickUpBan;
