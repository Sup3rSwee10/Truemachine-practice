import { apiClient } from './client';

// Специфичные действия над заявками (payments), которых нет в обычном CRUD.
// Базовый CRUD (list/get/create/update/remove) берётся из createResourceApi('payments').

export async function submitForApproval(id) {
  const { data } = await apiClient.put(`/payments/${id}`, { status: 'under_approval' });
  return data;
}

export async function returnToDraft(id) {
  const { data } = await apiClient.put(`/payments/${id}`, { status: 'draft' });
  return data;
}

export async function approvePayment(id, comment) {
  const { data } = await apiClient.post(`/payments/${id}/approve`, { comment });
  return data;
}

export async function rejectPayment(id, comment) {
  const { data } = await apiClient.post(`/payments/${id}/reject`, { comment });
  return data;
}

export async function markAsPaid(id) {
  const { data } = await apiClient.post(`/payments/${id}/mark-as-paid`);
  return data;
}

export async function reschedulePayment(id, newDate) {
  const { data } = await apiClient.post(`/payments/${id}/reschedule`, { new_date: newDate });
  return data;
}

export async function changePaymentAccount(id, newAccountId) {
  const { data } = await apiClient.post(`/payments/${id}/change-account`, {
    new_account_id: newAccountId,
  });
  return data;
}

export async function fetchApprovals(id) {
  const { data } = await apiClient.get(`/payments/${id}/approvals`);
  return data;
}
