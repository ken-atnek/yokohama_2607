/* =======================================
 * キャスト一覧 コンポーネントアイテム
 * URL: /src/components/common/cast/ShopItemCastList.tsx
 * Referenced in: /src/components/common/cast/ShopCastList.tsx
 * Created: 2025-09-03
 * Last updated: 2026-07-17
 * ======================================= */

import styles from './ShopItemCastList.module.scss';
import clsx from 'clsx';
import type { CastDetail } from '@/types/CastDetails';
import type { ReactNode } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { CastGradeMap } from '@/data/CastGradeData';
import { usePathname } from 'next/navigation';
import { getShopFromPath } from '@/lib/shopUtils';
import CastBadgeIcons from '@/components/common/cast/CastBadgeIcons';
import ScheduleTimeBlock from '@/components/common/cast/ScheduleTimeBlock';

type Props = {
  cast: CastDetail;
  linkType?: string;
  topSlot?: ReactNode;
  showDefaultScheduleInfo?: boolean;
  showServiceList?: boolean;
  variant?: 'default' | 'schedule';
};

const getValidTimeSlots = (cast: CastDetail) => {
  if (Array.isArray(cast.timeSlots) && cast.timeSlots.length > 0) {
    return cast.timeSlots.filter(
      (slot) =>
        typeof slot?.startTime === 'string' &&
        slot.startTime !== '' &&
        typeof slot?.endTime === 'string' &&
        slot.endTime !== ''
    );
  }

  if (
    typeof cast.startTime === 'string' &&
    cast.startTime !== '' &&
    typeof cast.endTime === 'string' &&
    cast.endTime !== ''
  ) {
    return [{ startTime: cast.startTime, endTime: cast.endTime }];
  }

  return [];
};

const ItemCastList = ({
  cast,
  linkType = 'castlist',
  topSlot,
  showDefaultScheduleInfo = true,
  showServiceList = true,
  variant = 'default',
}: Props) => {
  const grade = CastGradeMap[cast.gradeId];
  const ageLabel = cast.ageText || (cast.age !== null ? String(cast.age) : '');
  const hasNewBadge = cast.badgeType === 'new';
  const hasNewKirakiraBadge = cast.badgeType === 'new_kirakira';
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const timeSlots = getValidTimeSlots(cast);
  return (
    <li
      className={clsx(
        styles.boxCast,
        variant === 'schedule' && styles.isSchedule
      )}
    >
      {topSlot}
      {showDefaultScheduleInfo ? (
        <div className={styles.wrapTodayTime}>
          {timeSlots.length > 0 || cast.scheduleStatus ? (
            <h3>本日出勤</h3>
          ) : null}

          {(timeSlots.length > 0 || cast.scheduleStatus) && (
            <ScheduleTimeBlock
              timeSlots={timeSlots}
              scheduleStatus={cast.scheduleStatus}
              slotListClassName={styles.itemTimeList}
              slotItemClassName={styles.itemTime}
              statusClassName={styles.scheduleStatus}
              timeTag="span"
              slotKeyPrefix={cast.castId}
            />
          )}
        </div>
      ) : null}
      <Link
        href={`/${shop}/profile/?id=${cast.castId}&type=${linkType}`}
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
            hasNewBadge && styles.hasNewBadge,
            hasNewKirakiraBadge && styles.hasNewKirakira,
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
          <span className={styles.waist}>{cast.waist}</span>
          <span className={styles.hip}>{cast.hip}</span>
        </div>
      </div>
      {showServiceList ? (
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
      ) : null}
    </li>
  );
};

export default ItemCastList;
