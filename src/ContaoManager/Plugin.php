<?php

namespace Websailing\MemberExportBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Websailing\MemberExportBundle\MemberExportBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(MemberExportBundle::class)
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle', 'Codefog\HasteBundle\CodefogHasteBundle'])
        ];
    }
}

