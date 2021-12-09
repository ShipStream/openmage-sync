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

    /**
     * @param $orderIncrementId
     * @param $data
     * @return string|null
     * @throws Mage_Api_Exception
     * @throws Mage_Core_Exception
     */
    public function createWithTracking($orderIncrementId, $data)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        if ( ! $order->getId()) {
            $this->_fault('order_not_exists');
        }
        if ( ! $order->canShip()) {
            $this->_fault('data_invalid', Mage::helper('sales')->__('Cannot do shipment for order.'));
        }

        $itemsQty = [];
        // TODO - use payload data to create only shipped items

        $comments = [];
        // TODO - add all relevant information to comments (e.g. serial numbers, etc.)

        $tracks = [];
        $carriers = $this->_getCarriers($order);
        foreach ($data['packages'] as $package) {
            $carrier = $package['carrier'];
            if (!isset($carriers[$carrier])) {
                $carrier = 'custom';
                $title = $data['service_description'];
            } else {
                $title = $carriers[$carrier];
            }
            foreach ($package['tracking_numbers'] as $trackingNumber) {
                $tracks[] = Mage::getModel('sales/order_shipment_track')
                    ->setNumber($trackingNumber)
                    ->setCarrierCode($carrier)
                    ->setTitle($title);
            }
        }

        // Get admin config of sending shipment email for order store
        $storeId = $order->setStoreId();
        $email = Mage::getStoreConfigFlag('shipping/shipstream/send_shipment_email',$storeId);

        $shipment = $order->prepareShipment($itemsQty);
        $shipment->register();
        if ($comments) {
            $shipment->addComment(implode("<br/>\n", $comments), false);
        }
        if ($email) {
            $shipment->setEmailSent(true);
        }
        $shipment->getOrder()->setIsInProcess(true);
        $shipment->getResource()->beginTransaction();
        try {
            $shipment->save();
            foreach ($tracks as $track) {
                $shipment->addTrack($track)->save();
            }
            $shipment->getOrder()->save();
            $shipment->getResource()->commit();
        } catch (Mage_Core_Exception $e) {
            $shipment->getResource()->rollBack();
            $this->_fault('data_invalid', $e->getMessage());
        }

        // Send email to customer
        if ($email) {
            try {
                $shipment->sendEmail($email, '');
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        return $shipment->getIncrementId();
    }
}
