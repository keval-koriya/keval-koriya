<?php

namespace Encora\AutoProfileImport\Model;

use Encora\AutoProfileImport\Model\ProfileConfig\Config;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\FlagManager;

class FileManager
{
    const GLOBAL_COMPANY_CODE = "0000";

    const INDICATION_FILE_NAME = '.apply';

    const LOG_FILE_NAME = 'last_run_log.txt';

    public function __construct(
        protected Config        $config,
        protected FlagManager   $flagManager,
        protected DirectoryList $directoryList,
        protected File          $driverFile,
    )
    {
    }

    public function getFileName(string $profileCode, string $companyCode = self::GLOBAL_COMPANY_CODE): string
    {
        $parentPath = $this->getParentDirectoryPath();
        $fileName = $profileCode . ".csv";
        return $parentPath . '/' . $companyCode . '/' . $fileName;
    }

    public function hasIndicationFile(string $companyCode = self::GLOBAL_COMPANY_CODE)
    {
        $parentPath = $this->getParentDirectoryPath();
        $filePath = $parentPath . '/' . $companyCode . '/' . self::INDICATION_FILE_NAME;
        return $this->driverFile->isExists($filePath);
    }

    public function removeIndicationFile(string $companyCode = self::GLOBAL_COMPANY_CODE)
    {
        $parentPath = $this->getParentDirectoryPath();
        $filePath = $parentPath . '/' . $companyCode . '/' . self::INDICATION_FILE_NAME;
        if ($this->driverFile->isExists($filePath)) {
            $this->driverFile->deleteFile($filePath);
        }
    }

    public function writeLogFile(string $content, string $companyCode = self::GLOBAL_COMPANY_CODE)
    {
        $parentPath = $this->getParentDirectoryPath();
        $filePath = $parentPath . '/' . $companyCode . '/' . self::LOG_FILE_NAME;
        $this->driverFile->filePutContents($filePath, $content, FILE_APPEND);
    }

    public function isChanged(string $profileCode, string $companyCode = self::GLOBAL_COMPANY_CODE): bool
    {
        $filePath = $this->getFileName($profileCode, $companyCode);
        if (!$this->driverFile->isExists($filePath)) {
            return true;
        }
        $hash = $this->getHash($profileCode, $companyCode);
        return $hash !== $this->generateHash($profileCode, $companyCode);
    }

    public function getHash(string $profileCode, string $companyCode = self::GLOBAL_COMPANY_CODE): string
    {
        $key = $this->generateHashKey($profileCode, $companyCode);
        return (string)$this->flagManager->getFlagData($key);
    }

    public function saveHash(string $profileCode, string $companyCode = self::GLOBAL_COMPANY_CODE): void
    {
        $key = $this->generateHashKey($profileCode, $companyCode);
        $newHash = $this->generateHash($profileCode, $companyCode);
        $this->flagManager->saveFlag($key, $newHash);
    }

    public function generateHash(string $profileCode, string $companyCode = self::GLOBAL_COMPANY_CODE): string
    {
        $filePath = $this->getFileName($profileCode, $companyCode);
        return (string)md5_file($filePath);
    }

    private function generateHashKey(string $profileCode, string $companyCode = self::GLOBAL_COMPANY_CODE): string
    {
        return 'encora_auto_import_' . $companyCode . '_' . $profileCode;
    }

    private function getParentDirectoryPath()
    {
        $filePath = $this->directoryList->getPath(DirectoryList::MEDIA) . '/downloadable/import';
        $filePath = str_replace($this->directoryList->getRoot() . '/', '', $filePath);
        return $filePath;
    }
}
