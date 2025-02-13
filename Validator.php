<?php

namespace Encora\AutoProfileImport\Model\ProfileConfig;

use Encora\AutoProfileImport\Exception\InvalidProfileConfigurationException;
use Encora\ScheduledImportExport\Model\Options\ProductAttributes;
use Magento\ImportExport\Model\Import\Config;

class Validator
{
    public function __construct(
        protected Config            $defaultConfig,
        protected ProductAttributes $productAttributes
    )
    {
    }

    /**
     * @throws InvalidProfileConfigurationException
     */
    public function validate(array $config): void
    {
        if (!isset($config['profiles']) || !isset($config['sequence']) || !isset($config['dependencies'])) {
            throw new InvalidProfileConfigurationException(__('Invalid configuration. Missing profiles, sequence or dependencies'));
        }


        $validTypes = array_keys($this->defaultConfig->getEntities());
        $profileCodes = [];
        foreach ($config['profiles'] as $profile) {
            if (!isset($profile['code'])) {
                throw new InvalidProfileConfigurationException(__('Invalid configuration. Missing profile code.'));
            }
            if (in_array($profile['code'], $profileCodes)) {
                throw new InvalidProfileConfigurationException(__("Invalid configuration. Duplicate code '{$profile['code']}' defined."));
            }

            if (!isset($profile['type'])) {
                throw new InvalidProfileConfigurationException(__('Invalid configuration. Missing entity type.'));
            }
            if (!in_array($profile['type'], $validTypes)) {
                throw new InvalidProfileConfigurationException(__("Invalid entity type '{$profile['type']}' in profiles."));
            }

            if ($profile['type'] === 'product_attributes' && !isset($profile['attribute'])) {
                throw new InvalidProfileConfigurationException(__("Invalid configuration. Missing attribute code for '{$profile['type']}'."));
            }
            $validAttributes = array_column($this->productAttributes->getAllAttributeCodes(), 'value');
            if ($profile['type'] === 'product_attributes' && !in_array($profile['attribute'], $validAttributes)) {
                throw new InvalidProfileConfigurationException(__("Invalid attribute code '{$profile['attribute']}' for '{$profile['type']}'."));
            }

            $profileCodes[] = $profile['code'];
        }

        foreach ($config['sequence'] as $profileCode) {
            if (!in_array($profileCode, $profileCodes)) {
                throw new InvalidProfileConfigurationException(__("Invalid sequence. Profile code '{$profileCode}' not found in profiles."));
            }
        }

        foreach ($config['dependencies'] as $profileCode => $dependencies) {
            if (!in_array($profileCode, $profileCodes)) {
                throw new InvalidProfileConfigurationException(__("Invalid profile code in dependencies. Profile code '{$profileCode}' not found in profiles."));
            }

            foreach ($dependencies as $dependency) {
                if (!in_array($dependency, $profileCodes)) {
                    throw new InvalidProfileConfigurationException(__("Invalid dependencies. Profile code '{$dependency}' not found in profiles."));
                }
            }
        }
    }
}
