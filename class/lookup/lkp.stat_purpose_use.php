<?php

class lkp_stat_purpose_use extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'stat_building_purpose_use';
    protected $checkForeignKey = true;
    protected $checkDomian = true;

    /**
     * ecogis.building_purpose_use fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'sbpu_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'sbpu_name_1', // Nome        
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in elenco
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'sbpu_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2), // Label
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'sbpu_order',
                'type' => 'number',
                'required' => true,
                'default' => 0,
                'width' => 100,
                'label' => _('Ordinamento'))
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _("Nuova destinazione d'uso (Pubblico)");
            case 'mod': return _("Modifica destinazione d'uso (Pubblico)");
            case 'show': return _("Visualizza destinazione d'uso (Pubblico)");
            case 'list': return _("Elenco destinazioni d'uso (Pubblico)");
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare la destinazione d'uso pubblica \"%s\"?"),
            'error' => _("Impossibile cancellare questa destinazione d'uso pubblica, perchè vi sono dei dati ad essa collegati"));
    }

}
