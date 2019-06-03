<?php

class lkp_udm extends R3LookupBaseObject {

    protected $table = 'udm';                   // Nome tabella
    protected $checkForeignKey = true;          // Se true effettua un controllo preventivo dei lookup per cambiare il bottone di cancellazione
    protected $checkDomian = true;
    protected $UUID = 'udm_uuid';

    public function defFields() {
        $fields = array(
            array('name' => 'udm_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'udm_name_1', // Nome        
                'type' => 'text', // Tipo
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'udm_name_2',
                'type' => 'text',
                'label' => _('Nome') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'udm_is_electricity',
                'type' => 'boolean',
                'label' => _('UDM Elettrico')),
            array('name' => 'udm_order',
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
            case 'add': return _('Nuova unità di misura');
            case 'mod': return _('Modifica unità di misura');
            case 'show': return _('Visualizza unità di misura');
            case 'list': return _('Elenco unità di misura');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare l'unità di misura \"%s\"?"),
            'error' => _("Impossibile cancellare questa unità di misura, perchè vi sono dei dati ad essa collegati"));
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
