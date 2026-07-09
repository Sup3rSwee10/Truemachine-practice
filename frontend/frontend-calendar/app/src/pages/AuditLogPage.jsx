import { useEffect, useState } from 'react';
import { fetchAuditLogs } from '../api/auditLog';

const actionLabels = {
  create: 'Создание',
  update: 'Изменение',
  delete: 'Удаление',
  status_change: 'Смена статуса',
  reschedule: 'Перенос даты',
  approve: 'Согласование',
  reject: 'Отклонение',
};

export default function AuditLogPage() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchAuditLogs()
      .then(setLogs)
      .catch((err) => setError(err.response?.data?.message || err.message))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="page">
      <h1 className="page__title">Журнал аудита</h1>

      {loading && <div className="data-table__state">Загрузка…</div>}
      {error && <div className="data-table__state data-table__state--error">Не удалось загрузить: {error}</div>}

      {!loading && !error && (
        <table className="data-table">
          <thead>
            <tr>
              <th>Дата/время</th>
              <th>Пользователь</th>
              <th>Действие</th>
              <th>Заявка №</th>
              <th>Поле</th>
              <th>Было</th>
              <th>Стало</th>
            </tr>
          </thead>
          <tbody>
            {logs.length === 0 && (
              <tr>
                <td colSpan={7} style={{ textAlign: 'center' }}>Записей нет</td>
              </tr>
            )}
            {logs.map((log) => (
              <tr key={log.id}>
                <td>{formatDateTime(log.created_at)}</td>
                <td>{log.user_name || log.user?.name || log.user_id || '—'}</td>
                <td>{actionLabels[log.action] || log.action}</td>
                <td>{log.entity_id}</td>
                <td>{log.field_name || '—'}</td>
                <td>{log.old_value ?? '—'}</td>
                <td>{log.new_value ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function formatDateTime(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString('ru-RU');
}
