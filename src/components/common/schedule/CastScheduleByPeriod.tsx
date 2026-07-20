/* =======================================
 * 横浜ダンディーグループ 出勤情報一覧コンポーネント
 * URL: /src/components/common/schedule/CastScheduleByPeriod.tsx
 * Referenced in: /src/app/dandy/weekly-schedule/page.tsx
 * Created: 2025-08-30
 * Last updated: 2026-07-18
 * ======================================= */
'use client';
import styles from '@/styles/ShopSchedule.module.scss';
import { useEffect, useMemo, useState } from 'react';
import clsx from 'clsx';
import Link from 'next/link';
import Image from 'next/image';
import { usePathname } from 'next/navigation';
import { loadScheduleConfig } from '@/lib/loadScheduleConfig';
import { getDateList } from '@/lib/getScheduleDataList';
import { trackPageAccess } from '@/lib/accessCounterApi';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import type { CastDetail } from '@/types/CastDetails';
import ShopSwitchTabs from '@/components/common/ShopSwitchTabs';
import ScheduleTimeBlock from '@/components/common/cast/ScheduleTimeBlock';
type ScheduleData = {
  date: string;
  casts: CastDetail[];
};

const getValidTimeSlots = (cast?: CastDetail) => {
  if (!cast) return [];

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

const CastScheduleByPeriod = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

  const [schedules, setSchedules] = useState<ScheduleData[]>([]);
  const [isError, setIsError] = useState(false);

  // データ取得
  useEffect(() => {
    if (!shop) return;

    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
    const pageId = 'scheduleByPeriod';
    trackPageAccess(shop, pageId);
    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する

    const fetchSchedules = async () => {
      try {
        setIsError(false);
        const config = await loadScheduleConfig(shop);
        const dateList = getDateList(config.switchHour, config.days);
        const basePath = `/db/contents/${shop}/schedule`;

        // スケジュールデータは常にキャッシュバスティング
        const timestamp = Date.now();
        const timestampParam = `?t=${timestamp}`;

        const results = await Promise.all(
          dateList.map(async (date) => {
            const response = await fetch(
              `${basePath}/${date}.json${timestampParam}`,
              { cache: 'no-store' }
            );
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            const json = await response.json();
            return json as ScheduleData;
          })
        );

        setSchedules(results);
      } catch {
        setIsError(true);
      }
    };

    fetchSchedules();
  }, [shop]);

  // キャストIDで重複除去
  const periodScheduleList = useMemo(() => {
    const map = new Map<string, CastDetail>();
    schedules.forEach((schedule) => {
      schedule.casts.forEach((cast) => {
        if (!map.has(cast.castId)) {
          map.set(cast.castId, cast);
        }
      });
    });
    return Array.from(map.values());
  }, [schedules]);

  // 並び順を保存（詳細ページで prev/next に使用）
  useEffect(() => {
    if (periodScheduleList.length > 0) {
      const castOrder = periodScheduleList.map((c) => ({
        id: c.castId,
        name: c.castName,
      }));
      // 修正: 用途ごとにキーを分ける
      sessionStorage.setItem(
        'castOrder_scheduleperiod',
        JSON.stringify(castOrder)
      );
    }
  }, [periodScheduleList]);

  return (
    <>
      <section className={clsx(styles.containerHead, styles[activeStoreClass])}>
        <Link href={`/${shop}/schedule/`} className={styles.linkByDay}>
          日別表示はコチラ
        </Link>
        {schedules.length > 0 && (
          <div className={styles.boxPeriod}>
            <time dateTime={schedules[0].date}>
              {new Date(schedules[0].date).getFullYear()}.
              {new Date(schedules[0].date).getMonth() + 1}.
              {new Date(schedules[0].date).getDate()}
              <span>
                {
                  ['日', '月', '火', '水', '木', '金', '土'][
                    new Date(schedules[0].date).getDay()
                  ]
                }
              </span>
            </time>
            <time dateTime={schedules[schedules.length - 1].date}>
              {new Date(schedules[schedules.length - 1].date).getFullYear()}.
              {new Date(schedules[schedules.length - 1].date).getMonth() + 1}.
              {new Date(schedules[schedules.length - 1].date).getDate()}
              <span>
                {
                  ['日', '月', '火', '水', '木', '金', '土'][
                    new Date(schedules[schedules.length - 1].date).getDay()
                  ]
                }
              </span>
            </time>
            <p>の出勤表</p>
          </div>
        )}
        {/* 店舗切替タブ */}
        <ShopSwitchTabs basePath="weekly-schedule" variant="weekly-schedule" />
      </section>
      <section
        className={clsx(styles.containerContents, styles[activeStoreClass])}
      >
        {isError ? <p>データの読み込みに失敗しました。</p> : null}
        <article className={styles.blockPeriod}>
          <ul
            className={`${styles.scheduleList} ${styles['scheduleList--' + schedules.length]}`}
          >
            {/* ヘッダー（日付）行 */}
            <li className={styles.rowHeader}>
              <div className={styles.cellCastHeader}>NAME</div>
              {schedules.map((schedule, index) => (
                <div
                  key={`head-${schedule.date}-${index}`}
                  className={styles.cellDate}
                >
                  <time dateTime={schedule.date}>
                    {(() => {
                      const d = new Date(schedule.date);
                      const youbi = ['日', '月', '火', '水', '木', '金', '土'];
                      return (
                        <>
                          {d.getMonth() + 1}/{d.getDate()}
                          <span>{youbi[d.getDay()]}</span>
                        </>
                      );
                    })()}
                  </time>
                </div>
              ))}
            </li>

            {/* キャストごとの行 */}
            {periodScheduleList.map((cast) => (
              <li key={cast.castId} className={styles.row}>
                {/* キャスト情報 */}
                <div className={styles.boxCastInfo}>
                  <Link
                    href={`/${shop}/profile/?id=${cast.castId}&type=scheduleperiod`}
                    className={styles.wrapImage}
                  >
                    <Image
                      src={
                        cast.castImageSquare && cast.castImageSquare !== ''
                          ? cast.castImageSquare
                          : cast.castImage && cast.castImage !== ''
                            ? cast.castImage
                            : `/images/cast/${shop}/no-image.webp`
                      }
                      alt={cast.castName}
                      fill
                    />
                  </Link>
                  <div className={styles.castProfile}>
                    <div className={styles.wrapName}>
                      <p className={styles.castName}>{cast.castName}</p>
                      <span className={styles.age}>{cast.age}</span>
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
                </div>

                {/* 各日の出勤時間（または空欄） */}
                {schedules.map((schedule) => {
                  const match = schedule.casts.find(
                    (c) => c.castId === cast.castId
                  );
                  const timeSlots = getValidTimeSlots(match);
                  return (
                    <div
                      key={`${cast.castId}-${schedule.date}`}
                      className={styles.wrapDate}
                    >
                      <ScheduleTimeBlock
                        timeSlots={timeSlots}
                        scheduleStatus={match?.scheduleStatus}
                        slotListClassName={styles.scheduleTimeSlots}
                        slotItemClassName={styles.scheduleTimeSlot}
                        statusClassName={styles.scheduleStatus}
                        timeTag="time"
                        slotKeyPrefix={`${cast.castId}-${schedule.date}`}
                      />
                    </div>
                  );
                })}
              </li>
            ))}
          </ul>
        </article>
      </section>
    </>
  );
};
export default CastScheduleByPeriod;
