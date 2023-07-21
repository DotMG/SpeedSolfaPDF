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
  var $lyricsLine = 0;
  function __construct($sourceFile = 'assets/samples/solfa-60.txt')
  {
    $sourceAsArray = file($sourceFile); //@todo error handling
    array_walk($sourceAsArray, array($this, 'parseAllLines'));
    $this->loadMeta();
    $this->loadSeparators();
    $this->loadNotes();
    $this->loadAllLyrics();
    $this->loadNoteTemplate();
    $this->setupBlocks();
  } //fun __construct
  function parseAllLines($line)
  {
    if (':' == substr($line, 2, 1)) {
      $key = substr($line, 0, 1);
      $index = intval(substr($line, 1, 1));
      $val = substr($line, 3);
    } else {
      $key = substr($line, 0, 3);
      $index = intval(substr($line, 1, 2));
      $val = substr($line, 4);
    }
    if ('' != trim($key)) {
      $this->fileData[$key][$index] = rtrim($val);
    }
  }
  function debug()
  {
    print_r($this);
  }
  function loadMeta()
  {
    $txtMetaLine = $this->fileData['M'][0];
    $metaItemArray = preg_split('/\|(?=[a-z]:)/', $txtMetaLine);
    array_walk($metaItemArray, array($this, 'getMeta'));
  }
  function getMeta($metaItem)
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
    $key = substr($metaItem, 0, 1);
    if ('' != trim($key) && ('M' != $key)) {
      $this->meta[$key] = substr($metaItem, 2);
    }
  }
  function loadSeparators()
  {
    $separatorLine = trim($this->fileData['S'][0]);
    $this->separators = str_split($separatorLine);
  }
  function loadNoteTemplate()
  {
    $noteTemplate = $this->fileData['T'][0];
    $templateNotes = '';
    $noteMarker    = '';
    /* Markers are :
     * $< or $> : marks starting of < or >
     * $= : marks end of < or >
     * $Q : point d'orgue
     */
    $this->marker = array();
    foreach (str_split($noteTemplate) as $noteSymbol) {
      if ($noteMarker != '') {
        if ($noteSymbol != '}' && substr($noteMarker, 0, 2) == '${') {
          $noteMarker .= $noteSymbol;
          continue;
        }
        $noteMarker .= $noteSymbol;
        if ($noteSymbol != '{') {
          $this->marker[] = $noteMarker;
          $noteMarker = '';
        }
        continue;
      }
      if (in_array($noteSymbol, $this->separators)) {
        $this->nextNote($templateNotes, $noteSymbol);
        $templateNotes = '';
        continue;
      }
      if ('$' == $noteSymbol) {
        $noteMarker = '$';
        continue;
      }
      $templateNotes .= $noteSymbol;
    }
    if ($templateNotes) {
      $this->nextNote($templateNotes);
    }
  }
  function nextNote($templateNote, $separator = '')
  {
    $newBlock = new Block($templateNote, $separator, $this->marker, $this->meta);
    list($subNote, $subMark) = $this->getSubNotes($newBlock->getNbNote());
    if ($subMark) {
      $newBlock->setMark($subMark);
    }
    $newBlock->setNote($subNote);
    $subLyrics = $this->getSubLyrics($newBlock->getNbLyrics());
    $newBlock->setLyrics($subLyrics);
    $this->template[$this->i_block] = $newBlock;
    if ('/' == $separator) {
      $this->lyricsLine++;
      $this->i_lyrics = 0;
    }
    $this->i_block++;
    $this->marker = array();
  }
  function loadNotes()
  {
    foreach ($this->fileData['N'] as $index => $notes) {
      $this->loadNoteAtIndex($notes, $index);
    }
  }
  function loadNoteAtIndex($notes, $index)
  {
    $notes = preg_replace_callback('/(.)(\d+)/', function ($matches) {
      return str_repeat($matches[1], $matches[2]);
    }, $notes);
    $aNotes = str_split($notes);
    $currentNote = '';
    $noBlock = 0;
    foreach ($aNotes as $oneNote) {
      switch ($oneNote) {
        case "'":
        case ',':
          $currentNote .= $oneNote;
          break;
        case '"':
          $currentNote .= "''";
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
          $this->note[$index][$noBlock] = $currentNote;
          $currentNote = $oneNote;
          if ('' == $this->note[$index][$noBlock]) {
            break;
          }
          if ('|' == $oneNote) {
            $currentNote = '';
          }
          $noBlock++;
          if (isset($parenOpen[$index])) {
            unset($parenOpen[$index]);
            $this->noteMarq[$index][$noBlock][] = '(';
          }
          break;
        case '(':
          $parenOpen[$index] = true;
          break;
        case ')':
          $this->noteMarq[$index][$noBlock][] = $oneNote;
          break;
      }
    }
    if ($currentNote != '') {
      $this->note[$index][$noBlock] = $currentNote;
    }
  }
  function loadAllLyrics()
  {
    foreach ($this->fileData['L'] as $index => $lyrics) {
      $this->loadOneLyrics($lyrics, $index);
    }
  }
  function loadOneLyrics($lyrics, $index)
  {
    $lyrics = preg_replace_callback('/_(\d)/', function ($matches) {
      return str_repeat('_', $matches[1]);
    }, $lyrics);
    $arrayLyrics = preg_split('/[\/]/', $lyrics);
    foreach ($arrayLyrics as $i => $lyricsItem) {
      $lyricsItem = str_replace('_', '-_', $lyricsItem);
      $lyricsItem = str_replace('_-_', '__', $lyricsItem);
      $lyricsItem = str_replace(' -_', '_', $lyricsItem);
      $lyricsItem = str_replace(' _', '_', $lyricsItem);
      $this->lyrics[$index][$i] = preg_split('/_/', $lyricsItem);
    }
  }
  function setupBlocks()
  {
  }
  function getSubNotes($nbNote)
  {
    if (0 == $nbNote) {
      return array(array_fill(1, sizeof($this->note), array('')), array());
    }
    $subNote  = array();
    $subMark  = array();
    foreach ($this->note as $kNote => $vNote) {
      $subNote[$kNote]   = array_slice($vNote, $this->i_note, $nbNote);
      $mark = array();
      for ($i = $this->i_note; $i < $this->i_note + $nbNote; $i++) {
        if (isset($this->noteMarq[$kNote][$i])) {
          $mark[$i] = $this->noteMarq[$kNote][$i];
        }
      }
      if (sizeof($mark)) {
        $subMark[$kNote] = $mark;
      }
    }
    $this->i_note += $nbNote;
    return array($subNote, $subMark);
  }
  function getSubLyrics($nbLyrics)
  {
    if (0 == $nbLyrics) {
      return array_fill(1, sizeof($this->lyrics), '');
    }
    $subLyrics = array();
    foreach ($this->lyrics as $kLyrics => $vLyrics) {
      if (!isset($vLyrics[$this->lyricsLine]) || !is_array($vLyrics[$this->lyricsLine])) {
        $vLyrics[$this->lyricsLine] = array();
      }
      $subLyrics[$kLyrics] = join('', array_slice($vLyrics[$this->lyricsLine], $this->i_lyrics, $nbLyrics));
    }
    $this->i_lyrics += $nbLyrics;
    return $subLyrics;
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
    $x = $pdf->canvasLeft;
    $y = $pdf->canvasTop;
    // ecriture entete
    //title 
    $pdf->setXY($x, $y);
    $pdf->setFont('yan', '', $pdf->getFontSizeNote()+6);
    $pdf->cell($pdf->canvasWidth, $pdf->fontHeight, $this->meta['t'], align: 'C');
    $y = $pdf->getY() + $pdf->fontHeight * 1.5;
    // author
    $pdf->setXY($x, $y);
    $pdf->setFont('yan', '', $pdf->getFontSizeLyrics()+2);
    $pdf->cell($pdf->canvasWidth, $pdf->fontHeight, $this->meta['h'], align: 'R');
    $pdf->setXY($x, $y);
    $pdf->multiCell($pdf->canvasWidth / 2, $pdf->fontHeight, $this->meta['a'], align: 'L');
    //
    $y = $pdf->getY() + $pdf->fontHeight;
    //tonalite + rythme
    $pdf->setFont('fir', '', $pdf->getFontSizeLyrics());
    $tonaliteRythme = 'Do dia ' . $this->meta["c"] . '       ' . $this->meta['m'];
    $pdf->setXY($x, $y);
    $pdf->cell($pdf->getStringWidth($tonaliteRythme), $pdf->fontHeight, $tonaliteRythme, align: 'L');
    // speed 
    $pdf->setXY($x, $y);
    $pdf->cell($pdf->canvasWidth, $pdf->fontHeight, $this->meta['r'], ln: 0, align: 'C');

    $y = $pdf->getY() + $pdf->fontHeight * 2;
    if (isset($this->meta['i'])) {
      $y += ($pdf->fontHeight) * ($this->meta['i'] - 1);
    }

    $pdf->setXY($x, $y);
    $mark = array();
    foreach ($this->template as $oneBlock) {
      if (is_array($oneBlock->marker) && sizeof($oneBlock->marker) > 0) {
        $yMarker = $y - $pdf->fontHeight;
        foreach ($oneBlock->marker as $oneMarker) {
          if ($oneMarker == '$<' || $oneMarker == '$>') {
            $this->setHairpin($x, $oneMarker);
          }
          if ($oneMarker == '$=') {
            list($x0, $crescendoOrDiminuendo) = $this->getHairpin();
            $upp = $pdf->fontHeight / 4;
            $baseY = $yMarker + $pdf->fontHeight / 2;
            if ($crescendoOrDiminuendo == '$>') {
              $pdf->line($x0, $baseY-$upp, $x, $baseY);
              $pdf->line($x0, $baseY+$upp, $x, $baseY);
            }
            if ($crescendoOrDiminuendo == '$<') {
              $pdf->line($x0, $baseY, $x, $baseY-$upp);
              $pdf->line($x0, $baseY, $x, $baseY+$upp);
            }
          }
          if ($oneMarker == '$Q') {
            $pdf->setXY($x, $yMarker);
            $pdf->setFont('fir', '', $pdf->getFontSizeLyrics());
            $pdf->cell($pdf->blockWidth, $pdf->fontHeight, "Ͼ", align: 'C');
            $yMarker -= $pdf->fontHeight;
          }
          if ('${' == substr($oneMarker, 0, 2)) {   // this is for VIM }
            $pdf->setXY($x, $yMarker);
            $pdf->setFont('fir', '', $pdf->getFontSizeLyrics());
            $pdf->cell($pdf->blockWidth, $pdf->fontHeight, substr($oneMarker, 2, strlen($oneMarker)-3), align: 'C');
            $yMarker -= $pdf->fontHeight;
          }
        }
      }
      $pdf->setXY($x, $y);
      $pdf->setFont('yan', '', $pdf->getFontSizeNote());
      $pdf->multiCell($pdf->blockWidth, 0, $oneBlock->noteString, align: 'C');
      foreach (range(1, sizeof($this->note)) as $ln) {
        $nextX = $x + $pdf->blockWidth;
        $yLine = $pdf->getY() + $pdf->fontHeight * $ln - $pdf->fontHeight * 4 - $pdf->fontHeight / 16;
        $oneBlockMark = $oneBlock->getMark($ln);
        if (in_array('(', $oneBlockMark)) {
          $mark[$ln] = array('x' => $x + ($pdf->blockWidth - $oneBlock->noteWidth) / 2, 'y' => $yLine);
        }
        if (in_array(')', $oneBlockMark)) {
          $nextX = $nextX - ($pdf->blockWidth - $oneBlock->noteWidth) / 2;
          if ($yLine != $mark[$ln]['y']) {
            $pdf->line($mark[$ln]['x'], $mark[$ln]['y'], $pdf->canvasLeft + $pdf->canvasWidth, $mark[$ln]['y']);
            $pdf->line($pdf->canvasLeft, $yLine, $nextX, $yLine);
          } else {
            $pdf->line($mark[$ln]['x'], $yLine, $nextX, $yLine);
          }
        }
      }
      $deltaY = 0.4 + $pdf->fontHeight * $oneBlock->getNoteHeight();
      $pdf->setXY($x, $y + $deltaY);
      $pdf->setFont('yan', '', $pdf->getFontSizeLyrics());
      $pdf->multiCell($pdf->blockWidth, 0, $oneBlock->lyricsString, align: 'C');
      if ($x === $pdf->canvasLeft) {
        //accolade
        $pdf->image("assets/accolade.png", $x - 2, $y + 1, 0, $deltaY * 0.92 );
      }
      if ($oneBlock->template != '') {
        $x += $pdf->blockWidth;
      } else {
        $x += $pdf->blockWidth / 4;
      }
      $pdf->setXY($x, $y);
      $pdf->setFont('yan', '', $pdf->getFontSizeNote());
      $pdf->printSeparator($oneBlock->separator, $oneBlock->getNoteHeight());
      $pdf->setFont('yan', '', $pdf->getFontSizeLyrics());
      if ($x >= 0 * $pdf->canvasLeft + $pdf->canvasWidth) {
        $x = $pdf->canvasLeft;
        $deltaY += $pdf->fontHeight * ($oneBlock->getLyricsHeight() + 1.4);
        if (isset($this->meta['i'])) {
          $deltaY += ($this->meta['i'] - 1) * $oneBlock->getNoteHeight();
        }
        $y += $deltaY;
        if ($y >= $pdf->canvasTop + $pdf->canvasHeight - $deltaY) {
          $y = $pdf->canvasTop;
          $pdf->addPage();
        }
      }
    }
    $pdf->output('F', 'pdfsolfa2.pdf');
  }
} //class Solfa
