<?php
/*
 * This file can be called using a cron to create dzi image  files automatically
 */

include dirname(__FILE__).'/../../config/config.inc.php';

require ('vendor/autoload.php');

$openseadragon = Module::getInstanceByName('openseadragon');

$openseadragon->cron();
