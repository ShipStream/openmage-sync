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
     * Get information about the environment
     *
     * @return array
     */
    public function info()
    {
        $result = [];
        $result['magento_edition'] = Mage::getEdition();
        $result['magento_version'] = Mage::getVersion();
        $result['openmage_version'] = method_exists('Mage','getOpenMageVersion') ? Mage::getOpenMageVersion() : '';
        $result['shipstream_sync_version'] = (string) Mage::getConfig()->getModuleConfig('ShipStream_Sync')->version;
        return $result;
    }

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
        $service = new ShipStream_Sync_Model_InventorySync();
        return $service();
    }

}
