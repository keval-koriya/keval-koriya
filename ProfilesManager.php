<?php

namespace Encora\AutoProfileImport\Model;

use Encora\AutoProfileImport\Model\Config\ProfileConfig;
use Encora\AutoProfileImport\Model\ProfileConfig\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Registry;

class ProfilesManager
{
    public const ENTITY_CODE = 'entity_code';
    public const OTHER_LOCALE = 'other_locale';

    const COMMON_PROFILE_DATA = [
        'operation_type' => 'import',
        'behavior' => 'append',
        'start_time' => '00:00:00',
        'freq' => 'D',
        'force_import' => 0,
        'status' => 0,
        'is_success' => 2,
        'last_run_date' => null,
        'email_receiver' => 'general',
        'email_sender' => 'general',
        'email_template' => 'magento_scheduledimportexport_import_failed',
        'email_copy' => null,
        'email_copy_method' => 'bcc',
        'user_id' => 4,
        'is_system_generated' => 1
    ];

//    public const PROFILE_DATA = [
//        'bevel' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'bevel'
//        ],
//        'bevel_shape' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'bevel_shape'
//        ],
//        'catalog_brand' => [
//            'entity_type' => 'catalog_brand',
//            'attribute_code' => ''
//        ],
//        'color' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'color'
//        ],
//        'design' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'design'
//        ],
//        'frame_material_type' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'frame_material_type'
//        ],
//        'frame_type' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'frame_type'
//        ],
//        'grooving' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'grooving'
//        ],
//        'lens_color_coat' => [
//            'entity_type' => 'lens_color_coat',
//            'attribute_code' => ''
//        ],
//        'lens_instruction' => [
//            'entity_type' => 'lens_instructions',
//            'attribute_code' => ''
//        ],
//        'local_brand' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'local_brandcd'
//        ],
//        'local_coat' => [
//            'entity_type' => 'local_coat',
//            'attribute_code' => ''
//        ],
//        'local_color' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'color'
//        ],
//        'local_design_group' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'design_group'
//        ],
//        'local_lens' => [
//            'entity_type' => 'local_lens',
//            'attribute_code' => ''
//        ],
//        'local_special_treatment' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'special_treatment'
//        ],
//        'local_design_warranty' => [
//            'entity_type' => 'local_design_warranty',
//            'attribute_code' => ''
//        ],
//        'lens_type' => [
//            'entity_type' => 'product_attributes',
//            'attribute_code' => 'lens_type'
//        ]
//    ];
//
//    public const OTHER_LOCALE_PROFILE_DATA = [
//        'local_color', 'local_design_group', 'local_lens', 'local_coat'
//    ];
    private AdapterInterface $connection;

    public function __construct(
        protected FileManager        $fileManager,
        protected Config             $profileConfig,
        protected ResourceConnection $resourceConnection,
        protected Registry           $registry
    )
    {
        $this->connection = $this->resourceConnection->getConnection();
        $this->registry->register('execution_from_system_profile_manager', true);
    }

    public function adjustAll()
    {
        $companyCodes = $this->profileConfig->getCompanyCodes();
        foreach ($companyCodes as $companyCode) {
            $this->adjustByCompanyCode($companyCode);
        }
    }

    public function adjustByCompanyCode(string $companyCode)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('magento_scheduled_operations');

        $profiles = $this->profileConfig->getProfiles();
        foreach ($profiles as $profile) {
            $code = $profile['code'];
            $type = $profile['type'];
            $attribute = $profile['attribute'] ?? null;

            $profileName = $companyCode . '_' . $code;
            $profileCsvName = $code . '.csv';
            $filePath = $this->fileManager->getFileName($code, $companyCode);
            $fileConfig = [
                "_import_field_separator" => ";",
                "_import_multiple_value_separator" => ",",
                "server_type" => "upload_file",
                "attribute_code" => $attribute,
                "file_path" => $filePath,
                "file_name" => $profileCsvName,
                "import_images_file_dir" => "",
                "locale" => "en_US"];

            $profileData = array_merge(
                self::COMMON_PROFILE_DATA,
                [
                    'name' => $profileName,
                    'entity_type' => $type,
                    'file_info' => json_encode($fileConfig),
                    'details' => "System Generate Profile - " . ucwords(str_replace('_', ' ', $profileName))
                ]
            );

            $id = $this->getIdByName($profileName);
            if ($id) {
                $connection->update($tableName, $profileData, ['id = ?' => $id]);
            } else {
                $connection->insert($tableName, $profileData);
            }
        }
    }

    public function getIdByCode($profileCode, $companyCode)
    {
        $profileName = $companyCode . '_' . $profileCode;
        return $this->getIdByName($profileName);
    }

    public function getIdByName(string $name)
    {
        $output = $this->connection->select()
            ->from($this->connection->getTableName('magento_scheduled_operations'))
            ->columns(['id'])
            ->where('name = "' . $name . '"')
            ->where('is_system_generated = 1');
        $row = $this->connection->fetchRow($output);
        return $row['id'] ?? null;
    }
}
