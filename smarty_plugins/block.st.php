<?php
/**
 * block.st.php - Smarty gettext block plugin
 *
 */

use R3Gis\ApplicationBundle\Utils\SymfonyServiceContainerInstance;

/**
 * Smarty block function, provides symfony translator support for smarty.
 *
 * The block content is the text that should be translated.
 */
function smarty_block_st($params, $text, &$smarty)
{
        $container = SymfonyServiceContainerInstance::get();
        $translator = $container->get('translator');
        
        return $translator->trans($text);
}
