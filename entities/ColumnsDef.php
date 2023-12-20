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

namespace YesWiki\Hpf\Entity;

use ArrayAccess;
use Exception;
use Iterator;
use JsonSerializable;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;
use Throwable;

class ColumnsDef implements ArrayAccess,Iterator,JsonSerializable
{
    public const COLUMNS_SEARCH = [
        'email' => [
            'search' => "/^\s*(?:bf_)?(e-?)?mails?\s*$/i",
            'filter' => "/^\s*([^@ \\t\\n\\r]+@[^@ \\t\\n\\r]+\.[a-z0-9]{1,6})\s*$/",
            'prop' => 'bf_mail'
        ],
        'name' => [
            'search' => "/^\s*(?:bf_)?(?:noms?|names?)\s*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'prop' => 'bf_nom',
            'post' => [
                'trim',
                'strtolower',
                '\\'.self::class.'::rowUcWords'
            ]
        ],
        'firstname' => [
            'search' => "/^\s*(?:bf_)?(?:pr(?:e|é)noms?|firstnames?)\s*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'prop' => 'bf_prenom',
            'post' => [
                'trim',
                'strtolower',
                '\\'.self::class.'::rowUcWords'
            ]
        ],
        'town' => [
            'search' => "/^\s*(?:bf_)?(?:villes?|towns?)\s*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'prop' => 'bf_ville',
            'post' => [
                'trim',
                'strtolower',
                '\\'.self::class.'::rowUcWords'
            ]
        ],
        'postalcode' => [
            'search' => "/^\s*(?:bf_)?(?:codes?_?posta(?:l|aux)|postal_?codes?|CP)\s*$/i",
            'filter' => "/^\s*([0-9][0-9AB] ?[0-9]{0,3})\s*$/",
            'prop' => 'bf_code_postal'
        ],
        'dept' => [
            'search' => "/^\s*(?:bf_)?(?:dept|d.+parte?ments?).*$/i",
            'filter' => "/^\s*([0-9][0-9AB][0-9]?)\s*$/"
        ],
        'wantedStructure' => [
            'search' => "/^\s*structures?.*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'post' => [
                'trim'
            ]
        ],
        'value' => [
            'search' => "/^\s*(?:bf_)?(?:adh(?:é|e)sions?|montants?|sommes?|total).*$/i",
            'filter' => "/^\s*([0-9]+((?:\.|,)[0-9]+)?).*\s*$/",
            'post' => 'floatval'
        ],
        'number' => [
            'search' => "/^\s*(?:bf_)?(?:num(?:é|e)ros?|n°).*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'post' => [
                'trim'
            ]
        ],
        'comment' => [
            'search' => "/^\s*(?:bf_)?(?:comments?|commentaires?).*$/i",
            'filter' => "/^\s*(.*)\s*$/"
        ],
        'isGroup' => [
            'search' => "/^\s*(?:Est groupe.*|bf_is_group)\s*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'post' => [
                'trim',
                'strtolower',
                '\\'.self::class.'::extractX'
            ]
        ],
        'groupName' => [
            'search' => "/^\s*(Nom du groupe|bf_group).*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'post' => [
                'trim'
            ]
        ],
        'date' => [
            'search' => "/^\s*(Date|bf_date)\s*$/i",
            'post' => [
                '\\'.self::class.'::formatDate'
            ]
        ],
        'visibility' => [
            'search' => "/^\s*(visibilit).*$/i",
            'post' => [
                'trim',
                'strtolower',
                '\\'.self::class.'::extractX'
            ]
        ],
        'year' => [
            'search' => "/^\s*((?:ann(?:é|e)e|year)s?).*$/i",
            'filter' => "/^\s*(20[1-3][0-9])\s*$/",
            'post' => [
                'trim',
                'strtolower'
            ]
            ],
        'receivedbyhpf' => [
            'search' => "/^\s*(?:Encaiss(?:é|e|.+)s? par HPF).*\s*$/i",
            'filter' => "/^\s*(.*)\s*$/",
            'post' => [
                'trim',
                'strtolower',
                '\\'.self::class.'::extractX'
            ]
        ],
    ];

    /**
     * @var int
     * current index
     */
    protected $currentIdx;

    
    /**
     * @var array
     * data
     */
    protected $data;

    /**
     * @var array
     * cache keys
     */
    protected $cacheKeys;

    /**
     * @var int
     * idx in data
     */
    public function __construct()
    {
        $this->currentIdx = 0;
        $this->cacheKeys = [];
        $this->data = [];
    }

    /* === implements JsonSerializable === */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->data;
    }

    /* === implements ArrayAccess === */
    public function offsetExists($offset): bool
    {
        return is_string($offset) && array_key_exists($offset,$this->data);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (!is_string($offset) || !array_key_exists($offset,self::COLUMNS_SEARCH)){
            throw new Exception('The key "'.(is_string($offset) ? $offset : 'unknown').'" is not authorized !');
        }
        if (!is_integer($value)){
            throw new Exception('Value should be an integer !');
        }
        $this->data[$offset] = $value;
        $this->cacheKeys = array_keys($this->data);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if (is_string($offset) && in_array($offset,$this->data)){
            unset($this->data);
            $this->cacheKeys = array_keys($this->data);
        }
    }

    /* === implements Iterator === */

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->data[$this->cacheKeys[$this->currentIdx] ?? 0] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->cacheKeys[$this->currentIdx] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        ++$this->currentIdx;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->currentIdx = 0;
    }

    public function valid():bool
    {
        return array_key_exists($this->currentIdx,$this->cacheKeys)
            && array_key_exists($this->cacheKeys[$this->currentIdx],$this->data);
    }

    /* === static === */
    public static function rowUcWords($input): string
    {
        return is_string($input) ? ucwords($input,"-,. \t\r\n\f\v") : '';
    }
    public static function extractX($input): string
    {
        return (is_string($input) && strlen($input) > 0) ? 'x' : '';
    }
    public static function formatDate($input): string
    {
        $match = [];
        if (is_string($input) && preg_match("/^\s*=?\"?([0-9]{2}\/[0-9]{2}\/[0-9]{2,4})\"?\s*$/",$input,$match)){
            return $match[1];
        }
        try {
            if (!empty($input)){
                return PhpSpreadsheetDate::excelToDateTimeObject($input)->format('d/m/Y');
            }
        } catch (Throwable $th) {
        }
        return '';
    }
}
