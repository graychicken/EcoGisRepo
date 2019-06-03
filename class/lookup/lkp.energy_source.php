<?php

class lkp_energy_source extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'energy_source';
    protected $checkForeignKey = true;
    protected $checkDomian = true;
    protected $UUID = 'es_uuid';

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'es_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'et_id',
                'type' => 'lookup',
                'label' => _('Tipo fonte'),
                'required' => true,
                'width' => 200,
                'lookup' => array('table' => 'energy_type', 'list_field' => 'et_name_<LANG>', 'where' => "et_is_private IS FALSE AND et_code IN ('HEATING', 'ELECTRICITY', 'WATER')")),
            array('name' => 'es_name_1', // Nome        
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'xattr' => array('align' => 'center'), // Attributi simple table
                'edit' => true, // visibile in elenco
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'es_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome 2'),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'es_order',
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

    public function canMod() {
        return R3AuthInstance::get()->hasPerm('MOD', 'ALL_LOOKUP');
    }

    public function canDel() {
        return R3AuthInstance::get()->hasPerm('DEL', 'ALL_LOOKUP');
    }

}
