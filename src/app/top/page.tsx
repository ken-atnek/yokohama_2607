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
// import BlockTelop from '@/components/area/BlockTelop';
import BlockPickUp from '@/components/area/BlockPickUp';
import BannerGroup from '@/components/common/BannerGroup';
import bannerGroupStyles from '@/components/common/BannerGroup.module.scss';
import BlockNewFace from '@/components/area/BlockNewFace';
import CastRanking from '@/components/common/cast/CastRanking';
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
  // 🔽 タイムスタンプでキャッシュバスティング
  const timestamp = Date.now();

  return (
    <>
      <main className={styles.areaTop}>
        <h1>横浜の風俗｜ファッションヘルス:横浜ダンディーグループ</h1>
        <section className={styles.containerHeadTitle}>
          <Image src={GroupLogo} alt="横浜ダンディーグループ" priority />
          <p>横浜ダンディーグループ</p>
        </section>
        <section className={styles.containerPickUp}>
          <BlockPickUp />
        </section>
        {/* <section className={styles.containerTelop}>
          <h2>news</h2>
          <BlockTelop />
        </section> */}
        <ContainerShopList />
        <section className={styles.containerContents}>
          <article className={styles.innerContainerContents}>
            <div className={styles.boxLeftBan}>
              <h2 className={styles.h2Topics}>
                <span>topics</span>トピックス
              </h2>
              <BannerGroup
                jsonPath={`/db/contents/area/areaTopLeftBanArea.json?t=${timestamp}`}
                title="Yokohama area event"
                className={bannerGroupStyles.boxAreaGroup}
              />
              <BannerGroup
                jsonPath={`/db/contents/area/areaTopLeftBanDandy.json?t=${timestamp}`}
                title="yokohama dandy event"
                className={bannerGroupStyles.boxAreaDandy}
              />
              <BannerGroup
                jsonPath={`/db/contents/area/areaTopLeftBanMr.json?t=${timestamp}`}
                title="mr dandy event"
                className={bannerGroupStyles.boxAreaMr}
              />
              <BannerGroup
                jsonPath={`/db/contents/area/areaTopLeftBanClub.json?t=${timestamp}`}
                title="club dandy event"
                className={bannerGroupStyles.boxAreaClub}
              />
              <BannerGroup
                jsonPath={`/db/contents/area/areaTopLeftBanRecruit.json?t=${timestamp}`}
                title="recruit"
                className={bannerGroupStyles.boxAreaRecruit}
              />
              <div className={styles.boxVanilla}>
                <iframe src="https://qzin.jp/1021/widget?d=0" seamless></iframe>
              </div>
            </div>
            <div className={styles.boxMainContents}>
              <BannerGroup
                jsonPath={`/db/contents/area/areaTopMainHead.json?t=${timestamp}`}
                className={bannerGroupStyles.wrapMainBanHead}
              />
              <div className={styles.wrapNewFace}>
                <h2 className={styles.itemH2}>
                  <span>new face</span>
                  新人情報
                </h2>
                <BlockNewFace />
              </div>
              <div className={styles.wrapRanking}>
                <h2 className={styles.itemH2}>6月度指名ランキング</h2>
                <CastRanking
                  jsonPath="/db/contents/area/areaMainRanking.json"
                  showShopName={true}
                  rankIconType="area"
                />
              </div>
              <div className={styles.wrapBanInformation}>
                <h2 className={styles.itemH2}>
                  <span>Information</span>
                  インフォメーション
                </h2>
                <BannerGroup
                  jsonPath={`/db/contents/area/areaTopMainInformation.json?t=${timestamp}`}
                  className={bannerGroupStyles.wrapMainBanInformation}
                />
              </div>
              <div className={styles.wrapBanBottom}>
                <BannerGroup
                  jsonPath={`/db/contents/area/areaTopMainBottom.json?t=${timestamp}`}
                  className={bannerGroupStyles.wrapMainBanBottom}
                />
              </div>
            </div>
          </article>
        </section>
      </main>
      <Footer className={styles.areaFooter} />
    </>
  );
}
