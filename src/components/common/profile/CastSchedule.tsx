/* =======================================
 * 横浜ダンディーグループ キャスト詳細 スケジュール
 * URL: /src/components/common/profile/CastSchedule.tsx
 * Referenced in: /src/components/common/profile/CastProfile.tsx
 * Created: 2025-09-10
 * Last updated: 2026-07-17
 * ======================================= */
'use client';

import styles from './ShopCastProfile.module.scss';
import { useEffect, useState } from 'react';
import clsx from 'clsx';

type ScheduleTimeSlot = {
  startTime?: string;
  endTime?: string;
};

type ScheduleItem = {
  date: string;
  weekday: string;
  startTime?: string;
  endTime?: string;
  scheduleStatus?: string;
  timeSlots?: ScheduleTimeSlot[];
};

type ScheduleData = {
  displayType?: 'calendar' | 'comment';
  comment?: string;
  schedule?: ScheduleItem[];
};

type CastScheduleProps = {
  castId: string;
  shop: string;
};

function normalizeScheduleItem(
  item: Partial<ScheduleItem>
): ScheduleItem | null {
  if (typeof item.date !== 'string' || typeof item.weekday !== 'string') {
    return null;
  }

  const timeSlots =
    Array.isArray(item.timeSlots) && item.timeSlots.length > 0
      ? item.timeSlots.filter(
          (slot): slot is ScheduleTimeSlot =>
            typeof slot === 'object' &&
            slot !== null &&
            typeof slot.startTime === 'string' &&
            typeof slot.endTime === 'string' &&
            slot.startTime !== '' &&
            slot.endTime !== ''
        )
      : typeof item.startTime === 'string' &&
          item.startTime !== '' &&
          typeof item.endTime === 'string' &&
          item.endTime !== ''
        ? [{ startTime: item.startTime, endTime: item.endTime }]
        : [];

  return {
    date: item.date,
    weekday: item.weekday,
    startTime: typeof item.startTime === 'string' ? item.startTime : undefined,
    endTime: typeof item.endTime === 'string' ? item.endTime : undefined,
    scheduleStatus:
      typeof item.scheduleStatus === 'string' ? item.scheduleStatus : undefined,
    timeSlots,
  };
}

function normalizeScheduleData(data: unknown): ScheduleData {
  if (Array.isArray(data)) {
    return {
      displayType: 'calendar',
      schedule: data
        .map((item) => normalizeScheduleItem(item))
        .filter((item): item is ScheduleItem => item !== null),
    };
  }

  if (typeof data !== 'object' || data === null) {
    return { displayType: 'calendar', schedule: [] };
  }

  const source = data as ScheduleData;
  const schedule = Array.isArray(source.schedule)
    ? source.schedule
        .map((item) => normalizeScheduleItem(item))
        .filter((item): item is ScheduleItem => item !== null)
    : [];

  return {
    displayType: source.displayType === 'comment' ? 'comment' : 'calendar',
    comment: typeof source.comment === 'string' ? source.comment : '',
    schedule,
  };
}

export default function CastSchedule({ castId, shop }: CastScheduleProps) {
  const [scheduleData, setScheduleData] = useState<ScheduleData>({
    displayType: 'calendar',
    comment: '',
    schedule: [],
  });
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

          const data = await res.json();
          setScheduleData(normalizeScheduleData(data));
          setError(false);
          return;
        }

        setScheduleData({
          displayType: 'calendar',
          comment: '',
          schedule: [],
        });
        setError(false);
      } catch {
        setError(true);
      }
    };

    fetchSchedule();
  }, [castId, shop]);

  if (error) return <p>スケジュールの読み込みに失敗しました。</p>;
  if (scheduleData.displayType === 'comment') {
    if (!scheduleData.comment) return null;

    return <p className={styles.scheduleComment}>{scheduleData.comment}</p>;
  }

  if (!scheduleData.schedule || scheduleData.schedule.length === 0) return null;

  return (
    <ul className={styles.scheduleList}>
      {scheduleData.schedule.map((item) => {
        const dateObj = new Date(item.date);
        const timeSlots = item.timeSlots || [];

        return (
          <li key={item.date}>
            <div
              className={clsx(
                styles.wrapDate,
                item.weekday === 'sat' && styles.saturday,
                item.weekday === 'sun' && styles.sunday
              )}
            >
              <span className={styles.itemWeek}>
                {item.weekday.toUpperCase()}
              </span>
              <span className={styles.itemDate}>
                {dateObj.getMonth() + 1}/{dateObj.getDate()}
              </span>
            </div>
            {timeSlots.length > 0 ? (
              <div className={styles.scheduleTime}>
                {timeSlots.map((slot, index) => (
                  <div
                    className={styles.scheduleSlot}
                    key={`${item.date}-${index}`}
                  >
                    <span>{slot.startTime}</span>
                    <span>{slot.endTime}</span>
                  </div>
                ))}
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
