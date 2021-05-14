<?php

use setasign\Fpdi\Fpdi;

require_once('setasign/fpdf/fpdf.php');
require_once('setasign/Fpdi/autoload.php');
//require_once('setasign/Fpdi/Fpdi.php');
require_once('setasign/fpdi_pdf-parser2/src/autoload.php');

//$pdf = new /setasign/Fpdi/Fpdi();

date_default_timezone_set('UTC');
//separador de ruta /  \
$rut = chr(47); // chr(47)/ chr(92)\
//numero de ceros en nombre 00000
$length = 5;

// directorio base
$dir = array(
    '/pruebas/carpetaPHP17'
);

foreach ($dir as $paths) {

    buscarpdf($paths);
}

/*
if ($argc > 1) {
    $Numinicial = 1;
    $dir = $argv;
    
    for ($i = $Numinicial; $i <= $argc; $i++) { //each ($dir as $paths) {

        buscarpdf($argv[$i]);
    }
} else {
    print_r("Error. Debe indicar: php index.php ruta1 [ruta2] [ruat3] ...".chr(10).chr(10));
}
*/

/* ------------------------ */
function buscartexto($origen, $texto)
{
    //busca un texto de derecha a izquierda
    $comienzo = strlen($origen);
    $ncar = strlen($texto);
    $salida = -1;
    $comienzo = $comienzo - $ncar;

    for ($i = $comienzo; $i >= 0; $i--) {
        $parte = substr($origen, $i, $ncar);

        if ($parte == $texto) {
            $salida = $i;
            return $salida;
        }
    }
    return $salida;
}


function buscarpdf($path)
{

    $afiles = array();
    $adir = array();
    $nfile = 0;
    $ndir = 0;
    $length = $GLOBALS["length"];
    $noprocesado = false;
    $elimnar = false;

    // Arreglo con todos los nombres de los archivos
    $files = array_diff(scandir($path), array('.', '..'));
    echo 'Ruta: ' . $path . '<br>' . chr(10);
    //listamos los archivos que no contengan la clave _P00000 y a parte la lista de directorios
    foreach ($files as $file) {
        $noprocesado = false;
        $data = explode(".", $file);
        //si es un archivo
        if (is_file($path . '/' . $file)) {
            $fileExtension = $data[1];
            //que sea un pdf
            if (strtoupper($fileExtension) == 'PDF') {
                $numfin = $length + 2;
                $final = substr($data[0], -$numfin);
                $part1 = substr($final, 0, 2);
                $part2 = substr($final, -$length);
                $valpart2 = intval($part2);
                $indexb = buscartexto($data[0], '_P');
                if ($indexb > 0) {
                    $largo = strlen($data[0]) - $indexb - 2;
                    if ($largo > 0 && $largo <= $length) {
                        $part2 = substr($data[0], $indexb + 2, $largo);
                        $valpart2 = intval($part2);
                        if ($valpart2 > 0 && $valpart2 < 100000) {
                            $string1 = substr(str_repeat(0, $length) . '1', -$length);
                            $parte1 = $path . '/' . $data[0] . '_P' . $string1 . ".pdf";
                            $parte2 = $path . '/' . $data[0] . '_P1' . ".pdf";
                            if (file_exists($parte1) || file_exists($parte2)) {
                                $noprocesado = false;
                            } else {
                                $noprocesado = true;
                            }
                        } else {
                            $noprocesado = true;
                        }
                    } else {
                        $noprocesado = true;
                    }
                } else {
                    $noprocesado = true;
                }
                //no tiene la clave _P00000
                //if (!($part1 == '_P' && ($valpart2 > 0 && $valpart2 < 100000))) {
                if ($noprocesado) {
                    array_push($afiles, $file);
                    $nfile += 1;
                }
            }
        } else {
            array_push($adir, $file);
            $ndir += 1;
        }
    }

    //procesamos primero los archivos del directorio
    if ($nfile > 0) {
        foreach ($afiles as $file) {
            $eliminar = false;
            // Divides en dos el nombre de tu archivo utilizando el .
            $data = explode(".", $file);
            // Nombre del archivo
            $fileName = $data[0];
            $fileExtension = $data[1];

            echo $file . '  ';

            /*$old_pdf = $fileName;
            $new_pdf = $path . '/' . $file;*/

            $old_pdf = $path . '/' . $file;
            $new_pdf = $path . '/' . $fileName . '_P';

            $oldpath = $new_pdf;

            /*            $newpath = verifyPdfVersion($oldpath);
            if ($newpath) {
                if ($newpath != $oldpath) {
                    $new_pdf = $newpath;
                    $eliminar= true;
                }
            }*/

            $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            echo 'Inicia division' . $formateado . ' -> ';

            dividerPdf($path, $new_pdf, $old_pdf);

            /*if ($eliminar) {
                shell_exec('rm '.chr(39).$newpath.chr(39));
            }*/

            $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            echo $formateado; //date("Y-m-d H:i:s.u"); 
            echo chr(10);
        }
    } else {
        foreach ($adir as $ruta) {
            buscarpdf($path . "/" . $ruta);
        }
    }
}

function dividerPdf($path2, $pdfPath, $pdfAbsolutePath)
{
    $rut = $GLOBALS["rut"];
    $length = $GLOBALS["length"];
    
    $string1 = substr(str_repeat(0, $length) . '1', -$length);
    $new_filename2 = $pdfPath . $string1 . ".pdf";  //_P00001

    
    $paso = file_exists($new_filename2);

    if ($paso) {
        echo '-Archivo ya procesado-';
    } else {

        $pdf = new Fpdi();
        $pagecount = $pdf->setSourceFile($pdfAbsolutePath);

        if ($pagecount) {
            if ($pagecount > 1) {
                // Split each page into a new PDF
                for ($i = 1; $i <= $pagecount; $i++) {
                    $string = substr(str_repeat(0, $length) . $i, -$length);
                    $new_filename2 = $pdfPath . $string . ".pdf";
                    $new_pdf = new Fpdi();
                    $new_pdf->AddPage();
                    $new_pdf->setSourceFile($pdfAbsolutePath);
                    $new_pdf->useTemplate($new_pdf->importPage($i));
                    try {
                        $new_filename = $new_filename2; 
                        $new_pdf->Output($new_filename, "F");
                    } catch (Exception $e) {
                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                    }
                }
            }
        }
    }
    //}
}

function verifyPdfVersion($pdfPath)
{
    try {
        $path_parts = pathinfo($pdfPath);

        $pdfVersion = getPdfVersion($pdfPath);

        if (is_null($pdfVersion))
            return null;

        $newPdfVersionPath = $path_parts["dirname"] . "/" . $path_parts["filename"] . "_v14." . $path_parts["extension"];

        // compare that number from 1.4(if greater than proceed with ghostscript)
        if ($pdfVersion > "1.4") {

            if (is_null(isActiveGS())) {
                //$this->log->error("::GS is disabled::");

                return null;
            }

            //if (!file_exists(dirname($newPdfVersionPath)))

                //mkdir(dirname($newPdfVersionPath), 0777, true);

                // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW LOCATION
                //logger('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile="' . $newPdfVersionPath . '" "' . $pdfPath . '"');


                $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            echo 'conversion ' . $formateado . ' --> ';

            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=' . chr(39) . $newPdfVersionPath . chr(39) . ' ' . chr(39) . $pdfPath . chr(39));

            $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            echo $formateado . ' ' . '<br>' . chr(10);

            if (file_exists($newPdfVersionPath)) {
                return $newPdfVersionPath;
            } else {
                //$this->log->error("GS command not worked");
                return null;
            }
        }

        return $pdfPath;
    } catch (\Exception $e) {
        //$this->log->error(_METHOD_ . " " . $e->getMessage() . " " . $e->getTraceAsString());
        return null;
    }
}

function getPdfVersion($pathPdf)
{
    try {
        // read pdf file first line because pdf first line contains
        // pdf version information

        $filepdf = fopen(chr(39) . $pathPdf . chr(39), "r");

        if ($filepdf) {
            $line_first = fgets($filepdf);
            fclose($filepdf);
        } else {
            return null;
        }

        // extract number such as 1.4 ,1.5 from first read line of pdf file
        preg_match_all('!\d+!', $line_first, $matches);

        // save that number in a variable
        $pdfversion = implode('.', $matches[0]);

        return $pdfversion;
    } catch (\Exception $e) {
        //$this->log->error(_METHOD_ . " " . $e->getMessage());

        return null;
    }
}

function isActiveGS()
{
    if (is_null(shell_exec("gs -v")))

        putenv('PATH=/usr/local/bin'); //uncomment this Works only in MAC

    return shell_exec("gs -v");
}


?>
