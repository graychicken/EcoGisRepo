<?php

class lkp_fraction extends R3LookupBaseObject {

    protected $table = 'common.fraction';                   // Nome tabella
    protected $view = 'fraction_data';                     // Nome view da cui leggere i dati
    protected $checkForeignKey = true;                      // Se true effettua un controllo preventivo dei lookup per cambiare il bottone di cancellazione
    protected $checkDomian = true;              // Se true effettua sempre il check per il dominio

    public function defFields() {
        $mu_id = R3EcogisHelper::getDefaultMunicipality();
        $fields = array(
            array('name' => 'fr_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'mu_id',
                'type' => 'lookup',
                'width' => 200,
                'label' => _('Comune'),
                'kind' => $this->act <> 'add' ? 'readonly' : null,
                'required' => true,
                'lookup' => array('table' => 'municipality', 'list_field' => 'mu_name_<LANG>'),
                'filter' => array('type' => 'select', 'fields' => array('mu_id', 'mu_name_<LANG>'), 'where' => 'do_id=<DOMAIN_ID>', 'cond_where' => array('mu_id' => $mu_id)),
                'visible' => $mu_id == '',
                'default' => $mu_id),
            array('name' => 'fr_code',
                'type' => 'text',
                'label' => _('Codice'),
                'align' => 'right',
                'width' => 200),
            array('name' => 'fr_name_1', // Nome
                'type' => 'text', // Tipo
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'fr_name_2',
                'type' => 'text',
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'label' => _('Nome') . getLangNameShort(2)),
            array('name' => 'fr_visible',
                'type' => 'boolean',
                'default' => true,
                'label' => _('Visibile')),
        );
        return $fields;
    }

    public function getListWhere() {
        return "t0.do_id={$this->do_id} " . (R3AuthInstance::get()->getParam('mu_id') == '' ? '' : 'AND mu_id=' . R3AuthInstance::get()->getParam('mu_id'));
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova frazione');
            case 'mod': return _('Modifica frazione');
            case 'show': return _('Visualizza frazione');
            case 'list': return _('Elenco frazioni');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare la frazione \"%s\"?"),
            'error' => _("Impossibile cancellare questa frazione, perchè vi sono dei dati ad essa collegati"));
    }

    public function getPermName() {
        global $smarty;

        if ($this->auth->hasPerm('ADD', 'FRACTION')) {
            $smarty->assign("USER_CAN_ADD_LOOKUP", true);
        }
        return 'FRACTION';
    }

}
