<?php
namespace DotMG\SpeedSolfaPDF;

class PDF extends \tFPDF
{
  private $text;
  private $fontSizeNote = 12.5;
  private $fontSizeLyrics = 10;
  public $block_width;
  public $font_height;
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
    $_default_size = array(
      'page_width' => 210,
      'page_height' => 297,
      'canvas_left' => 12
    );
    $_default_size['canvas_top'] = $_default_size['canvas_left'];
    $_default_size['canvas_width'] = $_default_size['page_width'] - 2 * $_default_size['canvas_left'];
    $_default_size['canvas_height'] = $_default_size['page_height'] - 2 * $_default_size['canvas_top'];
    $_merged_size = array_merge($_default_size, $size);
    $this->page_width = $_merged_size['page_width'];
    $this->page_height = $_merged_size['page_height'];
    $this->canvas_left = $_merged_size['canvas_left'];
    $this->canvas_width = $_merged_size['canvas_width'];
    $this->canvas_top = $_merged_size['canvas_top'];
    $this->canvas_height = $_merged_size['canvas_height'];
  }
  function printSeparator($separator, $height)
  {
    $h = $this->font_height * $height;
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
    $sep_repeat = rtrim(str_repeat($separator . "\n", $height));
    $this->setFont('yan', '', $this->getFontSizeNote());
    $this->multiCell(4, 0, $sep_repeat, align: 'L', border: 0);
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
    $_width = 0;
    foreach (explode("\n", $note) as $_note) {
      $_width = max($_width, $this->getStringWidth($_note));
    }
    $_note_width = $_width;
    $_width = 0;
    $this->setFont('yan', '', $this->fontSizeLyrics);
    foreach (explode("\n", $lyrics) as $_lyrics) {
      $_width = max($_width, $this->getStringWidth($_lyrics));
    }
    $_lyrics_width = $_width;
    return array($_note_width, $_lyrics_width, max($_note_width, $_lyrics_width));
  }
  function recalcWidth()
  {
    $_nb_blocks = intval($this->canvas_width / (Block::$max_width + 1.3));
    if ($_nb_blocks > 0) {
      $this->block_width = $this->canvas_width / $_nb_blocks;
    } else {
      $this->block_width = $this->canvas_width;
    }
  }
  // OverLoad
  function SetFont($family, $style = '', $size = 0)
  {
    parent::SetFont($family, $style, $size);
    $this->font_height = $size * 0.315;
  }
  function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
  {
    parent::MultiCell($w, $this->font_height, $txt, $border, $align);
  }
}
