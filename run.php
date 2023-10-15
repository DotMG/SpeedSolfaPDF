<?php
include("vendor/autoload.php");

use DotMG\SpeedSolfaPDF\Solfa;

$shortOpts = ""
  . "s" // smartWidth
  ;
$longOpts = array(
  "smart-width",
  "transposeto::",
  "transposeasif::"
);
$restIndex = null;
$options = getopt($shortOpts, $longOpts, $restIndex);
$fileName = array_slice($argv, $restIndex);
if (is_array($fileName) && !empty($fileName[0])) {
  $solfa = new Solfa($fileName[0], $options);
} else {
  $solfa = new Solfa('', $options);
}
$solfa->renderPDF();
