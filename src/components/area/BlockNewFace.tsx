/* =======================================
 * 横浜ダンディーグループ NEW FACE
 * URL: /src/components/area/BlockNewFace.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-09
 * ======================================= */

'use client';

import type { CSSProperties } from 'react';
import Image from 'next/image';
import Link from 'next/link';
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

  const renderCard = (
    cast: AreaCastItem,
    isActive: boolean,
    keySuffix: string
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
        <Image src={cast.castImage} alt={cast.castName} width={416} height={612} />
      </div>
      <div className={styles.wrapProfile}>
        <AreaCastMeta cast={cast} />
      </div>
      </Link>
    );
  };

  return (
    <ul className={styles.blockNewFace}>
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
                renderCard(cast, idx === activeIndex, `${shopId}-${idx}`)
              )}
            </div>
          </li>
        );
      })}
    </ul>
  );
};

export default BlockNewFace;
