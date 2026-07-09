import { apiClient } from './client';

/**
 * GET /audit/logs — список всех записей аудита (доступ: admin, manager)
 * GET /audit/logs/{id} — одна запись
 * GET /audit/payment/{paymentId} — история по конкретному платежу
 *
 * Точная форма ответа (пагинация/обёртка) бекендером не присылалась —
 * обрабатываем и массив, и {items: [...]}/{data: [...]} на всякий случай.
 */
function normalizeList(data) {
  if (Array.isArray(data)) return data;
  return data.items || data.data || data.logs || [];
}

export async function fetchAuditLogs(params = {}) {
  const { data } = await apiClient.get('/audit/logs', { params });
  return normalizeList(data);
}

export async function fetchAuditLogEntry(id) {
  const { data } = await apiClient.get(`/audit/logs/${id}`);
  return data;
}

export async function fetchPaymentAuditHistory(paymentId) {
  const { data } = await apiClient.get(`/audit/payment/${paymentId}`);
  return normalizeList(data);
}
