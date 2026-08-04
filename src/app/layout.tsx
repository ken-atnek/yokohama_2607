/* =======================================
 * 横浜ダンディーグループ ルートレイアウト
 * URL: /src/app/layout.tsx
 * Referenced in: /src/app/layout.tsx
 * Created: 2026-07-08
 * Last updated: 2026-08-04
 * ======================================= */
import type { Metadata } from 'next';
import localFont from 'next/font/local';
import { Bebas_Neue, Noto_Sans_JP, Reenie_Beanie } from 'next/font/google';
import { isRealProduction, metadataBase } from '@/lib/env';
import '@/styles/globals.scss';
import SvgDefs from '@/components/SvgDefs';
const futuraCondensedMedium = localFont({
  src: '../../public/fonts/FuturaCondensedMedium.woff2',
  variable: '--font-futura-condensed-medium',
  display: 'swap',
});

const futuraCyrillicBook = localFont({
  src: '../../public/fonts/FuturaCyrillicBook.woff2',
  variable: '--font-futura-cyrillic-book',
  display: 'swap',
});

const futuraCyrillicDemi = localFont({
  src: '../../public/fonts/FuturaCyrillicDemi.woff2',
  variable: '--font-futura-cyrillic-demi',
  display: 'swap',
});

const bebasNeue = Bebas_Neue({
  subsets: ['latin'],
  variable: '--font-bebas-neue',
  weight: '400',
  display: 'swap',
});

const notoSansJp = Noto_Sans_JP({
  subsets: ['latin'],
  variable: '--font-noto-sans-jp',
  weight: ['300', '400', '500', '700', '900'],
  display: 'swap',
});

const reenieBeanie = Reenie_Beanie({
  subsets: ['latin'],
  variable: '--font-reenie-beanie',
  weight: '400',
  display: 'swap',
});

const siteTitle = '横浜ダンディーグループ';
const siteName = '横浜ダンディーグループ';
const siteDescription =
  '横浜ダンディ、ミスターダンディ、クラブダンディのキャスト情報、出勤情報、リアルタイム情報を掲載する公式サイトです。';

export const metadata: Metadata = {
  title: {
    default: siteTitle,
    template: `%s | ${siteName}`,
  },
  description: siteDescription,
  icons: {
    icon: [
      {
        url: '/favicon/favicon-light.svg',
        type: 'image/svg+xml',
        media: '(prefers-color-scheme: light)',
      },
      {
        url: '/favicon/favicon-light.svg',
        type: 'image/svg+xml',
        media: '(prefers-color-scheme: dark)',
      },
    ],
  },
  ...(isRealProduction && {
    metadataBase,
    openGraph: {
      title: siteTitle,
      description: siteDescription,
      url: '/',
      siteName,
      locale: 'ja_JP',
      type: 'website',
    },
    twitter: {
      card: 'summary_large_image',
      title: siteTitle,
      description: siteDescription,
    },
  }),
  robots: isRealProduction ? 'index, follow' : 'noindex, nofollow',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="ja"
      className={`${futuraCondensedMedium.variable} ${futuraCyrillicBook.variable} ${futuraCyrillicDemi.variable} ${bebasNeue.variable} ${notoSansJp.variable} ${reenieBeanie.variable}`}
    >
      <body>
        <SvgDefs />
        {children}
      </body>
    </html>
  );
}
