<?php

use setasign\Fpdi\Fpdi;

require_once('setasign/fpdf/fpdf.php');
require_once('setasign/Fpdi/autoload.php');
//require_once('setasign/Fpdi/Fpdi.php');
require_once('setasign/fpdi_pdf-parser2/src/autoload.php');


date_default_timezone_set('UTC');
//separador de ruta /  \
$rut = chr(47); // chr(47)/ chr(92)\
//numero de ceros en nombre 00000
$length = 5;

// directorios fijos
$dir = array(
    '/pruebas/separatePDF/carpetaA'
);

foreach ($dir as $rutas) {
    
    buscarpdf($rutas);
}

/* se usan los parametros de linea de comando
if ($argc > 1) {
    $Numinicial = 1;
    $primero = strtoupper($argv[0]);
    if ($primero == "PHP") {
        $Numinicial = 2;
    }
        
    for ($i = $Numinicial; $i <= $argc; $i++) {

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
    $procesar = true;

    $eldir = scandir("'".$path."'");

    // Arreglo con todos los nombres de los archivos
    $files = array_diff($eldir, array('.', '..'));
    echo 'Ruta: ' . $path . chr(10);
    //listamos los archivos que no contengan la clave _P00000 y a parte la lista de directorios
    foreach ($files as $file) {
        $procesar = true;
        $data = explode(".", $file);
        //si es un archivo
        if (is_file($path . '/' . $file)) {
            $fileExtension = $data[1];
            //es un archivo pdf
            if (strtoupper($fileExtension) == 'PDF') {
                //busca si tiene la clave _P
                $indexb = buscartexto($data[0], '_P');
                if ($indexb > 0) {
                    $largo = strlen($data[0]) - $indexb - 2;
                    //despues de la clave hay caracteres
                    if ($largo > 0 && $largo <= $length) {
                        //obtenemos la parte numerica
                        $part2 = substr($data[0], $indexb + 2, $largo);
                        $valpart2 = intval($part2);
                        //es un valor 1 a 99999
                        if ($valpart2 > 0 && $valpart2 < 100000) {
                            $procesar = false;
                        }
                    }
                } else {
                    $string1 = substr(str_repeat(0, $length) . '1', -$length);
                    $parte1 = $path . '/' . $data[0] . '_P' . $string1 . ".pdf";
                    $parte2 = $path . '/' . $data[0] . '_P1' . ".pdf";
                    if (file_exists($parte1) || file_exists($parte2)) {
                        $procesar = false;
                        echo $file . ' -Archivo ya procesado-' . chr(10);
                    } else {
                        $procesar = true;
                    }
                }

                if ($procesar) {
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
            $data = explode(".", $file);
            // Nombre del archivo
            $fileName = $data[0];
            $fileExtension = $data[1];

            echo $file . ' ';

            $old_pdf = $path . '/' . $file;
            $new_pdf = $path . '/' . $fileName . '_P';

            $oldpath = $new_pdf;

            /*verifica la version y genera un archivo convertido nombre_v14.pdf
            $newpath = verifyPdfVersion($oldpath);
            if ($newpath) {
                if ($newpath != $oldpath) {
                    $new_pdf = $newpath;
                    $eliminar= true;
                }
            }*/

            dividerPdf($path, $new_pdf, $old_pdf);
            
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

    $pdf = new Fpdi();
    $pagecount = $pdf->setSourceFile($pdfAbsolutePath);
    echo $pagecount . ' pág ';
    if ($pagecount) {
        if ($pagecount > 1) {
            $fecha = new DateTime();
            $nstamp = $fecha->getTimestamp();
            $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            echo 'Inicia division' . $formateado . ' -> ';
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
                    //$new_pdf->Output($new_filename, "F");
                    //obtenemos mdf5 y timestamp de la hoja
                    $file_content = $new_pdf->Output("S",$new_filename, false);
                    $md5f = md5($file_content);
                    $cadMd5ts = cadstamtime($md5f, $i);
                    //agregamos MD5 + timespamt + hoja
                    $new_pdf->SetFont('Arial', 'B', '11');
                    $new_pdf->SetXY(30,-15);
                    $new_pdf->Write(200,$cadMd5ts);
                    //guardamos
                    $new_pdf->Output($new_filename, "F");
                } catch (Exception $e) {
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                }
            }
            $fecha = new DateTime();
            $nstamp2 = $fecha->getTimestamp();
            $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            $difstamp = $nstamp2 - $nstamp;
            echo $formateado.' = '.$difstamp.chr(10); 
        }
    }
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

function cadstamtime($md5actual, $hoja)
{
    $hoy = time();
    return $md5actual.' '.date("Y-m-d H:I:s",$hoy).' page ['.$hoja.']';
}

?>
