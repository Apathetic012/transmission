transmission
============

a PHP lib for the Transmission RPC Interface.

Usage
-----

```php
require __DIR__.'/vendor/autoload.php';

use Transmission\Transmission;

$config = array(
  'host' => '127.0.0.1',
  'port' => 9091,
  'endpoint' => '/transmission/rpc',
  'debug' => true,
  'fields' => array('totalSize') // default fields to get
);
$transmission = new Transmission($config);
```

## Methods

- add
- get

## Laravel

- Add `Transmission\TransmissionServiceProvider` to your service providers.
- Add an alias to `Transmission`

```php
// config/app.php

'providers' => array(
  'Transmission\TransmissionServiceProvider'
),

'alias' => array(
  'Transmission' => 'Transmission\Facades\Transmission'
)
```

You can then call methods like:

```php

$add = Transmission::add($torrentUrl);
var_dump($add);
// object(stdClass)[210]
//   public 'hashString' => string 'e630b2c2bb1763bf59917164989cea88ab3b85e3' (length=40)
//   public 'id' => int 29
//   public 'name' => string 'Imagine+Dragons+-+Radioactive+[Single]+[mp3@320]' (length=48)
```

TODO

- other methods
