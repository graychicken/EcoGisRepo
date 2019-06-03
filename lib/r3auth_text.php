<?php
  
//auth errors
$statusText = array();
$statusText[AUTH_IDLED] =                   _('Sessione scaduta');
$statusText[AUTH_OK] =                      _('Ok');
$statusText[AUTH_EXPIRED] =                 _('Password scaduta');
$statusText[AUTH_WRONG_LOGIN] =             _('Login / password non valide');
$statusText[AUTH_METHOD_NOT_SUPPORTED] =    _('Tipo autenticazione non supportato');
$statusText[AUTH_SECURITY_BREACH] =         _('Errore di sicurezza');
$statusText[AUTH_SECURITY_BREACH] =         _('Errore di sicurezza');
$statusText[AUTH_CALLBACK_ABORT] =          _('Annullamento callback');
$statusText[AUTH_NOT_LOGGED_IN] =           _('Bisogna loggarsi/sessione scaduta');
$statusText[AUTH_ACCOUNT_DISABLED] =        _('Account disabilitato');
$statusText[AUTH_ACCOUNT_NOT_STARTED] =     _('Account non ancora attivo');
$statusText[AUTH_ACCOUNT_EXPIRED] =         _('Account scaduto');
$statusText[AUTH_INVALID_IP] =              _('IP di provenienza non valido');
$statusText[AUTH_PASSWORD_EXPIRED] =        _('Password scaduta');
$statusText[AUTH_PASSWORD_IN_EXPIRATION] =  _('Password in scadenza');
$statusText[AUTH_PASSWORD_REPLACE] =        _('La password deve essere modificata');
$statusText[AUTH_INVALID_AUTH_DATA] =       _('Parametri di autenticazione non validi');
$statusText[AUTH_USER_DISCONNECTED] =       _('Utente disconnesso dall\'amministratore');

//auth text
$authText = array();
$authText['user_manager_acname_type_C'] = _('Applicativo');
$authText['user_manager_acname_type_S'] = _('Sistema');

?>