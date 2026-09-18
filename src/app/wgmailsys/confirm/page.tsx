/* =======================================
 * ダンディーグループ 出勤お知らせメール確認ページ
 * URL: /src/app/wgmailsys/confirm/page.tsx
 * Referenced in: /src/app/wgmailsys/confirm/page.tsx
 * Created: 2026-08-04
 * Last updated: 2026-08-04
 * ======================================= */
import { Suspense } from 'react';
import type { Metadata } from 'next';
import WgMailSysLayout from '@/components/common/WgMailSysLayout';
import ConfirmationClient from './ConfirmationClient';
import styles from '../WgMailSys.module.scss';

export const metadata: Metadata = {
  title: '出勤お知らせメール 本登録',
  robots: 'noindex, nofollow, noarchive',
  referrer: 'no-referrer',
};

export default function DandyNotificationConfirmPage() {
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
          <ConfirmationClient />
        </Suspense>
      </section>
    </WgMailSysLayout>
  );
}
