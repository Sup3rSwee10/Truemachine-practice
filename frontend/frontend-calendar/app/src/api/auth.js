import { apiClient, setToken } from './client';

/**
 * POST /login { email, password } -> { message, user, roles, token }
 */
export async function login(email, password) {
  const { data } = await apiClient.post('/login', { email, password });
  setToken(data.token);
  return { user: data.user, roles: data.roles };
}

/**
 * POST /logout — инвалидирует токен на сервере.
 */
export async function logout() {
  try {
    await apiClient.post('/logout');
  } finally {
    setToken(null);
  }
}

/**
 * GET /me -> { user, roles }
 */
export async function fetchCurrentUser() {
  const { data } = await apiClient.get('/me');
  return { user: data.user, roles: data.roles };
}
