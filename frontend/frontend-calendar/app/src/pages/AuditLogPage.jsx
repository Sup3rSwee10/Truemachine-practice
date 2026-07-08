export default function AuditLogPage() {
  return (
    <div className="page">
      <h1 className="page__title">Журнал аудита</h1>
      <div className="data-table__state">
        В переданной спецификации API (openapi.yaml) эндпоинт журнала аудита отсутствует.
        История согласований по конкретной заявке доступна через
        <code> GET /payments/{'{id}'}/approvals</code> — как только появится общий эндпоинт
        аудита (например, <code>GET /admin/audit-log</code>), эту страницу останется
        подключить по тому же шаблону, что и остальные разделы.
      </div>
    </div>
  );
}
