<?php

namespace Encora\AutoProfileImport\Model\ProfileConfig;

use Encora\AutoProfileImport\Exception\InvalidProfileConfigurationException;
use Encora\AutoProfileImport\Model\ProfileConfig;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\SerializerInterface;

class Config extends DataObject
{
    /**
     * @throws InvalidProfileConfigurationException
     */
    public function __construct(
        protected SerializerInterface $serializer,
        protected Validator           $validator
    )
    {
        $config = $this->getProfileConfig();
        $this->validator->validate($config);
        return parent::__construct($config);
    }

    public function getProfiles()
    {
        return $this->getData('profiles');
    }

    public function getSequence()
    {
        return $this->getData('sequence');
    }

    public function getDependencies()
    {
        return $this->getData('dependencies');
    }

    public function getCompanyCodes()
    {
        return $this->getData('company_codes');
    }

    public function getProfileDependencies(string|array $profileCode, bool $includeSelf = true): array
    {
        if (is_string($profileCode)) {
            $profileCodes = [$profileCode];
        } else {
            $profileCodes = $profileCode;
        }

        $getDependencies = function ($profileCode) use (&$getDependencies) {
            $dependencies = $this->getDependencies();
            $profileDependencies = $dependencies[$profileCode] ?? [];
            $dependencies = $profileDependencies;
            foreach ($profileDependencies as $dependency) {
                $dependencies = array_merge($dependencies, $getDependencies($dependency));
            }
            return $dependencies;
        };

        $dependencies = [];
        foreach ($profileCodes as $profileCode) {
            $dependencies = array_merge($dependencies, $getDependencies($profileCode));
            if ($includeSelf) {
                $dependencies[] = $profileCode;
            }
        }

        $dependencies = array_unique($dependencies);
        $this->sortProfiles($dependencies);
        return $dependencies;
    }

    public function sortProfiles(array &$profiles)
    {
        $sequence = $this->getSequence();
        usort($profiles, function ($a, $b) use ($sequence) {
            $aIndex = array_search($a, $sequence);
            $bIndex = array_search($b, $sequence);
            return $aIndex - $bIndex;
        });
    }


    public function isEnabled(): bool
    {
        return true;
    }

    public function getProfileConfig()
    {
        $config = <<<JSON
{
    "company_codes": [
        "0000",
        "AG31"
    ],
    "profiles": [
        {
            "code": "bevel",
            "type": "product_attributes",
            "attribute": "bevel"
        },
        {
            "code": "bevel_shape",
            "type": "product_attributes",
            "attribute": "bevel_shape"
        },
        {
            "code": "frame_type",
            "type": "product_attributes",
            "attribute": "frame_type"
        },
        {
            "code": "catalog_brand",
            "type": "catalog_brand"
        }
    ],
    "sequence": [
        "bevel_shape",
        "frame_type",
        "bevel",
        "catalog_brand"
    ],
    "dependencies": {
        "bevel": [],
        "bevel_shape": ["bevel"],
        "frame_type": ["bevel_shape"],
        "catalog_brand": ["frame_type"]
    }
}
JSON;
        $config = $this->serializer->unserialize($config);
        return $config;
    }
}
