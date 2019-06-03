<?php

class lkp_building_restructure_year extends R3LookupBaseObject {

    protected $table = 'building_restructure_year';                   // Nome tabella
    protected $checkForeignKey = true;                          // Se true effettua un controllo preventivo dei lookup per cambiare il bottone di cancellazione
    protected $checkDomian = true;
    protected $UUID = 'bry_uuid';

    public function defFields() {
        $fields = array(
            array('name' => 'bry_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'bry_name_1', // Nome
                'type' => 'text', // Tipo
                'required' => true, // Campo obbligatorio
                'label' => _('Descrizione') . getLangNameShort(1), // Label
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'bry_name_2',
                'type' => 'text',
                'label' => _('Descrizione') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'bry_start_year', // Nome
                'type' => 'text', // Tipo
                'required' => true, // Campo obbligatorio
                'label' => _('Anno inizio') . getLangNameShort(1)), // Label
            array('name' => 'bry_end_year',
                'type' => 'text',
                'label' => _('Anno fine') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'bry_order',
                'type' => 'number',
                'required' => true,
                'default' => 0,
                'width' => 100,
                'label' => _('Ordinamento')),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo anno di ristrutturazione');
            case 'mod': return _('Modifica anno di ristrutturazione');
            case 'show': return _('Visualizza anno di ristrutturazione');
            case 'list': return _('Elenco anni di ristrutturazione');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare l'anno di ristrutturazione \"%s\"?"),
            'error' => _("Impossibile cancellare questo anno di ristrutturazione, perchè vi sono dei dati ad esso collegati"));
    }

}
