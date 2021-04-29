<?php

/**
 * Config API
 *
 * @category ShipStream
 * @package  ShipStream_Sync
 */
class ShipStream_Sync_Model_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Configuration value setter
     *
     * @param string $path
     * @param string|int|float|null $value
     * @return bool
     */
    public function set_config($path, $value)
    {
        return Mage::helper('shipstream')->setConfig($path, $value);
    }

    /**
     * Perform a full inventory sync
     *
     * @return array
     */
    public function sync_inventory()
    {
        try {
            Mage::getModel('shipstream/cron')->fullInventorySync(FALSE);
        } catch (Exception $e) {
            Mage::logException($e);
            return ['success' => FALSE, 'message' => $e->getMessage()];
        }
        return ['success' => TRUE];
    }

}
