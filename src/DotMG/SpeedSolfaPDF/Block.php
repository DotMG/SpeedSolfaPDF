<?php
namespace DotMG\SpeedSolfaPDF;

class Block
{
  var $separator = '';
  var $width = '';
  var $template = '';
  private $nb_note = '';
  private $nb_lyrics = '';
  var $marker = null;
  private $note;
  private $lyrics;
  private $note_mark;
  public $note_width = 0;
  public static $max_width;
  function __construct($template_note, $separator, $marker, $meta)
  {
    $this->template = str_replace(array('{', '}'), '', $template_note);
    $this->separator = $separator;
    $this->nb_note = strlen(preg_replace('/[^DRMFSLT]/', '', $template_note));
    $_template_syllabus = preg_replace('/\([^\)]*\)/', 'D', $template_note);
    $this->nb_lyrics = strlen(preg_replace('/[^DRMFSLT]/', '', $_template_syllabus));
    $this->marker = $marker;
    $this->meta = $meta;
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
    return $this->nb_note;
  }
  function getNbLyrics()
  {
    return $this->nb_lyrics;
  }
  function setNote($sub)
  {
    if (null == $sub) {
      return;
    }
    $this->note  = $sub;
    $this->noteAsMultistring();
  }
  function setMark($note_mark)
  {
    if (array() == $note_mark) {
      return;
    }
    $this->note_mark = $note_mark;
  }
  function getMark($i)
  {
    if (!is_array($this->note_mark) || !isset($this->note_mark[$i]) || !is_array($this->note_mark[$i])) return array();
    return array_merge(...$this->note_mark[$i]);
  }
  function setLyrics($sub)
  {
    if (null == $sub) {
      return;
    }
    $this->lyrics  = $sub;
    $this->lyricsAsMultistring();
  }
  function noteAsMultistring()
  {
    $_format = preg_replace('/[DRMFSLT]/', '%s', $this->template);
    $_return = '';
    $_underlined = array();
    foreach ($this->note as $i => $note) {
      for ($i=0; $i<100; $i++) { $note[] = 'T' ; }
      $_formatted = vsprintf($_format, $note);
      $_formatted = str_replace('-.-', '-', $_formatted);
      $_formatted = str_replace(
        array('D', 'R', 'F', 'S', 'T'),
        array('di', 'ri', 'fi', 'si', 'ta'),
        $_formatted
      );
      $_formatted = str_replace('.-)', ')', $_formatted);
      $_formatted = preg_replace('/\((.i*,*)\)/', '\1', $_formatted);
      $_formatted = preg_replace('/\.,-$/', '', $_formatted);
      $_formatted = preg_replace('/\.-$/', '', $_formatted);
      $_formatted = str_replace(',,', '₂', $_formatted);
      $_formatted = preg_replace('/(?<=[drmfsltia]),/', '₁', $_formatted);
      if (preg_match('/^\((.*)\)$/', $_formatted, $_match)) {
        $_formatted = $_match[1];
        $_underlined[$i] = array(array('(', ')'));
      }
      if (preg_match('/[\(\)]/', $_formatted, $match)) {
	$_underlined[$i] = array($match);
	$_formatted = preg_replace('/[\(\)]/', '', $_formatted);
        //print_r($_formatted);
      }
      $_return .= $_formatted . "\n";
    }
    $this->setMark($_underlined);
    $this->note_string = rtrim($_return);
  }
  function lyricsAsMultistring()
  {
    if (is_array($this->lyrics))
      $this->lyrics_string = implode("\n", $this->lyrics);
    $this->calcMinWidth();
  }
  function calcMinWidth()
  {
    $_pdf = new PDF($this->meta);
    list($this->note_width, $_lyrics_width, $_min_width) = $_pdf->calcWidth($this->note_string, $this->lyrics_string);
    $this->width = $_min_width;
    Block::$max_width = max($_min_width, Block::$max_width);
    return $_min_width;
  }
}
