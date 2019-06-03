<?php

if (defined("__SIMPLEDOC_PHP__"))
    return;
define("__SIMPLEDOC_PHP__", 1);

define("DOC_DIR_LIMIT", 1000);          // Numero massimo di foto per cartella

class pDocElement {

    public $Name;          // Nome reale del documento
    public $File;          // Nome documento
    public $URL;           // URL documento
    public $type;          // Tipo di documento (estensione)
    public $FileSize;      // File Size

}

class pSimpleDoc {

    public $BasePath;  // Percorso di base
    public $Path;      // Percorso sotto cache
    public $BaseURL;   // URL di base
    public $URL;       // URL
    public $Name;      // Nome
    public $PrevWidth;
    public $PrevHeight;

    function pSimpleDoc($Path, $URL, $Name = '') {
        $this->BasePath = $Path;
        if ($Name == '')
            $this->Path = $Path;
        else {
            $this->Path = $Path . $Name . '/';
            if (!file_exists($this->Path))
                mkdir($this->Path);
        }
        $this->BaseURL = $URL;
        if ($Name == '')
            $this->URL = $URL;
        else
            $this->URL = $URL . $Name . '/';

        $this->Name = $Name;
    }

    function GetDocElement($id, $name) {
        $Path = $this->Path . sprintf('%08d', $id / DOC_DIR_LIMIT) . '/';
        $ext = strtolower(strrchr($name, '.'));
        $fileName = $Path . sprintf('%08d', $id) . $ext;
        if (file_exists($fileName)) {
            $DE = new pDocElement();
            $DE->Name = $name;
            $DE->File = $fileName;
            $DE->URL = $this->URL . sprintf('%08d', $id / DOC_DIR_LIMIT) . '/' . sprintf('%08d', $id) . $ext;
            $DE->type = $ext;
            $DE->FileSize = filesize($DE->File);

            return $DE;
        }

        return null;
    }

    // Elimina il documento
    function DeleteDocument($id, $name = '') {

        $exts = array();
        $Path = $this->Path . sprintf('%08d', $id / DOC_DIR_LIMIT) . '/';
        $ext = strtolower(strrchr($name, '.'));
        if ($ext == '') {
            $exts[] = '.jpg';
            $exts[] = '.png';
            $exts[] = '.gif';
        } else
            $exts[] = $ext;

        for ($cont = 0; $cont < count($exts); $cont++) {
            $Name = $Path . sprintf('%08d', $id) . $exts[$cont];
            if (file_exists($Name))
                unlink($Name);
        }
    }

    /**
     * Get path on file system
     *
     * @param $id Id for document
     *
     * @returns string
     */
    function getDocumentPath($id) {
        $Path = $this->Path . sprintf('%08d', $id / DOC_DIR_LIMIT) . '/';
        if (!file_exists($Path))
            mkdir($Path);

        return $Path;
    }

    /**
     * Get file name
     *
     * @param $id Id for document
     *
     * @returns string
     */
    static function getFileSysName($id) {
        $Name = sprintf('%08d', $id);

        return $Name;
    }

    function AddDocument($id, $src, $ext = '', $move = false, $isUpload = false) {
        if (is_readable($src)) {
            $Path = $this->Path . sprintf('%08d', $id / DOC_DIR_LIMIT) . '/';
            if (!file_exists($Path))
                mkdir($Path);

            if ($ext == '')
                $ext = strtolower(strrchr($src, '.'));
            $Name = $Path . sprintf('%08d', $id) . $ext;

            if ($move) {
                if ($isUpload) {
                    move_uploaded_file($src, $Name);
                } else {
                    rename($src, $Name);
                }
            } else {
                copy($src, $Name);
            }
            $DE = new pDocElement();
            $DE->Name = $Name;

            $DE->File = $Name;
            $DE->URL = $this->URL . sprintf('%08d', $id / DOC_DIR_LIMIT) . '/' . sprintf('%08d', $id) . $ext;
            $DE->type = $ext;
            $DE->FileSize = filesize($DE->File);

            return $DE;
        }
        return null;
    }

    /**
     * Add Document from Upload
     *
     * @return DocumentElement. Returns null if upload wasn't successfull
     */
    function AddDocumentFromUpload($id, $uploadname, $move = true) {
        if (is_array($uploadname))
            $upload = $uploadname;
        else
            $upload = $_FILES[$uploadname];

        return $this->AddDocument($id, $upload['tmp_name'], $this->GetDocExt($upload["name"]), $move, true);
    }

    // Restituisce l'estensione dell'immagine (valori possibili: .gif, .jpg, .png o vuoto)
    function GetDocExt($name) {
        $ext = strtolower(strrchr($name, '.'));
        return $ext;
    }

    // Restituisce il nome del documento senza percorso e mettendo sempre l'estensione (.gif, .jpg, .png)
    function AdjDocumentName($name, $maxlen) {
        if (defined('R3_APP_CHARSET_DB'))
            $charset = R3_APP_CHARSET_DB;
        else
            $charset = "ISO-88591-1";

        if (function_exists('mb_strrchr')) {
            $ext = mb_strtolower(mb_strrchr($name, '.', false, $charset), $charset);
        } else { // For php < 5.2
            $ext = mb_strtolower(strrchr($name, '.'), $charset);
        }
        $name = mb_substr($name, 0, mb_strlen($name, $charset) - mb_strlen($ext, $charset), $charset);
        if ($ext == '.jpeg')
            $ext = '.jpg';
        if (mb_strpos($name, '/', 0, $charset) !== false) {
            if (function_exists('mb_strrchr')) {
                $name = mb_substr(mb_strrchr($name, '/', false, $charset), 1, mb_strlen($name, $charset), $charset);
            } else { // For php < 5.2
                $name = mb_substr(strrchr($name, '/'), 1, mb_strlen($name, $charset), $charset);
            }
        }

        return mb_substr($name, 0, $maxlen - mb_strlen($ext, $charset), $charset) . $ext;
    }

    // Verifica se l'upload del documento è ok
    function UploadOk($uploadname) {
        $upload = $_FILES[$uploadname];
        if ($this->GetDocExt($upload['name']) && $upload['error'] == 0 && $upload['size'] > 0)
            return true;
        return false;
    }

}

