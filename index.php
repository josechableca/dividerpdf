<?php

$DirApp = __DIR__;
$cadMd5ts = "";

use setasign\Fpdi\Fpdi;

require_once('setasign/fpdf/fpdf.php');
require_once('setasign/Fpdi/autoload.php');
require_once('setasign/fpdi_pdf-parser2/src/autoload.php');

date_default_timezone_set('America/Mexico_City');

$rut = chr(47); // chr(47)/ chr(92)\
$length = 5;

// directorios fijos archivo Listdir.txt
//$dir = LoadDir(); se cambia metodo para permitir la manipulacion desde una aplicacion
$dir = file($DirApp . '/Listdir.txt');

InicializaApp();
//iniciaLog();


//verificacion de errores 
$fileerr = file($DirApp . '/Lastresult.log');
$numitem = count($fileerr);
if ($numitem == 1) {
    $eserror = false;
} else {
    $eserror = true;
}
//revision de todas las rutas cargadas
foreach ($dir as $rutas) {
    $rutas2 = trim($rutas);
    if (strlen($rutas2) > 8) {
        list($tfile, $tdir, $nfle, $exnte, $ttal_P) = buscarpdf($rutas2, null, $eserror, $fileerr);
    }
}


/* -------------funcion recursiva----------------------------- */
function InicializaApp()
{
    $DirApp2 = $GLOBALS['DirApp'];
    //nombre de archivo log con base en la fecha y hora----------------------------------
    $hoy = time();
    //verificamos carpetas log, staticts, year
    $namecheck = $DirApp2 . '/log';

    if (!is_dir($namecheck)) {
        mkdir($namecheck, 0777);
    }

    $namecheck = $DirApp2 . '/statistics';

    if (!is_dir($namecheck)) {
        mkdir($namecheck, 0777);
    }

    $yearnum = date("Y", $hoy);
    $monthnum = date("m", $hoy);
    $namecheck = $DirApp2 . '/statistics/' . $yearnum;

    if (!is_dir($namecheck)) {
        mkdir($namecheck, 0777);
    }

    //verificamos los archivos 
    $namecheck = $DirApp2 . '/Lastresult.log';
    if (!file_exists($namecheck)) {
        $fileXerr = fopen($namecheck, "w");
        fwrite($fileXerr, 'OK' . PHP_EOL);
        fclose($fileXerr);
    }
    $namecheck = $DirApp2 . '/statistics/Reg' . $yearnum . '.log';
    if (!file_exists($namecheck)) {
        $nstamp = time();
        $fileXerr = fopen($namecheck, "w");
        $monthnum = date("m", $nstamp);
        fwrite($fileXerr, 'month|total file pdf|total file month|pdf separate' . PHP_EOL);
        $cadlinea = $monthnum . '|0|0|0';
        fwrite($fileXerr, $cadlinea . PHP_EOL);
        fclose($fileXerr);
    }

    $namecheck = $DirApp2 . '/statistics/' . $yearnum . '/month' . $monthnum . '.log';
    if (!file_exists($namecheck)) {
        $fileXerr = fopen($namecheck, "w");
        fwrite($fileXerr, 'date|total file|pdf process|pdf separate' . PHP_EOL);
        fwrite($fileXerr, date("d-m-Y", $hoy) . '|0|0|0' . PHP_EOL);
        fclose($fileXerr);
    }
}
function iniciaLog()
{
    $hoy = time();
    $namefile = date("Ymd_", $hoy);
    $namefiletxt = $GLOBALS["DirApp"] . '/log/' . $namefile . $hoy . '.txt';
    $fileLog = fopen($namefiletxt, "w");
    fwrite($fileLog, 'revision: ' . date("Y-m-d  H:m:s", $hoy) . PHP_EOL);
    //                123456789 12345678901234567890123456789012345678901234567890 12345 12345678901234567890123456 12345678901234567890123456
    fwrite($fileLog, 'Structure name                                               pages process start              finished' . PHP_EOL);
    fwrite($fileLog, '---------+--------------------------------------------------+-----+--------------------------+--------------------------' . PHP_EOL);
    fclose($fileLog);
    return $namefiletxt;
}

function buscarpdf($pathx, $namefiletxt, $es_error, $file_err)
{
    $afiles = array();
    $adir = array();
    //contadores
    $nfile = 0;
    $ndir = 0;
    $totalfile = 0;
    $totalpdf = 0;
    $total_P = 0;
    $totaldir = 0;
    $existente = 0;

    $length = $GLOBALS["length"];
    $procesar = true;

    // Arreglo con todos los nombres de los archivos
    $eldir = scandir($pathx);
    $files = array_diff($eldir, array('.', '..'));
    //si no se ha creado el archivo log, proceder a crear

    if (!$namefiletxt) {
        $namefiletxt = iniciaLog();
    }

    //guardamos la ruta en el archivo log
    $fileLog = fopen($namefiletxt, "a");
    fwrite($fileLog, espacios('[Ruta]', 9) . ' ' . espacios($pathx, 50) . ' ' . PHP_EOL);

    //accesamos al archivo de errores  y guardamos la ruta en caso error
    $fileXerr = fopen($GLOBALS["DirApp"] . '/Lastresult.log', "w");
    fwrite($fileXerr, $pathx . PHP_EOL);
    fclose($fileXerr);
    //checa si la ruta actual fue la ruta donde se produjo la iterrupcion, si hay error
    $checaerrpdf = false;
    $laruta = substr($file_err[0], 0, -1);
    if ($es_error) {
        if ($pathx == $laruta) {
            $checaerrpdf = true;
        }
    }

    //listamos los archivos que seha pdf, no contengan la clave _P y la lista de directorios
    foreach ($files as $file) {
        $procesar = true;
        $data = explode(".", $file);
        //si es un archivo
        if (is_file($pathx . '/' . $file)) {
            $totalfile++;
            $fileExtension = $data[1];
            //es un archivo pdf
            if (strtoupper($fileExtension) == 'PDF') {
                $totalpdf++;
                //busca si tiene la clave _P en el nombre de archivo
                $indexb = buscartexto($data[0], '_P');
                $continuar = true;
                //verificamos si es el archivo donde se produjo la interrupcion, lo anotamos para revision
                if ($checaerrpdf) {
                    $elarchivo = substr($file_err[1], 0, -1);
                    if ($file == $elarchivo) {
                        $procesar = true;
                        $continuar = false;
                    }
                }
                //continua la revision del tipo de archivo
                if ($continuar) {
                    //el nombre de archivo contiene _P
                    if ($indexb > 0) {
                        $largo = strlen($data[0]) - $indexb - 2;
                        //despues de la clave hay caracteres
                        if ($largo > 0 && $largo <= $length) {
                            //obtenemos la parte numerica
                            $part2 = substr($data[0], $indexb + 2, $largo);
                            $valpart2 = intval($part2);
                            //es un valor 1 a 99999
                            if ($valpart2 > 0 && $valpart2 < 100000) {
                                $total_P++;
                                $procesar = false;
                            }
                        }
                    } else {
                        //verificamos si el archivo ya fue dividido
                        $string1 = substr(str_repeat(0, $length) . '1', -$length);
                        $parte1 = $pathx . '/' . $data[0] . '_P' . $string1 . ".pdf";
                        $parte2 = $pathx . '/' . $data[0] . '_P1' . ".pdf";
                        //verificamos si existe un archivo con _P00001 o _P1
                        if (file_exists($parte1) || file_exists($parte2)) {
                            $procesar = false;
                            $existente++;
                        } else {
                            //anotar paa revision
                            $procesar = true;
                        }
                    }
                }
                //se anota la lista de archivos pdf para procesar
                if ($procesar) {
                    array_push($afiles, $file);
                    $nfile += 1;
                }
            }
        } else {
            //se anota la lista de directorios
            $totaldir++;
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

            $ejecuta = '[archivo] ' . espacios($file, 50) . ' ';

            //guardamos caso error la ruta, archivo
            $fileXerr = fopen($GLOBALS["DirApp"] . '/Lastresult.log', "w");
            fwrite($fileXerr, $pathx . PHP_EOL);
            fwrite($fileXerr, $file . PHP_EOL);
            fwrite($fileXerr, '0' . PHP_EOL);
            fclose($fileXerr);

            $old_pdf = $pathx . '/' . $file;
            $new_pdf = $pathx . '/' . $fileName . '_P';
            $part = 0;
            //verificamos si el archivo es el ultimo cuando se produco la interrupcion
            //cargamos la parte donde se quedo.
            if ($checaerrpdf) {
                $elarchivo = substr($file_err[1], 0, -1);
                if ($file == $elarchivo) {
                    $part = intval($file_err[2]);
                }
            }
            fwrite($fileLog, $ejecuta);
            //proceso DIVIDIR

            $resultado = dividerPdf($pathx, $new_pdf, $old_pdf, $part, $checaerrpdf);

            fwrite($fileLog, $resultado . PHP_EOL);
        }
        if ($ndir > 0) {
            fclose($fileLog);
        }
    } else {
        if ($nfile = 0) fclose($fileLog);

        foreach ($adir as $ruta) {
            list($tfile, $tdir, $nfle, $exnte, $ttal_P) = buscarpdf($pathx . "/" . $ruta, $namefiletxt, $es_error, $file_err);
            //contabilizamos
            $totalfile += $tfile;
            $totaldir += $tdir;
            $nfile += $nfle;
            $existente += $exnte;
            $total_P += $ttal_P;
        }
        $fileLog = fopen($namefiletxt, "a");
    }
    //guardar resultados y cerrar
    $lintxt = 'Total de archivos: ' . $totalfile . '  Directorios: ' . $totaldir . ' archivos pdf : (nuevos) ' . $nfile . ' (Existentes) ' . $existente . ' (de extraccion) ' . $total_P;
    fwrite($fileLog, $lintxt . PHP_EOL);
    fclose($fileLog);
    //guardamos si no ocurrio error 
    $fileXerr = fopen($GLOBALS["DirApp"] . '/Lastresult.log', "w");
    fwrite($fileXerr, 'OK' . PHP_EOL);
    fclose($fileXerr);
}

function espacios($eltexto, $numcar)
{
    $tmp = $eltexto;
    if ($numcar > 0) {
        $largo = strlen($eltexto);
        if ($largo < $numcar) {
            $falta = $numcar - $largo;
            $string1 = str_repeat(" ", $falta);
            $tmp = $eltexto . $string1;
        }
    }
    return $tmp;
}

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

function dividerPdf($path2, $pdfPath, $pdfAbsolutePath, $file_part, $es_ruta)
{
    $rut = $GLOBALS["rut"];
    $length = $GLOBALS["length"];

    $pdf = new Fpdi();
    $pagecount = $pdf->setSourceFile($pdfAbsolutePath);
    $inicial = 1;
    if ($es_ruta) {
        if ($file_part > 0) {
            $inicial = $file_part;
        }
    }

    $resp =  espacios($pagecount, 5) . ' ';
    if ($pagecount) {
        if ($pagecount > 1) {
            //almacnamos time stamp de inicio            
            //$nstamp = time();
            $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            $resp = $resp . espacios($formateado, 26) . ' ';

            //iniciamos registro del mes
            iniciaMes();
            iniciaYear();


            // Split each page into a new PDF
            for ($i = $inicial; $i <= $pagecount; $i++) {
                //guardamos parte caso error
                $fileXerr = fopen($GLOBALS["DirApp"] . '/Lastresult.log', "r+");
                fseek($fileXerr, 0, SEEK_END);
                fwrite($fileXerr, $i . PHP_EOL);
                fclose($fileXerr);

                $GLOBALS['cadMd5ts'] = "";
                $string = substr(str_repeat(0, $length) . $i, -$length);
                $new_filename2 = $pdfPath . $string . ".pdf";

                obtenerMD5($new_filename2, $i, $pdfAbsolutePath);
                //impresion de hoja

                $new_pdf = new Fpdi();
                $new_pdf->AddPage();
                $new_pdf->setSourceFile($pdfAbsolutePath);
                $new_pdf->useTemplate($new_pdf->importPage($i));

                try {
                    //agregamos MD5 + timespamt + hoja
                    $new_pdf->SetFont('Courier', '', '9');
                    $new_pdf->SetY(-21);
                    $new_pdf->Cell(0, 0, $GLOBALS['cadMd5ts']);
                    //guardamos
                    $new_pdf->Output($new_filename2, "F");
                    //registramos en el mes
                    RegistraMes('hoja', 1);
                    RegistraYear('hoja', 1);
                } catch (Exception $e) {
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                }
            }
            $fecha = new DateTime();
            //$nstamp2 = $fecha->getTimestamp();
            //$nstamp2 = time();
            $ahora = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
            $formateado = $ahora->format("Y-m-d H:i:s.u");
            //$difstamp = $nstamp2 - $nstamp;
            $resp = $resp . espacios($formateado, 26);
            RegistraMes('mes', 1);
            RegistraYear('mes', 1);
        } else $resp = '[archivo de una pagina]';
    } else $resp = '[Error-al abrir archivo]';
    return $resp;
}

function obtenerMD5($filenn, $i2, $pdfAbsolutePath2)
{

    //obtene la cadena resumen de la hoja de pdf
    $tem_pdf = new Fpdi();
    $tem_pdf->AddPage();
    $tem_pdf->setSourceFile($pdfAbsolutePath2);
    $tem_pdf->useTemplate($tem_pdf->importPage($i2));
    //obtenemos mdf5 y timestamp de la hoja
    $file_content = $tem_pdf->Output("S", $filenn, false);
    $md5f = md5($file_content);
    $GLOBALS['cadMd5ts'] = cadstamtime($md5f, $i2);
}


function RegistraMes($endonde, $num)
{
    $nstamp = time();
    $yearnum = date("Y", $nstamp);
    $monthnum = date("m", $nstamp);
    $listatxt = file($GLOBALS["DirApp"] . '/statistics/' . $yearnum . '/month' . $monthnum . '.log');
    $fileReg = fopen($GLOBALS["DirApp"] . '/statistics/' . $yearnum . '/month' . $monthnum . '.log', "r+");
    $pp = count($listatxt);

    for ($i = 1; $i < $pp; $i++) {
        $txt = fgets($fileReg);
    }
    $lineatxt = explode("|", $listatxt[$pp - 1]);
    if ($endonde == 'mes') {
        $totnum = intval($lineatxt[1]);
        $lineatxt[1] = $totnum + $num;
        $totmes = intval($lineatxt[2]);
        $lineatxt[2] = $totmes + $num;
        $lineatxt[3] = trim($lineatxt[3]);
    } else if ($endonde == 'hoja') {
        $tothh = intval($lineatxt[3]);
        $lineatxt[3] = $tothh + $num;
    }
    fwrite($fileReg, $lineatxt[0] . '|' . $lineatxt[1] . '|' . $lineatxt[2] . '|' . $lineatxt[3] . PHP_EOL);
    fclose($fileReg);
}


function iniciaMes()
{
    $nstamp = time();
    $yearnum = date("Y", $nstamp);
    $monthnum = date("m", $nstamp);
    $daydate = date("d-m-Y", $nstamp);
    $listatxt = file($GLOBALS["DirApp"] . '/statistics/' . $yearnum . '/month' . $monthnum . '.log');
    $fileReg = fopen($GLOBALS["DirApp"] . '/statistics/' . $yearnum . '/month' . $monthnum . '.log', "r+");
    $pp = count($listatxt);
    $txt = "";
    for ($i = 1; $i < $pp; $i++) {
        $txt = fgets($fileReg);
    }
    $lineatxt = explode("|", $listatxt[$pp - 1]);
    if ($lineatxt[0] != $daydate) {
        $totnum = 0;
        if ($lineatxt[0] != 'date') {
            $totnum = intval($lineatxt[1]);
        }
        $txt = fgets($fileReg);
        //incializamos contadores por dia
        fwrite($fileReg, $daydate . '|' . $totnum . '|0|0' . PHP_EOL);
    }
    fclose($fileReg);
}

function RegistraYear($endonde, $num)
{
    $nstamp = time();
    $yearnum = date("Y", $nstamp);
    $monthnum = date("m", $nstamp);
    $filemm = $GLOBALS["DirApp"] . '/statistics/Reg' . $yearnum . '.log';
    $listatxt = file($filemm);
    $fileReg = fopen($filemm, "r+");
    $pp = count($listatxt);

    for ($i = 1; $i < $pp; $i++) {
        $txt = fgets($fileReg);
    }
    $lineatxt = explode("|", $listatxt[$pp - 1]);
    if ($endonde == 'mes') {
        $totnum = intval($lineatxt[1]);
        $lineatxt[1] = $totnum + $num;
        $totmes = intval($lineatxt[2]);
        $lineatxt[2] = $totmes + $num;
        $lineatxt[3] = trim($lineatxt[3]);
    } else if ($endonde == 'hoja') {
        $tothh = intval($lineatxt[3]);
        $lineatxt[3] = $tothh + $num;
    }

    fwrite($fileReg, $lineatxt[0] . '|' . $lineatxt[1] . '|' . $lineatxt[2] . '|' . $lineatxt[3] . PHP_EOL);
    fclose($fileReg);
}

function iniciaYear()
{
    $nstamp = time();
    $yearnum = date("Y", $nstamp);
    $monthnum = date("m", $nstamp);

    if (intval($monthnum) > 1) {
        $filemm = $GLOBALS["DirApp"] . '/statistics/Reg' . $yearnum . '.log';
        $listatxt = file($filemm);
        $fileReg = fopen($filemm, "r+");

        $pp = count($listatxt);
        $txt = "";
        for ($i = 1; $i < $pp; $i++) {
            $txt = fgets($fileReg);
        }
        $lineatxt = explode("|", $listatxt[$pp - 1]);
        if ($lineatxt[0] != $monthnum) {
            $totnum = 0;
            if ($lineatxt[0] != 'month') {
                $totnum = intval($lineatxt[1]);
            }
            $txt = fgets($fileReg);
            //incializamos contadores por dia
            fwrite($fileReg, $monthnum . '|' . $totnum . '|0|0' . PHP_EOL);
        }
        fclose($fileReg);
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
    return $md5actual . '  ' . date("Y-m-d H:I:s", $hoy) . ' page ' . $hoja;
}

function LoadDir()
{
    $ListaDir = array();
    $filedir00 = fopen($GLOBALS["DirApp"] . "/Listdir.txt", "r");
    if ($filedir00) {
        while (!feof($filedir00)) {
            $linea = fgets($filedir00);
            array_push($ListaDir, $linea);
        }
    }
    fclose($filedir00);
    return $ListaDir;
}
