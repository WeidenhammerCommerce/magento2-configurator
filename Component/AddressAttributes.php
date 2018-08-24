<?php

namespace CtiDigital\Configurator\Component;

use CtiDigital\Configurator\Api\LoggerInterface;
use CtiDigital\Configurator\Exception\ComponentException;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\ObjectManagerInterface;
use Magento\Eav\Model\AttributeRepository;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Model\ResourceModel\Attribute;

/**
 * Class CustomerAttributes
 * @package CtiDigital\Configurator\Model\Component
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class AddressAttributes extends Attributes
{
    protected $alias = 'customer_address';

    protected $entityTypeId = 'customer_address';

    protected $name = 'Address Attributes';

    protected $description = 'Component to create/maintain address attributes.';

    protected $customerConfigMap = [
        'label' => 'frontend_label',
        'sortOrder' => 'sort_order',
        'notice' => 'note',
        'default' => 'default_value',
        'size' => 'multiline_count',
        'prefer' => 'toggle',
        'type' => 'backend_type',
        'input' => 'frontend_input',
        'filterable' => 'is_filterable_in_grid',
        'searchable' => 'is_searchable_in_grid',
        'position' => 'sort_order',
        'source' => 'source_model',
        'visible' => 'is_visible',
        'required' => 'is_required',
        'system' =>'is_system',
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
            'adminhtml_customer_address',
            'customer_address_edit',
            'customer_register_address'
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
            foreach ($attributeConfigurationData[$this->alias] as $attributeCode => $attributeConfiguration) {
                $this->processAttribute($attributeCode, $attributeConfiguration);
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
                'attribute_set_id' => \Magento\Customer\Api\AddressMetadataInterface::ATTRIBUTE_SET_ID_ADDRESS,
                    'attribute_group_id' => 2,
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
