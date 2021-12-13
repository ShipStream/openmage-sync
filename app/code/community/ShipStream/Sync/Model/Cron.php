<?php

class ShipStream_Sync_Model_Cron
{
    const LOG_FILE = 'shipstream_cron.log';

    /**
     * Synchronize Magento inventory with the warehouse inventory
     *
     * @return void
     * @throws Exception
     */
    public function fullInventorySync($sleep = TRUE)
    {
        if ( ! Mage::helper('shipstream/api')->isConfigured()) {
            return;
        }
        if ($sleep) {
            sleep(random_int(0, 60)); // Avoid stampeding the server
        }
        Mage::log('Beginning inventory sync.', Zend_Log::DEBUG, self::LOG_FILE);
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write'); /** @var $db Magento_Db_Adapter_Pdo_Mysql */
        $_source = $this->_getSourceInventory();
        try {
            if ( ! empty($_source) && is_array($_source)) {
                foreach (array_chunk($_source, 5000, TRUE) as $source) {
                    $db->beginTransaction();
                    try {
                        $target = $this->_getTargetInventory(array_keys($source));
                        //Get qty of order items that are in processing state and not submitted to shipstream
                        $processingQty = $this->_getProcessingOrderItemsQty(array_keys($source));
                        foreach ($source as $sku => $qty) {
                            if ( ! isset($target[$sku])) continue;
                            $syncQty = floatval($qty);
                            if(isset($processingQty[$sku]))
                                $syncQty = floatval($qty) - floatval($processingQty[$sku]['qty']); //subtract processing that is not submitted
                            if ($syncQty === floatval($target[$sku]['qty'])) continue;
                            Mage::log("SKU: $sku remote qty is $qty and local is {$target[$sku]['qty']}", Zend_Log::DEBUG, self::LOG_FILE);
                            $stockItem = Mage::getModel('cataloginventory/stock_item')->load($target[$sku]['stock_item_id']); /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
                            if ( ! $stockItem->getId()) {
                                throw new Mage_Core_Exception(Mage::helper('shipstream')->__('Cannot load the stock item for the product with "%s" SKU.', $sku));
                            }
                            if ($stockItem->getManageStock() && Mage::helper('cataloginventory')->isQty($stockItem->getTypeId())) {
                                $oldQty = $stockItem->getQty();
                                $stockItem->setQty($qty);
                                if ($oldQty < 1 && ! $stockItem->getIsInStock() && $stockItem->getCanBackInStock() && $stockItem->getQty() > $stockItem->getMinQty()) {
                                    $stockItem->setIsInStock(true)
                                        ->setStockStatusChangedAutomaticallyFlag(true);
                                }
                                $stockItem->save();
                            }
                        }
                        $db->commit();
                    } catch (Exception $e) {
                        $db->rollback();
                        throw $e;
                    }
                }
            }
        } catch (Exception $exception) {
        }
        Mage::helper('shipstream/api')->callback('unlockOrderImport');
        if (isset($exception)) { // Instead of }finally{
            throw $exception;
        }
    }

    /**
     * Retrieve inventory from the warehouse
     *
     * @throws Exception
     * @return array
     */
    protected function _getSourceInventory()
    {
        $data = Mage::helper('shipstream/api')->callback('inventoryWithLock');
        return empty($data['skus']) ? [] : $data['skus'];
    }

    /**
     * Retrieve Magento inventory
     *
     * @param array $skus
     * @return array
     */
    protected function _getTargetInventory(array $skus)
    {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $columns = ['sku' => 'p.sku', 'stock_item_id' => 'si.item_id', 'qty' => 'si.qty'];
        $select = $db->select()->forUpdate(TRUE)
            ->from(['p' => $resource->getTableName('catalog/product')], $columns)
            ->join(['si' => $resource->getTableName('cataloginventory/stock_item')], 'p.entity_id = si.product_id', [])
            ->where('si.stock_id = ?', Mage_CatalogInventory_Model_Stock::DEFAULT_STOCK_ID)
            ->where('p.sku IN (?)', $skus);

        return $db->fetchAssoc($select);
    }

    /**
     * Retrieve Magento order items qty that are in processing state and not submitted to shipstream
     * @param array $sku
     * @return array
     */
    protected function _getProcessingOrderItemsQty(array $skus){

        $orderStates = [
            Mage_Sales_Model_Order::STATE_COMPLETE,
            Mage_Sales_Model_Order::STATE_CLOSED,
            Mage_Sales_Model_Order::STATE_CANCELED
        ];
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $columns = ['sku' => 'soi.sku', 'qty' => 'sum(soi.qty_ordered)'];
        $select = $db->select()->forUpdate(TRUE)
            ->from(['soi' => $resource->getTableName('sales/order_item')], $columns)
            ->join(['so' => $resource->getTableName('sales/order')], 'so.entity_id = soi.order_id', [])
            ->where('so.state NOT IN (?)',$orderStates)
            ->where('so.status != (?)',"submitted")
            ->where('soi.sku IN (?)', $skus)
            ->where('soi.product_type = (?)', 'simple')
            ->group('soi.sku');

        return $db->fetchAssoc($select);
    }
}
