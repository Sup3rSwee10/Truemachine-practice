import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { entities } from '../config/entities';
import EntityForm from '../components/EntityForm';
import { kopecksToRub, rubToKopecks } from '../utils/money';

export default function EntityFormPage({ entityKey }) {
  const entity = entities[entityKey];
  const { id } = useParams(); // если есть id — это редактирование
  const navigate = useNavigate();

  const [initialValues, setInitialValues] = useState({});
  const [loading, setLoading] = useState(!!id);

  useEffect(() => {
    if (!id || !entity) return;
    entity.api
      .get(id)
      .then((data) => setInitialValues(toFormValues(data, entity)))
      .finally(() => setLoading(false));
  }, [id, entity]);

  if (!entity) {
    return <div className="page">Раздел не найден</div>;
  }

  async function handleSubmit(values) {
    const payload = toApiPayload(values, entity);
    if (id) {
      await entity.api.update(id, payload);
    } else {
      await entity.api.create(payload);
    }
    navigate(`/${entity.routePath}`);
  }

  return (
    <div className="page">
      <h1 className="page__title">{id ? `${entity.title} — Редактирование` : entity.createTitle}</h1>
      {loading ? (
        <div className="data-table__state">Загрузка…</div>
      ) : (
        <EntityForm
          fields={entity.fields.filter((f) => !(f.hideOnCreate && !id))}
          initialValues={initialValues}
          onSubmit={handleSubmit}
        />
      )}
    </div>
  );
}

// API -> форма: копейки переводим в рубли для отображения
function toFormValues(data, entity) {
  const values = { ...data };
  (entity.moneyFields || []).forEach((f) => {
    if (values[f] !== undefined) values[f] = kopecksToRub(values[f]);
  });
  return values;
}

// Форма -> API: рубли переводим обратно в копейки, убираем пустые необязательные поля
function toApiPayload(values, entity) {
  const payload = { ...values };
  (entity.moneyFields || []).forEach((f) => {
    if (payload[f] !== undefined && payload[f] !== '') payload[f] = rubToKopecks(payload[f]);
  });
  // Пустая строка для необязательного select/number -> убираем поле, а не отправляем ''
  Object.keys(payload).forEach((key) => {
    if (payload[key] === '') delete payload[key];
  });
  return payload;
}
