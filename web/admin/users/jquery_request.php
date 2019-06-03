<?php

require_once __DIR__ . '/../../../etc/config.php';
require_once R3_APP_ROOT . 'vendor/r3gis/common/lib/simplephoto.php';

$db = ezcDbInstance::get();
$auth = R3AuthInstance::get();
if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
    die();
}

define('R3_REQUEST_OK', 0);
define('R3_REQUEST_ERROR', -1);
define('R3_REQUEST_WARNING', -2);

if (isset($_GET['act']) && $_GET['act'] == 'add_signature') {
    if (!$auth->hasPerm('ADD', 'SIGNATURE')) {
        $ret['status'] = R3_REQUEST_ERROR;
        $ret['error'] = 'Permission denied';
        echo json_encode($ret);
        die();
    }
    if ($_FILES['us_signature']['error'] <> 0) {
        $ret['status'] = R3_REQUEST_ERROR;
        $ret['error'] = _('Caricamento fallito: problema sconosciuto.');
        echo json_encode($ret);
        die();
    }
    $validMime = array('image/gif', 'image/jpg', 'image/jpeg', 'image/pjpeg', 'image/png');
    if (!in_array($_FILES['us_signature']['type'], $validMime)) {
        $ret['status'] = R3_REQUEST_ERROR;
        $ret['error'] = _('Caricamento fallito: formato immagine non supportato.');
        $ret['mime'] = $_FILES['us_signature']['type'];
        $ret['validMimes'] = $validMime;
        echo json_encode($ret);
        die();
    }

    try {
        switch($_FILES['us_signature']['type']) {
            case 'image/gif':
                $ext = 'gif';
            break;
            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $ext = 'jpg';
            break;
            case 'image/png':
                $ext = 'png';
            break;
        }

        $resizedFile = R3_TMP_DIR.md5(microtime()).".{$ext}";
        pSimplePhoto::CreateThumb($_FILES['us_signature']['tmp_name'], $resizedFile, 350, 350, true, false);

        $file = file_get_contents($resizedFile);
        $mime = $_FILES['us_signature']['type'];

        $sql = "UPDATE auth.users SET " .
               "    us_signature=:data, ".
               "    us_signature_mime=:mime ".
               "WHERE " .
               "    us_id=:id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':data', $file, \PDO::PARAM_LOB);
        $stmt->bindParam(':mime', $mime);
        $stmt->bindParam(':id', $auth->getUID());
        $stmt->execute();

        $ret['status'] = R3_REQUEST_OK;
        $ret['random'] = md5(microtime());
        echo json_encode($ret);
        die();
    } catch(Exception $e) {
        if ($_FILES['us_signature']['error'] <> 0) {
            $ret['status'] = R3_REQUEST_ERROR;
            $ret['error'] = _('Unknown Error.');
            echo json_encode($ret);
            die();
        }
    }
} else if (isset($_GET['act']) && $_GET['act'] == 'show_signature') {
    if (!$auth->hasPerm('ADD', 'SIGNATURE')) {
        $ret['status'] = R3_REQUEST_ERROR;
        $ret['error'] = 'Permission denied';
        echo json_encode($ret);
        die();
    }

    $stmt = $db->query("SELECT us_signature, us_signature_mime FROM auth.users WHERE us_id=".$auth->getUID());
    $stmt->bindColumn('us_signature', $blob);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row === false) {
        $ret['status'] = R3_REQUEST_ERROR;
        $ret['error'] = _('Unknown Error.');
        echo json_encode($ret);
        die();
    }

    header("Content-Type: ".$row['us_signature_mime']);
    echo $blob;
    die();
} else if (isset($_GET['act']) && $_GET['act'] == 'del_signature') {
    if (!$auth->hasPerm('ADD', 'SIGNATURE')) {
        $ret['status'] = R3_REQUEST_ERROR;
        $ret['error'] = 'Permission denied';
        echo json_encode($ret);
        die();
    }

    $sql = "UPDATE auth.users SET " .
           "    us_signature=null, ".
           "    us_signature_mime=null ".
           "WHERE " .
           "    us_id = ? ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array($auth->getUID()));

    $ret['status'] = R3_REQUEST_OK;
    echo json_encode($ret);
    die();
}
