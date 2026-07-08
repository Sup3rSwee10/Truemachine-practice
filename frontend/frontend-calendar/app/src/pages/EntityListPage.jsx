import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { entities } from '../config/entities';
import DataTable from '../components/DataTable';
import ImportModal from '../components/ImportModal';
import RequestRowActions from './requests/RequestRowActions';
import RegistryRowActions from './registries/RegistryRowActions';
import TemplateRowActions from './templates/TemplateRowActions';
import { useAuth } from '../context/AuthContext';

export default function EntityListPage({ entityKey }) {
  const entity = entities[entityKey];
  const navigate = useNavigate();
  const { user } = useAuth();

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [scope, setScope] = useState('all'); // all | mine
  const [showImport, setShowImport] = useState(false);

  function load() {
    if (!entity) return;
    setLoading(true);
    setError(null);
    entity.api
      .list()
      .then(({ items }) => setRows(entity.mapRow ? items.map(entity.mapRow) : items))
      .catch((err) => setError(err.response?.data?.message || err.message))
      .finally(() => setLoading(false));
  }

  useEffect(load, [entity, entityKey]);

  const hasCreatedBy = rows.length > 0 && rows[0].created_by !== undefined;

  const visibleRows = useMemo(() => {
    if (entity?.showMineToggle && scope === 'mine' && hasCreatedBy && user) {
      return rows.filter((r) => r.created_by === user.id);
    }
    return rows;
  }, [rows, scope, entity, user, hasCreatedBy]);

  if (!entity) {
    return <div className="page">Раздел не найден</div>;
  }

  async function handleDelete(id) {
    if (!window.confirm('Удалить запись?')) return;
    try {
      await entity.api.remove(id);
      load();
    } catch (err) {
      alert(err.response?.data?.message || 'Не удалось удалить запись');
    }
  }

  const needsActionsColumn =
    entity.canEdit || entity.canDelete || entityKey === 'requests' || entityKey === 'registries' || entityKey === 'templates';

  function renderActions(row) {
    return (
      <div className="row-actions">
        {entityKey === 'requests' && <RequestRowActions payment={row} onChanged={load} />}
        {entityKey === 'registries' && <RegistryRowActions registry={row} onChanged={load} />}
        {entityKey === 'templates' && <TemplateRowActions template={row} onChanged={load} />}
        {entity.canEdit && (
          <button className="btn btn--small" onClick={() => navigate(`/${entity.routePath}/${row.id}`)}>
            Редактировать
          </button>
        )}
        {entity.canDelete && (
          <button className="btn btn--small btn--danger" onClick={() => handleDelete(row.id)}>
            Удалить
          </button>
        )}
      </div>
    );
  }

  return (
    <div className="page">
      <div className="page__header">
        <h1 className="page__title">{entity.title}</h1>
        <div className="page__header-actions">
          {entity.canImport && (
            <button className="btn" onClick={() => setShowImport(true)}>
              Импорт из файла
            </button>
          )}
          <Link to={`/${entity.routePath}/new`} className="btn btn--primary">
            + Добавить
          </Link>
        </div>
      </div>

      {entity.showMineToggle && (
        <div className="scope-toggle">
          <button
            className={'scope-toggle__item' + (scope === 'all' ? ' scope-toggle__item--active' : '')}
            onClick={() => setScope('all')}
          >
            Все заявки
          </button>
          <button
            className={'scope-toggle__item' + (scope === 'mine' ? ' scope-toggle__item--active' : '')}
            onClick={() => setScope('mine')}
            disabled={!hasCreatedBy}
            title={!hasCreatedBy ? 'API не возвращает автора заявки (поле created_by) — фильтр недоступен, пока это не исправят на бэкенде' : undefined}
          >
            Мои заявки
          </button>
          {!hasCreatedBy && !loading && (
            <span className="scope-toggle__hint">
              ⚠ API не отдаёт автора заявки — фильтр «Мои» временно недоступен
            </span>
          )}
        </div>
      )}

      <DataTable
        columns={entity.columns}
        rows={visibleRows}
        loading={loading}
        error={error}
        renderActions={needsActionsColumn ? renderActions : undefined}
      />

      {showImport && (
        <ImportModal
          kind={entity.canImport}
          onClose={() => setShowImport(false)}
          onDone={load}
        />
      )}
    </div>
  );
}
