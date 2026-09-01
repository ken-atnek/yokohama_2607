/* =======================================
 * 横浜ダンディーグループ リアルタイムコンポーネント
 * URL: /src/components/common/realtime/ContainerRealTime.tsx
 * Referenced in: /src/app/dandy/realtime/page.tsx
 * Created: 2025-08-29
 * Last updated: 2026-07-18
 * ======================================= */
'use client';

import styles from '@/styles/ShopRealtime.module.scss';
import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { usePathname } from 'next/navigation';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import { trackPageAccess } from '@/lib/accessCounterApi';
import Image from 'next/image';
import type { CastDetail } from '@/types/CastDetails';
import Link from 'next/link';
import { CastGradeMap } from '@/data/CastGradeData';
import ShopSwitchTabs from '@/components/common/ShopSwitchTabs';
import ScheduleTimeBlock from '@/components/common/cast/ScheduleTimeBlock';
import { getRealTimeDetailText } from '@/lib/getRealTimeDetailText';
import { normalizeCastBase } from '@/lib/normalizeCast';
import {
  formatRealtimeTime,
  normalizeRealTimeData,
  REALTIME_STATUS_LABELS,
} from '@/lib/realtime';

type CastWithStatus = CastDetail & {
  realTimeStatus: string | number;
  availableFrom?: string;
};

type RealTimeResponseItem = {
  upDateTime?: string;
  castData?: Array<Partial<CastDetail> & Record<string, unknown>>;
};

const DANDY_REALTIME_STATUS_ORDER: Record<number, number> = {
  3: 0,
  2: 1,
  1: 2,
  4: 3,
  5: 4,
};

function normalizeRealtimeCast(
  source: Partial<CastDetail> & Record<string, unknown>,
  shop: string
): CastWithStatus | null {
  const normalized = normalizeCastBase(source, {
    shop,
    profileImagesMode: 'empty',
  });

  if (!normalized) {
    return null;
  }

  const realtime = normalizeRealTimeData(source);

  return {
    ...normalized,
    realTimeStatus: realtime.realTimeStatus,
    realTimeUpdatedAt: realtime.realTimeUpdatedAt,
    realTimeDetail: realtime.realTimeDetail,
    realTimeComment: realtime.realTimeComment,
    availableFrom: realtime.availableFrom,
  };
}

const ContainerRealtime = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

  const [castData, setCastData] = useState<CastWithStatus[]>([]);
  const [updateTime, setUpdateTime] = useState<string>('');
  const [isError, setIsError] = useState(false);

  useEffect(() => {
    const pageId = 'realTime';
    trackPageAccess(shop, pageId);

    const fetchRealtimeData = async () => {
      try {
        setIsError(false);
        const timestamp = Date.now();
        const dataPath = `/db/contents/${shop}/RealTime.json?t=${timestamp}`;

        const response = await fetch(dataPath, { cache: 'no-store' });
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = (await response.json()) as RealTimeResponseItem[];
        const firstItem = Array.isArray(data) ? data[0] : undefined;
        const nextCastData = Array.isArray(firstItem?.castData)
          ? firstItem.castData
              .map((item) => normalizeRealtimeCast(item, shop))
              .filter((item): item is CastWithStatus => item !== null)
          : [];

        setCastData(nextCastData);
        setUpdateTime(
          typeof firstItem?.upDateTime === 'string' ? firstItem.upDateTime : ''
        );
      } catch {
        setCastData([]);
        setUpdateTime('');
        setIsError(true);
      }
    };

    fetchRealtimeData();
  }, [shop]);

  const groupedByStatus = castData.reduce(
    (acc: Record<string, CastWithStatus[]>, item) => {
      const key = String(item.realTimeStatus);
      if (!acc[key]) {
        acc[key] = [];
      }
      acc[key].push(item);
      return acc;
    },
    {}
  );

  const realtimeList = castData.filter(
    (cast, idx, self) => self.findIndex((c) => c.castId === cast.castId) === idx
  );

  useEffect(() => {
    if (realtimeList.length > 0) {
      const castOrder = realtimeList.map((cast) => ({
        id: cast.castId,
        name: cast.castName,
      }));
      sessionStorage.setItem('castOrder_realtime', JSON.stringify(castOrder));
    }
  }, [realtimeList]);

  const statusEntries = Object.entries(groupedByStatus).sort(
    ([statusA], [statusB]) => {
      const orderA = Number(statusA);
      const orderB = Number(statusB);

      if (shop === 'dandy' || shop === 'club_dandy') {
        const priorityA =
          DANDY_REALTIME_STATUS_ORDER[orderA] ?? Number.MAX_SAFE_INTEGER;
        const priorityB =
          DANDY_REALTIME_STATUS_ORDER[orderB] ?? Number.MAX_SAFE_INTEGER;

        if (priorityA !== priorityB) {
          return priorityA - priorityB;
        }
      }

      return orderA - orderB;
    }
  );

  const getValidTimeSlots = (cast: CastWithStatus) => {
    if (Array.isArray(cast.timeSlots) && cast.timeSlots.length > 0) {
      return cast.timeSlots;
    }

    if (cast.startTime && cast.endTime) {
      return [{ startTime: cast.startTime, endTime: cast.endTime }];
    }

    return [];
  };

  const formattedUpdateTime = formatRealtimeTime(updateTime) || '--:--';

  return (
    <section
      className={clsx(styles.containerRealtime, styles[activeStoreClass])}
    >
      <ShopSwitchTabs basePath="realtime" variant="realtime" />
      <div className={styles.blockSwitchTab}></div>
      <div className={styles.blockRialTimeDetails}>
        <div className={styles.boxUpDateTime}>
          <div className={styles.itemTime}>
            <span>更新時間</span>
            <time dateTime={updateTime}>{formattedUpdateTime}</time>
          </div>
          <p>
            表示時間には多少ずれが生じる場合があります。
            <br className="sp" />
            詳しくはお電話にてご確認下さい
          </p>
        </div>
        {isError ? <p>データの読み込みに失敗しました。</p> : null}

        {statusEntries.map(([status, list]) => (
          <article
            key={status}
            className={clsx(styles.statusGroup, styles[`status${status}`])}
          >
            <h3 className={styles.statusTitle}>
              {REALTIME_STATUS_LABELS[Number(status)] ?? '未設定'}
            </h3>
            <ul className={styles.castList}>
              {list.map((cast) => {
                const grade = CastGradeMap[cast.gradeId];
                const timeSlots = getValidTimeSlots(cast);
                const realtimeDetailText = getRealTimeDetailText({
                  detail: cast.realTimeDetail,
                  availableFrom: cast.availableFrom,
                  shouldCompareTime: true,
                });

                return (
                  <li
                    key={cast.castId}
                    className={clsx(
                      styles.itemCast,
                      cast.gradeId >= 0 && styles[`grade${cast.gradeId}`]
                    )}
                  >
                    <div className={styles.innerItemCast}>
                      <Link
                        href={`/${shop}/profile/?id=${cast.castId}&type=realtime`}
                        className={styles.boxImage}
                        prefetch={false}
                      >
                        <div className={styles.wrapPhoto}>
                          {grade && grade.gradeId !== 0 ? (
                            <span className={styles.gradeLabel}>
                              {grade.labelEn}
                            </span>
                          ) : null}
                          <Image
                            src={cast.castImage}
                            alt={cast.castName}
                            fill
                            sizes="(max-width: 767px) 42vw, 10cqw"
                          />
                        </div>
                      </Link>
                      <div className={styles.boxDetails}>
                        <div className={styles.realTimeDetail}>
                          {realtimeDetailText}
                        </div>
                        <div className={styles.castProfile}>
                          <div className={styles.wrapName}>
                            <p className={styles.castName}>{cast.castName}</p>
                            <span className={styles.age}>
                              {cast.ageText || cast.age || ''}
                            </span>
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
                        <div className={styles.wrapTodayTime}>
                          <h4>出勤時間</h4>
                          {timeSlots.length > 0 || cast.scheduleStatus ? (
                            <ScheduleTimeBlock
                              timeSlots={timeSlots}
                              scheduleStatus={cast.scheduleStatus}
                              slotListClassName={styles.innerTime}
                              slotItemClassName={styles.innerTimeSlot}
                              statusClassName={styles.innerTimeStatus}
                              timeTag="span"
                              slotKeyPrefix={cast.castId}
                            />
                          ) : (
                            <div className={styles.innerTime}>
                              <span>--:--</span>
                              <span>--:--</span>
                            </div>
                          )}
                        </div>
                      </div>
                      {cast.realTimeComment ? (
                        <div className={styles.boxComment}>
                          <p>{cast.realTimeComment}</p>
                        </div>
                      ) : null}
                    </div>
                  </li>
                );
              })}
            </ul>
          </article>
        ))}
      </div>
    </section>
  );
};

export default ContainerRealtime;
