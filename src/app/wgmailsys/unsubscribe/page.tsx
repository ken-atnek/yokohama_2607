/* =======================================
 * ダンディーグループ 出勤お知らせメール個別解除ページ
 * URL: /src/app/wgmailsys/unsubscribe/page.tsx
 * Referenced in: /src/app/wgmailsys/unsubscribe/page.tsx
 * Created: 2026-08-10
 * Last updated: 2026-08-10
 * ======================================= */
import { Suspense } from 'react';
import type { Metadata } from 'next';
import WgMailSysLayout from '@/components/common/WgMailSysLayout';
import UnsubscribeClient from './UnsubscribeClient';
import styles from '../WgMailSys.module.scss';

export const metadata: Metadata = {
  title: '出勤お知らせメール 解除',
  robots: 'noindex, nofollow, noarchive',
  referrer: 'no-referrer',
};

export default function DandyNotificationUnsubscribePage() {
  return (
    <WgMailSysLayout>
      <section className={styles.wgmailsys}>
        <Suspense
          fallback={
            <div className={styles.panel}>
              <p className={styles.message}>確認しています。</p>
            </div>
          }
        >
          <UnsubscribeClient />
        </Suspense>
      </section>
    </WgMailSysLayout>
  );
}
