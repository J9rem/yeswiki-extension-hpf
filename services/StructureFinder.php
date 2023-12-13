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

namespace YesWiki\Hpf\Service;

use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;

class StructureFinder
{
    protected $cache;
    protected $entryManager;
    protected $formManager;

    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager
    ) {
        
        $this->cache = [
            'structures' => [],
            'fields' => []
        ];
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
    }
    
    /**
     * find structure from department using cache
     * if several, return empty
     * @param string $deptcode
     * @param string $formId
     * @return string 
     */
    public function findStructureFromDept(string $deptcode, string $formId): string
    {
        if (empty($deptcode) || empty($formId)){
            return '';
        }
        if (!array_key_exists($deptcode,$this->cache['structures'])){
            $this->cache['structures'][$deptcode] = '';
            $field = $this->getField($formId);
            if (!empty($field)){
                $this->cache['structures'][$deptcode] = $this->findStructureFromDeptAndField($deptcode,$field);
            }
            
        }
        return $this->cache['structures'][$deptcode];
    }

    /**
     * get field from cache
     * @param string $formId
     * @return null|SelectEntryField
     */
    protected function getField(?string $formId): ?SelectEntryField
    {
        if (empty($formId)){
            return null;
        }
        if (!array_key_exists($formId,$this->cache['fields'])){
            $this->cache['fields'][$formId] = null;
            $form = $this->formManager->getOne($formId);
            if (!empty($form['prepared'])){
                foreach($form['prepared'] as $field){
                    if (empty($this->cache['fields'][$formId])
                        && $field instanceof SelectEntryField
                        && in_array($field->getName(),['bf_structure_locale_adhesion_groupe','bf_structure_locale_adhesion'])){
                        $this->cache['fields'][$formId] = $field;
                    }
                }
            }
        }
        return $this->cache['fields'][$formId];
    }

    /**
     * get structure form deptcode and field
     * @param string $deptcode
     * @param SelectEntryField|null $field
     * @param string $wantedStructure
     * @return string
     */
    public function findStructureFromDeptAndField(string $deptcode,?SelectEntryField $field,string $wantedStructure= ''): string
    {
        if (empty($field)){
            return '';
        }
        $options = $field->getOptions();
        $entries = array_map(
            function($entryId){
                return $this->entryManager->getOne($entryId);
            },
            array_keys($options)
        );
        $entries = array_filter(
            $entries,
            function($e) use ($deptcode){
                return !empty($e['checkboxListeDepartementsFrancais'])
                    && in_array($deptcode,explode(',',$e['checkboxListeDepartementsFrancais']));
            }
        );
        if (!empty($wantedStructure) && count($entries) > 1){
            $correspondingEntries = array_filter(
                $entries,
                function($e) use ($wantedStructure){
                    return $e['id_fiche'] == $wantedStructure;
                }
            );
            if (!empty($correspondingEntries)){
                return $wantedStructure;
            }
        }
        if (empty($entries) || count($entries) > 1) {
            return '';
        }
        $e = array_pop($entries);
        return $e['id_fiche'];
    }
}
