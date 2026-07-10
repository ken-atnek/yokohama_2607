export type ScheduleConfig = {
  switchHour: number;
  days: number;
};

/**
 * 指定パスの schedule-config.json を取得し、設定を返す
 * @param path 例: "hot", "villa"
 * @returns ScheduleConfig オブジェクト
 */
export const loadScheduleConfig = async (
  path: string
): Promise<ScheduleConfig> => {
  const res = await fetch(`/db/contents/${path}/schedule/schedule-config.json`);
  if (!res.ok) {
    throw new Error(`Failed to load schedule config for path: ${path}`);
  }
  return await res.json();
};
