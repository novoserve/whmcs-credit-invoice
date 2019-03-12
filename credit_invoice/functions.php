<?php

use \WHMCS\Billing\Invoice;
use \WHMCS\Billing\Invoice\Item;
use \WHMCS\Database\Capsule;

/*
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
*/

function invoiceNumToInvoiceId($num) {
	return Capsule::table('tblinvoices')->where('invoicenum', $num)->value('id');
}

function IdtoInvoiceNum($id) {
	return Capsule::table('tblinvoices')->where('id', $id)->value('invoicenum');
}

function credit_invoice_markpaid() {
	$invoiceId = filter_input(INPUT_POST, 'invoice', FILTER_SANITIZE_NUMBER_INT);
	$originalIdNum = filter_input(INPUT_POST, 'originalIdNum', FILTER_SANITIZE_NUMBER_INT);
	$originalId = filter_input(INPUT_POST, 'originalId', FILTER_SANITIZE_NUMBER_INT);

	$invoice = Invoice::with('items')->findOrFail($invoiceId);

	$addTransaction = localAPI('AddInvoicePayment', array('invoiceid' => $originalId, 'transid' => $originalIdNum, 'gateway' => 'creditnote', 'amount' => abs($invoice->total)), 'API');
	if ($addTransaction['result'] != 'success') {
		echo $originalId;
	    die("Something went wrong: " . $addTransaction['result']);
	}
	
	$invoicet->datePaid = Carbon\Carbon::now();
	$invoice->status = 'Paid';
	$invoice->save();

	redirect_message_ok($invoiceId);
}

function credit_invoice_credit() {
	$invoiceId = filter_input(INPUT_POST, 'invoice', FILTER_SANITIZE_NUMBER_INT);
	$invoiceIdToNum = IdtoInvoiceNum($invoiceId);
	$invoice = Invoice::with('items')->findOrFail($invoiceId);
	//$getSeq = Capsule::table('tblconfiguration')->where('setting', 'SequentialInvoiceNumberValue')->value('value');
	$getNum = Capsule::table('tbladdonmodules')->where('setting', 'custominvoicenumber')->value('value');
	$getNewInvoiceNum = date('Y').$getNum;

	if (!is_numeric($getNum)) {
		die('Invoicenumber is not numeric!');
	}

	// Duplicate original invoice (this is the credit note).
	$credit = $invoice->replicate();
	$credit->subtotal = -$credit->subtotal;
	$credit->tax1 = -$credit->tax1;
	$credit->total = -$credit->total;
	$credit->adminNotes = "CREDITNOTE|$invoiceIdToNum|"; //refer in creditnote to original innvoice
	$credit->dateCreated = Carbon\Carbon::now();
	$credit->dateDue = Carbon\Carbon::now();
	$credit->invoiceNumber = $getNewInvoiceNum;
	//$credit->datePaid = Carbon\Carbon::now();
	$credit->status = 'Unpaid';
	$credit->save();

	Capsule::table('tbladdonmodules')->where('setting', 'custominvoicenumber')->update(['value' => $getNum+1]);

	//Capsule::table('tblinvoices')->where('id', $credit->id)->update(['invoicenum' => date('Y').$credit->id]);

	// Copy old invoice items to credit note
	$oldItems = Capsule::table('tblinvoiceitems')->where('invoiceid', '=', $invoice->id)->get();
	$newItems = [];
	foreach ($oldItems as $item) {
		$newItems[] = [
			'invoiceid' => $credit->id,
			'userid' => $credit->userid,
			'type' => $item->type,
			'relid' => $item->relid,
			'description' => $item->description,
			'amount' => -$item->amount,
			'taxed' => $item->taxed
		];
	}

	// Add a new item to credit note, describing that this is a credit
	$newItems[] = [
		'invoiceid' => $credit->id,
		'userid' => $credit->userid,
		'type' => '',
		'relid' => '0',
		'description' => "Creditnote for invoice #{$invoiceIdToNum}", //refer in creditnote to original innvoice
		'amount' => 0,
		'taxed' => false
	];
	Capsule::table('tblinvoiceitems')->insert($newItems);

	// Mark original invoice as paid and add reference to credit note.
	//$invoice->status = 'Paid';
	$invoice->adminNotes = $invoice->adminNotes . PHP_EOL . "CREDITAPPLIED|{$getNewInvoiceNum}|";
	$invoice->save();

	

	// Finally redirect to our credit note.
	redirect_to_invoice($credit->id);
};

function invoice_is_paid($invoiceId) {
	$invoice = Invoice::findOrFail($invoiceId);
	if ($invoice->status == 'Paid') {
		return true;
	} else {
		return false;
	}
}

function invoice_is_credited($invoiceId) {
	$invoice = Invoice::findOrFail($invoiceId);
	preg_match('/CREDITAPPLIED\|(\d*)/', $invoice->adminNotes, $match);
	return $match;
}

function invoice_is_creditnote($invoiceId) {
	$invoice = Invoice::findOrFail($invoiceId);
	preg_match('/CREDITNOTE\|(\d*)/', $invoice->adminNotes, $match);
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