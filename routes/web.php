<?php

/**
 * ВНИМАНИЕ: Этот файл больше не используется!
 * 
 * Роуты теперь загружаются через RouteServiceProvider в следующем порядке:
 * 1. routes/web_core.php - системные маршруты
 * 2. routes/plugins.php - маршруты плагинов (если существует)
 * 3. routes/web_content.php - контентные маршруты
 * 4. FallbackController - обработчик 404
 * 
 * Этот файл оставлен для обратной совместимости, но не загружается автоматически.
 * Все роуты перенесены в routes/web_core.php.
 * 
 * См. app/Providers/RouteServiceProvider.php для деталей.
 */
