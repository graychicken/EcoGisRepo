<?php

/****************************************************************/
/*                                                              */
/*  SimplePhoto v1.0.1     29/03/2006                           */
/*                                                              */
/*  1.0.0 Versione base (04/10/2005)                            */
/*  1.0.1 Gestisce la lingua (29/03/2006)                       */
/*  1.0.2 Works with E_STRICT (05/10/2007)                      */
/*  1.0.3 add new function for pPhotoElement: getExt (18/03/08) */
/*  1.0.4 Bug Fix GetPhotoElement E_STRICT (21/04/08)           */
/*  1.0.5 No thumb directory outside of file directories        */
/*  1.0.6 Bug Fix: AdjPhotoName ext-length > 4                  */
/*  1.0.7 Add File Size                              */
/*                                                              */
/****************************************************************/

if (defined("__SIMPLEPHOTO_PHP__")) return;
define("__SIMPLEPHOTO_PHP__", 1);

define("PHOTO_THUMB_DIR", 'thumb');       // Nome cartella anteprima
define("PHOTO_THUMB_PREFIX", 'thumb_');   // prefisso anteprima
define("PHOTO_DIR_LIMIT", 1000);          // Numero massimo di foto per cartella
define("PHOTO_GIF_SUPPORT", true);       // Se true abilita il supporto GIF

function getPhotoHeader() {
    global $smarty;
    return $smarty->fetch('photo_header.tpl');
}

class pPhotoElement {
    public $Name;          // Nome reale della foto (passato in GetPhotoElement)
    public $File;          // Nome foto grande completo
    public $URL;           // URL foto grande
    public $Width;         // Lunghezza foto
    public $Height;        // Altezza foto
    public $PrevFile;      // Nome anteprima completo
    public $PrevURL;       // URL anteprima
    public $PrevWidth;     // Lunghezza anteprima
    public $PrevHeight;    // Altezza anteprima
    public $FileSize;      // File Size

    public function getPrevHTML($descr, $path, $id, $elemID=1, $tpl='photo_preview.tpl', $subpath='') {
        global $smarty, $lbl;

        // if (!file_exists($this->PrevFile)) return '';

        $vlu = array();

        $vlu['descr'] = $descr;
        $vlu['path'] = $path;
        $vlu['subpath'] = $subpath;
        $vlu['id']   = $id;
        $vlu['ext']  = substr(strrchr($this->PrevFile, '.'), 1);

        $vlu['elemID']  = $elemID;

        $smarty->append('lbl', $lbl);
        $smarty->assign('vlu', $vlu);
        $smarty->assign('random', md5(microtime(true) + rand(0, 65535)));

        return $smarty->fetch($tpl);
    }

    /**
     * get current file extension
     * @since 1.0.3
     *
     * @return file extension
     */
    public function getExt() {
        return substr(strrchr($this->File, '.'), 1);
    }

}

class pSimplePhoto {
    public $BasePath;  // Percorso di base
    public $Path;      // Percorso sotto cache
    public $BaseURL;   // URL di base
    public $URL;       // URL
    public $Name;      // Nome
    public $PrevWidth;
    public $PrevHeight;
    public $maxWidth;
    public $maxHeight;

    public function __construct($Path, $URL, $PrevWidth=0, $PrevHeight=0, $Name = '') {
        $this->BasePath = $Path;
        if ($Name == '')
            $this->Path = $Path;
        else {
            $this->Path = $Path . $Name . '/';
            if (!file_exists($this->Path))
                mkdir($this->Path);
        }
        $this->BaseURL = $URL;
        if ($Name == '')  $this->URL = $URL;
        else              $this->URL = $URL . $Name . '/';
// DD 1.0.5: Do not need this directory thumb
//    if (!file_exists($this->Path . PHOTO_THUMB_DIR))
//      mkdir($this->Path . PHOTO_THUMB_DIR);

        $this->Name = $Name;
        $this->PrevWidth = $PrevWidth;
        $this->PrevHeight = $PrevHeight;
    }

    // PrevType: 0: Se esiste prende l'immagine piccola, altrimenti l'immagine grande
    //           1: Se non esiste l'immagine piccola la crea e la restituisce
    //          -1: Se non esiste non la restituisce
    public function GetPhotoElement($id, $name, $PrevType=0, $lang='') {
        $Path = $this->Path . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/';
        $PrevPath = $this->Path . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/';
        if ($lang != '') $lang = "_$lang";
        $ext = strtolower(strrchr($name, '.'));
        $Name = $Path . sprintf('%08d', $id) . $lang . $ext;
        $PrevName = $PrevPath . PHOTO_THUMB_PREFIX . sprintf('%08d', $id) . $lang . $ext;
        if (file_exists($Name)) {
            $PE = new pPhotoElement();
            $PE->Name = $name;
            $size = GetImageSize($Name);
            $PE->File = $Name;
            $PE->URL = $this->URL . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . sprintf('%08d', $id) . $lang . $ext;
            $PE->Width = $size[0];
            $PE->Height = $size[1];
            $PE->FileSize = filesize($PE->File);
            if (file_exists($PrevName)) {
                $size = GetImageSize($PrevName);
                $PE->PrevFile = $PrevName;
                $PE->PrevURL = $this->URL . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/' . PHOTO_THUMB_PREFIX . sprintf('%08d', $id) . $lang . $ext;
                $PE->PrevWidth = $size[0];
                $PE->PrevHeight = $size[1];
            } else {
                if ($PrevType == 0) {
                    // Prende il file grande, ma correggie width / height
                    $PE->PrevFile = $PE->Name;
                    $PE->PrevURL = $PE->URL;
                    // if (!$AlwaysResize && $SrcInfo[0] <= $NewWidth && $SrcInfo[1] <= $NewHeight) {
                    $PE->PrevWidth = $PE->Width;
                    $PE->PrevHeight = $PE->Height;
                    // } else {
                    // $RatioWidth = $PE->Width / $this->PrevWidth;
                    // $RatioHeight = $PE->Height / $this->PrevHeight;
                    // if($RatioWidth < $RatioHeight) {
                    // $PE->PrevWidth = $PE->Width / $RatioHeight;
                    // $PE->PrevHeight = $NewHeight;
                    // } else {
                    // $PE->PrevWidth = $NewWidth;
                    // $PE->PrevHeight = $PE->Height / $RatioWidth;
                    // }
                    // }
                } else if ($PrevType == 1) {
                    // Ricrea l'anteprima
                    $rv = $this->CreateThumb($PE->File, $PrevName, $this->PrevWidth, $this->PrevHeight);
                    if ($rv === 0) {
                        $PE->PrevFile = $PrevName;
                        $PE->PrevURL = $this->URL . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/' . PHOTO_THUMB_PREFIX . sprintf('%08d', $id) . $lang . $ext;
                        $PE->PrevWidth = $this->PrevWidth;
                        $PE->PrevHeight = $this->PrevHeight;
                    } else {
                        echo "Could not create preview image, return value = $rv";
                    }
                }
            }
            return $PE;
        }

        return null;
    }

    // Alimina le foto
    public function DeletePhoto($id, $name='', $lang='') {
        $exts = array();
        $Path = $this->Path . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/';
        $PrevPath = $this->Path . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/';
        $ext = strtolower(strrchr($name, '.'));
        if ($ext == '') {
            $exts[] = '.jpg';
            $exts[] = '.png';
            $exts[] = '.gif';
        } else
            $exts[] = $ext;

        for ($cont = 0; $cont < count($exts); $cont++) {
            if ($lang != '') $lang = "_$lang";
            $PrevName = $PrevPath . PHOTO_THUMB_PREFIX . sprintf('%08d', $id) . $lang . $exts[$cont];
            if (file_exists($PrevName))
                unlink($PrevName);
            $Name = $Path . sprintf('%08d', $id) . $lang . $exts[$cont];
            if (file_exists($Name))
                unlink($Name);
        }
    }

    public function AddPhoto($id, $src, $ext='', $move=false, $lang='') {
        if (is_readable($src)) {
            $Path = $this->Path . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/';
            if (!file_exists($Path)) {
                if (($rv = mkdir($Path)) === FALSE) {
                    echo "could not mkdir($Path)";
                }
            }
            $PrevPath = $this->Path . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/';
            if (!file_exists($PrevPath))
                mkdir($PrevPath);
            if ($ext == '')  $ext = strtolower(strrchr($src, '.'));
            if ($lang != '') $lang = "_$lang";
            $Name = $Path . sprintf('%08d', $id) . $lang . $ext;
            $PrevName = $PrevPath . PHOTO_THUMB_PREFIX . sprintf('%08d', $id) . $lang . $ext;
            if ($move)  $rv = rename($src, $Name);
            else        $rv = copy($src, $Name);
            if ($rv === FALSE) {
                echo "could not ".($move?'move':'copy')." from $src to $Name";
                return NULL;
            }
            $PE = new pPhotoElement();
            $PE->Name = $Name;
            $size = GetImageSize($Name);

            if ($this->maxWidth > 0 && $this->maxHeight > 0) {
                if ($size[0] > $this->maxWidth || $size[1] > $this->maxHeight) {
                    $rv = $this->CreateThumb($Name, $Name, $this->maxWidth, $this->maxHeight);
                    if ($rv !== 0) {
                        echo "Could not create preview image, return value = $rv";
                    }
                }
            }

            $PE->File = $Name;
            $PE->URL = $this->URL . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . sprintf('%08d', $id) . $ext;
            $PE->Width = $size[0];
            $PE->Height = $size[1];
            $PE->FileSize = filesize($PE->File);
            // Calcolo anteprima
            if ($this->PrevWidth > 0 && $this->PrevHeight > 0) {
                $rv = $this->CreateThumb($Name, $PrevName, $this->PrevWidth, $this->PrevHeight);
                if ($rv === 0) {
                    $PE->PrevFile = $PrevName;
                    $PE->PrevURL = $this->URL . sprintf('%08d', $id / PHOTO_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/' . PHOTO_THUMB_PREFIX . sprintf('%08d', $id) . $ext;
                    $PE->PrevWidth = $this->PrevWidth;
                    $PE->PrevHeight = $this->PrevHeight;
                } else {
                    echo "Could not create preview image, return value = $rv";
                }
            }
            return $PE;
        }
        return null;
    }

    public function AddPhotoFromUpload($id, $uploadname, $lang='') {

        if (is_array($uploadname)) $upload = $uploadname;
        else                       $upload = $_FILES[$uploadname];

        return $this->AddPhoto($id, $upload['tmp_name'], $this->GetImageExt($upload['name'], true), false, $lang);
    }

    // Restituisce l'estensione dell'immagine (valori possibili: .gif, .jpg, .png o vuoto)
    static public function GetImageExt($name) {
        $ext = strtolower(strrchr($name, '.'));
        if ($ext == '.jpeg')  $ext = '.jpg';
        if ($ext == '.gif' || $ext == '.jpg' || $ext == '.png')  return $ext;
        return null;
    }

    // Restituisce il nome della foto senza percorso e mettendo sempre l'estensione (.gif, .jpg, .png)
    static public function AdjPhotoName($name, $maxlen=-1) {
        if (defined('R3_APP_CHARSET_DB'))
            $charset = R3_APP_CHARSET_DB;
        else
            $charset = "ISO-8859-1";

        if (function_exists('mb_strrchr')) {
            $ext = mb_strtolower(mb_strrchr($name, '.', false, $charset), $charset);
        } else { // For php < 5.2
            $ext = mb_strtolower(strrchr($name, '.'), $charset);
        }
        $name = mb_substr($name, 0, mb_strlen($name, $charset) - mb_strlen($ext, $charset), $charset);
        if ($ext == '.jpeg')  $ext = '.jpg';
        if ($ext == '.gif' || $ext == '.jpg' || $ext == '.png') {
            if (mb_strpos($name, '/', 0, $charset) !== false) {
                if (function_exists('mb_strrchr')) {
                    $name = mb_substr(mb_strrchr($name, '/', false, $charset), 1, mb_strlen($name, $charset), $charset);
                } else { // For php < 5.2
                    $name = mb_substr(strrchr($name, '/'), 1, mb_strlen($name, $charset), $charset);
                }
            }
            if ($maxlen > 0)
                return mb_substr($name, 0, $maxlen - mb_strlen($ext, $charset), $charset) . $ext;
            else
                return $name . $ext;
        } else
            return null;
    }

    // Verifica se l'upload dell'immagine � ok
    static public function UploadOk($uploadname) {
        if (is_array($uploadname))
            $upload = $uploadname;
        else if (isset($_FILES[$uploadname]))
            $upload = $_FILES[$uploadname];
        else
            return false;
        if (pSimplePhoto::GetImageExt($upload['name']) && $upload['error'] == 0 && $upload['size'] > 0)
            return true;
        return false;
    }

    /**
     * Create minified image
     * 
     * @param type $SrcName
     * @param type $DestName
     * @param type $NewWidth
     * @param type $NewHeight
     * @param type $MaintainAspectRatio
     * @param type $AlwaysResize
     * @param type $Progressive
     * @return int 0 for correct execution
     */
    static public function CreateThumb($SrcName, $DestName, $NewWidth, $NewHeight, $MaintainAspectRatio = true, $AlwaysResize = false, $Progressive = true) {

        if ($NewWidth <= 0 || $NewHeight <= 0)  return -9;            // Invalid image size
        $SrcInfo = GetImageSize($SrcName);
        switch ($SrcInfo[2]) {
            case IMAGETYPE_GIF:  $SrcImage = ImageCreateFromGIF($SrcName);
                break;   // GIF
            case IMAGETYPE_JPEG:  $SrcImage = ImageCreateFromJPEG($SrcName);
                break;  // JPEG
            case IMAGETYPE_PNG:  $SrcImage = ImageCreateFromPNG($SrcName);
                break;   // PNG
            default: return -1;                                         // Image type NOT supported
        }
        if (!$SrcImage)  return -2; // Error open/read image

        if (!$AlwaysResize && $SrcInfo[0] <= $NewWidth && $SrcInfo[1] <= $NewHeight) {
            $DestWidth = $SrcInfo[0];
            $DestHeight = $SrcInfo[1];
        } else {
            $RatioWidth = $SrcInfo[0] / $NewWidth;
            $RatioHeight = $SrcInfo[1] / $NewHeight;
            if($RatioWidth < $RatioHeight) {
                $DestWidth = $SrcInfo[0] / $RatioHeight;
                $DestHeight = $NewHeight;
            } else {
                $DestWidth = $NewWidth;
                $DestHeight = $SrcInfo[1] / $RatioWidth;
            }
        }

        // creating the destination image with the new Width and Height
        $DestImage = imagecreatetruecolor($DestWidth, $DestHeight);
        imageantialias($DestImage, true);
        imagealphablending($DestImage, false);
        imagesavealpha($DestImage, true);
        imageinterlace($DestImage, $Progressive);
        $Transparent = imagecolorallocatealpha($DestImage, 255, 255, 255, 0);
        /*for($x = 0; $x < $DestWidth; $x++) {
      for($y=0; $y < $DestHeight; $y++) {
        imagesetpixel($DestImage, $x, $y, $Transparent);
      }
    }*/
        imagefilledrectangle ($DestImage, 0, 0, $DestWidth-1, $DestHeight-1, $Transparent);
        imagecopyresampled($DestImage, $SrcImage, 0, 0, 0, 0, $DestWidth, $DestHeight, $SrcInfo[0], $SrcInfo[1]);
        //ImageCopyResized($DestImage, $SrcImage, 0, 0, 0, 0, $DestWidth, $DestHeight, $SrcInfo[0], $SrcInfo[1]);
        switch(strtolower(strrchr($DestName, '.'))) {
            case ".jpg": $b = ImageJPEG($DestImage, $DestName);
                break;
            case ".png": $b = ImagePNG($DestImage, $DestName);
                break;
            case ".gif": if (PHOTO_GIF_SUPPORT)  $b = ImageGIF($DestImage, $DestName);
                else                    $b = ImagePNG($DestImage, $DestName);
                break;
                break;
        }
        if (!isset($b) || !$b)  return -3;
        return 0; // Success
    }
}
