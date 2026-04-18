import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Preloader } from './Preloader';

export default function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return <Preloader label="Preparing portal..." />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return children;
}
