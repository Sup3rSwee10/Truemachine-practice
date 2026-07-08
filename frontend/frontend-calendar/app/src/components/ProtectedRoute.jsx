//Раскоментить при рабочем api

// import { Navigate } from 'react-router-dom';
// import { useAuth } from '../context/AuthContext';

// export default function ProtectedRoute({ children }) {
//   const { isAuthenticated, loading } = useAuth();

//   if (loading) {
//     return <div className="page-loading">Загрузка…</div>;
//   }
//   if (!isAuthenticated) {
//     return <Navigate to="/login" replace />;
//   }
//   return children;
// }



// Проверка верстки без api

import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function ProtectedRoute({ children }) {
  // TODO: убрать перед реальным использованием!
  return children;

  // -- реальная проверка авторизации, временно отключена --
  // const { isAuthenticated, loading } = useAuth();
  // if (loading) return <div className="page-loading">Загрузка…</div>;
  // if (!isAuthenticated) return <Navigate to="/login" replace />;
  // return children;
}