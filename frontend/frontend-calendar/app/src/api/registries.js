import { apiClient } from './client';
import { downloadFile } from '../utils/download';

// Заполнить реестр всеми согласованными заявками (POST /registries/{id}/attach)
export async function attachPayments(id) {
  const { data } = await apiClient.post(`/registries/${id}/attach`);
  return data;
}

// Скачать реестр в Excel
export async function exportRegistry(id) {
  await downloadFile(apiClient, `/registries/${id}/export`, {}, `registry-${id}.xlsx`);
}
