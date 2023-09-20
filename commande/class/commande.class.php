<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2014 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
 * Copyright (C) 2010-2020 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2011      Jean Heimburger      <jean@tiaris.info>
 * Copyright (C) 2012-2014 Christophe Battarel  <christophe.battarel@altairis.fr>
 * Copyright (C) 2012      Cedric Salvador      <csalvador@gpcsolutions.fr>
 * Copyright (C) 2013      Florian Henry		<florian.henry@open-concept.pro>
 * Copyright (C) 2014-2015 Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2018      Nicolas ZABOURI	    <info@inovea-conseil.com>
 * Copyright (C) 2016-2022 Ferran Marcet        <fmarcet@2byte.es>
 * Copyright (C) 2021-2022 Frédéric France      <frederic.france@netlogic.fr>
 * Copyright (C) 2022      Gauthier VERDOL      <gauthier.verdol@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */


require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';


/**
 *  Class to manage warehouserequest
 */
class Warehouserequest extends Commande
{


	public function deleteallwarehouserequest()
	{
		global $conf, $langs, $user;

		$lines = $this->lines;
		foreach ($lines as $linekey => $linedata) {
			$result = $this->deleteline($user, $linedata->id);
		}
	}


	/**
	 *  Delete an order line
	 *
	 *	@param      User	$user		User object
	 *  @param      int		$lineid		Id of line to delete
	 *  @return     int        		 	>0 if OK, 0 if nothing to do, <0 if KO
	 */
	public function deleteline($user = null, $lineid = 0)
	{
		if (1) {
			$this->db->begin();

			$sql = "SELECT fk_product, qty";
			$sql .= " FROM " . MAIN_DB_PREFIX . "warehouserequest";
			$sql .= " WHERE rowid = " . ((int) $lineid);

			$result = $this->db->query($sql);
			if ($result) {
				$obj = $this->db->fetch_object($result);

				if ($obj) {
					$product = new Product($this->db);
					$product->id = $obj->fk_product;

					// Delete line
					$line = new WarehouserequestLine($this->db);

					// For triggers
					$line->fetch($lineid);

					// Memorize previous line for triggers
					$staticline = clone $line;
					$line->oldline = $staticline;

					if ($line->delete($user) > 0) {
						//	$result = $this->update_price(1);

						if ($result > 0) {
							$this->db->commit();
							return 1;
						} else {
							$this->db->rollback();
							$this->error = $this->db->lasterror();
							return -1;
						}
					} else {
						$this->db->rollback();
						$this->error = $line->error;
						return -1;
					}
				} else {
					$this->db->rollback();
					return 0;
				}
			} else {
				$this->db->rollback();
				$this->error = $this->db->lasterror();
				return -1;
			}
		} else {
			$this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
			return -1;
		}
	}


	/**
	 *  Update a line in database
	 *
	 *  @param    	int				$rowid            	Id of line to update
	 *  @param    	string			$desc             	Description of line
	 *  @param    	float			$pu               	Unit price
	 *  @param    	float			$qty              	Quantity
	 *  @param    	float			$remise_percent   	Percent of discount
	 *  @param    	float			$txtva           	Taux TVA
	 * 	@param		float			$txlocaltax1		Local tax 1 rate
	 *  @param		float			$txlocaltax2		Local tax 2 rate
	 *  @param    	string			$price_base_type	HT or TTC
	 *  @param    	int				$info_bits        	Miscellaneous informations on line
	 *  @param    	int				$date_start        	Start date of the line
	 *  @param    	int				$date_end          	End date of the line
	 * 	@param		int				$type				Type of line (0=product, 1=service)
	 * 	@param		int				$fk_parent_line		Id of parent line (0 in most cases, used by modules adding sublevels into lines).
	 * 	@param		int				$skip_update_total	Keep fields total_xxx to 0 (used for special lines by some modules)
	 *  @param		int				$fk_fournprice		Id of origin supplier price
	 *  @param		int				$pa_ht				Price (without tax) of product when it was bought
	 *  @param		string			$label				Label
	 *  @param		int				$special_code		Special code (also used by externals modules!)
	 *  @param		array			$array_options		extrafields array
	 * 	@param 		string			$fk_unit 			Code of the unit to use. Null to use the default one
	 *  @param		double			$pu_ht_devise		Amount in currency
	 * 	@param		int				$notrigger			disable line update trigger
	 * 	@param		string			$ref_ext			external reference
	 * @param       integer $rang   line rank
	 *  @return   	int              					< 0 if KO, > 0 if OK
	 */
	public function updateline($rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1 = 0.0, $txlocaltax2 = 0.0, $price_base_type = 'HT', $info_bits = 0, $date_start = '', $date_end = '', $type = 0, $fk_parent_line = 0, $skip_update_total = 0, $fk_fournprice = null, $pa_ht = 0, $label = '', $special_code = 0, $array_options = 0, $fk_unit = null, $pu_ht_devise = 0, $notrigger = 0, $ref_ext = '', $rang = 0)
	{
		global $conf, $mysoc, $langs, $user;

		dol_syslog(get_class($this) . "::updateline id=$rowid, desc=$desc, pu=$pu, qty=$qty, remise_percent=$remise_percent, txtva=$txtva, txlocaltax1=$txlocaltax1, txlocaltax2=$txlocaltax2, price_base_type=$price_base_type, info_bits=$info_bits, date_start=$date_start, date_end=$date_end, type=$type, fk_parent_line=$fk_parent_line, pa_ht=$pa_ht, special_code=$special_code, ref_ext=$ref_ext");
		include_once DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php';

		if (1) {
			// Clean parameters
			if (empty($qty)) {
				$qty = 0;
			}
			if (empty($info_bits)) {
				$info_bits = 0;
			}
			if (empty($txtva)) {
				$txtva = 0;
			}
			if (empty($txlocaltax1)) {
				$txlocaltax1 = 0;
			}
			if (empty($txlocaltax2)) {
				$txlocaltax2 = 0;
			}
			if (empty($remise_percent)) {
				$remise_percent = 0;
			}
			if (empty($special_code) || $special_code == 3) {
				$special_code = 0;
			}
			if (empty($ref_ext)) {
				$ref_ext = '';
			}

			if ($date_start && $date_end && $date_start > $date_end) {
				$langs->load("errors");
				$this->error = $langs->trans('ErrorStartDateGreaterEnd');
				return -1;
			}

			$remise_percent = price2num($remise_percent);
			$qty = price2num($qty);
			$pu = price2num($pu);
			$pa_ht = price2num($pa_ht);
			$pu_ht_devise = price2num($pu_ht_devise);
			if (!preg_match('/\((.*)\)/', $txtva)) {
				$txtva = price2num($txtva); // $txtva can have format '5.0(XXX)' or '5'
			}
			$txlocaltax1 = price2num($txlocaltax1);
			$txlocaltax2 = price2num($txlocaltax2);

			$this->db->begin();

			// Calcul du total TTC et de la TVA pour la ligne a partir de
			// qty, pu, remise_percent et txtva
			// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
			// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.

			$localtaxes_type = getLocalTaxesFromRate($txtva, 0, $this->thirdparty, $mysoc);

			// Clean vat code
			$vat_src_code = '';
			$reg = array();
			if (preg_match('/\((.*)\)/', $txtva, $reg)) {
				$vat_src_code = $reg[1];
				$txtva = preg_replace('/\s*\(.*\)/', '', $txtva); // Remove code into vatrate.
			}

			$tabprice = calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits, $type, $mysoc, $localtaxes_type, 100, $this->multicurrency_tx, $pu_ht_devise);

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

			//Fetch current line from the database and then clone the object and set it in $oldline property
			$line = new WarehouserequestLine($this->db);
			$line->fetch($rowid);
			$line->fetch_optionals();

			if (!empty($line->fk_product)) {
				$product = new Product($this->db);
				$result = $product->fetch($line->fk_product);
				$product_type = $product->type;

				if (!empty($conf->global->STOCK_MUST_BE_ENOUGH_FOR_ORDER) && $product_type == 0 && $product->stock_reel < $qty) {
					$langs->load("errors");
					$this->error = $langs->trans('ErrorStockIsNotEnoughToAddProductOnOrder', $product->ref);
					$this->errors[] = $this->error;

					dol_syslog(get_class($this) . "::addline error=Product " . $product->ref . ": " . $this->error, LOG_ERR);

					$this->db->rollback();
					return self::STOCK_NOT_ENOUGH_FOR_ORDER;
				}
			}

			$staticline = clone $line;

			$line->oldline = $staticline;
			$this->line = $line;
			$this->line->context = $this->context;
			$this->line->rang = $rang;

			// Reorder if fk_parent_line change
			if (!empty($fk_parent_line) && !empty($staticline->fk_parent_line) && $fk_parent_line != $staticline->fk_parent_line) {
				$rangmax = $this->line_max($fk_parent_line);
				$this->line->rang = $rangmax + 1;
			}

			$this->line->id = $rowid;
			$this->line->label = $label;
			$this->line->desc = $desc;
			$this->line->qty = $qty;
			$this->line->ref_ext = $ref_ext;

			$this->line->vat_src_code = $vat_src_code;
			$this->line->tva_tx         = $txtva;
			$this->line->localtax1_tx   = $txlocaltax1;
			$this->line->localtax2_tx   = $txlocaltax2;
			$this->line->localtax1_type = empty($localtaxes_type[0]) ? '' : $localtaxes_type[0];
			$this->line->localtax2_type = empty($localtaxes_type[2]) ? '' : $localtaxes_type[2];
			$this->line->remise_percent = $remise_percent;
			$this->line->subprice       = $subprice;
			$this->line->info_bits      = $info_bits;
			$this->line->special_code   = $special_code;
			$this->line->total_ht       = $total_ht;
			$this->line->total_tva      = $total_tva;
			$this->line->total_localtax1 = $total_localtax1;
			$this->line->total_localtax2 = $total_localtax2;
			$this->line->total_ttc      = $total_ttc;
			$this->line->date_start     = $date_start;
			$this->line->date_end       = $date_end;
			$this->line->product_type   = $type;
			$this->line->fk_parent_line = $fk_parent_line;
			$this->line->skip_update_total = $skip_update_total;
			$this->line->fk_unit        = $fk_unit;

			$this->line->fk_fournprice = $fk_fournprice;
			$this->line->pa_ht = $pa_ht;

			// Multicurrency
			$this->line->multicurrency_subprice		= $pu_ht_devise;
			$this->line->multicurrency_total_ht 	= $multicurrency_total_ht;
			$this->line->multicurrency_total_tva 	= $multicurrency_total_tva;
			$this->line->multicurrency_total_ttc 	= $multicurrency_total_ttc;

			// TODO deprecated
			$this->line->price = $price;

			if (is_array($array_options) && count($array_options) > 0) {
				// We replace values in this->line->array_options only for entries defined into $array_options
				foreach ($array_options as $key => $value) {
					$this->line->array_options[$key] = $array_options[$key];
				}
			}

			$result = $this->line->update($user, $notrigger);
			if ($result > 0) {
				// Reorder if child line
				if (!empty($fk_parent_line)) {
					$this->line_order(true, 'DESC');
				}

				// Mise a jour info denormalisees
				//	$this->update_price(1);

				$this->db->commit();
				return $result;
			} else {
				$this->error = $this->line->error;

				$this->db->rollback();
				return -1;
			}
		} else {
			$this->error = get_class($this) . "::updateline Order status makes operation forbidden";
			$this->errors = array('OrderStatusMakeOperationForbidden');
			return -2;
		}
	}




	/**
	 *	Get object from database. Get also lines.
	 *
	 *	@param      int			$id       		Id of object to load
	 * 	@param		string		$ref			Ref of object
	 * 	@param		string		$ref_ext		External reference of object
	 * 	@param		string		$notused		Internal reference of other object
	 *	@return     int         				>0 if OK, <0 if KO, 0 if not found
	 */
	public function fetch($id, $ref = '', $ref_ext = '', $notused = '')
	{
		// Check parameters
		if (empty($id) && empty($ref) && empty($ref_ext)) {
			return -1;
		}

		$sql = 'SELECT c.rowid, c.entity, c.date_creation, c.ref, c.fk_soc, c.fk_user_author, c.fk_user_valid, c.fk_user_modif, c.fk_statut';
		$sql .= ', c.amount_ht, c.total_ht, c.total_ttc, c.total_tva, c.localtax1 as total_localtax1, c.localtax2 as total_localtax2, c.fk_cond_reglement, c.deposit_percent, c.fk_mode_reglement, c.fk_availability, c.fk_input_reason';
		$sql .= ', c.fk_account';
		$sql .= ', c.date_commande, c.date_valid, c.tms';
		$sql .= ', c.date_livraison as delivery_date';
		$sql .= ', c.fk_shipping_method';
		$sql .= ', c.fk_warehouse';
		$sql .= ', c.fk_projet as fk_project, c.remise_percent, c.remise, c.remise_absolue, c.source, c.facture as billed';
		$sql .= ', c.note_private, c.note_public, c.ref_client, c.ref_ext, c.ref_int, c.model_pdf, c.last_main_doc, c.fk_delivery_address, c.extraparams';
		$sql .= ', c.fk_incoterms, c.location_incoterms';
		$sql .= ", c.fk_multicurrency, c.multicurrency_code, c.multicurrency_tx, c.multicurrency_total_ht, c.multicurrency_total_tva, c.multicurrency_total_ttc";
		$sql .= ", c.module_source, c.pos_source";
		$sql .= ", i.libelle as label_incoterms";
		$sql .= ', p.code as mode_reglement_code, p.libelle as mode_reglement_libelle';
		$sql .= ', cr.code as cond_reglement_code, cr.libelle as cond_reglement_libelle, cr.libelle_facture as cond_reglement_libelle_doc';
		$sql .= ', ca.code as availability_code, ca.label as availability_label';
		$sql .= ', dr.code as demand_reason_code';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'commande as c';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_payment_term as cr ON c.fk_cond_reglement = cr.rowid';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_paiement as p ON c.fk_mode_reglement = p.id';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_availability as ca ON c.fk_availability = ca.rowid';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_input_reason as dr ON c.fk_input_reason = dr.rowid';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_incoterms as i ON c.fk_incoterms = i.rowid';

		if ($id) {
			$sql .= " WHERE c.rowid=" . ((int) $id);
		} else {
			$sql .= " WHERE c.entity IN (" . getEntity('commande') . ")"; // Dont't use entity if you use rowid
		}

		if ($ref) {
			$sql .= " AND c.ref='" . $this->db->escape($ref) . "'";
		}
		if ($ref_ext) {
			$sql .= " AND c.ref_ext='" . $this->db->escape($ref_ext) . "'";
		}
		if ($notused) {
			$sql .= " AND c.ref_int='" . $this->db->escape($notused) . "'";
		}

		dol_syslog(get_class($this) . "::fetch", LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			$obj = $this->db->fetch_object($result);
			if ($obj) {
				$this->id = $obj->rowid;
				$this->entity = $obj->entity;

				$this->ref = $obj->ref;
				$this->ref_client = $obj->ref_client;
				$this->ref_customer = $obj->ref_client;
				$this->ref_ext				= $obj->ref_ext;
				$this->ref_int				= $obj->ref_int;

				$this->socid = $obj->fk_soc;
				$this->thirdparty = null; // Clear if another value was already set by fetch_thirdparty

				$this->fk_project = $obj->fk_project;
				$this->project = null; // Clear if another value was already set by fetch_projet

				$this->statut = $obj->fk_statut;
				$this->status = $obj->fk_statut;

				$this->user_author_id = $obj->fk_user_author;
				$this->user_creation_id = $obj->fk_user_author;
				$this->user_validation_id = $obj->fk_user_valid;
				$this->user_valid = $obj->fk_user_valid;			// deprecated
				$this->user_modification_id = $obj->fk_user_modif;
				$this->user_modification    = $obj->fk_user_modif;
				$this->total_ht				= $obj->total_ht;
				$this->total_tva			= $obj->total_tva;
				$this->total_localtax1		= $obj->total_localtax1;
				$this->total_localtax2		= $obj->total_localtax2;
				$this->total_ttc			= $obj->total_ttc;
				$this->date = $this->db->jdate($obj->date_commande);
				$this->date_commande		= $this->db->jdate($obj->date_commande);
				$this->date_creation		= $this->db->jdate($obj->date_creation);
				$this->date_validation = $this->db->jdate($obj->date_valid);
				$this->date_modification = $this->db->jdate($obj->tms);
				$this->remise				= $obj->remise;
				$this->remise_percent		= $obj->remise_percent;
				$this->remise_absolue		= $obj->remise_absolue;
				$this->source				= $obj->source;
				$this->billed				= $obj->billed;
				$this->note = $obj->note_private; // deprecated
				$this->note_private = $obj->note_private;
				$this->note_public = $obj->note_public;
				$this->model_pdf = $obj->model_pdf;
				$this->modelpdf = $obj->model_pdf; // deprecated
				$this->last_main_doc = $obj->last_main_doc;
				$this->mode_reglement_id	= $obj->fk_mode_reglement;
				$this->mode_reglement_code	= $obj->mode_reglement_code;
				$this->mode_reglement		= $obj->mode_reglement_libelle;
				$this->cond_reglement_id	= $obj->fk_cond_reglement;
				$this->cond_reglement_code	= $obj->cond_reglement_code;
				$this->cond_reglement		= $obj->cond_reglement_libelle;
				$this->cond_reglement_doc = $obj->cond_reglement_libelle_doc;
				$this->deposit_percent = $obj->deposit_percent;
				$this->fk_account = $obj->fk_account;
				$this->availability_id = $obj->fk_availability;
				$this->availability_code	= $obj->availability_code;
				$this->availability	    	= $obj->availability_label;
				$this->demand_reason_id		= $obj->fk_input_reason;
				$this->demand_reason_code = $obj->demand_reason_code;
				$this->date_livraison = $this->db->jdate($obj->delivery_date); // deprecated
				$this->delivery_date = $this->db->jdate($obj->delivery_date);
				$this->shipping_method_id   = ($obj->fk_shipping_method > 0) ? $obj->fk_shipping_method : null;
				$this->warehouse_id         = ($obj->fk_warehouse > 0) ? $obj->fk_warehouse : null;
				$this->fk_delivery_address = $obj->fk_delivery_address;
				$this->module_source        = $obj->module_source;
				$this->pos_source           = $obj->pos_source;

				//Incoterms
				$this->fk_incoterms         = $obj->fk_incoterms;
				$this->location_incoterms   = $obj->location_incoterms;
				$this->label_incoterms    = $obj->label_incoterms;

				// Multicurrency
				$this->fk_multicurrency 		= $obj->fk_multicurrency;
				$this->multicurrency_code = $obj->multicurrency_code;
				$this->multicurrency_tx 		= $obj->multicurrency_tx;
				$this->multicurrency_total_ht = $obj->multicurrency_total_ht;
				$this->multicurrency_total_tva 	= $obj->multicurrency_total_tva;
				$this->multicurrency_total_ttc 	= $obj->multicurrency_total_ttc;

				$this->extraparams = (array) json_decode($obj->extraparams, true);

				$this->lines = array();

				if (1) {
					$this->brouillon = 1;
				}

				// Retrieve all extrafield
				// fetch optionals attributes and labels
				$this->fetch_optionals();

				$this->db->free($result);

				// Lines
				$result = $this->fetch_lines();
				if ($result < 0) {
					return -3;
				}
				return 1;
			} else {
				$this->error = 'Order with id ' . $id . ' not found sql=' . $sql;
				return 0;
			}
		} else {
			$this->error = $this->db->error();
			return -1;
		}
	}





	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Load array lines
	 *
	 *	@param		int		$only_product			Return only physical products, not services
	 *	@param		int		$loadalsotranslation	Return translation for products
	 *	@return		int								<0 if KO, >0 if OK
	 */
	public function fetch_lines($only_product = 0, $loadalsotranslation = 0)
	{
		// phpcs:enable
		global $langs, $conf, $user;

		$this->lines = array();

		$sql = 'SELECT l.rowid, l.fk_product, l.fk_parent_line, l.product_type, l.fk_commande, l.label as custom_label, l.description, l.price, l.qty, l.vat_src_code, l.tva_tx, l.ref_ext,';
		$sql .= ' l.localtax1_tx, l.localtax2_tx, l.localtax1_type, l.localtax2_type, l.fk_remise_except, l.remise_percent, l.subprice, l.fk_product_fournisseur_price as fk_fournprice, l.buy_price_ht as pa_ht, l.rang, l.info_bits, l.special_code,';
		$sql .= ' l.total_ht, l.total_ttc, l.total_tva, l.total_localtax1, l.total_localtax2, l.date_start, l.date_end,';
		$sql .= ' l.fk_unit,';
		$sql .= ' l.fk_multicurrency, l.multicurrency_code, l.multicurrency_subprice, l.multicurrency_total_ht, l.multicurrency_total_tva, l.multicurrency_total_ttc,';
		$sql .= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label, p.tosell as product_tosell, p.tobuy as product_tobuy, p.tobatch as product_tobatch, p.barcode as product_barcode,';
		$sql .= ' p.weight, p.weight_units, p.volume, p.volume_units';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'warehouserequest as l';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON (p.rowid = l.fk_product)';
		$sql .= ' WHERE l.fk_commande = ' . ((int) $this->id);
		if ($only_product) {
			$sql .= ' AND p.fk_product_type = 0';
		}
		$sql .= ' AND l.fk_user = ' . $user->id;
		$sql .= ' ORDER BY l.rang, l.rowid';

		dol_syslog(get_class($this) . "::fetch_lines", LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);

			$i = 0;
			while ($i < $num) {
				$objp = $this->db->fetch_object($result);

				$line = new WarehouserequestLine($this->db);

				$line->rowid            = $objp->rowid;
				$line->id               = $objp->rowid;
				$line->fk_commande      = $objp->fk_commande;
				$line->commande_id      = $objp->fk_commande;
				$line->label            = $objp->custom_label;
				$line->desc             = $objp->description;
				$line->description      = $objp->description; // Description line
				$line->product_type     = $objp->product_type;
				$line->qty              = $objp->qty;
				$line->ref_ext          = $objp->ref_ext;

				$line->vat_src_code     = $objp->vat_src_code;
				$line->tva_tx           = $objp->tva_tx;
				$line->localtax1_tx     = $objp->localtax1_tx;
				$line->localtax2_tx     = $objp->localtax2_tx;
				$line->localtax1_type	= $objp->localtax1_type;
				$line->localtax2_type	= $objp->localtax2_type;
				$line->total_ht         = $objp->total_ht;
				$line->total_ttc        = $objp->total_ttc;
				$line->total_tva        = $objp->total_tva;
				$line->total_localtax1  = $objp->total_localtax1;
				$line->total_localtax2  = $objp->total_localtax2;
				$line->subprice         = $objp->subprice;
				$line->fk_remise_except = $objp->fk_remise_except;
				$line->remise_percent   = $objp->remise_percent;
				$line->price            = $objp->price;
				$line->fk_product       = $objp->fk_product;
				$line->fk_fournprice = $objp->fk_fournprice;
				$marginInfos = getMarginInfos($objp->subprice, $objp->remise_percent, $objp->tva_tx, $objp->localtax1_tx, $objp->localtax2_tx, $line->fk_fournprice, $objp->pa_ht);
				$line->pa_ht = $marginInfos[0];
				$line->marge_tx			= $marginInfos[1];
				$line->marque_tx		= $marginInfos[2];
				$line->rang             = $objp->rang;
				$line->info_bits        = $objp->info_bits;
				$line->special_code = $objp->special_code;
				$line->fk_parent_line = $objp->fk_parent_line;

				$line->ref = $objp->product_ref;
				$line->libelle = $objp->product_label;

				$line->product_ref = $objp->product_ref;
				$line->product_label = $objp->product_label;
				$line->product_tosell   = $objp->product_tosell;
				$line->product_tobuy    = $objp->product_tobuy;
				$line->product_desc     = $objp->product_desc;
				$line->product_tobatch  = $objp->product_tobatch;
				$line->product_barcode  = $objp->product_barcode;

				$line->fk_product_type  = $objp->fk_product_type; // Produit ou service
				$line->fk_unit          = $objp->fk_unit;

				$line->weight           = $objp->weight;
				$line->weight_units     = $objp->weight_units;
				$line->volume           = $objp->volume;
				$line->volume_units     = $objp->volume_units;

				$line->date_start       = $this->db->jdate($objp->date_start);
				$line->date_end         = $this->db->jdate($objp->date_end);

				// Multicurrency
				$line->fk_multicurrency = $objp->fk_multicurrency;
				$line->multicurrency_code = $objp->multicurrency_code;
				$line->multicurrency_subprice 	= $objp->multicurrency_subprice;
				$line->multicurrency_total_ht 	= $objp->multicurrency_total_ht;
				$line->multicurrency_total_tva 	= $objp->multicurrency_total_tva;
				$line->multicurrency_total_ttc 	= $objp->multicurrency_total_ttc;

				$line->fetch_optionals();

				// multilangs
				if (!empty($conf->global->MAIN_MULTILANGS) && !empty($objp->fk_product) && !empty($loadalsotranslation)) {
					$tmpproduct = new Product($this->db);
					$tmpproduct->fetch($objp->fk_product);
					$tmpproduct->getMultiLangs();

					$line->multilangs = $tmpproduct->multilangs;
				}

				$this->lines[$i] = $line;

				$i++;
			}

			$this->db->free($result);

			return 1;
		} else {
			$this->error = $this->db->error();
			return -3;
		}
	}




	/**
	 *	Add an order line into database (linked to product/service or not)
	 *
	 *	@param      string			$desc            	Description of line
	 *	@param      float			$pu_ht    	        Unit price (without tax)
	 *	@param      float			$qty             	Quantite
	 * 	@param    	float			$txtva           	Force Vat rate, -1 for auto (Can contain the vat_src_code too with syntax '9.9 (CODE)')
	 * 	@param		float			$txlocaltax1		Local tax 1 rate (deprecated, use instead txtva with code inside)
	 * 	@param		float			$txlocaltax2		Local tax 2 rate (deprecated, use instead txtva with code inside)
	 *	@param      int				$fk_product      	Id of product
	 *	@param      float			$remise_percent  	Percentage discount of the line
	 *	@param      int				$info_bits			Bits of type of lines
	 *	@param      int				$fk_remise_except	Id remise
	 *	@param      string			$price_base_type	HT or TTC
	 *	@param      float			$pu_ttc    		    Prix unitaire TTC
	 *	@param      int				$date_start       	Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 *	@param      int				$date_end         	End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 *	@param      int				$type				Type of line (0=product, 1=service). Not used if fk_product is defined, the type of product is used.
	 *	@param      int				$rang             	Position of line
	 *	@param		int				$special_code		Special code (also used by externals modules!)
	 *	@param		int				$fk_parent_line		Parent line
	 *  @param		int				$fk_fournprice		Id supplier price
	 *  @param		int				$pa_ht				Buying price (without tax)
	 *  @param		string			$label				Label
	 *  @param		array			$array_options		extrafields array. Example array('options_codeforfield1'=>'valueforfield1', 'options_codeforfield2'=>'valueforfield2', ...)
	 * 	@param 		string			$fk_unit 			Code of the unit to use. Null to use the default one
	 * 	@param		string		    $origin				Depend on global conf MAIN_CREATEFROM_KEEP_LINE_ORIGIN_INFORMATION can be 'orderdet', 'propaldet'..., else 'order','propal,'....
	 *  @param		int			    $origin_id			Depend on global conf MAIN_CREATEFROM_KEEP_LINE_ORIGIN_INFORMATION can be Id of origin object (aka line id), else object id
	 * 	@param		double			$pu_ht_devise		Unit price in currency
	 * 	@param		string			$ref_ext		    line external reference
	 *  @param		int				$noupdateafterinsertline	No update after insert of line
	 *	@return     int             					>0 if OK, <0 if KO
	 *
	 *	@see        add_product()
	 *
	 *	Les parametres sont deja cense etre juste et avec valeurs finales a l'appel
	 *	de cette methode. Aussi, pour le taux tva, il doit deja avoir ete defini
	 *	par l'appelant par la methode get_default_tva(societe_vendeuse,societe_acheteuse,produit)
	 *	et le desc doit deja avoir la bonne valeur (a l'appelant de gerer le multilangue)
	 */
	public function addline($desc, $pu_ht, $qty, $txtva, $txlocaltax1 = 0, $txlocaltax2 = 0, $fk_product = 0, $remise_percent = 0, $info_bits = 0, $fk_remise_except = 0, $price_base_type = 'HT', $pu_ttc = 0, $date_start = '', $date_end = '', $type = 0, $rang = -1, $special_code = 0, $fk_parent_line = 0, $fk_fournprice = null, $pa_ht = 0, $label = '', $array_options = 0, $fk_unit = null, $origin = '', $origin_id = 0, $pu_ht_devise = 0, $ref_ext = '', $noupdateafterinsertline = 0)
	{
		global $mysoc, $conf, $langs, $user;


		$logtext = "::addline commandeid=$this->id, desc=$desc, pu_ht=$pu_ht, qty=$qty, txtva=$txtva, fk_product=$fk_product, remise_percent=$remise_percent";
		$logtext .= ", info_bits=$info_bits, fk_remise_except=$fk_remise_except, price_base_type=$price_base_type, pu_ttc=$pu_ttc, date_start=$date_start";
		$logtext .= ", date_end=$date_end, type=$type special_code=$special_code, fk_unit=$fk_unit, origin=$origin, origin_id=$origin_id, pu_ht_devise=$pu_ht_devise, ref_ext=$ref_ext";
		dol_syslog(get_class($this) . $logtext, LOG_DEBUG);

		if (1) {
			include_once DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php';

			// Clean parameters

			if (empty($remise_percent)) {
				$remise_percent = 0;
			}
			if (empty($qty)) {
				$qty = 0;
			}
			if (empty($info_bits)) {
				$info_bits = 0;
			}
			if (empty($rang)) {
				$rang = 0;
			}
			if (empty($txtva)) {
				$txtva = 0;
			}
			if (empty($txlocaltax1)) {
				$txlocaltax1 = 0;
			}
			if (empty($txlocaltax2)) {
				$txlocaltax2 = 0;
			}
			if (empty($fk_parent_line) || $fk_parent_line < 0) {
				$fk_parent_line = 0;
			}
			if (empty($this->fk_multicurrency)) {
				$this->fk_multicurrency = 0;
			}
			if (empty($ref_ext)) {
				$ref_ext = '';
			}

			$remise_percent = price2num($remise_percent);
			$qty = price2num($qty);
			$pu_ht = price2num($pu_ht);
			$pu_ht_devise = price2num($pu_ht_devise);
			$pu_ttc = price2num($pu_ttc);
			$pa_ht = price2num($pa_ht);
			if (!preg_match('/\((.*)\)/', $txtva)) {
				$txtva = price2num($txtva); // $txtva can have format '5,1' or '5.1' or '5.1(XXX)', we must clean only if '5,1'
			}
			$txlocaltax1 = price2num($txlocaltax1);
			$txlocaltax2 = price2num($txlocaltax2);
			if ($price_base_type == 'HT') {
				$pu = $pu_ht;
			} else {
				$pu = $pu_ttc;
			}
			$label = trim($label);
			$desc = trim($desc);

			// Check parameters
			if ($type < 0) {
				return -1;
			}

			if ($date_start && $date_end && $date_start > $date_end) {
				$langs->load("errors");
				$this->error = $langs->trans('ErrorStartDateGreaterEnd');
				return -1;
			}

			$this->db->begin();

			$product_type = $type;
			if (!empty($fk_product) && $fk_product > 0) {
				$product = new Product($this->db);
				$result = $product->fetch($fk_product);
				$product_type = $product->type;

				if (!empty($conf->global->STOCK_MUST_BE_ENOUGH_FOR_ORDER) && $product_type == 0 && $product->stock_reel < $qty) {
					$langs->load("errors");
					$this->error = $langs->trans('ErrorStockIsNotEnoughToAddProductOnOrder', $product->ref);
					$this->errors[] = $this->error;
					dol_syslog(get_class($this) . "::addline error=Product " . $product->ref . ": " . $this->error, LOG_ERR);
					$this->db->rollback();
					return self::STOCK_NOT_ENOUGH_FOR_ORDER;
				}
			}
			// Calcul du total TTC et de la TVA pour la ligne a partir de
			// qty, pu, remise_percent et txtva
			// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
			// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.

			$localtaxes_type = getLocalTaxesFromRate($txtva, 0, $this->thirdparty, $mysoc);

			// Clean vat code
			$reg = array();
			$vat_src_code = '';
			if (preg_match('/\((.*)\)/', $txtva, $reg)) {
				$vat_src_code = $reg[1];
				$txtva = preg_replace('/\s*\(.*\)/', '', $txtva); // Remove code into vatrate.
			}

			$tabprice = calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits, $product_type, $mysoc, $localtaxes_type, 100, $this->multicurrency_tx, $pu_ht_devise);

			/*var_dump($txlocaltax1);
			 var_dump($txlocaltax2);
			 var_dump($localtaxes_type);
			 var_dump($tabprice);
			 var_dump($tabprice[9]);
			 var_dump($tabprice[10]);
			 exit;*/

			$total_ht  = $tabprice[0];
			$total_tva = $tabprice[1];
			$total_ttc = $tabprice[2];
			$total_localtax1 = $tabprice[9];
			$total_localtax2 = $tabprice[10];
			$pu_ht = $tabprice[3];

			// MultiCurrency
			$multicurrency_total_ht  = $tabprice[16];
			$multicurrency_total_tva = $tabprice[17];
			$multicurrency_total_ttc = $tabprice[18];
			$pu_ht_devise = $tabprice[19];

			// Rang to use
			$ranktouse = $rang;
			if ($ranktouse == -1) {
				$rangmax = $this->line_max($fk_parent_line);
				$ranktouse = $rangmax + 1;
			}

			// TODO A virer
			// Anciens indicateurs: $price, $remise (a ne plus utiliser)
			$price = $pu;
			$remise = 0;
			if ($remise_percent > 0) {
				$remise = round(($pu * $remise_percent / 100), 2);
				$price = $pu - $remise;
			}

			// Insert line
			$this->line = new WarehouserequestLine($this->db);
			$existingResult = $this->line->fetch_with_product_id($fk_product, $this->id);
			if ($existingResult) {
				$newQty = $existingResult->qty + $qty;
				$this->updateline($existingResult->rowid, $desc, $pu, $newQty, $remise_percent, $txtva, $txlocaltax1 = 0.0, $txlocaltax2 = 0.0, $price_base_type = 'HT', $info_bits = 0, $date_start = '', $date_end = '', $type = 0, $fk_parent_line = 0, $skip_update_total = 0, $fk_fournprice = null, $pa_ht, $label, $special_code, $array_options = 0, $fk_unit, $pu_ht_devise, $notrigger = 0, $ref_ext, $ranktouse);
			} else {
				$this->line->context = $this->context;

				$this->line->fk_commande = $this->id;
				$this->line->label = $label;
				$this->line->desc = $desc;
				$this->line->qty = $qty;
				$this->line->ref_ext = $ref_ext;

				$this->line->vat_src_code = $vat_src_code;
				$this->line->tva_tx = $txtva;
				$this->line->localtax1_tx = ($total_localtax1 ? $localtaxes_type[1] : 0);
				$this->line->localtax2_tx = ($total_localtax2 ? $localtaxes_type[3] : 0);
				$this->line->localtax1_type = empty($localtaxes_type[0]) ? '' : $localtaxes_type[0];
				$this->line->localtax2_type = empty($localtaxes_type[2]) ? '' : $localtaxes_type[2];
				$this->line->fk_product = $fk_product;
				$this->line->product_type = $product_type;
				$this->line->fk_remise_except = $fk_remise_except;
				$this->line->remise_percent = $remise_percent;
				$this->line->subprice = $pu_ht;
				$this->line->rang = $ranktouse;
				$this->line->info_bits = $info_bits;
				$this->line->total_ht = $total_ht;
				$this->line->total_tva = $total_tva;
				$this->line->total_localtax1 = $total_localtax1;
				$this->line->total_localtax2 = $total_localtax2;
				$this->line->total_ttc = $total_ttc;
				$this->line->special_code = $special_code;
				$this->line->origin = $origin;
				$this->line->origin_id = $origin_id;
				$this->line->fk_parent_line = $fk_parent_line;
				$this->line->fk_unit = $fk_unit;

				$this->line->date_start = $date_start;
				$this->line->date_end = $date_end;

				$this->line->fk_fournprice = $fk_fournprice;
				$this->line->pa_ht = $pa_ht;

				// Multicurrency
				$this->line->fk_multicurrency = $this->fk_multicurrency;
				$this->line->multicurrency_code = $this->multicurrency_code;
				$this->line->multicurrency_subprice		= $pu_ht_devise;
				$this->line->multicurrency_total_ht 	= $multicurrency_total_ht;
				$this->line->multicurrency_total_tva 	= $multicurrency_total_tva;
				$this->line->multicurrency_total_ttc 	= $multicurrency_total_ttc;

				// TODO Ne plus utiliser
				$this->line->price = $price;

				if (is_array($array_options) && count($array_options) > 0) {
					$this->line->array_options = $array_options;
				}

				$result = $this->line->insert($user);
			}
			if ($result > 0) {
				// Reorder if child line
				if (!empty($fk_parent_line)) {
					$this->line_order(true, 'DESC');
				} elseif ($ranktouse > 0 && $ranktouse <= count($this->lines)) { // Update all rank of all other lines
					$linecount = count($this->lines);
					for ($ii = $ranktouse; $ii <= $linecount; $ii++) {
						$this->updateRangOfLine($this->lines[$ii - 1]->id, $ii + 1);
					}
				}

				// Mise a jour informations denormalisees au niveau de la commande meme
				if (empty($noupdateafterinsertline)) {
					//	$result = $this->update_price(1, 'auto', 0, $mysoc); // This method is designed to add line from user input so total calculation must be done using 'auto' mode.
				}

				if ($result > 0) {
					$this->db->commit();
					return $this->line->id;
				} else {
					$this->db->rollback();
					return -1;
				}
			} else {
				$this->error = $this->line->error;
				dol_syslog(get_class($this) . "::addline error=" . $this->error, LOG_ERR);
				$this->db->rollback();
				return -2;
			}
		} else {
			dol_syslog(get_class($this) . "::addline status of order must be Draft to allow use of ->addline()", LOG_ERR);
			return -3;
		}
	}
}





/**
 *  Class to manage warehouse request lines lines
 */
class WarehouserequestLine extends OrderLine
{


	/**
	 *	Insert line into database
	 *
	 *	@param      User	$user        	User that modify
	 *	@param      int		$notrigger		1 = disable triggers
	 *	@return		int						<0 if KO, >0 if OK
	 */
	public function insert($user = null, $notrigger = 0)
	{
		global $langs, $conf, $user;

		$error = 0;

		$pa_ht_isemptystring = (empty($this->pa_ht) && $this->pa_ht == ''); // If true, we can use a default value. If this->pa_ht = '0', we must use '0'.

		dol_syslog(get_class($this) . "::insert rang=" . $this->rang);

		// Clean parameters
		if (empty($this->tva_tx)) {
			$this->tva_tx = 0;
		}
		if (empty($this->localtax1_tx)) {
			$this->localtax1_tx = 0;
		}
		if (empty($this->localtax2_tx)) {
			$this->localtax2_tx = 0;
		}
		if (empty($this->localtax1_type)) {
			$this->localtax1_type = 0;
		}
		if (empty($this->localtax2_type)) {
			$this->localtax2_type = 0;
		}
		if (empty($this->total_localtax1)) {
			$this->total_localtax1 = 0;
		}
		if (empty($this->total_localtax2)) {
			$this->total_localtax2 = 0;
		}
		if (empty($this->rang)) {
			$this->rang = 0;
		}
		if (empty($this->remise_percent)) {
			$this->remise_percent = 0;
		}
		if (empty($this->info_bits)) {
			$this->info_bits = 0;
		}
		if (empty($this->special_code)) {
			$this->special_code = 0;
		}
		if (empty($this->fk_parent_line)) {
			$this->fk_parent_line = 0;
		}
		if (empty($this->pa_ht)) {
			$this->pa_ht = 0;
		}
		if (empty($this->ref_ext)) {
			$this->ref_ext = '';
		}

		// if buy price not defined, define buyprice as configured in margin admin
		if ($this->pa_ht == 0 && $pa_ht_isemptystring) {
			$result = $this->defineBuyPrice($this->subprice, $this->remise_percent, $this->fk_product);
			if ($result < 0) {
				return $result;
			} else {
				$this->pa_ht = $result;
			}
		}

		// Check parameters
		if ($this->product_type < 0) {
			return -1;
		}

		$this->db->begin();

		// Insertion dans base de la ligne
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'warehouserequest';
		$sql .= ' (fk_commande, fk_parent_line, label, description, qty, fk_user , ref_ext,';
		$sql .= ' vat_src_code, tva_tx, localtax1_tx, localtax2_tx, localtax1_type, localtax2_type,';
		$sql .= ' fk_product, product_type, remise_percent, subprice, price, fk_remise_except,';
		$sql .= ' special_code, rang, fk_product_fournisseur_price, buy_price_ht,';
		$sql .= ' info_bits, total_ht, total_tva, total_localtax1, total_localtax2, total_ttc, date_start, date_end,';
		$sql .= ' fk_unit';
		$sql .= ', fk_multicurrency, multicurrency_code, multicurrency_subprice, multicurrency_total_ht, multicurrency_total_tva, multicurrency_total_ttc';
		$sql .= ')';
		$sql .= " VALUES (" . $this->fk_commande . ",";
		$sql .= " " . ($this->fk_parent_line > 0 ? "'" . $this->db->escape($this->fk_parent_line) . "'" : "null") . ",";
		$sql .= " " . (!empty($this->label) ? "'" . $this->db->escape($this->label) . "'" : "null") . ",";
		$sql .= " '" . $this->db->escape($this->desc) . "',";
		$sql .= " '" . price2num($this->qty) . "',";
		$sql .= " '" . $user->id . "',";
		$sql .= " '" . $this->db->escape($this->ref_ext) . "',";
		$sql .= " " . (empty($this->vat_src_code) ? "''" : "'" . $this->db->escape($this->vat_src_code) . "'") . ",";
		$sql .= " '" . price2num($this->tva_tx) . "',";
		$sql .= " '" . price2num($this->localtax1_tx) . "',";
		$sql .= " '" . price2num($this->localtax2_tx) . "',";
		$sql .= " '" . $this->db->escape($this->localtax1_type) . "',";
		$sql .= " '" . $this->db->escape($this->localtax2_type) . "',";
		$sql .= ' ' . ((!empty($this->fk_product) && $this->fk_product > 0) ? $this->fk_product : "null") . ',';
		$sql .= " '" . $this->db->escape($this->product_type) . "',";
		$sql .= " '" . price2num($this->remise_percent) . "',";
		$sql .= " " . (price2num($this->subprice) !== '' ? price2num($this->subprice) : "null") . ",";
		$sql .= " " . ($this->price != '' ? "'" . price2num($this->price) . "'" : "null") . ",";
		$sql .= ' ' . (!empty($this->fk_remise_except) ? $this->fk_remise_except : "null") . ',';
		$sql .= ' ' . ((int) $this->special_code) . ',';
		$sql .= ' ' . ((int) $this->rang) . ',';
		$sql .= ' ' . (!empty($this->fk_fournprice) ? $this->fk_fournprice : "null") . ',';
		$sql .= ' ' . price2num($this->pa_ht) . ',';
		$sql .= " " . ((int) $this->info_bits) . ",";
		$sql .= " " . price2num($this->total_ht, 'MT') . ",";
		$sql .= " " . price2num($this->total_tva, 'MT') . ",";
		$sql .= " " . price2num($this->total_localtax1, 'MT') . ",";
		$sql .= " " . price2num($this->total_localtax2, 'MT') . ",";
		$sql .= " " . price2num($this->total_ttc, 'MT') . ",";
		$sql .= " " . (!empty($this->date_start) ? "'" . $this->db->idate($this->date_start) . "'" : "null") . ',';
		$sql .= " " . (!empty($this->date_end) ? "'" . $this->db->idate($this->date_end) . "'" : "null") . ',';
		$sql .= ' ' . (!$this->fk_unit ? 'NULL' : ((int) $this->fk_unit));
		$sql .= ", " . (!empty($this->fk_multicurrency) ? ((int) $this->fk_multicurrency) : 'NULL');
		$sql .= ", '" . $this->db->escape($this->multicurrency_code) . "'";
		$sql .= ", " . price2num($this->multicurrency_subprice, 'CU');
		$sql .= ", " . price2num($this->multicurrency_total_ht, 'CT');
		$sql .= ", " . price2num($this->multicurrency_total_tva, 'CT');
		$sql .= ", " . price2num($this->multicurrency_total_ttc, 'CT');
		$sql .= ')';

		dol_syslog(get_class($this) . "::insert", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . 'warehouserequest');
			$this->rowid = $this->id;

			if (!$error) {
				$result = $this->insertExtraFields();
				if ($result < 0) {
					$error++;
				}
			}

			if (!$error && !$notrigger) {
				// Call trigger
				$result = $this->call_trigger('WAREHOUSEREQUEST_INSERT', $user);
				if ($result < 0) {
					$error++;
				}
				// End call triggers
			}

			if (!$error) {
				$this->db->commit();
				return 1;
			}

			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::insert " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->error = $this->db->error();
			$this->db->rollback();
			return -2;
		}
	}



	/**
	 *	Update the line object into db
	 *
	 *	@param      User	$user        	User that modify
	 *	@param      int		$notrigger		1 = disable triggers
	 *	@return		int		<0 si ko, >0 si ok
	 */
	public function update(User $user, $notrigger = 0)
	{
		global $conf, $langs;

		$error = 0;

		$pa_ht_isemptystring = (empty($this->pa_ht) && $this->pa_ht == ''); // If true, we can use a default value. If this->pa_ht = '0', we must use '0'.

		// Clean parameters
		if (empty($this->tva_tx)) {
			$this->tva_tx = 0;
		}
		if (empty($this->localtax1_tx)) {
			$this->localtax1_tx = 0;
		}
		if (empty($this->localtax2_tx)) {
			$this->localtax2_tx = 0;
		}
		if (empty($this->localtax1_type)) {
			$this->localtax1_type = 0;
		}
		if (empty($this->localtax2_type)) {
			$this->localtax2_type = 0;
		}
		if (empty($this->qty)) {
			$this->qty = 0;
		}
		if (empty($this->total_localtax1)) {
			$this->total_localtax1 = 0;
		}
		if (empty($this->total_localtax2)) {
			$this->total_localtax2 = 0;
		}
		if (empty($this->marque_tx)) {
			$this->marque_tx = 0;
		}
		if (empty($this->marge_tx)) {
			$this->marge_tx = 0;
		}
		if (empty($this->remise_percent)) {
			$this->remise_percent = 0;
		}
		if (empty($this->info_bits)) {
			$this->info_bits = 0;
		}
		if (empty($this->special_code)) {
			$this->special_code = 0;
		}
		if (empty($this->product_type)) {
			$this->product_type = 0;
		}
		if (empty($this->fk_parent_line)) {
			$this->fk_parent_line = 0;
		}
		if (empty($this->pa_ht)) {
			$this->pa_ht = 0;
		}
		if (empty($this->ref_ext)) {
			$this->ref_ext = '';
		}

		// if buy price not defined, define buyprice as configured in margin admin
		if ($this->pa_ht == 0 && $pa_ht_isemptystring) {
			$result = $this->defineBuyPrice($this->subprice, $this->remise_percent, $this->fk_product);
			if ($result < 0) {
				return $result;
			} else {
				$this->pa_ht = $result;
			}
		}

		$this->db->begin();

		// Mise a jour ligne en base
		$sql = "UPDATE " . MAIN_DB_PREFIX . "warehouserequest SET";
		$sql .= " description='" . $this->db->escape($this->desc) . "'";
		$sql .= " , label=" . (!empty($this->label) ? "'" . $this->db->escape($this->label) . "'" : "null");
		$sql .= " , vat_src_code=" . (!empty($this->vat_src_code) ? "'" . $this->db->escape($this->vat_src_code) . "'" : "''");
		$sql .= " , tva_tx=" . price2num($this->tva_tx);
		$sql .= " , localtax1_tx=" . price2num($this->localtax1_tx);
		$sql .= " , localtax2_tx=" . price2num($this->localtax2_tx);
		$sql .= " , localtax1_type='" . $this->db->escape($this->localtax1_type) . "'";
		$sql .= " , localtax2_type='" . $this->db->escape($this->localtax2_type) . "'";
		$sql .= " , qty=" . price2num($this->qty);
		$sql .= " , ref_ext='" . $this->db->escape($this->ref_ext) . "'";
		$sql .= " , subprice=" . price2num($this->subprice) . "";
		$sql .= " , remise_percent=" . price2num($this->remise_percent) . "";
		$sql .= " , price=" . price2num($this->price) . ""; // TODO A virer
		$sql .= " , remise=" . price2num($this->remise) . ""; // TODO A virer
		if (empty($this->skip_update_total)) {
			$sql .= " , total_ht=" . price2num($this->total_ht) . "";
			$sql .= " , total_tva=" . price2num($this->total_tva) . "";
			$sql .= " , total_ttc=" . price2num($this->total_ttc) . "";
			$sql .= " , total_localtax1=" . price2num($this->total_localtax1);
			$sql .= " , total_localtax2=" . price2num($this->total_localtax2);
		}
		$sql .= " , fk_product_fournisseur_price=" . (!empty($this->fk_fournprice) ? $this->fk_fournprice : "null");
		$sql .= " , buy_price_ht='" . price2num($this->pa_ht) . "'";
		$sql .= " , info_bits=" . ((int) $this->info_bits);
		$sql .= " , special_code=" . ((int) $this->special_code);
		$sql .= " , date_start=" . (!empty($this->date_start) ? "'" . $this->db->idate($this->date_start) . "'" : "null");
		$sql .= " , date_end=" . (!empty($this->date_end) ? "'" . $this->db->idate($this->date_end) . "'" : "null");
		$sql .= " , product_type=" . $this->product_type;
		$sql .= " , fk_parent_line=" . (!empty($this->fk_parent_line) ? $this->fk_parent_line : "null");
		if (!empty($this->rang)) {
			$sql .= ", rang=" . ((int) $this->rang);
		}
		$sql .= " , fk_unit=" . (!$this->fk_unit ? 'NULL' : $this->fk_unit);

		// Multicurrency
		$sql .= " , multicurrency_subprice=" . price2num($this->multicurrency_subprice) . "";
		$sql .= " , multicurrency_total_ht=" . price2num($this->multicurrency_total_ht) . "";
		$sql .= " , multicurrency_total_tva=" . price2num($this->multicurrency_total_tva) . "";
		$sql .= " , multicurrency_total_ttc=" . price2num($this->multicurrency_total_ttc) . "";

		$sql .= " WHERE rowid = " . ((int) $this->rowid);

		//print $sql;

		dol_syslog(get_class($this) . "::update", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if (!$error) {
				$this->id = $this->rowid;
				$result = $this->insertExtraFields();
				if ($result < 0) {
					$error++;
				}
			}

			if (!$error && !$notrigger) {
				// Call trigger
				$result = $this->call_trigger('WAREHOUSEREQUEST_MODIFY', $user);
				if ($result < 0) {
					$error++;
				}
				// End call triggers
			}

			if (!$error) {
				$this->db->commit();
				return 1;
			}

			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::update " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->error = $this->db->error();
			$this->db->rollback();
			return -2;
		}
	}


	/**
	 * 	Delete line in database
	 *
	 *	@param      User	$user        	User that modify
	 *  @param      int		$notrigger	    0=launch triggers after, 1=disable triggers
	 *	@return	 int  <0 si ko, >0 si ok
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $conf, $langs;

		$error = 0;

		if (empty($this->id) && !empty($this->rowid)) {		// For backward compatibility
			$this->id = $this->rowid;
		}

		$this->db->begin();

		$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . "warehouserequest WHERE rowid = " . ((int) $this->id);

		dol_syslog("WarehouserequestLine::delete", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			// Remove extrafields
			if (!$error) {
				$this->id = $this->rowid;
				$result = $this->deleteExtraFields();
				if ($result < 0) {
					$error++;
					dol_syslog(get_class($this) . "::delete error -4 " . $this->error, LOG_ERR);
				}
			}

			if (!$error && !$notrigger) {
				// Call trigger
				$result = $this->call_trigger('WAREHOUSEREQUEST_DELETE', $user);
				if ($result < 0) {
					$error++;
				}
				// End call triggers
			}

			if (!$error) {
				$this->db->commit();
				return 1;
			}

			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::delete " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}


	/**
	 *  Load line warehouserequest
	 *
	 *  @param  int		$rowid          Id line order
	 *  @return	int						<0 if KO, >0 if OK
	 */
	public function fetch($rowid)
	{
		$sql = 'SELECT cd.rowid, cd.fk_commande, cd.fk_parent_line, cd.fk_product, cd.product_type, cd.label as custom_label, cd.description, cd.price, cd.qty, cd.tva_tx, cd.localtax1_tx, cd.localtax2_tx,';
		$sql .= ' cd.remise, cd.remise_percent, cd.fk_remise_except, cd.subprice, cd.ref_ext,';
		$sql .= ' cd.info_bits, cd.total_ht, cd.total_tva, cd.total_localtax1, cd.total_localtax2, cd.total_ttc, cd.fk_product_fournisseur_price as fk_fournprice, cd.buy_price_ht as pa_ht, cd.rang, cd.special_code,';
		$sql .= ' cd.fk_unit,';
		$sql .= ' cd.fk_multicurrency, cd.multicurrency_code, cd.multicurrency_subprice, cd.multicurrency_total_ht, cd.multicurrency_total_tva, cd.multicurrency_total_ttc,';
		$sql .= ' p.ref as product_ref, p.label as product_label, p.description as product_desc, p.tobatch as product_tobatch,';
		$sql .= ' cd.date_start, cd.date_end, cd.vat_src_code';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'warehouserequest as cd';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON cd.fk_product = p.rowid';
		$sql .= ' WHERE cd.rowid = ' . ((int) $rowid);
		$result = $this->db->query($sql);
		if ($result) {
			$objp = $this->db->fetch_object($result);
			$this->rowid            = $objp->rowid;
			$this->id = $objp->rowid;
			$this->fk_commande      = $objp->fk_commande;
			$this->fk_parent_line   = $objp->fk_parent_line;
			$this->label            = $objp->custom_label;
			$this->desc             = $objp->description;
			$this->qty              = $objp->qty;
			$this->price            = $objp->price;
			$this->subprice         = $objp->subprice;
			$this->ref_ext          = $objp->ref_ext;
			$this->vat_src_code     = $objp->vat_src_code;
			$this->tva_tx           = $objp->tva_tx;
			$this->localtax1_tx		= $objp->localtax1_tx;
			$this->localtax2_tx		= $objp->localtax2_tx;
			$this->remise           = $objp->remise;
			$this->remise_percent   = $objp->remise_percent;
			$this->fk_remise_except = $objp->fk_remise_except;
			$this->fk_product       = $objp->fk_product;
			$this->product_type     = $objp->product_type;
			$this->info_bits        = $objp->info_bits;
			$this->special_code = $objp->special_code;
			$this->total_ht         = $objp->total_ht;
			$this->total_tva        = $objp->total_tva;
			$this->total_localtax1  = $objp->total_localtax1;
			$this->total_localtax2  = $objp->total_localtax2;
			$this->total_ttc        = $objp->total_ttc;
			$this->fk_fournprice = $objp->fk_fournprice;
			$marginInfos			= getMarginInfos($objp->subprice, $objp->remise_percent, $objp->tva_tx, $objp->localtax1_tx, $objp->localtax2_tx, $this->fk_fournprice, $objp->pa_ht);
			$this->pa_ht			= $marginInfos[0];
			$this->marge_tx			= $marginInfos[1];
			$this->marque_tx		= $marginInfos[2];
			$this->special_code = $objp->special_code;
			$this->rang = $objp->rang;

			$this->ref = $objp->product_ref; // deprecated

			$this->product_ref      = $objp->product_ref;
			$this->product_label    = $objp->product_label;
			$this->product_desc     = $objp->product_desc;
			$this->product_tobatch  = $objp->product_tobatch;
			$this->fk_unit          = $objp->fk_unit;

			$this->date_start       = $this->db->jdate($objp->date_start);
			$this->date_end         = $this->db->jdate($objp->date_end);

			$this->fk_multicurrency = $objp->fk_multicurrency;
			$this->multicurrency_code = $objp->multicurrency_code;
			$this->multicurrency_subprice	= $objp->multicurrency_subprice;
			$this->multicurrency_total_ht	= $objp->multicurrency_total_ht;
			$this->multicurrency_total_tva	= $objp->multicurrency_total_tva;
			$this->multicurrency_total_ttc	= $objp->multicurrency_total_ttc;

			$this->db->free($result);

			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	public function fetch_with_product_id($pro_id, $fk_commande_id)
	{


		$sql = 'SELECT cd.rowid, cd.fk_commande, cd.fk_parent_line, cd.fk_product, cd.product_type, cd.label as custom_label, cd.description, cd.price, cd.qty, cd.tva_tx, cd.localtax1_tx, cd.localtax2_tx,';
		$sql .= ' cd.remise, cd.remise_percent, cd.fk_remise_except, cd.subprice, cd.ref_ext,';
		$sql .= ' cd.info_bits, cd.total_ht, cd.total_tva, cd.total_localtax1, cd.total_localtax2, cd.total_ttc, cd.fk_product_fournisseur_price as fk_fournprice, cd.buy_price_ht as pa_ht, cd.rang, cd.special_code,';
		$sql .= ' cd.fk_unit,';
		$sql .= ' cd.fk_multicurrency, cd.multicurrency_code, cd.multicurrency_subprice, cd.multicurrency_total_ht, cd.multicurrency_total_tva, cd.multicurrency_total_ttc,';
		$sql .= ' p.ref as product_ref, p.label as product_label, p.description as product_desc, p.tobatch as product_tobatch,';
		$sql .= ' cd.date_start, cd.date_end, cd.vat_src_code';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'warehouserequest as cd';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON cd.fk_product = p.rowid';
		$sql .= ' WHERE cd.fk_product = ' . ((int) $pro_id);
		$sql .= ' AND cd.fk_commande = ' . ((int) $fk_commande_id);
		$result = $this->db->query($sql);
		if ($result) {
			$result = $this->db->fetch_object($result);
			return $result;
		} else {
			return 0;
		}
	}
}
