<?php

class lkp_energy_source_udm_admin extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'energy_source_udm';
    // Se true effettua un controllo preventivo dei lookup per cambiare il bottone di cancellazione
    protected $checkForeignKey = true;
    // The UUID field name
    protected $UUID = 'esu_uuid';

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'esu_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'lookup',
                'width' => 200,
                'label' => _('Ente'),
                'lookup' => array('table' => 'customer', 'list_field' => 'cus_name_<LANG>'),
                'filter' => array('type' => 'select', 'fields' => array('do_id', 'cus_name_<LANG>'))),
            array('name' => 'mu_id',
                'type' => 'lookup',
                'width' => 200,
                'label' => _('Comune'),
                'lookup' => array('table' => 'municipality', 'list_field' => 'mu_name_<LANG>'),
                'filter' => array('type' => 'select', 'fields' => array('mu_id', 'mu_name_<LANG>'), 'where' => 'do_id IS NOT NULL')),
            array('name' => 'es_id',
                'type' => 'lookup',
                'width' => 200,
                'label' => _('Tipo alimentazione'),
                'required' => true,
                //'wrap'=>false,
                'lookup' => array('table' => 'energy_source', 'list_field' => 'es_name_<LANG>'),
                'filter' => array('type' => 'select', 'fields' => array('es_id', 'es_name_<LANG>'))),
            array('name' => 'udm_id',
                'type' => 'lookup',
                'width' => 100,
                'label' => _('Unità di misura'),
                'show_label' => true,
                'required' => true,
                'lookup' => array('table' => 'udm', 'list_field' => 'udm_name_<LANG>'),
                'filter' => array('type' => 'select', 'fields' => array('udm_id', 'udm_name_<LANG>'))),
            array('name' => 'esu_kwh_factor',
                'type' => 'float',
                'label' => _('Fattore conversione kWh'),
                'required' => true),
            array('name' => 'esu_tep_factor',
                'type' => 'float',
                'label' => _('Fattore conversione T.E.P.'),
                'required' => true),
            array('name' => 'esu_co2_factor',
                'type' => 'float',
                'label' => _('Fattore conversione CO2 [kg]'),
                'required' => true),
            array('name' => 'esu_is_private',
                'type' => 'boolean',
                'label' => _('Uso interno [Fornitori di energia]')),
            array('name' => 'esu_is_consumption',
                'type' => 'boolean',
                'label' => _('Fonte a consumo')),
            array('name' => 'esu_is_production',
                'type' => 'boolean',
                'label' => _('Fonte a produzione')),
            array('name' => 'esu_allow_in_building',
                'type' => 'boolean',
                'label' => _('Fonte presente nella scheda edifici')),
            array('name' => 'ges_id',
                'type' => 'lookup',
                'label' => _('Alimentazione inventario'),
                'lookup' => array('table' => 'global_energy_source_data',
                    'list_field' => "ges_full_name_<LANG>"),
                'filter' => array('type' => 'select', 'fields' => array('ges_id', 'ges_full_name_<LANG>'))),
            array('name' => 'gc_id',
                'type' => 'lookup',
                'label' => _('Categoria inventario fissa'),
                'lookup' => array('table' => 'global_category_data',
                    'list_field' => "gc_full_name_<LANG>")),
            array('name' => 'esu_text',
                'type' => 'text',
                'list' => false,
                'label' => _('Note interne')),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo fattore di conversione (ADMIN)');
            case 'mod': return _('Modifica fattore di conversione (ADMIN)');
            case 'show': return _('Visualizza fattore di conversione (ADMIN)');
            case 'list': return _('Elenco fattori di conversione (ADMIN)');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare questo fattore di conversione?'),
            'error' => _('Impossibile cancellare questo tipo di alimentazione, perchè vi sono dei dati ad esso collegati'));
    }

}
