<?php

class lkp_global_energy_source extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'global_energy_source';
    protected $checkForeignKey = true;
    protected $UUID = 'ges_uuid';

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'ges_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'get_id',
                'type' => 'lookup',
                'label' => _('Tipo fonte'),
                'required' => true,
                'width' => 200,
                'lookup' => array('table' => 'global_energy_type', 'list_field' => 'get_name_<LANG>')),
            array('name' => 'ges_name_1', // Nome        
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in edit
                'delete_name' => true, // Questo campo viene usato per il messaggio di conferma cancellazione
            ),
            array('name' => 'ges_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'ges_name_3',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(3)),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo tipo alimentazione');
            case 'mod': return _('Modifica tipo alimentazione');
            case 'show': return _('Visualizza tipo alimentazione');
            case 'list': return _('Elenco tipi alimentazione');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare il tipo di alimentazione "%s"?'),
            'error' => _('Impossibile cancellare questo tipo di alimentazione, perchè vi sono dei dati ad esso collegati'));
    }

    public function canAdd() {
        return R3AuthInstance::get()->hasPerm('ADD', 'ALL_LOOKUP');
    }

}
