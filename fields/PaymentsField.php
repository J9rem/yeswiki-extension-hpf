<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\TextField;

/**
 * @Field({"payments"})
 */
class PaymentsField extends TextField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->subType = "text";
        $this->readAccess = "@admins";
        $this->writeAccess = "@admins";
    }
}
