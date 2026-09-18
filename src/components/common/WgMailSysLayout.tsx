/* =======================================
 * ダンディーグループ 出勤お知らせメール共通レイアウト
 * URL: /src/components/common/WgMailSysLayout.tsx
 * Referenced in: /src/app/wgmailsys route pages
 * Created: 2026-09-03
 * Last updated: 2026-09-03
 * ======================================= */
import Footer from '@/components/common/Footer';
import styles from '@/app/wgmailsys/WgMailSys.module.scss';

export default function WgMailSysLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className={styles.serviceShell}>
      <header className={styles.serviceHeader} aria-label="出勤お知らせメール">
        <div className={styles.serviceHeaderInner}>
          <p className={styles.serviceBrand}>横浜ダンディーグループ</p>
          <p className={styles.serviceTitle}>出勤お知らせメール</p>
        </div>
      </header>
      <main className={styles.serviceMain}>{children}</main>
      <div className={styles.serviceFooter}>
        <Footer />
      </div>
    </div>
  );
}
