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
Upgrade from ComposerChangelog 1.0.2
-----------------------

* Initial comments for upgrading from v1.0.2
* Initial comments for upgrading from v1.0.2

Upgrade from ComposerChangelog 1.0.1
-----------------------

* Initial comments for upgrading from v1.0.1
* Initial comments for upgrading from v1.0.1

Upgrade from ComposerChangelog 1.0.0
-----------------------

* Initial comments for upgrading from v1.0.0
* Initial comments for upgrading from v1.0.0

```

There are 3 main parts of every release changelog message (separated with one empty space):
1) the `Upgrade from `;
2) package name with one word `ComposerChangelog`;
3) version `1.0.0` (according to [semver](https://semver.org/)) from which you are updating.

And under this main message you can wrote down all necessary upgrade notes.

> Note: The following upgrading instructions must be cumulative. In other words,
if you want to upgrade from version A to version C and there is
version B between A and C, you need to follow the instructions
for both A and B.

Now, after updating package user will get in console message like:

```bash
composer-changelog$ composer update aksafan/composer-changelog
./composer.json has been updated
Loading composer repositories with package information
Updating dependencies (including require-dev)
Package operations: 0 installs, 1 update, 0 removals
  - Updating aksafan/composer-changelog (1.0.0 => 1.0.3): Loading from cache
Writing lock file
Generating autoload files
1 package you are using is looking for funding.
Use the `composer fund` command to find out more!

  Seems you have upgraded aksafan/composer-changelog from version 1.0.0 to 1.0.3.

  Please check the upgrade notes for possible incompatible changes and adjust your application code accordingly.

  Upgrade from ComposerChangelog 1.0.2
 -----------------------
 
 * Initial comments for upgrading from v1.0.2
 * Initial comments for upgrading from v1.0.2
 
 Upgrade from ComposerChangelog 1.0.1
 -----------------------
 
 * Initial comments for upgrading from v1.0.1
 * Initial comments for upgrading from v1.0.1
 
 Upgrade from ComposerChangelog 1.0.0
 -----------------------
 
 * Initial comments for upgrading from v1.0.0
 * Initial comments for upgrading from v1.0.0

  You can find the upgrade notes for all versions online at:
https://github.com/aksafan/composer-changelog.git

```

Completed example can be found in the root directory of this package in `UPGRADE.md` file.


License
-------

Copyright 2020 by Anton Khainak.

Available under the MIT license.

Inspired by [yii2-composer](https://github.com/yiisoft/yii2-composer) plugin.