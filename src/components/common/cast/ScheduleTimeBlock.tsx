/* =======================================
 * 横浜ダンディーグループ スケジュール時間表示ブロック
 * URL: /src/components/common/cast/ScheduleTimeBlock.tsx
 * Referenced in: /src/components/common/cast/ShopItemCastList.tsx
 * Created: 2026-07-17
 * Last updated: 2026-07-17
 * ======================================= */
import type { ScheduleTimeSlot } from '@/types/CastDetails';

type ScheduleTimeBlockProps = {
  timeSlots: ScheduleTimeSlot[];
  scheduleStatus?: string;
  slotListClassName: string;
  slotItemClassName: string;
  statusClassName: string;
  timeTag?: 'span' | 'time';
  slotKeyPrefix: string;
};

export default function ScheduleTimeBlock({
  timeSlots,
  scheduleStatus,
  slotListClassName,
  slotItemClassName,
  statusClassName,
  timeTag = 'span',
  slotKeyPrefix,
}: ScheduleTimeBlockProps) {
  const TimeTag = timeTag;

  return (
    <>
      {timeSlots.length > 0 ? (
        <div className={slotListClassName}>
          {timeSlots.map((slot, index) => (
            <div
              key={`${slotKeyPrefix}-${index}`}
              className={slotItemClassName}
            >
              <TimeTag>{slot.startTime}</TimeTag>
              <TimeTag>{slot.endTime}</TimeTag>
            </div>
          ))}
        </div>
      ) : null}
      {scheduleStatus ? (
        <p className={statusClassName}>{scheduleStatus}</p>
      ) : null}
    </>
  );
}
