<?php

class ShipStream_Sync_Model_InventorySync
{
    const LOG_FILE = 'shipstream.log';

    /**
     * Synchronize Magento inventory with the warehouse inventory

     * @throws Exception
     */
    public function __invoke(): array
    {
        Mage::log('Beginning inventory sync.', Zend_Log::DEBUG, self::LOG_FILE);
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write'); /** @var $db Magento_Db_Adapter_Pdo_Mysql */

        $results = ['no_change' => [], 'updated' => [], 'errors' => []];
        try {
            $data = Mage::helper('shipstream/api')->callback('inventoryWithLock');
            $_source =  $data['skus'] ?? [];
            if (empty($_source)) {
                Mage::log('No inventory data received.', Zend_Log::DEBUG, self::LOG_FILE);
            } else {
                foreach (array_chunk($_source, 5000, TRUE) as $source) {
                    $db->beginTransaction();
                    try {
                        $target = $this->_getTargetInventory(array_keys($source));
                        // Get qty of order items that are in processing state and not submitted to shipstream
                        $processingQty = $this->_getProcessingOrderItemsQty(array_keys($source));
                        $updated = $noChange = [];
                        foreach ($source as $sku => $qty) {
                            if ( ! isset($target[$sku])) {
                                continue;
                            }
                            $qty =  floor(floatval($qty));
                            $syncQty = $qty;
                            if (isset($processingQty[$sku])) {
                                $syncQty = floor($qty - floatval($processingQty[$sku]['qty']));
                            }
                            $targetQty = floatval($target[$sku]['qty']);
                            if ($syncQty == $targetQty) {
                                $noChange[] = $sku;
                                continue;
                            }
                            Mage::log("SKU: $sku remote qty is $qty and local is $targetQty", Zend_Log::DEBUG, self::LOG_FILE);
                            $stockItem = Mage::getModel('cataloginventory/stock_item')->load($target[$sku]['stock_item_id']); /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
                            if ( ! $stockItem->getId()) {
                                throw new Mage_Core_Exception(Mage::helper('shipstream')->__('Cannot load the stock item for the product with "%s" SKU.', $sku));
                            }
                            if ($stockItem->getManageStock() && Mage::helper('cataloginventory')->isQty($stockItem->getTypeId())) {
                                $oldQty = $stockItem->getQty();
                                $stockItem->setQty($syncQty);
                                if ($oldQty < 1 && ! $stockItem->getIsInStock() && $stockItem->getCanBackInStock() && $stockItem->getQty() > $stockItem->getMinQty()) {
                                    $stockItem->setIsInStock(true)
                                        ->setStockStatusChangedAutomaticallyFlag(true);
                                    Mage::log("SKU: $sku is back in stock.", Zend_Log::DEBUG, self::LOG_FILE);
                                }
                                $stockItem->save();
                            }
                            $updated[] = ['sku' => $stockItem->getProduct()->getSku(), 'old_qty' => $oldQty, 'new_qty' => $stockItem->getQty()];
                        }
                        $db->commit();
                        $results['no_change'] = array_merge($results['no_change'], $noChange);
                        $results['updated'] = array_merge($results['updated'], $updated);
                    } catch (Exception $e) {
                        $db->rollback();
                        $results['errors'][] = $e->getMessage();
                        Mage::log("Error syncing inventory ({$e->getMessage()}). Full exception:\n$e", Zend_Log::ERR, self::LOG_FILE);
                    }
                }
            }
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            Mage::log("Error syncing inventory ({$e->getMessage()}). Full exception:\n$e", Zend_Log::ERR, self::LOG_FILE);
        }
        try {
            Mage::helper('shipstream/api')->callback('unlockOrderImport');
        } catch (Exception $e) {
            Mage::log("Error unlocking order import ({$e->getMessage()}).", Zend_Log::ERR, self::LOG_FILE);
            $results['errors'][] = $e->getMessage();
        }

        Mage::log('Inventory sync complete.', Zend_Log::DEBUG, self::LOG_FILE);
        return $results;
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
     * @param array $skus
     * @return mixed
     */
    protected function _getProcessingOrderItemsQty(array $skus)
    {

        $orderStates = [
            Mage_Sales_Model_Order::STATE_COMPLETE,
            Mage_Sales_Model_Order::STATE_CLOSED,
            Mage_Sales_Model_Order::STATE_CANCELED
        ];
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $columns = ['sku' => 'soi.sku', 'qty' => 'GREATEST(0, sum(soi.qty_ordered - soi.qty_canceled - soi.qty_refunded))'];
        $select = $db->select()->forUpdate(TRUE)
            ->from(['soi' => $resource->getTableName('sales/order_item')], $columns)
            ->join(['so' => $resource->getTableName('sales/order')], 'so.entity_id = soi.order_id', [])
            ->where('so.state NOT IN (?)',$orderStates)
            ->where('so.status != ?',"submitted")
            ->where('so.state != "holded" OR so.hold_before_status != "submitted"')
            ->where('soi.sku IN (?)', $skus)
            ->where('soi.product_type = ?', 'simple')
            ->group('soi.sku');

        return $db->fetchAssoc($select);
    }
}