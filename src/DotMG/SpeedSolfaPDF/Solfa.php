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
  private $alternate = [];
  private $x;
  private $y;
  private $pdf;
  private $options;
  public  $midi;
  public  $midiSPEED = 32;
  public  $midiFocusOther = 38;
  public  $midiFocusValue = 88;
  public  $midiFocusChannel = -1;
  function __construct($sourceFile = 'assets/samples/solfa-60.txt', $options = array())
  {
    $this->options = $options;
    $sourceAsArray = file($sourceFile); //@todo error handling
    array_walk($sourceAsArray, array($this, 'parseAllLines'));
    if ($_midiSPEED = $this->getOpt('midispeed')) {
      $this->midiSPEED = intval($_midiSPEED);
    }
    if ($_midiFocusOther = $this->getOpt('midifocusother')) {
      $this->midiFocusOther = $_midiFocusOther;
    }
    if ($_midiFocusValue = $this->getOpt('midifocusvalue')) {
      $this->midiFocusValue = $_midiFocusValue;
    }
    if ($_midiFocusChannel = $this->getOpt('midifocuschannel')) {
      $this->midiFocusChannel = $_midiFocusChannel;
    }
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
      if (($key == 'T') && ($index == 0)) {
        if (!isset($this->fileData['T'][0])) {
          $this->fileData['T'] =  array(rtrim($val));
        } else {
          $this->fileData['T'][0] .= '/'. rtrim($val);
        }
      }
      else {
        $this->fileData[$key][$index] = rtrim($val);
      }
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
    /* $_a_keyAbbrev = array(
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
      if ('c' == $key) {
        $this->meta['C'] = ucfirst($this->meta[$key]);
        $keyOrigin = $this->tonalityToNumber($this->meta[$key]);
        $keyDest = $keyOrigin;
        $keyAsIf = $keyOrigin;
        if ($transposeTo = $this->getOpt('transposeto')) {
          $keyDest = $this->tonalityToNumber($transposeTo);
        }
        if ($transposeAsIf = $this->getOpt('transposeasif')) {
          if (is_int($transposeAsIf)) {
            $keyAsIf = $transposeAsIf + $keyOrigin;
          } else {
            $keyAsIf = $this->tonalityToNumber($transposeAsIf);
          }
        }
        if (!isset($this->meta['transposeValue'])) {
          $this->meta['transposeValue'] = $keyDest - $keyAsIf;
        }
        if ($keyAsIf != $keyOrigin) {
          $this->meta['C'] = $this->numberToTonality($keyAsIf);
        }
        if ($keyDest != $keyOrigin) {
          $this->meta['C'] .= ' (' . $this->numberToTonality($keyDest) . ')';
        }
      }
    }
  }
  function getOpt($optionName) {
    if (isset($this->options[$optionName])) {
      return $this->options[$optionName];
    }
    return false;
  }
  function loadSeparators()
  {
    $separatorLine = trim($this->fileData['S'][0]);
    $this->separators = str_split($separatorLine);
  }
  function numberToTonality($number)
  {
    $numberToTonality = array('', 'C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B');
    return $numberToTonality[$number];
  }
  static function tonalityToNumber($tonality)
  {
    $tonalityToNumber = array(
      'C' => 1,
      'C#' => 2,       "Db" => 2,
      'D' => 3,
      'D#' => 4,       "Eb" => 4,
      'E' => 5,
      'F' => 6,
      'F#' => 7,       "Gb" => 7,
      'G' => 8,
      'G#' => 9,       "Ab" => 9,
      'A' => 10,
      'A#' => 11,      "Bb" => 11,
      'B' => 12,
    );
    if (!isset($tonalityToNumber[$tonality]))
    {
      return -1;
    }
    return $tonalityToNumber[$tonality];
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
        if ($noteSymbol == '}' && substr($noteMarker, 0, 2) == '${') {
          if (preg_match('/Do dia ([A-G]b?)/', $noteMarker, $_newTonaliteMatch)) {
            $newTonalite = $_newTonaliteMatch[1];
            $keyAsIf = $this->tonalityToNumber($newTonalite);
            $keyOrigin = $this->tonalityToNumber($this->meta['c']);
            if ($transposeTo = $this->getOpt('transposeto')) {
              $keyOrigin = $this->tonalityToNumber($transposeTo);
              $noteMarker .= ' (' . $this->numberToTonality($keyOrigin) . ')';
              $this->meta['transposeValue'] = $keyOrigin - $keyAsIf;
            }
            $this->marker[] = $newTonalite;
          }
          if (preg_match('/[IV]/', $noteMarker)) {
            $this->marker[] = $noteMarker.'}';
          }
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
    if ($templateNotes || ('' != $noteSymbol )) {
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
    $notes = preg_replace_callback('/(.)([1-9]+)/', function ($matches) {
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
        case '0':
          $this->note[$index][$noBlock] = $currentNote;
          $currentNote = $oneNote;
          if ('' == $this->note[$index][$noBlock]) {
            if (isset($parenOpen[$index])) {
              $this->noteMarq[$index][$noBlock][] = '(';
              unset($parenOpen[$index]);
            }
            if (isset($squareBracketOpen[$index])) {
              $this->noteMarq[$index][$noBlock][] = '[';
              print_r($this->noteMarq[$index][$noBlock]);
              unset($squareBracketOpen[$index]);
            }
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
          if (isset($squareBracketOpen[$index])) {
            unset($squareBracketOpen[$index]);
            $this->noteMarq[$index][$noBlock][] = '[';
          }
          break;
        case '(':
          $parenOpen[$index] = true;
          break;
        case '[':
          $squareBracketOpen[$index] = true;
          break;
        case ']':
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
    if (isset($this->fileData['L'])) {
      foreach ($this->fileData['L'] as $index => $lyrics) {
        $this->loadOneLyrics($lyrics, $index);
      }
    }
    if (isset($this->fileData['Y'])) {
      foreach ($this->fileData['Y'] as $index => $lyrics) {
        $this->loadSmartLyrics($lyrics, $index);
      }
    }
  }
  function loadSmartLyrics($lyrics, $index)
  {
    $lyrics = preg_replace('/[aeiouyàéỳ,;\.\-:](?![ aeiouyàéỳ,;\.\-:])/i', '\0_', $lyrics);
    $lyrics = preg_replace('/([aeiouyàéỳ,;\.\-:])__/i', '\1_', $lyrics);
    $lyrics = preg_replace('/ /', ' _', $lyrics);
    $lyrics = preg_replace('/_\//', '/', $lyrics);
    $lyrics = preg_replace('/_0/', '', $lyrics);
    $this->loadOneLyrics($lyrics, $index);
  }

  function loadOneLyrics($lyrics, $index)
  {
    $lyrics = preg_replace('/\{[^}]*}/', '', $lyrics);
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
  function setAlternate($marker) {
    $this->alternate[$marker] = $this->x;
  }
  function getAlternate($matchMarker) {
    if (empty($this->alternate)) {
      return null;
    }
    if ('' == $matchMarker) {
      $x0 = array_pop($this->alternate);
      return $x0;
    }
    if (isset($this->alternate[$matchMarker])) {
      $x0 = $this->alternate[$matchMarker];
      unset($this->alternate[$matchMarker]);
      return $x0;
    }
    return null;
  }
  function drawAlternate($matchMarker)  {
    $x0 = $this->getAlternate($matchMarker);
    if (null == $x0 || ($this->x - $x0 < 3)) {
      return;
    }
    $yMarker = $this->y - $this->pdf->fontHeight;
    $upp = $this->pdf->fontHeight / 2;
    $baseY = $yMarker + $this->pdf->fontHeight / 2;
    $this->pdf->line($x0, $baseY, $x0, $baseY - $upp);
    $this->pdf->line($x0, $baseY-$upp, $this->x, $baseY - $upp);
    $this->pdf->line($this->x, $baseY, $this->x, $baseY - $upp);
    $this->pdf->SetXY($x0 + ($this->x - $x0 ) / 4, $baseY - 3* $upp - 1 );
    $this->pdf->setFont('fir', '', ($this->pdf->getFontSizeNote() + $this->pdf->getFontSizeLyrics()) / 2);
    $this->pdf->cell(8, 4, $matchMarker);
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
   $nbBlocks = intval(($this->pdf->canvasWidth - $this->pdf->canvasLeft) / Block::$maxWidth);
   $xRight = $leftMost + $nbBlocks;
   if (!isset($this->options['s']) && !isset($this->options['smart-width'])) {
     return $nbBlocks;
   }
   if ($xRight >= sizeof($this->template)) {
     return $nbBlocks;
   }
   $rightSeparator = $this->separatorAt($xRight);
   while (($rightSeparator != '/') && ($rightSeparator != '|') && ($xRight > $leftMost + 3)) {
     $xRight --;
     $rightSeparator = $this->separatorAt($xRight);
   }
   if ($xRight < $leftMost + 3) {
     return $nbBlocks;
   }
   return $xRight-$leftMost;
  }
  function midiSequence($noteString, $separator, $marker)
  {
    $MIN_TRACK = 4;
    $this->midiKey = $this->meta['c'];
    $notes = explode("\n", $noteString);
    for ($idx = 0; $idx < $MIN_TRACK; $idx++)
    {
      if (!isset($notes[$idx])) {
        $notes[$idx] = '';
      }
      $vlu = $notes[$idx];
      $this->analyseNote($vlu, $idx, $separator, $marker);
      if (is_array($marker) && in_array('$Q', $marker))
      {
        $this->midiAddDuration($duration=6, $idx);
        $this->midiNewNote('', $idx);
        $this->midiAddDuration($duration=2, $idx);
      }
    }
    $dbg = [...$notes, $separator];
    #print_r($dbg);
  }
  function analyseNote($vlu, $idx, $separator, $marker)
  {
    $actualNote = '';
    $duration = 4;
    if ($marker == '$Q')
    {
      $duration = 8;
    }
    if ('' == $vlu)
    {
      $this->midiNewNote('', $idx);
      $this->midiAddDuration($duration, $idx);
      return;
    }
    if (is_array($marker) && (preg_match('/\|([A-G]b?)\|/', '|'.join('|', $marker).'|', $midiKeyArray)))
    {
      $this->midiAddMarker($midiKeyArray[1], $idx);
    }
    $vlu = preg_replace("/[\\.,](?!d|r|m|f|s|l|t|\-)/", "\\0-", $vlu);
    //$vlu = preg_replace("/^[\\.,]/", "-\\0", $vlu);
    $letters = mb_str_split($vlu);
    foreach ($letters as $letter)
    {
      switch($letter)
      {
      case '-':
        $this->midiAddDuration($duration, $idx);
        $actualNote = '-';
        continue 2;
      case '.':
        $duration /= 2;
        if ($actualNote == '')
        {
          $this->midiBlank($idx);
          $this->midiAddDuration($duration*2, $idx);
        }
        $this->midiAddDuration(-$duration, $idx);
        continue 2;
      case ',':
        $duration /= 2;
        $this->midiAddDuration(-$duration, $idx);
        continue 2;
      case 'd': case 'r': case 'm': case 'f': case 's': case 'l': case 't':
        $this->midiNewNote($letter, $idx);
        $this->midiAddDuration($duration, $idx);
        $actualNote = $letter;
        continue 2;
      case 'i': case '₁': case 'a': case "'": case "₂":
        $this->midiAlterNote($letter, $idx);
        continue 2;
      case ' ': case '0':
        $this->midiNewNote('', $idx);
        $this->midiAddDuration($duration, $idx);
        continue 2;
      default:
        die("ERRXD: [$letter] LEN:".strlen($letter). " ASCII:".ord($letter));
      }
    }
  }
  function midiBlank($idx)
  {
    $this->midiNewNote('x', $idx);
  }
  function midiAddMarker($marker, $idx)
  {
    $_i = $this->midi[$idx]["seq"];
    if (!isset($this->midi[$idx]["m"]))
    {
      $this->midi[$idx]["m"] = array();
    }
    $this->midi[$idx]["m"][$_i] = $marker;
  }

  function midiNewNote($note, $idx)
  {
    if (!isset($this->midi[$idx]))
    {
      $this->midi[$idx] = array();
    }
    if (!isset($this->midi[$idx]["seq"]))
    {
      $this->midi[$idx]["seq"] = 0;
    }
    $_i = $this->midi[$idx]["seq"];
    #if ($idx == 1) print ( "NEWNOTE: $idx {$this->midi[$idx]["n"][$_i]} / {$this->midi[$idx]["d"][$_i]}\n");
    $this->midi[$idx]["seq"]++;
    $_i = $this->midi[$idx]["seq"];
    if (!isset($this->midi[$idx]["n"]))
    {
      $this->midi[$idx]["n"] = array();
    }
    $this->midi[$idx]["n"][$_i] = $note;
  }
  function midiAlterNote($alteration, $idx)
  {
    $_i = $this->midi[$idx]["seq"];
    switch ($alteration) {
    case 'i': case 'a':
      $this->midi[$idx]["n"][$_i] = strtoupper($this->midi[$idx]["n"][$_i]);
      return;
    case '₁':
      $this->midi[$idx]["n"][$_i] .= ',';
      return;
    case '₂':
      $this->midi[$idx]["n"][$_i] .= ',,';
      return;
    case "'":
      $this->midi[$idx]["n"][$_i] .= "'";
      return;
    }
  }
  function midiAddDuration($duration, $idx)
  {
    $_i = $this->midi[$idx]["seq"];
    if (!isset($this->midi[$idx]["d"]))
    {
      $this->midi[$idx]["d"] = array();
    }
    if (!isset($this->midi[$idx]["d"][$_i]))
    {
      $this->midi[$idx]["d"][$_i] = 0;
    }
    $this->midi[$idx]["d"][$_i] += $duration;
  }
  function renderPDF()
  {
    //@todo :
    $this->pdf = new PDF($this->meta);
    $this->pdf->setupSize();
    $nbBlocks = $this->pdf->recalcWidth();
    $newNbBlocks = $this->smartWidth();
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
    $tonaliteRythme = 'Do dia ' . $this->meta["C"] . '       ' . $this->meta['m'];
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
      $hasMarker = null;
      if (is_array($oneBlock->marker) && sizeof($oneBlock->marker) > 0) {
        $yMarker = $this->y - $this->pdf->fontHeight;
        foreach ($oneBlock->marker as $oneMarker) {
          if (preg_match('/^..\[([IV]+)\}$/', $oneMarker, $matchMarker)) {
            $this->setAlternate($matchMarker[1]);
            $oneMarker = '';
          }
          if (preg_match('/^..([IV]*)\]\}$/', $oneMarker, $matchMarker)) {
            $this->drawAlternate($matchMarker[1]);
            $oneMarker = '';
          }
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
            $hasMarker[] = $oneMarker;
          }
          if (preg_match('/^[A-G]b?$/', $oneMarker))
          {
            $hasMarker[] = $oneMarker;
          }
          if ('${' == substr($oneMarker, 0, 2)) {   // this is for VIM }
            $this->pdf->setXY($this->x, $yMarker);
            $this->pdf->setFont('fir', '', ($this->pdf->getFontSizeNote() + $this->pdf->getFontSizeLyrics()) / 2 );
            $this->pdf->cell($this->pdf->blockWidth, $this->pdf->fontHeight, substr($oneMarker, 2, strlen($oneMarker)-3), align: 'C');
            $yMarker -= $this->pdf->fontHeight;
          }
        }
      }
      $this->pdf->setXY($this->x, $this->y);
      $this->pdf->setFont('yan', '', $this->pdf->getFontSizeNote());
      $this->pdf->multiCell($this->pdf->blockWidth, 0, $oneBlock->noteString, align: 'C');
      $this->midiSequence($oneBlock->noteString, $oneBlock->separator, $hasMarker);
      foreach (range(1, sizeof($this->note)) as $ln) {
        $nextX = $this->x + $this->pdf->blockWidth;
        $yLine = $this->y + $this->pdf->fontHeight * $ln;
        $oneBlockMark = $oneBlock->getMark($ln);
        if ( (in_array('(', $oneBlockMark)) || (in_array('[', $oneBlockMark)) ) {
          $mark[$ln] = array('x' => $this->x + ($this->pdf->blockWidth - $oneBlock->noteWidth) / 2, 'y' => $yLine);
        }
        if ( (in_array(')', $oneBlockMark)) || (in_array(']', $oneBlockMark)) ) {
          if (in_array(']', $oneBlockMark))
          {
            $this->pdf->setDash(0.6, 0.8);
          }
          $nextX = $nextX - ($this->pdf->blockWidth - $oneBlock->noteWidth) / 2;
          if ($yLine != $mark[$ln]['y']) {
            $this->pdf->line($mark[$ln]['x'], $mark[$ln]['y'], $this->pdf->canvasLeft + $this->pdf->canvasWidth, $mark[$ln]['y']);
            $this->pdf->line($this->pdf->canvasLeft, $yLine, $nextX, $yLine);
          } else {
            $this->pdf->line($mark[$ln]['x'], $yLine, $nextX, $yLine);
          }
          if (in_array(']', $oneBlockMark))
          {
            $this->pdf->setDash();
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
      // } else {
      //  $this->x += 0;
      }
      $this->pdf->setXY($this->x, $this->y);
      if (($this->x < $this->pdf->canvasWidth + $this->pdf->canvasLeft) || ($oneBlock->separator == '/')) {
        $this->pdf->setFont('yan', '', $this->pdf->getFontSizeNote());
        $this->pdf->printSeparator($oneBlock->separator, $oneBlock->getNoteHeight());
      } else {
        $this->pdf->image("assets/accoladef.png", $this->x - 2, $this->y + 1, 0, $deltaY * 0.92 );
      }
      $this->pdf->setFont('yan', '', $this->pdf->getFontSizeLyrics());
      if ($this->x >= $this->pdf->canvasLeft + $this->pdf->canvasWidth) {
        $hairPin = $this->getHairpin();
        if ($hairPin != null) {
          $this->drawHairpin();
        }
        if (!empty($this->alternate)) {
          foreach ($this->alternate as $ky => $vl) {
            $this->drawAlternate($ky);
            $this->alternate[$ky] = $this->pdf->canvasLeft;
          }
        }
        $this->x = $this->pdf->canvasLeft;
        $newNbBlocks = $this->smartWidth($oneBlock->getNum());
        $this->pdf->blockWidth = $this->pdf->canvasWidth / $newNbBlocks;
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

        $this->pdf->SetXY($this->x+1, $this->y);
        $this->pdf->setFont('yan', '', $this->pdf->getFontSizeNote());
        $this->pdf->printSeparator($this->separatorAt($oneBlock->getNum()), $oneBlock->getNoteHeight());
      }
    }

    $jsMidi = "import MidiWriter from 'midi-writer-js';
    let note = null;\nlet midiTrack = [];\n";
    $instrum = array(1, 20, 43, 33, 59, 34, 42);

    $introMidi = array();
    $introMididur = 0;
    foreach ($this->midi as $idx => $mididata)
    {
      $wait = 0;
      $jsMidi .= "midiTrack[$idx] = new MidiWriter.Track();
      midiTrack[$idx].addEvent(new MidiWriter.ProgramChangeEvent({channel: $idx, instrument: {$instrum[$idx]}}));\n//___MIDITRACK_DOTMG_$idx\n";
      $introMidi[$idx] = '';
      $introdur[$idx] = 0;
      for ($i = 1; $i <= $mididata["seq"]; $i++)
      {
        $introwait = 0;
        $tjsMidi = '';
        if ($mididata['n'][$i] ==  '')
        {
          $wait += $mididata['d'][$i] * $this->midiSPEED;
        }
        else
        {
          if ($mididata['n'][$i] == 'x')
          {
            $G4 = 'G4';
            $mute = true;
          }
          else
          {
            $G4 = Block::midiTransposed($mididata['n'][$i], $this->midiKey);
            $mute = false;
          }

          $duration = $mididata['d'][$i] * $this->midiSPEED;
          $tjsMidi .= "note = new MidiWriter.NoteEvent({pitch: ['$G4'], channel: $idx, ";
          if ($wait)
          {
            $tjsMidi .= "wait: 'T$wait', ";
            $introwait = $wait;
            $wait = 0;
          }
          if ($mute)
          {
            $tjsMidi .= "velocity: 0, ";
          }
          elseif ($this->midiFocusChannel == -1)
          {
            $tjsMidi .= "velocity: 95, ";
          }
          elseif ($idx == $this->midiFocusChannel)
          {
            $tjsMidi .= "velocity: {$this->midiFocusValue}, ";
          }
          else
          {
            $tjsMidi .= "velocity: {$this->midiFocusOther}, ";
          }
          $tjsMidi .= "duration: 'T$duration'}); midiTrack[$idx].addEvent(note);\n";
        }
        if (isset($mididata['m'][$i]))
        {
          $this->midiKey = $mididata['m'][$i];
        }
        if ($i > $mididata["seq"] - 7) {
          $introMidi[$idx] .= $tjsMidi;
          if ($i == $mididata["seq"]) {
            $introMidi[$idx] .= "note = new MidiWriter.NoteEvent({pitch: ['$G4'], channel: $idx, velocity: 50, duration: 'T160'}); midiTrack[$idx].addEvent(note);";
          }
          $introdur[$idx] += $duration + $introwait;
          $introwait = 0;
        }
        $jsMidi .= $tjsMidi;
      }
      if ($idx == 0) {
        $introMididur = $introdur[$idx];
      }
      $pause = '';
      if ($introMididur > $introdur[$idx]) {
        $pause = "note = new MidiWriter.NoteEvent({pitch: ['G4'], channel: $idx, velocity: 0, duration: 'T".($introMididur - $introdur[$idx])."'}); midiTrack[$idx].addEvent(note);";
      }
      if ($introMididur < $introdur[$idx]) {
        // @todo: replace all //dn
      }
      $jsMidi = str_replace("//___MIDITRACK_DOTMG_$idx", "//d$idx - {$introdur[$idx]}\n$pause{$introMidi[$idx]}\n//e$idx\n", $jsMidi);
    }
    $jsMidi .= "const write = new MidiWriter.Writer(midiTrack);
    write.stdout();\n";
    //print_r($this->midi);
    $this->pdf->output('F', 'pdfsolfa2.pdf');
    echo $jsMidi;
  }
} //class Solfa
