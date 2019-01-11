<?php
/* Copyright (C) 2004-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2008      Raphael Bertrand     <raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2015 Juanjo Menent	    <jmenent@2byte.es>
 * Copyright (C) 2012      Christophe Battarel   <christophe.battarel@altairis.fr>
 * Copyright (C) 2012      Cedric Salvador      <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015      Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2017      Ferran Marcet        <fmarcet@2byte.es>
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
 * juste pour test git
 * 
 * 
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/propale/doc/pdf_analyse_phase.modules.php
 *	\ingroup    propale
 *	\brief      Fichier de la classe permettant de generer les propales au modele analyse_phase_test
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require DOL_DOCUMENT_ROOT.'/conf/conf.php';

/**
 *	Class to generate PDF proposal analyse_phase
 */
class pdf_analyse_phase extends ModelePDFPropales
{
	var $db;
	var $name;
	var $description;
	var $type;
 
	//guits
	var $tauxFG=1.174; 			//taux frais generaux
	var $tauxMini=1.58;			//taux propale mini
	var $tauxConseille=1.62;		//taux propale recommendé										
										  
													

	var $phpmin = array(4,3,0); // Minimum version of PHP required by module
	var $version = 'dolibarr';

	var $page_largeur;
	var $page_hauteur;
	var $format;
	var $marge_gauche;
	var	$marge_droite;
	var	$marge_haute;
	var	$marge_basse;

	var $emetteur;	// Objet societe qui emet






	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		global $conf,$langs,$mysoc;

		$langs->load("main");
		$langs->load("bills");

		$this->db = $db;
		$this->name = "analyse_phase";
		$this->description = 'analyse financière prenant en charge les phases';

		// Dimension page pour format A4
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
		$this->marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
		$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
		$this->marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;

		$this->option_logo = 1;                    // Affiche logo
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 1;                 // Affiche mode reglement
		$this->option_condreg = 1;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 1;      // Affiche code produit-service
		$this->option_multilang = 1;               // Dispo en plusieurs langues
		$this->option_escompte = 0;                // Affiche si il y a eu escompte
		$this->option_credit_note = 0;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   //Support add of a watermark on drafts

		$this->franchise=!$mysoc->tva_assuj;

		// Get source company
		$this->emetteur=$mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code=substr($langs->defaultlang,-2);    // By default, if was not defined

		// Define position of columns
		$this->posxdesc=$this->marge_gauche+1;
		if($conf->global->PRODUCT_USE_UNITS)
		{
			$this->posxtva=99;
			$this->posxup=114;
			$this->posxqty=130;
			$this->posxunit=147;
		}
		else
		{
			$this->posxtva=110;
			$this->posxup=126;
			$this->posxqty=145;
		}
		$this->posxdiscount=162;
		$this->postotalht=174;
		if (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) || ! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN)) $this->posxtva=$this->posxup;
		$this->posxpicture=$this->posxtva - (empty($conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH)?20:$conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH);	// width of images
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$this->posxpicture-=20;
			$this->posxtva-=20;
			$this->posxup-=20;
			$this->posxqty-=20;
			$this->posxunit-=20;
			$this->posxdiscount-=20;
			$this->postotalht-=20;
		}

		$this->tva=array();
		$this->localtax1=array();
		$this->localtax2=array();
		$this->atleastoneratenotnull=0;
		$this->atleastonediscount=0;
	}



















	/**
     *  Function to build pdf onto disk
     *
     *  @param		Object		$object				Object to generate
     *  @param		Translate	$outputlangs		Lang output object
     *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
     *  @param		int			$hidedetails		Do not show line details
     *  @param		int			$hidedesc			Do not show desc
     *  @param		int			$hideref			Do not show ref
     *  @return     int             				1=OK, 0=KO
	 */
	function write_file($object,$outputlangs,$srctemplatepath='',$hidedetails=0,$hidedesc=0,$hideref=0)
	{
		global $user,$langs,$conf,$mysoc,$db,$hookmanager;
		

		if (! is_object($outputlangs)) $outputlangs=$langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (! empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output='ISO-8859-1';

		$outputlangs->load("main");
		$outputlangs->load("dict");
		$outputlangs->load("companies");
		$outputlangs->load("bills");
		$outputlangs->load("propal");
		$outputlangs->load("products");

		$nblignes = count($object->lines);

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray=array();
		if (! empty($conf->global->MAIN_GENERATE_PROPOSALS_WITH_PICTURE))
		{
			$objphoto = new Product($this->db);

			for ($i = 0 ; $i < $nblignes ; $i++)
			{
				if (empty($object->lines[$i]->fk_product)) continue;

				$objphoto->fetch($object->lines[$i]->fk_product);
                //var_dump($objphoto->ref);exit;
  
  
				if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO))
				{
					$pdir[0] = get_exdir($objphoto->id,2,0,0,$objphoto,'product') . $objphoto->id ."/photos/";
					$pdir[1] = get_exdir(0,0,0,0,$objphoto,'product') . dol_sanitizeFileName($objphoto->ref).'/';
				}
				else
				{
					$pdir[0] = get_exdir(0,0,0,0,$objphoto,'product') . dol_sanitizeFileName($objphoto->ref).'/';				// default
					$pdir[1] = get_exdir($objphoto->id,2,0,0,$objphoto,'product') . $objphoto->id ."/photos/";	// alternative
				}

				$arephoto = false;
				foreach ($pdir as $midir)
				{
					if (! $arephoto)
					{
						$dir = $conf->product->dir_output.'/'.$midir;

						foreach ($objphoto->liste_photos($dir,1) as $key => $obj)
						{
							if (empty($conf->global->CAT_HIGH_QUALITY_IMAGES))		// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
							{
								if ($obj['photo_vignette'])
								{
									$filename= $obj['photo_vignette'];
								}
								else
								{
									$filename=$obj['photo'];
								}
							}
							else
							{
								$filename=$obj['photo'];
							}

							$realpath = $dir.$filename;
							$arephoto = true;
						}
					}
				}

				if ($realpath && $arephoto) $realpatharray[$i]=$realpath;
			}
		}

		if (count($realpatharray) == 0) $this->posxpicture=$this->posxtva;

		if ($conf->propal->dir_output)
		{
			$object->fetch_thirdparty();

			$deja_regle = 0;

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->propal->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->propal->dir_output . "/" . $objectref;
				$file = $dir . "/AF-" . $objectref . ".pdf";
			}

			if (! file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				// Add pdfgeneration hook
				if (! is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager=new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('beforePDFCreation',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks

				// Create pdf instance
                $pdf=pdf_getInstance($this->format);
                $default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
																							
																																												 
																															 
	            $pdf->SetAutoPageBreak(1,0);

                if (class_exists('TCPDF'))
                {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));
                // Set path to the background PDF File
                if (empty($conf->global->MAIN_DISABLE_FPDI) && ! empty($conf->global->MAIN_ADD_PDF_BACKGROUND))
                {
                    $pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/'.$conf->global->MAIN_ADD_PDF_BACKGROUND);
                    $tplidx = $pdf->importPage(1);
                }

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor(128,128,128);

				//$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetTitle("Mon titre");
				$pdf->SetSubject("analyse financière");
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				//$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("CommercialProposal")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." analyse financière ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right
/*
				// Positionne $this->atleastonediscount si on a au moins une remise
				for ($i = 0 ; $i < $nblignes ; $i++)
				{
					if ($object->lines[$i]->remise_percent)
					{
						$this->atleastonediscount++;
					}
				}
				if (empty($this->atleastonediscount) && empty($conf->global->PRODUCT_USE_UNITS))
				{
					$this->posxpicture+=($this->postotalht - $this->posxdiscount);
					$this->posxtva+=($this->postotalht - $this->posxdiscount);
					$this->posxup+=($this->postotalht - $this->posxdiscount);
					$this->posxqty+=($this->postotalht - $this->posxdiscount);
					$this->posxdiscount+=($this->postotalht - $this->posxdiscount);
					//$this->postotalht;
				}
*/
  
  
  
  
    
  
  
  
  //debut	jean 		   
						  
							//calcul des variables
				$PrixRevient=0;
				$PrixDeVente=0;
				$PrixDeVenteService=0;
				$PrixDeVenteProduit=0;
				$PrixDeRevientService=0;
				$PrixDeRevientProduit=0;
				$Nbheure=0;
				//variable FG a definir par la creation d'un module
				$FG=1.174;
				
  
	//guits		
	
	
	
	//a voir?
	$txp = $object->array_options['options_txp'];
	$txs = $object->array_options['options_txs'];
	$coefp = $txp +100;
	$coefp = $coefp /100;
	$coefs = $txs +100;
	$coefs = $coefs /100;
	
			
		//J'inisialise les variables
		$nbphase = 0;
		$TD_Recap = array ('nb_heures'=>0, 'prmp'=>0, 'pvmp'=>0, 'prmo'=>0, 'pvmo'=>0);
		$tab_prdoduit = array();
		$tab_mo = array();
		$TD_Phase[0] = array ('Titre'=>'', 'nb_heures'=>0, 'prmp'=>0, 'pvmp'=>0, 'prmo'=>0, 'pvmo'=>0, 'produits'=>$tab_prdoduit, 'mo'=>$tab_mo);
		$TD_Analyse = array ('TD_Recap'=>$TD_Recap, 'TD_Phase'=>$TD_Phase);
		
		
		
		//Je commence la lecture de chaque ligne de la propal
		foreach ($object->lines as $line)
		{
			//C'est un titre de phase
			if ($line->product_type==9 AND $line->qty==1)
			{
				
				////guits debug
				////$arr = get_defined_vars(); //affiche toutes les variables
				//ob_start(); 
				//var_export($line); 
				//$tab_debug=ob_get_contents(); 
				//ob_end_clean(); 
				//$fichier=fopen('testanalyse_phase_line.log','w'); 
				//fwrite($fichier,$tab_debug); 
				//fclose($fichier); 
				////guits debug fin	
		
		
				$nbphase++;
				$tab_prdoduit = array();
				$tab_mo = array();
				$TD_Analyse['TD_Phase'][$nbphase] = array ('Titre'=>$line->desc, 'nb_heures'=>0, 'prmp'=>0, 'pvmp'=>0, 'prmo'=>0, 'pvmo'=>0, 'Produits'=>$tab_prdoduit, 'mo'=>$tab_mo);
			}
			
			//ligne autre que titre et total (standard)
			if ($line->product_type != 9)
			{
		
				//Je construis la requete pour lire les nomencatures
				$query1 = "SELECT rowid, totalPRCMO FROM llx_nomenclature WHERE fk_object = ".$line->id." AND object_type= 'propal'";
				$rown = $db->query($query1, 0, 'auto');
				$resultrown = $db->fetch_object($rown);
	
				//Je construis la requete pour lire le detail des nomencatures
				$query2 = "SELECT fk_product, rowid, price, qty FROM llx_nomenclaturedet WHERE fk_nomenclature = ".$resultrown->rowid;

				$rownd = $db->query($query2, 0, 'auto');
				$pnl = 0;
				$pn = 0;
				$pmp = 0;
				$PrixDeVenteProduitNomenclature=0;
				$PrixDeVenteServiceNomenclature=0;
 
 
				//Je teste si c'est une nomencalture ou une ligne standard
				if ($resultrown->totalPRCMO != null)
				{
					//Je parcours chaque ligne de la nomanclature
					foreach ($rownd as $linend)
					{
						$fkp = $linend['fk_product'];
						//Je teste si la ligne de nomenclature est un service ou un produit
						$query3 = "SELECT fk_product_type, ref, label FROM llx_product WHERE rowid = ".$fkp;
						$productbdd = $db->query($query3, 0, 'auto');
						$producttype = $db->fetch_object($productbdd);
						if ($producttype->fk_product_type == 0) //c'est un produit
						{
							//Je teste si le prix est dans la nomenclature (prix perso), sinon je vais cherchez le prix fournisseur
							 if  ($linend['price'] != 0)
							{ 
								//partie pour la création de la liste matière par phase
								if ($TD_Analyse['TD_Phase'][$nbphase]['Produits'] != null)
								{
									if ( array_key_exists ( $fkp, $TD_Analyse['TD_Phase'][$nbphase]['Produits'])) //test pour savoir si la ref produit est déjà présente dans le tableau
									{
										$q = $TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]['qte'] + ($linend['qty'] * $line->qty);
										$p = $TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]['prix_total'] + ($linend['price'] * $line->qty);
										$TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
									}
									else //le produit n'était pas présent dans le tableau
									{
										$q = $linend['qty'] * $line->qty;
										$p = $linend['price'] * $line->qty;
										$TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
									}	
								}
								//Fin partie phase
								
									
								//je ne calcule pas le prix de vente, je vais le lire dans la propale a revoir.....
								//$pnl = $linend['price'] * $linend['qty'] * $coefp; modif pour qantite et prix perso
								$pnl = $linend['price'] * $coefp;
								$pmpl = $linend['price'] * $line->qty;
								$pn = $pn + $pnl;
								//$pmp = $pmp + $pmpl;
								$PrixDeVenteProduitNomenclature = $PrixDeVenteProduitNomenclature + $pnl;
								$TD_Analyse['TD_Recap']['prmp'] = $TD_Analyse['TD_Recap']['prmp'] + $pmpl;
								$TD_Analyse['TD_Phase'][$nbphase]['prmp'] = $TD_Analyse['TD_Phase'][$nbphase]['prmp'] + $pmpl;
							}
							else
							{
								// On récupère le dernier tarif fournisseur pour ce produit
								$q = 'SELECT unitprice FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_product = '.$linend['fk_product'].' ORDER BY price ASC LIMIT 1';
								$resql = $db->query($q);
								$res = $db->fetch_object($resql);
								$res = $res->unitprice;
								if ($res == NULL)
								{
									// On récupère le prix de revient pour ce produit
									$q = 'SELECT cost_price FROM llx_product WHERE rowid = '.$linend['fk_product'];
									$resql = $db->query($q);
									$res = $db->fetch_object($resql);
									$res = $res->cost_price;
								}
								//partie pour la création de la liste matière par phase
								if ( array_key_exists ( $fkp, $tab_prdoduit)) //test pour savoir si la ref produit est déjà présente dans le tableau
								{
									$q = ($linend['qty'] * $line->qty) + $TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]['qte'];
									$p =($res * ($linend['qty'] * $line->qty)) + $TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]['prix_total'];
									$TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
									
								}
								else //le produit n'était pas présent dans le tableau
								{
									$q = $linend['qty'] * $line->qty;
									$p = $res*$q;
									$TD_Analyse['TD_Phase'][$nbphase]['Produits'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
								}	
								//Fin partie phase	
								//je ne calcule pas le prix de vente, je vais le lire dans la propale
								$pnl = $res * $linend['qty'] * $coefp;
								$pmpl = $res * $linend['qty'] * $line->qty;
								$pn = $pn + $pnl;
								$PrixDeVenteProduitNomenclature = $PrixDeVenteProduitNomenclature + $pnl;
								$TD_Analyse['TD_Recap']['prmp'] = $TD_Analyse['TD_Recap']['prmp'] + $pmpl;
								$TD_Analyse['TD_Phase'][$nbphase]['prmp'] = $TD_Analyse['TD_Phase'][$nbphase]['prmp'] + $pmpl;
							}
						}
						if ($producttype->fk_product_type == 1) //c'est un service
						{
							$TD_Analyse['TD_Recap']['nb_heures'] = $TD_Analyse['TD_Recap']['nb_heures'] + ($linend['qty'] * $line->qty);		
							$TD_Analyse['TD_Phase'][$nbphase] ['nb_heures'] = $TD_Analyse['TD_Phase'][$nbphase] ['nb_heures'] + ($linend['qty'] * $line->qty);	
							
							
							//liste MO																
							if ( array_key_exists ( $fkp, $TD_Analyse['TD_Phase'][$nbphase]['mo'])) //test pour savoir si la ref produit est déjà présente dans le tableau
							{
								$q = $TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]['qte'] + ($linend['qty'] * $line->qty);
								//$p = $TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]['prix_total'] + ($linend['price'] * $line->qty);
								//$TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
								$TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q);
							}
							else //le produit n'était pas présent dans le tableau
							{
								$q = $linend['qty'] * $line->qty;
								//~ $p = $linend['price'] * $line->qty;
								//~ $TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
								$TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q);
							}	
						
							
								
							if  ($linend['price'] != 0)
							{
								//je ne calcule pas le prix de vente, je vais le lire dans la propale
								$pnl = $linend['price'] * $linend['qty'] * $coefs;
								//$pmpl = $linend['price'] * $linend['qty'] * $line->qty;
								$pmpl = $linend['price'] * $line->qty;
								$pn = $pn + $pnl;
								//$pmp = $pmp + $pmpl;
								$PrixDeVenteServiceNomenclature = $PrixDeVenteServiceNomenclature + $pnl;
								$TD_Analyse['TD_Recap']['prmo'] = $TD_Analyse['TD_Recap']['prmo'] + $pmpl;
								$TD_Analyse['TD_Phase'][$nbphase]['prmo'] = $TD_Analyse['TD_Phase'][$nbphase]['prmo'] + $pmpl;
							}
							else
							{	
								// On récupère le dernier tarif fournisseur pour ce produit
								
								//$q = 'SELECT fk_availability FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_product = '.$linend['fk_product'].' AND fk_availability > 0 ORDER BY rowid DESC LIMIT 1';
								$q = 'SELECT unitprice FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_product = '.$linend['fk_product'].' ORDER BY price ASC LIMIT 1';
								$resql = $db->query($q);
								$res = $db->fetch_object($resql);
								$res = $res->unitprice;
								if ($res == NULL)
								{
									// On récupère le prix de revient pour ce produit
									$q = 'SELECT cost_price FROM llx_product WHERE rowid = '.$linend['fk_product'];
									$resql = $db->query($q);
									$res = $db->fetch_object($resql);
									$res = $res->cost_price;
								}
								$pnl = $res * $linend['qty'] * $coefs;
								$pmpl = $res * $linend['qty'] * $line->qty;
								$pn = $pn + $pnl;
								//$pmp = $pmp + $pmpl;
								$PrixDeVenteServiceNomenclature = $PrixDeVenteServiceNomenclature + $pnl;
								$TD_Analyse['TD_Recap']['prmo'] = $TD_Analyse['TD_Recap']['prmo'] + $pmpl;
								$TD_Analyse['TD_Phase'][$nbphase]['prmo'] = $TD_Analyse['TD_Phase'][$nbphase]['prmo'] + $pmpl;
							}
						}					
					}
					//je compare le prix de vente calculé et le prix de vente de la propal 
				
					if (($pn * $line->qty) == $line->total_ht)
					{
						$PrixDeVenteServiceNomenclature = $PrixDeVenteServiceNomenclature * $line->qty;
						$PrixDeVenteProduitNomenclature = $PrixDeVenteProduitNomenclature * $line->qty;
						$TD_Analyse['TD_Recap']['pvmo'] = $TD_Analyse['TD_Recap']['pvmo'] + $PrixDeVenteServiceNomenclature;
						$TD_Analyse['TD_Phase'][$nbphase]['pvmo'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmo'] + $PrixDeVenteServiceNomenclature;
						$TD_Analyse['TD_Recap']['pvmp'] = $TD_Analyse['TD_Recap']['pvmp'] + $PrixDeVenteProduitNomenclature;
						$TD_Analyse['TD_Phase'][$nbphase]['pvmp'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmp'] + $PrixDeVenteProduitNomenclature;
					}
					else	//le prix de vete a été modifié aprés application du coef, donc je pondaire la differrence
					{
						if (($pn * $line->qty) > $line->total_ht)
						{
							$dif = ($pn * $line->qty) - $line->total_ht;
							$PartProduit = $PrixDeVenteProduitNomenclature / $pn;
							$PartService = $PrixDeVenteServiceNomenclature / $pn;
							$PrixDeVenteServiceNomenclature = $PrixDeVenteServiceNomenclature * $line->qty;
							$PrixDeVenteProduitNomenclature = $PrixDeVenteProduitNomenclature * $line->qty;
							$TD_Analyse['TD_Recap']['pvmo'] = $TD_Analyse['TD_Recap']['pvmo'] + $PrixDeVenteServiceNomenclature - ($dif * $PartService);
							$TD_Analyse['TD_Phase'][$nbphase]['pvmo'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmo'] + $PrixDeVenteServiceNomenclature - ($dif * $PartService);
							$TD_Analyse['TD_Recap']['pvmp'] = $TD_Analyse['TD_Recap']['pvmp'] + $PrixDeVenteProduitNomenclature - ($dif * $PartProduit);
							$TD_Analyse['TD_Phase'][$nbphase]['pvmp'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmp'] + $PrixDeVenteProduitNomenclature - ($dif * $PartProduit);
						}
						else
						{
							$dif = $line->total_ht - ($pn * $line->qty);
							$PartProduit = $PrixDeVenteProduitNomenclature / $pn;
							$PartService = $PrixDeVenteServiceNomenclature / $pn;
							$PrixDeVenteServiceNomenclature = $PrixDeVenteServiceNomenclature * $line->qty;
							$PrixDeVenteProduitNomenclature = $PrixDeVenteProduitNomenclature * $line->qty;
							$TD_Analyse['TD_Recap']['pvmo'] = $TD_Analyse['TD_Recap']['pvmo'] + $PrixDeVenteServiceNomenclature + ($dif * $PartService);
							$TD_Analyse['TD_Phase'][$nbphase]['pvmo'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmo'] + $PrixDeVenteServiceNomenclature + ($dif * $PartService);
							$TD_Analyse['TD_Recap']['pvmp'] = $TD_Analyse['TD_Recap']['pvmp'] + $PrixDeVenteProduitNomenclature + ($dif * $PartProduit);
							$TD_Analyse['TD_Phase'][$nbphase]['pvmp'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmp'] + $PrixDeVenteProduitNomenclature + ($dif * $PartProduit);
						}
					}
				}
		
				else //ça n'est pas une nomenclature	
				{
					if ($line->product_type == 0) //c'est un produit
					{	
						$TD_Analyse['TD_Recap']['pvmp'] = $TD_Analyse['TD_Recap']['pvmp'] + $line->total_ht;
						$TD_Analyse['TD_Phase'][$nbphase]['pvmp'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmp'] + $line->total_ht;
						$pu = $line->pa_ht;
						$pu = $pu * $line->qty;
						$TD_Analyse['TD_Recap']['prmp'] = $TD_Analyse['TD_Recap']['prmp'] + $pu;
						$TD_Analyse['TD_Phase'][$nbphase]['prmp'] = $TD_Analyse['TD_Phase'][$nbphase]['prmp'] + $pu;
							
						//partie pour la création de la liste matière par phase
						if ( array_key_exists ( $line->fk_product, $tab_prdoduit)) //test pour savoir si la ref produit est déjà présente dans le tableau
						{
							$q = $line->qty + $TD_Phase[$nbphase]['Produits'] [$fkp]['qte'];
							$p =($line->pa_ht * $line->qty) + $TD_Phase[$nbphase]['Produits'] [$fkp]['prix_total'];
							//$tab_prdoduit [$line->fk_product]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
							
						}
						else //le produit n'était pas présent dans le tableau
						{
							$q = $line->qty;
							$p = $line->pa_ht * $line->qty;
							//$tab_prdoduit [$line->fk_product]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
						}	
						//Fin partie phase
						
					}
					if ($line->product_type == 1) //c'est un service
					{	
						//liste MO																
							if ( array_key_exists ( $line->fk_product, $TD_Analyse['TD_Phase'][$nbphase]['mo'])) //test pour savoir si la ref produit est déjà présente dans le tableau
							{
								$q = $TD_Analyse['TD_Phase'][$nbphase]['mo'] [$line->fk_product]['qte'] +  $line->qty;
								//$p = $TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]['prix_total'] + ($linend['price'] * $line->qty);
								//$TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
								$TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q);
							}
							else //le produit n'était pas présent dans le tableau
							{
								$q = $line->qty;
								//~ $p = $linend['price'] * $line->qty;
								//~ $TD_Analyse['TD_Phase'][$nbphase]['mo'] [$fkp]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q, 'prix_total'=>$p);
								$TD_Analyse['TD_Phase'][$nbphase]['mo'] [$line->fk_product]= array('ref'=>$producttype->ref, 'label'=>$producttype->label, 'qte'=>$q);
							}
						
						$TD_Analyse['TD_Recap']['pvmo'] = $TD_Analyse['TD_Recap']['pvmo'] + $line->total_ht;
						$TD_Analyse['TD_Phase'][$nbphase]['pvmo'] = $TD_Analyse['TD_Phase'][$nbphase]['pvmo'] + $line->total_ht;
						$pu = $line->pa_ht;
						$pu = $pu * $line->qty;
						$TD_Analyse['TD_Recap']['nb_heures'] = $TD_Analyse['TD_Recap']['nb_heures'] + $line->qty;
						$TD_Analyse['TD_Phase'][$nbphase] ['nb_heures'] = $TD_Analyse['TD_Phase'][$nbphase] ['nb_heures'] + $line->qty;
						//$phase['heures'] = $phase['heures'] + $line->qty;
						$TD_Analyse['TD_Recap']['prmo'] = $TD_Analyse['TD_Recap']['prmo'] + $pu;
						$TD_Analyse['TD_Phase'][$nbphase]['prmo'] = $TD_Analyse['TD_Phase'][$nbphase]['prmo'] + $pu;
						//$object->updateline($line->id, $pu, $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->desc, 'HT', $line->info_bits, $line->special_code, $line->fk_parent_line, $line->skip_update_total, 0, $line->pa_ht, $line->label, $line->product_type, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
					}
				}
					
			}
		}	
		
		
		
		
	

											//guits debug
											//$arr = get_defined_vars(); //affiche toutes les variables
											ob_start(); 

											var_export($TD_Analyse); 

											$tab_debug=ob_get_contents(); 
											ob_end_clean(); 
											$fichier=fopen('testanalyse_phase_analyse.log','w'); 
											fwrite($fichier,$tab_debug); 
											fclose($fichier); 
											//guits debug fin
	 
	


//suite jean	
	
	////guits debug
    	////$arr = get_defined_vars(); //affiche toutes les variables
		//ob_start(); 

		//var_export($object); 

		//$tab_debug=ob_get_contents(); 
		//ob_end_clean(); 
		//$fichier=fopen('testanalyse_phase.log','w'); 
		//fwrite($fichier,$tab_debug); 
		//fclose($fichier); 
		////guits debug fin
	
	
	
	
	
	
	
				/* 	// Loop on each lines
				for ($i = 0; $i < $nblignes; $i++)
				{
					$PrixRevient+=$object->lines[$i]->pa_ht*$object->lines[$i]->qty;
					//echo "Prix de revient : ".$PrixRevient."<br />";
					//si c'est un service
					if($object->lines[$i]->product_type==1){
						$PrixDeVenteService+=$object->lines[$i]->total_ht;
						$PrixDeRevientService+=$object->lines[$i]->pa_ht*$object->lines[$i]->qty;
						$Nbheure+=$object->lines[$i]->qty;
					}
					//sinon c'est un produit
					else{
						$PrixDeVenteProduit+=$object->lines[$i]->total_ht;
						$PrixDeRevientProduit+=$object->lines[$i]->pa_ht*$object->lines[$i]->qty;
					}
					//echo "PrixDeVenteService : ".$PrixDeVenteService."<br />";
					//echo "PrixDeVenteProduit : ".$PrixDeVenteProduit."<br />";
					
				} */
				
				
				
		//$titre = "monTitreAMoi";
		//$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top-4);
		//$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);	
		//$pdf->MultiCell(90, 4, $titre, 0, 'L');	
				
				$PrixRevient = $TD_Analyse['TD_Recap']['prmp'] + $TD_Analyse['TD_Recap']['prmo'];
				$PrixDeVente = $TD_Analyse['TD_Recap']['pvmo'] + $TD_Analyse['TD_Recap']['pvmp'];
				$Marge = $PrixDeVente - $PrixRevient;
				$TauxMarge = ($Marge / $PrixDeVente) * 100;
				$TauxMargeBrute = (($PrixDeVente - $TD_Analyse['TD_Recap']['prmp']) / $PrixDeVente) * 100;
				$PartMatiere = ($TD_Analyse['TD_Recap']['pvmp'] / $PrixDeVente) * 100;
				$PartService = ($TD_Analyse['TD_Recap']['pvmo'] / $PrixDeVente) * 100;
				if ($TD_Analyse['TD_Recap']['pvmo'] != 0)//pour les cas ou il n'y a pas de service
				{
					$TauxMargeService = (($TD_Analyse['TD_Recap']['pvmo'] - $TD_Analyse['TD_Recap']['prmo']) / $TD_Analyse['TD_Recap']['pvmo']) * 100;
				}
				if ($TD_Analyse['TD_Recap']['pvmp'] != 0)//pour les cas ou il n'y a pas de produit
				{
					$TauxMargeMatiere = (($TD_Analyse['TD_Recap']['pvmp'] - $TD_Analyse['TD_Recap']['prmp']) / $TD_Analyse['TD_Recap']['pvmp']) * 100;
				}
				if ($TD_Analyse['TD_Recap']['nb_heures'] != 0)//pour les cas ou il n'y a pas de service
				{
					$PrixHeure = $TD_Analyse['TD_Recap']['pvmo'] / $TD_Analyse['TD_Recap']['nb_heures'];
				}
				
				//$TauxMargeService = ($PrixDeRevientService / $PrixDeVenteService ) * 100;
				//$TauxMargeMatiere = ($PrixDeRevientProduit / $PrixDeVenteProduit) * 100;
				
				
				
				$pdf->AddPage();
				if (! empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;

																							
																																														 
																																													   
																															 
																											 

				$this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('','', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0,0,0);


				$tab_top = 90;
				$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)?42:10);
				$tab_height = 130;
				$tab_height_newpage = 150;
				
				
				//$myhtml="<hr>";
			
  
						
				//$myhtml.="<table>";
				//$myhtml.="<tr>";
				//$myhtml.="<th><b>analyse_phase Globale</b></th><th></th><th></th><th></th><th></th><th></th>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td>Chiffre d'affaire</td><td>".$PrixDeVente." €</td><td>Marge nette</td><td>".$Marge."  €</td><td>Taux de marge nette</td><td>".round($TauxMarge, 1)." %</td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td>Taux de marge brute</td><td>".round($TauxMargeBrute, 1)." %</td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td><b>analyse_phase matière</b></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td>Prix de revient matière</td><td>".round($PrixDeRevientProduit, 2)." €</td><td>Part matière</td><td>".$PrixDeVenteProduit." € soit ".round($PartMatiere)." %</td><td>Taux de marge matière</td><td>".round($TauxMargeMatiere)." %</td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td><b>analyse_phase main d'oeuvre</b></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td>Prix de revient MO</td><td>".round($PrixDeRevientService, 2)." €</td><td>Part MO</td><td>".$PrixDeVenteService." € soit ".round($PartService)." %</td><td>Taux de marge MO</td><td>".round($TauxMargeService)." %</td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="<tr>";
				//$myhtml.="<td>Nombre d'heures</td><td>".$Nbheure." h</td><td></td><td></td><td></td><td></td>";
				//$myhtml.="</tr>";
				//$myhtml.="</table>";
				
				
				
				
							   
				//$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top-1, $myhtml, 0, 1);				  
	 
	 
	  ////affichage phase// New page
	  
	  //$pdf->AddPage();
				//if (! empty($tplidx)) $pdf->useTemplate($tplidx);
				//$pagenb++;
				
				
				
				
				
				$lecture_phase =1;
				
				
				
				
				
				
				
				
				
				
				
				
						////$myhtml.="</TR>";
					////$myhtml.="</TABLE>";
					
					
					
					$myhtml="<style style='text/css'>";
					$myhtml.="table{";
						$myhtml.="border: 1px solid black;";
						$myhtml.="border-collapse: collapse;";
					$myhtml.="}";

					$myhtml.="th, td {";
						$myhtml.="border: 1px solid black;";
					$myhtml.="}";
					$myhtml.="</style> ";
					$myhtml.="</head>";
					$myhtml.="<html>";
					$myhtml.="<body>";

					//Tableaux analyse générale
					$myhtml.="<p><b>Analyse globale</b></p>";
					$myhtml.="<TABLE>";
					$myhtml.="<TR><TD>Chiffre d'affaire</TD><TD>Marge nette</TD><TD>Marge brute</TD>";
					$myhtml.="</TR>";
					$myhtml.="<TR><TD>".round($PrixDeVente, 2)."€</TD><TD>".round($Marge, 2)."€ soit ".round($TauxMarge, 1)."%</TD><TD>".round($TauxMargeBrute, 1)."</TD>";
					$myhtml.="</TR>";
					$myhtml.="</TABLE>";
					
					
					$myhtml.="<p><b>Analyse matière</b></p>";
					$myhtml.="<TABLE>";
					$myhtml.="<TR><TD>Prix de revient</TD><TD>Part Matière</TD><TD>Marge</TD>";
					$myhtml.="</TR>";
					$myhtml.="<TR><TD>".round($TD_Analyse['TD_Recap']['prmp'], 2)."€</TD><TD>".round($TD_Analyse['TD_Recap']['pvmp'], 2)."€ soit ".round($PartMatiere, 1)."%</TD><TD>".round(($TD_Analyse['TD_Recap']['pvmp'] - $TD_Analyse['TD_Recap']['prmp']), 2)."€ soit ".round($TauxMargeMatiere, 1)."%</TD>";
					$myhtml.="</TR>";
					$myhtml.="</TABLE>";
										
					$myhtml.="<p><b>Analyse main d'oeuvre</b></p>";
					$myhtml.="<TABLE>";
					$myhtml.="<TR><TD>Prix de revient</TD><TD>Part MO</TD><TD>Marge</TD><TD>Nombre d'heures</TD>";
					$myhtml.="</TR>";
					$myhtml.="<TR><TD>".round($TD_Analyse['TD_Recap']['prmo'], 2)."€</TD><TD>".round($TD_Analyse['TD_Recap']['pvmo'], 2)."€ soit ".round($PartService, 1)."%</TD><TD>".round(($TD_Analyse['TD_Recap']['pvmo'] - $TD_Analyse['TD_Recap']['prmo']), 2)."€ soit ".round($TauxMargeService, 1)."%</TD><TD>".$TD_Analyse['TD_Recap']['nb_heures']." h à ".round($PrixHeure, 2)."€</TD>";
					$myhtml.="</TR>";
					$myhtml.="</TABLE>";
					
					
					//Tableaux analyse par phase
					if ($nbphase > 1) //Je n'affiche le tableau que si il y a plus d'une phase
					{
						$lecture_phase = 1;
						$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top-1, $myhtml, 0, 1);	
						$tab_top = $tab_top - 60;
	 
	 
	 
	  ////affichage phase// New page
	  
	  $pdf->AddPage();
				if (! empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;
						$myhtml="<style style='text/css'>";
					$myhtml.="table{";
						$myhtml.="border: 1px solid black;";
						$myhtml.="border-collapse: collapse;";
					$myhtml.="}";

					$myhtml.="th, td {";
						$myhtml.="border: 1px solid black;";
					$myhtml.="}";
					$myhtml.="</style> ";
					$myhtml.="</head>";
					$myhtml.="<html>";
					$myhtml.="<body>";
						
						
						
						
						
						while ($lecture_phase <= $nbphase)
						{	
							
							//attention!!!! il manque tout les calculs!!!! et peut etre une nouvelle page
							//$TD_Analyse['TD_Phase'][$nbphase] = array ('Titre'=>$line->desc, 'nb_heures'=>0, 'prmp'=>0, 'pvmp'=>0, 'prmo'=>0, 'pvmo'=>0, 'Produits'=>$tab_prdoduit);				
							
							//$PrixDeVente = $TD_Analyse['TD_Phase'][$lecture_phase]['pvmp'] + $TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'];
							
							
							$PrixRevient = $TD_Analyse['TD_Phase'][$lecture_phase]['prmp'] + $TD_Analyse['TD_Phase'][$lecture_phase]['prmo'];
							$PrixDeVente = $TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'] + $TD_Analyse['TD_Phase'][$lecture_phase]['pvmp'];
							$Marge = $PrixDeVente - $PrixRevient;
							$TauxMarge = ($Marge / $PrixDeVente) * 100;
							$TauxMargeBrute = (($PrixDeVente - $TD_Analyse['TD_Phase'][$lecture_phase]['prmp']) / $PrixDeVente) * 100;
							$PartMatiere = ($TD_Analyse['TD_Phase'][$lecture_phase]['pvmp'] / $PrixDeVente) * 100;
							$PartService = ($TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'] / $PrixDeVente) * 100;
							if ($TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'] != 0)
							{
								$TauxMargeService = (($TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'] - $TD_Analyse['TD_Phase'][$lecture_phase]['prmo']) / $TD_Analyse['TD_Phase'][$lecture_phase]['pvmo']) * 100;
							}
							if ($TD_Analyse['TD_Phase'][$lecture_phase]['pvmp'] != 0)
							{
								$TauxMargeMatiere = (($TD_Analyse['TD_Phase'][$lecture_phase]['pvmp'] - $TD_Analyse['TD_Phase'][$lecture_phase]['prmp']) / $TD_Analyse['TD_Phase'][$lecture_phase]['pvmp']) * 100;
							}
							if ($TD_Analyse['TD_Phase'][$lecture_phase]['nb_heures'] != 0)
							{
								$PrixHeure = $TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'] / $TD_Analyse['TD_Phase'][$lecture_phase]['nb_heures'];
							}
							
							
							
							
							
							
							
							$myhtml.="<p><b>Analyse phase ".$lecture_phase."</b></p>";
							$myhtml.="<p><b>".$TD_Analyse['TD_Phase'][$lecture_phase]['Titre']."</b></p>";
							$myhtml.="<TABLE>";
							$myhtml.="<TR><TD>Chiffre d'affaire</TD><TD>Marge nette</TD><TD>Marge brute</TD>";
							$myhtml.="</TR>";
							$myhtml.="<TR><TD>".round($PrixDeVente, 2)."€</TD><TD>".round($Marge, 2)."€ soit ".round($TauxMarge, 1)."%</TD><TD>".round($TauxMargeBrute, 1)."</TD>";
							$myhtml.="</TR>";
							$myhtml.="</TABLE>";
							
							
							$myhtml.="<p><b>Analyse matière</b></p>";
							
							$myhtml.="<TABLE>";
							
							
							$myhtml.="<TR><TD>Prix de revient</TD><TD>Part Matière</TD><TD>Marge</TD>";
							$myhtml.="</TR>";
							$myhtml.="<TR><TD>".round($TD_Analyse['TD_Phase'][$lecture_phase]['prmp'], 2)."€</TD><TD>".round($TD_Analyse['TD_Phase'][$lecture_phase]['pvmp'], 2)."€ soit ".round($PartMatiere, 1)."%</TD><TD>".round(($TD_Analyse['TD_Phase'][$lecture_phase]['pvmp'] - $TD_Analyse['TD_Phase'][$lecture_phase]['prmp']), 2)."€ soit ".round($TauxMargeMatiere, 1)."%</TD>";
							$myhtml.="</TR>";
							$myhtml.="</TABLE>";
												
							$myhtml.="<p><b>Analyse main d'oeuvre</b></p>";
							$myhtml.="<TABLE>";
							$myhtml.="<TR><TD>Prix de revient</TD><TD>Part MO</TD><TD>Marge</TD><TD>Nombre d'heures</TD>";
							$myhtml.="</TR>";
							$myhtml.="<TR><TD>".round($TD_Analyse['TD_Phase'][$lecture_phase]['prmo'], 2)."€</TD><TD>".round($TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'], 2)."€ soit ".round($PartService, 1)."%</TD><TD>".round(($TD_Analyse['TD_Phase'][$lecture_phase]['pvmo'] - $TD_Analyse['TD_Phase'][$lecture_phase]['prmo']), 2)."€ soit ".round($TauxMargeService, 1)."%</TD><TD>".$TD_Analyse['TD_Phase'][$lecture_phase]['nb_heures']." h à ".round($PrixHeure, 2)."€</TD>";
							$myhtml.="</TR>";
							$myhtml.="</TABLE>";
							$lecture_phase++;
						}
					}
					
					
					//Tableaux mo par phase
					$lecture_phase = 1;
					while ($lecture_phase <= $nbphase)
					{	
						
						$myhtml.="<p><b>Liste MO phase ".$lecture_phase."</b></p>";
						$myhtml.="<p><b>".$TD_Analyse['TD_Phase'][$lecture_phase]['Titre']."</b></p>";
						$myhtml.="<TABLE>";
						$myhtml.="<TR><TD style='background-color:red'>Descriptions</TD><TD>Qantités</TD>";
						$myhtml.="</TR>";
						$produits = $TD_Analyse['TD_Phase'][$lecture_phase]['mo'];
						
						//$array_produit = array ();
						foreach ($produits as $produit)
						{
							$myhtml.="<TR><TD>".$produit['label']."</TD><TD>".$produit['qte']."</TD>";
							//$myhtml.="<TR><TD>".round($PrixDeVente, 2)."€</TD><TD>".round($Marge, 2)."€ soit ".round($TauxMarge, 1)."%</TD><TD>".round($TauxMargeBrute, 1)."</TD>";
							$myhtml.="</TR>";
							
										
							
						}
						$myhtml.="</TABLE>";
				
						$lecture_phase++;
					}
					
			
			
					//Tableaux produits par phase
					$lecture_phase = 1;
					while ($lecture_phase <= $nbphase)
					{	
						
						$myhtml.="<p><b>Liste produits phase ".$lecture_phase."</b></p>";
						$myhtml.="<p><b>".$TD_Analyse['TD_Phase'][$lecture_phase]['Titre']."</b></p>";
						$myhtml.="<TABLE>";
						$myhtml.="<TR><TD style='background-color:red'>Descriptions</TD><TD>Qantités</TD><TD>Prix d'achats</TD>";
						$myhtml.="</TR>";
						$produits = $TD_Analyse['TD_Phase'][$lecture_phase]['Produits'];
						
						//$array_produit = array ();
						foreach ($produits as $produit)
						{
							$myhtml.="<TR><TD>".$produit['label']."</TD><TD>".$produit['qte']."</TD><TD>".$produit['prix_total']."</TD>";
							//$myhtml.="<TR><TD>".round($PrixDeVente, 2)."€</TD><TD>".round($Marge, 2)."€ soit ".round($TauxMarge, 1)."%</TD><TD>".round($TauxMargeBrute, 1)."</TD>";
							$myhtml.="</TR>";
							
						
							
						}
						$myhtml.="</TABLE>";
				
						$lecture_phase++;
					}
					
			
					
					
					
					//$myhtml.="<p>liste produits phase ".$lecture_phase."</p>";
					////$myhtml.="<TABLE BORDER=4 cellspacing=4 cellpadding=4 WIDTH='80%'>";
					//$myhtml.="<TABLE>";
					//$myhtml.="<TR><TD>Description</TD><TD>Qte</TD><TD>Prix total</TD>";
					//$myhtml.="</TR>";
					////foreach ($tab_prdoduit as $produit)
					////{
						
						////$html.="";
						////$html.="<p>liste produits phase ".$lecture_phase."</p>";
						//$titi ="allé!!!!!";
						////myhtml.="<TR>";
						////$myhtml.="<TR><TD>".$produit['label']."</TD><TD>".$produit['qte']."</TD><TD>".$produit['prix_total']."</TD>";
						//$myhtml.="<TR><TD>".$titi."</TD><TD>".$titi."</TD><TD>".$titi."</TD>";
						//$myhtml.="</TR>";
						
						
						
						
						////$myhtml.="<p>";
						////$myhtml.=$produit['qte']." X ".$produit['ref']." / ".$produit['ref']."pour un prix total de ".$produit['prix_total']."€";
						////$myhtml.="<br>";
						
					////}
					
					//$myhtml.="</TABLE>";
					$myhtml.="</body>";
					
					
					//$html.="</p>";
					$lecture_phase++;
					//$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top-1, $myhtml, 0, 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top, dol_htmlentitiesbr($myhtml), 0, 1);
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				
				//While ($lecture_phase <= $nbphase)
				//foreach ($tab_prdoduit_phase as $tab_produit)
				//{
						
					//$pdf->AddPage();
					//if (! empty($tplidx)) $pdf->useTemplate($tplidx);
					//$pagenb++;

																								
																																															 
																																														   
																																 
																												 

					//$this->_pagehead($pdf, $object, 1, $outputlangs);
					//$pdf->SetFont('','', $default_font_size - 1);
					//$pdf->MultiCell(0, 3, '');		// Set interline to 3
					//$pdf->SetTextColor(0,0,0);


					//$tab_top = 90;
					//$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)?42:10);
					//$tab_height = 130;
					//$tab_height_newpage = 150;
					
					
					//$myhtml="<hr>";
				
	  
							
					//$myhtml.="<table>";
					//$myhtml.="<tr>";
					//$myhtml.="<th><b>analyse Phase ".$lecture_phase."></b></th><th></th><th></th><th></th><th></th><th></th>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td>Chiffre d'affaire</td><td>".$PrixDeVente." €</td><td>Marge nette</td><td>".$Marge."  €</td><td>Taux de marge nette</td><td>".round($TauxMarge, 1)." %</td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td>Taux de marge brute</td><td>".round($TauxMargeBrute, 1)." %</td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td><b>analyse_phase matière</b></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td>Prix de revient matière</td><td>".round($PrixDeRevientProduit, 2)." €</td><td>Part matière</td><td>".$PrixDeVenteProduit." € soit ".round($PartMatiere)." %</td><td>Taux de marge matière</td><td>".round($TauxMargeMatiere)." %</td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td><b>analyse_phase main d'oeuvre</b></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td>Prix de revient MO</td><td>".round($PrixDeRevientService, 2)." €</td><td>Part MO</td><td>".$PrixDeVenteService." € soit ".round($PartService)." %</td><td>Taux de marge MO</td><td>".round($TauxMargeService)." %</td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td></td><td></td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="<tr>";
					//$myhtml.="<td>Nombre d'heures</td><td>".$Nbheure." h</td><td></td><td></td><td></td><td></td>";
					//$myhtml.="</tr>";
					//$myhtml.="</table>";
					
					
						
						////$myhtml.="</TR>";
						////$myhtml.="<TR><TD>".$produit['label']."</TD><TD>".$produit['qte']."</TD><TD>".$produit['prix_total']."</TD>";
						
						
						
						
						
						//////$myhtml.="<p>";
						//////$myhtml.=$produit['qte']." X ".$produit['ref']." / ".$produit['ref']."pour un prix total de ".$produit['prix_total']."€";
						//////$myhtml.="<br>";
						
					////}
					////$myhtml.="</TR>";
					////$myhtml.="</TABLE>";
					
					
					
					//$myhtml.="<style style='text/css'>";
					//$myhtml.="table{";
						//$myhtml.="border: 1px solid black;";
						//$myhtml.="border-collapse: collapse;";
					//$myhtml.="}";

					//$myhtml.="th, td {";
						//$myhtml.="border: 1px solid black;";
					//$myhtml.="}";
					//$myhtml.="</style> ";
					//$myhtml.="</head>";
					//$myhtml.="<html>";
					//$myhtml.="<body>";

					//$myhtml.="<table>";
					//$myhtml.="<tr>";
					//$myhtml.="<td>Description</td>";
					//$myhtml.="<td>Qte</td>";
					//$myhtml.="td>Prix Total</td>";
					//$myhtml.="</tr>";
					//$myhtml.="<p>liste produits phase ".$lecture_phase."</p>";
					//$myhtml.="<TABLE BORDER=4 cellspacing=4 cellpadding=4 WIDTH='80%'>";
					//$myhtml.="<TR><TD>Description</TD><TD>Qte</TD><TD>Prix total</TD>";
					//$myhtml.="</TR>";
					//foreach ($tab_prdoduit as $produit)
					//{
						
						////$html.="";
						////$html.="<p>liste produits phase ".$lecture_phase."</p>";
						
						////myhtml.="<TR>";
						//$myhtml.="<TR><TD>".$produit['label']."</TD><TD>".$produit['qte']."</TD><TD>".$produit['prix_total']."</TD>";
						//$myhtml.="</TR>";
						
						
						
						
						////$myhtml.="<p>";
						////$myhtml.=$produit['qte']." X ".$produit['ref']." / ".$produit['ref']."pour un prix total de ".$produit['prix_total']."€";
						////$myhtml.="<br>";
						
					//}
					
					//$myhtml.="</TABLE>";
					//$myhtml.="</body>";
					
					
		
					
					
					
					
					
					
					
					////$html.="</p>";
					//$lecture_phase++;
					////$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top-1, $myhtml, 0, 1);
					//$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top, dol_htmlentitiesbr($myhtml), 0, 1);

				//}

				
			
													   
					  
	
						  
									  
	 
																	 
													   
						  
											 
														
																			   
										
	  
							 
		  
														
																			   
	  
	
 // Pied de page
				//$this->_pagefoot($pdf,$object,$outputlangs);
				//if (method_exists($pdf,'AliasNbPages')) $pdf->AliasNbPages();
				
				
				$pdf->Close();

				$pdf->Output($file,'F');

				//Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('afterPDFCreation',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks

				if (! empty($conf->global->MAIN_UMASK))
				@chmod($file, octdec($conf->global->MAIN_UMASK));

				return 1;   // Pas d'erreur
			}
			else
			{
				$this->error=$langs->trans("ErrorCanNotCreateDir",$dir);
				return 0;
			}
		}
		else
		{
			$this->error=$langs->trans("ErrorConstantNotDefined","PROP_OUTPUTDIR");
			return 0;
		}

											 
								  
	}

	/**
	 *  Show payments table
	 *
     *  @param	TCPDF		$pdf           Object PDF
     *  @param  Object		$object         Object proposal
     *  @param  int			$posy           Position y in PDF
     *  @param  Translate	$outputlangs    Object langs for output
     *  @return int             			<0 if KO, >0 if OK
	 */
	function _tableau_versements(&$pdf, $object, $posy, $outputlangs)
	{

	}


	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		Object		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	void
	 */
	function _tableau_info(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('','', $default_font_size - 1);

		// If France, show VAT mention if not applicable
		if ($this->emetteur->country_code == 'FR' && $this->franchise == 1)
		{
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

			$posy=$pdf->GetY()+4;
		}

		$posxval=52;

        // Show shipping date
        if (! empty($object->date_livraison))
		{
            $outputlangs->load("sendings");
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("DateDeliveryPlanned").':';
			$pdf->MultiCell(80, 4, $titre, 0, 'L');
			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$dlp=dol_print_date($object->date_livraison,"daytext",false,$outputlangs,true);
			$pdf->MultiCell(80, 4, $dlp, 0, 'L');

            $posy=$pdf->GetY()+1;
		}
        elseif ($object->availability_code || $object->availability)    // Show availability conditions
		{
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("AvailabilityPeriod").':';
			$pdf->MultiCell(80, 4, $titre, 0, 'L');
			$pdf->SetTextColor(0,0,0);
			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_availability=$outputlangs->transnoentities("AvailabilityType".$object->availability_code)!=('AvailabilityType'.$object->availability_code)?$outputlangs->transnoentities("AvailabilityType".$object->availability_code):$outputlangs->convToOutputCharset($object->availability);
			$lib_availability=str_replace('\n',"\n",$lib_availability);
			$pdf->MultiCell(80, 4, $lib_availability, 0, 'L');

			$posy=$pdf->GetY()+1;
		}

		// Show payments conditions
		if (empty($conf->global->PROPALE_PDF_HIDE_PAYMENTTERMCOND) && ($object->cond_reglement_code || $object->cond_reglement))
		{
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentConditions").':';
			$pdf->MultiCell(43, 4, $titre, 0, 'L');

			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_condition_paiement=$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)!=('PaymentCondition'.$object->cond_reglement_code)?$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code):$outputlangs->convToOutputCharset($object->cond_reglement_doc);
			$lib_condition_paiement=str_replace('\n',"\n",$lib_condition_paiement);
			$pdf->MultiCell(67, 4, $lib_condition_paiement,0,'L');

			$posy=$pdf->GetY()+3;
		}

		if (empty($conf->global->PROPALE_PDF_HIDE_PAYMENTTERMCOND))
		{
			// Check a payment mode is defined
			/* Not required on a proposal
			if (empty($object->mode_reglement_code)
			&& ! $conf->global->FACTURE_CHQ_NUMBER
			&& ! $conf->global->FACTURE_RIB_NUMBER)
			{
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetTextColor(200,0,0);
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->MultiCell(90, 3, $outputlangs->transnoentities("ErrorNoPaiementModeConfigured"),0,'L',0);
				$pdf->SetTextColor(0,0,0);

				$posy=$pdf->GetY()+1;
			}
			*/

			// Show payment mode
			if ($object->mode_reglement_code
			&& $object->mode_reglement_code != 'CHQ'
			&& $object->mode_reglement_code != 'VIR')
			{
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$titre = $outputlangs->transnoentities("PaymentMode").':';
				$pdf->MultiCell(80, 5, $titre, 0, 'L');
				$pdf->SetFont('','', $default_font_size - 2);
				$pdf->SetXY($posxval, $posy);
				$lib_mode_reg=$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code)!=('PaymentType'.$object->mode_reglement_code)?$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code):$outputlangs->convToOutputCharset($object->mode_reglement);
				$pdf->MultiCell(80, 5, $lib_mode_reg,0,'L');

				$posy=$pdf->GetY()+2;
			}

			// Show payment mode CHQ
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ')
			{
				// Si mode reglement non force ou si force a CHQ
				if (! empty($conf->global->FACTURE_CHQ_NUMBER))
				{
					$diffsizetitle=(empty($conf->global->PDF_DIFFSIZE_TITLE)?3:$conf->global->PDF_DIFFSIZE_TITLE);

					if ($conf->global->FACTURE_CHQ_NUMBER > 0)
					{
						$account = new Account($this->db);
						$account->fetch($conf->global->FACTURE_CHQ_NUMBER);

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$account->proprio),0,'L',0);
						$posy=$pdf->GetY()+1;

			            if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
			            {
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('','', $default_font_size - $diffsizetitle);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
							$posy=$pdf->GetY()+2;
			            }
					}
					if ($conf->global->FACTURE_CHQ_NUMBER == -1)
					{
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$this->emetteur->name),0,'L',0);
						$posy=$pdf->GetY()+1;

			            if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
			            {
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('','', $default_font_size - $diffsizetitle);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
							$posy=$pdf->GetY()+2;
			            }
					}
				}
			}

			// If payment mode not forced or forced to VIR, show payment with BAN
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR')
			{
				if (! empty($object->fk_account) || ! empty($object->fk_bank) || ! empty($conf->global->FACTURE_RIB_NUMBER))
				{
					$bankid=(empty($object->fk_account)?$conf->global->FACTURE_RIB_NUMBER:$object->fk_account);
					if (! empty($object->fk_bank)) $bankid=$object->fk_bank;   // For backward compatibility when object->fk_account is forced with object->fk_bank
					$account = new Account($this->db);
					$account->fetch($bankid);

					$curx=$this->marge_gauche;
					$cury=$posy;

					$posy=pdf_bank($pdf,$outputlangs,$curx,$cury,$account,0,$default_font_size);

					$posy+=2;
				}
			}
		}

		return $posy;
	}


	/**
	 *	Show total to pay
	 *
	 *	@param	PDF			$pdf            Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param  int			$deja_regle     Montant deja regle
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		global $conf,$mysoc;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('','', $default_font_size - 1);

		// Tableau total
		$col1x = 120; $col2x = 170;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x-=20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder=0;
		$index = 0;

		// Total HT
		$pdf->SetFillColor(255,255,255);
		$pdf->SetXY($col1x, $tab2_top + 0);
		$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

		$total_ht = ($conf->multicurrency->enabled && $object->mylticurrency_tx != 1 ? $object->multicurrency_total_ht : $object->total_ht);
		$pdf->SetXY($col2x, $tab2_top + 0);
		$pdf->MultiCell($largcol2, $tab2_hl, price($total_ht + (! empty($object->remise)?$object->remise:0), 0, $outputlangs), 0, 'R', 1);

		// Show VAT by rates and total
		$pdf->SetFillColor(248,248,248);

		$total_ttc = ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

		$this->atleastoneratenotnull=0;
		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$tvaisnull=((! empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
			if (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL) && $tvaisnull)
			{
				// Nothing to do
			}
			else
			{
				//Local tax 1 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
					foreach( $this->localtax1 as $localtax_type => $localtax_rate )
					{
						if (in_array((string) $localtax_type, array('1','3','5'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
							if ($tvakey!=0)    // On affiche pas taux 0
							{
								//$this->atleastoneratenotnull++;

								$index++;
								$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT1",$mysoc->country_code).' ';
								$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
								$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

								$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
								$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
							}
						}
					}
	      		//}
				//Local tax 2 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
					foreach( $this->localtax2 as $localtax_type => $localtax_rate )
					{
						if (in_array((string) $localtax_type, array('1','3','5'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
							if ($tvakey!=0)    // On affiche pas taux 0
							{
								//$this->atleastoneratenotnull++;



								$index++;
								$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code).' ';
								$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
								$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

								$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
								$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);

							}
						}
					}
				//}
				// VAT
				foreach($this->tva as $tvakey => $tvaval)
				{
					if ($tvakey != 0)    // On affiche pas taux 0
					{
						$this->atleastoneratenotnull++;

						$index++;
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

						$tvacompl='';
						if (preg_match('/\*/',$tvakey))
						{
							$tvakey=str_replace('*','',$tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat =$outputlangs->transcountrynoentities("TotalVAT",$mysoc->country_code).' ';
						$totalvat.=vatrate($tvakey,1).$tvacompl;
						$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
					}
				}

				//Local tax 1 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
					foreach( $this->localtax1 as $localtax_type => $localtax_rate )
					{
						if (in_array((string) $localtax_type, array('2','4','6'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
							if ($tvakey != 0)    // On affiche pas taux 0
							{
								//$this->atleastoneratenotnull++;

								$index++;
								$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT1",$mysoc->country_code).' ';

								$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
								$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);
								$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
								$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
							}
						}
					}
	      		//}
				//Local tax 2 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
					foreach( $this->localtax2 as $localtax_type => $localtax_rate )
					{
						if (in_array((string) $localtax_type, array('2','4','6'))) continue;

						foreach( $localtax_rate as $tvakey => $tvaval )
						{
						    // retrieve global local tax
							if ($tvakey != 0)    // On affiche pas taux 0
							{
								//$this->atleastoneratenotnull++;

								$index++;
								$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transcountrynoentities("TotalLT2",$mysoc->country_code).' ';

								$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
								$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

								$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
								$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
							}
						}
					}
				//}

				// Total TTC
				$index++;
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->SetTextColor(0,0,60);
				$pdf->SetFillColor(224,224,224);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), $useborder, 'L', 1);

				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($total_ttc, 0, $outputlangs), $useborder, 'R', 1);
			}
		}

		$pdf->SetTextColor(0,0,0);

		/*
		$resteapayer = $object->total_ttc - $deja_regle;
		if (! empty($object->paye)) $resteapayer=0;
		*/

		if ($deja_regle > 0)
		{
			$index++;

			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("AlreadyPaid"), 0, 'L', 0);

			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle, 0, $outputlangs), 0, 'R', 0);

			/*
			if ($object->close_code == 'discount_vat')
			{
				$index++;
				$pdf->SetFillColor(255,255,255);

				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOfferedShort"), $useborder, 'L', 1);

				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc - $deja_regle, 0, $outputlangs), $useborder, 'R', 1);

				$resteapayer=0;
			}
			*/

			$index++;
			$pdf->SetTextColor(0,0,60);
			$pdf->SetFillColor(224,224,224);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L', 1);

			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer, 0, $outputlangs), $useborder, 'R', 1);

			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->SetTextColor(0,0,0);
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}

	/**
	 *   Show table for lines
	 *
	 *   @param		PDF			$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @return	void
	 */
	function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop=0, $hidebottom=0, $currency='')
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) $hidetop=-1;

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('','',$default_font_size - 2);

		if (empty($hidetop))
		{
			$titre = $outputlangs->transnoentities("AmountInCurrency",$outputlangs->transnoentitiesnoconv("Currency".$currency));
			$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top-4);
			$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);

			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			if (! empty($conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR)) $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_droite-$this->marge_gauche, 5, 'F', null, explode(',',$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR));
		}

		$pdf->SetDrawColor(128,128,128);
		$pdf->SetFont('','',$default_font_size - 1);

		// Output Rect
		$this->printRect($pdf,$this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $tab_height, $hidetop, $hidebottom);	// Rect prend une longueur en 3eme param et 4eme param

		if (empty($hidetop))
		{
			$pdf->line($this->marge_gauche, $tab_top+5, $this->page_largeur-$this->marge_droite, $tab_top+5);	// line prend une position y en 2eme param et 4eme param

			$pdf->SetXY($this->posxdesc-1, $tab_top+1);
			$pdf->MultiCell(108,2, $outputlangs->transnoentities("Designation"),'','L');
		}

		if (! empty($conf->global->MAIN_GENERATE_PROPOSALS_WITH_PICTURE))
		{
			$pdf->line($this->posxpicture-1, $tab_top, $this->posxpicture-1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				//$pdf->SetXY($this->posxpicture-1, $tab_top+1);
				//$pdf->MultiCell($this->posxtva-$this->posxpicture-1,2, $outputlangs->transnoentities("Photo"),'','C');
			}
		}

		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) && empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN))
		{
			$pdf->line($this->posxtva-1, $tab_top, $this->posxtva-1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				// Not do -3 and +3 instead of -1 -1 to have more space for text 'Sales tax'
				$pdf->SetXY($this->posxtva-3, $tab_top+1);
				$pdf->MultiCell($this->posxup-$this->posxtva+3,2, $outputlangs->transnoentities("VAT"),'','C');
			}
		}

		$pdf->line($this->posxup-1, $tab_top, $this->posxup-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posxup-1, $tab_top+1);
			$pdf->MultiCell($this->posxqty-$this->posxup-1,2, $outputlangs->transnoentities("PriceUHT"),'','C');
		}

		$pdf->line($this->posxqty-1, $tab_top, $this->posxqty-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posxqty-1, $tab_top+1);
			if($conf->global->PRODUCT_USE_UNITS)
			{
				$pdf->MultiCell($this->posxunit-$this->posxqty-1,2, $outputlangs->transnoentities("Qty"),'','C');
			}
			else
			{
				$pdf->MultiCell($this->posxdiscount-$this->posxqty-1,2, $outputlangs->transnoentities("Qty"),'','C');
			}
		}

		if($conf->global->PRODUCT_USE_UNITS) {
			$pdf->line($this->posxunit - 1, $tab_top, $this->posxunit - 1, $tab_top + $tab_height);
			if (empty($hidetop)) {
				$pdf->SetXY($this->posxunit - 1, $tab_top + 1);
				$pdf->MultiCell($this->posxdiscount - $this->posxunit - 1, 2, $outputlangs->transnoentities("Unit"), '',
					'C');
			}
		}

		$pdf->line($this->posxdiscount-1, $tab_top, $this->posxdiscount-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			if ($this->atleastonediscount)
			{
				$pdf->SetXY($this->posxdiscount-1, $tab_top+1);
				$pdf->MultiCell($this->postotalht-$this->posxdiscount+1,2, $outputlangs->transnoentities("ReductionShort"),'','C');
			}
		}
		if ($this->atleastonediscount)
		{
			$pdf->line($this->postotalht, $tab_top, $this->postotalht, $tab_top + $tab_height);
		}
		if (empty($hidetop))
		{
			$pdf->SetXY($this->postotalht-1, $tab_top+1);
			$pdf->MultiCell(30,2, $outputlangs->transnoentities("TotalHT"),'','C');
		}
	}

	/**
	 *  Show top header of page.
	 *
	 *  @param	PDF			$pdf     		Object PDF
	 *  @param  Object		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	void
	 */
	function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $conf,$langs;

		$outputlangs->load("main");
		$outputlangs->load("bills");
		$outputlangs->load("propal");
		$outputlangs->load("companies");

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);

		//  Show Draft Watermark
		if($object->statut==0 && (! empty($conf->global->PROPALE_DRAFT_WATERMARK)) )
		{
            pdf_watermark($pdf,$outputlangs,$this->page_hauteur,$this->page_largeur,'mm',$conf->global->PROPALE_DRAFT_WATERMARK);
		}

		$pdf->SetTextColor(0,0,60);
		$pdf->SetFont('','B', $default_font_size + 3);

		$posy=$this->marge_haute;
		$posx=$this->page_largeur-$this->marge_droite-100;

		$pdf->SetXY($this->marge_gauche,$posy);

		// Logo
		$logo=$conf->mycompany->dir_output.'/logos/'.$this->emetteur->logo;
		if ($this->emetteur->logo)
		{
			if (is_readable($logo))
			{
			    $height=pdf_getHeightForLogo($logo);
			    $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);	// width=0 (auto)
			}
			else
			{
				$pdf->SetTextColor(200,0,0);
				$pdf->SetFont('','B',$default_font_size - 2);
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound",$logo), 0, 'L');
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
			}
		}
		else
		{
			$text=$this->emetteur->name;
			$pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}

		$pdf->SetFont('','B',$default_font_size + 3);
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$title="analyse financière";
		$pdf->MultiCell(100, 4, $title, '', 'R');

		$pdf->SetFont('','B',$default_font_size);

		$posy+=5;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("Ref")." : " . $outputlangs->convToOutputCharset($object->ref), '', 'R');

		$posy+=1;
		$pdf->SetFont('','', $default_font_size - 2);

		if ($object->ref_client)
		{
			$posy+=4;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("RefCustomer")." : " . $outputlangs->convToOutputCharset($object->ref_client), '', 'R');
		}

		$posy+=4;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$pdf->MultiCell(100, 3, $outputlangs->transnoentities("Date")." : " . dol_print_date($object->date,"day",false,$outputlangs,true), '', 'R');

		$posy+=4;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		//$pdf->MultiCell(100, 3, $outputlangs->transnoentities("DateEndPropal")." : " . dol_print_date($object->fin_validite,"day",false,$outputlangs,true), '', 'R');

		if ($object->thirdparty->code_client)
		{
			$posy+=4;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("CustomerCode")." : " . $outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}

		// Get contact
		if (!empty($conf->global->DOC_SHOW_FIRST_SALES_REP))
		{
		    $arrayidcontact=$object->getIdContact('internal','SALESREPFOLL');
		    if (count($arrayidcontact) > 0)
		    {
		        $usertmp=new User($this->db);
		        $usertmp->fetch($arrayidcontact[0]);
                $posy+=4;
                $pdf->SetXY($posx,$posy);
		        $pdf->SetTextColor(0,0,60);
		        $pdf->MultiCell(100, 3, $langs->trans("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R');
		    }
		}

		$posy+=2;

		// Show list of linked objects
		$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, 100, 3, 'R', $default_font_size);

		if ($showaddress)
		{
			// Sender properties
			$carac_emetteur='';
		 	// Add internal contact of proposal if defined
			$arrayidcontact=$object->getIdContact('internal','SALESREPFOLL');
		 	if (count($arrayidcontact) > 0)
		 	{
		 		$object->fetch_user($arrayidcontact[0]);
		 		$labelbeforecontactname=($outputlangs->transnoentities("FromContactName")!='FromContactName'?$outputlangs->transnoentities("FromContactName"):$outputlangs->transnoentities("Name"));
		 		$carac_emetteur .= ($carac_emetteur ? "\n" : '' ).$labelbeforecontactname." ".$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs))."\n";
		 	}

		 	$carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty);

			// Show sender
			$posy=42;
		 	$posx=$this->marge_gauche;
			if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$this->page_largeur-$this->marge_droite-80;
			$hautcadre=40;

			// Show sender frame
			$pdf->SetTextColor(0,0,0);
			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posx,$posy-5);
			$pdf->MultiCell(66,5, $outputlangs->transnoentities("BillFrom").":", 0, 'L');
			$pdf->SetXY($posx,$posy);
			$pdf->SetFillColor(230,230,230);
			$pdf->MultiCell(82, $hautcadre, "", 0, 'R', 1);
			$pdf->SetTextColor(0,0,60);

			// Show sender name
			$pdf->SetXY($posx+2,$posy+3);
			$pdf->SetFont('','B', $default_font_size);
			$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			$posy=$pdf->getY();

			// Show sender information
			$pdf->SetXY($posx+2,$posy);
			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->MultiCell(80, 4, $carac_emetteur, 0, 'L');


			// If CUSTOMER contact defined, we use it
			$usecontact=false;
			$arrayidcontact=$object->getIdContact('external','CUSTOMER');
			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$object->fetch_contact($arrayidcontact[0]);
			}

			//Recipient name
			// On peut utiliser le nom de la societe du contact
			if ($usecontact && !empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name= pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$carac_client=pdf_build_address($outputlangs,$this->emetteur,$object->thirdparty,($usecontact?$object->contact:''),$usecontact,'target',$object);

			// Show recipient
			$widthrecbox=100;
			if ($this->page_largeur < 210) $widthrecbox=84;	// To work with US executive format
			$posy=42;
			$posx=$this->page_largeur-$this->marge_droite-$widthrecbox;
							  
			if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$this->marge_gauche;

			// Show recipient frame
			$pdf->SetTextColor(0,0,0);
			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posx+2,$posy-5);
			$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo").":", 0, 'L');
																							
														  
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			// Show recipient name
			$pdf->SetXY($posx+2,$posy+3);
			$pdf->SetFont('','B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 4, $carac_client_name, 0, 'L');

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->SetXY($posx+2,$posy);
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');
		}

		$pdf->SetTextColor(0,0,0);
	}

	/**
	 *   	Show footer of page. Need this->emetteur object
     *
	 *   	@param	PDF			$pdf     			PDF
	 * 		@param	Object		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	function _pagefoot(&$pdf,$object,$outputlangs,$hidefreetext=0)
	{
		global $conf;
		$showdetails=$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		return pdf_pagefoot($pdf,$outputlangs,'PROPOSAL_FREE_TEXT',$this->emetteur,$this->marge_basse,$this->marge_gauche,$this->page_hauteur,$object,$showdetails,$hidefreetext);
	}

	/**
	 *	Show area for the customer to sign
	 *
	 *	@param	PDF			$pdf            Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	function _signature_area(&$pdf, $object, $posy, $outputlangs)
	{
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$tab_top = $posy + 4;
		$tab_hl = 4;
											   

		$posx = 120;
		$largcol = ($this->page_largeur - $this->marge_droite - $posx);
		$useborder=0;
		$index = 0;
		// Total HT
		$pdf->SetFillColor(255,255,255);
		$pdf->SetXY($posx, $tab_top + 0);
		$pdf->SetFont('','', $default_font_size - 2);
		$pdf->MultiCell($largcol, $tab_hl, $outputlangs->transnoentities("ProposalCustomerSignature"), 0, 'L', 1);

		$pdf->SetXY($posx, $tab_top + $tab_hl);
		$pdf->MultiCell($largcol, $tab_hl*3, '', 1, 'R');

		return ($tab_hl*7);
	}
}
