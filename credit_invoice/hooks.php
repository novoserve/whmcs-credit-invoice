<?php

use \WHMCS\Billing\Invoice;
use \WHMCS\Billing\Invoice\Item;

defined('WHMCS') || exit;

require_once __DIR__ . '/functions.php';

add_hook('AdminInvoicesControlsOutput', 1, function($vars) {
	ob_start(); ?>

	<?php if ($creditId = invoice_is_credited($vars['invoiceid'])[1]): ?>

		<a href="invoices.php?action=edit&id=<?= $creditId ?>" class="button btn btn-default">Credited in <?= $creditId ?></a>

	<?php elseif ($originalId = invoice_is_creditnote($vars['invoiceid'])[1]): ?>

		<a href="invoices.php?action=edit&id=<?= $originalId ?>" class="button btn btn-default">Credit invoice of <?= $originalId ?></a>

		<br><br>

		<form method="POST" action="addonmodules.php?module=credit_invoice" name="credit_invoice_actions" style="display:inline;margin-top: 5px;" onsubmit="return confirm('Do you really want to issue a credit?');">
			<input type="hidden" name="invoice" value="<?= $vars['invoiceid'] ?>">
			<button type="submit" name="action" value="issuecredit"
			class="button btn btn-warning"
			data-toggle="tooltip"
			data-placement="left"
			data-original-title="Mark this credit invoice as Paid and add the credit to the customers account.">Add credit and mark as Paid</button>
		</form>

	<?php elseif (invoice_is_proforma($vars['invoiceid'])): ?>

		<a href="#" class="button btn btn-default" disabled>Credit invoice</a>

	<?php else: ?>

		<form method="POST" action="addonmodules.php?module=credit_invoice" name="credit_invoice_actions" style="display:inline;margin-top: 5px;" onsubmit="return confirm('Do you really want to create a credit invoice?');">
			<input type="hidden" name="invoice" value="<?= $vars['invoiceid'] ?>">
			<button type="submit" name="action" value="credit"
			class="button btn btn-default"
			data-toggle="tooltip"
			data-placement="left"
			data-original-title="Click to copy invoice to a credit note, with reversed line items.">Credit invoice</button>
		</form>

	<?php endif ?>

	<?php if (isset($_GET['credit']) && $_GET['credit'] == 'ok'): ?>
		<br><br>
		<div class="alert alert-success col-md-4 col-md-offset-4" align="center">
		  <strong>Success!</strong> Invoice credited to account.
		</div>
	<?php endif ?>


	<?php echo ob_get_clean();
});


add_hook('UpdateInvoiceTotal', 1, function($vars) {
	$invoice = Invoice::with('items')->findOrFail($vars['invoiceid']);

	foreach ($invoice->items as $item) {
		if ($item->taxed && $item->amount < 0) {
			$invoice->tax1 = $item->amount * ($invoice->taxRate1 / 100);
		}
	}

	$invoice->total = $invoice->subtotal - $invoice->credit + $invoice->tax1 + $invoice->tax2;
	$invoice->save();
});