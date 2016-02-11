Установка
=========

## Получение пакета Composer

Предпочтительный способ установки этого расширения через [composer](http://getcomposer.org/download/).

Выполнить команды:

```
php composer.phar config repositories.0 '{"type": "git","url": "git@gitlab.bnmedia.ru:develop/yii2-file-daemon.git"}'
php composer.phar require --prefer-dist phantom-d/yii2-file-daemon "dev-master"
```

или добавить в секцию **require**:

```
"phantom-d/yii2-file-daemon": "dev-master"
```

и в секцию **repositories**:

```
{
    "type": "git",
    "url": "git@gitlab.bnmedia.ru:develop/yii2-file-daemon.git"
}
```

 в ваш файл `composer.json`

## Настройка приложения

