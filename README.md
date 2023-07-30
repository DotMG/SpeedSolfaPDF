# SpeedSolfaPDF
Create a PDF document containing a Sol-Fa

## Requirements
SpeedSolfaPDF is a set of PHP scripts to convert a txt format of a Solfa into a PDF document. So, it just requires PHP. Version 7.2 or higher is advised. It depends on a [modified version](https://github.com/DotMG/tFPDF) of [tFPDF](https://github.com/setasign/tFPDF), and modified fonts based on YanoneKaffesatz-Light and FiraSans-Regular; but all these dependencies' installation is assured by composer.

## Installation
Just decompress the archive or git clone the repository into a folder of your choice. Make sur the parent folder is writeable. Then see the file [INSTALL](https://github.com/DotMG/SpeedSolfaPDF/wiki/Installation) to finish the installation of dependencies.

## Format
SpeedSolfaPDF invents a new format to speed up the creation of a Solfa PDF. The [specification of this format](https://github.com/DotMG/SpeedSolfaPDF/wiki/Le-format) is available in French in the wiki.

## Running
    php run.php [options] source-file.txt

available options are actually:

    -s : activate smartWidth. With smartWidth activated, SpeedSolfaPDF tries
       its best to align a line on double vertical lines or single long
       vertical lines (separator code / or | )
    --smart-width : the same as -s
