Установка
=========

## Получение пакета Composer

Предпочтительный способ установки этого расширения через [composer](http://getcomposer.org/download/).

Выполнить команды:

```
php composer.phar config repositories.bnmedia-filedaemon '{"type": "git","url": "git@gitlab.bnmedia.ru:develop/yii2-file-daemon.git"}'
php composer.phar require --prefer-dist phantom-d/yii2-file-daemon "dev-master"
```
или добавить в секцию **require**:

```json
"phantom-d/yii2-file-daemon": "dev-master"
```
и в секцию **repositories**:

```json
{
    "type": "git",
    "url": "git@gitlab.bnmedia.ru:develop/yii2-file-daemon.git"
}
```
 в ваш файл `composer.json`

## Настройка приложения

1. Для первоначальной настройки рекомендуется скопировать базовый файл настройки демонов.
Базовый файл конфигурации располагается в директории `@vendor/phantom-d/yii2-file-daemon/config/daemons.php`

    Создать директорию:
    * Шаблон Yii2-base - `@app/config/daemons`
    * Шаблон Yii2-advanced - `@app/common/config/daemons`

    В созданную директорию скопировать базовый файл конфигурации.

    В базовом файле конфигурации указаны настройки демона контролирующего запуск и остановку остальных демонов.
2. Создаём файл контроллера демона с именем `FileServerDaemonController.php`, который буде выполнять роль демона обработки файлов:
 * Шаблон Yii2-base - `@app/commands`

    ```php
    <?php

    namespace app\commands;

    use phantomd\filedaemon\commands\controllers\FileDaemonController;

    /**
     * Class FileServerDaemonController.
     */
    class FileServerDaemonController extends FileDaemonController
    {

    }

    ```

 * Шаблон Yii2-advanced - `@app/console/controllers`

    ```php
    <?php

    namespace console\controllers;

    use phantomd\filedaemon\commands\controllers\FileDaemonController;

    /**
     * Class FileServerDaemonController.
     */
    class FileServerDaemonController extends FileDaemonController
    {

    }

    ```

3. Создаём файл контроллера наблюдателя с именем `WatcherDaemonController.php`, который буде выполнять роль демона обработки файлов:
 * Шаблон Yii2-base - `@app/commands`

    ```php
    <?php

    namespace app\commands;

    use phantomd\filedaemon\commands\controllers;

    /**
     * Class WatcherDaemonController.
     */
    class WatcherDaemonController extends controllers\WatcherDaemonController
    {

    }

    ```

 * Шаблон Yii2-advanced - `@app/console/controllers`

    ```php
    <?php

    namespace console\controllers;

    use phantomd\filedaemon\commands\controllers;

    /**
     * Class WatcherDaemonController.
     */
    class WatcherDaemonController extends controllers\WatcherDaemonController
    {

    }

    ```

4. Создаём файл REST контроллера с именем `DaemonController.php`, с помощью которого будете добавлять данные для постановки задач на обработку
 * Шаблон Yii2-base - `@app/controllers`

    ```php
    <?php

    namespace app\controllers;

    /**
     * Class DaemonController. Frontend REST controller.
     */
    class DaemonController extends \phantomd\filedaemon\frontend\controllers\DaemonController
    {

        /**
         * @var string Daemon name in configuration
         */
        protected static $configAlias = 'file-server';

    }

    ```

 * Шаблон Yii2-advanced - `@app/frontend/controllers`

    ```php
    <?php

    namespace frontend\controllers;

    /**
     * Class DaemonController. Frontend REST controller.
     */
    class DaemonController extends \phantomd\filedaemon\frontend\controllers\DaemonController
    {

        /**
         * @var string Daemon name in configuration
         */
        protected static $configAlias = 'file-server';

    }

    ```

5. Для непрерывной работы наблюдателя добавьте эту строчку в crontab:
    ```
    5 * * * * /{PATH/TO/YII/PROJECT}/yii watcher-daemon --demonize=1
    ```
    Наблюдатель не может стартовать дважды, только один процесс может работать.
 
