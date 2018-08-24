<?php

namespace CtiDigital\Configurator\Component;

use CtiDigital\Configurator\Api\LoggerInterface;
use CtiDigital\Configurator\Exception\ComponentException;
use Magento\Customer\Model\Customer;
use Magento\Framework\ObjectManagerInterface;
use Magento\Eav\Model\AttributeRepository;
use Magento\Eav\Setup\EavSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Model\ResourceModel\Attribute;

/**
 * Class CustomerAttributes
 * @package CtiDigital\Configurator\Model\Component
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class CustomerAttributes extends Attributes
{
    const DEFAULT_ATTRIBUTE_SET_ID = 1;
    const DEFAULT_ATTRIBUTE_GROUP_ID = 1;

    protected $alias = 'customer_attributes';
    protected $name = 'Customer Attributes';
    protected $description = 'Component to create/maintain customer attributes.';

    /**
     * @var string
     */
    protected $entityTypeId = Customer::ENTITY;

    protected $customerConfigMap = [
        'visible' => 'is_visible',
        'position' => 'sort_order'
    ];

    /**
     * @var Attribute
     */
    protected $attributeResource;

    /**
     * @var array
     */
    protected $defaultForms = [
        'values' => [
            'customer_account_create',
            'customer_account_edit',
            'adminhtml_checkout',
            'adminhtml_customer'
        ]
    ];

    public function __construct(
        LoggerInterface $log,
        ObjectManagerInterface $objectManager,
        EavSetup $eavSetup,
        AttributeRepository $attributeRepository,
        CustomerSetup $customerSetup,
        Attribute $attributeResource
    ) {
        $this->attributeConfigMap = array_merge($this->attributeConfigMap, $this->customerConfigMap);
        $this->attributeResource = $attributeResource;
        parent::__construct($log, $objectManager, $eavSetup, $attributeRepository);
        $this->eavSetup = $customerSetup;
    }

    /**
     * @param array $attributeConfigurationData
     */
    protected function processData($attributeConfigurationData = null)
    {
        try {
            foreach ($attributeConfigurationData['customer_attributes'] as $attributeCode => $attributeConfiguration) {
                $this->processAttribute($attributeCode, $attributeConfiguration);
                //$this->addAdditionalValues($attributeCode, $attributeConfiguration);
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    /**
     * @param $attributeCode
     * @param $attributeConfig
     */
    protected function processAttribute($attributeCode, array $attributeConfig)
    {
        $this->hasOptions = false;
        $updateAttribute = true;
        $attributeExists = false;
        $attributeArray = $this->eavSetup->getAttribute($this->entityTypeId, $attributeCode);
        if ($attributeArray && $attributeArray['attribute_id']) {
            $attributeExists = true;
            $this->log->logComment(sprintf('Attribute %s exists. Checking for updates.', $attributeCode));
            $updateAttribute = $this->checkForAttributeUpdates($attributeCode, $attributeArray, $attributeConfig);

            if (isset($attributeConfig['option'])) {
                $newAttributeOptions = $this->manageAttributeOptions($attributeCode, $attributeConfig['option']);
                $attributeConfig['option']['values'] = $newAttributeOptions;
                if ($this->hasOptions && sizeof($newAttributeOptions) > 0) {
                    $updateAttribute = true;
                }
            }
        }

        if ($updateAttribute) {

            if (!array_key_exists('user_defined', $attributeConfig)) {
                $attributeConfig['user_defined'] = 1;
            }

            $this->eavSetup->addAttribute(
                $this->entityTypeId,
                $attributeCode,
                $attributeConfig
            );

            if (!isset($attributeConfiguration['used_in_forms']) ||
                !isset($attributeConfiguration['used_in_forms']['values'])) {
                $attributeConfiguration['used_in_forms'] = $this->defaultForms;
            }

            $this->eavSetup->getEavConfig()->getAttribute($this->entityTypeId, $attributeCode)->addData([
                'attribute_set_id' => self::DEFAULT_ATTRIBUTE_SET_ID,
                'attribute_group_id' => self::DEFAULT_ATTRIBUTE_GROUP_ID,
                'used_in_forms' => $attributeConfiguration['used_in_forms']['values']
            ]);

            if ($attributeExists) {
                $this->log->logInfo(sprintf('Attribute %s updated.', $attributeCode));
                return;
            }

            $this->log->logInfo(sprintf('Attribute %s created.', $attributeCode));
        }
    }
}
