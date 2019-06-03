<?php

class lkp_building_type extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'building_type';
    protected $checkForeignKey = true;
    protected $checkDomian = true;
    protected $UUID = 'bt_uuid';

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'bt_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'bt_name_1', // Nome
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in edit
                'delete_name' => true, // Questo campo viene usato per il messaggio di conferma cancellazione
            ),
            array('name' => 'bt_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'bt_has_extradata',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Dati aggiuntivi'),
                'visible' => true),
            array('name' => 'bt_order',
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
            case 'add': return _('Nuova tipologia costruttiva edificio');
            case 'mod': return _('Modifica tipologia costruttiva edificio');
            case 'show': return _('Visualizza tipologia costruttiva edificio');
            case 'list': return _('Elenco tipologie costruttive edifici');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare la tipologia costruttiva "%s"?'),
            'error' => _('Impossibile cancellare questa tipologia costruttiva, perchè vi sono dei dati ad esso collegati'));
    }

}
