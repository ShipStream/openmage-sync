<?php

/**
 * Catalog inventory API
 *
 * @category ShipStream
 * @package  ShipStream_Sync
 */
class ShipStream_Sync_Model_Stock_Item_Api extends Mage_CatalogInventory_Model_Stock_Item_Api_V2
{
    /**
     * Adjust the product qty using the given delta
     *
     * @see Mage_CatalogInventory_Model_Stock_Item_Api_V2::update()
     * @see Mage_CatalogInventory_Model_Observer::addInventoryData()
     *
     * @param int|string $productId - product entity id or SKU
     * @param float $delta
     * @return bool
     * @throws Exception
     */
    public function adjust($productId, $delta)
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write'); /** @var $adapter Varien_Db_Adapter_Pdo_Mysql */
        $adapter->beginTransaction();
        try {
            $this->_lockStockItems($productId);

            // Check if the product exists
            $product = Mage::getModel('catalog/product'); /** @var $product Mage_Catalog_Model_Product */
            $idBySku = $product->getIdBySku($productId);
            $productId = $idBySku ? $idBySku : $productId;
            $product->setStoreId($this->_getStoreId())
                ->load($productId);
            if ( ! $product->getId()) {
                $this->_fault('not_exists');
            }

            // Update the stock item's quantity
            $stockItem = $product->getStockItem(); /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
            if ($stockItem->getManageStock() && Mage::helper('cataloginventory')->isQty($stockItem->getTypeId())) {
                $oldQty = $stockItem->getQty();
                $stockItem->addQty($delta);
                if ($oldQty < 1 && ! $stockItem->getIsInStock() && $stockItem->getCanBackInStock() && $stockItem->getQty() > $stockItem->getMinQty()) {
                    $stockItem->setIsInStock(true)
                        ->setStockStatusChangedAutomaticallyFlag(true);
                }
                $stockItem->save();
            }

            $adapter->commit();
        } catch (Mage_Core_Exception $e) {
            $adapter->rollback();
            $this->_fault('not_updated', $e->getMessage());
        } catch (Exception $e) {
            $adapter->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Lock stock items for update
     *
     * @param null|int|array $productId
     * @return void
     */
    protected function _lockStockItems($productId = NULL)
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()->forUpdate(TRUE)
            ->from(['stock_item' => $resource->getTableName('cataloginventory/stock_item')], 'item_id');
        if (is_numeric($productId)) {
            $select->where('stock_item.product_id = ?', $productId);
        } elseif (is_array($productId)) {
            $select->where('stock_item.product_id IN (?)', $productId);
        }
        $adapter->query($select)->closeCursor();
    }
}
