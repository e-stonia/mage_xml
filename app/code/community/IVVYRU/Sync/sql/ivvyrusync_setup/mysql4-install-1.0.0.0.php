<?php
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

/***CODE****/
$setup->addAttribute('customer', 'code', array(
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'Code',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
 $entityTypeId,
 $attributeSetId,
 $attributeGroupId,
 'code',
 '997'  
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'code');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->setData('sort_order','997');
$oAttribute->save();

/***PRICELIST****/
$setup->addAttribute('customer', 'pricelist', array(
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'Pricelist',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
 $entityTypeId,
 $attributeSetId,
 $attributeGroupId,
 'pricelist',
 '998'  
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'pricelist');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->setData('sort_order','998');
$oAttribute->save();

/***BALANCE****/
$setup->addAttribute('customer', 'balance', array(
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'Balance',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
 $entityTypeId,
 $attributeSetId,
 $attributeGroupId,
 'balance',
 '999'  
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'balance');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->setData('sort_order','999');
$oAttribute->save();

$installer->endSetup();

