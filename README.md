# yii-ftpassetmanager

[![License](https://img.shields.io/badge/License-BSD_3--Clause-blue.svg)](https://opensource.org/licenses/BSD-3-Clause)
![GitHub all releases](https://img.shields.io/github/downloads/rgglez/yii-ftpassetmanager/total) 
![GitHub issues](https://img.shields.io/github/issues/rgglez/yii-ftpassetmanager) 
![GitHub commit activity](https://img.shields.io/github/commit-activity/y/rgglez/yii-ftpassetmanager)

EFtpAssetManager extends CAssetManager to allow the use of PHP's wrappers 
ftp:// o http:// to store the assets. This is useful in a webfarm scenario
where the webserver is the frontend to a number of PHP FastCGI servers which
in other way would need to store the assets in a central server shared with
NFS or some other shared filesystem, or in every server.

## Installation

* Unpack or clone the extension to your extensions directory.
* Setup your FTP and HTTP servers in the machine which will serve the assets.

## Usage

In the **'components'** section of your *main.php*:

```php
<?php
//...
'components' => [
    // ...

    'assetManager' => [
        'class' => 'EFtpAssetManager',
        'lockAssets' => true,
        'lockPath' => '/var/assets',
        'basePath' => 'ftp://login:password@assets.example.com/',
        'baseUrl' => 'https://assets.example.com/',    
    ],
    
    // ...
],
```

## License

Copyright © 2008 Rodolfo González González.

See the LICENSE file.
