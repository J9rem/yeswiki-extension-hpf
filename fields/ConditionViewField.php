<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-condition-view-field
 */

namespace YesWiki\Hpf\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\LabelField;

/**
 * @Field({"conditionview"})
 */
class ConditionViewField extends LabelField
{
    public const FIELD_CONDITION_FIELD_NAME = 4;
    public const FIELD_CONDITION_FIELD_WAITED_VALUE = 6;

    protected $conditionFieldName;
    protected $conditionFieldWaitedValue;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->conditionFieldName = !empty($values[self::FIELD_CONDITION_FIELD_NAME]) && is_string($values[self::FIELD_CONDITION_FIELD_NAME])
            ? trim($values[self::FIELD_CONDITION_FIELD_NAME])
            : '';
        $this->maxChars = '';
        
        $this->conditionFieldWaitedValue = !empty($values[self::FIELD_CONDITION_FIELD_WAITED_VALUE]) && is_string($values[self::FIELD_CONDITION_FIELD_WAITED_VALUE])
            ? trim($values[self::FIELD_CONDITION_FIELD_WAITED_VALUE])
            : '';
    }

    protected function renderInput($entry)
    {
        return '';
    }

    // Render the show view of the field
    protected function renderStatic($entry)
    {
        return (!empty($this->conditionFieldName) &&
            is_array($entry) &&
            array_key_exists($this->conditionFieldName, $entry) &&
            strval($entry[$this->conditionFieldName]) == $this->conditionFieldWaitedValue)
            ? parent::renderStatic($entry)
            : '';
    }
}
