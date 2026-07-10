/* =======================================
 * 横浜ダンディーグループ グループエントランス本体
 * URL: /src/components/entrance/Entrance.tsx
 * Referenced in: /src/app/page.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */

'use client';

import Image from 'next/image';
import clsx from 'clsx';
import { useRouter } from 'next/navigation';
import styles from '@/components/entrance/Entrance.module.scss';
import EntranceCastList from '@/components/entrance/CastList';
import ReciprocalLink from '@/components/entrance/ReciprocalLink';
import EntranceAreaShopList from '@/components/entrance/AreaShopList';
import EntranceGroupShopList from '@/components/entrance/GroupShopList';
import { Shops } from '@/data/AreaShopData';
import { setAgeVerified } from '@/lib/age';
import GroupLogo from '/public/images/logo/dg.webp';

type EntranceScope = 'group' | 'dandy' | 'mr_dandy' | 'club_dandy';

export default function Entrance({
  scope,
  backPath,
  excludeStoreId,
}: {
  scope: EntranceScope;
  backPath: string;
  excludeStoreId?: string;
}) {
  const router = useRouter();
  const currentShop = Shops.find((shop) => shop.storeId === scope);
  const announceLogo = currentShop?.logo ?? GroupLogo;
  const announceAlt = currentShop?.name ?? '横浜ダンディーグループ';

  return (
    <div className={styles.containerEntrance}>
      <section className={styles.blockCastList}>
        <EntranceCastList />
      </section>

      <section className={styles.blockButton}>
        <button
          type="button"
          className={styles.itemButton}
          onClick={() => {
            setAgeVerified();
            router.replace(backPath);
          }}
        >
          yes
        </button>
        <div className={clsx(styles.boxAnnounce)}>
          <Image src={announceLogo} alt={announceAlt} width={180} height={72} />
          <p>あなたは18歳以上ですか？</p>
        </div>
        <a
          href="https://www.yahoo.co.jp/"
          aria-label="Yahooへ"
          className={styles.itemButton}
        >
          no
        </a>
      </section>

      <section className={styles.blockAreaShop}>
        <h2 className={styles.itemH3}>yokohama shop list</h2>
        <EntranceAreaShopList excludeStoreId={excludeStoreId} />
      </section>

      <section className={styles.blockReciprocalLink}>
        <ReciprocalLink />
      </section>

      <section className={styles.blockGroupShop}>
        <h2 className={styles.itemH3}>group shop list</h2>
        <EntranceGroupShopList />
      </section>

      <div className={styles.copyRight}>
        <p>
          当サイトはアダルトな内容を含んでいます。
          <br className="sp" />
          18歳未満の方の閲覧は堅くご遠慮願います。
        </p>
      </div>
    </div>
  );
}
