<?php namespace Transmission\Facades;

use Illuminate\Support\Facades\Facade;

class Transmission extends Facade {

  protected static function getFacadeAccessor() { return 'transmission'; }
}
