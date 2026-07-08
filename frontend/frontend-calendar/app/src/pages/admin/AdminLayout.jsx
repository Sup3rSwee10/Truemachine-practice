import { NavLink, Outlet } from 'react-router-dom';

const tabs = [
  { to: '/admin/users', label: 'Пользователи' },
  { to: '/admin/counterparties', label: 'Контрагенты' },
  { to: '/admin/accounts', label: 'Счета' },
  { to: '/admin/articles', label: 'Статьи' },
];

export default function AdminLayout() {
  return (
    <div>
      <div className="admin-tabs">
        {tabs.map((tab) => (
          <NavLink
            key={tab.to}
            to={tab.to}
            className={({ isActive }) => 'admin-tabs__item' + (isActive ? ' admin-tabs__item--active' : '')}
          >
            {tab.label}
          </NavLink>
        ))}
      </div>
      <Outlet />
    </div>
  );
}
