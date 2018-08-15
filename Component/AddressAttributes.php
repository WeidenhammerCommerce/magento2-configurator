<?php

namespace CtiDigital\Configurator\Component;

use CtiDigital\Configurator\Api\LoggerInterface;
use CtiDigital\Configurator\Exception\ComponentException;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Eav\Model\AttributeRepository;
use Magento\Customer\Setup\CustomerSetupFactory;
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
     * @var CustomerSetupFactory
     */
    protected $customerSetup;

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
        CustomerSetupFactory $customerSetupFactory,
        Attribute $attributeResource
    ) {
        $this->attributeConfigMap = array_merge($this->attributeConfigMap, $this->customerConfigMap);
        $this->customerSetup = $customerSetupFactory;
        $this->attributeResource = $attributeResource;
        parent::__construct($log, $objectManager, $eavSetup, $attributeRepository);
    }

    /**
     * @param array $attributeConfigurationData
     */
    protected function processData($attributeConfigurationData = null)
    {
        try {
            foreach ($attributeConfigurationData[$this->alias] as $attributeCode => $attributeConfiguration) {
                $this->processAttribute($attributeCode, $attributeConfiguration);
                $this->addAdditionalValues($attributeCode, $attributeConfiguration);
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    /**
     * Adds necessary additional values to the attribute. Without these, values can't be saved
     * to the attribute and it won't appear in any forms.
     *
     * @param $attributeCode
     * @param $attributeConfiguration
     */
    protected function addAdditionalValues($attributeCode, $attributeConfiguration)
    {
        if (!isset($attributeConfiguration['used_in_forms']) ||
            !isset($attributeConfiguration['used_in_forms']['values'])) {
            $attributeConfiguration['used_in_forms'] = $this->defaultForms;
        }

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetup->create();
        try {
            $attribute = $customerSetup->getEavConfig()
                ->getAttribute($this->entityTypeId, $attributeCode)
                ->addData([
                    'attribute_set_id' => \Magento\Customer\Api\AddressMetadataInterface::ATTRIBUTE_SET_ID_ADDRESS,
                    'attribute_group_id' => 2,
                    'used_in_forms' => $attributeConfiguration['used_in_forms']['values']
                ]);
            $this->attributeResource->save($attribute);
        } catch (LocalizedException $e) {
            $this->log->logError(sprintf(
                'Error applying additional values to %s: %s',
                $attributeCode,
                $e->getMessage()
            ));
        } catch (\Exception $e) {
            $this->log->logError(sprintf(
                'Error saving additional values for %s: %s',
                $attributeCode,
                $e->getMessage()
            ));
        }
    }
}
