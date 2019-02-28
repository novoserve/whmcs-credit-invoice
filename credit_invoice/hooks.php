<?php

use \WHMCS\Billing\Invoice;
use \WHMCS\Billing\Invoice\Item;
use \WHMCS\Database\Capsule;

defined('WHMCS') || exit;

require_once __DIR__ . '/functions.php';

add_hook('AdminInvoicesControlsOutput', 1, function($vars) {
	ob_start(); ?>

	<?php if ($creditId = invoice_is_credited($vars['invoiceid'])[1]): ?>

		<a href="invoices.php?action=edit&id=<?php echo invoiceNumToInvoiceId($creditId); ?>" class="button btn btn-default">Open creditnote #<?= $creditId ?></a>

	<?php elseif ($originalId = invoice_is_creditnote($vars['invoiceid'])[1]): ?>

		<a href="invoices.php?action=edit&id=<?php echo invoiceNumToInvoiceId($originalId); ?>" class="button btn btn-default">Open original #<?= $originalId ?></a>

		<br><br>

		<form method="POST" action="addonmodules.php?module=credit_invoice" name="credit_invoice_actions" style="display:inline;margin-top: 5px;" onsubmit="return confirm('Do you want to mark this invoice as Paid?');">
			<input type="hidden" name="invoice" value="<?= $vars['invoiceid'] ?>">
			<input type="hidden" name="originalId" value="<?php echo invoiceNumToInvoiceId($originalId); ?>">
			<input type="hidden" name="originalIdNum" value="<?php echo $originalId; ?>">
			<button type="submit" name="action" value="markpaid"
			class="button btn btn-warning"
			data-toggle="tooltip"
			data-placement="left"
			data-original-title="Mark this credit invoice as Paid.">Mark as Paid and Credit</button>
		</form>

	<?php else: ?>

		<form method="POST" action="addonmodules.php?module=credit_invoice" name="credit_invoice_actions" style="display:inline;margin-top: 5px;" onsubmit="return confirm('Do you really want to create a credit invoice?');">
			<input type="hidden" name="invoice" value="<?= $vars['invoiceid'] ?>">
			<button type="submit" name="action" value="credit"
			class="button btn btn-default"
			data-toggle="tooltip"
			data-placement="left"
			data-original-title="Click to copy invoice to a credit note, with reversed line items.">Credit Invoice</button>
		</form>

	<?php endif ?>

	<?php if (isset($_GET['credit']) && $_GET['credit'] == 'ok'): ?>
		<br><br>
		<div class="alert alert-success col-md-4 col-md-offset-4" align="center">
		  <strong>Success!</strong> Requested action executed successfully!
		</div>
	<?php endif ?>


	<?php echo ob_get_clean();
});


add_hook('UpdateInvoiceTotal', 1, function($vars) {
	$invoice = Invoice::with('items')->findOrFail($vars['invoiceid']);

	foreach ($invoice->items as $item) {
		if ($item->taxed && $item->amount < 0) {
			$invoice->tax1 = $invoice->subtotal * ($invoice->taxRate1 / 100);
		}
	}

	$invoice->total = $invoice->subtotal - $invoice->credit + $invoice->tax1 + $invoice->tax2;
	$invoice->save();
});

/*
add_hook('InvoicePaid', 1, function($vars) {

    $invoice = Invoice::with('items')->findOrFail($vars['invoiceid']);
    if ($invoice->items[0]->type == 'AddFunds') {
    	$getSeq = Capsule::table('tblconfiguration')->where('setting', 'SequentialInvoiceNumberValue')->value('value');
    	Capsule::table('tblconfiguration')->where('setting', 'SequentialInvoiceNumberValue')->update(['value' => $getSeq-1]);
    	Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->update(['invoicenum' => '']);
    	Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->update(['status' => 'Credit']);
    }

});
*/