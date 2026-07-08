import { apiClient } from './client';

/**
 * Фабрика стандартных REST-запросов для сущности, под реальный API
 * (см. openapi.yaml). Разные разделы оборачивают список по-разному:
 *   - /accounts, /counterparties, /items, /payments, /incomes, /registries -> просто массив
 *   - /recurring-templates -> { total, templates: [...] }
 *   - /admin/users -> { users: [...] }
 * listKey указывает, под каким ключом искать массив, если это не сам ответ.
 *
 * getById/update/remove доступны не для всех сущностей (например, у /items
 * нет GET/PUT/DELETE по id) — это указывается через canGet/canEdit/canDelete
 * в конфиге сущности (src/config/entities.js), а не здесь.
 */
export function createResourceApi(basePath, { listKey } = {}) {
  return {
    async list(params = {}) {
      const { data } = await apiClient.get(`/${basePath}`, { params });
      if (listKey && data && Array.isArray(data[listKey])) {
        return { items: data[listKey], total: data.total ?? data[listKey].length };
      }
      if (Array.isArray(data)) {
        return { items: data, total: data.length };
      }
      return { items: data.items ?? [], total: data.total ?? data.items?.length ?? 0 };
    },
    async get(id) {
      const { data } = await apiClient.get(`/${basePath}/${id}`);
      return data;
    },
    async create(payload) {
      const { data } = await apiClient.post(`/${basePath}`, payload);
      return data;
    },
    async update(id, payload) {
      const { data } = await apiClient.put(`/${basePath}/${id}`, payload);
      return data;
    },
    async remove(id) {
      await apiClient.delete(`/${basePath}/${id}`);
    },
  };
}
