<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-bazar-template-tableau-with-email
 */

namespace YesWiki\Hpf\Controller;

use YesWiki\Bazar\Field\EmailField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Hpf\Service\AreaManager;
use YesWiki\Core\YesWikiController;

class DisplayEmailController extends YesWikiController
{
    protected $areaManager;

    public function __construct(
        AreaManager $areaManager
    ) {
        $this->areaManager = $areaManager;
    }

    /**
     * display email on entries for static templates event if has been hidden because not admin
     * @param array $entries
     * @param array $arg
     * @return array $entries
     */
    public function displayEmailIfAdminOfParent(array $entries, ?array $arg): array
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

        $selectmembersdisplayfilters = (
            !empty($arg['selectmembersdisplayfilters']) &&
            in_array($arg['selectmembersdisplayfilters'], [true,1,"1","true"], true)
        );
        $entriesIds = array_map(function ($entry) {
            return $entry['id_fiche'] ?? "";
        }, $entries);
        $filteredEntries = $this->areaManager->filterEntriesFromParents(
            $entriesIds,
            false,
            $selectmembers,
            $selectmembersparentform,
            function (array $entry, array $form, string $suffix, $user) use (&$entries, $entriesIds) {
                return $this->appendEmailIfNeeded($entry,$form,$suffix,$user,$entries,$entriesIds);
            },
            $selectmembersdisplayfilters
        );
        if ($selectmembersdisplayfilters) {
            foreach ($entries as $idx => $entry) {
                $entryId = $entry['id_fiche'] ?? "";
                if (!empty($entryId) && !empty($filteredEntries[$entryId]) &&
                    !empty($filteredEntries[$entryId][AreaManager::KEY_FOR_PARENTS])) {
                    $entries[$idx][AreaManager::KEY_FOR_PARENTS] = $filteredEntries[$entryId][AreaManager::KEY_FOR_PARENTS];
                    if (!empty($filteredEntries[$entryId][AreaManager::KEY_FOR_AREAS])) {
                        $entries[$idx][AreaManager::KEY_FOR_AREAS] = $filteredEntries[$entryId][AreaManager::KEY_FOR_AREAS];
                    }
                    if (!empty($filteredEntries[$entryId]['html_data'])) {
                        $entries[$idx]['html_data'] = $filteredEntries[$entryId]['html_data'];
                    }
                }
            }
        }
        return $entries;
    }

    /**
     * append email on entry if needed
     * @param array $entry
     * @param array $form
     * @param string $suffix
     * @param $user
     * @param array &$entries,
     * @param array $entriesIds
     * @return array $entry
     */
    public function appendEmailIfNeeded(array $entry, array $form, string $suffix, $user, array &$entries, array $entriesIds){
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $entryKey = array_search($entry['id_fiche'] ?? '', $entriesIds);
        if ($entryKey !== false) {
            foreach ($form['prepared'] as $field) {
                $propName = $field->getPropertyName();
                if ($field instanceof EmailField && !empty($propName)) {
                    $fullEntry = $entryManager->getOne($entry['id_fiche'],false,null,true,true);
                    $email = $fullEntry[$propName] ?? "";
                    if (!isset($entries[$entryKey]['email.ids'])){
                        $entries[$entryKey]['email.ids'] = [];
                    }
                    if (!in_array($propName,$entries[$entryKey]['email.ids'])){
                        $entries[$entryKey]['email.ids'][] = $propName;
                    }
                    if (!empty($email) && isset($entries[$entryKey][$propName])) {
                        $entries[$entryKey][$propName] = $email;
                    }
                    $entry[$propName] = $email;
                    $entry['email.ids'] = $entries[$entryKey]['email.ids'];
                }
            }
        }
        return $entry;
    }
}
