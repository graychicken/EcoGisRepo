<?php

class lkp_device_type extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'device_type';
    protected $checkForeignKey = true;
    protected $checkDomian = true;
    protected $UUID = 'dt_uuid';

    /**
     * ecogis.device_type fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'dt_id',
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
            array('name' => 'dt_name_1', // Nome        
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in elenco
                'delete_name' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'dt_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2), // Label
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'dt_has_extradata',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Dati aggiuntivi'),
                'visible' => true),
            array('name' => 'dt_is_consumption',
                'type' => 'boolean',
                'width' => 80,
                'default' => true,
                'label' => _('Impianto a consumo'),
                'visible' => true),
            array('name' => 'dt_is_production',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Impianto a produzione'),
                'visible' => true),
            array('name' => 'dt_order',
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
            case 'add': return _("Nuova tipologia impianto");
            case 'mod': return _("Modifica tipologia impianto");
            case 'show': return _("Visualizza tipologia impianto");
            case 'list': return _("Elenco tipologia impianto");
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _("Sei sicuro di voler cancellare la tipologia di impiano \"%s\"?"),
            'error' => _('Impossibile cancellare questa tipologia di impianto, perchè vi sono dei dati ad esso collegati'));
    }

}
