import { apiClient } from './client';
import { downloadFile } from '../utils/download';

export async function fetchReportsHistory() {
  const { data } = await apiClient.get('/reports/history');
  if (Array.isArray(data)) return data;
  return data.items || data.data || data.reports || [];
}

export async function fetchBalancesReport(date) {
  const { data } = await apiClient.get('/reports/balances', { params: { date } });
  return data;
}

export async function fetchCashGapsReport(startDate, endDate) {
  const { data } = await apiClient.get('/reports/cash-gaps', {
    params: { start_date: startDate, end_date: endDate },
  });
  return data;
}

export async function fetchPlanFactReport(startDate, endDate, accountId) {
  const { data } = await apiClient.get('/reports/plan-fact', {
    params: { start_date: startDate, end_date: endDate, account_id: accountId || undefined },
  });
  return data;
}

export async function downloadBalancesReport(date) {
  await downloadFile(apiClient, '/reports/export/balances', { date }, `balances-${date}.xlsx`);
}

export async function downloadCashGapsReport(startDate, endDate) {
  await downloadFile(
    apiClient,
    '/reports/export/cash-gaps',
    { start_date: startDate, end_date: endDate },
    `cash-gaps-${startDate}_${endDate}.xlsx`
  );
}

export async function downloadPlanFactReport(startDate, endDate) {
  await downloadFile(
    apiClient,
    '/reports/export/plan-fact',
    { start_date: startDate, end_date: endDate },
    `plan-fact-${startDate}_${endDate}.xlsx`
  );
}
