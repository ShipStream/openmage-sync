<?php

class ShipStream_Sync_Model_Observer
{
    /**
     * Order save commit after event
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function salesOrderSaveAfter(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getDataObject();

        // Change "Submitted" order status which is default order status for "Complete" state
        // to "Complete" if the order is virtual (only contains Virtual or Downloadable products).
        if ($order->getIsVirtual()
            && $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING
            && ($order->getStatus() == 'ready_to_ship' || $order->getStatus() == 'submitted')) {
            $history = $order->addStatusHistoryComment('Changed order status to "Complete" as the order is virtual.', 'complete');
            $history->setIsCustomerNotified(FALSE);
            $order->save();
            return;
        }

        // Submit order to ShipStream when status transitions to Ready to Ship
        if (Mage::getStoreConfigFlag('shipstream/general/realtime_sync')
            &&  ! $order->getIsVirtual()
            && $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING
            && $order->dataHasChangedFor('status')
            && $order->getStatus() == 'ready_to_ship'
        ) {
            // Callback to trigger order import.
            try {
                Mage::helper('shipstream/api')->callback(
                    'syncOrder',
                    ['increment_id' => $order->getIncrementId()]
                );
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }
    }
}
