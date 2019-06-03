<?php

class lkp_heating_degree_days extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'heating_degree_day';
    protected $view = 'heating_degree_day_data';
    protected $checkForeignKey = true;
    protected $checkDomian = true;

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $mu_id = R3EcogisHelper::getDefaultMunicipality();
        $fields = array(
            array('name' => 'hdd_id',
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
            
            array('name' => 'hdd_year', // Nome
                'type' => 'number', // Tipo
                'required' => true, // Campo obbligatorio
                'label' => _('Anno'), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in edit
                'delete_name' => true, // Questo campo viene usato per il messaggio di conferma cancellazione
            ),
            array('name' => 'hdd_factor',
                'type' => 'number',
                'label' => _('Coefficiente'))
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo valore grado giorno termico');
            case 'mod': return _('Modifica grado giorno termico');
            case 'show': return _('Visualizza grado giorno termico');
            case 'list': return _('Elenco gradi giorno termici');
        }
    }

    public function getListWhere() {
        return (R3AuthInstance::get()->getParam('mu_id') == '' ? '' : 'AND mu_id=' . R3AuthInstance::get()->getParam('mu_id'));
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare il valore del grado giorno per l\'anno %s?'),
            'error' => _('Impossibile cancellare il valore del grado giorno, perchè vi sono dei dati ad esso collegati'));
    }

}
