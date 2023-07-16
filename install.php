<?php
echo PHP_EOL."SpeedSolfaPDF INSTALLATION".PHP_EOL;
echo PHP_EOL."To run this installation script, you must be connected to the internet".PHP_EOL;

copy('https://getcomposer.org/installer', 'composer-setup.php');
if (hash_file('sha384', 'composer-setup.php') === 'e21205b207c3ff031906575712edab6f13eb0b361f2085f1f1237b7126d785e826a450292b6cfd1d64d92e6563bbde02') {
  echo 'Installer verified'; 
} else { 
  echo 'Installer corrupt'; 
  unlink('composer-setup.php'); 
  exit();
} 

$targetDirectory = dirname(__FILE__).DIRECTORY_SEPARATOR."bin";
@mkdir($targetDirectory);
echo PHP_EOL."Installation begins".PHP_EOL;

echo PHP_EOL."Now we are about to install and run composer.".PHP_EOL;
$argv[] = '--install-dir='.$targetDirectory;
register_shutdown_function('composer_update', $targetDirectory);

include("composer-setup.php");
unlink('composer-setup.php');

function composer_update($targetDirectory) {
  chdir(dirname(__FILE__));
  exec("php $targetDirectory".DIRECTORY_SEPARATOR."composer.phar update");
}
