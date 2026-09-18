/* =======================================
 * ダンディーグループ 出勤お知らせメール設定リンク要求クライアント
 * URL: /src/app/wgmailsys/settings-request/SettingsRequestClient.tsx
 * Referenced in: /src/app/wgmailsys/settings-request/page.tsx
 * Created: 2026-08-10
 * Last updated: 2026-09-03
 * ======================================= */
'use client';

import clsx from 'clsx';
import { FormEvent, useEffect, useState } from 'react';
import styles from '../WgMailSys.module.scss';

type ViewState =
  | { kind: 'checking' }
  | { kind: 'ready'; csrfToken: string; message: string | null }
  | { kind: 'submitting'; csrfToken: string; message: string | null }
  | { kind: 'accepted'; message: string }
  | { kind: 'maintenance'; message: string }
  | { kind: 'error'; message: string };

type ApiResponse = {
  ok: boolean;
  state?: string;
  message?: string;
  csrfToken?: string;
};

type ApiResult = {
  status: number;
  data: ApiResponse;
};

const apiPath = '/sys/api/settings-link-request.php';
const temporaryMessage =
  '一時的に処理できませんでした。時間をおいて再度お試しください。';

export default function SettingsRequestClient() {
  const [email, setEmail] = useState('');
  const [view, setView] = useState<ViewState>({ kind: 'checking' });

  useEffect(() => {
    let cancelled = false;
    const body = new URLSearchParams();
    body.set('action', 'prepare');

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

        if (data.ok === true && data.state === 'ready' && typeof data.csrfToken === 'string') {
          setView({
            kind: 'ready',
            csrfToken: data.csrfToken,
            message: null,
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

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (view.kind !== 'ready') {
      return;
    }

    const current = view;
    setView({ ...current, kind: 'submitting' });

    const body = new URLSearchParams();
    body.set('action', 'request');
    body.set('email', email);
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
        if (data.ok === true && data.state === 'accepted') {
          setView({
            kind: 'accepted',
            message: data.message || '入力されたメールアドレスの登録状況を確認し、設定用メールを送信します。',
          });
          return;
        }

        if (data.state === 'validation_error') {
          setView({
            ...current,
            kind: 'ready',
            message: data.message || 'メールアドレスを正しく入力してください。',
          });
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

  if (view.kind === 'accepted' || view.kind === 'maintenance' || view.kind === 'error') {
    return (
      <div className={styles.panel}>
        <h1 className={styles.heading}>出勤お知らせメール 設定リンク</h1>
        <p
          className={clsx(
            styles.messageBox,
            view.kind === 'accepted'
              ? styles.messageSuccess
              : view.kind === 'maintenance'
                ? styles.messageWarning
                : styles.messageError
          )}
          role={view.kind === 'error' ? 'alert' : 'status'}
        >
          {view.message}
        </p>
      </div>
    );
  }

  const isSubmitting = view.kind === 'submitting';

  return (
    <div className={styles.panel}>
      <h1 className={styles.heading}>出勤お知らせメール 設定リンク</h1>
      <form className={styles.form} onSubmit={handleSubmit}>
        <label className={styles.field}>
          <span className={styles.fieldLabel}>メールアドレス</span>
          <input
            className={styles.input}
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="email"
            required
          />
        </label>
        {view.message ? (
          <p className={styles.errorText} role="alert">
            {view.message}
          </p>
        ) : null}
        <div className={styles.actionRow}>
          <button className={styles.button} type="submit" disabled={isSubmitting}>
            {isSubmitting ? '送信中です' : '設定リンクを送信する'}
          </button>
        </div>
      </form>
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

  if (data.state === 'temporary_error' || status >= 500) {
    return {
      kind: 'error',
      message: data.message || temporaryMessage,
    };
  }

  return {
    kind: 'error',
    message: data.message || temporaryMessage,
  };
}
