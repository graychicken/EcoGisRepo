<?php  /* UTF-8 FILE: òàèü */

class lkp_stat_type extends R3LookupBaseObject {

    protected $table = 'stat_type';                   // Nome tabella
    protected $checkForeignKey = true;                          // Se true effettua un controllo preventivo dei lookup per cambiare il bottone di cancellazione
    protected $checkDomian = true;
    //protected $UUID = 'bby_uuid';
    public function canAdd() {
        return false;
    }
    public function canDel() {
        return false;
    }
  
    public function defFields() {
        $fields = array(
            array('name'=>'st_id',
                  'type'=>'number',                                                                                           
                  'is_primary_key'=>true),
            /*array('name'=>'do_id',              
                  'type'=>'domain'),*/
            array('name'=>'st_parent_id',
                  'type'=>'lookup',
                  'label'=>_('Principale'),
                  'required'=>true,
                  'width'=>200,
                  'lookup'=>array('table'=>$this->table, 'list_field'=>'st_title_short_<LANG>', 'alias'=>'get_name', 'pk'=>array('st_parent_id', 'st_id'), 'where'=>"st_parent_id IS NULL AND (do_id IS NULL OR do_id={$this->do_id})"),
                  'filter'=>array('type'=>'select', 'fields'=>array('st_id', 'st_title_short_<LANG>'), 'where'=>"st_parent_id IS NULL AND (do_id IS NULL OR do_id={$this->do_id})", 'orderby'=>'st_order, st_title_short_<LANG>')
                  ),
            array('name'=>'st_order',
                  'type'=>'number',
                  'required'=>true,
                  'default'=>0,
                  'width'=>40,
                  'label'=>_('Ordinamento')),
            array('name'=>'st_code',
                  'type'=>'text',
                  'required'=>true,
                  'label'=>_('Codice')),
            array('name'=>'st_title_short_1',      // Nome
                  'type'=>'text',           
                  'required'=>true,         
                  'label'=>_('Titolo corto') . getLangNameShort(1)),
            array('name'=>'st_title_short_2',
                  'type'=>'text',         
                  'label'=>_('Titolo corto') . getLangNameShort(2),
                  'required'=>R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1,
                  'visible'=>R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1),
            array('name'=>'st_title_long_1',      // Nome
                  'type'=>'text',           
                  'label'=>_('Titolo lungo') . getLangNameShort(1),
                  'list'=>false),
            array('name'=>'st_title_long_2',
                  'type'=>'text',         
                  'label'=>_('Titolo lungo') . getLangNameShort(2),
                  'visible'=>R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1,
                  'list'=>false),
            array('name'=>'st_has_absolute_data',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Dati assoluti')),
            array('name'=>'st_udm_1',      // Nome
                  'type'=>'text',           
                  'label'=>_('Unità di misura assoluta') . getLangNameShort(1),
                  'list'=>false),
            array('name'=>'st_udm_2',
                  'type'=>'text',         
                  'label'=>_('Unità di misura assoluta') . getLangNameShort(2),
                  'visible'=>R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1,
                  'list'=>false),
            array('name'=>'st_has_relative_data',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Dati relativi')),
	    array('name'=>'st_udm_relative_1',      // Nome
                  'type'=>'text',           
                  'label'=>_('Unità di misura relativa') . getLangNameShort(1),
                  'list'=>false),
            array('name'=>'st_udm_relative_2',
                  'type'=>'text',         
                  'label'=>_('Unità di misura relativa') . getLangNameShort(2),
                  'visible'=>R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1,
                  'list'=>false),
				  
            array('name'=>'st_descr_1',      // Nome
                  'type'=>'memo',           
                  'label'=>_('Descrizione superiore') . getLangNameShort(1),
                  'list'=>false),
            array('name'=>'st_descr_2',
                  'type'=>'memo',         
                  'label'=>_('Descrizione superiore') . getLangNameShort(2),
                  'visible'=>R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1,
                  'list'=>false),
	    array('name'=>'st_lower_descr_1',      // Nome
                  'type'=>'memo',           
                  'label'=>_('Descrizione inferiore') . getLangNameShort(1),
                  'list'=>false),
            array('name'=>'st_lower_descr_2',
                  'type'=>'memo',         
                  'label'=>_('Descrizione inferiore') . getLangNameShort(2),
                  'visible'=>R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1)>1,
                  'list'=>false),
            array('name'=>'st_enable',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Attiva (calcolata)')),
            array('name'=>'st_visible',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Visibile (su sito pubblico)')),
                          
            /* Deprecato
             * array('name'=>'st_detailaudit_only',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Attiva solo con audit di dettaglio')),*/
            /* Deprecato
             * array('name'=>'st_has_region_data',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Statistica con dati regionali')),       */       
             array('name'=>'st_has_province_data',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Statistica con dati provinciali')),
             array('name'=>'st_has_municipality_data',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Statistica con dati comunali')),
            array('name'=>'st_has_year',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Statistica con periodo temporale')),
            array('name'=>'st_has_building_purpose_use',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_("Filtro per dest.uso")),
            array('name'=>'st_has_building_build_year',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Filtro per periodo costruzione')),
            array('name'=>'st_render_preview_as_grid',
                  'type'=>'boolean',
                  'width'=>50,
                  'label'=>_('Rendering anteprima con griglia')),
            );
        return $fields;
    }
       
    // The page title
    public function getPageTitle() {
        switch($this->request['act']) {
            case 'add': return _('Nuova statistica');
            case 'mod': return _('Modifica statistica');
            case 'show': return _('Visualizza statistica');
            case 'list': return _('Elenco statistiche');
        }
    }
    
    public function getDeleteMessage($id, $name, $status) {
        return array('confirm'=>_("Sei sicuro di voler cancellare la statistica \"%s\"?"),
                     'error'=>_("Impossibile cancellare questa statistica"));
    }

    public function getListWhere() {
        return 't0.st_parent_id IS NOT NULL';
    }
    
}


?>