/* =======================================
 * ダンディーグループ 出勤お知らせメール完了ページ
 * URL: /src/app/wgmailsys/complete/page.tsx
 * Referenced in: /src/app/wgmailsys/complete/page.tsx
 * Created: 2026-08-04
 * Last updated: 2026-09-03
 * ======================================= */
import clsx from 'clsx';
import type { Metadata } from 'next';
import WgMailSysLayout from '@/components/common/WgMailSysLayout';
import styles from '../WgMailSys.module.scss';

export const metadata: Metadata = {
  title: '本登録が完了しました',
  robots: 'noindex, nofollow, noarchive',
  referrer: 'no-referrer',
};

export default function DandyNotificationCompletePage() {
  return (
    <WgMailSysLayout>
      <section className={styles.wgmailsys}>
        <div className={styles.panel}>
          <h1 className={styles.heading}>本登録が完了しました</h1>
          <p className={clsx(styles.messageBox, styles.messageSuccess)}>
            出勤お知らせメールの登録が完了しました。
          </p>
        </div>
      </section>
    </WgMailSysLayout>
  );
}
