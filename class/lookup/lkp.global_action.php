<?php

class lkp_global_action extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'global_plain_action';
    protected $checkForeignKey = true;
    protected $checkDomian = true;
    protected $UUID = 'gpa_uuid';

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'gpa_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'gpa_code', // Nome
                'type' => 'text', // Tipo
                'width' => 100, // Lunghezza campo (tabella e edit)
                'required' => false, // Campo obbligatorio
                'label' => _('Codice'), // Label
                'list' => true, // visibile in lista
                'edit' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'gpa_name_1', // Nome
                'type' => 'text', // Tipo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in edit
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'gpa_name_2',
                'type' => 'text',
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'gpa_name_3',
                'type' => 'text',
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(3)),
            array('name' => 'gpa_extradata_1', // Nome
                'type' => 'text', // Tipo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'label' => _('Descrizione') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true), // visibile in edit
            array('name' => 'gpa_extradata_2',
                'type' => 'text',
                'width' => array(null, 300),
                'label' => _('Descrizione') . getLangNameShort(2),
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'gpa_extradata_3',
                'type' => 'text',
                'width' => array(null, 300),
                'label' => _('Descrizione') . getLangNameShort(3)),
            array('name' => 'gpa_order',
                'type' => 'number',
                'required' => true,
                'width' => 50,
                'default' => 0,
                'label' => _('Ordinamento')),
            array('name' => 'gpa_has_extradata',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Dati aggiuntivi'),
                'visible' => true),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova categorie tabelle');
            case 'mod': return _('Modifica categorie tabelle');
            case 'show': return _('Visualizza categorie tabelle');
            case 'list': return _('Elenco categorie tabelle');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare la categoria "%s"?'),
            'error' => _('Impossibile cancellare questa categoria, perchè vi sono dei dati ad esso collegati'));
    }

}
