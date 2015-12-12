# SMA Sunny Portal Extractor

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

This is a php class for collecting data from the SMA Home Energy Monitor via 
the SMA Sunny Portal Web Interface.

## Install

### Via Composer

``` bash
$ composer require GunnaPHP/SMA
```
### Manually
 Download the Zip file and extract
 
``` PHP
require_once '[PATH/TO]/src/SunnyPortal.php';
```
## Usage

### Create Instance
``` PHP
use GunnaPHP\SMA\SunnyPortal;

$portal  = new SunnyPortal([
	'username' => '{Portal Login Email}',
	'password' => '{Portal Login Password}'
]);
```
### Get a List of Plants
``` PHP
$plants = $portal->getPlantList();
foreach ($plants AS $plantOID=>$plantName) {
    echo $plantName.': '.$plantOID.PHP_EOL;
}
```
### Select Plant
Select the plant you want to collect the live data from
``` PHP
$poral->setPlant('{SMA Plant OID}');
```

### Collect Data
``` PHP
while ( true ) {
  $data = $portal->liveData();
  echo 'PV Generation: '.$data->PV.'kW'.PHP_EOL;
  echo 'Grid Consumption: '.$data->GridConsumption.'kW'.PHP_EOL;
  sleep(60); // Note: Minimum Interval between data reads is 15 secs
}
```

### Example output 
``` PHP
stdClass Object
(
    [__type] => LiveDataUI
    [Timestamp] => stdClass Object
        (
            [__type] => DateTime
            [DateTime] => 2015-12-12T08:15:10
            [Kind] => Unspecified
        )
    [PV] => 
    [FeedIn] => 0
    [GridConsumption] => 0
    [DirectConsumption] => 
    [SelfConsumption] => 
    [SelfSupply] => 
    [TotalConsumption] => 0
    [DirectConsumptionQuote] => 
    [SelfConsumptionQuote] => 
    [AutarkyQuote] => 
    [BatteryIn] => 
    [BatteryOut] => 
    [BatteryChargeStatus] => 
    [OperationHealth] => 
    [BatteryStateOfHealth] => 
    [InfoMessages] => Array()
    [WarningMessages] => Array()
    [ErrorMessages] => Array()
    [Info] => stdClass Object()
)

```

## Credits

- [Steven Miles][https://www.srmiles.com]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/GunnaPHP/SMA.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/GunnaPHP/SMA.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/GunnaPHP/SMA
[link-downloads]: https://packagist.org/packages/GunnaPHP/SMA
[link-author]: https://github.com/srmiles
