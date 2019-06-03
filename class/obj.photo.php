<?php  /* UTF-8 FILE: òàèü */

class eco_photo extends R3AppBasePhotoObject {

    public function __construct(array $request=array(), array $opt=array()){
	    parent::__construct($request, $opt);
        
        $this->file_id =          initVar('file_id');
        $this->act =              initVar('act', 'open');
        $this->type =             initVar('type');
        $this->kind =             initVar('kind');
        $this->preview =          isset($this->request['preview']);
        $this->disposition =      initVar('disposition',     'inline'); // Document disposition (inline / download)
    }
    
    /**
     * Return the page title
    */
    public function getPageTitle() { }
            
    /**
     * Return the sql to generate the list
    */
    public function getListSQL() { }
    
    /**
     * Create the table header
     * param string $order             The table order
    */
    public function createListTableHeader(&$order) { }
    
    /**
     * Return the data for a single customer 
    */
    public function getData($id=null) {

        $db = ezcDbInstance::get();
        if ($this->act == 'open') {
            // open/download the document
            $this->deliver('photo');
            die();
        }
        die("Invalid action [$this->act]");
    }
    
    // Return an error image
    public function deliverError($text, $width=null, $height=null) {
        if ($width === null || $height === null) {
            list($width, $height) = explode('x', $this->auth->getConfigValue('APPLICATION', 'PHOTO_PREVIEW_SIZE', '200x200'));
        }    
        $image = imagecreate($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 0));
        imagefilledrectangle($image, 1, 1, $width - 2, $height - 2, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 2, 10, 10, $text, imagecolorallocate($image, 0, 0, 0));
        imagepng($image);
        imagedestroy($image);
    }
    
    /**
     * Deliver a file
    */
    public function deliver($kind) {
        $file_info = $this->getDocFileInfoByFileId($this->file_id);
        if ($file_info === false) {
            $this->deliverError(sprintf(_("File #%s non trovato"), $this->file_id));
            die();
        }
        if ($this->type == '' && $this->kind == '') {
            list($this->type, $this->kind) = explode('_', $file_info['doct_code'] . '_');
        }
        $this->type = strtolower($this->type);
        $this->kind = strtolower($this->kind);

        $name = $this->getDocFullName($file_info['doc_file'], $this->type, $this->kind, $file_info['doc_file_id'], $this->preview);
        if ($this->preview && !file_exists($name)) {
            $orgName = $this->getDocFullName($file_info['doc_file'], $this->type, $this->kind, $file_info['doc_file_id'], false);
            if (!file_exists($orgName)) {
                $this->deliverError(sprintf(_("File #%s mancante"), $this->file_id));
                die();
            }
            list($width, $height) = explode('x', $this->auth->getConfigValue('APPLICATION', 'PHOTO_PREVIEW_SIZE', '200x200'));
            $this->resizeImage($orgName, $name, $width, $height);
            
        }
        deliverFile($name, array('name'=>$file_info['doc_file'],
                                 'disposition'=>$this->disposition,
                                 'cacheable'=>$this->auth->getConfigValue('APPLICATION', 'PHOTO_CACHE_TTL') > 0,
                                 'cache_ttl'=>$this->auth->getConfigValue('APPLICATION', 'PHOTO_CACHE_TTL')));
    }
    
    public function checkPerm() {
        $db = ezcDbInstance::get();
        
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act,  $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        // Extra security
        //if ($act <> 'ADD' && $this->id <> '') {
        //    $this->bu_id = $db->query('SELECT bu_id FROM document_data WHERE doc_id=' . forceInteger($this->id, 0, false, '.'))->fetchColumn();
        //} else if ($this->file_id <> '') {
        //    $this->bu_id = $db->query('SELECT bu_id FROM document_data WHERE doc_file_id=' . (int)$this->file_id)->fetchColumn();
        //}
        //if (!R3EcoGisHelper::isValidBuilding($this->bu_id))
        //    die(sprintf(_("PERMISSION DENIED [Not my building]")));
    }
    
}


?>