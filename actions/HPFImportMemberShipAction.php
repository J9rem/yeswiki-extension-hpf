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

use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Hpf\Entity\ColumnsDef;

class HPFImportMemberShipAction extends YesWikiAction
{
    protected $entryManager;

    public function formatArguments($arg)
    {
        return([
            'college1' => $this->formatString($arg,'college1'),
            'college2' => $this->formatString($arg,'college2')
        ]);
    }

    protected function formatString(array $arg, string $key): string
    {
        return (!empty($arg[$key]) && is_string($arg[$key]))
            ? $arg[$key]
            : '';
    }

    public function run()
    {
        // only admins
        if (!$this->wiki->UserIsAdmin()){
            return $this->render('@templates/alert-message.twig',[
                'message' => _t('BAZ_NEED_ADMIN_RIGHTS'),
                'type' => 'danger'
            ]);
        }

        // get Services
        $this->entryManager = $this->getService(EntryManager::class);

        $data = [];
        $error = '';
        $fileName = '';
        if ($this->isFile()){
            try {
                $fileName = $_FILES['file']['name'];
                $type = $this->getType($fileName);
                $data = $this->extractValues($fileName,$type);
                $data = $this->cleanEmptyLines($data);
                $data = $this->extractData($data);
                $data = $this->appendCalculatedProps($data);
            } catch (Exception $th) {
                $error = $th->getMessage();
            }
        }
        return $this->render('@hpf/hpf-import-memberships-action.twig',compact(['data','error','fileName']));  
    }

    /**
     * test is File is furnish
     * use $_POST
     * @return bool
     */
    protected function isFile(): bool
    {
        return !empty($_FILES['file']['name']);
    }

    /**
     * check if file have validating ext and return type
     * @param string $name
     * @return string
     * @throws Exception
     */
    protected function getType(string $name): string
    {
        $match = [];
        if (!preg_match('/\.((?:csv|ods|xls(?:x|m)?))$/',$name,$match)){
            throw new Exception(_t('HPF_IMPORT_BAD_ERROR_FORMAT'));
        }
        return $match[1];
    }

    /**
     * extract values from file
     * @param string $filename
     * @param string $type
     * @return array $data
     */
    protected function extractValues(string $filename, string $type): array
    {
        switch ($type) {
            case 'csv':
            case 'ods':
            case 'xls':
            case 'xlsx':
            case 'xlsm':
                return $this->extractXls_X($filename,$_FILES['file']['tmp_name']);
                
            default:
                if (!empty($type)){
                    throw new Exception("type : \"$type\" is not supported !");
                }
                throw new Exception('"type" should not be empty !');
        }
    }

    /**
     * extractXlsx?
     * @param string $fileName
     * @param string $tmpFilename
     * @return array
     * @throws Exception
     */
    protected function extractXls_X(string $fileName,string $tmpFilename): array
    {
        $data = [];
        $tmpPath = sys_get_temp_dir();
        if (!in_array(substr($tmpPath,-1),['/','\\'])){
            $tmpPath .= DIRECTORY_SEPARATOR;
        }
        $filePath = $tmpPath.$fileName;
        if (is_file($filePath)){
            unlink($filePath);
            if (is_file($filePath)){
                throw new Exception("Not possible to delete previous tmp file !");   
            }
        }
        move_uploaded_file($tmpFilename, $filePath);
        if (!is_file($filePath)){
            throw new Exception("Not possible to create tmp file !");  
        }
        chmod($filePath, 0755);
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());
            // Get the highest row and column numbers referenced in the worksheet
            $highestRow = $worksheet->getHighestRow(); // e.g. 10
            $highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn); // e.g. 5
            for ($row = 0; $row < $highestRow; ++$row) {
                if (!isset($data[$row])){
                    $data[$row] = [];
                }
                for ($col = 0; $col < $highestColumnIndex; ++$col) {
                    $data[$row][$col] = $worksheet->getCellByColumnAndRow($col+1, $row+1)->getValue();
                }
            }
        } catch (Throwable $th) {
        } finally {
            try {
                if (is_file($filePath)){
                    unlink($filePath);
                }
            } catch (Throwable $th2) {
            }
        }
        return $data;
    }

    /**
     * remove lines with 3 empty celle on five first columns of eah rox
     * @param array $rows
     * @return array
     */
    protected function cleanEmptyLines(array $rows): array
    {
        $results = [];
        foreach ($rows as $row) {
            if (count($row) > 3){
                $nbEmptyCells = 0;
                for ($i=0; $i < 5; $i++) { 
                    if ($i < count($row) && empty($row[$i])){
                        $nbEmptyCells += 1;
                    }
                }
                if ($nbEmptyCells < 3){
                    $results[] = $row;
                }
            }
        }
        return $results;
    }

    /**
     * detect columns from first line
     * @param array $data
     * @return ColumnsDef
     * @throws Exception if not formatted as waited data
     */
    protected function detectColumns(array $data): ColumnsDef
    {
        $firstLine = $data[0];
        $availableIdx = array_keys($firstLine);

        $colDefs = new ColumnsDef();
        foreach (ColumnsDef::COLUMNS_SEARCH as $key => $colDef) {
            foreach ($availableIdx as $idx) {
                if (!isset($colDefs[$key]) && preg_match($colDef['search'],$firstLine[$idx])){
                    $colDefs[$key] = intval($idx);
                    $availableIdx = array_filter(
                        $availableIdx,
                        function ($i) use ($idx){
                            return $i != $idx;
                        }
                    );
                }
            }
        }
        if (empty($colDefs)){
            throw new Exception('Not possible to detec columns !');
        }
        return $colDefs;
    }

    /**
     * extract data
     * @param array $rows
     * @return array
     * @throws Exception if not formatted as waited data
     */
    protected function extractData(array $rows): array
    {
        $extracted = [];
        $colDef = $this->detectColumns($rows);
        foreach ($rows as $key => $row) {
            // not first line
            if ($key > 0){
                $extractedLine = [];
                foreach ($colDef as $key => $idx) {
                    $colDefFilter = ColumnsDef::COLUMNS_SEARCH[$key]['filter'] ?? '';
                    $match = [];
                    $extractedLine[$key] = (
                        isset($row[$idx])
                        && (
                            empty($colDefFilter)
                            || preg_match($colDefFilter,strval($row[$idx]),$match)
                        )
                    )
                    ? ($match[1] ?? $row[$idx])
                    : '';
                    $colDefPostAction = ColumnsDef::COLUMNS_SEARCH[$key]['post'] ?? '';
                    if (!empty($colDefPostAction)){
                        $extractedLine[$key] = call_user_func($colDefPostAction,$extractedLine[$key]);
                    }
                }
                $extracted[] = $extractedLine;
            }
        }
        return $extracted;
    }

    /**
     * append calculated props
     * @param array $data
     * @return array 
     */
    protected function appendCalculatedProps(array $data): array
    {
        foreach ($data as $key => $newValue){
            $data[$key]['isGroup'] = !empty($newValue['comment']) && preg_match('/^\\s*groupe?s?.*\\s*$/i',$newValue['comment']);
            $data[$key]['associatedEntry'] = $this->searchEntry($newValue, ['email'], $data[$key]['isGroup']);
            if (empty($data[$key]['associatedEntry'])){
                $data[$key]['associatedEntry'] = $this->searchEntry($newValue, ['name','firstname'], $data[$key]['isGroup']);
            }
        }
        return $data;
    }

    /**
     * search entry
     * @param array $newValue
     * @param array $searchOn
     * @param bool $isGroup
     * @return array $entry
     */
    protected function searchEntry(array $newValue, array $searchOn, bool $isGroup): array
    {
        $formId = $isGroup ? $this->arguments['college2'] : $this->arguments['college1'];
        if (empty($formId) || empty($newValue) || empty($searchOn)){
            return [];
        }
        $queries = [];
        foreach ($searchOn as $type) {
            if (!isset($newValue[$type]) || !isset(ColumnsDef::COLUMNS_SEARCH[$type]['prop'])){
                return [];
            }
            $queries[ColumnsDef::COLUMNS_SEARCH[$type]['prop']] = $newValue[$type];
        }

        $entries = $this->entryManager->search([
            'formsIds' => [
                $formId
            ],
            'queries' => $queries
        ]);
        return count($entries) > 0 ? $entries[array_key_first($entries)]: [];
    }
}
