import { useEffect, useState } from 'react';
import { entities } from '../config/entities';

export default function EntityForm({ fields, initialValues = {}, onSubmit, submitLabel = 'Сохранить' }) {
  const [values, setValues] = useState(initialValues);
  const [relatedOptions, setRelatedOptions] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  // Подгружаем варианты для select-полей, которые ссылаются на другую сущность (optionsFrom)
  useEffect(() => {
    fields
      .filter((f) => f.optionsFrom)
      .forEach(async (f) => {
        const refEntity = entities[f.optionsFrom];
        if (!refEntity) return;
        try {
          const { items } = await refEntity.api.list();
          setRelatedOptions((prev) => ({
            ...prev,
            [f.name]: items.map((item) => ({
              value: item.id,
              label: item.name ?? item.fullName ?? item.number ?? String(item.id),
              raw: item,
            })),
          }));
        } catch {
          // Если справочник не загрузился — просто оставляем пустой список,
          // пользователь всё равно увидит остальную форму.
          setRelatedOptions((prev) => ({ ...prev, [f.name]: [] }));
        }
      });
  }, [fields]);

  function handleChange(name, value) {
    setValues((prev) => ({ ...prev, [name]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await onSubmit(values);
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Не удалось сохранить');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className="entity-form" onSubmit={handleSubmit}>
      {fields.map((field) => {
        const isRequired = typeof field.required === 'function' ? field.required(values) : field.required;
        return (
          <div className="entity-form__row" key={field.name}>
            <label className="entity-form__label" htmlFor={field.name}>
              {field.label}
              {isRequired && <span className="entity-form__required">*</span>}
            </label>
            {renderInput({ ...field, required: isRequired }, values, handleChange, relatedOptions)}
            {field.hint && <div className="entity-form__hint">{field.hint}</div>}
          </div>
        );
      })}

      {error && <div className="entity-form__error">{error}</div>}

      <div className="entity-form__actions">
        <button type="submit" className="btn btn--primary" disabled={submitting}>
          {submitting ? 'Сохранение…' : submitLabel}
        </button>
      </div>
    </form>
  );
}

function renderInput(field, values, handleChange, relatedOptions) {
  const value = values[field.name] ?? '';
  const commonProps = {
    id: field.name,
    name: field.name,
    value,
    required: field.required,
    onChange: (e) => handleChange(field.name, e.target.value),
  };

  switch (field.type) {
    case 'textarea':
      return <textarea {...commonProps} rows={3} />;
    case 'select': {
      let options = field.optionsFrom ? relatedOptions[field.name] || [] : field.options || [];
      if (field.optionsFilter) {
        options = options.filter((opt) => field.optionsFilter(opt.raw, values));
      }
      return (
        <select {...commonProps}>
          <option value="">— выберите —</option>
          {options.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      );
    }
    case 'number':
      return <input {...commonProps} type="number" step="1" />;
    case 'money':
      return <input {...commonProps} type="number" step="0.01" min="0" placeholder="0.00" />;
    case 'multiselect': {
      const selected = Array.isArray(value) ? value : [];
      return (
        <div className="entity-form__checkboxes">
          {(field.options || []).map((opt) => (
            <label key={opt.value} className="entity-form__checkbox">
              <input
                type="checkbox"
                checked={selected.includes(opt.value)}
                onChange={(e) => {
                  const next = e.target.checked
                    ? [...selected, opt.value]
                    : selected.filter((v) => v !== opt.value);
                  handleChange(field.name, next);
                }}
              />
              {opt.label}
            </label>
          ))}
        </div>
      );
    }
    case 'date':
      return <input {...commonProps} type="date" />;
    case 'password':
      return <input {...commonProps} type="password" autoComplete="new-password" />;
    default:
      return <input {...commonProps} type="text" />;
  }
}
