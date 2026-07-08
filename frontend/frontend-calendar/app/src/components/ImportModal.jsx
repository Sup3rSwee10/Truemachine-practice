import { useEffect, useState } from 'react';
import { entities } from '../config/entities';
import { importPayments, importIncomes } from '../api/import';

export default function ImportModal({ kind, onClose, onDone }) {
  const [file, setFile] = useState(null);
  const [accountId, setAccountId] = useState('');
  const [accounts, setAccounts] = useState(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    entities.accounts.api.list().then(({ items }) => setAccounts(items)).catch(() => setAccounts([]));
  }, []);

  async function handleSubmit(e) {
    e.preventDefault();
    if (!file || !accountId) return;
    setBusy(true);
    setError(null);
    try {
      const importFn = kind === 'payments' ? importPayments : importIncomes;
      const data = await importFn(file, accountId);
      setResult(data);
      onDone?.();
    } catch (err) {
      setError(err.response?.data?.message || 'Не удалось импортировать файл');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-card" onClick={(e) => e.stopPropagation()}>
        <div className="modal-card__header">
          <h2>{kind === 'payments' ? 'Импорт заявок из файла' : 'Импорт поступлений из файла'}</h2>
          <button className="modal-card__close" onClick={onClose}>×</button>
        </div>

        {!result ? (
          <form onSubmit={handleSubmit}>
            <div className="entity-form__row">
              <label>Файл (.xlsx, .xls, .csv)</label>
              <input type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files[0])} required />
            </div>
            <div className="entity-form__row">
              <label>Счёт</label>
              <select value={accountId} onChange={(e) => setAccountId(e.target.value)} required>
                <option value="">— выберите счёт —</option>
                {(accounts || []).map((acc) => (
                  <option key={acc.id} value={acc.id}>{acc.name}</option>
                ))}
              </select>
            </div>
            {error && <div className="entity-form__error">{error}</div>}
            <div className="entity-form__actions">
              <button type="submit" className="btn btn--primary" disabled={busy}>
                {busy ? 'Загрузка…' : 'Импортировать'}
              </button>
            </div>
          </form>
        ) : (
          <div>
            <p>{result.message}</p>
            <p>Импортировано: <strong>{result.imported_count}</strong></p>
            {result.error_count > 0 && (
              <>
                <p>Ошибок: <strong>{result.error_count}</strong></p>
                <ul>
                  {(result.errors || []).map((e, i) => <li key={i}>{e}</li>)}
                </ul>
              </>
            )}
            <div className="entity-form__actions">
              <button className="btn btn--primary" onClick={onClose}>Готово</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
