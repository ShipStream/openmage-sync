<?php

class ShipStream_Sync_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Plugin configuration value setter
     *
     * @param string $path
     * @param string|int|float|null $value
     * @return bool
     */
    public function setConfig($path, $value)
    {
        $flag = Mage::getModel('core/flag', ['flag_code' => 'ShipStream_Sync/'.$path])->loadSelf();
        $flag->setFlagData($value)->save();
        return TRUE;
    }

    /**
     * Plugin configuration value getter
     *
     * @param string $path
     * @return string|int|float|null
     */
    public function getConfig($path)
    {
        $flag = Mage::getModel('core/flag', ['flag_code' => 'ShipStream_Sync/'.$path])->loadSelf();
        return $flag->getFlagData();
    }
}
