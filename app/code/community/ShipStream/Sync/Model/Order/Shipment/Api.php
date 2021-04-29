<?php

/**
 * Sales order shipment API
 *
 * @category ShipStream
 * @package  ShipStream_Sync
 */
class ShipStream_Sync_Model_Order_Shipment_Api extends Mage_Sales_Model_Order_Shipment_Api_V2
{
    /**
     * Retrieve shipment information
     *
     * @param string $shipmentIncrementId
     * @return array
     */
    public function info($shipmentIncrementId)
    {
        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipmentIncrementId);
        if ( ! $shipment->getId()) {
            $this->_fault('not_exists');
        }

        $result = $this->_getAttributes($shipment, 'shipment');
        $result['order_increment_id'] = $shipment->getOrder()->getIncrementId();
        $result['shipping_address'] = $this->_getAttributes($shipment->getShippingAddress(), 'order_address');
        $result['shipping_method'] = $shipment->getOrder()->getShippingMethod(FALSE);

        $result['items'] = array();
        foreach ($shipment->getAllItems() as $item) { /** @var $item Mage_Sales_Model_Order_Shipment_Item */
            $shipmentItemData = $this->_getAttributes($item, 'shipment_item');
            $shipmentItemData['product_type'] = $item->getOrderItem()->getProductType();
            $result['items'][] = $shipmentItemData;
        }

        $result['tracks'] = array();
        foreach ($shipment->getAllTracks() as $track) {
            $result['tracks'][] = $this->_getAttributes($track, 'shipment_track');
        }

        $result['comments'] = array();
        foreach ($shipment->getCommentsCollection() as $comment) {
            $result['comments'][] = $this->_getAttributes($comment, 'shipment_comment');
        }

        return $result;
    }
}
