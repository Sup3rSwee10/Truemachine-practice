import { useEffect, useState } from 'react';
import { fetchPaymentAuditHistory } from '../api/auditLog';

const actionLabels = {
  create: 'Создание',
  update: 'Изменение',
  delete: 'Удаление',
  status_change: 'Смена статуса',
  reschedule: 'Перенос даты',
  approve: 'Согласование',
  reject: 'Отклонение',
};

export default function PaymentHistoryModal({ paymentId, onClose }) {
  const [logs, setLogs] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchPaymentAuditHistory(paymentId)
      .then(setLogs)
      .catch((err) => setError(err.response?.data?.message || err.message));
  }, [paymentId]);

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-card" onClick={(e) => e.stopPropagation()}>
        <div className="modal-card__header">
          <h2>История заявки №{paymentId}</h2>
          <button className="modal-card__close" onClick={onClose}>×</button>
        </div>

        {error && <div className="data-table__state data-table__state--error">{error}</div>}
        {!error && !logs && <div className="data-table__state">Загрузка…</div>}
        {!error && logs && logs.length === 0 && <div className="data-table__state">Записей нет</div>}
        {!error && logs && logs.length > 0 && (
          <table className="data-table">
            <thead>
              <tr>
                <th>Дата</th>
                <th>Кто</th>
                <th>Действие</th>
                <th>Поле</th>
                <th>Было → Стало</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id}>
                  <td>{log.created_at ? new Date(log.created_at).toLocaleString('ru-RU') : '—'}</td>
                  <td>{log.user_name || log.user?.name || log.user_id || '—'}</td>
                  <td>{actionLabels[log.action] || log.action}</td>
                  <td>{log.field_name || '—'}</td>
                  <td>{log.field_name ? `${log.old_value ?? '—'} → ${log.new_value ?? '—'}` : (log.new_value ?? '—')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
