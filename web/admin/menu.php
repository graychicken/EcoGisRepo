<?php

require_once(R3_LIB_DIR . 'simplemenu.php');
if (isset($hasMenu) && $hasMenu == true) {
    $smarty->assign('HeaderName', 'header_w_menu');
} else {
    $smarty->assign('HeaderName', 'header_no_menu');
}

// Menu settings
$menu = new pSimpleMenu();

/* ------------------------------ Menu Header ------------------------------ */

/* General Node */
if ($auth->hasPerm('SHOW', 'MAP') || $auth->hasPerm('SHOW', 'BUILDING') || $auth->hasPerm('SHOW', 'STREET_LIGHTING')) {
    $menu->addMainItem('general', _('Generale'));
}

/* General Node */
if ($auth->hasPerm('SHOW', 'STATISTIC')) {
    $menu->addMainItem('statistic', _('Statistiche'));
}



/* General Node */
$menu->addMainItem('paes', _('Patto dei sindaci'));

if ($auth->hasPerm('SHOW', 'SIMULATION') && $auth->hasPerm('SHOW', 'ACTION_CATALOG')) {
    $menu->addMainItem('simulation', _('Simulazioni'));
}

/* Config Node */
if ($auth->hasPerm('SHOW', 'LOOKUP')) {
    $menu->addMainItem('config', _('Configurazione'));
}

/* Admin Node */
if ($auth->hasPerm('SHOW', 'USER_SETTINGS') ||
        $auth->hasPerm('SHOW', 'USER_MANAGER')) {
    $menu->addMainItem('admin', _('Amministrazione'));
}

if ($auth->hasPerm('SHOW', 'HELP') ||
        $auth->hasPerm('SHOW', 'ABOUT')) {
    $menu->addMainItem('help', _('Aiuto'));
}

/* Language Node */
if ($auth->hasPerm('CHANGE', 'LANGUAGE') && $numLanguages > 1) {
    $menu->addMainItem('language', _('Lingua'));
}

/* ------------------------------ Menu Body ------------------------------ */

// Return the appropriate R3MenuNavigate string
function R3MenuNavigate($page, $on, $params = null) {
    global $framesetReload;

    if (is_array($params)) {
        $s = '';
        foreach ($params as $key => $val) {
            $s .= "&amp;$key=" . $urlencode($val);
        }
        $params = $s;
    } else {
        $params = '&amp;' . $params;
    }
    if ($framesetReload) {
        return "R3MenuNavigate(this, 'app_manager.php?page=$page&amp;on=$on$params', '_top')";
    } else {
        return "R3MenuNavigate(this, '$page.php?on={$on}{$params}')";
    }
}

if ($auth->hasPerm('SHOW', 'MAP')) {
    $menu->addSimpleItem('general', 'map', array('label' => _('Apri mappa'), 'js' => "openGisClient('" . GC_URL . "')"));
}

if ($auth->hasPerm('SHOW', 'BUILDING')) {
    $menu->addSimpleItem('general', 'building_list', array('label' => _('Edifici'), 'js' => R3MenuNavigate('list', 'building', 'init')));
}
if ($auth->hasPerm('SHOW', 'STREET_LIGHTING')) {
    $menu->addSimpleItem('general', 'street_lighting_list', array('label' => _('Illuminazione stradale'), 'js' => R3MenuNavigate('list', 'street_lighting', 'init')));
}


if ($auth->hasPerm('SHOW', 'STATISTIC')) {
    $menu->addSimpleItem('statistic', 'generic_building_statistic', array('label' => _('Statistiche edifici'), 'js' => R3MenuNavigate('edit', 'generic_building_statistic', 'init')));
}
// paes items
if ($auth->hasPerm('IMPORT', 'SEAP') || $auth->hasPerm('SHOW', 'IMPORT_SEAP')) {
    if ($auth->getParam('mu_id') == '') {
        $menu->addSimpleItem('paes', 'import_seap', array('label' => _('Import PAES'), 'js' => R3MenuNavigate('list', 'import_seap', 'init')));
    } else {
        $menu->addSimpleItem('paes', 'import_seap', array('label' => _('Import PAES'), 'js' => R3MenuNavigate('edit', 'import_seap', 'init')));
    }
}
if ($auth->hasPerm('SHOW', 'GLOBAL_STRATEGY')) {
    if ($auth->getParam('mu_id') == '') {
        $menu->addSimpleItem('paes', 'global_strategy', array('label' => _('Parametri principali'), 'js' => R3MenuNavigate('list', 'global_strategy', 'init')));
    } else {
        $menu->addSimpleItem('paes', 'global_strategy', array('label' => _('Parametri principali'), 'js' => R3MenuNavigate('edit', 'global_strategy', 'init')));
    }
}
if ($auth->hasPerm('SHOW', 'GLOBAL_RESULT')) {
    $menu->addSimpleItem('paes', 'global_result', array('label' => _('Inventario emissioni'), 'js' => R3MenuNavigate('list', 'global_result', 'init')));
}

if ($auth->hasPerm('SHOW', 'GLOBAL_PLAIN')) {
    $menu->addSimpleItem('paes', 'global_plain', array('label' => _("Piano di azione"), 'js' => R3MenuNavigate('list', 'global_plain', 'init')));
}

if ($auth->hasPerm('SHOW', 'GLOBAL_PLAIN_ACTION')) {
    $menu->addSimpleItem('paes', 'global_plain_action', array('label' => _('Azioni PAES'), 'js' => R3MenuNavigate('list', 'global_plain_action', 'init')));
}

if ($auth->hasPerm('SHOW', 'SIMULATION') && $auth->hasPerm('SHOW', 'ACTION_CATALOG')) {
    $menu->addSimpleItem('simulation', 'action_catalog', array('label' => _('Catalogo azioni'), 'js' => R3MenuNavigate('list', 'action_catalog', 'init')));
    $menu->addSimpleItem('simulation', 'simulation_list', array('label' => _('Simulazioni'), 'js' => R3MenuNavigate('list', 'simulation', 'init')));
}


// - Config Items
if ($auth->hasPerm('SHOW', 'LOOKUP')) {
    if ($auth->hasPerm('SHOW', 'FRACTION')) {
        $menu->addSimpleItem('config', 'fraction', array('label' => _('Frazioni'), 'js' => R3MenuNavigate('lookup_list', 'fraction', 'init')));
    }
    $menu->addSimpleItem('config', 'street', array('label' => _('Stradario'), 'js' => R3MenuNavigate('lookup_list', 'street', 'init')));
    if ($auth->getConfigValue('APPLICATION', 'CATASTRAL_TYPE') == 'AUSTRIA') {
        $menu->addSimpleItem('config', 'cat_munic', array('label' => _('Comuni catastali'), 'js' => R3MenuNavigate('lookup_list', 'cat_munic', 'init')));
    }
    $menu->addItem('config', '', '-');

    $menu->addSimpleItem('config', 'building_type', array('label' => _("Tipologia costruttiva edificio"), 'js' => R3MenuNavigate('lookup_list', 'building_type', 'init')));
    $menu->addSimpleItem('config', 'purpose_use', array('label' => _("Destinazioni d'uso"), 'js' => R3MenuNavigate('lookup_list', 'purpose_use', 'init')));

    if (R3AuthInstance::get()->getConfigValue('APPLICATION', 'ENABLE_PUBLIC_SITE', 'F') == 'T') {
        $menu->addSimpleItem('config', 'stat_purpose_use', array('label' => _("Destinazioni d'uso (Pubblico)"), 'js' => R3MenuNavigate('lookup_list', 'stat_purpose_use', 'init')));
    }

    $menu->addSimpleItem('config', 'building_build_year', array('label' => _("Anni costruzione"), 'js' => R3MenuNavigate('lookup_list', 'building_build_year', 'init')));
    $menu->addSimpleItem('config', 'building_restructure_year', array('label' => _("Anni ristrutturazione"), 'js' => R3MenuNavigate('lookup_list', 'building_restructure_year', 'init')));

    $menu->addSimpleItem('config', 'device_type', array('label' => _("Tipologia impianto"), 'js' => R3MenuNavigate('lookup_list', 'device_type', 'init')));

    $menu->addSimpleItem('config', 'funding_type', array('label' => _('Tipologia finanziamento'), 'js' => R3MenuNavigate('lookup_list', 'funding_type', 'init')));

    $menu->addItem('config', '', '-');

    $menu->addSimpleItem('config', 'energy_source', array('label' => _('Tipo alimentazione'), 'js' => R3MenuNavigate('lookup_list', 'energy_source', 'init')));
    $menu->addSimpleItem('config', 'udm', array('label' => _('Unità di misura'), 'js' => R3MenuNavigate('lookup_list', 'udm', 'init')));
    $menu->addSimpleItem('config', 'energy_source_udm', array('label' => _('Fattori di conversione'), 'js' => R3MenuNavigate('list', 'energy_source_udm', 'init')));
    if ($auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
        $menu->addSimpleItem('config', 'energy_source_udm_admin', array('label' => _('Fattori di conversione (Admin)'), 'js' => R3MenuNavigate('lookup_list', 'energy_source_udm_admin', 'init')));
    }
    $menu->addSimpleItem('config', 'heating_degree_days', array('label' => _('Gradi giorno termici'), 'js' => R3MenuNavigate('lookup_list', 'heating_degree_days', 'init')));
    $menu->addItem('config', '', '-');

    $menu->addSimpleItem('config', 'utility', array('label' => _('Fornitori di energia'), 'js' => R3MenuNavigate('list', 'utility', 'init')));

    if ($auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
        $menu->addSimpleItem('config', 'global_energy_source_main', array('label' => _('Tipo fonte (PAES)'), 'js' => R3MenuNavigate('lookup_list', 'global_energy_source_main', 'init')));
        $menu->addSimpleItem('config', 'global_energy_source', array('label' => _('Tipo alimentazione (PAES)'), 'js' => R3MenuNavigate('lookup_list', 'global_energy_source', 'init')));
        $menu->addSimpleItem('config', 'global_category_main', array('label' => _('Macrocategorie/macrosettori tabelle (PAES)'), 'js' => R3MenuNavigate('lookup_list', 'global_category_main', 'init')));
        $menu->addSimpleItem('config', 'global_category', array('label' => _('Categorie/settori tabelle (PAES)'), 'js' => R3MenuNavigate('lookup_list', 'global_category', 'init')));

        if ($auth->hasPerm('SHOW', 'GLOBAL_RESULT_TABLE_BUILDER')) {
            $menu->addSimpleItem('config', 'global_result_table_builder', array('label' => _('Tabelle inventario (PAES)'), 'js' => R3MenuNavigate('list', 'global_result_table_builder', 'init')));
        }
        $menu->addSimpleItem('config', 'global_action', array('label' => _('Azioni principali (PAES)'), 'js' => R3MenuNavigate('lookup_list', 'global_action', 'init')));

        if ($auth->hasPerm('SHOW', 'GLOBAL_ACTION_BUILDER')) {
            $menu->addSimpleItem('config', 'global_action_builder', array('label' => _('Azioni principali/categoria (PAES)'), 'js' => R3MenuNavigate('list', 'global_action_builder', 'init')));
        }
    }
    $menu->addSimpleItem('config', 'global_method', array('label' => _('Fonte dati inventario (PAES)'), 'js' => R3MenuNavigate('lookup_list', 'global_method', 'init')));

    if (R3AuthInstance::get()->getConfigValue('APPLICATION', 'ENABLE_PUBLIC_SITE', 'F') == 'T') {
        $menu->addItem('config', '', '-');
        $menu->addSimpleItem('config', 'stat_general', array('label' => _("Statistiche - Generale"), 'js' => R3MenuNavigate('edit', 'stat_general', 'init')));
        $menu->addSimpleItem('config', 'stat_type', array('label' => _('Statistiche - Definizioni'), 'js' => R3MenuNavigate('list', 'stat_type', 'init')));
    }
}

if ($auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->hasPerm('SET', 'CACHE')) {
    $menu->addSimpleItem('admin', 'cache', array('label' => _('Gestione cache'), 'js' => R3MenuNavigate('edit', 'cache', 'init')));
}
if ($auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->hasPerm('SHOW', 'CUSTOMER')) {
    $menu->addSimpleItem('admin', 'customer', array('label' => _('Gestione enti'), 'js' => R3MenuNavigate('list', 'customer', 'init')));
}
if ($auth->hasPerm('SHOW', 'MUNICIPALITY_COLLECTION')) {
    $menu->addSimpleItem('admin', 'municipality_collection', array('label' => _('Raggruppamenti di comuni'), 'js' => R3MenuNavigate('list', 'municipality_collection', 'init')));
}

if ($auth->hasPerm('SHOW', 'PUBLIC_USER')) {
    $menu->addSimpleItem('admin', 'public_user', array('label' => _('Utenti pubblici'), 'js' => R3MenuNavigate('list', 'public_user', 'init')));
}
if ($auth->hasPerm('SHOW', 'USER_SETTINGS')) {
    $menu->addSimpleItem('admin', 'personal_settings', array('label' => _('Impostazioni personali'), 'js' => "R3MenuNavigate(this, 'user_manager.php?obj=personal_settings')"));
}
if ($auth->hasPerm('SHOW', 'USER_MANAGER')) {
    $menu->addSimpleItem('admin', 'user_manager', array('label' => _('Gestione utenti'), 'js' => "R3MenuNavigate(this, 'user_manager.php?padding=0')"));
}

if ($auth->hasPerm('SHOW', 'HELP')) {
    $menu->addSimpleItem('help', 'help', array('label' => _('Guida'), 'js' => R3MenuNavigate('edit', 'help', 'init')));
}
if ($auth->hasPerm('SHOW', 'ABOUT')) {
    $menu->addSimpleItem('help', 'about', array('label' => _('Credits'), 'js' => R3MenuNavigate('edit', 'about', 'init')));
}

if ($auth->hasPerm('CHANGE', 'LANGUAGE')) {
    // - Language Items
    $menu->addSimpleItem('language', 'lng_italian', array('label' => _('Italiano'), 'js' => "R3MenuNavigate(this, 'set_app_param.php?kind=lang&amp;lang=it', '_top')"));
    $menu->addSimpleItem('language', 'lng_german', array('label' => _('Deutsch'), 'js' => "R3MenuNavigate(this, 'set_app_param.php?kind=lang&amp;lang=de', '_top')"));
}
$smarty->assign("menu", $menu->outputStrict($MenuItem, '', 'TD', false));
