/* =======================================
 * 横浜ダンディーグループ エリアTOP キャスト名+スリーサイズ共通表示
 * URL: /src/components/area/AreaCastMeta.tsx
 * Referenced in: /src/components/area/BlockNewFace.tsx, /src/components/area/BlockPickUp.tsx
 * Created: 2026-07-20
 * Last updated: 2026-07-20
 * ======================================= */

import styles from '@/styles/AreaTop.module.scss';
import type { AreaCastItem } from './useAreaCastRotation';

const AreaCastMeta = ({ cast }: { cast: AreaCastItem }) => {
  const ageLabel = cast.ageText || (cast.age !== null ? String(cast.age) : '');

  return (
    <>
      <div className={styles.castName}>{cast.castName}</div>
      <div className={styles.castSize}>
        <span className={styles.age}>{ageLabel}</span>
        <span className={styles.tall}>{cast.tall}</span>
        <span className={styles.bust}>
          {cast.bust}
          <i>{cast.cup}</i>
        </span>
        <span className={styles.waist}>{cast.waist}</span>
        <span className={styles.hip}>{cast.hip}</span>
      </div>
    </>
  );
};

export default AreaCastMeta;
