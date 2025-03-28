<?php
/* Copyright (C) 2003-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2014	   Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2014	   Florian Henry		<florian.henry@open-concept.pro>
 * Copyright (C) 2023	   Gauthier VERDOL		<gauthier.verdol@atm-consulting.fr>
 * Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
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

/**
 *	\file       htdocs/product/stock/stats/commande_fournisseur.php
 *	\ingroup    product service facture
 *	\brief      Page of supplier order statistics for a batch
 */

// Load Dolibarr environment
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array('companies', 'bills', 'products', 'orders'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$batch = GETPOST('batch', 'alpha');
$objectid = GETPOSTINT('productid');

// Security check
$fieldvalue = (!empty($id) ? $id : (!empty($ref) ? $ref : ''));
$fieldtype = (!empty($ref) ? 'ref' : 'rowid');
$socid = 0;
if (!empty($user->socid)) {
	$socid = $user->socid;
}

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
$hookmanager->initHooks(array('batchproductstatssupplierorder'));

$showmessage = GETPOST('showmessage');

// Load variable for pagination
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (empty($sortorder)) {
	$sortorder = "DESC";
}
if (empty($sortfield)) {
	$sortfield = "cf.date_commande";
}

$search_month = GETPOSTINT('search_month');
$search_year = GETPOSTINT('search_year');

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_month = '';
	$search_year = '';
}

if (!$user->hasRight('produit', 'lire')) {
	accessforbidden();
}


/*
 * View
 */

$commandefournisseurstatic = new CommandeFournisseur($db);
$societestatic = new Societe($db);

$form = new Form($db);
$formother = new FormOther($db);

if ($id > 0 || !empty($ref)) {
	$object = new Productlot($db);
	if ($ref) {
		$tmp = explode('_', $ref);
		$objectid = $tmp[0];
		$batch = $tmp[1];
	}
	$result = $object->fetch($id, $objectid, $batch);

	$parameters = array('id' => $id);
	$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}

	$helpurl = '';
	$shortlabel = dol_trunc($object->batch, 16);
	$title = $langs->trans('Batch')." ".$shortlabel." - ".$langs->trans('Referers');
	$helpurl = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';

	llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'mod-product page-stock-stats_commande_fournisseur');

	if ($result > 0) {
		$head = productlot_prepare_head($object);
		$titre = $langs->trans("CardProduct".$object->type);
		$picto = 'lot';
		$morehtmlref = '';
		print dol_get_fiche_head($head, 'referers', $langs->trans("Batch"), -1, $object->picto);

		$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		$linkback = '<a href="'.DOL_URL_ROOT.'/product/stock/productlot_list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

		$shownav = 1;
		if ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) {
			$shownav = 0;
		}

		dol_banner_tab($object, 'id', $linkback, $shownav, 'rowid', 'batch', $morehtmlref);

		print '<div class="fichecenter">';

		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield" width="100%">';


		// Product
		print '<tr><td class="titlefield">'.$langs->trans("Product").'</td><td>';
		$producttmp = new Product($db);
		$producttmp->fetch($object->fk_product);
		print $producttmp->getNomUrl(1, 'stock')." - ".$producttmp->label;
		print '</td></tr>';
		print "</table>";

		echo '<br>';

		//      // Sell by
		//      if (empty($conf->global->PRODUCT_DISABLE_SELLBY)) {
		//          print '<tr><td>';
		//          print $form->editfieldkey($langs->trans('SellByDate'), 'sellby', $object->sellby, $object, $user->rights->stock->creer, 'datepicker');
		//          print '</td><td>';
		//          print $form->editfieldval($langs->trans('SellByDate'), 'sellby', $object->sellby, $object, $user->rights->stock->creer, 'datepicker');
		//          print '</td>';
		//          print '</tr>';
		//      }
		//
		//      // Eat by
		//      if (empty($conf->global->PRODUCT_DISABLE_EATBY)) {
		//          print '<tr><td>';
		//          print $form->editfieldkey($langs->trans('EatByDate'), 'eatby', $object->eatby, $object, $user->rights->stock->creer, 'datepicker');
		//          print '</td><td>';
		//          print $form->editfieldval($langs->trans('EatByDate'), 'eatby', $object->eatby, $object, $user->rights->stock->creer, 'datepicker');
		//          print '</td>';
		//          print '</tr>';
		//      }
		//
		//      if (getDolGlobalString('PRODUCT_LOT_ENABLE_TRACEABILITY')) {
		//          print '<tr><td>'.$form->editfieldkey($langs->trans('ManufacturingDate'), 'manufacturing_date', $object->manufacturing_date, $object, $user->rights->stock->creer).'</td>';
		//          print '<td>'.$form->editfieldval($langs->trans('ManufacturingDate'), 'manufacturing_date', $object->manufacturing_date, $object, $user->rights->stock->creer, 'datepicker').'</td>';
		//          print '</tr>';
		//          // print '<tr><td>'.$form->editfieldkey($langs->trans('FirstUseDate'), 'commissionning_date', $object->commissionning_date, $object, $user->rights->stock->creer).'</td>';
		//          // print '<td>'.$form->editfieldval($langs->trans('FirstUseDate'), 'commissionning_date', $object->commissionning_date, $object, $user->rights->stock->creer, 'datepicker').'</td>';
		//          // print '</tr>';
		//          print '<tr><td>'.$form->editfieldkey($langs->trans('DestructionDate'), 'scrapping_date', $object->scrapping_date, $object, $user->rights->stock->creer).'</td>';
		//          print '<td>'.$form->editfieldval($langs->trans('DestructionDate'), 'scrapping_date', $object->scrapping_date, $object, $user->rights->stock->creer, 'datepicker').'</td>';
		//          print '</tr>';
		//      }
		//
		//      // Quality control
		//      if (getDolGlobalString('PRODUCT_LOT_ENABLE_QUALITY_CONTROL')) {
		//          print '<tr><td>'.$form->editfieldkey($langs->trans('EndOfLife'), 'eol_date', $object->eol_date, $object, $user->rights->stock->creer).'</td>';
		//          print '<td>'.$form->editfieldval($langs->trans('EndOfLife'), 'eol_date', $object->eol_date, $object, $user->rights->stock->creer, 'datepicker').'</td>';
		//          print '</tr>';
		//          print '<tr><td>'.$form->editfieldkey($langs->trans('QCFrequency'), 'qc_frequency', $object->qc_frequency, $object, $user->rights->stock->creer).'</td>';
		//          print '<td>'.$form->editfieldval($langs->trans('QCFrequency'), 'qc_frequency', $object->qc_frequency, $object, $user->rights->stock->creer, 'numeric').'</td>';
		//          print '</tr>';
		//      }
		//
		//      // Other attributes
		//      include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

		print '<table class="border centpercent tableforfield" width="100%">';

		$nboflines = show_stats_for_batch($object, $socid);

		print "</table>";

		print '</div>';
		print '<div class="clearboth"></div>';

		print dol_get_fiche_end();

		if ($showmessage && $nboflines > 1) {
			print '<span class="opacitymedium">'.$langs->trans("ClinkOnALinkOfColumn", $langs->transnoentitiesnoconv("Referers")).'</span>';
		} elseif ($user->hasRight('fournisseur', 'commande', 'lire')) {
			$sql = "SELECT DISTINCT s.nom as name, s.rowid as socid, s.code_fournisseur,";
			$sql .= " cf.ref, cf.date_commande, cf.date_livraison as delivery_date, cf.fk_statut as statut, cf.rowid as facid,";
			$sql .= " cfd.rowid, SUM(cfdi.qty) as qty";
			//          $sql.= ", cfd.total_ht * SUM(cfdi.qty) / cfd.qty as total_ht_pondere";
			if (!$user->hasRight('societe', 'client', 'voir')) {
				$sql .= ", sc.fk_soc, sc.fk_user ";
			}
			$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseur as cf ON (cf.fk_soc = s.rowid)";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet as cfd ON (cfd.fk_commande = cf.rowid)";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."receptiondet_batch as cfdi ON (cfdi.fk_elementdet = cfd.rowid)";
			if (!$user->hasRight('societe', 'client', 'voir')) {
				$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql .= " WHERE cf.entity IN (".getEntity('product').")";
			$sql .= " AND cfdi.batch = '".($db->escape($object->batch))."'";
			if (!empty($search_month)) {
				$sql .= ' AND MONTH(cf.date_commande) IN ('.$db->sanitize($search_month).')';
			}
			if (!empty($search_year)) {
				$sql .= ' AND YEAR(cf.date_commande) IN ('.$db->sanitize($search_year).')';
			}
			if (!$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($socid) {
				$sql .= " AND cf.fk_soc = ".((int) $socid);
			}
			$sql .= " GROUP BY cf.rowid";
			$sql .= $db->order($sortfield, $sortorder);

			// Calcul total qty and amount for global if full scan list
			$total_ht_pondere = 0;
			$total_qty = 0;

			// Count total nb of records
			$totalofrecords = '';
			if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
				$result = $db->query($sql);
				$totalofrecords = $db->num_rows($result);
			}

			$sql .= $db->plimit($limit + 1, $offset);

			$result = $db->query($sql);
			if ($result) {
				$num = $db->num_rows($result);

				$option = '&id='.$object->id;

				if ($limit > 0 && $limit != $conf->liste_limit) {
					$option .= '&limit='.((int) $limit);
				}
				if (!empty($search_month)) {
					$option .= '&search_month='.urlencode((string) ($search_month));
				}
				if (!empty($search_year)) {
					$option .= '&search_year='.urlencode((string) ($search_year));
				}

				print '<form method="post" action="'.$_SERVER ['PHP_SELF'].'?id='.$object->id.'" name="search_form">'."\n";
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="sortfield" value="'.$sortfield.'"/>';
				print '<input type="hidden" name="sortorder" value="'.$sortorder.'"/>';

				// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
				print_barre_liste($langs->trans("SuppliersOrders"), $page, $_SERVER["PHP_SELF"], $option, $sortfield, $sortorder, '', $num, $totalofrecords, '', 0, '', '', $limit, 0, 0, 1);

				if (!empty($page)) {
					$option .= '&page='.urlencode((string) ($page));
				}

				print '<div class="liste_titre liste_titre_bydiv centpercent">';
				print '<div class="divsearchfield">';
				print $langs->trans('Period').' ('.$langs->trans("OrderDate").') - ';
				print $langs->trans('Month').':<input class="flat" type="text" size="4" name="search_month" value="'.$search_month.'"> ';
				print $langs->trans('Year').':'.$formother->selectyear($search_year ? $search_year : - 1, 'search_year', 1, 20, 5);
				print '<div style="vertical-align: middle; display: inline-block">';
				print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"), 'search.png', '', 0, 1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
				print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"), 'searchclear.png', '', 0, 1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
				print '</div>';
				print '</div>';
				print '</div>';

				$i = 0;
				print '<div class="div-table-responsive">';
				print '<table class="tagtable liste listwithfilterbefore" width="100%">';
				print '<tr class="liste_titre">';
				print_liste_field_titre("Ref", $_SERVER["PHP_SELF"], "s.rowid", "", $option, '', $sortfield, $sortorder);
				print_liste_field_titre("Company", $_SERVER["PHP_SELF"], "s.nom", "", $option, '', $sortfield, $sortorder);
				print_liste_field_titre("SupplierCode", $_SERVER["PHP_SELF"], "s.code_fournisseur", "", $option, '', $sortfield, $sortorder);
				print_liste_field_titre("OrderDate", $_SERVER["PHP_SELF"], "cf.date_commande", "", $option, 'align="center"', $sortfield, $sortorder);
				print_liste_field_titre("DateDeliveryPlanned", $_SERVER["PHP_SELF"], "cf.date_livraison", "", $option, 'align="center"', $sortfield, $sortorder);
				print_liste_field_titre("Qty", $_SERVER["PHP_SELF"], "cfdi.qty", "", $option, 'align="center"', $sortfield, $sortorder);
				//              print_liste_field_titre("AmountHT", $_SERVER["PHP_SELF"], "total_ht_pondere", "", $option, 'align="right"', $sortfield, $sortorder);
				print_liste_field_titre("Status", $_SERVER["PHP_SELF"], "cf.fk_statut", "", $option, 'align="right"', $sortfield, $sortorder);
				print "</tr>\n";

				if ($num > 0) {
					while ($i < min($num, $limit)) {
						$objp = $db->fetch_object($result);

						if ($objp->type == Facture::TYPE_CREDIT_NOTE) {
							$objp->qty = -($objp->qty);
						}

						//                      $total_ht_pondere += $objp->total_ht_pondere;
						$total_qty += $objp->qty;

						$commandefournisseurstatic->id = $objp->facid;
						$commandefournisseurstatic->ref = $objp->ref;
						$societestatic->fetch($objp->socid);
						//                      $paiement = $commandefournisseurstatic->getSommePaiement();

						print '<tr class="oddeven">';
						print '<td>';
						print $commandefournisseurstatic->getNomUrl(1);
						print "</td>\n";
						print '<td>'.$societestatic->getNomUrl(1).'</td>';
						print "<td>".$objp->code_fournisseur."</td>\n";
						print '<td class="center">';
						print dol_print_date($db->jdate($objp->date_commande), 'dayhour')."</td>";
						print '<td class="center">';
						print dol_print_date($db->jdate($objp->delivery_date), 'dayhour')."</td>";
						print '<td class="center">'.$objp->qty."</td>\n";
						//                      print '<td align="right">'.price($objp->total_ht_pondere)."</td>\n";
						print '<td align="right">'.$commandefournisseurstatic->LibStatut($objp->statut, 5).'</td>';
						print "</tr>\n";
						$i++;
					}
				}
				print '<tr class="liste_total">';
				if ($num < $limit) {
					print '<td class="left">'.$langs->trans("Total").'</td>';
				} else {
					print '<td class="left">'.$langs->trans("Totalforthispage").'</td>';
				}
				print '<td colspan="2"></td>';
				print '<td></td>';
				print '<td></td>';
				print '<td class="center">'.$total_qty.'</td>';
				//              print '<td class="right">'.$total_ht_pondere.'</td>';
				print '<td></td>';
				print "</table>";
				print '</div>';
				print '</form>';
			} else {
				dol_print_error($db);
			}
			$db->free($result);
		}
	}
} else {
	dol_print_error();
}

// End of page
llxFooter();
$db->close();
