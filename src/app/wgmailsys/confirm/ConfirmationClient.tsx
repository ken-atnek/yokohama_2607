/* =======================================
 * ダンディーグループ 出勤お知らせメール確認クライアント
 * URL: /src/app/wgmailsys/confirm/ConfirmationClient.tsx
 * Referenced in: /src/app/wgmailsys/confirm/page.tsx
 * Created: 2026-08-04
 * Last updated: 2026-09-03
 * ======================================= */
'use client';

import clsx from 'clsx';
import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import styles from '../WgMailSys.module.scss';

type ReadyData = {
  shopName: string;
  castName: string;
  csrfToken: string;
};

type ViewState =
  | { kind: 'checking' }
  | (ReadyData & { kind: 'ready' })
  | (ReadyData & { kind: 'submitting' })
  | { kind: 'invalid'; message: string }
  | { kind: 'maintenance'; message: string }
  | { kind: 'error'; message: string };

type ApiResponse = {
  ok: boolean;
  state?: string;
  message?: string;
  shopName?: string;
  castName?: string;
  csrfToken?: string;
  redirectUrl?: string;
};

type ApiResult = {
  status: number;
  data: ApiResponse;
};

const confirmPath = '/wgmailsys/confirm/';
const apiPath = '/sys/api/confirmation.php';
const invalidMessage =
  'このURLは無効か、有効期限が切れています。お手数ですが、女の子詳細ページからもう一度登録してください。';
const temporaryMessage =
  '一時的に処理できませんでした。時間をおいて再度お試しください。';

export default function ConfirmationClient() {
  const searchParams = useSearchParams();
  const previewRequestRef = useRef<Promise<ApiResult> | null>(null);
  const [view, setView] = useState<ViewState>({ kind: 'checking' });

  useEffect(() => {
    let cancelled = false;
    let previewRequest = previewRequestRef.current;

    if (previewRequest === null) {
      let rawToken = searchParams.get('token') ?? '';
      window.history.replaceState(null, '', confirmPath);

      const body = new URLSearchParams();
      body.set('action', 'preview');
      body.set('token', rawToken);
      rawToken = '';

      previewRequest = fetch(apiPath, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body,
        credentials: 'same-origin',
        cache: 'no-store',
      }).then(readApiResult);
      previewRequestRef.current = previewRequest;
    }

    previewRequest
      .then(({ status, data }) => {
        if (cancelled) {
          return;
        }

        if (
          data.ok === true &&
          data.state === 'ready' &&
          typeof data.shopName === 'string' &&
          typeof data.castName === 'string' &&
          typeof data.csrfToken === 'string'
        ) {
          setView({
            kind: 'ready',
            shopName: data.shopName,
            castName: data.castName,
            csrfToken: data.csrfToken,
          });
          return;
        }

        setView(resolveFailureView(status, data));
      })
      .catch(() => {
        if (!cancelled) {
          setView({ kind: 'error', message: temporaryMessage });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [searchParams]);

  const handleConfirm = () => {
    if (view.kind !== 'ready') {
      return;
    }

    const current = view;
    setView({ ...current, kind: 'submitting' });

    const body = new URLSearchParams();
    body.set('action', 'confirm');
    body.set('csrf_token', current.csrfToken);

    fetch(apiPath, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body,
      credentials: 'same-origin',
      cache: 'no-store',
    })
      .then(readApiResult)
      .then(({ status, data }) => {
        if (data.ok === true && data.state === 'completed' && typeof data.redirectUrl === 'string') {
          window.location.assign(data.redirectUrl);
          return;
        }

        setView(resolveFailureView(status, data));
      })
      .catch(() => {
        setView({ kind: 'error', message: temporaryMessage });
      });
  };

  if (view.kind === 'checking') {
    return (
      <div className={styles.panel} role="status" aria-live="polite">
        <p className={clsx(styles.messageBox, styles.messageInfo)}>
          確認しています。
        </p>
      </div>
    );
  }

  if (view.kind === 'invalid' || view.kind === 'maintenance' || view.kind === 'error') {
    return (
      <div className={styles.panel}>
        <h1 className={styles.heading}>出勤お知らせメール 本登録</h1>
        <p
          className={clsx(
            styles.messageBox,
            view.kind === 'maintenance' ? styles.messageWarning : styles.messageError
          )}
          role={view.kind === 'maintenance' ? 'status' : 'alert'}
        >
          {view.message}
        </p>
      </div>
    );
  }

  const isSubmitting = view.kind === 'submitting';

  return (
    <div className={styles.panel}>
      <h1 className={styles.heading}>出勤お知らせメール 本登録</h1>
      <p className={styles.lead}>以下の内容で登録を完了します。</p>
      <div className={styles.summary}>
        <div className={styles.summaryRow}>
          <span className={styles.summaryLabel}>店舗名</span>
          <span className={styles.summaryValue}>{view.shopName}</span>
        </div>
        <div className={styles.summaryRow}>
          <span className={styles.summaryLabel}>女の子名</span>
          <span className={styles.summaryValue}>{view.castName}</span>
        </div>
      </div>
      <p className={styles.notice}>
        心当たりがない場合は、このまま画面を閉じてください。
      </p>
      <div className={styles.actionRow}>
        <button
          className={styles.button}
          type="button"
          onClick={handleConfirm}
          disabled={isSubmitting}
        >
          {isSubmitting ? '送信中です' : '登録を完了する'}
        </button>
      </div>
    </div>
  );
}

function readApiResult(response: Response): Promise<ApiResult> {
  return response.json().then((data: ApiResponse) => ({
    status: response.status,
    data,
  }));
}

function resolveFailureView(status: number, data: ApiResponse): ViewState {
  if (data.state === 'maintenance') {
    return {
      kind: 'maintenance',
      message: data.message || temporaryMessage,
    };
  }

  if (data.state === 'invalid_or_expired') {
    return {
      kind: 'invalid',
      message: data.message || invalidMessage,
    };
  }

  if (data.state === 'temporary_error' || status >= 500) {
    return {
      kind: 'error',
      message: data.message || temporaryMessage,
    };
  }

  return {
    kind: 'invalid',
    message: data.message || invalidMessage,
  };
}
