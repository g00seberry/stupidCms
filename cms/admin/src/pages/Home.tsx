import { observer } from 'mobx-react-lite';
import { exampleStore } from '@/stores/exampleStore';

/**
 * Главная страница панели управления с демонстрацией MobX-состояния.
 */
export const Home = observer(() => {
  return (
    <div>
      <h2 className="text-3xl font-bold mb-4">Welcome to CMS Admin</h2>
      <p className="text-gray-600 mb-6">This is the admin panel for the CMS system.</p>

      <div className="bg-white p-6 rounded-lg shadow">
        <h3 className="text-xl font-semibold mb-4">MobX Example</h3>
        <div className="flex items-center gap-4">
          <button
            onClick={() => exampleStore.decrement()}
            className="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600"
          >
            -
          </button>
          <span className="text-2xl font-bold">{exampleStore.count}</span>
          <button
            onClick={() => exampleStore.increment()}
            className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
          >
            +
          </button>
          <button
            onClick={() => exampleStore.reset()}
            className="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            Reset
          </button>
        </div>
      </div>
    </div>
  );
});

export default Home;
