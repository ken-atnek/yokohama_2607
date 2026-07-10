/* =======================================
 * 横浜ダンディーグループ グループ店舗一覧
 * URL: /src/components/entrance/GroupShopList.tsx
 * Referenced in: /src/components/entrance/Entrance.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */

'use client';
import ExternalLink from '@/components/common/ExternalLink';
import styles from '@/components/entrance/Entrance.module.scss';
import {
  useState,
  useEffect,
  useCallback,
  useRef,
  type CSSProperties,
} from 'react';
import { GroupShops } from '@/data/GroupShopData';

const areaLabels: { [key: string]: string } = {
  yokohama: '横浜エリア',
  kyoto: '京都エリア',
  kobe: '神戸エリア',
  fukuoka: '福岡エリア',
  kumamoto: '熊本エリア',
};

// Group shops by area
const groupByArea = (shops: typeof GroupShops) => {
  return shops.reduce((acc: { [key: string]: typeof GroupShops }, shop) => {
    if (!acc[shop.area]) acc[shop.area] = [];
    acc[shop.area].push(shop);
    return acc;
  }, {});
};

const TRANSITION_MS = 260;

const EntranceGroupShopList = () => {
  const grouped = groupByArea(GroupShops);
  const areaKeys = Object.keys(grouped);

  // PC版の状態
  const [selectedArea, setSelectedArea] = useState<string | null>(null);
  const [renderArea, setRenderArea] = useState<string | null>(null);

  // スマホ版の状態（エリアごとの開閉状態）
  const [expandedAreas, setExpandedAreas] = useState<Set<string>>(new Set());

  // パネルの高さをJSでアニメ（PC版用）
  const panelRef = useRef<HTMLUListElement>(null);
  const [panelStyle, setPanelStyle] = useState<CSSProperties>({
    height: '0px',
    overflow: 'hidden',
    transition: `height ${TRANSITION_MS}ms ease`,
  });

  // PC版のボタンクリック
  const handleSelect = useCallback((areaId: string) => {
    setSelectedArea((prev) => {
      if (prev === areaId) {
        return null;
      }
      setRenderArea(areaId);
      return areaId;
    });
  }, []);

  // スマホ版のアコーディオン切替
  const toggleMobileArea = useCallback((areaId: string) => {
    setExpandedAreas((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(areaId)) {
        newSet.delete(areaId);
      } else {
        newSet.add(areaId);
      }
      return newSet;
    });
  }, []);

  // PC版の初回オープンやキーボード操作時の整合性
  useEffect(() => {
    if (selectedArea && renderArea !== selectedArea) {
      setRenderArea(selectedArea);
    }
  }, [selectedArea, renderArea]);

  // PC版のオープン/クローズ/切替ごとの高さアニメを制御
  useEffect(() => {
    const el = panelRef.current;
    if (!el) return;

    const forceReflow = () => void el.offsetHeight;

    if (selectedArea === null) {
      const current = getComputedStyle(el).height;
      setPanelStyle((s) => ({ ...s, height: current }));
      forceReflow();
      setPanelStyle((s) => ({ ...s, height: '0px' }));
      return;
    }

    const from = getComputedStyle(el).height;
    setPanelStyle((s) => ({ ...s, height: from }));
    forceReflow();

    const to = `${el.scrollHeight}px`;
    setPanelStyle((s) => ({ ...s, height: to }));
  }, [selectedArea, renderArea]);

  // PC版のアニメ完了後の後片付け
  const handleTransitionEnd = () => {
    const el = panelRef.current;
    if (!el) return;

    if (selectedArea) {
      setPanelStyle((s) => ({ ...s, height: 'auto' }));
    } else {
      setRenderArea(null);
    }
  };

  return (
    <article className={styles.boxGroupShop}>
      {/* PC版のタブ表示 */}
      <div className={styles.pcVersion}>
        <nav role="tablist" aria-label="エリア選択">
          {areaKeys.map((area) => {
            const isActive = selectedArea === area;
            const panelId = `panel-${area}`;
            const tabId = `tab-${area}`;
            return (
              <button
                key={area}
                id={tabId}
                type="button"
                role="tab"
                aria-selected={isActive}
                aria-controls={panelId}
                data-active={isActive || undefined}
                onClick={() => handleSelect(area)}
              >
                <span>{areaLabels[area] ?? area}</span>
              </button>
            );
          })}
        </nav>
        <ul
          id={selectedArea ? `panel-${selectedArea}` : undefined}
          role={selectedArea ? 'tabpanel' : undefined}
          aria-labelledby={selectedArea ? `tab-${selectedArea}` : undefined}
          ref={panelRef}
          style={panelStyle}
          onTransitionEnd={handleTransitionEnd}
        >
          {renderArea
            ? grouped[renderArea].map((shop) => (
                <li key={shop.storeId}>
                  <ExternalLink href={shop.url} aria-label={shop.name}>
                    {shop.name}
                  </ExternalLink>
                </li>
              ))
            : null}
        </ul>
      </div>

      {/* スマホ版のアコーディオン表示 */}
      <div className={styles.mobileVersion}>
        {areaKeys.map((area) => {
          const isExpanded = expandedAreas.has(area);
          return (
            <div key={area} className={styles.areaSection}>
              <button
                type="button"
                onClick={() => toggleMobileArea(area)}
                aria-expanded={isExpanded}
              >
                <span className={styles.nameAreaJp}>
                  {areaLabels[area] ?? area}
                </span>
                <span className={styles.nameAreaEn}>{area.toUpperCase()}</span>
              </button>
              <ul data-expanded={isExpanded}>
                <div>
                  {grouped[area].map((shop) => (
                    <li key={shop.storeId}>
                      <ExternalLink href={shop.url} aria-label={shop.name}>
                        {shop.name}
                      </ExternalLink>
                    </li>
                  ))}
                </div>
              </ul>
            </div>
          );
        })}
      </div>
    </article>
  );
};

export default EntranceGroupShopList;
