import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

// Видимость пунктов меню по ролям — согласно «Правила.docx» п.1.5:
// Инициатор — только свои заявки/поступления/шаблоны;
// Казначей — календарь, реестры, отчёты, согласование;
// Руководитель — согласование, реестры (утверждение), аудит;
// Администратор — всё, включая панель администратора и аудит.
const menuItems = [
  { to: '/calendar', label: 'Платёжный календарь', roles: ['treasurer', 'manager', 'admin'] },
  { to: '/requests', label: 'Заявки', roles: null }, // видно всем ролям
  { to: '/receipts', label: 'Поступления', roles: null },
  { to: '/templates', label: 'Шаблоны', roles: null },
  { to: '/registries', label: 'Реестры', roles: ['treasurer', 'manager', 'admin'] },
  { to: '/reports', label: 'Отчёты', roles: ['treasurer', 'manager', 'admin'] },
  { to: '/admin/users', label: 'Панель администратора', roles: ['admin'] },
  { to: '/audit-log', label: 'Журнал аудита', roles: ['admin', 'manager'] },
];

export default function Sidebar() {
  const { logout, hasRole } = useAuth();

  const visibleItems = menuItems.filter(
    (item) => !item.roles || item.roles.some((r) => hasRole(r))
  );

  return (
    <aside className="sidebar">
      <nav className="sidebar__nav">
        {visibleItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) =>
              'sidebar__item' + (isActive ? ' sidebar__item--active' : '')
            }
          >
            {item.label}
          </NavLink>
        ))}
      </nav>
      <button className="sidebar__logout" onClick={() => logout()}>
        Выйти
      </button>
    </aside>
  );
}
