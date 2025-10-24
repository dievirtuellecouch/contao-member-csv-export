<?php

$GLOBALS['BE_MOD']['accounts']['member']['export'] = [\Websailing\MemberExportBundle\Service\MemberExport::class, 'generate'];

$GLOBALS['MEMBER_EXPORT_FORMATS'] = [
    'csv' => [\Websailing\MemberExportBundle\Service\MemberExport::class, 'exportCsv'],
    'excel5' => [\Websailing\MemberExportBundle\Service\MemberExport::class, 'exportExcel5'],
    'excel2007' => [\Websailing\MemberExportBundle\Service\MemberExport::class, 'exportExcel2007'],
];

