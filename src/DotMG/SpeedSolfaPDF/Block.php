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
  function get_lyrics_height()
  {
    return sizeof($this->lyrics);
  }
  function get_note_height()
  {
    return sizeof($this->note);
  }
  function get_nb_note()
  {
    return $this->nb_note;
  }
  function get_nb_lyrics()
  {
    return $this->nb_lyrics;
  }
  function set_note($sub)
  {
    if (null == $sub) {
      return;
    }
    $this->note  = $sub;
    $this->note_as_multistring();
  }
  function set_mark($note_mark)
  {
    if (array() == $note_mark) {
      return;
    }
    $this->note_mark = $note_mark;
  }
  function get_mark($i)
  {
    if (!is_array($this->note_mark) || !isset($this->note_mark[$i]) || !is_array($this->note_mark[$i])) return array();
    return array_merge(...$this->note_mark[$i]);
  }
  function set_lyrics($sub)
  {
    if (null == $sub) {
      return;
    }
    $this->lyrics  = $sub;
    $this->lyrics_as_multistring();
  }
  function note_as_multistring()
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
    $this->set_mark($_underlined);
    $this->note_string = rtrim($_return);
  }
  function lyrics_as_multistring()
  {
    if (is_array($this->lyrics))
      $this->lyrics_string = implode("\n", $this->lyrics);
    $this->calc_min_width();
  }
  function calc_min_width()
  {
    $_pdf = new PDF($this->meta);
    list($this->note_width, $_lyrics_width, $_min_width) = $_pdf->calc_width($this->note_string, $this->lyrics_string);
    $this->width = $_min_width;
    Block::$max_width = max($_min_width, Block::$max_width);
    return $_min_width;
  }
}
