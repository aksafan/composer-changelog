<p align="center">
    <a href="https://getcomposer.org/" target="_blank" rel="external">
        <img src="https://getcomposer.org/img/logo-composer-transparent.png" height="178px">
    </a>
    <h1 align="center">Yii 2 Composer Installer</h1>
    <br>
</p>

This is the composer plugin to show changelog after updating packages through composer.

# Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

To install, either run

```
$ php composer.phar require aksafan/composer-changelog
```

or add

```
"aksafan/composer-changelog": "*"
```

to the `require` section of your `composer.json` file.


Usage
-----

To use this extension you need to create `UPGRADE.md` file in the root directory of your project with text like the following for every new release you want to show messages about:

```md
Upgrade from aksafan/composer-changelog 0.0.1
-----------------------

* Initial comments
* Another comments
```

There are 3 main parts of every release changelog message (separated with one empty space):
1) the `Upgrade from `;
2) package name `aksafan/composer-changelog`;
3) version `0.0.1` (according to [semver](https://semver.org/))

And under this main message you can wrote down all necessary upgrade notes.

Completed example can be found in the root directory of this package in `UPGRADE.md` file.


License
-------

Copyright 2020 by Anton Khainak.

Available under the MIT license.

Inspired by [yii2-composer](https://github.com/yiisoft/yii2-composer) plugin.