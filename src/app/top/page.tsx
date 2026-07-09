/* =======================================
 * 横浜ダンディーグループ エリアTOPページ
 * URL: /src/app/top/page.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-09
 * ======================================= */

import styles from '@/styles/AreaTop.module.scss';
import type { Metadata } from 'next';
import Footer from '@/components/common/Footer';
import GroupLogo from '/public/images/logo/dandy-g-logo.webp';
import Image from 'next/image';
import BlockTelop from '@/components/area/BlockTelop';
import BlockPickUp from '@/components/area/BlockPickUp';
// import BannerGroup from '@/components/common/BannerGroup';
// import BlockNewFace from '@/components/area/BlockNewFace';
import ContainerShopList from '@/components/common/ContainerShopList';
import { isRealProduction } from '@/lib/env';

export const generateMetadata = (): Metadata => {
  return {
    title: '横浜の風俗｜ファッションヘルス:横浜ダンディーグループ',
    description: isRealProduction
      ? '横浜 風俗の横浜ダンディーグループは横浜エリアのファッションヘルスです。横浜で魅力的な女性たちとの特別なお時間をお過ごしください。'
      : undefined,
  };
};

export default function AreaTop() {
  // 最下層コンポーネントを止めているため、データ取得用の timestamp もいったん未使用
  // const timestamp = Date.now();

  return (
    <>
      <main className={styles.areaTop}>
        <h1>横浜の風俗｜ファッションヘルス:横浜ダンディーグループ</h1>
        <section className={styles.containerHeadTitle}>
          <Image src={GroupLogo} alt="横浜ダンディーグループ" priority />
          <p>横浜ダンディーグループ</p>
        </section>
        <section className={styles.containerPickUp}>
          {/* PICK UP: 最下層コンポーネントのため初期構築では停止 */}
          <BlockPickUp />
        </section>
        <section className={styles.containerTelop}>
          <h2>news</h2>
          {/* TELOP: 最下層コンポーネントのため初期構築では停止 */}
          <BlockTelop />
        </section>
        {/* SHOP LIST: 最下層コンポーネントのため初期構築では停止 */}
        <ContainerShopList />
        <section className={styles.containerContents}>
          <article className={styles.innerContainerContents}>
            <div className={styles.boxLeftBan}>
              <h2 className={styles.h2Topics}>
                <span>topics</span>トピックス
              </h2>
              {/* TOPICS BANNER: 最下層コンポーネントのため初期構築では停止 */}
              {/* <BannerGroup
                jsonPath={`/data/area-top/areaTopLeftBanGroup.json?t=${timestamp}`}
                title="kobe area event"
                className={styles.boxGroup}
              />
              <BannerGroup
                jsonPath={`/data/area-top/areaTopLeftBanHot.json?t=${timestamp}`}
                title="kobe hotpoint event"
                className={styles.boxHot}
              />
              <BannerGroup
                jsonPath={`/data/area-top/areaTopLeftBanVilla.json?t=${timestamp}`}
                title="hotpoint villa event"
                className={styles.boxVilla}
              />
              <BannerGroup
                jsonPath={`/data/area-top/areaTopLeftBanRecruit.json?t=${timestamp}`}
                title="recruit"
                className={styles.boxRecruit}
              /> */}
            </div>
            <div className={styles.boxMainContents}>
              {/* MAIN BANNER: 最下層コンポーネントのため初期構築では停止 */}
              {/* <BannerGroup
                jsonPath={`/data/area-top/areaTopMainHead.json?t=${timestamp}`}
                className={styles.wrapMainBanHead}
              /> */}
              <div className={styles.wrapNewFace}>
                <h2 className={styles.itemH2}>
                  <span>new face</span>
                  新人情報
                </h2>
                {/* NEW FACE: 最下層コンポーネントのため初期構築では停止 */}
                {/* <BlockNewFace /> */}
              </div>
            </div>
          </article>
        </section>
      </main>
      <Footer className={styles.areaFooter} />
    </>
  );
}
