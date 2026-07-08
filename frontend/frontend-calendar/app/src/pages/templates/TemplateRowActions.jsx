import { useState } from 'react';
import { generateFromTemplate } from '../../api/templates';

export default function TemplateRowActions({ template, onChanged }) {
  const [busy, setBusy] = useState(false);

  async function handleGenerate() {
    setBusy(true);
    try {
      const result = await generateFromTemplate(template.id);
      alert(`Сгенерировано: ${result.generated_count}`);
      onChanged();
    } catch (err) {
      alert(err.response?.data?.message || 'Не удалось сгенерировать заявки');
    } finally {
      setBusy(false);
    }
  }

  return (
    <button className="btn btn--small" disabled={busy} onClick={handleGenerate}>
      Сгенерировать
    </button>
  );
}
