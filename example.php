<?php
  
require_once 'src/SunnyPortal.php';
  

$username = ''; // Set your Login Username
$password = ''; // Set your Login Password

$plantOID = ''; // Set your Plant OID

$portal = new \GunnaPHP\SMA\SunnyPortal([
  'username' => $username,
  'password' => $password
]);


if ( empty($plantOID) ) {
/**
 * Get a List of all Plants
 */ 
  $plants = $portal->getPlantList();

  foreach ($plants AS $plantOID=>$plantName) {

    echo $plantName.': '.$plantOID.PHP_EOL;

  }

  exit();

}
 
$portal->setPlant($plantOID);

while (true)
{
  $data = $portal->liveData();
  echo 'PV Generation: '.$data->PV.'kW'.PHP_EOL;
  echo 'Grid Consumption: '.$data->GridConsumption.'kW'.PHP_EOL;
  sleep(15);
}
