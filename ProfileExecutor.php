<?php

namespace Encora\AutoProfileImport\Model;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;
use Magento\ScheduledImportExport\Model\Observer;
use Magento\ScheduledImportExport\Model\Scheduled\Operation;

class ProfileExecutor
{
    public function __construct(
        protected ProfilesManager $profilesManager,
        private State             $state,
        private Observer          $scheduleObserver,
    )
    {
    }

    /**
     * @param $profileCode
     * @param $companyCode
     * @return false|mixed
     */
    public function execute($profileCode, $companyCode)
    {
        $id = $this->profilesManager->getIdByCode($profileCode, $companyCode);
        if (!$id) {
            return false;
        }
        return $this->executeById($id);
    }

    /**
     * @param int $profileId
     * @return false|mixed
     */
    public function executeById(int $profileId)
    {
        $result = false;
        try {
            return true;
            $result = $this->state->emulateAreaCode(Area::AREA_GLOBAL, function () use ($profileId) {
                $schedule = new DataObject();
                $schedule->setJobCode(Operation::CRON_JOB_NAME_PREFIX . $profileId);
                return $this->scheduleObserver->processScheduledOperation($schedule, true);
            });
        } catch (Exception $exception) {
        }
        return $result;
    }
}
