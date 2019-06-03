<?php

class lkp_cat_munic extends R3LookupBaseObject {

    protected $table = 'cat_munic';                   // Nome tabella
    protected $view = 'cat_munic_data';              // Nome view da cui leggere i dati
    protected $checkForeignKey = true;                // Se true effettua un controllo preventivo dei lookup per cambiare il bottone di cancellazione
    protected $checkDomian = true;                    // Se true effettua sempre il check per il dominio

    public function defFields() {
        $mu_id = R3EcogisHelper::getDefaultMunicipality();
        $fields = array(
            array('name' => 'cm_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'mu_id',
                'type' => 'lookup',
                'width' => 200,
                'label' => _('Comune'),
                'required' => true,
                'lookup' => array('table' => 'municipality', 'list_field' => 'mu_name_<LANG>'),
                'filter' => array('type' => 'select', 'fields' => array('mu_id', 'mu_name_<LANG>'), 'where' => 'do_id=<DOMAIN_ID>', 'cond_where' => array('mu_id' => $mu_id)),
                'visible' => $mu_id == '',
                'default' => $mu_id),
            array('name' => 'cm_code',
                'type' => 'text',
                'label' => _('Codice'),
                'width' => 200),
            array('name' => 'cm_name_1', // Nome        
                'type' => 'text', // Tipo
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'cm_name_2',
                'type' => 'text',
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'label' => _('Nome') . getLangNameShort(2)),
            array('name' => 'cm_visible',
                'type' => 'boolean',
                'default' => true,
                'label' => _('Visibile')),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo comune catastale');
            case 'mod': return _('Modifica comune catastale');
            case 'show': return _('Visualizza comune catastale');
            case 'list': return _('Elenco comuni catastale');
        }
    }

    public function getListWhere() {
        return (R3AuthInstance::get()->getParam('mu_id') == '' ? '' : 'AND mu_id=' . R3AuthInstance::get()->getParam('mu_id'));
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare il comune catastale \"%s\"?"),
            'error' => _("Impossibile cancellare questo comune catastale, perchè vi sono dei dati ad esso collegati"));
    }

}
