/* =======================================
 * ダンディーグループ 出勤お知らせメール設定ページ
 * URL: /src/app/wgmailsys/settings/page.tsx
 * Referenced in: /src/app/wgmailsys/settings/page.tsx
 * Created: 2026-08-10
 * Last updated: 2026-08-10
 * ======================================= */
import { Suspense } from 'react';
import type { Metadata } from 'next';
import WgMailSysLayout from '@/components/common/WgMailSysLayout';
import SettingsClient from './SettingsClient';
import styles from '../WgMailSys.module.scss';

export const metadata: Metadata = {
  title: '出勤お知らせメール 設定',
  robots: 'noindex, nofollow, noarchive',
  referrer: 'no-referrer',
};

export default function DandyNotificationSettingsPage() {
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
          <SettingsClient />
        </Suspense>
      </section>
    </WgMailSysLayout>
  );
}
