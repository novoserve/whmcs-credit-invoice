<?php

use \WHMCS\Billing\Invoice;
use \WHMCS\Billing\Invoice\Item;
use \WHMCS\Database\Capsule;

function credit_invoice_issuecredit() {
	$invoiceId = filter_input(INPUT_POST, 'invoice', FILTER_SANITIZE_NUMBER_INT);
	$invoice = Invoice::with('items')->findOrFail($invoiceId);

	$results = localAPI('AddCredit', [
		'clientid' => $invoice->clientId,
		'description' => 'Credit for credit invoice '.$invoice->invoiceNumber,
		'amount' => abs($invoice->total),
	], 'Tamer');

	if ($results['result'] == 'success') {

		$invoicet->datePaid = Carbon\Carbon::now();
		$invoice->status = 'Paid';
		$invoice->save();
		
	    redirect_message_ok($invoiceId);
	} else {
	    die("Something went wrong: " . $results['result']);
	}
}

function credit_invoice_credit() {
	$invoiceId = filter_input(INPUT_POST, 'invoice', FILTER_SANITIZE_NUMBER_INT);
	$invoice = Invoice::with('items')->findOrFail($invoiceId);
	$getSeq = Capsule::table('tblconfiguration')->where('setting', 'SequentialInvoiceNumberValue')->value('value');

	// Duplicate original invoice (this is the credit note).
	$credit = $invoice->replicate();
	$credit->subtotal = -$credit->subtotal;
	$credit->tax1 = -$credit->tax1;
	$credit->total = -$credit->total;
	$credit->adminNotes = "Refund Invoice|{$invoiceId}|DO-NOT-REMOVE";
	$credit->dateCreated = Carbon\Carbon::now();
	$credit->dateDue = Carbon\Carbon::now();
	$credit->invoiceNumber = date('Y').date('m').$getSeq;
	//$credit->datePaid = Carbon\Carbon::now();
	//$credit->status = 'Paid';
	$credit->status = 'Unpaid';
	$credit->save();

	// Copy old invoice items to credit note
	$oldItems = Capsule::table('tblinvoiceitems')->where('invoiceid', '=', $invoice->id)->get();
	$newItems = [];
	foreach ($oldItems as $item) {
		//var_dump($item); die();
		$newItems[] = [
			'invoiceid' => $credit->id,
			'userid' => $credit->userid,
			'description' => $item->description,
			'amount' => -$item->amount,
			'taxed' => $item->taxed
		];
	}

	// Add a new item to credit note, describing that this is a credit
	$newItems[] = [
		'invoiceid' => $credit->id,
		'userid' => $credit->userid,
		'description' => "Credit invoice for invoice #{$invoiceId}",
		'amount' => 0,
		'taxed' => false
	];
	Capsule::table('tblinvoiceitems')->insert($newItems);

	// Mark original invoice as paid and add reference to credit note.
	$invoice->status = 'Paid';
	//$invoice->status = 'Unpaid';
	$invoice->adminNotes = $invoice->adminNotes . PHP_EOL . "Refund Credit Note|{$credit->id}|DO-NOT-REMOVE";
	$invoice->save();

	// Increase the sequentialnumbering.
	Capsule::table('tblconfiguration')->where('setting', 'SequentialInvoiceNumberValue')->update(['value' => $getSeq+1]);

	// Finally redirect to our credit note.
	redirect_to_invoice($credit->id);
};

function invoice_is_proforma($invoiceId) {
	$invoice = Invoice::findOrFail($invoiceId);
	if (empty($invoice->invoiceNumber)) {
		return true;
	} else {
		return false;
	}
}

function invoice_is_credited($invoiceId) {
	$invoice = Invoice::findOrFail($invoiceId);
	preg_match('/Refund Credit Note\|(\d*)/', $invoice->adminNotes, $match);
	return $match;
}

function invoice_is_creditnote($invoiceId) {
	$invoice = Invoice::findOrFail($invoiceId);
	preg_match('/Refund Invoice\|(\d*)/', $invoice->adminNotes, $match);
	return $match;
}

function redirect_to_invoice($invoiceId) {
	header("Location: invoices.php?action=edit&id={$invoiceId}");
	die();
}

function redirect_message_ok($invoiceId) {
	header("Location: invoices.php?action=edit&id={$invoiceId}&credit=ok");
	die();
}