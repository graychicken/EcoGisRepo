<?php

class lkp_global_category extends R3LookupBaseObject {

    // Nome tabella
    protected $table = 'global_category';
    protected $checkForeignKey = true;
    protected $checkDomian = true;
    protected $UUID = 'gc_uuid';

    /**
     * ecogis.global_subcategory fields definition
     */
    public function defFields() {
        $fields = array(
            array('name' => 'gc_id',
                'type' => 'number',
                'is_primary_key' => true),
            array('name' => 'do_id',
                'type' => 'domain'),
            array('name' => 'gc_code', // Nome
                'type' => 'text', // Tipo
                'width' => 100, // Lunghezza campo (tabella e edit)
                'required' => false, // Campo obbligatorio
                'label' => _('Codice'), // Label
                'list' => true, // visibile in lista
                'edit' => true), // Questo campo viene usato per il messaggio di conferma cancellazione
            array('name' => 'gc_parent_id',
                'type' => 'lookup',
                'label' => _('Macro categoria'),
                'required' => true,
                'width' => 200,
                'lookup' => array('table' => 'global_category', 'list_field' => 'gc_name_<LANG>', 'alias' => 'get_name', 'pk' => array('gc_parent_id', 'gc_id'), 'where' => "gc_parent_id IS NULL AND (do_id IS NULL OR do_id={$this->do_id})"),
                'filter' => array('type' => 'select', 'fields' => array('gc_id', 'gc_name_<LANG>'), 'where' => "gc_parent_id IS NULL AND (do_id IS NULL OR do_id={$this->do_id})")),
            array('name' => 'gc_name_1', // Nome
                'type' => 'text', // Tipo
                'width' => array(null, 300), // Lunghezza campo (tabella e edit)
                'required' => true, // Campo obbligatorio
                'label' => _('Nome') . getLangNameShort(1), // Label
                'list' => true, // visibile in lista
                'edit' => true, // visibile in edit
                'delete_name' => true, // Questo campo viene usato per il messaggio di conferma cancellazione
            ),
            array('name' => 'gc_name_2',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(2),
                'required' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1,
                'visible' => R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1),
            array('name' => 'gc_name_3',
                'type' => 'text',
                'size' => 80,
                'width' => array(null, 300),
                'label' => _('Nome') . getLangNameShort(3)),
            array('name' => 'gc_order',
                'type' => 'number',
                'required' => true,
                'width' => 50,
                'default' => 0,
                'label' => _('Ordinamento piano azione')),
            array('name' => 'gc_visible',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Visibile'),
                'visible' => true,
                'default' => true),
            array('name' => 'gc_total_only',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Solo totale'),
                'visible' => true),
            array('name' => 'gc_has_extradata',
                'type' => 'boolean',
                'width' => 80,
                'label' => _('Dati aggiuntivi settore piano azione'),
                'visible' => true),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova categorie/settore tabelle');
            case 'mod': return _('Modifica categorie/settore tabelle');
            case 'show': return _('Visualizza categorie/settore tabelle');
            case 'list': return _('Elenco categorie/settore tabelle');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare la categoria "%s"?'),
            'error' => _('Impossibile cancellare questa categoria, perchè vi sono dei dati ad esso collegati'));
    }

    public function getListWhere() {
        return "t0.gc_parent_id IS NOT NULL OR t0.do_id={$this->do_id}";
    }

}
