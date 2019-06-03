<?php

class lkp_street extends R3LookupBaseObject {

    protected $table = 'common.street';         // Nome tabella
    protected $view = 'street_data';           // Nome view da cui leggere i dati
    protected $checkForeignKey = true;          // Se true effettua un controllo preventivo dei lookup per cambiare il bottone di cancellazione
    protected $checkDomian = true;              // Se true effettua sempre il check per il dominio

    public function defFields() {
        $mu_id = R3EcogisHelper::getDefaultMunicipality();
        $fields = array(
            array('name' => 'st_id',
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
            array('name' => 'st_code',
                'type' => 'text',
                'label' => _('Codice'),
                // Change filter label + filter functionality %2\$s take always the same argument
                'filter' => array('type' => 'text', 'label' => 'Codice/nome', 'mask' => "(%s ILIKE '%%%2\$s%%') OR (st_name_1 ILIKE '%%%2\$s%%') OR (st_name_2 ILIKE '%%%2\$s%%')"),
                'width' => 200,
                'attr' => array('sortable' => true, 'order_fields' => 'st_code_pad, st_name_1, st_name_2, st_id')),
            array('name' => 'st_name_1', // Nome        
                'type' => 'text', // Tipo
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'st_name_2',
                'type' => 'text',
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'label' => _('Nome') . getLangNameShort(2)),
            array('name' => 'st_lkp_name_1', // Nome
                'type' => 'text', // Tipo
                'required' => false, // Campo obbligatorio
                'label' => _('Nome lookup') . getLangNameShort(1)), // Label
            array('name' => 'st_lkp_name_2',
                'type' => 'text',
                'required' => false, //R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'label' => _('Nome lookup') . getLangNameShort(2)),
            array('name' => 'st_visible',
                'type' => 'boolean',
                'default' => true,
                'label' => _('Visibile')),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova strada');
            case 'mod': return _('Modifica strada');
            case 'show': return _('Visualizza strada');
            case 'list': return _('Stradario');
        }
    }

    public function getListWhere() {
        return (R3AuthInstance::get()->getParam('mu_id') == '' ? '' : 'AND mu_id=' . R3AuthInstance::get()->getParam('mu_id'));
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare la strada \"%s\"?"),
            'error' => _("Impossibile cancellare questa strada, perchè vi sono dei dati ad essa collegati"));
    }

    public function getPermName() {
        global $smarty;

        if ($this->auth->hasPerm('ADD', 'STREET')) {
            $smarty->assign("USER_CAN_ADD_LOOKUP", true);
        }
        return 'STREET';
    }

    public function canAdd() {
        return R3AuthInstance::get()->hasPerm('ADD', 'STREET');
    }

}
