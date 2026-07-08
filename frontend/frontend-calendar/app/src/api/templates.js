import { apiClient } from './client';

// Ручная генерация заявок/поступлений по повторяющемуся шаблону
export async function generateFromTemplate(id) {
  const { data } = await apiClient.post(`/recurring-templates/${id}/generate`);
  return data;
}
