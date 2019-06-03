<?php

class lkp_global_energy_source_main extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'global_energy_type';
    protected $checkForeignKey = true;
    protected $UUID = 'get_uuid';

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'get_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'get_code',
                'type' => 'text',
                'label' => _('Codice'),
                'required' => true,
                'width' => 200),
            array('name' => 'get_name_1', // Nome        
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in elenco
                'delete_name' => true, // Questo campo viene usato per il messaggio di conferma cancellazione
            ),
            array('name' => 'get_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'get_name_3',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(3)),
            array('name' => 'get_show_label',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Mostra label categoria'),
                'default' => true),
            array('name' => 'get_order',
                'type' => 'number',
                'required' => true,
                'default' => 0,
                'label' => _('Ordinamento')),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo tipo fonte');
            case 'mod': return _('Modifica tipo fonte');
            case 'show': return _('Visualizza tipo fonte');
            case 'list': return _('Elenco tipi fonte');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare la fonte "%s"?'),
            'error' => _('Impossibile cancellare questa fonte, perchè vi sono dei dati ad essa collegati'));
    }

    public function canAdd() {
        return R3AuthInstance::get()->hasPerm('ADD', 'ALL_LOOKUP');
    }

}
