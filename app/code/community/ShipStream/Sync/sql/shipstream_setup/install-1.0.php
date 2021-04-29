<?php /* @var $this Mage_Core_Model_Resource_Setup */

// Add "Submitted" order status and assign it to the "Complete" state
$model = Mage::getModel('sales/order_status');
$model->load('submitted', 'status');
if ( ! $model->getId()) {
    $model->addData(array('status' => 'submitted', 'label' => 'Submitted'));
    $model->save();
}
$model->assignState(Mage_Sales_Model_Order::STATE_COMPLETE, TRUE);