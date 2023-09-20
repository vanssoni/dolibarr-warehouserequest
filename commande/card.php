<?php
/* Copyright (C) 2003-2006	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Marc Barilley / Ocebo	<marc@ocebo.com>
 * Copyright (C) 2005-2015	Regis Houssin			<regis.houssin@inodbox.com>
 * Copyright (C) 2006		Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2010-2013	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2011-2022	Philippe Grand			<philippe.grand@atoo-net.com>
 * Copyright (C) 2012-2013	Christophe Battarel		<christophe.battarel@altairis.fr>
 * Copyright (C) 2012-2016	Marcos García			<marcosgdf@gmail.com>
 * Copyright (C) 2012       Cedric Salvador      	<csalvador@gpcsolutions.fr>
 * Copyright (C) 2013		Florian Henry			<florian.henry@open-concept.pro>
 * Copyright (C) 2014       Ferran Marcet			<fmarcet@2byte.es>
 * Copyright (C) 2015       Jean-François Ferry		<jfefe@aternatik.fr>
 * Copyright (C) 2018-2021  Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2022	    Gauthier VERDOL     	<gauthier.verdol@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   \file      htdocs/commande/card.php
 *   \ingroup   commande
 *   \brief     Page to show sales order
 */
// Load Dolibarr environment
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formorder.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmargin.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/warehouserequest/commande/class/commande.class.php';

if (isModEnabled("propal")) {
	require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
}

if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
	require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
}

if (isModEnabled('variants')) {
	require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductCombination.class.php';
}
include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('orders', 'sendings', 'companies', 'bills', 'propal', 'deliveries', 'products', 'other', 'warehouserequest@warehouserequest'));

if (isModEnabled('incoterm')) {
	$langs->load('incoterm');
}
if (isModEnabled('margin')) {
	$langs->load('margins');
}
if (isModEnabled('productbatch')) {
	$langs->load('productbatch');
}


$id        = (GETPOST('id', 'int') ? GETPOST('id', 'int') : GETPOST('orderid', 'int'));
$ref       =  GETPOST('ref', 'alpha');
$socid     =  GETPOST('socid', 'int');
$action    =  GETPOST('action', 'aZ09');
$cancel    =  GETPOST('cancel', 'alpha');
$confirm   =  GETPOST('confirm', 'alpha');
$lineid    =  GETPOST('lineid', 'int');
$contactid =  GETPOST('contactid', 'int');
$projectid =  GETPOST('projectid', 'int');
$origin    =  GETPOST('origin', 'alpha');
$originid  = (GETPOST('originid', 'int') ? GETPOST('originid', 'int') : GETPOST('origin_id', 'int'));    // For backward compatibility
$rank      = (GETPOST('rank', 'int') > 0) ? GETPOST('rank', 'int') : -1;

// PDF
$hidedetails = (GETPOST('hidedetails', 'int') ? GETPOST('hidedetails', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0));
$hidedesc = (GETPOST('hidedesc', 'int') ? GETPOST('hidedesc', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0));
$hideref = (GETPOST('hideref', 'int') ? GETPOST('hideref', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0));

// Security check
if (!empty($user->socid)) {
	$socid = $user->socid;
}

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('warehouserequest', 'globalcard'));

$result = restrictedArea($user, 'commande', $id);

$object = new Warehouserequest($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php';     // Must be include, not include_once

// Permissions / Rights
$usercanread    =  $user->hasRight("commande", "lire");
$usercancreate  =  $user->hasRight("commande", "creer");
$usercandelete  =  $user->hasRight("commande", "supprimer");

// Advanced permissions
$usercanclose       =  ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($usercancreate)) || (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $user->hasRight('commande', 'order_advance', 'close')));
$usercanvalidate    =  ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $usercancreate) || (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $user->hasRight('commande', 'order_advance', 'validate')));
$usercancancel      =  ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $usercancreate) || (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $user->hasRight('commande', 'order_advance', 'annuler')));
$usercansend        =   (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->hasRight('commande', 'order_advance', 'send'));
$usercangeneretedoc =   (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->hasRight('commande', 'order_advance', 'generetedoc'));

$usermustrespectpricemin    = ((!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && empty($user->rights->produit->ignore_price_min_advance)) || empty($conf->global->MAIN_USE_ADVANCED_PERMS));
$usercancreatepurchaseorder = ($user->hasRight('fournisseur', 'commande', 'creer') || $user->hasRight('supplier_order', 'creer'));

$permissionnote    = $usercancreate;     //  Used by the include of actions_setnotes.inc.php
$permissiondellink = $usercancreate;     //  Used by the include of actions_dellink.inc.php
$permissiontoadd   = $usercancreate;     //  Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php


$error = 0;

$date_delivery = dol_mktime(GETPOST('liv_hour', 'int'), GETPOST('liv_min', 'int'), 0, GETPOST('liv_month', 'int'), GETPOST('liv_day', 'int'), GETPOST('liv_year', 'int'));


/*
 * Actions
 */

$parameters = array('socid' => $socid);
// Note that $action and $object may be modified by some hooks
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$backurlforlist = DOL_URL_ROOT . '/commande/list.php';

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = DOL_URL_ROOT . '/commande/card.php?id=' . ((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	if ($cancel) {
		if (!empty($backtopageforcancel)) {
			header("Location: " . $backtopageforcancel);
			exit;
		} elseif (!empty($backtopage)) {
			header("Location: " . $backtopage);
			exit;
		}
		$action = '';
	}

	include DOL_DOCUMENT_ROOT . '/core/actions_setnotes.inc.php';    // Must be include, not include_once

	include DOL_DOCUMENT_ROOT . '/core/actions_dellink.inc.php';     // Must be include, not include_once

	include DOL_DOCUMENT_ROOT . '/core/actions_lineupdown.inc.php';  // Must be include, not include_once



	if (GETPOST('new') == 1) {
		$object->deleteallwarehouserequest();
	}




	if ($action == 'confirm_validate' && $confirm == 'yes' && $usercanvalidate) {


		$entereddetlines = [];
		$error = 0;
		$lasterror = '';

		$warehousereqlines = $object->lines;

		$db->begin();
		foreach ($warehousereqlines as $warehoudata => $warehouseval) {
			$lineobj = new OrderLine($db);
			foreach ($warehouseval as $datakey => $dataval) {
				$lineobj->$datakey = $dataval;
			}
			$existing_row = $lineobj->fetch_with_product_id($lineobj->fk_product, $lineobj->fk_commande);
			if ($existing_row) {
				$lineobj->id = $existing_row->rowid;
				// echo "<pre>";
				// print_r($lineobj);
				// die();
				$qty = $lineobj->qty;
				$lineobj->fetch($existing_row->rowid);
				$lineobj->qty = $existing_row->qty + $qty;
				// Clean parameters
				$qty = $lineobj->qty;
				$info_bits =$lineobj->info_bits;
				$txtva = $lineobj->tva_tx;
				$txlocaltax1 = $lineobj->localtax1_tx;
				$txlocaltax2 = $lineobj->localtax2_tx;
				$remise_percent = $lineobj->remise_percent;
				$special_code = $lineobj->special_code;
				if (!$special_code || $special_code == 3) {
					$special_code = 0;
				}
				$ref_ext = '';
				$remise_percent = price2num($remise_percent);
				$qty = price2num($qty);
				$pu = price2num($lineobj->price);
				$pa_ht = price2num($lineobj->pa_ht);
				$pu_ht_devise = price2num($lineobj->pa_ht);
				if (!preg_match('/\((.*)\)/', $txtva)) {
					$txtva = price2num($txtva); // $txtva can have format '5.0(XXX)' or '5'
				}
				$txlocaltax1 = price2num($txlocaltax1);
				$txlocaltax2 = price2num($txlocaltax2);

				// Calcul du total TTC et de la TVA pour la ligne a partir de
				// qty, pu, remise_percent et txtva
				// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
				// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.

				$localtaxes_type = getLocalTaxesFromRate($txtva, 0, $lineobj->thirdparty, $mysoc);

				// Clean vat code
				$vat_src_code = '';
				$reg = array();
				if (preg_match('/\((.*)\)/', $txtva, $reg)) {
					$vat_src_code = $reg[1];
					$txtva = preg_replace('/\s*\(.*\)/', '', $txtva); // Remove code into vatrate.
				}

				$tabprice = calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits, $type, $mysoc, $localtaxes_type, 100, @$lineobj->multicurrency_tx, $pu_ht_devise);

				$total_ht  = $tabprice[0];
				$total_tva = $tabprice[1];
				$total_ttc = $tabprice[2];
				$total_localtax1 = $tabprice[9];
				$total_localtax2 = $tabprice[10];
				$pu_ht  = $tabprice[3];
				$pu_tva = $tabprice[4];
				$pu_ttc = $tabprice[5];

				// MultiCurrency
				$multicurrency_total_ht  = $tabprice[16];
				$multicurrency_total_tva = $tabprice[17];
				$multicurrency_total_ttc = $tabprice[18];
				$pu_ht_devise = $tabprice[19];

				// Anciens indicateurs: $price, $subprice (a ne plus utiliser)
				$price = $pu_ht;
				if ($price_base_type == 'TTC') {
					$subprice = $pu_ttc;
				} else {
					$subprice = $pu_ht;
				}
				$remise = 0;
				if ($remise_percent > 0) {
					$remise = round(($pu * $remise_percent / 100), 2);
					$price = ($pu - $remise);
				}
				$lineobj->tva_tx = $txtva;
				$lineobj->localtax1_tx = $txlocaltax1;
				$lineobj->localtax2_tx = $txlocaltax2;
				$lineobj->localtax1_type = $lineobj->localtax1_type;
				$lineobj->localtax2_type = $lineobj->localtax2_type;

				$lineobj->total_ht = $total_ht;
				$lineobj->total_tva = $total_tva;
				$lineobj->total_ttc = $total_ttc;

				$lineobj->total_localtax1 = $total_localtax1;
				$lineobj->total_localtax2 = $total_localtax2;


				$lineobj->multicurrency_subprice = $subprice;
				$lineobj->multicurrency_total_ht = $multicurrency_total_ht;
				$lineobj->multicurrency_total_tva = $multicurrency_total_tva;
				$lineobj->multicurrency_total_ttc = $multicurrency_total_ttc;
				$result = $lineobj->update($user);
			} else {
				$result = $lineobj->insert($user);
			}


			if ($result > 0) {
				$entereddetlines[] = $lineobj->id;
			} else {
				$lasterror = $lineobj->error;
				$error++;
			}
		}
		if (!$error) {
			$result = $object->update_price(1, 'auto', 0, $mysoc);
			if ($result < 0) {
				$lasterror = $object->error;
				$error++;
			}
		}







		if (!$error) {




			$commandeobj = new Commande($db);
			$commandeobj->fetch($id);



			$idwarehouse = $object->warehouse_id;

			$qualified_for_stock_change = 0;
			if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
				$qualified_for_stock_change = $commandeobj->hasProductsOrServices(2);
			} else {
				$qualified_for_stock_change = $commandeobj->hasProductsOrServices(1);
			}

			// Check parameters
			if (!empty($conf->stock->enabled) && !empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $qualified_for_stock_change) {
				if (!$idwarehouse || $idwarehouse == -1) {
					$error++;
					setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("Warehouse")), null, 'errors');
				}
			}

			if (!$error) {
				$locationTarget = '';

				$result = $commandeobj->valid($user, $idwarehouse);
				if ($result >= 0) {
					$error = 0;
					$deposit = null;

					$deposit_percent_from_payment_terms = getDictionaryValue('c_payment_term', 'deposit_percent', $commandeobj->cond_reglement_id);

					if (
						GETPOST('generate_deposit', 'alpha') == 'on' && !empty($deposit_percent_from_payment_terms)
						&& !empty($conf->facture->enabled) && !empty($user->rights->facture->creer)
					) {
						require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

						$date = dol_mktime(0, 0, 0, GETPOST('datefmonth', 'int'), GETPOST('datefday', 'int'), GETPOST('datefyear', 'int'));
						$forceFields = array();

						if (GETPOSTISSET('date_pointoftax')) {
							$forceFields['date_pointoftax'] = dol_mktime(0, 0, 0, GETPOST('date_pointoftaxmonth', 'int'), GETPOST('date_pointoftaxday', 'int'), GETPOST('date_pointoftaxyear', 'int'));
						}

						$deposit = Facture::createDepositFromOrigin($commandeobj, $date, GETPOST('cond_reglement_id', 'int'), $user, 0, GETPOST('validate_generated_deposit', 'alpha') == 'on', $forceFields);

						if ($deposit) {
							setEventMessage('DepositGenerated');
							$locationTarget = DOL_URL_ROOT . '/compta/facture/card.php?id=' . $deposit->id;
						} else {
							$error++;
							setEventMessages($commandeobj->error, $commandeobj->errors, 'errors');
						}
					}

					// Define output language
					if (!$error) {


						if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
							$outputlangs = $langs;
							$newlang = '';
							if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
								$newlang = GETPOST('lang_id', 'aZ09');
							}
							if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {
								$newlang = $commandeobj->thirdparty->default_lang;
							}
							if (!empty($newlang)) {
								$outputlangs = new Translate("", $conf);
								$outputlangs->setDefaultLang($newlang);
							}
							$model = $commandeobj->model_pdf;
							$ret = $commandeobj->fetch($id); // Reload to get new records

							$commandeobj->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);

							if ($deposit) {
								$deposit->fetch($deposit->id); // Reload to get new records
								$deposit->generateDocument($deposit->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							}
						}

						if ($locationTarget) {
						}
					} else {
						$error++;
					}
				} else {
					$error++;
					setEventMessages($commandeobj->error, $commandeobj->errors, 'errors');
				}
			}
		}













		/************** CREATE SHIPMENT ************ */



		$origin = $object->element;
		$origin_id = $object->id;



		$objectexp = new Expedition($db);


		$objectexp->origin				= $origin;
		$objectexp->origin_id = $origin_id;


		// We will loop on each line of the original document to complete the shipping object with various info and quantity to deliver
		$classname = ucfirst($objectexp->origin);
		$objectexpsrc = new $classname($db);
		$objectexpsrc->fetch($objectexp->origin_id);

		$objectexp->socid = $objectexpsrc->socid;
		$object->shipping_method_id		= $objectexpsrc->shipping_method_id;

		$batch_line = array();
		$stockLine = array();
		$array_options = array();

		$num = count($objectexpsrc->lines);
		$totalqty = 0;

		for ($i = 0; $i < $num; $i++) {






			if (in_array($objectexpsrc->lines[$i]->id, $entereddetlines)) {
			} else {
				continue;
			}

			$_POST['idl' . $i] = $objectexpsrc->lines[$i]->id;
			$_POST['qtyl' . $i] = $objectexpsrc->lines[$i]->qty;
			$_POST['entl' . $i] = $object->warehouse_id;



			$idl = "idl" . $i;

			$sub_qty = array();
			$subtotalqty = 0;

			$j = 0;
			$batch = "batchl" . $i . "_0";
			$stockLocation = "ent1" . $i . "_0";
			$qty = "qtyl" . $i;

			if (!empty($conf->productbatch->enabled) && $objectexpsrc->lines[$i]->product_tobatch) {      // If product need a batch number
				if (GETPOSTISSET($batch)) {
					//shipment line with batch-enable product
					$qty .= '_' . $j;
					while (GETPOSTISSET($batch)) {
						// save line of detail into sub_qty
						$sub_qty[$j]['q'] = GETPOST($qty, 'int'); // the qty we want to move for this stock record
						$sub_qty[$j]['id_batch'] = GETPOST($batch, 'int'); // the id into llx_product_batch of stock record to move
						$subtotalqty += $sub_qty[$j]['q'];

						//var_dump($qty);
						//var_dump($batch);
						//var_dump($sub_qty[$j]['q']);
						//var_dump($sub_qty[$j]['id_batch']);

						$j++;
						$batch = "batchl" . $i . "_" . $j;
						$qty = "qtyl" . $i . '_' . $j;
					}

					$batch_line[$i]['detail'] = $sub_qty; // array of details
					$batch_line[$i]['qty'] = $subtotalqty;
					$batch_line[$i]['ix_l'] = GETPOST($idl, 'int');

					$totalqty += $subtotalqty;
				} else {
					// No detail were provided for lots, so if a qty was provided, we can show an error.
					if (GETPOST($qty)) {
						// We try to set an amount
						// Case we dont use the list of available qty for each warehouse/lot
						// GUI does not allow this yet
						setEventMessages($langs->trans("StockIsRequiredToChooseWhichLotToUse"), null, 'errors');
					}
				}
			} elseif (GETPOSTISSET($stockLocation)) {
				//shipment line from multiple stock locations
				$qty .= '_' . $j;
				while (GETPOSTISSET($stockLocation)) {
					// save sub line of warehouse
					$stockLine[$i][$j]['qty'] = price2num(GETPOST($qty, 'alpha'), 'MS');
					$stockLine[$i][$j]['warehouse_id'] = GETPOST($stockLocation, 'int');
					$stockLine[$i][$j]['ix_l'] = GETPOST($idl, 'int');

					$totalqty += price2num(GETPOST($qty, 'alpha'), 'MS');

					$j++;
					$stockLocation = "ent1" . $i . "_" . $j;
					$qty = "qtyl" . $i . '_' . $j;
				}
			} else {
				//shipment line for product with no batch management and no multiple stock location
				if (GETPOST($qty, 'int') > 0) {
					$totalqty += price2num(GETPOST($qty, 'alpha'), 'MS');
				}
			}

			// Extrafields
			$array_options[$i] = $extrafields->getOptionalsFromPost($objectexp->table_element_line, $i);
			// Unset extrafield
			if (is_array($extrafields->attributes[$objectexp->table_element_line]['label'])) {
				// Get extra fields
				foreach ($extrafields->attributes[$objectexp->table_element_line]['label'] as $key => $value) {
					unset($_POST["options_" . $key]);
				}
			}
		}

		//var_dump($batch_line[2]);

		if ($totalqty > 0) {		// There is at least one thing to ship
			for ($i = 0; $i < $num; $i++) {
				$qty = "qtyl" . $i;
				if (!isset($batch_line[$i])) {
					// not batch mode
					if (isset($stockLine[$i])) {
						//shipment from multiple stock locations
						$nbstockline = count($stockLine[$i]);
						for ($j = 0; $j < $nbstockline; $j++) {
							if ($stockLine[$i][$j]['qty'] > 0) {
								$ret = $objectexp->addline($stockLine[$i][$j]['warehouse_id'], $stockLine[$i][$j]['ix_l'], $stockLine[$i][$j]['qty'], $array_options[$i]);
								if ($ret < 0) {
									setEventMessages($objectexp->error, $objectexp->errors, 'errors');
									$error++;
								}
							}
						}
					} else {
						if (GETPOST($qty, 'int') > 0 || (GETPOST($qty, 'int') == 0 && $conf->global->SHIPMENT_GETS_ALL_ORDER_PRODUCTS)) {
							$ent = "entl" . $i;
							$idl = "idl" . $i;
							$entrepot_id = is_numeric(GETPOST($ent, 'int')) ? GETPOST($ent, 'int') : GETPOST('entrepot_id', 'int');
							if ($entrepot_id < 0) {
								$entrepot_id = '';
							}
							if (!($objectexpsrc->lines[$i]->fk_product > 0)) {
								$entrepot_id = 0;
							}

							$ret = $objectexp->addline($entrepot_id, GETPOST($idl, 'int'), GETPOST($qty, 'int'), $array_options[$i]);
							if ($ret < 0) {
								setEventMessages($objectexp->error, $objectexp->errors, 'errors');
								$error++;
							}
						}
					}
				} else {
					// batch mode
					if ($batch_line[$i]['qty'] > 0) {
						$ret = $objectexp->addline_batch($batch_line[$i], $array_options[$i]);
						if ($ret < 0) {
							setEventMessages($objectexp->error, $objectexp->errors, 'errors');
							$error++;
						}
					}
				}
			}
			// Fill array 'array_options' with data from add form
			$ret = $extrafields->setOptionalsFromPost(null, $objectexp);
			if ($ret < 0) {
				$error++;
			}

			if (!$error) {
				$ret = $objectexp->create($user); // This create shipment (like Odoo picking) and lines of shipments. Stock movement will be done when validating shipment.
				if ($ret <= 0) {
					setEventMessages($objectexp->error, $objectexp->errors, 'errors');
					$error++;
				}

				/*
			$result = $objectexp->valid($user);
			if ($result < 0) {
				setEventMessages($objectexp->error, $objectexp->errors, 'errors');
				$error++;
			} 
			*/
			}
		} else {
			$labelfieldmissing = $langs->transnoentitiesnoconv("QtyToShip");
			if (!empty($conf->stock->enabled)) {
				$labelfieldmissing .= '/' . $langs->transnoentitiesnoconv("Warehouse");
			}
			setEventMessages($langs->trans("ErrorFieldRequired", $labelfieldmissing), null, 'errors');
			$error++;
		}





		/***************END CREATE SHIPMENT**********/







		if (!$error) {
			$object->deleteallwarehouserequest();

			$ret = $object->fetch($id); // Reload to get new records

		}






		if (!$error) {


			setEventMessages('Successfullyupdated', '', 'mesgs');
			$db->commit();
		} else {
			setEventMessages($lasterror, [], 'errors');
			$db->rollback();
		}

		$action = '';
	} // Action clone object
	elseif ($action == 'confirm_deleteline' && $confirm == 'yes' && $usercancreate) {
		// Remove a product line
		$result = $object->deleteline($user, $lineid);
		if ($result > 0) {
			// reorder lines
			$object->line_order(true);


			header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif ($action == 'setshippingmethod' && $usercancreate) {
		// shipping method
		$result = $object->setShippingMethod(GETPOST('shipping_method_id', 'int'));
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif ($action == 'setwarehouse' && $usercancreate) {
		// warehouse
		$result = $object->setWarehouse(GETPOST('warehouse_id', 'int'));
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif ($action == 'setremisepercent' && $usercancreate) {
		$result = $object->setDiscount($user, price2num(GETPOST('remise_percent'), '', 2));
	} elseif ($action == 'setremiseabsolue' && $usercancreate) {
		$result = $object->set_remise_absolue($user, price2num(GETPOST('remise_absolue'), 'MU', 2));
	} elseif ($action == 'addline' && GETPOST('submitforalllines', 'alpha') && GETPOST('vatforalllines', 'alpha') !== '') {
		// Define vat_rate
		$vat_rate = (GETPOST('vatforalllines') ? GETPOST('vatforalllines') : 0);
		$vat_rate = str_replace('*', '', $vat_rate);
		$localtax1_rate = get_localtax($vat_rate, 1, $object->thirdparty, $mysoc);
		$localtax2_rate = get_localtax($vat_rate, 2, $object->thirdparty, $mysoc);
		foreach ($object->lines as $line) {
			$result = $object->updateline($line->id, $line->desc, $line->subprice, $line->qty, $line->remise_percent, $vat_rate, $localtax1_rate, $localtax2_rate, 'HT', $line->info_bits, $line->date_start, $line->date_end, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->fk_unit, $line->multicurrency_subprice);
		}
	} elseif ($action == 'addline' && GETPOST('submitforalllines', 'alpha') && GETPOST('remiseforalllines', 'alpha') !== '' && $usercancreate) {
		// Define remise_percent
		$remise_percent = (GETPOST('remiseforalllines') ? GETPOST('remiseforalllines') : 0);
		$remise_percent = str_replace('*', '', $remise_percent);
		foreach ($object->lines as $line) {
			$result = $object->updateline($line->id, $line->desc, $line->subprice, $line->qty, $remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->date_start, $line->date_end, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->fk_unit, $line->multicurrency_subprice);
		}
	} elseif ($action == 'addline' && $usercancreate) {		// Add a new line
		$langs->load('errors');
		$error = 0;



		// Set if we used free entry or predefined product
		$predef = '';
		$product_desc = (GETPOSTISSET('dp_desc') ? GETPOST('dp_desc', 'restricthtml') : '');

		$price_ht = '';
		$price_ht_devise = '';
		$price_ttc = '';
		$price_ttc_devise = '';

		if (GETPOST('price_ht') !== '') {
			$price_ht = price2num(GETPOST('price_ht'), 'MU', 2);
		}
		if (GETPOST('multicurrency_price_ht') !== '') {
			$price_ht_devise = price2num(GETPOST('multicurrency_price_ht'), 'CU', 2);
		}
		if (GETPOST('price_ttc') !== '') {
			$price_ttc = price2num(GETPOST('price_ttc'), 'MU', 2);
		}
		if (GETPOST('multicurrency_price_ttc') !== '') {
			$price_ttc_devise = price2num(GETPOST('multicurrency_price_ttc'), 'CU', 2);
		}

		$prod_entry_mode = GETPOST('prod_entry_mode', 'aZ09');
		if ($prod_entry_mode == 'free') {
			$idprod = 0;
			$tva_tx = (GETPOST('tva_tx', 'alpha') ? price2num(preg_replace('/\s*\(.*\)/', '', GETPOST('tva_tx', 'alpha'))) : 0);
		} else {
			$idprod = GETPOST('idprod', 'int');
			$tva_tx = '';
		}

		$qty = price2num(GETPOST('qty' . $predef, 'alpha'), 'MS', 2);
		$remise_percent = (GETPOSTISSET('remise_percent' . $predef) ? price2num(GETPOST('remise_percent' . $predef, 'alpha'), '', 2) : 0);
		if (empty($remise_percent)) {
			$remise_percent = 0;
		}

		// Extrafields
		$extralabelsline = $extrafields->fetch_name_optionals_label($object->table_element_line);
		$array_options = $extrafields->getOptionalsFromPost($object->table_element_line, $predef);
		// Unset extrafield
		if (is_array($extralabelsline)) {
			// Get extra fields
			foreach ($extralabelsline as $key => $value) {
				unset($_POST["options_" . $key]);
			}
		}

		if ((empty($idprod) || $idprod < 0) && ($price_ht < 0) && ($qty < 0)) {
			setEventMessages($langs->trans('ErrorBothFieldCantBeNegative', $langs->transnoentitiesnoconv('UnitPriceHT'), $langs->transnoentitiesnoconv('Qty')), null, 'errors');
			$error++;
		}
		if ($prod_entry_mode == 'free' && (empty($idprod) || $idprod < 0) && GETPOST('type') < 0) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')), null, 'errors');
			$error++;
		}
		if ($prod_entry_mode == 'free' && (empty($idprod) || $idprod < 0) && $price_ht === '' && $price_ht_devise === '' && $price_ttc === '' && $price_ttc_devise === '') { 	// Unit price can be 0 but not ''. Also price can be negative for order.
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("UnitPriceHT")), null, 'errors');
			$error++;
		}
		if ($qty == '') {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Qty')), null, 'errors');
			$error++;
		}
		if ($qty < 0) {
			setEventMessages($langs->trans('FieldCannotBeNegative', $langs->transnoentitiesnoconv('Qty')), null, 'errors');
			$error++;
		}
		if ($prod_entry_mode == 'free' && (empty($idprod) || $idprod < 0) && empty($product_desc)) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Description')), null, 'errors');
			$error++;
		}

		if (!$error && isModEnabled('variants') && $prod_entry_mode != 'free') {
			if ($combinations = GETPOST('combinations', 'array')) {
				//Check if there is a product with the given combination
				$prodcomb = new ProductCombination($db);

				if ($res = $prodcomb->fetchByProductCombination2ValuePairs($idprod, $combinations)) {
					$idprod = $res->fk_product_child;
				} else {
					setEventMessages($langs->trans('ErrorProductCombinationNotFound'), null, 'errors');
					$error++;
				}
			}
		}

		if (!$error && ($qty >= 0) && (!empty($product_desc) || (!empty($idprod) && $idprod > 0))) {
			// Clean parameters
			$date_start = dol_mktime(GETPOST('date_start' . $predef . 'hour'), GETPOST('date_start' . $predef . 'min'), GETPOST('date_start' . $predef . 'sec'), GETPOST('date_start' . $predef . 'month'), GETPOST('date_start' . $predef . 'day'), GETPOST('date_start' . $predef . 'year'));
			$date_end = dol_mktime(GETPOST('date_end' . $predef . 'hour'), GETPOST('date_end' . $predef . 'min'), GETPOST('date_end' . $predef . 'sec'), GETPOST('date_end' . $predef . 'month'), GETPOST('date_end' . $predef . 'day'), GETPOST('date_end' . $predef . 'year'));
			$price_base_type = (GETPOST('price_base_type', 'alpha') ? GETPOST('price_base_type', 'alpha') : 'HT');

			// Ecrase $pu par celui du produit
			// Ecrase $desc par celui du produit
			// Ecrase $tva_tx par celui du produit
			// Ecrase $base_price_type par celui du produit
			if (!empty($idprod) && $idprod > 0) {
				$prod = new Product($db);
				$prod->fetch($idprod);

				$label = ((GETPOST('product_label') && GETPOST('product_label') != $prod->label) ? GETPOST('product_label') : '');

				// Update if prices fields are defined
				$tva_tx = get_default_tva($mysoc, $object->thirdparty, $prod->id);
				$tva_npr = get_default_npr($mysoc, $object->thirdparty, $prod->id);
				if (empty($tva_tx)) {
					$tva_npr = 0;
				}

				$pu_ht = $prod->price;
				$pu_ttc = $prod->price_ttc;
				$price_min = $prod->price_min;
				$price_min_ttc = $prod->price_min_ttc;
				$price_base_type = $prod->price_base_type;

				// If price per segment
				if (!empty($conf->global->PRODUIT_MULTIPRICES) && !empty($object->thirdparty->price_level)) {
					$pu_ht = $prod->multiprices[$object->thirdparty->price_level];
					$pu_ttc = $prod->multiprices_ttc[$object->thirdparty->price_level];
					$price_min = $prod->multiprices_min[$object->thirdparty->price_level];
					$price_min_ttc = $prod->multiprices_min_ttc[$object->thirdparty->price_level];
					$price_base_type = $prod->multiprices_base_type[$object->thirdparty->price_level];
					if (!empty($conf->global->PRODUIT_MULTIPRICES_USE_VAT_PER_LEVEL)) {  // using this option is a bug. kept for backward compatibility
						if (isset($prod->multiprices_tva_tx[$object->thirdparty->price_level])) {
							$tva_tx = $prod->multiprices_tva_tx[$object->thirdparty->price_level];
						}
						if (isset($prod->multiprices_recuperableonly[$object->thirdparty->price_level])) {
							$tva_npr = $prod->multiprices_recuperableonly[$object->thirdparty->price_level];
						}
					}
				} elseif (!empty($conf->global->PRODUIT_CUSTOMER_PRICES)) {
					// If price per customer
					require_once DOL_DOCUMENT_ROOT . '/product/class/productcustomerprice.class.php';

					$prodcustprice = new Productcustomerprice($db);

					$filter = array('t.fk_product' => $prod->id, 't.fk_soc' => $object->thirdparty->id);

					$result = $prodcustprice->fetchAll('', '', 0, 0, $filter);
					if ($result >= 0) {
						if (count($prodcustprice->lines) > 0) {
							$pu_ht = price($prodcustprice->lines[0]->price);
							$pu_ttc = price($prodcustprice->lines[0]->price_ttc);
							$price_min =  price($prodcustprice->lines[0]->price_min);
							$price_min_ttc =  price($prodcustprice->lines[0]->price_min_ttc);
							$price_base_type = $prodcustprice->lines[0]->price_base_type;
							$tva_tx = $prodcustprice->lines[0]->tva_tx;
							if ($prodcustprice->lines[0]->default_vat_code && !preg_match('/\(.*\)/', $tva_tx)) {
								$tva_tx .= ' (' . $prodcustprice->lines[0]->default_vat_code . ')';
							}
							$tva_npr = $prodcustprice->lines[0]->recuperableonly;
							if (empty($tva_tx)) {
								$tva_npr = 0;
							}
						}
					} else {
						setEventMessages($prodcustprice->error, $prodcustprice->errors, 'errors');
					}
				} elseif (!empty($conf->global->PRODUIT_CUSTOMER_PRICES_BY_QTY)) {
					// If price per quantity
					if ($prod->prices_by_qty[0]) {	// yes, this product has some prices per quantity
						// Search the correct price into loaded array product_price_by_qty using id of array retrieved into POST['pqp'].
						$pqp = GETPOST('pbq', 'int');

						// Search price into product_price_by_qty from $prod->id
						foreach ($prod->prices_by_qty_list[0] as $priceforthequantityarray) {
							if ($priceforthequantityarray['rowid'] != $pqp) {
								continue;
							}
							// We found the price
							if ($priceforthequantityarray['price_base_type'] == 'HT') {
								$pu_ht = $priceforthequantityarray['unitprice'];
							} else {
								$pu_ttc = $priceforthequantityarray['unitprice'];
							}
							// Note: the remise_percent or price by qty is used to set data on form, so we will use value from POST.
							break;
						}
					}
				} elseif (!empty($conf->global->PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES)) {
					// If price per quantity and customer
					if ($prod->prices_by_qty[$object->thirdparty->price_level]) {	// yes, this product has some prices per quantity
						// Search the correct price into loaded array product_price_by_qty using id of array retrieved into POST['pqp'].
						$pqp = GETPOST('pbq', 'int');
						// Search price into product_price_by_qty from $prod->id
						foreach ($prod->prices_by_qty_list[$object->thirdparty->price_level] as $priceforthequantityarray) {
							if ($priceforthequantityarray['rowid'] != $pqp) {
								continue;
							}
							// We found the price
							if ($priceforthequantityarray['price_base_type'] == 'HT') {
								$pu_ht = $priceforthequantityarray['unitprice'];
							} else {
								$pu_ttc = $priceforthequantityarray['unitprice'];
							}
							// Note: the remise_percent or price by qty is used to set data on form, so we will use value from POST.
							break;
						}
					}
				}

				$tmpvat = price2num(preg_replace('/\s*\(.*\)/', '', $tva_tx));
				$tmpprodvat = price2num(preg_replace('/\s*\(.*\)/', '', $prod->tva_tx));

				// Set unit price to use
				if (!empty($price_ht) || $price_ht === '0') {
					$pu_ht = price2num($price_ht, 'MU');
					$pu_ttc = price2num($pu_ht * (1 + ($tmpvat / 100)), 'MU');
				} elseif (!empty($price_ttc) || $price_ttc === '0') {
					$pu_ttc = price2num($price_ttc, 'MU');
					$pu_ht = price2num($pu_ttc / (1 + ($tmpvat / 100)), 'MU');
				} elseif ($tmpvat != $tmpprodvat) {
					// Is this still used ?
					if ($price_base_type != 'HT') {
						$pu_ht = price2num($pu_ttc / (1 + ($tmpvat / 100)), 'MU');
					} else {
						$pu_ttc = price2num($pu_ht * (1 + ($tmpvat / 100)), 'MU');
					}
				}

				$desc = '';

				// Define output language
				if (getDolGlobalInt('MAIN_MULTILANGS') && !empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE)) {
					$outputlangs = $langs;
					$newlang = '';
					if (empty($newlang) && GETPOST('lang_id', 'aZ09')) {
						$newlang = GETPOST('lang_id', 'aZ09');
					}
					if (empty($newlang)) {
						$newlang = $object->thirdparty->default_lang;
					}
					if (!empty($newlang)) {
						$outputlangs = new Translate("", $conf);
						$outputlangs->setDefaultLang($newlang);
					}

					$desc = (!empty($prod->multilangs[$outputlangs->defaultlang]["description"])) ? $prod->multilangs[$outputlangs->defaultlang]["description"] : $prod->description;
				} else {
					$desc = $prod->description;
				}

				//If text set in desc is the same as product descpription (as now it's preloaded) whe add it only one time
				if ($product_desc == $desc && !empty($conf->global->PRODUIT_AUTOFILL_DESC)) {
					$product_desc = '';
				}

				if (!empty($product_desc) && !empty($conf->global->MAIN_NO_CONCAT_DESCRIPTION)) {
					$desc = $product_desc;
				} else {
					$desc = dol_concatdesc($desc, $product_desc, '', !empty($conf->global->MAIN_CHANGE_ORDER_CONCAT_DESCRIPTION));
				}


				// Add custom code and origin country into description
				if (empty($conf->global->MAIN_PRODUCT_DISABLE_CUSTOMCOUNTRYCODE) && (!empty($prod->customcode) || !empty($prod->country_code))) {
					$tmptxt = '(';
					// Define output language
					if (getDolGlobalInt('MAIN_MULTILANGS') && !empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE)) {
						$outputlangs = $langs;
						$newlang = '';
						if (empty($newlang) && GETPOST('lang_id', 'alpha')) {
							$newlang = GETPOST('lang_id', 'alpha');
						}
						if (empty($newlang)) {
							$newlang = $object->thirdparty->default_lang;
						}
						if (!empty($newlang)) {
							$outputlangs = new Translate("", $conf);
							$outputlangs->setDefaultLang($newlang);
							$outputlangs->load('products');
						}
						if (!empty($prod->customcode)) {
							$tmptxt .= $outputlangs->transnoentitiesnoconv("CustomCode") . ': ' . $prod->customcode;
						}
						if (!empty($prod->customcode) && !empty($prod->country_code)) {
							$tmptxt .= ' - ';
						}
						if (!empty($prod->country_code)) {
							$tmptxt .= $outputlangs->transnoentitiesnoconv("CountryOrigin") . ': ' . getCountry($prod->country_code, 0, $db, $outputlangs, 0);
						}
					} else {
						if (!empty($prod->customcode)) {
							$tmptxt .= $langs->transnoentitiesnoconv("CustomCode") . ': ' . $prod->customcode;
						}
						if (!empty($prod->customcode) && !empty($prod->country_code)) {
							$tmptxt .= ' - ';
						}
						if (!empty($prod->country_code)) {
							$tmptxt .= $langs->transnoentitiesnoconv("CountryOrigin") . ': ' . getCountry($prod->country_code, 0, $db, $langs, 0);
						}
					}
					$tmptxt .= ')';
					$desc = dol_concatdesc($desc, $tmptxt);
				}

				$type = $prod->type;
				$fk_unit = $prod->fk_unit;
			} else {
				$pu_ht = price2num($price_ht, 'MU');
				$pu_ttc = price2num($price_ttc, 'MU');
				$tva_npr = (preg_match('/\*/', $tva_tx) ? 1 : 0);
				$tva_tx = str_replace('*', '', $tva_tx);
				if (empty($tva_tx)) {
					$tva_npr = 0;
				}
				$label = (GETPOST('product_label') ? GETPOST('product_label') : '');
				$desc = $product_desc;
				$type = GETPOST('type');
				$fk_unit = GETPOST('units', 'alpha');
				$pu_ht_devise = price2num($price_ht_devise, 'MU');
				$pu_ttc_devise = price2num($price_ttc_devise, 'MU');

				if ($pu_ttc && !$pu_ht) {
					$price_base_type = 'TTC';
				}
			}



			// Margin
			$fournprice = price2num(GETPOST('fournprice' . $predef) ? GETPOST('fournprice' . $predef) : '');
			$buyingprice = price2num(GETPOST('buying_price' . $predef) != '' ? GETPOST('buying_price' . $predef) : ''); // If buying_price is '0', we muste keep this value

			// Local Taxes
			$localtax1_tx = get_localtax($tva_tx, 1, $object->thirdparty);
			$localtax2_tx = get_localtax($tva_tx, 2, $object->thirdparty);

			$info_bits = 0;
			if ($tva_npr) {
				$info_bits |= 0x01;
			}

			$desc = dol_htmlcleanlastbr($desc);

			if ($usermustrespectpricemin) {
				if ($pu_ht && $price_min && ((price2num($pu_ht) * (1 - $remise_percent / 100)) < price2num($price_min))) {
					$mesg = $langs->trans("CantBeLessThanMinPrice", price(price2num($price_min, 'MU'), 0, $langs, 0, 0, -1, $conf->currency));
					setEventMessages($mesg, null, 'errors');
					$error++;
				} elseif ($pu_ttc && $price_min_ttc && ((price2num($pu_ttc) * (1 - $remise_percent / 100)) < price2num($price_min_ttc))) {
					$mesg = $langs->trans("CantBeLessThanMinPrice", price(price2num($price_min_ttc, 'MU'), 0, $langs, 0, 0, -1, $conf->currency));
					setEventMessages($mesg, null, 'errors');
					$error++;
				}
			}



			if (!$error) {
				// Insert line
				$result = $object->addline($desc, $pu_ht, $qty, $tva_tx, $localtax1_tx, $localtax2_tx, $idprod, $remise_percent, $info_bits, 0, $price_base_type, $pu_ttc, $date_start, $date_end, $type, min($rank, count($object->lines) + 1), 0, GETPOST('fk_parent_line'), $fournprice, $buyingprice, $label, $array_options, $fk_unit, '', 0, $pu_ht_devise);

				if ($result > 0) {
					$ret = $object->fetch($object->id); // Reload to get new records
					$object->fetch_thirdparty();

					if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
						// Define output language
						$outputlangs = $langs;
						$newlang = GETPOST('lang_id', 'alpha');
						if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {
							$newlang = $object->thirdparty->default_lang;
						}
						if (!empty($newlang)) {
							$outputlangs = new Translate("", $conf);
							$outputlangs->setDefaultLang($newlang);
						}

						$object->generateDocument($object->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
					}

					unset($_POST['prod_entry_mode']);

					unset($_POST['qty']);
					unset($_POST['type']);
					unset($_POST['remise_percent']);
					unset($_POST['price_ht']);
					unset($_POST['multicurrency_price_ht']);
					unset($_POST['price_ttc']);
					unset($_POST['tva_tx']);
					unset($_POST['product_ref']);
					unset($_POST['product_label']);
					unset($_POST['product_desc']);
					unset($_POST['fournprice']);
					unset($_POST['buying_price']);
					unset($_POST['np_marginRate']);
					unset($_POST['np_markRate']);
					unset($_POST['dp_desc']);
					unset($_POST['idprod']);
					unset($_POST['units']);

					unset($_POST['date_starthour']);
					unset($_POST['date_startmin']);
					unset($_POST['date_startsec']);
					unset($_POST['date_startday']);
					unset($_POST['date_startmonth']);
					unset($_POST['date_startyear']);
					unset($_POST['date_endhour']);
					unset($_POST['date_endmin']);
					unset($_POST['date_endsec']);
					unset($_POST['date_endday']);
					unset($_POST['date_endmonth']);
					unset($_POST['date_endyear']);
				} else {
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
		}
	} elseif ($action == 'updateline' && $usercancreate && GETPOST('save')) {
		// Update a line
		// Clean parameters
		$date_start = '';
		$date_end = '';
		$date_start = dol_mktime(GETPOST('date_starthour'), GETPOST('date_startmin'), GETPOST('date_startsec'), GETPOST('date_startmonth'), GETPOST('date_startday'), GETPOST('date_startyear'));
		$date_end = dol_mktime(GETPOST('date_endhour'), GETPOST('date_endmin'), GETPOST('date_endsec'), GETPOST('date_endmonth'), GETPOST('date_endday'), GETPOST('date_endyear'));
		$description = dol_htmlcleanlastbr(GETPOST('product_desc', 'restricthtml'));
		$vat_rate = (GETPOST('tva_tx') ? GETPOST('tva_tx', 'alpha') : 0);
		$vat_rate = str_replace('*', '', $vat_rate);

		$pu_ht = price2num(GETPOST('price_ht'), '', 2);
		$pu_ttc = price2num(GETPOST('price_ttc'), '', 2);

		$pu_ht_devise = price2num(GETPOST('multicurrency_subprice'), '', 2);
		$pu_ttc_devise = price2num(GETPOST('multicurrency_subprice_ttc'), '', 2);

		$qty = price2num(GETPOST('qty', 'alpha'), 'MS');

		// Define info_bits
		$info_bits = 0;
		if (preg_match('/\*/', $vat_rate)) {
			$info_bits |= 0x01;
		}

		// Define vat_rate
		$vat_rate = str_replace('*', '', $vat_rate);
		$localtax1_rate = get_localtax($vat_rate, 1, $object->thirdparty, $mysoc);
		$localtax2_rate = get_localtax($vat_rate, 2, $object->thirdparty, $mysoc);

		// Add buying price
		$fournprice = price2num(GETPOST('fournprice') ? GETPOST('fournprice') : '');
		$buyingprice = price2num(GETPOST('buying_price') != '' ? GETPOST('buying_price') : ''); // If buying_price is '0', we muste keep this value

		// Extrafields Lines
		$extralabelsline = $extrafields->fetch_name_optionals_label($object->table_element_line);
		$array_options = $extrafields->getOptionalsFromPost($object->table_element_line);
		// Unset extrafield POST Data
		if (is_array($extralabelsline)) {
			foreach ($extralabelsline as $key => $value) {
				unset($_POST["options_" . $key]);
			}
		}

		// Define special_code for special lines
		$special_code = GETPOST('special_code');
		if (!GETPOST('qty')) {
			$special_code = 3;
		}

		$remise_percent = GETPOST('remise_percent') != '' ? price2num(GETPOST('remise_percent'), '', 2) : 0;

		// Check minimum price
		$productid = GETPOST('productid', 'int');
		if (!empty($productid)) {
			$product = new Product($db);
			$product->fetch($productid);

			$type = $product->type;

			$price_min = $product->price_min;
			if ((!empty($conf->global->PRODUIT_MULTIPRICES) || !empty($conf->global->PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES)) && !empty($object->thirdparty->price_level)) {
				$price_min = $product->multiprices_min[$object->thirdparty->price_level];
			}
			$price_min_ttc = $product->price_min_ttc;
			if ((!empty($conf->global->PRODUIT_MULTIPRICES) || !empty($conf->global->PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES)) && !empty($object->thirdparty->price_level)) {
				$price_min_ttc = $product->multiprices_min_ttc[$object->thirdparty->price_level];
			}

			$label = ((GETPOST('update_label') && GETPOST('product_label')) ? GETPOST('product_label') : '');

			if ($usermustrespectpricemin) {
				if ($pu_ht && $price_min && ((price2num($pu_ht) * (1 - $remise_percent / 100)) < price2num($price_min))) {
					$mesg = $langs->trans("CantBeLessThanMinPrice", price(price2num($price_min, 'MU'), 0, $langs, 0, 0, -1, $conf->currency));
					setEventMessages($mesg, null, 'errors');
					$error++;
					$action = 'editline';
				} elseif ($pu_ttc && $price_min_ttc && ((price2num($pu_ttc) * (1 - $remise_percent / 100)) < price2num($price_min_ttc))) {
					$mesg = $langs->trans("CantBeLessThanMinPrice", price(price2num($price_min_ttc, 'MU'), 0, $langs, 0, 0, -1, $conf->currency));
					setEventMessages($mesg, null, 'errors');
					$error++;
					$action = 'editline';
				}
			}
		} else {
			$type = GETPOST('type');
			$label = (GETPOST('product_label') ? GETPOST('product_label') : '');

			// Check parameters
			if (GETPOST('type') < 0) {
				setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Type")), null, 'errors');
				$error++;
				$action = 'editline';
			}
		}

		if ($qty < 0) {
			setEventMessages($langs->trans('FieldCannotBeNegative', $langs->transnoentitiesnoconv('Qty')), null, 'errors');
			$error++;
			$action = 'editline';
		}

		if (!$error) {
			if (empty($user->rights->margins->creer)) {
				foreach ($object->lines as &$line) {
					if ($line->id == GETPOST('lineid', 'int')) {
						$fournprice = $line->fk_fournprice;
						$buyingprice = $line->pa_ht;
						break;
					}
				}
			}

			$price_base_type = 'HT';
			$pu = $pu_ht;
			if (empty($pu) && !empty($pu_ttc)) {
				$pu = $pu_ttc;
				$price_base_type = 'TTC';
			}

			$result = $object->updateline(GETPOST('lineid', 'int'), $description, $pu, $qty, $remise_percent, $vat_rate, $localtax1_rate, $localtax2_rate, $price_base_type, $info_bits, $date_start, $date_end, $type, GETPOST('fk_parent_line'), 0, $fournprice, $buyingprice, $label, $special_code, $array_options, GETPOST('units'), $pu_ht_devise);

			if ($result >= 0) {
				if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
					// Define output language
					$outputlangs = $langs;
					$newlang = '';
					if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
						$newlang = GETPOST('lang_id', 'aZ09');
					}
					if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {
						$newlang = $object->thirdparty->default_lang;
					}
					if (!empty($newlang)) {
						$outputlangs = new Translate("", $conf);
						$outputlangs->setDefaultLang($newlang);
					}

					$ret = $object->fetch($object->id); // Reload to get new records
					$object->generateDocument($object->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
				}

				unset($_POST['qty']);
				unset($_POST['type']);
				unset($_POST['productid']);
				unset($_POST['remise_percent']);
				unset($_POST['price_ht']);
				unset($_POST['multicurrency_price_ht']);
				unset($_POST['price_ttc']);
				unset($_POST['tva_tx']);
				unset($_POST['product_ref']);
				unset($_POST['product_label']);
				unset($_POST['product_desc']);
				unset($_POST['fournprice']);
				unset($_POST['buying_price']);

				unset($_POST['date_starthour']);
				unset($_POST['date_startmin']);
				unset($_POST['date_startsec']);
				unset($_POST['date_startday']);
				unset($_POST['date_startmonth']);
				unset($_POST['date_startyear']);
				unset($_POST['date_endhour']);
				unset($_POST['date_endmin']);
				unset($_POST['date_endsec']);
				unset($_POST['date_endday']);
				unset($_POST['date_endmonth']);
				unset($_POST['date_endyear']);
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
	} elseif ($action == 'updateline' && $usercancreate && GETPOST('cancel', 'alpha')) {
		header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id); // Pour reaffichage de la fiche en cours d'edition
		exit();
	}


	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT . '/core/actions_printing.inc.php';

	// Actions to build doc
	$upload_dir = !empty($conf->commande->multidir_output[$object->entity]) ? $conf->commande->multidir_output[$object->entity] : $conf->commande->dir_output;
	$permissiontoadd = $usercancreate;
	include DOL_DOCUMENT_ROOT . '/core/actions_builddoc.inc.php';

	// Actions to send emails
	$triggersendname = 'ORDER_SENTBYMAIL';
	$paramname = 'id';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_ORDER_TO'; // used to know the automatic BCC to add
	$trackid = 'ord' . $object->id;
	include DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';


	if (!$error && !empty($conf->global->MAIN_DISABLE_CONTACTS_TAB) && $usercancreate) {
		if ($action == 'addcontact') {
			if ($object->id > 0) {
				$contactid = (GETPOST('userid') ? GETPOST('userid') : GETPOST('contactid'));
				$typeid = (GETPOST('typecontact') ? GETPOST('typecontact') : GETPOST('type'));
				$result = $object->add_contact($contactid, $typeid, GETPOST("source", 'aZ09'));
			}

			if ($result >= 0) {
				header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $object->id);
				exit();
			} else {
				if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
					$langs->load("errors");
					setEventMessages($langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType"), null, 'errors');
				} else {
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
		} elseif ($action == 'swapstatut') {
			// bascule du statut d'un contact
			if ($object->id > 0) {
				$result = $object->swapContactStatus(GETPOST('ligne', 'int'));
			} else {
				dol_print_error($db);
			}
		} elseif ($action == 'deletecontact') {
			// Efface un contact
			$result = $object->delete_contact($lineid);

			if ($result >= 0) {
				header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $object->id);
				exit();
			} else {
				dol_print_error($db);
			}
		}
	}
}


/*
 *	View
 */

$title = $object->ref . " - " . $langs->trans('Card');
if ($action == 'create') {
	$title = $langs->trans("NewOrder");
}
$help_url = 'EN:Customers_Orders|FR:Commandes_Clients|ES:Pedidos de clientes|DE:Modul_Kundenaufträge';

llxHeader('', $title, $help_url);

$form = new Form($db);
$formfile = new FormFile($db);
$formorder = new FormOrder($db);
$formmargin = new FormMargin($db);
if (isModEnabled('project')) {
	$formproject = new FormProjets($db);
}

if (1) {
	// Mode view
	$now = dol_now();

	if ($object->id > 0) {


		$product_static = new Product($db);

		$soc = new Societe($db);
		$soc->fetch($object->socid);

		$author = new User($db);
		$author->fetch($object->user_author_id);

		$object->fetch_thirdparty();
		$res = $object->fetch_optionals();

		$head = commande_prepare_head($object);
		print dol_get_fiche_head($head, 'tabname1', $langs->trans("CustomerOrder"), -1, 'order');

		$formconfirm = '';

		// Confirmation to delete
		if ($action == 'delete') {
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteOrder'), $langs->trans('ConfirmDeleteOrder'), 'confirm_delete', '', 0, 1);
		}

		// Confirmation of validation
		if ($action == 'validate') {

			$text = $langs->trans('ConfirmValidatewarehouserequest', $numref);


			$formquestion = array();

			if (!$error) {
				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ValidateWarehouserequest'), $text, 'confirm_validate', $formquestion, 0, 1, 220);
			}
		}



















		// Confirm back to draft status
		if ($action == 'modif') {
			$qualified_for_stock_change = 0;
			if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
				$qualified_for_stock_change = $object->hasProductsOrServices(2);
			} else {
				$qualified_for_stock_change = $object->hasProductsOrServices(1);
			}

			$text = $langs->trans('ConfirmUnvalidateOrder', $object->ref);
			$formquestion = array();
			if (isModEnabled('stock') && !empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $qualified_for_stock_change) {
				$langs->load("stocks");
				require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
				$formproduct = new FormProduct($db);
				$forcecombo = 0;
				if ($conf->browser->name == 'ie') {
					$forcecombo = 1; // There is a bug in IE10 that make combo inside popup crazy
				}
				$formquestion = array(
					// 'text' => $langs->trans("ConfirmClone"),
					// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
					// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
					array('type' => 'other', 'name' => 'idwarehouse', 'label' => $langs->trans("SelectWarehouseForStockIncrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse') ? GETPOST('idwarehouse') : 'ifone', 'idwarehouse', '', 1, 0, 0, '', 0, $forcecombo))
				);
			}

			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('UnvalidateOrder'), $text, 'confirm_modif', $formquestion, "yes", 1, 220);
		}

		/*
		 * Confirmation de la cloture
		*/
		if ($action == 'shipped') {
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('CloseOrder'), $langs->trans('ConfirmCloseOrder'), 'confirm_shipped', '', 0, 1);
		}

		/*
		 * Confirmation de l'annulation
		 */
		if ($action == 'cancel') {
			$qualified_for_stock_change = 0;
			if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
				$qualified_for_stock_change = $object->hasProductsOrServices(2);
			} else {
				$qualified_for_stock_change = $object->hasProductsOrServices(1);
			}

			$text = $langs->trans('ConfirmCancelOrder', $object->ref);
			$formquestion = array();
			if (isModEnabled('stock') && !empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $qualified_for_stock_change) {
				$langs->load("stocks");
				require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
				$formproduct = new FormProduct($db);
				$forcecombo = 0;
				if ($conf->browser->name == 'ie') {
					$forcecombo = 1; // There is a bug in IE10 that make combo inside popup crazy
				}
				$formquestion = array(
					// 'text' => $langs->trans("ConfirmClone"),
					// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
					// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
					array('type' => 'other', 'name' => 'idwarehouse', 'label' => $langs->trans("SelectWarehouseForStockIncrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse') ? GETPOST('idwarehouse') : 'ifone', 'idwarehouse', '', 1, 0, 0, '', 0, $forcecombo))
				);
			}

			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans("Cancel"), $text, 'confirm_cancel', $formquestion, 0, 1);
		}

		// Confirmation to delete line
		if ($action == 'ask_deleteline') {
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&lineid=' . $lineid, $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline', '', 0, 1);
		}

		// Clone confirmation
		if ($action == 'clone') {
			// Create an array for form
			$formquestion = array(
				array('type' => 'other', 'name' => 'socid', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company(GETPOST('socid', 'int'), 'socid', '(s.client=1 OR s.client = 2 OR s.client=3)', '', 0, 0, null, 0, 'maxwidth300'))
			);
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneOrder', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
		}

		// Call Hook formConfirm
		$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
		// Note that $action and $object may be modified by hook
		$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action);
		if (empty($reshook)) {
			$formconfirm .= $hookmanager->resPrint;
		} elseif ($reshook > 0) {
			$formconfirm = $hookmanager->resPrint;
		}

		// Print form confirm
		print $formconfirm;


		// Order card

		$linkback = '<a href="' . DOL_URL_ROOT . '/commande/list.php?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';

		$morehtmlref = '<div class="refidno">';
		// Ref customer
		$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', 0, 1);
		$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string' . (isset($conf->global->THIRDPARTY_REF_INPUT_SIZE) ? ':' . $conf->global->THIRDPARTY_REF_INPUT_SIZE : ''), '', null, null, '', 1);
		// Thirdparty
		$morehtmlref .= '<br>' . $soc->getNomUrl(1, 'customer');
		if (empty($conf->global->MAIN_DISABLE_OTHER_LINK) && $object->thirdparty->id > 0) {
			$morehtmlref .= ' (<a href="' . DOL_URL_ROOT . '/commande/list.php?socid=' . $object->thirdparty->id . '&search_societe=' . urlencode($object->thirdparty->name) . '">' . $langs->trans("OtherOrders") . '</a>)';
		}
		// Project
		if (isModEnabled('project')) {
			$langs->load("projects");
			$morehtmlref .= '<br>';
			if ($usercancreate) {
				$morehtmlref .= img_picto($langs->trans("Project"), 'project', 'class="pictofixedwidth"');
				if ($action != 'classify') {
					$morehtmlref .= '<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&token=' . newToken() . '&id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> ';
				}
				$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, ($action == 'classify' ? 1 : 0), 0, 1, '');
			} else {
				if (!empty($object->fk_project)) {
					$proj = new Project($db);
					$proj->fetch($object->fk_project);
					$morehtmlref .= $proj->getNomUrl(1);
					if ($proj->title) {
						$morehtmlref .= '<span class="opacitymedium"> - ' . dol_escape_htmltag($proj->title) . '</span>';
					}
				}
			}
		}
		$morehtmlref .= '</div>';


		dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


		print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';

		print '<table class="border tableforfield centpercent">';






		// Shipping Method
		if (isModEnabled('expedition')) {
			print '<tr><td>';
			$editenable = $usercancreate;
			print $form->editfieldkey("SendingMethod", 'shippingmethod', '', $object, $editenable);
			print '</td><td class="valuefield">';
			if ($action == 'editshippingmethod') {
				$form->formSelectShippingMethod($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->shipping_method_id, 'shipping_method_id', 1);
			} else {
				$form->formSelectShippingMethod($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->shipping_method_id, 'none');
			}
			print '</td>';
			print '</tr>';
		}

		// Warehouse
		if (isModEnabled('stock') && !empty($conf->global->WAREHOUSE_ASK_WAREHOUSE_DURING_ORDER)) {
			$langs->load('stocks');
			require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
			$formproduct = new FormProduct($db);
			print '<tr><td>';
			$editenable = $usercancreate;
			print $form->editfieldkey("Warehouse", 'warehouse', '', $object, $editenable);
			print '</td><td class="valuefield">';
			if ($action == 'editwarehouse') {
				$formproduct->formSelectWarehouses($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->warehouse_id, 'warehouse_id', 1);
			} else {
				$formproduct->formSelectWarehouses($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->warehouse_id, 'none');
			}
			print '</td>';
			print '</tr>';
		}





		print '</table>';

		print '</div>';
		print '<div class="fichehalfright">';
		print '<div class="underbanner clearboth" style="display:none;"></div>';

		print '<table class="border tableforfield centpercent" style="display:none;">';

		if (isModEnabled("multicurrency") && ($object->multicurrency_code != $conf->currency)) {
			// Multicurrency Amount HT
			print '<tr><td class="titlefieldmiddle">' . $form->editfieldkey('MulticurrencyAmountHT', 'multicurrency_total_ht', '', $object, 0) . '</td>';
			print '<td class="valuefield nowrap right amountcard">' . price($object->multicurrency_total_ht, '', $langs, 0, -1, -1, (!empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency)) . '</td>';
			print '</tr>';

			// Multicurrency Amount VAT
			print '<tr><td>' . $form->editfieldkey('MulticurrencyAmountVAT', 'multicurrency_total_tva', '', $object, 0) . '</td>';
			print '<td class="valuefield nowrap right amountcard">' . price($object->multicurrency_total_tva, '', $langs, 0, -1, -1, (!empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency)) . '</td>';
			print '</tr>';

			// Multicurrency Amount TTC
			print '<tr><td>' . $form->editfieldkey('MulticurrencyAmountTTC', 'multicurrency_total_ttc', '', $object, 0) . '</td>';
			print '<td class="valuefield nowrap right amountcard">' . price($object->multicurrency_total_ttc, '', $langs, 0, -1, -1, (!empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency)) . '</td>';
			print '</tr>';
		}

		// Total HT
		$alert = '';
		if (!empty($conf->global->ORDER_MANAGE_MIN_AMOUNT) && $object->total_ht < $object->thirdparty->order_min_amount) {
			$alert = ' ' . img_warning($langs->trans('OrderMinAmount') . ': ' . price($object->thirdparty->order_min_amount));
		}
		print '<tr><td class="titlefieldmiddle">' . $langs->trans('AmountHT') . '</td>';
		print '<td class="valuefield nowrap right amountcard">' . price($object->total_ht, 1, '', 1, -1, -1, $conf->currency) . $alert . '</td>';

		// Total VAT
		print '<tr><td>' . $langs->trans('AmountVAT') . '</td><td class="valuefield nowrap right amountcard">' . price($object->total_tva, 1, '', 1, -1, -1, $conf->currency) . '</td></tr>';

		// Amount Local Taxes
		if ($mysoc->localtax1_assuj == "1" || $object->total_localtax1 != 0) { 		// Localtax1
			print '<tr><td>' . $langs->transcountry("AmountLT1", $mysoc->country_code) . '</td>';
			print '<td class="valuefield nowrap right amountcard">' . price($object->total_localtax1, 1, '', 1, -1, -1, $conf->currency) . '</td></tr>';
		}
		if ($mysoc->localtax2_assuj == "1" || $object->total_localtax2 != 0) { 		// Localtax2 IRPF
			print '<tr><td>' . $langs->transcountry("AmountLT2", $mysoc->country_code) . '</td>';
			print '<td class="valuefield nowrap right amountcard">' . price($object->total_localtax2, 1, '', 1, -1, -1, $conf->currency) . '</td></tr>';
		}

		// Total TTC
		print '<tr><td>' . $langs->trans('AmountTTC') . '</td><td class="valuefield nowrap right amountcard">' . price($object->total_ttc, 1, '', 1, -1, -1, $conf->currency) . '</td></tr>';

		// Statut
		//print '<tr><td>' . $langs->trans('Status') . '</td><td>' . $object->getLibStatut(4) . '</td></tr>';

		print '</table>';

		// Margin Infos
		if (isModEnabled('margin')) {
			$formmargin->displayMarginInfos($object);
		}


		print '</div>';
		print '</div>'; // Close fichecenter

		print '<div class="clearboth"></div><br>';

		if (!empty($conf->global->MAIN_DISABLE_CONTACTS_TAB)) {
			$blocname = 'contacts';
			$title = $langs->trans('ContactsAddresses');
			include DOL_DOCUMENT_ROOT . '/core/tpl/bloc_showhide.tpl.php';
		}

		if (!empty($conf->global->MAIN_DISABLE_NOTES_TAB)) {
			$blocname = 'notes';
			$title = $langs->trans('Notes');
			include DOL_DOCUMENT_ROOT . '/core/tpl/bloc_showhide.tpl.php';
		}

		/*
		 * Lines
		 */

		// Get object lines
		$result = $object->getLinesArray();

		$object->statut = 0;

		// Add products/services form
		//$forceall = 1;
		global $inputalsopricewithtax;
		$inputalsopricewithtax = 1;

		print '<form name="addproduct" id="addproduct" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">
		<input type="hidden" name="token" value="' . newToken() . '">
		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline') . '">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="page_y" value="">
		<input type="hidden" name="id" value="' . $object->id . '">';

		/*
		if (!empty($conf->use_javascript_ajax) && $object->statut == Commande::STATUS_DRAFT) {
			include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
		}
		*/

		print '<div class="div-table-responsive-no-min">';
		print '<table id="tablelines" class="noborder noshadow" width="100%">';

		// Show object lines
		if (!empty($object->lines)) {
			$object->printObjectLines($action, $mysoc, $soc, $lineid, 1);
		}

		$numlines = count($object->lines);


		/*
		 * Form to add new line
		 */
		if (1 && $usercancreate && $action != 'selectlines') {
			if ($action != 'editline') {
				// Add free products/services

				$parameters = array();
				// Note that $action and $object may be modified by hook
				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action);
				if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				if (empty($reshook))
					$object->formAddObjectLine(1, $mysoc, $soc);
			}
		}
		print '</table>';
		print '</div>';

		print "</form>\n";

		print dol_get_fiche_end();

		/*
		 * Buttons for actions
		 */
		if ($action != 'presend' && $action != 'editline') {
			print '<div class="tabsAction">';

			$parameters = array();
			// Note that $action and $object may be modified by hook
			$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
			if (empty($reshook)) {


				// Valid
				if (1 && ($object->total_ttc >= 0 || !empty($conf->global->ORDER_ENABLE_NEGATIVE)) && $numlines > 0 && $usercanvalidate) {
					print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER["PHP_SELF"] . '?action=validate&amp;token=' . newToken() . '&amp;id=' . $object->id, '');
				}
			}
			print '</div>';
		}
	}
}

print '<script>
$(document).ready(function(){
  $(".imgupforline, .imgdownforline").hide();
});
</script>';

// End of page
llxFooter();
$db->close();
