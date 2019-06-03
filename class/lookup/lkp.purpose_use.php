<?php

class lkp_purpose_use extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'building_purpose_use';
    protected $checkForeignKey = true;
    protected $checkDomian = true;
    protected $UUID = 'bpu_uuid';

    /**
     * ecogis.building_purpose_use fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'bpu_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'bpu_name_1', // Nome        
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in elenco
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'bpu_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2), // Label
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'bpu_has_extradata',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Dati aggiuntivi'),
                'visible' => true),
            array('name' => 'bpu_order',
                'type' => 'number',
                'required' => true,
                'default' => 0,
                'width' => 100,
                'label' => _('Ordinamento')),
            array('name' => 'gc_id',
                'type' => 'lookup',
                'label' => _('Categoria inventario fissa'),
                'lookup' => array('table' => 'global_category_data',
                    'list_field' => "gc_full_name_<LANG>")),
            array('name' => 'sbpu_id',
                'type' => 'lookup',
                'label' => _('Categoria pubblica'),
                'width' => 150,
                'lookup' => array('table' => 'stat_building_purpose_use',
                    'list_field' => "sbpu_name_<LANG>"),
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'ENABLE_PUBLIC_SITE', 'F') == 'T'),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _("Nuova destinazione d'uso");
            case 'mod': return _("Modifica destinazione d'uso");
            case 'show': return _("Visualizza destinazione d'uso");
            case 'list': return _("Elenco destinazioni d'uso");
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare la destinazione d'uso \"%s\"?"),
            'error' => _("Impossibile cancellare questa destinazione d'uso, perchè vi sono dei dati ad essa collegati"));
    }

}
