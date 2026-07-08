import { createContext, useContext, useEffect, useState } from 'react';
import { login as loginRequest, logout as logoutRequest, fetchCurrentUser } from '../api/auth';
import { getToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = getToken();
    if (!token) {
      setLoading(false);
      return;
    }
    fetchCurrentUser()
      .then(({ user, roles }) => {
        setUser(user);
        setRoles(roles || []);
      })
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  async function login(email, password) {
    const { user, roles } = await loginRequest(email, password);
    setUser(user);
    setRoles(roles || []);
    return { user, roles: roles || [] };
  }

  async function logout() {
    await logoutRequest();
    setUser(null);
    setRoles([]);
  }

  function hasRole(role) {
    return roles.includes(role);
  }

  return (
    <AuthContext.Provider
      value={{ user, roles, loading, login, logout, hasRole, isAuthenticated: !!user }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth должен использоваться внутри AuthProvider');
  return ctx;
}
