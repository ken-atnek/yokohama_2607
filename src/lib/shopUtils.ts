export const storeIdMap: Record<string, string> = {
  hot: 'kbHot',
  villa: 'kbVilla',
  style: 'kbStyle',
};

export const logoHrefMap: Record<string, string> = {
  hot: '#svg_logoKobeHot',
  villa: '#svg_logoKobeVilla',
  style: '#svg_logoKobeStyle',
};

export function getShopFromPath(pathname: string): string {
  const shop = pathname.split('/')[1];

  // shopが存在し、かつstoreIdMapに定義されている場合のみ返す
  if (shop && storeIdMap[shop]) {
    return shop;
  }

  // どの店舗にも該当しない場合は空文字またはnullを返す
  return '';
}

export function getStoreClass(shop: string): string {
  // shopが空の場合はクラスを返さない
  if (!shop || !storeIdMap[shop]) {
    return '';
  }
  return storeIdMap[shop];
}

export function getLogoHref(shop: string): string {
  return logoHrefMap[shop] || logoHrefMap['hot'];
}

export const platinumMailUrlMap: Record<string, string> = {
  // hot: 'https://www.cityheaven.net/hyogo/A2802/A280201/koubehp/platinummail/?of=y',
  hot: 'https://www.cityheaven.net/hyogo/A2802/A280201/koube_hp_part3/platinummail/?of=y',
  villa:
    'https://www.cityheaven.net/hyogo/A2802/A280201/koube_hp_part3/platinummail/?of=y',
};
