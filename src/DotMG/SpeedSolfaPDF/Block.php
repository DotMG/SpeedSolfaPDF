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
      for ($k=0; $k<100; $k++) { $note[] = 'T' ; }
      $formatted = vsprintf($format, $note);
      $markVoix  = $this->getMark($i);
      if (in_array('(', $markVoix)) {
        if (substr($formatted, 0, 1) != '(') {
          $formatted = "($formatted";
        }
        $this->unMark($i, '(');
      }
      if (in_array(')', $markVoix)) {
        if (!preg_match("/\\)$/", $formatted)) {
          $formatted = "$formatted)";
        }
        $this->unMark($i, ')');
      }
      $formatted = str_replace('-.-', '-', $formatted);
      $formatted = str_replace(
        array('D', 'R', 'F', 'S', 'T'),
        array('di', 'ri', 'fi', 'si', 'ta'),
        $formatted
      );
      $formatted = str_replace('.-)', ')', $formatted);
      $formatted = preg_replace('/\((.i*\'*,*)\)/', '\1', $formatted);
      $formatted = preg_replace('/\.,-$/', '', $formatted);
      $formatted = str_replace(',,', '₂', $formatted);
      $formatted = preg_replace('/(?<=[drmfsltia]),/', '₁', $formatted);
      if (preg_match('/^\((.*)\)$/', $formatted, $match)) {
        $formatted = $match[1];
        $underlined[$i] = array(array('(', ')'));
      }
      if (preg_match('/[\(\)]/', $formatted, $match)) {
        $underlined[$i] = array($match);
        $formatted = preg_replace('/[\(\)]/', '', $formatted);
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
}
