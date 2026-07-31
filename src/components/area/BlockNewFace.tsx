/* =======================================
 * 横浜ダンディーグループ NEW FACE
 * URL: /src/components/area/BlockNewFace.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-31
 * ======================================= */

'use client';

import { useMemo } from 'react';
import type { CSSProperties } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Splide, SplideSlide } from '@splidejs/react-splide';
import '@splidejs/react-splide/css';
import styles from '@/styles/AreaTop.module.scss';
import AreaCastMeta from './AreaCastMeta';
import {
  SHOP_ORDER,
  shopColorMap,
  zoomDelayMap,
  useAreaCastRotation,
  type AreaCastItem,
  type ShopId,
} from './useAreaCastRotation';

const BlockNewFace = () => {
  const { groups: newFaceGroups, activeIndices } = useAreaCastRotation(
    '/db/contents/area/areaTopNewFace.json'
  );

  const mobileLoopCasts = useMemo(() => {
    const casts = SHOP_ORDER.flatMap((shopId) => newFaceGroups[shopId]);

    return [...casts].sort(() => Math.random() - 0.5);
  }, [newFaceGroups]);

  const renderCard = (
    cast: AreaCastItem,
    isActive = true,
    keySuffix = 'default'
  ) => {
    return (
      <Link
        key={`${cast.shopId}-${cast.castId}-${keySuffix}`}
        href={`/${cast.shopId}/profile/?id=${cast.castId}`}
        className={`${styles.wrapLink} ${styles.fadeItem} ${
          isActive ? styles.isActive : ''
        }`}
        style={
          {
            '--shop-color': shopColorMap[cast.shopId],
            '--zoom-delay': zoomDelayMap[cast.shopId as ShopId],
          } as CSSProperties
        }
        aria-hidden={!isActive}
        tabIndex={isActive ? 0 : -1}
      >
        <div className={styles.wrapImage}>
          <Image
            src={cast.castImage}
            alt={cast.castName}
            width={416}
            height={612}
          />
        </div>
        <div className={styles.wrapProfile}>
          <AreaCastMeta cast={cast} />
        </div>
      </Link>
    );
  };

  return (
    <>
      <ul className={`${styles.blockNewFace} ${styles.pcVersion}`}>
        {SHOP_ORDER.map((shopId) => {
          const casts = newFaceGroups[shopId];
          if (casts.length === 0) {
            return null;
          }

          const activeIndex = activeIndices[shopId] ?? 0;

          return (
            <li key={shopId}>
              <div className={styles.fadeStage}>
                {casts.map((cast, idx) =>
                  renderCard(cast, idx === activeIndex, `pc-${shopId}-${idx}`)
                )}
              </div>
            </li>
          );
        })}
      </ul>
      <div className={styles.mobileBlockNewFace}>
        {mobileLoopCasts.length >= 2 ? (
          <Splide
            options={{
              type: 'loop',
              perPage: 2,
              perMove: 1,
              gap: '2vw',
              arrows: false,
              pagination: false,
              autoplay: true,
              interval: 0,
              speed: 5200,
              easing: 'linear',
              pauseOnHover: false,
              pauseOnFocus: false,
              drag: true,
              waitForTransition: true,
            }}
            className={styles.castSplide}
          >
            {mobileLoopCasts.map((cast, idx) => (
              <SplideSlide key={`${cast.shopId}-${cast.castId}-${idx}`}>
                {renderCard(
                  cast,
                  true,
                  `sp-${cast.shopId}-${cast.castId}-${idx}`
                )}
              </SplideSlide>
            ))}
          </Splide>
        ) : mobileLoopCasts.length === 1 ? (
          <div className={styles.singleCastWrapper}>
            {renderCard(mobileLoopCasts[0], true, 'sp-single')}
          </div>
        ) : null}
      </div>
    </>
  );
};

export default BlockNewFace;
