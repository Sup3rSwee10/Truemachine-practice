import { apiClient } from './client';

/**
 * GET /calendar?start_date&end_date&account_id&item_id&counterparty_id&status
 * -> {
 *      period: { start, end },
 *      accounts: [
 *        { account: {...}, days: [ { date, incomes, payments, balance_start,
 *                                    balance_end, is_cash_gap, items: [...] } ] }
 *      ]
 *    }
 * Суммы — в копейках.
 */
export async function fetchPaymentCalendar({ from, to, accountId, itemId, counterpartyId, status }) {
  const { data } = await apiClient.get('/calendar', {
    params: {
      start_date: from,
      end_date: to,
      account_id: accountId || undefined,
      item_id: itemId || undefined,
      counterparty_id: counterpartyId || undefined,
      status: status || undefined,
    },
  });
  return data;
}
