<?php

namespace DotMG\SpeedSolfaPDF;

class Solfa
{
  private $fileData = array();
  private $meta = array();
  private $note = array();
  private $template = array();
  private $lyrics = array();
  private $i_block = 0;
  private $i_note = 0;
  private $i_lyrics = 0;
  private $marker;
  private $lyricsLine = 0;
  private $hairpin = null;
  private $x;
  private $y;
  private $pdf;
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
  function setHairpin($marker) {
    $this->hairpin = array($this->x, $marker);
  }
  function getHairpin() {
    return $this->hairpin;
  }
  function unsetHairpin() {
    $this->hairpin = null;
  }
  function drawHairpin() {
    list($x0, $crescendoOrDiminuendo) = $this->getHairpin();
    $yMarker = $this->y - $this->pdf->fontHeight;
    $this->unsetHairpin();
    $upp = $this->pdf->fontHeight / 4;
    $baseY = $yMarker + $this->pdf->fontHeight / 2;
    if ($crescendoOrDiminuendo == '$>') {
      $this->pdf->line($x0, $baseY-$upp, $this->x, $baseY);
      $this->pdf->line($x0, $baseY+$upp, $this->x, $baseY);
    }
    if ($crescendoOrDiminuendo == '$<') {
      $this->pdf->line($x0, $baseY, $this->x, $baseY-$upp);
      $this->pdf->line($x0, $baseY, $this->x, $baseY+$upp);
    }
  }
  function separatorAt($x) {
    if (isset($this->template[$x]) && isset($this->template[$x]->separator)) {
      return $this->template[$x]->separator;
    }
    return '';
  }
  /* When smartWidth is activated, SpeedSolfaPDF does its best to right-align
   * the solfa line on either double-bar or single vertical bar
   */
  function smartWidth($leftMost = 0) {
   $nbBlocks = intval($this->pdf->canvasWidth / Block::$maxWidth);
   $xRight = $leftMost + $nbBlocks;
    if ($xRight > 10 + $leftMost) {
      while (($xRight > 5 + $leftMost) && ($this->separatorAt($xRight) != '/') 
	&& ($this->separatorAt($xRight) != '|')) {
        $xRight--;
      }
      if ($this->separatorAt($xRight) == '/') {
        return $xRight-$leftMost; 
      }
      if ($this->separatorAt($xRight) == '|') {
	for ($xDoubleBar = $xRight; $xDoubleBar > $xRight - 4; $xDoubleBar--) {
	  if ($this->separatorAt($xDoubleBar) == '/') {
	    return $xDoubleBar-$leftMost;
	  }
	}
	return $xRight-$leftMost;
      }
      if ($xRight == 5 + $leftMost) {
        return $nbBlocks;
      }
    }
    return $nbBlocks;
  }
  function renderPDF()
  {
    //@todo : 
    $this->pdf = new PDF($this->meta);
    $this->pdf->setupSize();
    $nbBlocks = $this->pdf->recalcWidth();
    $newNbBlocks = $this->smartWidth()+1;
    if ($newNbBlocks != $nbBlocks) {
     $this->pdf->blockWidth = $this->pdf->canvasWidth / $newNbBlocks;
    }
    $this->x = $this->pdf->canvasLeft;
    $this->y = $this->pdf->canvasTop;
    // ecriture entete
    //title 
    $this->pdf->setXY($this->x, $this->y);
    $this->pdf->setFont('yan', '', $this->pdf->getFontSizeNote()+6);
    $this->pdf->cell($this->pdf->canvasWidth, $this->pdf->fontHeight, $this->meta['t'], align: 'C');
    $this->y = $this->pdf->getY() + $this->pdf->fontHeight * 1.5;
    // author
    $this->pdf->setXY($this->x, $this->y);
    $this->pdf->setFont('yan', '', $this->pdf->getFontSizeLyrics()+2);
    $this->pdf->cell($this->pdf->canvasWidth, $this->pdf->fontHeight, $this->meta['h'], align: 'R');
    $this->pdf->setXY($this->x, $this->y);
    $this->pdf->multiCell($this->pdf->canvasWidth / 2, $this->pdf->fontHeight, $this->meta['a'], align: 'L');
    //
    $this->y = $this->pdf->getY() + $this->pdf->fontHeight;
    //tonalite + rythme
    $this->pdf->setFont('fir', '', $this->pdf->getFontSizeLyrics());
    $tonaliteRythme = 'Do dia ' . $this->meta["c"] . '       ' . $this->meta['m'];
    $this->pdf->setXY($this->x, $this->y);
    $this->pdf->cell($this->pdf->getStringWidth($tonaliteRythme), $this->pdf->fontHeight, $tonaliteRythme, align: 'L');
    // speed 
    $this->pdf->setXY($this->x, $this->y);
    $this->pdf->cell($this->pdf->canvasWidth, $this->pdf->fontHeight, $this->meta['r'], ln: 0, align: 'C');

    $this->y = $this->pdf->getY() + $this->pdf->fontHeight * 2;
    if (isset($this->meta['i'])) {
      $this->y += ($this->pdf->fontHeight) * ($this->meta['i'] - 1);
    }

    $this->pdf->setXY($this->x, $this->y);
    $mark = array();
    foreach ($this->template as $oneBlock) {
      if (is_array($oneBlock->marker) && sizeof($oneBlock->marker) > 0) {
        $yMarker = $this->y - $this->pdf->fontHeight;
        foreach ($oneBlock->marker as $oneMarker) {
          if ($oneMarker == '$<' || $oneMarker == '$>') {
            $this->setHairpin($oneMarker);
          }
          if ($oneMarker == '$=') {
	    $this->drawHairpin();
          }
          if ($oneMarker == '$Q') {
            $this->pdf->setXY($this->x, $yMarker);
            $this->pdf->setFont('fir', '', $this->pdf->getFontSizeLyrics());
            $this->pdf->cell($this->pdf->blockWidth, $this->pdf->fontHeight, "Ͼ", align: 'C');
            $yMarker -= $this->pdf->fontHeight;
          }
          if ('${' == substr($oneMarker, 0, 2)) {   // this is for VIM }
            $this->pdf->setXY($this->x, $yMarker);
            $this->pdf->setFont('fir', '', $this->pdf->getFontSizeLyrics());
            $this->pdf->cell($this->pdf->blockWidth, $this->pdf->fontHeight, substr($oneMarker, 2, strlen($oneMarker)-3), align: 'C');
            $yMarker -= $this->pdf->fontHeight;
          }
        }
      }
      $this->pdf->setXY($this->x, $this->y);
      $this->pdf->setFont('yan', '', $this->pdf->getFontSizeNote());
      $this->pdf->multiCell($this->pdf->blockWidth, 0, $oneBlock->noteString, align: 'C');
      foreach (range(1, sizeof($this->note)) as $ln) {
        $nextX = $this->x + $this->pdf->blockWidth;
        $yLine = $this->pdf->getY() + $this->pdf->fontHeight * $ln - $this->pdf->fontHeight * 4 - $this->pdf->fontHeight / 16;
        $oneBlockMark = $oneBlock->getMark($ln);
        if (in_array('(', $oneBlockMark)) {
          $mark[$ln] = array('x' => $this->x + ($this->pdf->blockWidth - $oneBlock->noteWidth) / 2, 'y' => $yLine);
        }
        if (in_array(')', $oneBlockMark)) {
          $nextX = $nextX - ($this->pdf->blockWidth - $oneBlock->noteWidth) / 2;
          if ($yLine != $mark[$ln]['y']) {
            $this->pdf->line($mark[$ln]['x'], $mark[$ln]['y'], $this->pdf->canvasLeft + $this->pdf->canvasWidth, $mark[$ln]['y']);
            $this->pdf->line($this->pdf->canvasLeft, $yLine, $nextX, $yLine);
          } else {
            $this->pdf->line($mark[$ln]['x'], $yLine, $nextX, $yLine);
          }
        }
      }
      $deltaY = 0.4 + $this->pdf->fontHeight * $oneBlock->getNoteHeight();
      $this->pdf->setXY($this->x, $this->y + $deltaY);
      $this->pdf->setFont('yan', '', $this->pdf->getFontSizeLyrics());
      $this->pdf->multiCell($this->pdf->blockWidth, 0, $oneBlock->lyricsString, align: 'C');
      if ($this->x === $this->pdf->canvasLeft) {
        //accolade
        $this->pdf->image("assets/accolade.png", $this->x - 2, $this->y + 1, 0, $deltaY * 0.92 );
      }
      if ($oneBlock->template != '') {
        $this->x += $this->pdf->blockWidth;
      } else {
        $this->x += $this->pdf->blockWidth / 4;
      }
      $this->pdf->setXY($this->x, $this->y);
      $this->pdf->setFont('yan', '', $this->pdf->getFontSizeNote());
      $this->pdf->printSeparator($oneBlock->separator, $oneBlock->getNoteHeight());
      $this->pdf->setFont('yan', '', $this->pdf->getFontSizeLyrics());
      if ($this->x >= 0 * $this->pdf->canvasLeft + $this->pdf->canvasWidth) {
	$hairPin = $this->getHairpin();
	if ($hairPin != null) {
	  $this->drawHairpin();
	}
        $this->x = $this->pdf->canvasLeft;
        $newNbBlocks = $this->smartWidth($oneBlock->getNum());
        if ($newNbBlocks != $nbBlocks) {
         $this->pdf->blockWidth = $this->pdf->canvasWidth / $newNbBlocks;
        }
	if ($hairPin) {
	  list($_, $hairpinSign) = $hairPin;
	  $this->setHairpin($hairpinSign);
	}
        $deltaY += $this->pdf->fontHeight * ($oneBlock->getLyricsHeight() + 1.4);
        if (isset($this->meta['i'])) {
          $deltaY += ($this->meta['i'] - 1) * $oneBlock->getNoteHeight();
        }
        $this->y += $deltaY;
        if ($this->y >= $this->pdf->canvasTop + $this->pdf->canvasHeight - $deltaY) {
          $this->y = $this->pdf->canvasTop;
          $this->pdf->addPage();
        }
      }
    }
    $this->pdf->output('F', 'pdfsolfa2.pdf');
  }
} //class Solfa
