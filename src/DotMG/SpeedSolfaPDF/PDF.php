<?php
namespace DotMG\SpeedSolfaPDF;

class PDF extends \tFPDF
{
  private $text;
  private $font_size_note = 12.5;
  private $font_size_lyrics = 10;
  public $block_width;
  public $font_height;
  function set_font_size($size_note = 12.5, $size_lyrics = 10)
  {
    $this->font_size_note = $size_note;
    $this->font_size_lyrics = $size_lyrics;
  }
  function setup_size($size = array())
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
  function print_separator($separator, $height)
  {
    $h = $this->font_height * $height;
    if ($separator == '|') {
      $this->Line($this->GetX(), $this->GetY(), $this->GetX(), $this->GetY() + $h);
      return;
    }
    if ($separator == '/') {
      $this->Line($this->GetX(), $this->GetY(), $this->GetX(), $this->GetY() + $h);
      $this->Line($this->GetX() + 0.51, $this->GetY(), $this->GetX() + 0.51, $this->GetY() + $h);
      return;
    }
    if ('!' == $separator) {
      $separator = '|';
    }
    $sep_repeat = rtrim(str_repeat($separator . "\n", $height));
    $this->SetFont('yan', '', $this->get_font_size_note());
    $this->MultiCell(4, 0, $sep_repeat, align: 'L', border: 0);
  }
  function __construct($meta = null, $orientation = 'P', $unit = 'mm', $size = 'A4')
  {
    parent::__construct($orientation, $unit, $size);
    $this->AddFont('yan', '', 'DotKaffeesatz-Light.ttf', true);
    $this->AddFont('fir', '', 'FiraDot-Regular.ttf', true);
    $this->AddPage($orientation);
    $this->SetAutoPageBreak(false);
    $this->SetCellMargin(0);
    if (isset($meta['n']) || isset($meta['l'])) {
      if (!isset($meta['n'])) {
        $meta['n'] = 12.5;
      }
      if (!isset($meta['l'])) {
        $meta['n'] = 10;
      }
      $this->set_font_size($meta['n'], $meta['l']);
    }
  }
  function set_multitext($text)
  {
    $this->text = $text;
  }
  function set_font_size_note($font_size_note)
  {
    $this->font_size_note = $font_size_note;
  }
  function get_font_size_note()
  {
    return $this->font_size_note;
  }
  function set_font_size_lyrics($font_size_lyrics)
  {
    $this->font_size_lyrics = $font_size_lyrics;
  }
  function get_font_size_lyrics()
  {
    return $this->font_size_lyrics;
  }
  function calc_width($note, $lyrics)
  {
    $this->SetFont('yan', '', $this->font_size_note);
    $_width = 0;
    foreach (explode("\n", $note) as $_note) {
      $_width = max($_width, $this->GetStringWidth($_note));
    }
    $_note_width = $_width;
    $_width = 0;
    $this->SetFont('yan', '', $this->font_size_lyrics);
    foreach (explode("\n", $lyrics) as $_lyrics) {
      $_width = max($_width, $this->GetStringWidth($_lyrics));
    }
    $_lyrics_width = $_width;
    return array($_note_width, $_lyrics_width, max($_note_width, $_lyrics_width));
  }
  function recalc_width()
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
