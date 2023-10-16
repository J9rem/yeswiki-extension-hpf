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

namespace YesWiki\Hpf\Controller;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Hpf\Controller\DisplayEmailController;
use YesWiki\Hpf\Service\AreaManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Groupmanagement\Entity\DataContainer;
use YesWiki\Wiki;

class GroupController extends YesWikiController implements EventSubscriberInterface
{
    protected $areaManager;
    protected $displayEmailController;
    protected $entryManager;

    public function __construct(
        AreaManager $areaManager,
        DisplayEmailController $displayEmailController,
        EntryManager $entryManager,
        Wiki $wiki
    ) {
        $this->areaManager = $areaManager;
        $this->displayEmailController = $displayEmailController;
        $this->entryManager = $entryManager;
        $this->wiki = $wiki;
    }

    public static function getSubscribedEvents()
    {
        return [
            'groupmanagement.bazarliste.entriesready' => 'filterEntriesFromParentsAfter',
            'groupmanagement.bazarliste.afterdynamicquery' => 'keepOnlyFilteredEntriesFromParentsAfter',
        ];
    }

    public function filterEntriesFromParentsAfter($event)
    {
        $eventData = $event->getData();
        if (!empty($eventData) && is_array($eventData) && isset($eventData['dataContainer']) && ($eventData['dataContainer'] instanceof DataContainer)) {
            $bazarData = $eventData['dataContainer']->getData();
            $arg = $bazarData['param'] ?? [];
            $selectmembers = $arg['selectmembers'] ?? "";
            if (!empty($selectmembers) && isset($bazarData['fiches']) && is_array($bazarData['fiches'])) {
                $bazarData['fiches'] = $this->filterEntriesFromParents($bazarData['fiches'], $arg);
                $eventData['dataContainer']->setData($bazarData);
            }
        }
    }

    public function keepOnlyFilteredEntriesFromParentsAfter($event)
    {
        $selectmembersdisplayfilters = (
            !empty($_GET['selectmembersdisplayfilters']) &&
            in_array($_GET['selectmembersdisplayfilters'], [true,1,"1","true"], true)
        );
        $istablewithemail = filter_input(INPUT_GET,'istablewithemail',FILTER_VALIDATE_BOOL);

        if (!$this->wiki->UserIsAdmin() || $selectmembersdisplayfilters || $istablewithemail) {
            $selectmembers = (
                !empty($_GET['selectmembers']) &&
                    is_string($_GET['selectmembers']) &&
                    in_array($_GET['selectmembers'], ["only_members","members_and_profiles_in_area"], true)
            ) ? $_GET['selectmembers'] : "";
            if (!empty($selectmembers)) {
                $eventData = $event->getData();
                if (!empty($eventData) &&
                    is_array($eventData) &&
                    isset($eventData['response']) &&
                    method_exists($eventData['response'], 'getContent')) {
                    $response = $eventData['response'];
                    $status = $response->getStatusCode();
                    if ($status < 400) {
                        $content = $response->getContent();
                        $contentDecoded = json_decode($content, true);
                        if (!empty($contentDecoded) && !empty($contentDecoded['entries']) && is_array($contentDecoded['entries'])) {
                            $fieldMapping = $contentDecoded['fieldMapping'] ?? [];
                            $idFicheIdx = array_search("id_fiche", $fieldMapping);
                            if ($idFicheIdx !== false && $idFicheIdx > -1) {
                                $entries = array_filter(array_map(function ($entryData) use ($idFicheIdx) {
                                    $entryId = $entryData[$idFicheIdx] ?? "";
                                    if (!empty($entryId)) {
                                        $entry = $this->entryManager->getOne($entryId);
                                        if (!empty($entry['id_fiche'])) {
                                            return $entry;
                                        }
                                    }
                                    return [];
                                }, $contentDecoded['entries']), function ($entry) {
                                    return !empty($entry);
                                });

                                $entries = $this->filterEntriesFromParents($entries, [
                                    'selectmembers' => $selectmembers,
                                    'selectmembersparentform' => $_GET['selectmembersparentform'] ?? "",
                                    'selectmembersdisplayfilters' => $selectmembersdisplayfilters,
                                    'id' => $_GET['idtypeannonce'] ?? "",
                                    'istablewithemail' => $istablewithemail
                                ]);
                                $entriesIds = array_map(function ($entry) {
                                    return $entry['id_fiche'] ?? "";
                                }, $entries);
                                $contentDecoded['entries'] = array_values(array_filter($contentDecoded['entries'], function ($entry) use ($idFicheIdx, $entriesIds) {
                                    return !empty($entry[$idFicheIdx]) && in_array($entry[$idFicheIdx], $entriesIds);
                                }));
                                
                                $entriesIdsCorrespondance = [];
                                foreach ($contentDecoded['entries'] as $key => $value) {
                                    if (!empty($value[$idFicheIdx])){
                                        $entriesIdsCorrespondance[$value[$idFicheIdx]] = $key;
                                    }
                                }
                                foreach($entries as $entry){
                                    if (!empty($entry['id_fiche']) && !empty($entry['email.ids']) && isset($entriesIdsCorrespondance[$entry['id_fiche']])){
                                        foreach($entry['email.ids'] as $id){
                                            $idx = array_search($id, $fieldMapping);
                                            if ($idx !== false && $idx > -1){
                                                $contentDecoded['entries'][$entriesIdsCorrespondance[$entry['id_fiche']]][$idx] = $entry[$id] ?? '';
                                            }
                                        }
                                    }
                                }
                                if ($selectmembersdisplayfilters) {
                                    $contentDecoded['fieldMapping'][] = AreaManager::KEY_FOR_PARENTS;
                                    $contentDecoded['fieldMapping'][] = AreaManager::KEY_FOR_AREAS;
                                    $contentDecoded['entries'] = array_map(function ($entryData) use ($idFicheIdx, $entries) {
                                        if (!empty($entryData[$idFicheIdx]) && !empty($entries[$entryData[$idFicheIdx]])) {
                                            $entryData[] = implode(',', $entries[$entryData[$idFicheIdx]][AreaManager::KEY_FOR_PARENTS] ?? []);
                                            $entryData[] = implode(',', $entries[$entryData[$idFicheIdx]][AreaManager::KEY_FOR_AREAS] ?? []);
                                        } else {
                                            $entryData[] = "";
                                            $entryData[] = "";
                                        }
                                        return $entryData;
                                    }, $contentDecoded['entries']);
                                    if (isset($contentDecoded['filters']) && is_array($contentDecoded['filters'])) {
                                        $contentDecoded['filters'] = $this->areaManager->updateFilters($contentDecoded['filters'], null, $entries, [
                                            'selectmembers' => $selectmembers,
                                            'selectmembersparentform' => $_GET['selectmembersparentform'] ?? "",
                                            'template' => $_GET['template'] ?? ""
                                        ]);
                                    }
                                }

                                $response->setData($contentDecoded);
                            }
                        }
                    }
                }
            }
        }
    }

    public function filterEntriesFromParents(array $entries, array $arg): array
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
        

        if (!empty($selectmembers) && (!$this->wiki->UserIsAdmin() || $selectmembersdisplayfilters || $arg['istablewithemail'])) {
            $ids = $arg['id'] ?? null;
            if (empty($this->areaManager->getAdminSuffix()) || empty($ids)) {
                return [];
            } else {
                $ids = array_filter(is_array($ids) ? $ids : (is_string($ids) ? explode(',', $ids) : []), function ($id) {
                    return substr($id, 0, 4) != "http" && strval($id) == strval(intval($id));
                });
                if (empty($ids)) {
                    return [];
                } else {
                    return $this->areaManager->filterEntriesFromParents(
                        $entries,
                        true,
                        $selectmembers,
                        $selectmembersparentform,
                        function (array $entry, array $form, string $suffix, $user) use (&$entries, $entriesIds) {
                            return $this->displayEmailController->appendEmailIfNeeded($entry,$form,$suffix,$user,$entries,$entriesIds);
                        },
                        $selectmembersdisplayfilters
                    );
                }
            }
        }
        return $entries;
    }
}
