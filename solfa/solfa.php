<?php
require("../tfpdf/tfpdf.php");
class MyTFPDF extends tFPDF
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
    $this->AddFont('yan', '', 'YanoneDot-Light.ttf', true);
    $this->AddFont('fir', '', 'FiraDot-Regular.ttf', true);
    $this->AddPage($orientation);
    $this->SetAutoPageBreak(false);
    $this->SetCMargin(0);
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
    if (sizeof($this->note_mark[$i]) == 1) {
      $return = array_values($this->note_mark[$i]);
      return $return[0];
    }
    return $this->note_mark[$i];
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
      $_formatted = vsprintf($_format, $note);
      $_formatted = str_replace('-.-', '-', $_formatted);
      $_formatted = str_replace(
        array('D', 'R', 'F', 'S', 'T'),
        array('di', 'ri', 'fi', 'si', 'ta'),
        $_formatted
      );
      $_formatted = str_replace('.-)', ')', $_formatted);
      $_formatted = preg_replace('/\((.)\)/', '\1', $_formatted);
      $_formatted = preg_replace('/\.,-$/', '', $_formatted);
      if (preg_match('/^\((.*)\)$/', $_formatted, $_match)) {
        $_formatted = $_match[1];
        $_underlined[$i] = array(array('(', ')'));
      }
      if (preg_match('/[\(\)]/', $_formatted)) {
        print_r($note);
        print_r($_formatted);
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
    $_pdf = new MyTFPDF($this->meta);
    list($this->note_width, $_lyrics_width, $_min_width) = $_pdf->calc_width($this->note_string, $this->lyrics_string);
    $this->width = $_min_width;
    Block::$max_width = max($_min_width, Block::$max_width);
    return $_min_width;
  }
}
class Solfa
{
  var $file_data = array();
  var $meta = array();
  var $note = array();
  var $template = array();
  var $lyrics = array();
  var $i_block = 0;
  var $i_note = 0;
  var $i_lyrics = 0;
  var $marker;
  var $lyricsline = 0;
  function __construct($txt_file = 'samples/solfa-60.txt')
  {
    $_file_data = file($txt_file); //@todo error handling
    array_walk($_file_data, array($this, 'parse_all_lines'));
    $this->load_meta();
    $this->load_separators();
    $this->load_notes();
    $this->load_all_lyrics();
    $this->load_note_template();
    $this->setup_blocks();
    #$this->calc_size();
  } //fun __construct
  function parse_all_lines($line)
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
      $this->file_data[$_key][$_index] = rtrim($_val);
    }
  }
  function debug()
  {
    print_r($this);
  }
  function load_meta()
  {
    $_txt_meta_line = $this->file_data['M'][0];
    $_meta_item_array = preg_split('/\|(?=[a-z]:)/', $_txt_meta_line);
    array_walk($_meta_item_array, array($this, 'get_meta'));
  }
  function get_meta($meta_item)
  {
    /* $_a_key_abbrev = array(
      'a' => 'author',
      'c' => 'tonality', 
      'h' => 'composer',
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
  function load_separators()
  {
    $_separator_line = trim($this->file_data['S'][0]);
    $this->separators = str_split($_separator_line);
  }
  function load_note_template()
  {
    $_note_template = $this->file_data['T'][0];
    $is_on_paren = false;
    $_template_notes = '';
    $_note_marker    = '';
    /* Markers are :
     * $< or $> : marks starting of < or >
     * $= : marks end of < or >
     * $Q : point d'orgue
     */
    foreach (str_split($_note_template) as $_note_symbol) {
      $this->marker = array();
      if ($_note_marker != '') {
        $_note_marker .= $_note_symbol;
        $this->marker[] = $_note_marker;
        $_note_marker = '';
        continue;
      }
      if (in_array($_note_symbol, $this->separators)) {
        $this->next_note($_template_notes, $_note_symbol);
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
      $this->next_note($_template_notes);
    }
  }
  function next_note($template_note, $separator = '')
  {
    $_new_Block = new Block($template_note, $separator, $this->marker, $this->meta);
    list($_sub_note, $_sub_mark) = $this->get_sub_notes($_new_Block->get_nb_note());
    $_new_Block->set_note($_sub_note);
    if ($_sub_mark) {
      $_new_Block->set_mark($_sub_mark);
    }
    $_sub_lyrics = $this->get_sub_lyrics($_new_Block->get_nb_lyrics());
    $_new_Block->set_lyrics($_sub_lyrics);
    $this->template[$this->i_block] = $_new_Block;
    if ('/' == $separator) {
      $this->lyricsline++;
      $this->i_lyrics = 0;
    }
    $this->i_block++;
  }
  function load_notes()
  {
    foreach ($this->file_data['N'] as $_index => $notes) {
      $this->load_note($notes, $_index);
    }
  }
  function load_note($notes, $_index)
  {
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
  function load_all_lyrics()
  {
    foreach ($this->file_data['L'] as $_index => $lyrics) {
      $this->load_one_lyrics($lyrics, $_index);
    }
  }
  function load_one_lyrics($lyrics, $_index)
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
  function setup_blocks()
  {
  }
  function get_sub_notes($nb_note)
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
  function get_sub_lyrics($nb_lyrics)
  {
    if (0 == $nb_lyrics) {
      return array_fill(1, sizeof($this->lyrics), '');
    }
    $_sub_lyrics = array();
    foreach ($this->lyrics as $_i_lyrics => $_lyrics) {
      $_sub_lyrics[$_i_lyrics] = join('', array_slice($_lyrics[$this->lyricsline], $this->i_lyrics, $nb_lyrics));
    }
    $this->i_lyrics += $nb_lyrics;
    return $_sub_lyrics;
  }
  function render_pdf()
  {
    //@todo : 
    $pdf = new MyTFPDF($this->meta);
    $pdf->setup_size();
    $pdf->recalc_width();
    $x = $pdf->canvas_left;
    $y = $pdf->canvas_top;
    // ecriture entete
    //title 
    $pdf->SetXY($x, $y);
    $pdf->SetFont('yan', '', $pdf->get_font_size_note()+6);
    $pdf->Cell($pdf->canvas_width, $pdf->font_height, $this->meta['t'], align: 'C');
    $y = $pdf->GetY() + $pdf->font_height * 1.5;
    // author
    $pdf->SetXY($x, $y);
    $pdf->SetFont('yan', '', $pdf->get_font_size_lyrics()+2);
    $pdf->Cell($pdf->canvas_width, $pdf->font_height, $this->meta['h'], align: 'R');
    $pdf->SetXY($x, $y);
    $pdf->MultiCell($pdf->canvas_width / 2, $pdf->font_height, $this->meta['a'], align: 'L');
    //
    $y = $pdf->GetY() + $pdf->font_height;
    //tonalite + rythme
    $pdf->SetFont('fir', '', $pdf->get_font_size_lyrics());
    $tonalite_rythme = 'DO dia ' . $this->meta["c"] . '       ' . $this->meta['m'];
    $pdf->SetXY($x, $y);
    $pdf->Cell($pdf->GetStringWidth($tonalite_rythme), $pdf->font_height, $tonalite_rythme, align: 'L');
    // speed 
    $pdf->SetXY($x, $y);
    $pdf->Cell($pdf->canvas_width, $pdf->font_height, $this->meta['r'], ln: 0, align: 'C');

    $y = $pdf->GetY() + $pdf->font_height * 2;

    $pdf->SetXY($x, $y);
    $mark = array();
    foreach ($this->template as $_block) {
      if (is_array($_block->marker) && sizeof($_block->marker) > 0) {
        foreach ($_block->marker as $_marker) {
          if ($_marker == '$Q') {
            $pdf->SetXY($x, $y - $pdf->font_height);
            $pdf->SetFont('fir', '', $pdf->get_font_size_lyrics());
            $pdf->Cell($pdf->block_width, $pdf->font_height, "Ͼ", align: 'C');
          }
        }
      }
      $pdf->SetXY($x, $y);
      $pdf->SetFont('yan', '', $pdf->get_font_size_note());
      $pdf->MultiCell($pdf->block_width, 0, $_block->note_string, align: 'C');
      foreach (range(1, sizeof($this->note)) as $ln) {
        $nextx = $x + $pdf->block_width;
        $ln_y = $pdf->getY() + $pdf->font_height * $ln - $pdf->font_height * 4 - $pdf->font_height / 16;
        $_mark = $_block->get_mark($ln);
        if (in_array('(', $_mark)) {
          $mark[$ln] = array('x' => $x + ($pdf->block_width - $_block->note_width) / 2, 'y' => $ln_y);
        }
        if (in_array(')', $_mark)) {
          $nextx = $nextx - ($pdf->block_width - $_block->note_width) / 2;
          if ($ln_y != $mark[$ln]['y']) {
            $pdf->Line($mark[$ln]['x'], $mark[$ln]['y'], $pdf->canvas_left + $pdf->canvas_width, $mark[$ln]['y']);
            $pdf->Line($pdf->canvas_left, $ln_y, $nextx, $ln_y);
          } else {
            $pdf->Line($mark[$ln]['x'], $ln_y, $nextx, $ln_y);
          }
        }
      }
      $delta_y = 0.4 + $pdf->font_height * $_block->get_note_height();
      $pdf->SetXY($x, $y + $delta_y);
      $pdf->SetFont('yan', '', $pdf->get_font_size_lyrics());
      $pdf->MultiCell($pdf->block_width, 0, $_block->lyrics_string, align: 'C');
      $x += $pdf->block_width;
      $pdf->SetXY($x, $y);
      $pdf->SetFont('yan', '', $pdf->get_font_size_note());
      $pdf->print_separator($_block->separator, $_block->get_note_height());
      $pdf->SetFont('yan', '', $pdf->get_font_size_lyrics());
      if ($x >= 0 * $pdf->canvas_left + $pdf->canvas_width) {
        $x = $pdf->canvas_left;
        $delta_y += $pdf->font_height * ($_block->get_lyrics_height() + 0.7);
        $y += $delta_y;
        if ($y >= $pdf->canvas_top + $pdf->canvas_height - $delta_y) {
          $y = $pdf->canvas_top;
          $pdf->AddPage();
        }
      }
    }
    $pdf->Output('F', 'pdfsolfa2.pdf');
  }
} //class Solfa
if (is_array($argv) && !empty($argv[1])) {
  $solfa = new Solfa($argv[1]);
} else {
  $solfa = new Solfa();
}
$solfa->render_pdf();
#$solfa->debug();
