import { Routes, Route } from 'react-router-dom';
import { Layout } from '@/components/Layout';
import { Home } from '@/pages/Home';

/**
 * Корневой компонент admin-приложения, отвечающий за маршрутизацию и базовый Layout.
 */
export function App() {
  return (
    <Layout>
      <Routes>
        <Route path="/" element={<Home />} />
      </Routes>
    </Layout>
  );
}

export default App;
