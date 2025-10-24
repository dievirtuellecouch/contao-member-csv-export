<?php

// Add a global operation to trigger the export UI in the Member backend module
// Link points to key=export which is handled via BE_MOD callback in this bundle.
if (!isset($GLOBALS['TL_DCA']['tl_member']['list']['global_operations'])) {
    $GLOBALS['TL_DCA']['tl_member']['list']['global_operations'] = [];
}

$GLOBALS['TL_DCA']['tl_member']['list']['global_operations']['member_export'] = [
    'label'      => ['Export', 'Mitglieder exportieren'],
    'href'       => 'key=export',
    'class'      => 'header_icon',
    'icon'       => 'theme_export.svg',
    'attributes' => 'onclick="Backend.getScrollOffset()"',
];
