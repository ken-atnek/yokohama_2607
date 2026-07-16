/* =======================================
 * キャスト一覧 コンポーネントアイテム
 * URL:src/components/Shop/ItemCastList.tsx
 * Referenced in: : src/app/hot/cast/page.tsx
 * Created: 2025-09-03
 * Last updated: 2026-07-15
 * ======================================= */

import styles from './ShopCastList.module.scss';
import clsx from 'clsx';
import type { CastDetail } from '@/types/CastDetails';
import Image from 'next/image';
import Link from 'next/link';
import { CastGradeMap } from '@/data/CastGradeData';
import { usePathname } from 'next/navigation';
import { getShopFromPath } from '@/lib/shopUtils';
import CastBadgeIcons from '@/components/common/CastBadgeIcons';

type Props = {
  cast: CastDetail;
};

const ItemCastList = ({ cast }: Props) => {
  const grade = CastGradeMap[cast.gradeId];
  const ageLabel = cast.ageText || (cast.age !== null ? String(cast.age) : '');
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  return (
    <li key={cast.castId} className={styles.boxCast}>
      <div className={styles.wrapTodayTime}>
        {(cast.startTime && cast.endTime) || cast.scheduleStatus ? (
          <h3>本日出勤</h3>
        ) : null}

        {cast.startTime && cast.endTime && (
          <div className={styles.itemTime}>
            <span>{cast.startTime}</span>
            <span>{cast.endTime}</span>
          </div>
        )}

        {cast.scheduleStatus && (
          <p className={styles.scheduleStatus}>{cast.scheduleStatus}</p>
        )}
      </div>
      <Link
        href={`/${shop}/profile/?id=${cast.castId}&type=castlist`}
        prefetch={false}
        className={styles.cardLink}
      >
        <CastBadgeIcons
          badgeType={cast.badgeType}
          shopIconRank={cast.shopIconRank}
          areaIconRank={cast.areaIconRank}
          wrapperClassName={styles.wrapBadge}
          imageClassName={styles.badgeImage}
        />
        <div
          className={clsx(
            styles.wrapPhoto,
            cast.gradeId >= 0 && styles[`grade${cast.gradeId}`]
          )}
        >
          <div className={styles.photoFrame}>
            <Image
              src={cast.castImage}
              alt={cast.castName}
              width={220}
              height={300}
            />
          </div>
          {grade && grade.gradeId !== 0 ? (
            <p className={styles.gradeLabel}>{grade.labelEn}</p>
          ) : null}
          {cast.ranking && (
            <div
              className={clsx(styles.itemRanking, {
                [styles[`ranking${cast.ranking}`]]: cast.ranking,
              })}
            >
              <Image
                src={`/images/common/ranking/${String(cast.ranking).padStart(2, '0')}.webp`}
                alt={`ランキング ${cast.ranking}位`}
                width={50} // 必要に応じてサイズを調整
                height={50} // 必要に応じてサイズを調整
              />
            </div>
          )}
        </div>
      </Link>
      <div className={styles.castProfile}>
        <div className={styles.wrapName}>
          <p className={styles.castName}>{cast.castName}</p>
          <span className={styles.age}>{ageLabel}</span>
        </div>
        <div className={styles.castSize}>
          <span className={styles.tall}>{cast.tall}</span>
          <span className={styles.bust}>
            {cast.bust}
            <i>{cast.cup}</i>
          </span>
          <span className={styles.west}>{cast.west}</span>
          <span className={styles.hip}>{cast.hip}</span>
        </div>
      </div>
      <ul className={styles.serviceList}>
        <li
          className={clsx(
            cast.serviceHealth ? styles.isActive : styles.isInactive
          )}
        >
          ヘルス
        </li>
        <li
          className={clsx(
            cast.serviceMat ? styles.isActive : styles.isInactive
          )}
        >
          マット
        </li>
      </ul>
    </li>
  );
};

export default ItemCastList;
