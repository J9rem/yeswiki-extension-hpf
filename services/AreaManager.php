<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-area-management
 */

namespace YesWiki\Hpf\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Hpf\Service\GroupManagementServiceInterface;
use YesWiki\Wiki;

class AreaManager
{
    public const KEY_FOR_PARENTS = "bf-custom-send-mail-parents";
    public const KEY_FOR_AREAS = "bf-custom-send-mail-areas";

    protected $areaAssociationCache;
    protected $areaAssociationForm;
    protected $entryManager;
    protected $departmentList;
    protected $departmentListName;
    protected $formManager;
    protected $groupManagementService;
    protected $listManager;
    protected $params;
    protected $postalCodeFieldName;
    protected $wiki;

    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        GroupManagementServiceInterface $groupManagementService,
        ListManager $listManager,
        ParameterBagInterface $params,
        Wiki $wiki
    ) {
        $this->areaAssociationCache = null;
        $this->areaAssociationForm = null;
        $this->departmentList = null;
        $this->departmentListName = null;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->groupManagementService = $groupManagementService;
        $this->listManager = $listManager;
        $this->params = $params;
        $this->postalCodeFieldName = null;
        $this->wiki = $wiki;
    }

    public function getAdminSuffix(): string
    {
        $suffix = !$this->params->has('GroupsAdminsSuffixForEmails') ? "" : $this->params->get('GroupsAdminsSuffixForEmails');
        return (empty($suffix) || !is_string($suffix)) ? "" : $suffix;
    }

    public function getAreaFieldName(): string
    {
        $fieldName = !$this->params->has('AreaFieldName') ? "" : $this->params->get('AreaFieldName');
        return (empty($fieldName) || !is_string($fieldName)) ? "" : $fieldName;
    }

    public function filterEntriesFromParents(
        array $entries,
        bool $entriesMode = true,
        string $mode = "only_members",
        string $selectmembersparentform = "",
        $callback = null,
        bool $appendDisplayData = false
    ) {
        $suffix = $this->getAdminSuffix();
        $areaData = ($mode == "members_and_profiles_in_area") ? null : [];
        $filteredEntries = $this->groupManagementService->filterEntriesFromParents(
            $entries,
            $entriesMode,
            $suffix,
            $selectmembersparentform,
            function (array &$formCache, string $formId, $user) use (&$areaData, $selectmembersparentform, $suffix) {
                if (is_null($areaData)) {
                    // lazy loading
                    $areaData = $this->getAreas($selectmembersparentform, $suffix, $user);
                }
                $this->extractExtraFields($formCache, $formId, $areaData['fieldForArea'] ?? null);
            },
            self::KEY_FOR_PARENTS,
            $callback,
            function (array $entry, array &$results, array $formData, $user) use (&$areaData, $selectmembersparentform, $suffix, $mode, $callback, $appendDisplayData) {
                if (is_null($areaData)) {
                    // lazy loading
                    $areaData = $this->getAreas($selectmembersparentform, $suffix, $user);
                }
                if ($mode == "members_and_profiles_in_area" && (!empty($formData['areaFields']) || !empty($formData['association']))) {
                    $this->processAreas($entry, $results, $formData, $areaData['areas'], $suffix, $user, $callback, $appendDisplayData);
                }
            },
            $appendDisplayData
        );
        if (!empty($filteredEntries) && $appendDisplayData) {
            foreach ($filteredEntries as $entryId => $entry) {
                if (!empty($entry[self::KEY_FOR_PARENTS])) {
                    $filteredEntries[$entryId]['html_data'] = $filteredEntries[$entryId]['html_data'] . " data-".self::KEY_FOR_PARENTS."=\"".htmlentities(implode(',', $entry[self::KEY_FOR_PARENTS]))."\"";
                }
                if (!empty($entry[self::KEY_FOR_AREAS])) {
                    $filteredEntries[$entryId]['html_data'] = $filteredEntries[$entryId]['html_data'] . " data-".self::KEY_FOR_AREAS."=\"".htmlentities(implode(',', $entry[self::KEY_FOR_AREAS]))."\"";
                }
            }
        }
        return $filteredEntries;
    }

    /**
     * @param array &$formCache
     * @param scalar $formId
     * @param null|EnumField $fieldForArea
     */
    public function extractExtraFields(array &$formCache, string $formId, $fieldForArea)
    {
        $formCache[$formId]['areaFields'] = [];
        $formCache[$formId]['association'] = null;
        $areaAssociationForm = $this->getAreaAssociationForm();
        foreach ($formCache[$formId]['form']['prepared'] as $field) {
            if ($fieldForArea &&
                $field instanceof EnumField &&
                $field->getLinkedObjectName() === $fieldForArea->getLinkedObjectName()) {
                $formCache[$formId]['areaFields'][] = $field;
            }
            if (!empty($areaAssociationForm['linkedObjectName']) &&
                $field instanceof EnumField &&
                $field->getLinkedObjectName() === $areaAssociationForm['linkedObjectName']) {
                $formCache[$formId]['association'] = $field;
            }
        }
    }

    public function updateFilters(?array $filters, ?string $renderedEntries, ?array $entries = null, array $arg = []): array
    {
        $selectmembers = (
            !empty($arg['selectmembers']) &&
                is_string($arg['selectmembers']) &&
                in_array($arg['selectmembers'], ["only_members","members_and_profiles_in_area"], true)
        ) ? $arg['selectmembers'] : "";
        $selectmembersparentform = (
            !empty($arg['selectmembersparentform']) &&
                strval($arg['selectmembersparentform']) == strval(intval($arg['selectmembersparentform'])) &&
                intval($arg['selectmembersparentform']) > 0
        ) ? $arg['selectmembersparentform'] : "";
        $isMapTemplate = (!empty($arg['template']) && $arg['template'] === "map");
        if (!empty($renderedEntries)) {
            extract($this->getParentsAreasFromRender($renderedEntries));
        } elseif (!empty($entries)) {
            extract($this->getParentsAreas($entries, $isMapTemplate));
        } else {
            $parents = [];
            $areas = [];
        }
        $formattedParents = [];
        $formattedAreas = [];
        foreach ($parents as $entryId => $list) {
            foreach ($list as $tagName) {
                if (!isset($formattedParents[$tagName])) {
                    $formattedParents[$tagName] = [
                        'nb' => 0
                    ];
                }
                $formattedParents[$tagName]['nb'] = $formattedParents[$tagName]['nb'] + 1;
            }
        }
        foreach ($areas as $entryId => $list) {
            foreach ($list as $tagName) {
                if (!isset($formattedAreas[$tagName])) {
                    $formattedAreas[$tagName] = [
                        'nb' => 0
                    ];
                }
                $formattedAreas[$tagName]['nb'] = $formattedAreas[$tagName]['nb'] + 1;
            }
        }
        if (!empty($formattedParents)) {
            $tabfacette = [];
            $tab = (empty($_GET['facette']) || !is_string($_GET['facette'])) ? [] : explode('|', $_GET['facette']);
            //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                if (count($tabdecoup)>1) {
                    $tabfacette[$tabdecoup[0]] = explode(',', trim($tabdecoup[1]));
                }
            }
            $parentList = [];
            foreach ($formattedParents as $tagName => $formattedParent) {
                $entry = $this->entryManager->getOne($tagName);
                $label = empty($entry['bf_titre']) ? $tagName : $entry['bf_titre'];
                $parentList[] = [
                    "id" => self::KEY_FOR_PARENTS.$tagName,
                    "name" => self::KEY_FOR_PARENTS,
                    "value" => strval($tagName),
                    "label" => $label,
                    "nb" => $formattedParent['nb'] ?? 0,
                    "checked" => (!empty($tabfacette[self::KEY_FOR_PARENTS]) && in_array($tagName, $tabfacette[self::KEY_FOR_PARENTS])) ? " checked" : ""
                ];
            }
            $encoding = mb_internal_encoding();
            usort($parentList, function ($a, $b) use ($encoding) {
                if ($a['label'] == $b['label']) {
                    return 0;
                }
                return strcmp(mb_strtoupper($a['label'], $encoding), mb_strtoupper($b['label'], $encoding));
            });
            $areaList = [];
            $options = [];
            if ($selectmembers == "members_and_profiles_in_area") {
                $areaFieldName = $this->getAreaFieldName();
                if (!empty($areaFieldName) && !empty($selectmembersparentform)) {
                    $fieldForArea = $this->formManager->findFieldFromNameOrPropertyName($areaFieldName, $selectmembersparentform);
                    if (!empty($fieldForArea)) {
                        $options = $fieldForArea->getOptions();
                    }
                }
            }
            foreach ($formattedAreas as $tagName => $formattedArea) {
                $label = empty($options[$tagName]) ? $tagName : $options[$tagName];
                $areaList[] = [
                    "id" => self::KEY_FOR_AREAS.$tagName,
                    "name" => self::KEY_FOR_AREAS,
                    "value" => strval($tagName),
                    "label" => $label,
                    "nb" => $formattedArea['nb'] ?? 0,
                    "checked" => (!empty($tabfacette[self::KEY_FOR_PARENTS]) && in_array($tagName, $tabfacette[self::KEY_FOR_PARENTS])) ? " checked" : ""
                ];
            }
            usort($areaList, function ($a, $b) use ($encoding) {
                if ($a['value'] == $b['value']) {
                    return 0;
                }
                return strcmp(mb_strtoupper($a['value'], $encoding), mb_strtoupper($b['value'], $encoding));
            });
            $newFilters = [];
            foreach ($filters as $key => $value) {
                $newFilters[$key] = $value;
            }
            if (!empty($areaList)) {
                $newFilters[self::KEY_FOR_AREAS] = [
                    "icon" => "",
                    "title" => _t('HPF_AREA_MNGT_AREAS_TITLES'),
                    "collapsed" => true,
                    "index" => count($newFilters),
                    "list" => $areaList
                ];
            }
            if (count($parentList) > 1) {
                $newFilters[self::KEY_FOR_PARENTS] = [
                    "icon" => "",
                    "title" => _t('HPF_AREA_MNGT_PARENTS_TITLES'),
                    "collapsed" => true,
                    "index" => count($newFilters),
                    "list" => $parentList
                ];
            }
            $filters = $newFilters;
        }
        return $filters;
    }

    private function getParentsAreasFromRender(string $renderedEntries): array
    {
        $parents = [];
        $areas = [];
        $tagOrComa = "[\p{L}\-_.0-9,]+" ; // WN_CAMEL_CASE_EVOLVED + ","
        $search = 'data-id_fiche="__tag__"';
        $search = preg_quote($search, "/");
        $search = str_replace('__tag__', '('.WN_CAMEL_CASE_EVOLVED.')', $search);

        $part1 = '__sep__data-__keyForParents__="__tagOrComa__"';
        $part1 = str_replace('__keyForParents__', self::KEY_FOR_PARENTS, $part1);
        $part1 = preg_quote($part1, "/");

        $part2 = '__sep__data-__keyForAreas__="__tagOrComa__"';
        $part2 = str_replace('__keyForAreas__', self::KEY_FOR_AREAS, $part2);
        $part2 = preg_quote($part2, "/");

        $search = "/{$search}(?:$part1$part2|$part1)/";
        $search = str_replace('__sep__', '[^>]+', $search);
        $search = str_replace('__tagOrComa__', "($tagOrComa)", $search);

        if (preg_match_all($search, $renderedEntries, $matches)) {
            foreach ($matches[0] as $idx => $match) {
                $tag = $matches[1][$idx];
                $parentsAsString = !empty($matches[2][$idx])
                    ? $matches[2][$idx]
                    : (
                        $matches[4][$idx]
                    );
                $areasAsString = $matches[3][$idx];
                $currentParents = empty($parentsAsString) ? [] : explode(',', $parentsAsString);
                if (!isset($parents[$tag])) {
                    $parents[$tag] = $currentParents;
                }
                $currentAreas = empty($areasAsString) ? [] : explode(',', $areasAsString);
                if (!isset($areas[$tag])) {
                    $areas[$tag] = $currentAreas;
                }
            }
        }
        return compact(['parents','areas']);
    }

    private function getParentsAreas(array $entries, bool $isMapTemplate): array
    {
        $parents = [];
        $areas = [];
        foreach ($entries as $entry) {
            if (!$isMapTemplate || (!empty($entry['bf_latitude']) && !empty($entry['bf_longitude']))) {
                foreach ([
                    self::KEY_FOR_PARENTS => 'parents',
                    self::KEY_FOR_AREAS => 'areas',
                ] as $key => $varName) {
                    $counter = -1;
                    $values = empty($entry[$key])
                        ? []
                        : (
                            is_string($entry[$key])
                            ? explode(',', $entry[$key])
                            : (
                                is_array($entry[$key])
                                ? (
                                    count(array_filter($entry[$key], function ($k) use (&$counter) {
                                        $counter = $counter + 1;
                                        return $k != $counter;
                                    }, ARRAY_FILTER_USE_KEY)) > 0
                                    ? array_keys(array_filter($entry[$key], function ($val) {
                                        return in_array($val, [1,true,"1","true"]);
                                    }))
                                    : $entry[$key]
                                )
                                : []
                            )
                        );
                    if (!isset($$varName[$entry['id_fiche']])) {
                        $$varName[$entry['id_fiche']] = $values;
                    }
                }
            }
        }
        return compact(['parents','areas']);
    }

    protected function getAreas($selectmembersparentform, $suffix, $user): array
    {
        $areaFieldName = $this->getAreaFieldName();
        $fieldForArea = null;
        $areas = [];
        if (!empty($areaFieldName) && !empty($selectmembersparentform)) {
            $fieldForArea = $this->formManager->findFieldFromNameOrPropertyName($areaFieldName, $selectmembersparentform);
            if (!empty($fieldForArea) && $fieldForArea instanceof EnumField) {
                $parentsWhereAdmin = $this->groupManagementService->getParentsWhereAdmin($selectmembersparentform, $suffix, $user['name']);
                foreach ($parentsWhereAdmin as $idFiche => $entry) {
                    if ($fieldForArea instanceof CheckboxField) {
                        $newAreas = $fieldForArea->getValues($entry);
                    } else {
                        $newAreas = !empty($entry[$fieldForArea->getPropertyName()]) ? [$entry[$fieldForArea->getPropertyName()]] : [];
                    }
                    foreach ($newAreas as $area) {
                        if (!in_array($area, array_keys($areas))) {
                            $areas[$area] = [];
                        }
                        $areas[$area][] = empty($entry['id_fiche']) ? $idFiche : $entry['id_fiche'];
                    }
                }
            }
        }
        return compact(['areas','fieldForArea']);
    }

    protected function processAreas(
        array $entry,
        array &$results,
        array $formData,
        array $areas,
        ?string $suffix,
        $user,
        $callback,
        bool $appendDisplayData
    ) {
        if (!empty($areas)) {
            $validatedAreas = [];
            // same Area
            $currentAreas = [];
            foreach ($formData['areaFields'] as $field) {
                if ($field instanceof CheckboxField) {
                    $newAreas = $field->getValues($entry);
                } else {
                    $newAreas = !empty($entry[$field->getPropertyName()]) ? [$entry[$field->getPropertyName()]] : [];
                }
                foreach ($newAreas as $area) {
                    if (!in_array($area, $currentAreas)) {
                        $currentAreas[] = $area;
                    }
                }
            }

            // check administrative areas if $currentAreas is empty
            if (empty($currentAreas) && !empty($formData['association'])) {
                if ($formData['association'] instanceof CheckboxField) {
                    $currentAdminAreas = ($formData['association'])->getValues($entry);
                } else {
                    $currentAdminAreas = !empty($entry[($formData['association'])->getPropertyName()]) ? [$entry[($formData['association'])->getPropertyName()]] : [];
                }
                $associations = $this->getAssociations();
                foreach ($currentAdminAreas as $area) {
                    if (!empty($associations['areas'][$area])) {
                        foreach ($associations['areas'][$area] as $dept) {
                            if (!in_array($dept, $currentAreas)) {
                                $currentAreas[] = $dept;
                            }
                        }
                    }
                }
            }

            $listOfAreas = array_keys($areas);
            $validatedAreas = array_filter($currentAreas, function ($area) use ($listOfAreas) {
                return in_array($area, $listOfAreas);
            });

            // check postal code then append
            $areaFromPostalCode = $this->extractAreaFromPostalCode($entry);
            if (!empty($areaFromPostalCode) &&
                in_array(intval($areaFromPostalCode), $listOfAreas) &&
                !in_array(intval($areaFromPostalCode), $validatedAreas)
            ) {
                $validatedAreas[] = intval($areaFromPostalCode);
            }

            // save areas
            if (!empty($validatedAreas)) {
                $this->groupManagementService->appendEntryWithData(
                    $entry,
                    $results,
                    $appendDisplayData ? self::KEY_FOR_AREAS : '',
                    $validatedAreas,
                    function ($internalEntry) use ($formData, $suffix, $user, $callback) {
                        return (is_callable($callback))
                          ? $callback($internalEntry, $formData['form'], $suffix, $user)
                          : $internalEntry;
                    }
                );
                if ($appendDisplayData) {
                    $validatedParentsIds = [];
                    foreach ($validatedAreas as $area) {
                        $parentsIds = $areas[$area];
                        foreach ($parentsIds as $parentId) {
                            if (!in_array($parentId, $validatedParentsIds)) {
                                $validatedParentsIds[] = $parentId;
                            }
                        }
                    }
                    if (!empty($validatedParentsIds)) {
                        $this->groupManagementService->appendEntryWithData(
                            $entry,
                            $results,
                            self::KEY_FOR_PARENTS,
                            $validatedParentsIds,
                            function ($internalEntry) use ($formData, $suffix, $user, $callback) {
                                return (is_callable($callback))
                                  ? $callback($internalEntry, $formData['form'], $suffix, $user)
                                  : $internalEntry;
                            }
                        );
                    }
                }
            }
        }
    }

    public function getPostalCodeFieldName(): string
    {
        if (is_null($this->postalCodeFieldName)) {
            $this->postalCodeFieldName = $this->params->get('PostalCodeFieldName');
            if (!is_string($this->postalCodeFieldName)) {
                $this->postalCodeFieldName = "";
            }
        }
        return $this->postalCodeFieldName;
    }

    private function getDepartmentListName(): string
    {
        if (is_null($this->departmentListName)) {
            $this->departmentListName = $this->params->get('departmentListName');
            if (!is_string($this->departmentListName)) {
                $this->departmentListName = "";
            }
        }
        return $this->departmentListName;
    }

    private function getDepartmentList(): array
    {
        if (is_null($this->departmentList)) {
            $departmentListName = $this->getDepartmentListName();
            if (!empty($departmentListName)) {
                $list = $this->listManager->getOne($departmentListName);
                if (!empty($list['label'])) {
                    $this->departmentList = $list['label'];
                    return $this->departmentList;
                }
            }
            $this->departmentList = [];
        }
        return $this->departmentList;
    }

    private function getFormIdAreaToDepartment(): string
    {
        $formId = $this->params->get('formIdAreaToDepartment');
        return (
            !empty($formId) &&
            is_scalar($formId) &&
            (strval($formId) == strval(intval($formId))) &&
            intval($formId)>0
        )
            ? strval($formId)
            : "";
    }

    private function getAreaAssociationForm(): array
    {
        if (is_null($this->areaAssociationForm)) {
            $this->areaAssociationForm = [];
            $formId = $this->getFormIdAreaToDepartment();
            $departmentListName = $this->getDepartmentListName();
            if (!empty($formId) && !empty($departmentListName)) {
                $form = $this->formManager->getOne($formId);
                if (!empty($form['prepared'])) {
                    $areaField = null;
                    $deptField = null;
                    foreach ($form['prepared'] as $field) {
                        if (!$areaField &&
                            $field instanceof EnumField &&
                            !empty($field->getLinkedObjectName()) &&
                            $field->getLinkedObjectName() !== $departmentListName) {
                            $areaField = $field;
                        } elseif (!$deptField &&
                            $field instanceof EnumField &&
                            !empty($field->getLinkedObjectName()) &&
                            $field->getLinkedObjectName() === $departmentListName) {
                            $deptField = $field;
                        }
                    }
                    if ($areaField && $deptField) {
                        $this->areaAssociationForm = [
                            'form' => $form,
                            'field' => $areaField,
                            'formId' => $formId,
                            'linkedObjectName' => $areaField->getLinkedObjectName(),
                            'deptField' => $deptField
                        ];
                    }
                }
            }
        }
        return $this->areaAssociationForm;
    }

    public function getAssociations(): array
    {
        if (is_null($this->areaAssociationCache)) {
            $this->areaAssociationCache = [];
            $formData = $this->getAreaAssociationForm();
            if (!empty($formData)) {
                $entries = $this->entryManager->search([
                    'formsIds' => [$formData['formId']]
                ]);
                if (!empty($entries)) {
                    $areaPropName = ($formData['field'])->getPropertyName();
                    $deptPropName = ($formData['deptField'])->getPropertyName();
                    foreach ($entries as $entry) {
                        $area = (!empty($entry[$areaPropName]) && is_string($areaPropName))
                            ? explode(',', $entry[$areaPropName])[0]
                            : "";
                        if (!empty($area)) {
                            $depts = (!empty($entry[$deptPropName]) && is_string($deptPropName))
                                ? explode(',', $entry[$deptPropName])
                                : [];
                            foreach ($depts as $dept) {
                                if (!isset($this->areaAssociationCache['areas'])) {
                                    $this->areaAssociationCache['areas'] = [];
                                }
                                if (!isset($this->areaAssociationCache['areas'][$area])) {
                                    $this->areaAssociationCache['areas'][$area] = [];
                                }
                                if (!in_array($dept, $this->areaAssociationCache['areas'][$area])) {
                                    $this->areaAssociationCache['areas'][$area][] = $dept;
                                }
                                if (!isset($this->areaAssociationCache['depts'])) {
                                    $this->areaAssociationCache['depts'] = [];
                                }
                                if (!isset($this->areaAssociationCache['depts'][$dept])) {
                                    $this->areaAssociationCache['depts'][$dept] = [];
                                }
                                if (!in_array($area, $this->areaAssociationCache['depts'][$dept])) {
                                    $this->areaAssociationCache['depts'][$dept][] = $area;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->areaAssociationCache;
    }

    public function extractAreaFromPostalCode(array $entry): string
    {
        $departmentList = $this->getDepartmentList();
        if (!empty($departmentList)) {
            $postalCodeName = $this->getPostalCodeFieldName();
            $postalCode = (empty($entry[$postalCodeName]) || !is_string($entry[$postalCodeName])) ? '' : $entry[$postalCodeName];
            $postalCode = str_replace(" ", "", trim($postalCode));
            if (strlen($postalCode) === 5) {
                $twoChars = substr($postalCode, 0, 2);
                if (!empty($departmentList[intval($twoChars)])) {
                    return $twoChars;
                }
                $threeChars = substr($postalCode, 0, 3);
                if (!empty($departmentList[intval($threeChars)])) {
                    return $threeChars;
                }
            }
        }
        return "" ;
    }
}
