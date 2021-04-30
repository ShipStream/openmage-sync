<?php /* @var $this Mage_Core_Model_Resource_Setup */

// Add "Ready to Ship" order status and assign it to the "Processing" state
$model = Mage::getModel('sales/order_status');
$model->load('ready_to_ship', 'status');
if ( ! $model->getId()) {
    $model->addData(array('status' => 'ready_to_ship', 'label' => 'Ready to Ship'));
    $model->save();
}
$model->assignState(Mage_Sales_Model_Order::STATE_PROCESSING, FALSE);

// Add "Failed to Submit" order status and assign it to the "Processing" state
$model = Mage::getModel('sales/order_status');
$model->load('failed_to_submit', 'status');
if ( ! $model->getId()) {
    $model->addData(array('status' => 'failed_to_submit', 'label' => 'Failed to Submit'));
    $model->save();
}
$model->assignState(Mage_Sales_Model_Order::STATE_PROCESSING, FALSE);

// Add "Submitted" order status and assign it to the "Processing" state
$model = Mage::getModel('sales/order_status');
$model->load('submitted', 'status');
if ( ! $model->getId()) {
    $model->addData(array('status' => 'submitted', 'label' => 'Submitted'));
    $model->save();
}
$model->assignState(Mage_Sales_Model_Order::STATE_PROCESSING, FALSE);