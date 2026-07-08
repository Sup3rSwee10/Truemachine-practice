import { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const { roles } = await login(email, password);
      // По сценарию 1.1: «Заявки» для Инициатора, «Календарь» для остальных ролей
      const isOnlyInitiator = roles.length > 0 && roles.every((r) => r === 'initiator');
      const defaultRoute = isOnlyInitiator ? '/requests' : '/calendar';
      const redirectTo = location.state?.from?.pathname || defaultRoute;
      navigate(redirectTo, { replace: true });
    } catch (err) {
      if (err.response?.status === 429) {
        setError('Слишком много попыток входа. Попробуйте позже.');
      } else {
        setError('Неверный email или пароль');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="login-screen">
      <form className="login-card" onSubmit={handleSubmit}>
        <h1 className="login-card__title">Авторизация</h1>

        <div className="login-card__row">
          <label htmlFor="email">Логин:</label>
          <input
            id="email"
            type="email"
            placeholder="admin@test.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>

        <div className="login-card__row">
          <label htmlFor="password">Пароль:</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>

        {error && <div className="login-card__error">{error}</div>}

        <button type="submit" className="btn btn--primary" disabled={submitting}>
          {submitting ? 'Вход…' : 'Войти'}
        </button>
      </form>
    </div>
  );
}
