<?php

/**
 * Sales order API
 *
 * @category ShipStream
 * @package  ShipStream_Sync
 */
class ShipStream_Sync_Model_Order_Api extends Mage_Sales_Model_Api_Resource
{
    /**
     * Retrieve array of columns in order flat table.
     *
     * @param null|object|array $filters
     * @param array $cols
     * @return array
     */
    public function selectFields($filters, $cols = ['increment_id', 'updated_at'])
    {
        $orders = [];
        $collection = Mage::getModel("sales/order")->getCollection();
        $collection->getSelect()
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns($cols);

        /** @var Mage_Api_Helper_Data $apiHelper */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_attributesMap['order']);

        try {
            foreach ($filters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }

        /** @var Mage_Sales_Model_Order $order */
        foreach ($collection as $order) {
            $orders[] = $order->getData();
        }

        return $orders;
    }
}
