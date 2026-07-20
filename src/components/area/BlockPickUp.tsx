/* =======================================
 * 横浜ダンディーグループ PICK UP
 * URL: /src/components/area/BlockPickUp.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-09
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

const BlockPickUp = () => {
  const { groups: pickUpGroups, activeIndices } = useAreaCastRotation(
    '/db/contents/area/areaTopPickUp.json'
  );

  const mobileVisibleCasts = useMemo(
    () =>
      SHOP_ORDER.map((shopId) => {
        const casts = pickUpGroups[shopId];
        if (casts.length === 0) return null;
        return casts[activeIndices[shopId]] ?? casts[0];
      }).filter((cast): cast is AreaCastItem => cast !== null),
    [activeIndices, pickUpGroups]
  );

  const renderCastCard = (
    cast: AreaCastItem,
    options?: {
      isActive?: boolean;
      keySuffix?: string;
    }
  ) => {
    const isActive = options?.isActive ?? true;
    const keySuffix = options?.keySuffix ?? 'default';

    return (
      <Link
        key={`${cast.shopId}-${cast.castId}-${keySuffix}`}
        href={`/${cast.shopId}/profile/?id=${cast.castId}`}
        className={`${styles.wrapLink} ${styles.fadeItem} ${isActive ? styles.isActive : ''}`}
        style={
          {
            '--shop-color': shopColorMap[cast.shopId],
            '--zoom-delay': zoomDelayMap[cast.shopId as ShopId],
          } as CSSProperties
        }
        aria-hidden={!isActive}
        tabIndex={isActive ? 0 : -1}
      >
        <div className={styles.shopName}>{cast.shopName}</div>
        <div className={styles.wrapImage}>
          <Image
            src={cast.castImage}
            alt={cast.castName}
            width={416}
            height={612}
            priority
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
      <ul className={`${styles.blockPickUp} ${styles.pcVersion}`}>
        {SHOP_ORDER.map((shopId) => {
          const casts = pickUpGroups[shopId];
          if (casts.length === 0) {
            return null;
          }
          const activeIndex = activeIndices[shopId] ?? 0;
          return (
            <li key={shopId}>
              <div className={styles.fadeStage}>
                {casts.map((cast, idx) =>
                  renderCastCard(cast, {
                    isActive: idx === activeIndex,
                    keySuffix: `pc-${shopId}-${idx}`,
                  })
                )}
              </div>
            </li>
          );
        })}
      </ul>
      <div className={styles.mobileBlockPickUp}>
        {mobileVisibleCasts.length >= 2 ? (
          <Splide
            options={{
              type: 'slide',
              perPage: 1,
              perMove: 1,
              gap: '5vw',
              arrows: false,
              pagination: true,
              autoplay: false,
              drag: true,
            }}
            className={styles.castSplide}
          >
            {mobileVisibleCasts.map((cast) => (
              <SplideSlide key={`${cast.shopId}-${cast.castId}`}>
                {renderCastCard(cast, {
                  keySuffix: `sp-${cast.shopId}-${cast.castId}`,
                })}
              </SplideSlide>
            ))}
          </Splide>
        ) : mobileVisibleCasts.length === 1 ? (
          <div className={styles.singleCastWrapper}>
            {renderCastCard(mobileVisibleCasts[0], {
              keySuffix: `sp-single-${mobileVisibleCasts[0].shopId}`,
            })}
          </div>
        ) : null}
      </div>
    </>
  );
};

export default BlockPickUp;
