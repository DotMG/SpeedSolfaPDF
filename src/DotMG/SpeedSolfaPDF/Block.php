<?php
namespace DotMG\SpeedSolfaPDF;

class Block
{
  var $separator = '';
  var $width = '';
  var $template = '';
  private $nbNote = '';
  private $nbLyrics = '';
  var $marker = null;
  private $note;
  private $lyrics;
  private $noteMark;
  public $noteWidth = 0;
  private $numBlock;
  public static $maxWidth;
  public static $nbBlock = 0;
  public static $noteToKey = array(
'd,,'	=>	0,
'di,,'	=>	1, 
'r,,'	=>	2,
'ri,,'	=>	3,
'm,,'	=>	4,
'f,,'	=>	5,
'fi,,'	=>	6,
's,,'	=>	7,
'si,,'	=>	8,
'l,,'	=>	9,
'ta,,'	=>	10,
't,,'	=>	11,
'd,'	=>	12,
'di,'	=>	13,
'r,'	=>	14,
'ri,'	=>	15,
'm,'	=>	16,
'f,'	=>	17,
'fi,'	=>	18,
's,'	=>	19,
'si,'	=>	20,
'l,'	=>	21,
'ta,'	=>	22,
't,'	=>	23,
'd'	=>	24,
'di'	=>	25,
'r'	=>	26,
'ri'	=>	27,
'm'	=>	28,
'f'	=>	29,
'fi'	=>	30,
's'	=>	31,
'si'	=>	32,
'l'	=>	33,
'ta'	=>	34,
't'	=>	35,
"d'"	=>	36,
"di'"	=>	37,
"r'"	=>	38,
"ri'"	=>	39,
"m'"	=>	40,
"f'"	=>	41,
"fi'"	=>	42,
"s'"	=>	43,
"si'"	=>	44,
"l'"	=>	45,
"ta'"	=>	46,
"t'"	=>	47,
"d''"	=>	48,
"di''"	=>	49,
"r''"	=>	50,
"ri''"	=>	51,
"m''"	=>	52,
"f''"	=>	53,
"fi''"	=>	54,
"s''"	=>	55,
"si''"	=>	56,
"l''"	=>	57,
"ta''"	=>	58,
"t''"	=>	59,
'R,,'	=>	3,
 'F,,'   =>      6,
 'D,,'	=>	1,
 'S,,'	=>	8,
 'T,,'	=>	10,
 'D,'	=>	13,
 'R,'	=>	15,
 "R'"	=>	39,
 "D'"	=>	37,
 'T'	=>	34,
 'S'	=>	32,
 'F'	=>	30,
 'R'	=>	27,
 'D'	=>	25,
 'T,'	=>	22,
 'S,'	=>	20,
 'F,'	=>	18,
 "F'"	=>	42,
 "S'"	=>	44,
 "T'"	=>	46,
 "D''"	=>	49,
 "R''"	=>	51,
 "F''"	=>	54,
 "S''"	=>	56,
 "T''"	=>	58,

);
  public static $keyToNote;
  function __construct($templateNote, $separator, $marker, $meta)
  {
    $this->template = str_replace(array('{', '}'), '', $templateNote);
    $this->separator = $separator;
    $this->nbNote = strlen(preg_replace('/[^DRMFSLT]/', '', $templateNote));
    $templateSyllable = preg_replace('/\([^\)]*\)/', 'D', $templateNote);
    $this->nbLyrics = strlen(preg_replace('/[^DRMFSLT]/', '', $templateSyllable));
    $this->marker = $marker;
    $this->meta = $meta;
    $this->numBlock = Block::$nbBlock;
    Block::$nbBlock++;
    Block::$keyToNote = array_keys(Block::$noteToKey);
  }
  function getNum() {
    return $this->numBlock;
  }
  function getLyricsHeight()
  {
    return sizeof($this->lyrics);
  }
  function getNoteHeight()
  {
    return sizeof($this->note);
  }
  function getNbNote()
  {
    return $this->nbNote;
  }
  function getNbLyrics()
  {
    return $this->nbLyrics;
  }
  function setNote($sub)
  {
    if (null == $sub) {
      return;
    }
    $this->note  = $sub;
    $this->noteAsMultistring();
  }
  function setMark($noteMark)
  {
    if (array() == $noteMark) {
      return;
    }
    $this->noteMark = $noteMark;
  }
  function getMark($i)
  {
    if (!is_array($this->noteMark) || !isset($this->noteMark[$i]) || !is_array($this->noteMark[$i])) return array();
    return array_merge(...$this->noteMark[$i]);
  }
  function setLyrics($sub)
  {
    if (null == $sub) {
      return;
    }
    $this->lyrics  = $sub;
    $this->lyricsAsMultistring();
  }
  function unMark($i, $mark) {
    foreach ($this->noteMark[$i] as $index => $arrayMark) {
      if (is_array($arrayMark)) {
        foreach ($arrayMark as $numMark => $valMark) {
          if ($mark == $valMark) {
            unset ($this->noteMark[$i][$index][$numMark]);
          }
        }
      }
    }
  }
  function noteAsMultistring()
  {
    $format = preg_replace('/[DRMFSLT]/', '%s', $this->template);
    $return = '';
    $underlined = array();
    foreach ($this->note as $i => $note) {
      $closingParen = '';
      for ($k=0; $k<100; $k++) { $note[] = 't' ; }
      $transpose_decalage = 0;
      if (isset($this->meta['transposeValue']) && is_int($this->meta['transposeValue']) && $this->meta['transposeValue'] != 0) {
        foreach($note as $k => $v) {
          if (isset(Block::$noteToKey[$v])) {
            $keyIndexOrigin = Block::$noteToKey[$v];
            $note[$k] = Block::$keyToNote[$keyIndexOrigin - $this->meta['transposeValue']];
          }
        }
      }
      $formatted = vsprintf($format, $note);
      $markVoix  = $this->getMark($i);
      if (in_array('(', $markVoix)) {
        if (substr($formatted, 0, 1) != '(') {
          $formatted = "($formatted";
        }
        $this->unMark($i, '(');
      }
      if (in_array('[', $markVoix)) {
        if (substr($formatted, 0, 1) != '[') {
          $formatted = "[$formatted";
        }
        $this->unMark($i, '[');
      }
      if (in_array(')', $markVoix)) {
        if (!preg_match("/\\)$/", $formatted)) {
          $formatted = "$formatted)";
        }
        $this->unMark($i, ')');
      }
      if (in_array(']', $markVoix)) {
        if (!preg_match("/\\]$/", $formatted)) {
          $formatted = "$formatted]";
        }
        $this->unMark($i, ']');
      }
      $formatted = str_replace('-.-', '-', $formatted);
      $formatted = str_replace('0.0', '', $formatted);
      $formatted = str_replace(
        array('D', 'R', 'F', 'S', 'T'),
        array('di', 'ri', 'fi', 'si', 'ta'),
        $formatted
      );
      $formatted = str_replace('.-)', ')', $formatted);
      $formatted = str_replace('.-]', ']', $formatted);
      $formatted = str_replace('(0', '0(', $formatted);
      $formatted = str_replace('[0', '0[', $formatted);
      $formatted = preg_replace('/\((.i*\'*,*)\)/', '\1', $formatted);
      $formatted = preg_replace('/\[(.i*\'*,*)\]/', '\1', $formatted);
      $formatted = preg_replace('/\.,-$/', '', $formatted);
      $formatted = str_replace(',,', '₂', $formatted);
      $formatted = preg_replace('/(?<=[drmfsltia]),/', '₁', $formatted);
      $formatted = preg_replace('/^0/', '', $formatted);
      $formatted = preg_replace('/^\.,0$/', '', $formatted);
      $formatted = preg_replace('/0$/', '', $formatted);
      $formatted = preg_replace('/\(?0\.?,?$/', '', $formatted);
      $formatted = preg_replace('/^\-\.,/', '.,', $formatted);
      if (preg_match('/0/', $formatted)) var_dump($formatted);
      if (preg_match('/^\((.+)\)$/', $formatted, $match)) {
        $formatted = $match[1];
        $underlined[$i] = array(array('(', ')'));
      }
      if (preg_match('/^\[(.*)\]$/', $formatted, $match)) {
        $formatted = $match[1];
        $underlined[$i] = array(array('[', ']'));
      }
      if (preg_match('/[\(\)\[\]]/', $formatted, $match)) {
        $underlined[$i] = array($match);
        $formatted = preg_replace('/[\(\)\[\]]/', '', $formatted);
        //print_r($formatted);
      }
      $return .= $formatted . "\n";
    }
    $this->noteString = rtrim($return);
    $this->setMark($underlined);
  }
  function lyricsAsMultistring()
  {
    if (is_array($this->lyrics))
      $this->lyricsString = implode("\n", $this->lyrics);
    $this->calcMinWidth();
  }
  function calcMinWidth()
  {
    $pdf = new PDF($this->meta);
    list($this->noteWidth, $lyricsWidth, $minWidth) = $pdf->calcWidth($this->noteString, $this->lyricsString);
    $this->width = $minWidth;
    Block::$maxWidth = max($minWidth, Block::$maxWidth);
    return $minWidth;
  }
  static function midiTransposed($note, $tonalite)
  {
    $noteNum = Block::$noteToKey[$note];
    $noteNum += Solfa::tonalityToNumber($tonalite) - 1;
    $mod12 =  $noteNum % 12; // 0d 1D 2r 3R 4m 5f 6F 7s 8S 9l 10T 11t
    $div12 = intval($noteNum / 12);
    $midiNote = array('C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B');
    $noteTransposed = Block::$keyToNote[$noteNum];
    $octave = 2 + $div12;
    return $midiNote[$mod12].$octave;
  }
} 
