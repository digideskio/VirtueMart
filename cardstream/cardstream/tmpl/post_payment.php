<?php
defined ('_JEXEC') or die();

if ($viewData['success'] == 1) {?>
<div class="post_payment_payment_name" style="width: 100%">
	<h3>Success!</h3>
</div>

<div class="post_payment_order_number" style="width: 100%">
	<span class="post_payment_order_number_title">Response Code: </span>
	<?php echo  $viewData["response_code"]; ?>
</div>

<div class="post_payment_order_total" style="width: 100%">
	<span class="post_payment_order_total_title">Response Message: </span>
	<?php echo  $viewData["response_message"]; ?>
</div>
<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$viewData["order_number"].'&order_pass='.$viewData["order_pass"], false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>
<?php } else { ?>
<div class="post_payment_payment_name" style="width: 100%">
	<h3>Error - Your Payment Failed</h3>
</div>
<div class="post_payment_order_number" style="width: 100%">
	<span class="post_payment_order_number_title">Response Code: </span>
	<?php echo  $viewData["response_code"]; ?>
</div>

<div class="post_payment_order_total" style="width: 100%">
	<span class="post_payment_order_total_title">Response Message: </span>
	<?php echo  $viewData["response_message"]; ?>
</div>

<p>Please try again.</p>
<?php } ?>