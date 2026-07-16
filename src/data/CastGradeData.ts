/* =======================================
 * キャストグレード定義
 * URL:/src/data/CastGradeData.ts
 * Created: 2026-07-09
 * Last updated: 2026-07-16
 * ======================================= */

export type CastGrade = {
  gradeId: number;
  label: string;
  labelEn: string;
};

export const CastGrades: CastGrade[] = [
  { gradeId: 0, label: 'なし', labelEn: 'none' },
  { gradeId: 1, label: 'シルバー', labelEn: 'silver' },
  { gradeId: 8, label: 'レジェンド', labelEn: 'legend' },
  { gradeId: 2, label: 'ゴールド', labelEn: 'gold' },
  { gradeId: 3, label: 'VIP', labelEn: 'vip' },
  { gradeId: 4, label: 'グランド VIP', labelEn: 'grand vip' },
  { gradeId: 5, label: 'プレミアム', labelEn: 'premium' },
  { gradeId: 6, label: 'ダイヤモンド', labelEn: 'diamond' },
  { gradeId: 7, label: 'ロイヤルクイーン', labelEn: 'royal queen' },
];

export const CastGradeMap = Object.fromEntries(
  CastGrades.map((grade) => [grade.gradeId, grade])
) as Record<number, CastGrade>;
