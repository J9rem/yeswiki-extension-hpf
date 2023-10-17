<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf;

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Groupmanagement\Controller\GroupController;
use YesWiki\Hpf\Service\AreaManager;

class __BazarListeAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $newArg = [];
        /* === Feature UUID : hpf-bazar-template-list-no-empty === */
        if (!empty($arg['template']) && $arg['template'] == "list-no-empty") {
            $newArg['dynamic'] = true;
        }
        /* === end of Feature UUID : hpf-bazar-template-list-no-empty === */
        /* === Feature UUID : hpf-area-management === */
        if (!empty($arg['template']) && $arg['template'] == "tableau-with-email.tpl.html" && $this->formatBoolean($arg,false,'dynamic')) {
            if ($this->getService(TemplateEngine::class)->hasTemplate('@bazar/entries/index-dynamic-templates/table.twig')){
                $vars = array_slice($arg,0);
                $vars['template'] = 'table';
                $varsCopy = array_slice($vars,0);
                $output = '';
                $bazarListeAction = null;
                if (file_exists('tools/bazar/actions/__BazarListeAction.php')) {
                    $bazarListeAction = $this->getService(Performer::class)->createPerformable([
                        'filePath' => 'tools/bazar/actions/__BazarListeAction.php',
                        'baseName' => '__BazarListeAction'
                    ],
                    $vars,
                    $output);
                }
                if (!is_null($bazarListeAction)){
                    $newArg = array_merge($newArg,$bazarListeAction->formatArguments($varsCopy));
                }
                $newArg['template'] = 'table';
                $newArg['istablewithemail'] = true;
            } else {
                $newArg['dynamic'] = false;
            }
            return $newArg;
        }
        if ($this->wiki->services->has(GroupController::class)){
            $selectmembers = (
                !empty($arg['selectmembers']) &&
                    is_string($arg['selectmembers']) &&
                    in_array($arg['selectmembers'], ["only_members","members_and_profiles_in_area"], true)
            ) ? $arg['selectmembers'] : "";

            $selectmembersdisplayfilters = (
                !empty($arg['selectmembersdisplayfilters']) &&
                in_array($arg['selectmembersdisplayfilters'], [true,1,"1","true"], true)
            );
            // keep that for compatibility with sendmail
            if (!empty($arg['template']) && $arg['template'] == "send-mail") {
                $newArgs['dynamic'] = true;
                $newArgs['pagination'] = -1;
                $arg['dynamic'] = true;
                $arg['pagination'] = -1;
            }
            if (($arg['template'] ?? '') === 'video'){
                $newArgs['dynamic'] = true;
                $arg['dynamic'] = true;
            }

            return $newArg + $this->getService(GroupController::class)->defineBazarListeActionParams(
                $arg,
                $_GET ?? [],
                function (bool $isDynamic, bool $isAdmin, array $_arg) use ($selectmembers, $selectmembersdisplayfilters) {
                    $replaceTemplate = !$isDynamic && !empty($selectmembers) ;
                    $options = ['selectmembers' => $selectmembers];
                    if ($selectmembersdisplayfilters) {
                        $groups = $this->formatArray($_GET['groups'] ?? $_arg['groups'] ?? null);
                        if (!$isDynamic) {
                            $groupicons = $this->formatArray($_arg['groupicons'] ?? null);
                            $titles = $this->formatArray($_GET['titles'] ?? $_arg['titles'] ?? null);
                            array_unshift($groups, AreaManager::KEY_FOR_PARENTS);
                            array_unshift($groupicons, "");
                            array_unshift($titles, _t('HPF_AREA_MNGT_PARENTS_TITLES'));
                            if ($selectmembers == "members_and_profiles_in_area") {
                                array_unshift($groups, AreaManager::KEY_FOR_PARENTS);
                                array_unshift($groupicons, "");
                                array_unshift($titles, _t('HPF_AREA_MNGT_AREAS_TITLES'));
                            }
                            $options['groups'] = $groups;
                            $options['groupicons'] = $groupicons;
                            $options['titles'] = $titles;
                            $options['areaManager'] = $this->getService(AreaManager::class);
                        } elseif (empty($groups)) {
                            // force groups for layout
                            $options['groups'] = [AreaManager::KEY_FOR_PARENTS];
                        }
                    }
                    return compact(['replaceTemplate','options']);
                }
            );
        }
        /* === end of Feature UUID : hpf-area-management === */
        return $newArg;
    }

    public function run()
    {
    }
}
