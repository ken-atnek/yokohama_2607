/* =======================================
 * 横浜ダンディーグループ 出勤情報日別コンポーネント
 * URL: /src/components/common/schedule/CastScheduleByDay.tsx
 * Referenced in: /src/app/dandy/schedule/page.tsx
 * Created: 2025-09-01
 * Last updated: 2026-07-17
 * ======================================= */
'use client';

import styles from '@/styles/ShopSchedule.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import Link from 'next/link';
import { useEffect, useState } from 'react';
import type { CastDetail } from '@/types/CastDetails';
import { loadScheduleConfig } from '@/lib/loadScheduleConfig';
import { getDateList } from '@/lib/getScheduleDataList';
import { getRealTimeDetailText } from '@/lib/getRealTimeDetailText';
import { trackPageAccess } from '@/lib/accessCounterApi';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import ShopSwitchTabs from '@/components/common/ShopSwitchTabs';
import ShopItemCastList from '@/components/common/cast/ShopItemCastList';
import ScheduleTimeBlock from '@/components/common/cast/ScheduleTimeBlock';

type ScheduleData = {
  date: string;
  casts: CastDetail[];
};

type RealTimeCast = {
  castId: string;
  realTimeStatus?: number;
  realTimeDetail?: string;
  availableFrom?: string;
};

type RealTimeData = {
  updatedAt?: string;
  casts?: RealTimeCast[];
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

const buildRealTimeMap = (data: unknown) => {
  if (typeof data !== 'object' || data === null) {
    return new Map<string, RealTimeCast>();
  }

  const source = data as RealTimeData;
  if (!Array.isArray(source.casts)) {
    return new Map<string, RealTimeCast>();
  }

  return new Map(
    source.casts
      .filter(
        (cast): cast is RealTimeCast =>
          typeof cast === 'object' &&
          cast !== null &&
          typeof cast.castId === 'string' &&
          cast.castId !== ''
      )
      .map((cast) => [cast.castId, cast])
  );
};

const CastScheduleByDay = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

  const [schedules, setSchedules] = useState<ScheduleData[]>([]);
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [isError, setIsError] = useState(false);
  const [realTimeMap, setRealTimeMap] = useState<Map<string, RealTimeCast>>(
    new Map()
  );

  // 日付に対応するスケジュールを取得
  const dailyScheduleList = schedules.find((s) => s.date === selectedDate);

  // スケジュールの読み込み
  useEffect(() => {
    if (!shop) return;

    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
    const pageId = 'scheduleByDay';
    trackPageAccess(shop, pageId);
    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する

    const loadSchedules = async () => {
      try {
        setIsError(false);
        const config = await loadScheduleConfig(shop);
        const dateList = getDateList(config.switchHour, config.days);
        const basePath = `/db/contents/${shop}/schedule`;
        const realTimePath = `/db/contents/${shop}/schedule/realtime-today.json`;

        // スケジュールデータは常にキャッシュバスティング
        const timestamp = Date.now();
        const timestampParam = `?t=${timestamp}`;

        const fetched = await Promise.all(
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

        const realTimeResponse = await fetch(
          `${realTimePath}${timestampParam}`,
          { cache: 'no-store' }
        );
        const nextRealTimeMap = realTimeResponse.ok
          ? buildRealTimeMap(await realTimeResponse.json())
          : new Map<string, RealTimeCast>();

        setSchedules(fetched);
        setRealTimeMap(nextRealTimeMap);
      } catch {
        setIsError(true);
        setRealTimeMap(new Map());
      }
    };

    loadSchedules();
  }, [shop]);

  // 初回ロード時、最初の日付を自動で選択
  useEffect(() => {
    if (schedules.length > 0 && !selectedDate) {
      setSelectedDate(schedules[0].date);
    }
  }, [schedules, selectedDate]);

  // 表示キャスト順を sessionStorage に保存
  useEffect(() => {
    if (dailyScheduleList?.casts?.length) {
      const castOrder = dailyScheduleList.casts.map((c) => ({
        id: c.castId,
        name: c.castName,
      }));
      // 修正: 用途ごとにキーを分ける
      sessionStorage.setItem(
        'castOrder_schedulebyday',
        JSON.stringify(castOrder)
      );
    }
  }, [dailyScheduleList]);
  return (
    <>
      <section className={clsx(styles.containerHead, styles[activeStoreClass])}>
        <Link
          href={`/${shop}/weekly-schedule/`}
          className={styles.linkByPeriod}
        >
          一覧表示はコチラ
        </Link>

        {/* 店舗切替タブ */}
        <ShopSwitchTabs basePath="schedule" variant="schedule" />
      </section>

      <section
        className={clsx(styles.containerContents, styles[activeStoreClass])}
      >
        {isError ? <p>データの読み込みに失敗しました。</p> : null}
        <article className={styles.blockByDay}>
          {/* 日付タブ */}
          <nav>
            {schedules.map((schedule) => {
              const dateObj = new Date(schedule.date);
              const display = `${dateObj.getMonth() + 1}/${dateObj.getDate()}`;
              const youbi = ['日', '月', '火', '水', '木', '金', '土'][
                dateObj.getDay()
              ];
              return (
                <div
                  key={schedule.date}
                  className={clsx({
                    [styles.isActive]: schedule.date === selectedDate,
                  })}
                >
                  <button
                    onClick={() => setSelectedDate(schedule.date)}
                    className={clsx(styles.tabButton, {
                      [styles.isActive]: schedule.date === selectedDate,
                    })}
                  >
                    {display}
                    <span>{youbi}</span>
                  </button>
                </div>
              );
            })}
          </nav>
          {/* キャスト一覧 */}
          <div className={styles.blockCastList}>
            {dailyScheduleList && (
              <ul
                className={clsx(styles.castList, {
                  [styles.isToday]: selectedDate === schedules[0]?.date,
                })}
              >
                {dailyScheduleList.casts.map((cast) => {
                  const timeSlots = getValidTimeSlots(cast);
                  const isToday =
                    selectedDate === schedules[0]?.date && schedules.length > 0;
                  const realTime = isToday
                    ? realTimeMap.get(cast.castId)
                    : undefined;
                  const realTimeStatus =
                    typeof realTime?.realTimeStatus === 'number'
                      ? realTime.realTimeStatus
                      : 0;
                  const realTimeDetail =
                    typeof realTime?.realTimeDetail === 'string'
                      ? realTime.realTimeDetail
                      : '';
                  const availableFrom =
                    typeof realTime?.availableFrom === 'string'
                      ? realTime.availableFrom
                      : '';
                  const displayRealTimeDetail = getRealTimeDetailText({
                    detail: realTimeDetail,
                    availableFrom,
                    shouldCompareTime: isToday,
                  });
                  return (
                    <ShopItemCastList
                      key={cast.castId}
                      cast={cast}
                      linkType="schedulebyday"
                      showDefaultScheduleInfo={false}
                      showServiceList
                      variant="schedule"
                      topSlot={
                        <>
                          {realTimeStatus ? (
                            <div
                              className={clsx(
                                styles.realTImeDetail,
                                styles[`status${realTimeStatus}`]
                              )}
                            >
                              {displayRealTimeDetail}
                            </div>
                          ) : (
                            <div className={styles.realTImeNoDetail} />
                          )}
                          <div className={styles.wrapTodayTime}>
                            <ScheduleTimeBlock
                              timeSlots={timeSlots}
                              scheduleStatus={cast.scheduleStatus}
                              slotListClassName={styles.scheduleTimeSlot}
                              slotItemClassName={styles.innerScheduleTimeSlot}
                              statusClassName={styles.scheduleStatus}
                              timeTag="span"
                              slotKeyPrefix={cast.castId}
                            />
                          </div>
                        </>
                      }
                    />
                  );
                })}
              </ul>
            )}
          </div>
        </article>
      </section>
    </>
  );
};

export default CastScheduleByDay;
