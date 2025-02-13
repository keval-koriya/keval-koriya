<?php

namespace Encora\AutoProfileImport\Model;

use Encora\AutoProfileImport\Model\ProfileConfig\Config;

class FileChangeApplier
{
    public function __construct(
        protected ProfileExecutor $profileExecutor,
        protected Config          $profileConfig,
        protected FileManager     $fileManager
    )
    {
    }

    public function apply(string|array $companyCode = null)
    {
        $companyCodes = $this->getCompanyCodes($companyCode);

        foreach ($companyCodes as $companyCode) {
            if (!$this->fileManager->hasIndicationFile($companyCode)) {
                continue;
            }
            $changedProfiles = $this->getChangedProfiles($companyCode);
            $dependentProfiles = $this->profileConfig->getProfileDependencies($changedProfiles);
            $this->processProfiles($dependentProfiles, $companyCode);
        }
    }

    private function getCompanyCodes(string|array $companyCode = null): array
    {
        if (is_string($companyCode)) {
            return [$companyCode];
        } elseif ($companyCode === null) {
            return $this->profileConfig->getCompanyCodes();
        } else {
            return $companyCode;
        }
    }

    private function processProfiles(array $profiles, string $companyCode)
    {
        $pendingProfiles = $profiles;
        $processedProfiles = [];
        foreach ($pendingProfiles as $key => $profile) {
            $result = $this->profileExecutor->execute($profile, $companyCode);
            if (!$result) {
                break;
            }
            $this->fileManager->saveHash($profile, $companyCode);
            $processedProfiles[] = $profile;
            unset($pendingProfiles[$key]);
        }
        $this->fileManager->removeIndicationFile($companyCode);
        $logMessage = $this->getLogMessage($pendingProfiles, $processedProfiles, $companyCode);
        $this->fileManager->writeLogFile($logMessage, $companyCode);
    }

    private function getLogMessage(array $pendingProfiles, array $processedProfiles, string $companyCode): string
    {
        $todayDate = date("Y-m-d H:i:s");

        $message = "\n\n\n===========================\n\nAs of $todayDate";
        if (count($pendingProfiles) > 0) {
            $message .= "\n\nBelow Profiles are pending to be processed for company code $companyCode: \n";
            foreach ($pendingProfiles as $profile) {
                $message .= "\n$profile";
            }
        } else {
            $message .= "\n\nNo Pending Profiles for company code $companyCode";
        }

        if (count($processedProfiles) > 0) {
            $message .= "\n\nBelow Profiles are processed for company code $companyCode: \n";
            foreach ($processedProfiles as $profile) {
                $message .= "\n$profile";
            }
        } else {
            $message .= "\n\nNo Profiles are processed for company code $companyCode";
        }

        $message .= "\n\n For more details, please refer to the import history section from admin.";
        return $message;
    }

    private function getChangedProfiles(string $companyCode): array
    {
        $changedProfiles = [];
        foreach ($this->profileConfig->getProfiles() as $profile) {
            $profileCode = $profile['code'];
            if ($this->fileManager->isChanged($profileCode, $companyCode)) {
                $changedProfiles[] = $profileCode;
            }
        }
        return $changedProfiles;
    }
}
