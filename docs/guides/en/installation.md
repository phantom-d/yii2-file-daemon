Installation
============

## Get Composer package

The preferred way to install this extension is through  [composer](http://getcomposer.org/download/).

Either run:

```
php composer.phar config repositories.bnmedia-filedaemon '{"type": "git","url": "git@gitlab.bnmedia.ru:develop/yii2-file-daemon.git"}'
php composer.phar require --prefer-dist phantom-d/yii2-file-daemon "dev-master"
```

or add to **require**:

```json
"phantom-d/yii2-file-daemon": "dev-master"
```

and to **repositories**:

```json
{
    "type": "git",
    "url": "git@gitlab.bnmedia.ru:develop/yii2-file-daemon.git"
}
```

to the require section of your `composer.json` file
