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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Alternativeupdatej9rem\Entity\DataContainer;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Hpf\Service\AreaManager;
use YesWiki\Core\YesWikiController;

class DisplayEmailController extends YesWikiController  implements EventSubscriberInterface
{
    protected $areaManager;

    public function __construct(
        AreaManager $areaManager
    ) {
        $this->areaManager = $areaManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'auj9.sendmail.filterentries' => 'checkIfFilterEntries',
            'auj9.sendmail.filterAuthorizedEntries' => 'filterAuthorizedEntries'
        ];
    }

    public function checkIfFilterEntries($event)
    {
        $eventData = $event->getData();
        if (!empty($eventData) && is_array($eventData) && isset($eventData['dataContainer']) && ($eventData['dataContainer'] instanceof DataContainer)) {
            $data = $eventData['dataContainer']->getData();
            if (!$data['isAdmin']) {
                $suffix = $this->areaManager->getAdminSuffix();
                if (empty($suffix)) {
                    $data['errorMessage'] = '(only for admins)';
                }
            }
            if (empty($data['errorMessage'])){
                $params = $this->getParams();
                if (!$data['isAdmin'] || !empty($params['selectmembers'])){
                    $data['canOverrideAdminRestriction'] = false;
                    $data['callbackIfNotOverridden'] = function($contacts,$callback) use ($params){
                        $this->areaManager->filterEntriesFromParents(
                            $contacts,
                            false,
                            $params['selectmembers'],
                            $params['selectmembersparentform'],
                            function ($entry, $form, $suffix, $user) use($callback){
                                return $callback($entry, $form);
                            }
                        );
                    };
                }
            }
            $eventData['dataContainer']->setData($data);
        }
    }

    public function filterAuthorizedEntries($event)
    {
        $eventData = $event->getData();
        if (!empty($eventData) && is_array($eventData) && isset($eventData['dataContainer']) && ($eventData['dataContainer'] instanceof DataContainer)) {
            $data = $eventData['dataContainer']->getData();
            if ($data['isAdmin']){
                $data['filteredEntriesIds'] = $data['entriesIds'];
            } else {
                $params = $data['params'];
                if (empty($params['selectmembers']) ||
                    !in_array($params['selectmembers'], ["only_members","members_and_profiles_in_area"])) {
                    $data['filteredEntriesIds'] = [];
                } else {
                    $selectmembersparentform = (empty($params['selectmembersparentform']) ||
                        intval($params['selectmembersparentform']) != $params['selectmembersparentform'] ||
                        intval($params['selectmembersparentform']) < 0)
                        ? ""
                        : $params['selectmembersparentform'];
                    $entriesIds = $data['entriesIds'];
                    $entries = $this->areaManager->filterEntriesFromParents($entriesIds, false, $params['selectmembers'], $selectmembersparentform);
                    $data['filteredEntriesIds'] = array_keys($entries);
                }
            }
            $eventData['dataContainer']->setData($data);
        }
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

    private function getParams(): array
    {
        $selectmembers = filter_input(INPUT_POST, 'selectmembers', FILTER_UNSAFE_RAW);
        $selectmembers = in_array($selectmembers, ["members_and_profiles_in_area","only_members"], true) ? $selectmembers : "";
        $selectmembersparentform = (!empty($_POST['selectmembersparentform']) && is_scalar($_POST['selectmembersparentform'])
            && strval($_POST['selectmembersparentform']) == intval($_POST['selectmembersparentform']) && intval($_POST['selectmembersparentform']) > 0)
            ? strval($_POST['selectmembersparentform']) : "";
        return compact([
            'selectmembers',
            'selectmembersparentform'
        ]);
    }
}
