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
use YesWiki\Core\YesWikiAction;

class HPFImportMemberShipAction extends YesWikiAction
{

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
        $data = [];
        $error = '';
        $fileName = '';
        if ($this->isFile()){
            try {
                $fileName = $_FILES['file']['name'];
                $type = $this->getType($fileName);
                $contents = $this->getContents($_FILES['file']);
                $data = $this->extractValues($fileName,$type,$contents);
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
     * test if file seems to be the right type
     * @param string $type
     * @return bool
     */
    protected function isSimilarToWantedType(string $type): bool
    {
        return !empty($type) && in_array(
            $type,
            [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'application/vnd.ms-excel.sheet.macroEnabled.12'
            ]
            );
    }

    /**
     * get content
     * @param array $params
     * @return string
     * @throws Exception
     */
    protected function getContents(array $params): string
    {
        if (empty($params['tmp_name']) || !is_file($params['tmp_name'])){
            throw new Exception("Error when importing file (tmp file is missing)");
        }
        return file_get_contents($params['tmp_name']);
    }

    /**
     * extract values from file
     * @param string $filename
     * @param string $type
     * @param string $content
     * @return array $data
     */
    protected function extractValues(string $filename, string $type, string $contents): array
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
     * check if coma separated
     * @param array $lines
     * @return bool
     */
    protected function isComaSeparated(array $lines): bool
    {
        for ($i=0; $i < count($lines); $i++) { 
            if (substr($lines[$i],0,1) == ','){
                return true;
            }
        }
        return false;
    }

    /**
     * convert content as lines
     * @param string $contents
     * @return array
     */
    protected function convertContensAsLines(string $contents): array
    {
        $lines = explode("\n",$contents);
        return array_map(
            function($line){
                return preg_replace('/\\r$/','',$line);
            },
            $lines
        );
    }

    /**
     * extract csv
     * @param array $lines
     * @param string $separator
     * @return array
     */
    protected function extractCsv(array $lines,string $separator): array
    {
        return array_map(
            function($line) use($separator){
                return str_getcsv($line,$separator);
            },
            $lines
        );
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
}
