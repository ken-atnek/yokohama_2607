/* =======================================
 * ダンディーグループ 出勤お知らせメール設定リンク要求ページ
 * URL: /src/app/wgmailsys/settings-request/page.tsx
 * Referenced in: /src/app/wgmailsys/settings-request/page.tsx
 * Created: 2026-08-10
 * Last updated: 2026-08-10
 * ======================================= */
import type { Metadata } from 'next';
import WgMailSysLayout from '@/components/common/WgMailSysLayout';
import SettingsRequestClient from './SettingsRequestClient';
import styles from '../WgMailSys.module.scss';

export const metadata: Metadata = {
  title: '出勤お知らせメール 設定リンク',
  robots: 'noindex, nofollow, noarchive',
  referrer: 'no-referrer',
};

export default function DandyNotificationSettingsRequestPage() {
  return (
    <WgMailSysLayout>
      <section className={styles.wgmailsys}>
        <SettingsRequestClient />
      </section>
    </WgMailSysLayout>
  );
}
