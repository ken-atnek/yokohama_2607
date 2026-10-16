/* =======================================
 * ダンディーグループ 出勤お知らせメール全解除ページ
 * URL: /src/app/wgmailsys/unsubscribe-all/page.tsx
 * Referenced in: /src/app/wgmailsys/unsubscribe-all/page.tsx
 * Created: 2026-08-10
 * Last updated: 2026-08-10
 * ======================================= */
import type { Metadata } from 'next';
import WgMailSysLayout from '@/components/common/WgMailSysLayout';
import UnsubscribeAllClient from './UnsubscribeAllClient';
import styles from '../WgMailSys.module.scss';

export const metadata: Metadata = {
  title: '出勤お知らせメール すべて解除',
  robots: 'noindex, nofollow, noarchive',
  referrer: 'no-referrer',
};

export default function DandyNotificationUnsubscribeAllPage() {
  return (
    <WgMailSysLayout>
      <section className={styles.wgmailsys}>
        <UnsubscribeAllClient />
      </section>
    </WgMailSysLayout>
  );
}
