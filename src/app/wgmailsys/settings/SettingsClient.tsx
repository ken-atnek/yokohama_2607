/* =======================================
 * ダンディーグループ 出勤お知らせメール設定クライアント
 * URL: /src/app/wgmailsys/settings/SettingsClient.tsx
 * Referenced in: /src/app/wgmailsys/settings/page.tsx
 * Created: 2026-08-10
 * Last updated: 2026-09-04
 * ======================================= */
'use client';

import clsx from 'clsx';
import { FormEvent, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import styles from '../WgMailSys.module.scss';

type QuietHours = {
  enabled: boolean;
  startMinute: number | null;
  endMinute: number | null;
};

type Subscription = {
  subscriptionId: number;
  shopName: string;
  castName: string;
  status: string;
  pauseReason: string | null;
  statusLabel: string;
  updatedAt: string | null;
};

type MessageTone = 'success' | 'error' | null;

type SettingsData = {
  suppressed: boolean;
  quietEnabled: '0' | '1';
  quietStart: string;
  quietEnd: string;
  subscriptions: Subscription[];
  lastUpdatedAt: string | null;
  settingsCsrfToken: string;
  message: string | null;
  messageTone: MessageTone;
};

type ReadyState = SettingsData & { kind: 'ready' };

type SavingState = SettingsData & { kind: 'saving' };

type ViewState =
  | { kind: 'checking' }
  | { kind: 'authReady'; csrfToken: string }
  | { kind: 'authenticating' }
  | ReadyState
  | SavingState
  | { kind: 'authenticationRequired'; message: string }
  | { kind: 'invalid'; message: string }
  | { kind: 'maintenance'; message: string }
  | { kind: 'restricted'; message: string }
  | { kind: 'error'; message: string };

type ApiResponse = {
  ok: boolean;
  state?: string;
  message?: string;
  csrfToken?: string;
  settingsCsrfToken?: string;
  quietHours?: QuietHours;
  subscriptions?: Subscription[];
  lastUpdatedAt?: string | null;
  suppressed?: boolean;
};

type ApiResult = {
  status: number;
  data: ApiResponse;
};

type InitialRequest = Promise<ViewState>;

const settingsPath = '/wgmailsys/settings/';
const apiPath = '/sys/api/settings.php';
const temporaryMessage =
  '一時的に処理できませんでした。時間をおいて再度お試しください。';

export default function SettingsClient() {
  const searchParams = useSearchParams();
  const initialRequestRef = useRef<InitialRequest | null>(null);
  const [view, setView] = useState<ViewState>({ kind: 'checking' });

  useEffect(() => {
    let cancelled = false;
    let initialRequest = initialRequestRef.current;

    if (initialRequest === null) {
      const hasToken = searchParams.has('token');
      let rawToken = searchParams.get('token') ?? '';
      window.history.replaceState(null, '', settingsPath);

      initialRequest = hasToken ? previewSettingsToken(rawToken) : fetchSettingsView();
      initialRequestRef.current = initialRequest;
      rawToken = '';
    }

    initialRequest
      .then((nextView) => {
        if (!cancelled) {
          setView(nextView);
        }
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

  const handleAuthenticate = () => {
    if (view.kind !== 'authReady') {
      return;
    }

    setView({ kind: 'authenticating' });

    const body = new URLSearchParams();
    body.set('action', 'authenticate');
    body.set('csrf_token', view.csrfToken);

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
        if (data.ok === true && data.state === 'authenticated') {
          loadSettings(() => false, setView);
          return;
        }

        setView(resolveFailureView(status, data));
      })
      .catch(() => {
        setView({ kind: 'error', message: temporaryMessage });
      });
  };

  const handleQuietSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (view.kind !== 'ready') {
      return;
    }

    const current = view;
    setView({ ...current, kind: 'saving' });

    const body = new URLSearchParams();
    body.set('action', 'update_quiet_hours');
    body.set('csrf_token', current.settingsCsrfToken);
    body.set('quiet_hours_enabled', current.quietEnabled);
    body.set('quiet_start_minute', current.quietEnabled === '1' ? current.quietStart : '');
    body.set('quiet_end_minute', current.quietEnabled === '1' ? current.quietEnd : '');

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
        if (
          data.ok === true &&
          data.state === 'updated' &&
          data.quietHours &&
          typeof data.settingsCsrfToken === 'string'
        ) {
          setView({
            ...current,
            kind: 'ready',
            quietEnabled: data.quietHours.enabled ? '1' : '0',
            quietStart: data.quietHours.startMinute === null ? '' : String(data.quietHours.startMinute),
            quietEnd: data.quietHours.endMinute === null ? '' : String(data.quietHours.endMinute),
            settingsCsrfToken: data.settingsCsrfToken,
            message: data.message || '保存しました。',
            messageTone: 'success',
          });
          return;
        }

        if (data.state === 'validation_error') {
          setView({
            ...current,
            kind: 'ready',
            message: data.message || '入力内容を確認してください。',
            messageTone: 'error',
          });
          return;
        }

        setView(resolveFailureView(status, data));
      })
      .catch(() => {
        setView({ kind: 'error', message: temporaryMessage });
      });
  };

  const updateQuietField = (field: 'quietEnabled' | 'quietStart' | 'quietEnd', value: string) => {
    if (view.kind !== 'ready') {
      return;
    }

    setView({
      ...view,
      [field]: field === 'quietEnabled' && value === '0' ? '0' : value,
      message: null,
      messageTone: null,
    } as ReadyState);
  };

  if (view.kind === 'checking' || view.kind === 'authenticating') {
    return (
      <div className={styles.panel} role="status" aria-live="polite">
        <p className={clsx(styles.messageBox, styles.messageInfo)}>
          確認しています。
        </p>
      </div>
    );
  }

  if (view.kind === 'authReady') {
    return (
      <div className={styles.panel}>
        <h1 className={styles.heading}>出勤お知らせメール 設定</h1>
        <p className={clsx(styles.messageBox, styles.messageInfo)}>
          設定画面を開きます。
        </p>
        <button className={styles.button} type="button" onClick={handleAuthenticate}>
          設定画面を開く
        </button>
      </div>
    );
  }

  if (
    view.kind === 'authenticationRequired' ||
    view.kind === 'invalid' ||
    view.kind === 'maintenance' ||
    view.kind === 'restricted' ||
    view.kind === 'error'
  ) {
    return (
      <div className={styles.panel}>
        <h1 className={styles.heading}>出勤お知らせメール 設定</h1>
        <p
          className={settingFailureMessageClass(view.kind)}
          role={view.kind === 'error' || view.kind === 'invalid' ? 'alert' : 'status'}
        >
          {view.message}
        </p>
        {view.kind === 'authenticationRequired' || view.kind === 'invalid' ? (
          <Link className={styles.textLink} href="/wgmailsys/settings-request/">
            設定リンクを取得する
          </Link>
        ) : null}
      </div>
    );
  }

  const isSaving = view.kind === 'saving';

  return (
    <div className={styles.panel}>
      <h1 className={styles.heading}>出勤お知らせメール 設定</h1>
      {view.suppressed ? (
        <p className={clsx(styles.messageBox, styles.messageWarning)}>
          現在の状態では通知設定の変更はできません。
        </p>
      ) : null}
      <form className={styles.form} onSubmit={handleQuietSubmit}>
        <fieldset className={styles.fieldset} disabled={isSaving || view.suppressed}>
          <legend className={styles.subHeading}>受信停止時間</legend>
          <label className={styles.checkField}>
            <input
              className={styles.checkbox}
              type="checkbox"
              checked={view.quietEnabled === '1'}
              onChange={(event) => updateQuietField('quietEnabled', event.target.checked ? '1' : '0')}
            />
            <span>使用する</span>
          </label>
          <div className={styles.inlineFields}>
            <label className={styles.field}>
              <span className={styles.fieldLabel}>開始</span>
              <input
                className={styles.input}
                type="time"
                min="00:00"
                max="23:59"
                step={60}
                value={minutesToTimeValue(view.quietStart)}
                onChange={(event) => updateQuietField('quietStart', timeValueToMinutes(event.target.value))}
                disabled={view.quietEnabled === '0'}
              />
            </label>
            <label className={styles.field}>
              <span className={styles.fieldLabel}>終了</span>
              <input
                className={styles.input}
                type="time"
                min="00:00"
                max="23:59"
                step={60}
                value={minutesToTimeValue(view.quietEnd)}
                onChange={(event) => updateQuietField('quietEnd', timeValueToMinutes(event.target.value))}
                disabled={view.quietEnabled === '0'}
              />
            </label>
          </div>
        </fieldset>
        {view.message ? (
          <p
            className={settingsMessageClass(view.messageTone)}
            role={view.messageTone === 'error' ? 'alert' : 'status'}
          >
            {view.message}
          </p>
        ) : null}
        <button className={styles.button} type="submit" disabled={isSaving || view.suppressed}>
          {isSaving ? '保存中です' : '保存する'}
        </button>
      </form>
      <div className={styles.section}>
        <h2 className={styles.subHeading}>登録中の女の子</h2>
        {view.subscriptions.length > 0 ? (
          <ul className={styles.subscriptionList}>
            {view.subscriptions.map((subscription) => (
              <li className={styles.subscriptionItem} key={subscription.subscriptionId}>
                <div className={styles.subscriptionContent}>
                  <span className={styles.summaryLabel}>{subscription.shopName}</span>
                  <p className={styles.subscriptionName}>{subscription.castName}</p>
                  <p className={subscriptionStatusClass(subscription)}>{subscription.statusLabel}</p>
                </div>
                <Link
                  className={styles.dangerButton}
                  href={`/wgmailsys/unsubscribe/?subscription=${subscription.subscriptionId}`}
                >
                  解除する
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className={styles.message}>登録中の女の子はありません。</p>
        )}
      </div>
      <div className={styles.dangerZone}>
        <div className={styles.actionRow}>
          <Link className={styles.dangerButton} href="/wgmailsys/unsubscribe-all/">
            すべて解除する
          </Link>
        </div>
        {view.lastUpdatedAt ? (
          <span className={styles.summaryLabel}>最終更新日時 {view.lastUpdatedAt}</span>
        ) : null}
      </div>
    </div>
  );
}

function settingFailureMessageClass(kind: ViewState['kind']): string {
  if (kind === 'error' || kind === 'invalid') {
    return clsx(styles.messageBox, styles.messageError);
  }

  return clsx(styles.messageBox, styles.messageWarning);
}

function loadSettings(isCancelled: () => boolean, setView: (view: ViewState) => void) {
  fetchSettingsView()
    .then((nextView) => {
      if (!isCancelled()) {
        setView(nextView);
      }
    })
    .catch(() => {
      if (!isCancelled()) {
        setView({ kind: 'error', message: temporaryMessage });
      }
    });
}

function previewSettingsToken(rawToken: string): Promise<ViewState> {
  const body = new URLSearchParams();
  body.set('action', 'auth_preview');
  body.set('token', rawToken);

  return fetch(apiPath, {
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
      if (data.ok === true && data.state === 'ready' && typeof data.csrfToken === 'string') {
        return { kind: 'authReady', csrfToken: data.csrfToken };
      }

      return resolveFailureView(status, data);
    });
}

function fetchSettingsView(): Promise<ViewState> {
  const body = new URLSearchParams();
  body.set('action', 'load');

  return fetch(apiPath, {
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
      if (
        data.ok === true &&
        data.state === 'ready' &&
        data.quietHours &&
        Array.isArray(data.subscriptions) &&
        typeof data.settingsCsrfToken === 'string'
      ) {
        return {
          kind: 'ready',
          suppressed: data.suppressed === true,
          quietEnabled: data.quietHours.enabled ? '1' : '0',
          quietStart: data.quietHours.startMinute === null ? '' : String(data.quietHours.startMinute),
          quietEnd: data.quietHours.endMinute === null ? '' : String(data.quietHours.endMinute),
          subscriptions: data.subscriptions,
          lastUpdatedAt: data.lastUpdatedAt ?? null,
          settingsCsrfToken: data.settingsCsrfToken,
          message: null,
          messageTone: null,
        };
      }

      return resolveFailureView(status, data);
    });
}

function settingsMessageClass(tone: MessageTone): string {
  if (tone === 'success') {
    return clsx(styles.messageBox, styles.messageSuccess);
  }

  if (tone === 'error') {
    return clsx(styles.messageBox, styles.messageError);
  }

  return clsx(styles.messageBox, styles.messageInfo);
}

function subscriptionStatusClass(subscription: Subscription): string {
  if (subscription.status === 'active') {
    return clsx(styles.statusBadge, styles.statusBadgeActive);
  }

  if (subscription.status === 'paused' && subscription.pauseReason === 'admin_paused') {
    return clsx(styles.statusBadge, styles.statusBadgeAdminPaused);
  }

  if (subscription.status === 'paused') {
    return clsx(styles.statusBadge, styles.statusBadgePaused);
  }

  return styles.statusBadge;
}

function minutesToTimeValue(value: string): string {
  if (value === '' || /^(0|[1-9]\d{0,3})$/.test(value) !== true) {
    return '';
  }

  const totalMinutes = Number(value);
  if (!Number.isInteger(totalMinutes) || totalMinutes < 0 || totalMinutes > 1439) {
    return '';
  }

  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

function timeValueToMinutes(value: string): string {
  if (value === '') {
    return '';
  }

  const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(value);
  if (match === null) {
    return '';
  }

  return String(Number(match[1]) * 60 + Number(match[2]));
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
      message: data.message || 'このURLは無効か、有効期限が切れています。',
    };
  }

  if (data.state === 'authentication_required') {
    return {
      kind: 'authenticationRequired',
      message: data.message || '設定画面を開くには、設定リンクから認証してください。',
    };
  }

  if (data.state === 'restricted') {
    return {
      kind: 'restricted',
      message: data.message || '現在の状態ではこの設定を変更できません。',
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
