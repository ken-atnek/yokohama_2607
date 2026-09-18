/* =======================================
 * ダンディーグループ 出勤お知らせメール全解除クライアント
 * URL: /src/app/wgmailsys/unsubscribe-all/UnsubscribeAllClient.tsx
 * Referenced in: /src/app/wgmailsys/unsubscribe-all/page.tsx
 * Created: 2026-08-10
 * Last updated: 2026-09-03
 * ======================================= */
'use client';

import clsx from 'clsx';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import styles from '../WgMailSys.module.scss';

type ReadyData = {
  subscriptionCount: number;
  csrfToken: string;
};

type ViewState =
  | { kind: 'checking' }
  | (ReadyData & { kind: 'ready' })
  | (ReadyData & { kind: 'submitting' })
  | { kind: 'completed' }
  | { kind: 'authenticationRequired'; message: string }
  | { kind: 'maintenance'; message: string }
  | { kind: 'error'; message: string };

type ApiResponse = {
  ok: boolean;
  state?: string;
  message?: string;
  subscriptionCount?: number;
  csrfToken?: string;
};

type ApiResult = {
  status: number;
  data: ApiResponse;
};

const apiPath = '/sys/api/unsubscribe-all.php';
const temporaryMessage =
  '一時的に処理できませんでした。時間をおいて再度お試しください。';

export default function UnsubscribeAllClient() {
  const [view, setView] = useState<ViewState>({ kind: 'checking' });

  useEffect(() => {
    let cancelled = false;
    const body = new URLSearchParams();
    body.set('action', 'preview');

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
        if (cancelled) {
          return;
        }

        if (
          data.ok === true &&
          data.state === 'ready' &&
          typeof data.subscriptionCount === 'number' &&
          typeof data.csrfToken === 'string'
        ) {
          setView({
            kind: 'ready',
            subscriptionCount: data.subscriptionCount,
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
  }, []);

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
        if (data.ok === true && (data.state === 'completed' || data.state === 'already_unsubscribed')) {
          setView({ kind: 'completed' });
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

  if (view.kind === 'completed') {
    return (
      <div className={styles.panel}>
        <h1 className={styles.heading}>出勤お知らせメール すべて解除</h1>
        <p className={clsx(styles.messageBox, styles.messageSuccess)} role="status">
          すべて解除しました。
        </p>
      </div>
    );
  }

  if (view.kind === 'authenticationRequired' || view.kind === 'maintenance' || view.kind === 'error') {
    return (
      <div className={styles.panel}>
        <h1 className={styles.heading}>出勤お知らせメール すべて解除</h1>
        <p
          className={clsx(
            styles.messageBox,
            view.kind === 'maintenance' || view.kind === 'authenticationRequired'
              ? styles.messageWarning
              : styles.messageError
          )}
          role={view.kind === 'maintenance' || view.kind === 'authenticationRequired' ? 'status' : 'alert'}
        >
          {view.message}
        </p>
        <Link className={styles.textLink} href="/wgmailsys/settings/">
          設定へ戻る
        </Link>
      </div>
    );
  }

  const isSubmitting = view.kind === 'submitting';

  return (
    <div className={clsx(styles.panel, styles.panelDanger)}>
      <h1 className={styles.heading}>出勤お知らせメール すべて解除</h1>
      <p className={clsx(styles.messageBox, styles.messageWarning)}>
        登録中の女の子 {view.subscriptionCount}件をすべて解除します。
      </p>
      <div className={styles.actionRow}>
        <button className={styles.dangerButton} type="button" onClick={handleConfirm} disabled={isSubmitting}>
          {isSubmitting ? '解除中です' : 'すべて解除する'}
        </button>
        <Link className={styles.textLink} href="/wgmailsys/settings/">
          戻る
        </Link>
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
  if (data.state === 'authentication_required') {
    return {
      kind: 'authenticationRequired',
      message: data.message || '設定画面を開くには、設定リンクから認証してください。',
    };
  }

  if (data.state === 'maintenance') {
    return {
      kind: 'maintenance',
      message: data.message || temporaryMessage,
    };
  }

  return {
    kind: 'error',
    message: data.message || temporaryMessage,
  };
}
