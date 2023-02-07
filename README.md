# yii-ftpassetmanager

EFtpAssetManager extends CAssetManager to allow the use of PHP's wrappers 
ftp:// o http:// to store the assets. This is useful in a webfarm scenario
where the webserver is the frontend to a number of PHP FastCGI servers which
in other way would need to store the assets in a central server shared with
NFS or some other shared filesystem, or in every server.

## Installation

Unpack or clone the extension to your extensions directory.

## Usage

In the **'components'** section of your *main.php*:

```
'assetManager' => [
    'class' => 'EFtpAssetManager',
    'lockAssets' => true,
    'lockPath' => '/var/assets',
    'host' => 'ftp.example.com',
    'path' => '/assets/',

]
```

## License

Copyright © 2008 Rodolfo González González.

[![License](https://img.shields.io/badge/License-BSD_3--Clause-blue.svg)](https://opensource.org/licenses/BSD-3-Clause)