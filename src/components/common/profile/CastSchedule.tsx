/* =======================================
 * 横浜ダンディーグループ キャスト詳細 スケジュール
 * URL: /src/components/common/profile/CastSchedule.tsx
 * Referenced in: /src/components/common/profile/CastProfile.tsx
 * Created: 2025-09-10
 * Last updated: 2026-07-16
 * ======================================= */
'use client';

import styles from './ShopCastProfile.module.scss';
import { useEffect, useState } from 'react';
import clsx from 'clsx';

type ScheduleItem = {
  date: string;
  weekday: string;
  startTime?: string;
  endTime?: string;
  scheduleStatus?: string;
};

type CastScheduleProps = {
  castId: string;
  shop: string;
};

export default function CastSchedule({ castId, shop }: CastScheduleProps) {
  const [schedule, setSchedule] = useState<ScheduleItem[]>([]);
  const [error, setError] = useState(false);

  useEffect(() => {
    const fetchSchedule = async () => {
      const paths = [
        `/db/cast/${shop}/${castId}/schedule.json`,
        `/cast/${shop}/${castId}/schedule.json`,
      ];

      try {
        const timestamp = Date.now();
        for (const path of paths) {
          const res = await fetch(`${path}?t=${timestamp}`);
          if (!res.ok) {
            continue;
          }

          const data: ScheduleItem[] = await res.json();
          setSchedule(data);
          setError(false);
          return;
        }

        setSchedule([]);
        setError(false);
      } catch {
        setError(true);
      }
    };

    fetchSchedule();
  }, [castId, shop]);

  if (error) return <p>スケジュールの読み込みに失敗しました。</p>;
  if (!schedule || schedule.length === 0) return null;

  return (
    <ul className={styles.scheduleList}>
      {schedule.map((item) => {
        const dateObj = new Date(item.date);
        return (
          <li key={item.date}>
            <div
              className={clsx(
                styles.wrapDate,
                item.weekday === 'sat' && styles.saturday,
                item.weekday === 'sun' && styles.sunday
              )}
            >
              <span className={styles.itemDate}>
                {dateObj.getMonth() + 1}/{dateObj.getDate()}
              </span>
              <span className={styles.itemWeek}>
                {item.weekday.toUpperCase()}
              </span>
            </div>
            {item.startTime && item.endTime ? (
              <div className={styles.scheduleTime}>
                <span>{item.startTime}</span>
                <span>{item.endTime}</span>
              </div>
            ) : item.scheduleStatus ? (
              <p className={styles.scheduleStatus}>{item.scheduleStatus}</p>
            ) : (
              <span className={styles.noData}>ー</span>
            )}
          </li>
        );
      })}
    </ul>
  );
}
