import { makeAutoObservable } from 'mobx';

/**
 * Хранилище счётчика для демонстрации возможностей MobX.
 */
export class ExampleStore {
  /**
   * Текущее значение счётчика.
   */
  count = 0;

  constructor() {
    makeAutoObservable(this);
  }

  /**
   * Увеличивает счётчик на единицу.
   */
  increment() {
    this.count++;
  }

  /**
   * Уменьшает счётчик на единицу.
   */
  decrement() {
    this.count--;
  }

  /**
   * Сбрасывает счётчик в ноль.
   */
  reset() {
    this.count = 0;
  }
}

/**
 * Единый экземпляр хранилища для использования в приложении.
 */
export const exampleStore = new ExampleStore();
