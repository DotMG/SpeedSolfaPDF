<?php
include("vendor/autoload.php");

use DotMG\SpeedSolfaPDF\Solfa;

if (is_array($argv) && !empty($argv[1])) {
  $solfa = new Solfa($argv[1]);
} else {
  $solfa = new Solfa();
}
$solfa->renderPDF();

