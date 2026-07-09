import { useEffect, useState } from 'react';
import {
  submitForApproval,
  returnToDraft,
  approvePayment,
  rejectPayment,
  markAsPaid,
  changePaymentAccount,
} from '../../api/payments';
import { useAuth } from '../../context/AuthContext';
import { entities } from '../../config/entities';
import PaymentHistoryModal from '../../components/PaymentHistoryModal';

// Правила п.1.5, 3.4-3.6: отправка/возврат в черновик — Инициатор (автор заявки);
// согласование — Казначей/Руководитель; отметка оплаты — Казначей/Руководитель.
// created_by теперь есть в ответе API — можно проверять именно авторство,
// а не только роль.
export default function RequestRowActions({ payment, onChanged }) {
  const [busy, setBusy] = useState(false);
  const [showMoveAccount, setShowMoveAccount] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [accounts, setAccounts] = useState([]);
  const [targetAccountId, setTargetAccountId] = useState('');
  const { hasRole, user } = useAuth();

  const isOwner = payment.created_by !== undefined && user && payment.created_by === user.id;
  const canInitiate = hasRole('admin') || (hasRole('initiator') && isOwner);
  const canApprove = hasRole('treasurer') || hasRole('manager') || hasRole('admin');
  const canMoveAccount = hasRole('treasurer') || hasRole('admin');
  const canViewHistory = hasRole('admin') || hasRole('manager');

  useEffect(() => {
    if (showMoveAccount && accounts.length === 0) {
      entities.accounts.api.list().then(({ items }) => setAccounts(items)).catch(() => {});
    }
  }, [showMoveAccount]);

  async function run(fn) {
    setBusy(true);
    try {
      await fn();
      onChanged();
    } catch (err) {
      alert(err.response?.data?.message || 'Не удалось выполнить действие');
    } finally {
      setBusy(false);
    }
  }

  async function handleMoveAccount() {
    if (!targetAccountId) return;
    const targetAccount = accounts.find((a) => String(a.id) === String(targetAccountId));
    const currentCurrency = payment.account?.currency?.code;
    const targetCurrency = targetAccount?.currency?.code;
    const willConvert = currentCurrency && targetCurrency && currentCurrency !== targetCurrency;

    if (
      willConvert &&
      !window.confirm(
        `Счёт «${targetAccount.name}» в валюте ${targetCurrency}, заявка — в ${currentCurrency}. Сумма будет сконвертирована по текущему курсу. Продолжить?`
      )
    ) {
      return;
    }

    setBusy(true);
    try {
      const result = await changePaymentAccount(payment.id, targetAccountId);
      if (result.warning) alert(result.warning);
      setShowMoveAccount(false);
      onChanged();
    } catch (err) {
      alert(err.response?.data?.message || 'Не удалось перенести заявку на другой счёт');
    } finally {
      setBusy(false);
    }
  }

  const { status, id } = payment;
  const canMoveAtAll = status !== 'paid';

  return (
    <>
      {status === 'draft' && canInitiate && (
        <button className="btn btn--small" disabled={busy} onClick={() => run(() => submitForApproval(id))}>
          Отправить на согл.
        </button>
      )}

      {status === 'under_approval' && canApprove && (
        <>
          <button className="btn btn--small btn--primary" disabled={busy} onClick={() => run(() => approvePayment(id))}>
            Согласовать
          </button>
          <button
            className="btn btn--small btn--danger"
            disabled={busy}
            onClick={() => {
              const comment = window.prompt('Причина отклонения:');
              if (comment) run(() => rejectPayment(id, comment));
            }}
          >
            Отклонить
          </button>
        </>
      )}
      {status === 'under_approval' && canInitiate && (
        <button className="btn btn--small" disabled={busy} onClick={() => run(() => returnToDraft(id))}>
          В черновик
        </button>
      )}

      {status === 'rejected' && canInitiate && (
        <button className="btn btn--small" disabled={busy} onClick={() => run(() => submitForApproval(id))}>
          Отправить повторно
        </button>
      )}

      {(status === 'approved' || status === 'approved_moved') && canApprove && (
        <button className="btn btn--small btn--primary" disabled={busy} onClick={() => run(() => markAsPaid(id))}>
          Отметить оплаченной
        </button>
      )}

      {canMoveAccount && canMoveAtAll && (
        <div className="move-account">
          {!showMoveAccount ? (
            <button className="btn btn--small" onClick={() => setShowMoveAccount(true)}>
              На другой счёт
            </button>
          ) : (
            <span className="move-account__form">
              <select value={targetAccountId} onChange={(e) => setTargetAccountId(e.target.value)}>
                <option value="">— счёт —</option>
                {accounts
                  .filter((a) => a.id !== payment.account_id)
                  .map((a) => (
                    <option key={a.id} value={a.id}>{a.name}</option>
                  ))}
              </select>
              <button className="btn btn--small btn--primary" disabled={busy || !targetAccountId} onClick={handleMoveAccount}>
                ОК
              </button>
              <button className="btn btn--small" onClick={() => setShowMoveAccount(false)}>
                ×
              </button>
            </span>
          )}
        </div>
      )}

      {canViewHistory && (
        <button className="btn btn--small" onClick={() => setShowHistory(true)}>
          История
        </button>
      )}
      {showHistory && <PaymentHistoryModal paymentId={id} onClose={() => setShowHistory(false)} />}
    </>
  );
}
