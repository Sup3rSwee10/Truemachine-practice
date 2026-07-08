import { useEffect, useState } from 'react';
import { fetchPaymentCalendar } from '../api/calendar';
import { reschedulePayment } from '../api/payments';
import { entities, paymentStatusLabel } from '../config/entities';
import { formatMoney } from '../utils/money';

function startOfWeek(date) {
  const d = new Date(date);
  const day = (d.getDay() + 6) % 7; // понедельник = 0
  d.setDate(d.getDate() - day);
  return d;
}

function toISODate(d) {
  return d.toISOString().slice(0, 10);
}

const statusOptions = [
  { value: 'draft', label: 'Черновик' },
  { value: 'under_approval', label: 'На согласовании' },
  { value: 'approved', label: 'Согласована' },
  { value: 'approved_moved', label: 'Согласована (перенесена)' },
  { value: 'in_registry', label: 'В реестре' },
  { value: 'paid', label: 'Оплачена' },
  { value: 'rejected', label: 'Отклонена' },
];

export default function PaymentCalendarPage() {
  const [period, setPeriod] = useState('week'); // week | month | custom
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');

  const [accountId, setAccountId] = useState('');
  const [itemId, setItemId] = useState('');
  const [counterpartyId, setCounterpartyId] = useState('');
  const [status, setStatus] = useState('');

  const [accounts, setAccounts] = useState([]);
  const [articles, setArticles] = useState([]);
  const [counterparties, setCounterparties] = useState([]);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState(null); // предупреждение от сервера после переноса
  const [dragOverKey, setDragOverKey] = useState(null);

  useEffect(() => {
    entities.accounts.api.list().then(({ items }) => setAccounts(items)).catch(() => {});
    entities.articles.api.list().then(({ items }) => setArticles(items)).catch(() => {});
    entities.counterparties.api.list().then(({ items }) => setCounterparties(items)).catch(() => {});
  }, []);

  function getRange() {
    const today = new Date();
    if (period === 'week') {
      const from = startOfWeek(today);
      const to = new Date(from);
      to.setDate(to.getDate() + 6);
      return { from: toISODate(from), to: toISODate(to) };
    }
    if (period === 'month') {
      const from = new Date(today.getFullYear(), today.getMonth(), 1);
      const to = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      return { from: toISODate(from), to: toISODate(to) };
    }
    return { from: customFrom, to: customTo };
  }

  function load() {
    const { from, to } = getRange();
    if (!from || !to) return;
    setLoading(true);
    setError(null);
    fetchPaymentCalendar({
      from,
      to,
      accountId: accountId || undefined,
      itemId: itemId || undefined,
      counterpartyId: counterpartyId || undefined,
      status: status || undefined,
    })
      .then(setData)
      .catch((err) => setError(err.response?.data?.message || err.message))
      .finally(() => setLoading(false));
  }

  useEffect(load, [period, customFrom, customTo, accountId, itemId, counterpartyId, status]);

  const days = data?.accounts?.[0]?.days?.map((d) => d.date) || [];

  function handleDragStart(e, item) {
    if (item.type !== 'payment') return; // перенос "на лету" поддержан API только для заявок
    e.dataTransfer.setData('text/plain', JSON.stringify({ id: item.id }));
    e.dataTransfer.effectAllowed = 'move';
  }

  async function handleDrop(e, newDate, cellKey) {
    e.preventDefault();
    setDragOverKey(null);
    let payload;
    try {
      payload = JSON.parse(e.dataTransfer.getData('text/plain'));
    } catch {
      return;
    }
    if (!payload?.id) return;

    try {
      const result = await reschedulePayment(payload.id, newDate);
      if (result.warning) {
        setNotice({ type: 'warning', text: result.warning });
      } else {
        setNotice({ type: 'success', text: `Заявка №${payload.id} перенесена на ${newDate}` });
      }
      load();
    } catch (err) {
      setNotice({ type: 'error', text: err.response?.data?.message || 'Не удалось перенести заявку' });
    }
  }

  return (
    <div className="page">
      <h1 className="page__title">Платёжный календарь</h1>

      {notice && (
        <div className={`calendar-notice calendar-notice--${notice.type}`}>
          {notice.text}
          <button className="calendar-notice__close" onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}

      <div className="calendar-filters">
        <label>
          <input type="radio" checked={period === 'week'} onChange={() => setPeriod('week')} />
          Текущая неделя
        </label>
        <label>
          <input type="radio" checked={period === 'month'} onChange={() => setPeriod('month')} />
          Текущий месяц
        </label>
        <label>
          <input type="radio" checked={period === 'custom'} onChange={() => setPeriod('custom')} />
          С
        </label>
        <input
          type="date"
          value={customFrom}
          onChange={(e) => {
            setCustomFrom(e.target.value);
            setPeriod('custom');
          }}
        />
        <span>по</span>
        <input
          type="date"
          value={customTo}
          onChange={(e) => {
            setCustomTo(e.target.value);
            setPeriod('custom');
          }}
        />
      </div>

      <div className="calendar-filters">
        <select value={accountId} onChange={(e) => setAccountId(e.target.value)}>
          <option value="">Все счета</option>
          {accounts.map((acc) => (
            <option key={acc.id} value={acc.id}>{acc.name}</option>
          ))}
        </select>
        <select value={itemId} onChange={(e) => setItemId(e.target.value)}>
          <option value="">Все статьи</option>
          {articles.map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>
        <select value={counterpartyId} onChange={(e) => setCounterpartyId(e.target.value)}>
          <option value="">Все контрагенты</option>
          {counterparties.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Все статусы</option>
          {statusOptions.map((s) => (
            <option key={s.value} value={s.value}>{s.label}</option>
          ))}
        </select>
      </div>

      {loading && <div className="data-table__state">Загрузка данных…</div>}
      {error && <div className="data-table__state data-table__state--error">Не удалось загрузить: {error}</div>}

      {!loading && !error && data && (
        <>
          <p className="calendar-hint">
            Перетащите заявку (жёлтая плашка) в другую ячейку дня, чтобы перенести дату списания.
          </p>
          <table className="calendar-table">
            <thead>
              <tr>
                <th>Счёт</th>
                {days.map((day) => (
                  <th key={day}>{formatDayLabel(day)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.accounts.map(({ account, days: accDays }) => (
                <tr key={account.id}>
                  <td className="calendar-table__account">{account.name}</td>
                  {accDays.map((cell) => {
                    const cellKey = `${account.id}-${cell.date}`;
                    return (
                      <td
                        key={cell.date}
                        className={
                          'calendar-table__cell' +
                          (cell.is_cash_gap ? ' calendar-table__cell--overdue' : '') +
                          (dragOverKey === cellKey ? ' calendar-table__cell--dragover' : '')
                        }
                        onDragOver={(e) => {
                          e.preventDefault();
                          setDragOverKey(cellKey);
                        }}
                        onDragLeave={() => setDragOverKey((k) => (k === cellKey ? null : k))}
                        onDrop={(e) => handleDrop(e, cell.date, cellKey)}
                      >
                        <div>+{formatMoney(cell.incomes)}</div>
                        <div>−{formatMoney(cell.payments)}</div>
                        <div className="calendar-table__balance">{formatMoney(cell.balance_end)}</div>
                        {cell.items?.length > 0 && (
                          <div className="calendar-table__items">
                            {cell.items.map((item) => (
                              <div
                                key={`${item.type}-${item.id}`}
                                draggable={item.type === 'payment'}
                                onDragStart={(e) => handleDragStart(e, item)}
                                className={
                                  'calendar-item calendar-item--' + item.type +
                                  (item.type === 'payment' ? ' calendar-item--draggable' : '')
                                }
                                title={
                                  item.type === 'payment'
                                    ? `Заявка №${item.id}${item.status ? ', ' + paymentStatusLabel(item.status) : ''}${item.counterparty ? ', ' + item.counterparty : ''} — можно перетащить на другой день`
                                    : `Поступление №${item.id}${item.counterparty ? ', ' + item.counterparty : ''}`
                                }
                              >
                                {formatMoney(item.amount)}
                              </div>
                            ))}
                          </div>
                        )}
                      </td>
                    );
                  })}
                </tr>
              ))}
              <tr className="calendar-table__totals">
                <td>Итого</td>
                {days.map((day) => (
                  <td key={day}>{formatMoney(sumBalanceForDay(data.accounts, day))}</td>
                ))}
              </tr>
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}

function sumBalanceForDay(accounts, day) {
  return accounts.reduce((sum, { days }) => {
    const cell = days.find((d) => d.date === day);
    return sum + (cell?.balance_end || 0);
  }, 0);
}

function formatDayLabel(isoDate) {
  const d = new Date(isoDate);
  return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
}
