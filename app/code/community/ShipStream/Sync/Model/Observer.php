<?php

class ShipStream_Sync_Model_Observer
{
    /**
     * Order save after event
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function salesOrderSaveAfter(Varien_Event_Observer $observer)
    {
        $order = $observer->getDataObject(); /** @var $order Mage_Sales_Model_Order */

        // Change "Submitted" order status which is default order status for "Complete" state
        // to "Complete" if the order is virtual (only contains Virtual or Downloadable products).
        if ($order->getIsVirtual()
            && $order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE
            && $order->getStatus() == 'submitted') {
            $history = $order->addStatusHistoryComment('Changed "Submitted" order status to "Complete" as the order is virtual.', 'complete');
            $history->setIsCustomerNotified(FALSE);
            $order->save();
        }
    }
}
