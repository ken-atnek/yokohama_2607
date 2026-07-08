/* =======================================
 * 横浜ダンディーグループ ルートレイアウト
 * URL: /src/app/layout.tsx
 * Referenced in: /src/app/layout.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */
import type { Metadata } from 'next';
import localFont from 'next/font/local';
import { Noto_Sans_JP } from 'next/font/google';
import { isRealProduction, metadataBase } from '@/lib/env';
import '@/styles/globals.scss';

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

const notoSansJp = Noto_Sans_JP({
  subsets: ['latin'],
  variable: '--font-noto-sans-jp',
  weight: ['400', '500', '700', '900'],
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
        url: '/favicon/favicon-dark.svg',
        type: 'image/svg+xml',
        media: '(prefers-color-scheme: dark)',
      },
    ],
  },
  ...(isRealProduction && {
    metadataBase,
    alternates: {
      canonical: '/',
    },
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
      className={`${futuraCondensedMedium.variable} ${futuraCyrillicBook.variable} ${futuraCyrillicDemi.variable} ${notoSansJp.variable}`}
    >
      <body>{children}</body>
    </html>
  );
}
