import { useEffect, useState } from 'react';
import {
  fetchBalancesReport,
  fetchCashGapsReport,
  fetchPlanFactReport,
  downloadBalancesReport,
  downloadCashGapsReport,
  downloadPlanFactReport,
  fetchReportsHistory,
} from '../api/reports';
import { formatMoney } from '../utils/money';

const tabs = [
  { key: 'balances', label: 'Остатки на дату' },
  { key: 'cash-gaps', label: 'Кассовые разрывы' },
  { key: 'plan-fact', label: 'План-Факт' },
  { key: 'history', label: 'История отчётов' },
];

export default function ReportsPage() {
  const [tab, setTab] = useState('balances');

  return (
    <div className="page">
      <h1 className="page__title">Отчёты</h1>

      <div className="admin-tabs">
        {tabs.map((t) => (
          <button
            key={t.key}
            className={'admin-tabs__item' + (tab === t.key ? ' admin-tabs__item--active' : '')}
            onClick={() => setTab(t.key)}
            type="button"
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'balances' && <BalancesReport />}
      {tab === 'cash-gaps' && <CashGapsReport />}
      {tab === 'plan-fact' && <PlanFactReport />}
      {tab === 'history' && <ReportsHistory />}
    </div>
  );
}

function BalancesReport() {
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  async function handleFetch() {
    setLoading(true);
    setError(null);
    try {
      setData(await fetchBalancesReport(date));
    } catch (err) {
      setError(err.response?.data?.message || err.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="report-block">
      <div className="report-filters">
        <label>Дата: <input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></label>
        <button className="btn btn--primary" onClick={handleFetch}>Показать</button>
        <button className="btn" onClick={() => downloadBalancesReport(date)}>Скачать Excel</button>
      </div>

      {loading && <div className="data-table__state">Загрузка…</div>}
      {error && <div className="data-table__state data-table__state--error">{error}</div>}

      {data && (
        <>
          <p><strong>Итого:</strong> {data.total_balance_rub_formatted}</p>
          <table className="data-table">
            <thead>
              <tr>
                <th>Счёт</th>
                <th>Валюта</th>
                <th style={{ textAlign: 'right' }}>Остаток</th>
              </tr>
            </thead>
            <tbody>
              {data.accounts.map((acc) => (
                <tr key={acc.account_id}>
                  <td>{acc.account_name}</td>
                  <td>{acc.currency}</td>
                  <td style={{ textAlign: 'right' }}>{acc.balance_formatted}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}

function CashGapsReport() {
  const [from, setFrom] = useState(new Date().toISOString().slice(0, 10));
  const [to, setTo] = useState(new Date().toISOString().slice(0, 10));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  async function handleFetch() {
    setLoading(true);
    setError(null);
    try {
      setData(await fetchCashGapsReport(from, to));
    } catch (err) {
      setError(err.response?.data?.message || err.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="report-block">
      <div className="report-filters">
        <label>С: <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></label>
        <label>По: <input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></label>
        <button className="btn btn--primary" onClick={handleFetch}>Показать</button>
        <button className="btn" onClick={() => downloadCashGapsReport(from, to)}>Скачать Excel</button>
      </div>

      {loading && <div className="data-table__state">Загрузка…</div>}
      {error && <div className="data-table__state data-table__state--error">{error}</div>}

      {data && (
        <>
          <p><strong>Всего разрывов:</strong> {data.total_gaps}</p>
          <table className="data-table">
            <thead>
              <tr>
                <th>Счёт</th>
                <th>Дата</th>
                <th style={{ textAlign: 'right' }}>Остаток</th>
                <th style={{ textAlign: 'right' }}>Дефицит</th>
              </tr>
            </thead>
            <tbody>
              {data.cash_gaps.map((gap, idx) => (
                <tr key={idx}>
                  <td>{gap.account_name}</td>
                  <td>{gap.date}</td>
                  <td style={{ textAlign: 'right' }}>{formatMoney(gap.balance_end)}</td>
                  <td style={{ textAlign: 'right' }}>{formatMoney(gap.deficit)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}

function PlanFactReport() {
  const [from, setFrom] = useState(new Date().toISOString().slice(0, 10));
  const [to, setTo] = useState(new Date().toISOString().slice(0, 10));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  async function handleFetch() {
    setLoading(true);
    setError(null);
    try {
      setData(await fetchPlanFactReport(from, to));
    } catch (err) {
      setError(err.response?.data?.message || err.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="report-block">
      <div className="report-filters">
        <label>С: <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></label>
        <label>По: <input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></label>
        <button className="btn btn--primary" onClick={handleFetch}>Показать</button>
        <button className="btn" onClick={() => downloadPlanFactReport(from, to)}>Скачать Excel</button>
      </div>

      {loading && <div className="data-table__state">Загрузка…</div>}
      {error && <div className="data-table__state data-table__state--error">{error}</div>}

      {data && (
        <table className="data-table">
          <thead>
            <tr>
              <th></th>
              <th style={{ textAlign: 'right' }}>План</th>
              <th style={{ textAlign: 'right' }}>Факт</th>
              <th style={{ textAlign: 'right' }}>% исполнения</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Поступления</td>
              <td style={{ textAlign: 'right' }}>{data.incomes.plan_formatted}</td>
              <td style={{ textAlign: 'right' }}>{data.incomes.fact_formatted}</td>
              <td style={{ textAlign: 'right' }}>{data.incomes.execution_percent}%</td>
            </tr>
            <tr>
              <td>Платежи</td>
              <td style={{ textAlign: 'right' }}>{data.payments.plan_formatted}</td>
              <td style={{ textAlign: 'right' }}>{data.payments.fact_formatted}</td>
              <td style={{ textAlign: 'right' }}>{data.payments.execution_percent}%</td>
            </tr>
          </tbody>
        </table>
      )}
    </div>
  );
}

function ReportsHistory() {
  const [reports, setReports] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchReportsHistory()
      .then(setReports)
      .catch((err) => setError(err.response?.data?.message || err.message));
  }, []);

  const typeLabels = {
    balances: 'Остатки по счетам',
    cash_gaps: 'Кассовые разрывы',
    plan_fact: 'План-факт',
  };

  return (
    <div className="report-block">
      {error && <div className="data-table__state data-table__state--error">{error}</div>}
      {!error && !reports && <div className="data-table__state">Загрузка…</div>}
      {!error && reports && reports.length === 0 && (
        <div className="data-table__state">Сохранённых отчётов пока нет</div>
      )}
      {!error && reports && reports.length > 0 && (
        <table className="data-table">
          <thead>
            <tr>
              <th>Тип</th>
              <th>Период</th>
              <th>Дата создания</th>
              <th>Файл</th>
            </tr>
          </thead>
          <tbody>
            {reports.map((r) => (
              <tr key={r.id}>
                <td>{typeLabels[r.type] || r.type}</td>
                <td>
                  {r.period_from || r.date || '—'}
                  {r.period_to ? ` — ${r.period_to}` : ''}
                </td>
                <td>{r.created_at ? new Date(r.created_at).toLocaleString('ru-RU') : '—'}</td>
                <td>
                  {r.file_url ? (
                    <a href={r.file_url} target="_blank" rel="noreferrer">Скачать</a>
                  ) : (
                    <span title="Бекендер пока не прислал эндпоинт скачивания конкретного сохранённого отчёта (например GET /reports/history/{id}/download)">
                      нет ссылки
                    </span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
