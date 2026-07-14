export const storeIdMap: Record<string, string> = {
  dandy: 'dandy',
  mr_dandy: 'mr_dandy',
  club_dandy: 'club_dandy',
};

export const logoHrefMap: Record<string, string> = {
  dandy: '',
  mr_dandy: '',
  club_dandy: '',
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
  return logoHrefMap[shop] || logoHrefMap['dandy'];
}

export const platinumMailUrlMap: Record<string, string> = {
  dandy: '',
  mr_dandy: '',
  club_dandy: '',
};
