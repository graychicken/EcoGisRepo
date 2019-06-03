<?php

class lkp_global_category_main extends R3LookupBaseObject {

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
            array('name' => 'gc_name_1', // Nome
                'type' => 'text', // Tipo
                'size' => 80, // Dim. campo
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
                'width' => 60,
                'label' => _('Visibile'),
                'visible' => true,
                'default' => true),
            array('name' => 'gc_show_label',
                'type' => 'boolean',
                'width' => 60,
                'label' => _('Mostra label'),
                'visible' => true,
                'default' => true),
        );
        return $fields;
    }

    // The page title
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova macrocategoria/macrosettore tabelle');
            case 'mod': return _('Modifica macrocategoria/macrosettore tabelle');
            case 'show': return _('Visualizza macrocategoria/macrosettore tabelle');
            case 'list': return _('Elenco macrocategorie/macrosettore tabelle');
        }
    }

    public function getDeleteMessage($id, $name, $status) {
        return array('confirm' => _('Sei sicuro di voler cancellare la macrocategoria "%s"?'),
            'error' => _('Impossibile cancellare questa macrocategoria, perchè vi sono dei dati ad esso collegati'));
    }

    public function getListWhere() {
        return 'gc_parent_id IS NULL';
    }

    public function canAdd() {
        return R3AuthInstance::get()->hasPerm('ADD', 'ALL_LOOKUP');
    }

}
