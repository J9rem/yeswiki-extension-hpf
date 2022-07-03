<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class HelloAssoService
{
    protected $params;
    private $wiki;

    public function __construct(ParameterBagInterface $params, Wiki $wiki)
    {
        $this->params = $params;
        $this->wiki = $wiki;
    }
}
