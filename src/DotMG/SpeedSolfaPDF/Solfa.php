<?php

namespace DotMG\SpeedSolfaPDF;

class Solfa
{
  var $fileData = array();
  var $meta = array();
  var $note = array();
  var $template = array();
  var $lyrics = array();
  var $i_block = 0;
  var $i_note = 0;
  var $i_lyrics = 0;
  var $marker;
  var $lyricsline = 0;
  function __construct($txt_file = 'assets/samples/solfa-60.txt')
  {
    $_fileData = file($txt_file); //@todo error handling
    array_walk($_fileData, array($this, 'parseAllLines'));
    $this->loadMeta();
    $this->loadSeparators();
    $this->loadNotes();
    $this->loadAllLyrics();
    $this->loadNoteTemplate();
    $this->setupBlocks();
    #$this->calc_size();
  } //fun __construct
  function parseAllLines($line)
  {
    $key = '';
    if (':' == substr($line, 2, 1)) {
      $_key = substr($line, 0, 1);
      $_index = intval(substr($line, 1, 1));
      $_val = substr($line, 3);
    } else {
      $_key = substr($line, 0, 3);
      $_index = intval(substr($line, 1, 2));
      $_val = substr($line, 4);
    }
    if ('' != trim($_key)) {
      $this->fileData[$_key][$_index] = rtrim($_val);
    }
  }
  function debug()
  {
    print_r($this);
  }
  function loadMeta()
  {
    $_txt_meta_line = $this->fileData['M'][0];
    $_meta_item_array = preg_split('/\|(?=[a-z]:)/', $_txt_meta_line);
    array_walk($_meta_item_array, array($this, 'getMeta'));
  }
  function getMeta($meta_item)
  {
    /* $_a_key_abbrev = array(
      'a' => 'author',
      'c' => 'tonality', 
      'h' => 'composer',
      'i' => 'interligne',
      'l' => 'lyrics font size',
      'm' => 'rythm',
      'n' => 'note font size',
      'r' => 'speed',
      't' => 'title',
    );*/
    $_key = substr($meta_item, 0, 1);
    if ('' != trim($_key) && ('M' != $_key)) {
      $this->meta[$_key] = substr($meta_item, 2);
    }
  }
  function loadSeparators()
  {
    $_separator_line = trim($this->fileData['S'][0]);
    $this->separators = str_split($_separator_line);
  }
  function loadNoteTemplate()
  {
    $_note_template = $this->fileData['T'][0];
    $is_on_paren = false;
    $_template_notes = '';
    $_note_marker    = '';
    /* Markers are :
     * $< or $> : marks starting of < or >
     * $= : marks end of < or >
     * $Q : point d'orgue
     */
    $this->marker = array();
    foreach (str_split($_note_template) as $_note_symbol) {
      if ($_note_marker != '') {
	if ($_note_symbol != '}' && substr($_note_marker, 0, 2) == '${') {
	  $_note_marker .= $_note_symbol;
	  continue;
	}
        $_note_marker .= $_note_symbol;
	if ($_note_symbol != '{') {
          $this->marker[] = $_note_marker;
          $_note_marker = '';
	}
        continue;
      }
      if (in_array($_note_symbol, $this->separators)) {
        $this->nextNote($_template_notes, $_note_symbol);
        $_template_notes = '';
        continue;
      }
      if ('$' == $_note_symbol) {
        $_note_marker = '$';
        continue;
      }
      $_template_notes .= $_note_symbol;
    }
    if ($_template_notes) {
      $this->nextNote($_template_notes);
    }
  }
  function nextNote($template_note, $separator = '')
  {
    $_new_Block = new Block($template_note, $separator, $this->marker, $this->meta);
    list($_sub_note, $_sub_mark) = $this->getSubNotes($_new_Block->getNbNote());
    $_new_Block->setNote($_sub_note);
    if ($_sub_mark) {
      $_new_Block->setMark($_sub_mark);
    }
    $_sub_lyrics = $this->getSubLyrics($_new_Block->getNbLyrics());
    $_new_Block->setLyrics($_sub_lyrics);
    $this->template[$this->i_block] = $_new_Block;
    if ('/' == $separator) {
      $this->lyricsline++;
      $this->i_lyrics = 0;
    }
    $this->i_block++;
    $this->marker = array();
  }
  function loadNotes()
  {
    foreach ($this->fileData['N'] as $_index => $notes) {
      $this->loadNoteAtIndex($notes, $_index);
    }
  }
  function loadNoteAtIndex($notes, $_index)
  {
    $notes = preg_replace_callback('/(.)(\d+)/', function ($matches) {
      return str_repeat($matches[1], $matches[2]);
    }, $notes);
    $_a_notes = str_split($notes);
    $current_note = '';
    $no_block = 0;
    foreach ($_a_notes as $une_note) {
      switch ($une_note) {
        case "'":
        case ',':
          $current_note .= $une_note;
          break;
        case '"':
          $current_note .= "''";
          break;
        case ' ':
        case "\t":
          break;
        case 'D':
        case 'R':
        case 'M':
        case 'F':
        case 'S':
        case 'L':
        case 'T':
        case 'd':
        case 'r':
        case 'm':
        case 'f':
        case 's':
        case 'l':
        case 't':
        case '|':
        case '-':
          $this->note[$_index][$no_block] = $current_note;
          $current_note = $une_note;
          if ('' == $this->note[$_index][$no_block]) {
            break;
          }
          if ('|' == $une_note) {
            $current_note = '';
          }
          $no_block++;
          if (isset($_paren_open[$_index])) {
            unset($_paren_open[$_index]);
            $this->note_marq[$_index][$no_block][] = '(';
          }
          break;
        case '(':
          $_paren_open[$_index] = true;
          break;
        case '(':
        case ')':
          $this->note_marq[$_index][$no_block][] = $une_note;
          break;
      }
    }
    if ($current_note != '') {
      $this->note[$_index][$no_block] = $current_note;
    }
  }
  function loadAllLyrics()
  {
    foreach ($this->fileData['L'] as $_index => $lyrics) {
      $this->loadOneLyrics($lyrics, $_index);
    }
  }
  function loadOneLyrics($lyrics, $_index)
  {
    $lyrics = preg_replace_callback('/_(\d)/', function ($matches) {
      return str_repeat('_', $matches[1]);
    }, $lyrics);
    $_a_lyrics = preg_split('/[\/]/', $lyrics);
    foreach ($_a_lyrics as $_i => $_l) {
      $_l = str_replace('_', '-_', $_l);
      $_l = str_replace('_-_', '__', $_l);
      $_l = str_replace(' -_', '_', $_l);
      $_l = str_replace(' _', '_', $_l);
      $this->lyrics[$_index][$_i] = preg_split('/_/', $_l);
    }
  }
  function setupBlocks()
  {
  }
  function getSubNotes($nb_note)
  {
    if (0 == $nb_note) {
      return array(array_fill(1, sizeof($this->note), array('')), array());
    }
    $_sub_note  = array();
    $_sub_mark  = array();
    foreach ($this->note as $_i_note => $_note) {
      $_sub_note[$_i_note]   = array_slice($_note, $this->i_note, $nb_note);
      $_mark = array();
      for ($i = $this->i_note; $i < $this->i_note + $nb_note; $i++) {
        if (isset($this->note_marq[$_i_note][$i])) {
          $_mark[$i] = $this->note_marq[$_i_note][$i];
        }
      }
      if (sizeof($_mark)) {
        $_sub_mark[$_i_note] = $_mark;
      }
    }
    $this->i_note += $nb_note;
    return array($_sub_note, $_sub_mark);
  }
  function getSubLyrics($nb_lyrics)
  {
    if (0 == $nb_lyrics) {
      return array_fill(1, sizeof($this->lyrics), '');
    }
    $_sub_lyrics = array();
    foreach ($this->lyrics as $_i_lyrics => $_lyrics) {
      if (!isset($_lyrics[$this->lyricsline]) || !is_array($_lyrics[$this->lyricsline])) {
	$_lyrics[$this->lyricsline] = array();
      }
      $_sub_lyrics[$_i_lyrics] = join('', array_slice($_lyrics[$this->lyricsline], $this->i_lyrics, $nb_lyrics));
    }
    $this->i_lyrics += $nb_lyrics;
    return $_sub_lyrics;
  }
  /**
   * Set Hairpin : mark the start of a crescendo or a diminuendo
   */
  function SetHairpin($x, $marker) {
    $this->hairpin = array($x, $marker);
  }
  function GetHairpin() {
    return $this->hairpin;
  }
  function renderPDF()
  {
    //@todo : 
    $pdf = new PDF($this->meta);
    $pdf->setupSize();
    $pdf->recalcWidth();
    $x = $pdf->canvas_left;
    $y = $pdf->canvas_top;
    // ecriture entete
    //title 
    $pdf->setXY($x, $y);
    $pdf->setFont('yan', '', $pdf->getFontSizeNote()+6);
    $pdf->cell($pdf->canvas_width, $pdf->font_height, $this->meta['t'], align: 'C');
    $y = $pdf->getY() + $pdf->font_height * 1.5;
    // author
    $pdf->setXY($x, $y);
    $pdf->setFont('yan', '', $pdf->getFontSizeLyrics()+2);
    $pdf->cell($pdf->canvas_width, $pdf->font_height, $this->meta['h'], align: 'R');
    $pdf->setXY($x, $y);
    $pdf->multiCell($pdf->canvas_width / 2, $pdf->font_height, $this->meta['a'], align: 'L');
    //
    $y = $pdf->getY() + $pdf->font_height;
    //tonalite + rythme
    $pdf->setFont('fir', '', $pdf->getFontSizeLyrics());
    $tonalite_rythme = 'Do dia ' . $this->meta["c"] . '       ' . $this->meta['m'];
    $pdf->setXY($x, $y);
    $pdf->cell($pdf->getStringWidth($tonalite_rythme), $pdf->font_height, $tonalite_rythme, align: 'L');
    // speed 
    $pdf->setXY($x, $y);
    $pdf->cell($pdf->canvas_width, $pdf->font_height, $this->meta['r'], ln: 0, align: 'C');

    $y = $pdf->getY() + $pdf->font_height * 2;
    if (isset($this->meta['i'])) {
      $y += ($pdf->font_height) * ($this->meta['i'] - 1);
    }

    $pdf->setXY($x, $y);
    $mark = array();
    foreach ($this->template as $_block) {
      if (is_array($_block->marker) && sizeof($_block->marker) > 0) {
	$yMarker = $y - $pdf->font_height;
        foreach ($_block->marker as $_marker) {
	  if ($_marker == '$<' || $_marker == '$>') {
	    $this->setHairpin($x, $_marker);
	  }
	  if ($_marker == '$=') {
	    list($x0, $crescendoOrDiminuendo) = $this->getHairpin();
	    $upp = $pdf->font_height / 4;
	    $baseY = $yMarker + $pdf->font_height / 2;
	    if ($crescendoOrDiminuendo == '$>') {
	      $pdf->line($x0, $baseY-$upp, $x, $baseY);
	      $pdf->line($x0, $baseY+$upp, $x, $baseY);
	    }
	    if ($crescendoOrDiminuendo == '$<') {
	      $pdf->line($x0, $baseY, $x, $baseY-$upp);
	      $pdf->line($x0, $baseY, $x, $baseY+$upp);
	    }
	  }
          if ($_marker == '$Q') {
            $pdf->setXY($x, $yMarker);
            $pdf->setFont('fir', '', $pdf->getFontSizeLyrics());
            $pdf->cell($pdf->block_width, $pdf->font_height, "Ͼ", align: 'C');
	    $yMarker -= $pdf->font_height;
          }
	  if ('${' == substr($_marker, 0, 2)) {
            $pdf->setXY($x, $yMarker);
            $pdf->setFont('fir', '', $pdf->getFontSizeLyrics());
            $pdf->cell($pdf->block_width, $pdf->font_height, substr($_marker, 2, strlen($_marker)-3), align: 'C');
	    $yMarker -= $pdf->font_height;
	  }
        }
      }
      $pdf->setXY($x, $y);
      $pdf->setFont('yan', '', $pdf->getFontSizeNote());
      $pdf->multiCell($pdf->block_width, 0, $_block->note_string, align: 'C');
      foreach (range(1, sizeof($this->note)) as $ln) {
        $nextx = $x + $pdf->block_width;
        $ln_y = $pdf->getY() + $pdf->font_height * $ln - $pdf->font_height * 4 - $pdf->font_height / 16;
        $_mark = $_block->getMark($ln);
        if (in_array('(', $_mark)) {
          $mark[$ln] = array('x' => $x + ($pdf->block_width - $_block->note_width) / 2, 'y' => $ln_y);
        }
        if (in_array(')', $_mark)) {
          $nextx = $nextx - ($pdf->block_width - $_block->note_width) / 2;
          if ($ln_y != $mark[$ln]['y']) {
            $pdf->line($mark[$ln]['x'], $mark[$ln]['y'], $pdf->canvas_left + $pdf->canvas_width, $mark[$ln]['y']);
            $pdf->line($pdf->canvas_left, $ln_y, $nextx, $ln_y);
          } else {
            $pdf->line($mark[$ln]['x'], $ln_y, $nextx, $ln_y);
          }
        }
      }
      $delta_y = 0.4 + $pdf->font_height * $_block->getNoteHeight();
      $pdf->setXY($x, $y + $delta_y);
      $pdf->setFont('yan', '', $pdf->getFontSizeLyrics());
      $pdf->multiCell($pdf->block_width, 0, $_block->lyrics_string, align: 'C');
      if ($x === $pdf->canvas_left) {
        //accolade
	$pdf->image("assets/accolade.png", $x - 2, $y + 1, 0, $delta_y * 0.92 );
      }
      $x += $pdf->block_width;
      $pdf->setXY($x, $y);
      $pdf->setFont('yan', '', $pdf->getFontSizeNote());
      $pdf->printSeparator($_block->separator, $_block->getNoteHeight());
      $pdf->setFont('yan', '', $pdf->getFontSizeLyrics());
      if ($x >= 0 * $pdf->canvas_left + $pdf->canvas_width) {
        $x = $pdf->canvas_left;
        $delta_y += $pdf->font_height * ($_block->getLyricsHeight() + 1.4);
	if (isset($this->meta['i'])) {
	  $delta_y += ($this->meta['i'] - 1) * $_block->getNoteHeight();
	}
        $y += $delta_y;
        if ($y >= $pdf->canvas_top + $pdf->canvas_height - $delta_y) {
          $y = $pdf->canvas_top;
          $pdf->addPage();
        }
      }
    }
    $pdf->output('F', 'pdfsolfa2.pdf');
  }
} //class Solfa
