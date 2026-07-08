import { useState } from 'react';
import { attachPayments, exportRegistry } from '../../api/registries';
import { entities } from '../../config/entities';
import { useAuth } from '../../context/AuthContext';

// Правила п.7.3-7.4: реестр утверждается Руководителем (draft -> sent_to_bank),
// отметку об оплате делает Казначей (sent_to_bank -> paid).
// Отдельных эндпоинтов approve/mark-as-paid для реестра в API нет — используем
// обычный PUT /registries/{id} { status } через общий resource-API.
export default function RegistryRowActions({ registry, onChanged }) {
  const [busy, setBusy] = useState(false);
  const { hasRole } = useAuth();

  async function changeStatus(newStatus, confirmText) {
    if (confirmText && !window.confirm(confirmText)) return;
    setBusy(true);
    try {
      await entities.registries.api.update(registry.id, { status: newStatus });
      onChanged();
    } catch (err) {
      alert(err.response?.data?.message || 'Не удалось изменить статус реестра');
    } finally {
      setBusy(false);
    }
  }

  async function handleAttach() {
    setBusy(true);
    try {
      const result = await attachPayments(registry.id);
      alert(`Добавлено заявок: ${result.added_count}. Сумма: ${result.total_amount_formatted}`);
      onChanged();
    } catch (err) {
      alert(err.response?.data?.message || 'Не удалось заполнить реестр');
    } finally {
      setBusy(false);
    }
  }

  async function handleExport() {
    setBusy(true);
    try {
      await exportRegistry(registry.id);
    } catch {
      alert('Не удалось скачать реестр');
    } finally {
      setBusy(false);
    }
  }

  const canApproveRegistry = hasRole('manager') || hasRole('admin');
  const canConfirmPayment = hasRole('treasurer') || hasRole('admin');

  return (
    <>
      {registry.status === 'draft' && (
        <button className="btn btn--small" disabled={busy} onClick={handleAttach}>
          Заполнить
        </button>
      )}
      {registry.status === 'draft' && canApproveRegistry && (
        <button
          className="btn btn--small btn--primary"
          disabled={busy}
          onClick={() => changeStatus('sent_to_bank', 'Утвердить реестр и отправить в банк?')}
        >
          Утвердить
        </button>
      )}
      {registry.status === 'sent_to_bank' && canConfirmPayment && (
        <button
          className="btn btn--small btn--primary"
          disabled={busy}
          onClick={() =>
            changeStatus('paid', 'Подтвердить оплату? Все заявки в реестре получат статус «Оплачена».')
          }
        >
          Подтвердить оплату
        </button>
      )}
      <button className="btn btn--small" disabled={busy} onClick={handleExport}>
        Экспорт
      </button>
    </>
  );
}
