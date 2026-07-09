import { createResourceApi } from '../api/resource';
import { formatMoney } from '../utils/money';

/**
 * Конфигурация CRUD-разделов под реальный API (см. openapi.yaml).
 * basePath — путь ресурса в API. moneyFields — поля, которые API хранит
 * в копейках (в форме показываем и принимаем рубли, конвертация — в
 * EntityFormPage). canEdit/canDelete отражают то, какие операции реально
 * есть у ресурса в API.
 */

const priorityOptions = [
  { value: 'low', label: 'Низкий' },
  { value: 'medium', label: 'Средний' },
  { value: 'high', label: 'Высокий' },
];

const statusLabels = {
  draft: 'Черновик',
  under_approval: 'На согласовании',
  approved: 'Согласована',
  approved_moved: 'Согласована (перенесена)',
  in_registry: 'В реестре',
  paid: 'Оплачена',
  rejected: 'Отклонена',
};

export function paymentStatusLabel(status) {
  return statusLabels[status] || status;
}

export const entities = {
  // Заявки на платёж — API: /payments
  requests: {
    title: 'Заявки',
    createTitle: 'Заявки — Создать заявку',
    routePath: 'requests',
    basePath: 'payments',
    api: createResourceApi('payments'),
    canEdit: true,
    canDelete: true,
    canImport: 'payments',
    showMineToggle: true,
    moneyFields: ['amount'],
    columns: [
      { key: 'id', label: '№' },
      { key: 'name', label: 'Название' },
      { key: 'planned_date', label: 'Дата списания' },
      { key: 'counterpartyName', label: 'Контрагент' },
      { key: 'itemName', label: 'Статья' },
      { key: 'accountName', label: 'Счёт' },
      { key: 'amountFormatted', label: 'Сумма', align: 'right' },
      { key: 'priority', label: 'Приоритет' },
      { key: 'statusLabel', label: 'Статус' },
    ],
    mapRow: (row) => ({
      ...row,
      counterpartyName: row.counterparty?.name,
      itemName: row.item?.name,
      accountName: row.account?.name,
      amountFormatted: formatMoney(row.amount),
      statusLabel: paymentStatusLabel(row.status),
    }),
    fields: [
      { name: 'name', label: 'Название', type: 'text', required: true },
      { name: 'planned_date', label: 'Дата списания', type: 'date', required: true },
      { name: 'account_id', label: 'Счёт', type: 'select', optionsFrom: 'accounts', required: true },
      { name: 'item_id', label: 'Статья', type: 'select', optionsFrom: 'articles', optionsFilter: (item) => item.type === 'expense', required: true },
      { name: 'counterparty_id', label: 'Контрагент', type: 'select', optionsFrom: 'counterparties' },
      { name: 'amount', label: 'Сумма, ₽', type: 'money', required: true },
      { name: 'priority', label: 'Приоритет', type: 'select', options: priorityOptions, required: true },
      {
        name: 'status',
        label: 'Статус при создании',
        type: 'select',
        options: [
          { value: 'draft', label: 'В черновик' },
          { value: 'under_approval', label: 'Отправить на согласование' },
        ],
      },
    ],
  },

  // Поступления — API: /incomes
  receipts: {
    title: 'Поступления',
    createTitle: 'Поступления — Создать поступление',
    routePath: 'receipts',
    basePath: 'incomes',
    api: createResourceApi('incomes'),
    canEdit: true,
    canDelete: true,
    canImport: 'incomes',
    moneyFields: ['amount'],
    columns: [
      { key: 'id', label: '№' },
      { key: 'name', label: 'Название' },
      { key: 'planned_date', label: 'Дата поступления' },
      { key: 'counterpartyName', label: 'Контрагент' },
      { key: 'itemName', label: 'Статья' },
      { key: 'accountName', label: 'Счёт' },
      { key: 'amountFormatted', label: 'Сумма', align: 'right' },
    ],
    mapRow: (row) => ({
      ...row,
      counterpartyName: row.counterparty?.name,
      itemName: row.item?.name,
      accountName: row.account?.name,
      amountFormatted: formatMoney(row.amount),
    }),
    fields: [
      { name: 'name', label: 'Название', type: 'text', required: true },
      { name: 'planned_date', label: 'Дата поступления', type: 'date', required: true },
      { name: 'account_id', label: 'Счёт', type: 'select', optionsFrom: 'accounts', required: true },
      { name: 'item_id', label: 'Статья', type: 'select', optionsFrom: 'articles', optionsFilter: (item) => item.type === 'income', required: true },
      { name: 'counterparty_id', label: 'Контрагент', type: 'select', optionsFrom: 'counterparties' },
      { name: 'amount', label: 'Сумма, ₽', type: 'money', required: true },
      { name: 'description', label: 'Описание', type: 'textarea' },
    ],
  },

  // Шаблоны повторяющихся операций — API: /recurring-templates
  templates: {
    title: 'Шаблоны',
    createTitle: 'Шаблоны — Создать шаблон',
    routePath: 'templates',
    basePath: 'recurring-templates',
    api: createResourceApi('recurring-templates', { listKey: 'templates' }),
    canEdit: true,
    canDelete: true,
    moneyFields: ['amount'],
    columns: [
      { key: 'name', label: 'Название' },
      { key: 'type', label: 'Тип' },
      { key: 'amountFormatted', label: 'Сумма', align: 'right' },
      { key: 'frequency', label: 'Периодичность' },
      { key: 'start_date', label: 'Начало' },
      { key: 'end_date', label: 'Окончание' },
    ],
    mapRow: (row) => ({ ...row, amountFormatted: formatMoney(row.amount) }),
    fields: [
      { name: 'name', label: 'Название шаблона', type: 'text', required: true },
      {
        name: 'type',
        label: 'Тип',
        type: 'select',
        options: [
          { value: 'payment', label: 'Платёж' },
          { value: 'income', label: 'Поступление' },
        ],
        required: true,
      },
      { name: 'amount', label: 'Сумма, ₽', type: 'money', required: true },
      { name: 'account_id', label: 'Счёт', type: 'select', optionsFrom: 'accounts', required: true },
      {
        name: 'item_id',
        label: 'Статья',
        type: 'select',
        optionsFrom: 'articles',
        optionsFilter: (item, values) => item.type === (values.type === 'income' ? 'income' : 'expense'),
        required: true,
      },
      { name: 'counterparty_id', label: 'Контрагент', type: 'select', optionsFrom: 'counterparties' },
      { name: 'priority', label: 'Приоритет', type: 'select', options: priorityOptions, required: (values) => values.type === 'payment' },
      {
        name: 'frequency',
        label: 'Периодичность',
        type: 'select',
        options: [
          { value: 'daily', label: 'Ежедневно' },
          { value: 'weekly', label: 'Еженедельно' },
          { value: 'monthly', label: 'Ежемесячно' },
        ],
        required: true,
      },
      { name: 'start_date', label: 'Дата начала', type: 'date', required: true },
      { name: 'end_date', label: 'Дата окончания', type: 'date' },
    ],
  },

  // Реестры платежей — API: /registries
  registries: {
    title: 'Реестры',
    createTitle: 'Реестры — Создать реестр',
    routePath: 'registries',
    basePath: 'registries',
    api: createResourceApi('registries'),
    canEdit: true,
    canDelete: true,
    columns: [
      { key: 'id', label: '№' },
      { key: 'name', label: 'Название' },
      { key: 'date', label: 'Дата' },
      { key: 'status', label: 'Статус' },
      { key: 'paymentsCount', label: 'Заявок', align: 'right' },
    ],
    mapRow: (row) => ({ ...row, paymentsCount: row.payments?.length ?? 0 }),
    fields: [
      { name: 'name', label: 'Название', type: 'text', required: true },
      { name: 'date', label: 'Дата', type: 'date', required: true },
      {
        name: 'status',
        label: 'Статус',
        type: 'select',
        hideOnCreate: true,
        options: [
          { value: 'draft', label: 'Черновик' },
          { value: 'sent_to_bank', label: 'Отправлен в банк' },
          { value: 'paid', label: 'Оплачен' },
        ],
        required: true,
      },
    ],
  },

  // Пользователи — API: /admin/users
  users: {
    title: 'Пользователи',
    createTitle: 'Панель администратора — Пользователи — Добавить пользователя',
    routePath: 'admin/users',
    basePath: 'admin/users',
    api: createResourceApi('admin/users', { listKey: 'users' }),
    canEdit: true,
    canDelete: true,
    columns: [
      { key: 'name', label: 'ФИО' },
      { key: 'email', label: 'Email' },
    ],
    fields: [
      { name: 'name', label: 'ФИО', type: 'text', required: true },
      { name: 'email', label: 'Email', type: 'text', required: true },
      { name: 'password', label: 'Пароль', type: 'password', required: true },
      {
        name: 'roles',
        label: 'Роли',
        type: 'multiselect',
        options: [
          { value: 'initiator', label: 'Инициатор' },
          { value: 'treasurer', label: 'Казначей' },
          { value: 'manager', label: 'Руководитель' },
          { value: 'admin', label: 'Администратор' },
        ],
        required: true,
      },
    ],
  },

  // Контрагенты — API: /counterparties
  counterparties: {
    title: 'Контрагенты',
    createTitle: 'Панель администратора — Контрагенты — Добавить контрагента',
    routePath: 'admin/counterparties',
    basePath: 'counterparties',
    api: createResourceApi('counterparties'),
    canEdit: true,
    canDelete: true,
    columns: [
      { key: 'name', label: 'Название' },
      { key: 'inn', label: 'ИНН' },
      { key: 'bank_name', label: 'Банк' },
      { key: 'current_account', label: 'Расчётный счёт' },
    ],
    fields: [
      { name: 'name', label: 'Название', type: 'text', required: true },
      { name: 'inn', label: 'ИНН', type: 'text', required: true },
      { name: 'bank_name', label: 'Банк', type: 'text', required: true },
      { name: 'bik', label: 'БИК', type: 'text', required: true },
      { name: 'correspondent_account', label: 'Корр. счёт', type: 'text', required: true },
      { name: 'current_account', label: 'Расчётный счёт', type: 'text', required: true },
    ],
  },

  // Счета — API: /accounts
  accounts: {
    title: 'Счета',
    createTitle: 'Панель администратора — Счета — Добавить счёт',
    routePath: 'admin/accounts',
    basePath: 'accounts',
    api: createResourceApi('accounts'),
    canEdit: true,
    canDelete: true,
    moneyFields: ['initial_balance'],
    columns: [
      { key: 'name', label: 'Название' },
      { key: 'account_number', label: 'Номер счёта' },
      { key: 'bank_name', label: 'Банк' },
      { key: 'initialBalanceFormatted', label: 'Начальный баланс', align: 'right' },
    ],
    mapRow: (row) => ({ ...row, initialBalanceFormatted: formatMoney(row.initial_balance) }),
    fields: [
      { name: 'name', label: 'Название счёта', type: 'text', required: true },
      { name: 'account_number', label: 'Номер счёта', type: 'text', required: true },
      { name: 'bank_name', label: 'Банк', type: 'text', required: true },
      { name: 'bik', label: 'БИК', type: 'text', required: true },
      { name: 'correspondent_account', label: 'Корр. счёт', type: 'text', required: true },
      { name: 'initial_balance', label: 'Начальный баланс, ₽', type: 'money', required: true },
      {
        name: 'currency_id',
        label: 'Валюта',
        type: 'select',
        required: true,
        options: [
          { value: 1, label: 'RUB (₽)' },
          { value: 2, label: 'USD ($)' },
          { value: 3, label: 'EUR (€)' },
          { value: 4, label: 'AMD (֏)' },
        ],
        hint: 'В API нет справочника валют — ID подобраны по порядку из «Правила.docx» (RUB, USD, EUR, AMD). Если на бэкенде другой порядок — поправьте значения value здесь.',
      },
    ],
  },

  // Статьи — API: /items (только список + создание, нет GET/PUT/DELETE по id)
  articles: {
    title: 'Статьи',
    createTitle: 'Панель администратора — Статьи — Добавить статью',
    routePath: 'admin/articles',
    basePath: 'items',
    api: createResourceApi('items'),
    canEdit: true,
    canDelete: true,
    adminOnly: true, // POST/PUT/DELETE /items — только админ, по данным бекендера
    columns: [
      { key: 'name', label: 'Название' },
      { key: 'typeLabel', label: 'Тип' },
    ],
    mapRow: (row) => ({
      ...row,
      typeLabel: row.type === 'income' ? 'Доходная' : 'Расходная',
    }),
    fields: [
      { name: 'name', label: 'Название статьи', type: 'text', required: true },
      {
        name: 'type',
        label: 'Тип',
        type: 'select',
        options: [
          { value: 'expense', label: 'Расходная' },
          { value: 'income', label: 'Доходная' },
        ],
        required: true,
      },
    ],
  },
};
