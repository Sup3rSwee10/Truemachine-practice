import { apiClient } from './client';

export async function importPayments(file, accountId) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('account_id', accountId);
  const { data } = await apiClient.post('/payments/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}

export async function importIncomes(file, accountId) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('account_id', accountId);
  const { data } = await apiClient.post('/incomes/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}
