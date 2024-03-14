<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-import-payments
 */

namespace YesWiki\Hpf\Exception;

use Exception;
use Throwable;

class ExceptionWithMessage extends Exception
{
    /**
     * var string
     */
    protected $typeForMessage;

    public function __construct(
        string $message = "",
        string $type = "danger",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->typeForMessage = $type;
    }

    /**
     * GETTER
     */
    public function getTypeForMessage(): string
    {
        return $this->typeForMessage;
    }
}
