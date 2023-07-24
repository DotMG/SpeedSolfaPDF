<?php
namespace DotMG\SpeedSolfaPDF;

class PDF extends \tFPDF
{
  private $text;
  private $fontSizeNote = 12.5;
  private $fontSizeLyrics = 10;
  public $blockWidth;
  public $fontHeight;
  function __construct($meta = null, $orientation = 'P', $unit = 'mm', $size = 'A4')
  {
    parent::__construct($orientation, $unit, $size);
    $this->addFont('yan', '', 'DotKaffeesatz-Light.ttf', true);
    $this->addFont('fir', '', 'FiraDot-Regular.ttf', true);
    $this->addPage($orientation);
    $this->setAutoPageBreak(false);
    $this->setCellMargin(0);
    if (isset($meta['n']) || isset($meta['l'])) {
      if (!isset($meta['n'])) {
        $meta['n'] = 12.5;
      }
      if (!isset($meta['l'])) {
        $meta['n'] = 10;
      }
      $this->setFontSize($meta['n'], $meta['l']);
    }
  }
  function setFontSize($sizeNote = 12.5, $sizeLyrics = 10)
  {
    $this->fontSizeNote = $sizeNote;
    $this->fontSizeLyrics = $sizeLyrics;
  }
  function setupSize($size = array())
  {
    $defaultSize = array(
      'pageWidth' => 210,
      'pageHeight' => 297,
      'canvasLeft' => 12
    );
    $defaultSize['canvasTop'] = $defaultSize['canvasLeft'];
    $defaultSize['canvasWidth'] = $defaultSize['pageWidth'] - 2 * $defaultSize['canvasLeft'];
    $defaultSize['canvasHeight'] = $defaultSize['pageHeight'] - 2 * $defaultSize['canvasTop'];
    $mergedSize = array_merge($defaultSize, $size);
    $this->pageWidth = $mergedSize['pageWidth'];
    $this->pageHeight = $mergedSize['pageHeight'];
    $this->canvasLeft = $mergedSize['canvasLeft'];
    $this->canvasWidth = $mergedSize['canvasWidth'];
    $this->canvasTop = $mergedSize['canvasTop'];
    $this->canvasHeight = $mergedSize['canvasHeight'];
  }
  function printSeparator($separator, $height)
  {
    $h = $this->fontHeight * $height;
    if ($separator == '|') {
      $this->line($this->getX(), $this->getY(), $this->getX(), $this->getY() + $h);
      return;
    }
    if ($separator == '/') {
      $this->line($this->getX(), $this->getY(), $this->getX(), $this->getY() + $h);
      $this->line($this->getX() + 0.51, $this->getY(), $this->getX() + 0.51, $this->getY() + $h);
      return;
    }
    if ('!' == $separator) {
      $separator = '|';
    }
    $sepRepeat = rtrim(str_repeat($separator . "\n", $height));
    $this->setFont('yan', '', $this->getFontSizeNote());
    $this->multiCell(4, 0, $sepRepeat, align: 'L', border: 0);
  }
  function setMultitext($text)
  {
    $this->text = $text;
  }
  function setFontSizeNote($fontSizeNote)
  {
    $this->fontSizeNote = $fontSizeNote;
  }
  function getFontSizeNote()
  {
    return $this->fontSizeNote;
  }
  function setFontSizeLyrics($fontSizeLyrics)
  {
    $this->fontSizeLyrics = $fontSizeLyrics;
  }
  function getFontSizeLyrics()
  {
    return $this->fontSizeLyrics;
  }
  function calcWidth($note, $lyrics)
  {
    $this->setFont('yan', '', $this->fontSizeNote);
    $width = 0;
    foreach (explode("\n", $note) as $oneNote) {
      $width = max($width, $this->getStringWidth($oneNote) + 1.2);
    }
    $oneNoteWidth = $width;
    $width = 0;
    $this->setFont('yan', '', $this->fontSizeLyrics);
    foreach (explode("\n", $lyrics) as $oneLyrics) {
      $width = max($width, $this->getStringWidth($oneLyrics) + 0.9);
    }
    $oneLyricsWidth = $width;
    return array($oneNoteWidth, $oneLyricsWidth, max($oneNoteWidth, $oneLyricsWidth));
  }
  function recalcWidth()
  {
    $nbBlocks = intval($this->canvasWidth / Block::$maxWidth);
    if ($nbBlocks > 0) {
      $this->blockWidth = $this->canvasWidth / $nbBlocks;
    } else {
      $this->blockWidth = $this->canvasWidth;
    }
  }
  // OverLoad
  function SetFont($family, $style = '', $size = 0)
  {
    parent::SetFont($family, $style, $size);
    $this->fontHeight = $size * 0.315;
  }
  function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
  {
    parent::MultiCell($w, $this->fontHeight, $txt, $border, $align);
  }
}
