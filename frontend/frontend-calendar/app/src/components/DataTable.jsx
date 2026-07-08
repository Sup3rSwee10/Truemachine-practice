export default function DataTable({ columns, rows, loading, error, emptyText = 'Нет данных', renderActions }) {
  if (loading) {
    return <div className="data-table__state">Загрузка данных…</div>;
  }
  if (error) {
    return <div className="data-table__state data-table__state--error">Не удалось загрузить данные: {error}</div>;
  }
  if (!rows || rows.length === 0) {
    return <div className="data-table__state">{emptyText}</div>;
  }

  return (
    <table className="data-table">
      <thead>
        <tr>
          {columns.map((col) => (
            <th key={col.key} style={{ textAlign: col.align || 'left' }}>
              {col.label}
            </th>
          ))}
          {renderActions && <th>Действия</th>}
        </tr>
      </thead>
      <tbody>
        {rows.map((row, idx) => (
          <tr key={row.id ?? idx}>
            {columns.map((col) => (
              <td key={col.key} style={{ textAlign: col.align || 'left' }}>
                {formatValue(row[col.key])}
              </td>
            ))}
            {renderActions && <td className="data-table__actions">{renderActions(row)}</td>}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function formatValue(value) {
  if (typeof value === 'boolean') return value ? 'Да' : 'Нет';
  if (value === null || value === undefined) return '—';
  return value;
}
